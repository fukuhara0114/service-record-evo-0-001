<?php

namespace App\Http\Controllers;

use App\Models\AttachedLoaner;
use App\Models\AttachedFile;
use App\Models\AttachedNote;
use App\Models\Dealer;
use App\Models\LoanerMaster;
use App\Models\ServiceRecord;
use App\Models\StatusLoaner;
use App\Services\MasterPriceVersionResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class LoanerRecordController extends Controller
{
    public function create()
    {
        $statusColumn = $this->resolveStatusColumn();

        $loaners = app(MasterPriceVersionResolver::class)->latestByKey(
            LoanerMaster::query()
                ->whereNotNull('loanerID')
                ->where('loanerID', '!=', '')
                ->whereNotNull('productName')
                ->where('productName', '!=', '')
                ->select([
                    'id',
                    'loanerID',
                    'productName',
                    'SN',
                    'manageNum',
                    'item',
                    'groupName',
                    'validDateMin',
                    'validDateMax',
                    $statusColumn,
                ]),
            'loanerID'
        )->sortBy([
            ['productName', 'asc'],
            ['loanerID', 'asc'],
        ])->values();

        $loanerProducts = $loaners
            ->groupBy('productName')
            ->map(function ($rows, $productName) use ($statusColumn) {
                $availableCount = $rows
                    ->filter(fn ($row) => (int) ($row->{$statusColumn} ?? -1) === 0)
                    ->count();

                return [
                    'productName' => $productName,
                    'totalCount' => $rows->count(),
                    'availableCount' => $availableCount,
                    'available' => $availableCount > 0,
                    'order_type' => $availableCount > 0 ? 'loaner' : 'waiting_list',
                ];
            })
            ->values();

        $statuses = StatusLoaner::orderBy('processID_new')->get(['processID_new', 'status']);
        $dealers = Dealer::orderBy('dealerName')->get();
        $unregisteredStatus = $this->resolveUnregisteredStatus();

        return Inertia::render('ServiceRecordLoanerCreate', [
            'loanerProducts' => $loanerProducts,
            'loaners' => $loaners,
            'statuses' => $statuses,
            'dealersMaster' => $dealers,
            'unregisteredStatus' => $unregisteredStatus,
        ]);
    }

    public function availability(Request $request)
    {
        $validated = $request->validate([
            'productName' => 'required|string|max:255',
        ]);

        $available = $this->findAvailableLoaner($validated['productName']);
        $orderType = $available ? 'loaner' : 'waiting_list';

        $payload = [
            'available' => $available !== null,
            'order_type' => $orderType,
            'loaner' => $available ? [
                'loanerID' => $available->loanerID,
                'productName' => $available->productName,
                'SN' => $available->SN,
                'manageNum' => $available->manageNum,
                'item' => $available->item,
            ] : null,
        ];

        if ($orderType === 'waiting_list') {
            [$start, $end, $basedOn] = $this->resolveWaitingListDefaultPeriod($validated['productName']);
            $payload['suggestedPeriod'] = [
                'plannedSentDate' => $start,
                'plannedReturnedDate' => $end,
                'basedOnReturnedDate' => $basedOn,
            ];
        }

        return response()->json($payload);
    }

    public function detail(int $id)
    {
        $with = [
            'serviceRecord.statusMasterLoaner',
            'loanerMaster:loanerID,productName,item,SN,manageNum,groupName,price',
        ];

        $attached = AttachedLoaner::with($with)->find($id);
        if (!$attached) {
            // 一覧からは orderID で遷移するため、associatedID でも解決する
            $attached = AttachedLoaner::with($with)
                ->where('associatedID', $id)
                ->orderByDesc('id')
                ->first();
        }

        if (!$attached) {
            abort(404, '指定された貸出案件は存在しません。');
        }

        $record = $attached->serviceRecord;
        if (!$record || !in_array($record->order_type, ['loaner', 'waiting_list'], true)) {
            abort(404, '指定された貸出案件は存在しません。');
        }

        $parentReturnCode = null;
        if ($record->parentID) {
            $parentReturnCode = ServiceRecord::query()
                ->where('orderID', $record->parentID)
                ->value('returnCode');
        }

        $columns = Schema::getColumnListing('attachedloaners');

        return Inertia::render('LoanerDetail', [
            'attached' => [
                'id' => $attached->id,
                'associatedID' => $attached->associatedID,
                'loanerID' => $attached->loanerID,
                'sentDate' => optional($attached->sentDate)->format('Y-m-d'),
                'returnedDate' => optional($attached->returnedDate)->format('Y-m-d'),
                'plannedSentDate' => optional($attached->plannedSentDate)->format('Y-m-d'),
                'plannedReturnedDate' => optional($attached->plannedReturnedDate)->format('Y-m-d'),
                'assignStatus' => $attached->assignStatus ?? null,
                'comment' => $attached->comment,
            ],
            'record' => $record->only([
                'orderID',
                'parentID',
                'order_type',
                'status',
                'productName',
                'SN',
                'loanerID',
                'price',
                'discount_service',
                'quoteNum',
                'quoteDate',
                'orderNum',
                'orderDate',
                'poNum',
                'dealer',
                'dealer_depart',
                'contactPerson',
                'email',
                'phone',
                'fax',
                'zipcode',
                'address1',
                'address2',
                'deliverToEndUser',
                'endUser',
                'endUser_depart',
                'endUser_contactPerson',
                'endUser_email',
                'endUser_phone',
                'endUser_fax',
                'endUser_zipcode',
                'endUser_address1',
                'endUser_address2',
                'deliveryDestination_company',
                'deliveryDestination_depart',
                'deliveryDestination_contactPerson',
                'deliveryDestination_email',
                'deliveryDestination_phone',
                'deliveryDestination_zipcode',
                'deliveryDestination_address1',
                'deliveryDestination_address2',
            ]) + [
                'status_label' => $record->order_type === 'loaner'
                    ? $record->statusMasterLoaner?->status
                    : null,
            ],
            'parentReturnCode' => $parentReturnCode,
            'loanerMaster' => $attached->loanerMaster?->only([
                'loanerID',
                'productName',
                'item',
                'SN',
                'manageNum',
                'groupName',
                'price',
                'validDateMin',
                'validDateMax',
            ]),
            'files' => AttachedFile::query()
                ->where('associatedID', $record->orderID)
                ->select(['id', 'associatedID', 'documentType', 'documentName', 'fileType', 'sortNum'])
                ->orderByRaw('CASE WHEN sortNum IS NULL THEN 1 ELSE 0 END')
                ->orderBy('sortNum')
                ->orderBy('id')
                ->get(),
            'notes' => $this->serializeLoanerNotes(
                AttachedNote::query()
                    ->where('associatedID', $record->orderID)
                    ->orderByDesc('whenWrote')
                    ->orderByDesc('id')
                    ->get()
            ),
            'statuses' => StatusLoaner::orderBy('processID_new')->get(['processID_new', 'status']),
            'dealersMaster' => Dealer::orderBy('dealerName')->get(),
            // 価格版解決用に同一 loanerID の全版を渡す
            'loanerUnits' => LoanerMaster::query()
                ->whereNotNull('loanerID')
                ->orderBy('productName')
                ->orderBy('loanerID')
                ->orderByDesc('validDateMin')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'loanerID',
                    'productName',
                    'item',
                    'SN',
                    'manageNum',
                    'groupName',
                    'price',
                    'validDateMin',
                    'validDateMax',
                    $this->resolveStatusColumn(),
                ]),
            'dateFields' => [
                'hasPlannedSent' => in_array('plannedSentDate', $columns, true),
                'hasPlannedReturned' => in_array('plannedReturnedDate', $columns, true),
            ],
        ]);
    }

    public function updateDetail(Request $request, int $id)
    {
        $attached = AttachedLoaner::with('serviceRecord')->findOrFail($id);
        $record = $attached->serviceRecord;

        if (!$record || !in_array($record->order_type, ['loaner', 'waiting_list'], true)) {
            return response()->json(['message' => '指定された貸出案件は存在しません。'], 404);
        }

        $recordRules = [
            'parentID' => 'nullable|integer|exists:servicerecord,orderID',
            'status' => 'nullable|integer',
            'productName' => 'nullable|string|max:255',
            'SN' => 'nullable|string|max:255',
            'loanerID' => 'nullable|integer',
            'price' => 'nullable|numeric',
            'discount_service' => 'nullable|numeric',
            'quoteNum' => 'nullable|string|max:255',
            'quoteDate' => 'nullable|date',
            'orderNum' => 'nullable|string|max:255',
            'orderDate' => 'nullable|date',
            'poNum' => 'nullable|string|max:255',
            'dealer' => 'nullable|string|max:255',
            'dealer_depart' => 'nullable|string|max:255',
            'contactPerson' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'fax' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'deliverToEndUser' => 'nullable|boolean',
            'endUser' => 'nullable|string|max:255',
            'endUser_depart' => 'nullable|string|max:255',
            'endUser_contactPerson' => 'nullable|string|max:255',
            'endUser_email' => 'nullable|string|max:255',
            'endUser_phone' => 'nullable|string|max:255',
            'endUser_fax' => 'nullable|string|max:255',
            'endUser_zipcode' => 'nullable|string|max:20',
            'endUser_address1' => 'nullable|string|max:255',
            'endUser_address2' => 'nullable|string|max:255',
            'deliveryDestination_company' => 'nullable|string|max:255',
            'deliveryDestination_depart' => 'nullable|string|max:255',
            'deliveryDestination_contactPerson' => 'nullable|string|max:255',
            'deliveryDestination_email' => 'nullable|string|max:255',
            'deliveryDestination_phone' => 'nullable|string|max:255',
            'deliveryDestination_zipcode' => 'nullable|string|max:20',
            'deliveryDestination_address1' => 'nullable|string|max:255',
            'deliveryDestination_address2' => 'nullable|string|max:255',
        ];

        $attachedColumns = Schema::getColumnListing('attachedloaners');
        $attachedRules = [
            'sentDate' => 'nullable|date',
            'returnedDate' => 'nullable|date|after_or_equal:sentDate',
            'comment' => 'nullable|string|max:1000',
        ];
        if (in_array('plannedSentDate', $attachedColumns, true)) {
            $attachedRules['plannedSentDate'] = 'nullable|date';
        }
        if (in_array('plannedReturnedDate', $attachedColumns, true)) {
            $attachedRules['plannedReturnedDate'] = 'nullable|date|after_or_equal:plannedSentDate';
        }
        if (in_array('assignStatus', $attachedColumns, true)) {
            $attachedRules['assignStatus'] = 'nullable|string|max:255';
        }

        $validated = $request->validate($recordRules + $attachedRules);

        // status=0（在庫有り）は falsy 扱いで欠落しやすいため、リクエスト生値を明示反映
        if ($request->exists('status')) {
            $rawStatus = $request->input('status');
            $validated['status'] = ($rawStatus === '' || $rawStatus === null) ? null : (int) $rawStatus;
        }

        if (
            isset($validated['parentID'])
            && (int) $validated['parentID'] === (int) $record->orderID
        ) {
            return response()->json(['message' => '自分自身を親案件には指定できません。'], 422);
        }

        if ($record->order_type === 'loaner' && array_key_exists('status', $validated) && $validated['status'] !== null) {
            $statusExists = StatusLoaner::query()
                ->where('processID_new', $validated['status'])
                ->exists();
            if (!$statusExists) {
                return response()->json(['message' => '指定された status は存在しません。'], 422);
            }
        }

        if (array_key_exists('loanerID', $validated) && $validated['loanerID'] !== null) {
            $loanerExists = LoanerMaster::query()
                ->where('loanerID', $validated['loanerID'])
                ->orWhere('id', $validated['loanerID'])
                ->exists();
            if (!$loanerExists) {
                return response()->json(['message' => '指定された貸出機は存在しません。'], 422);
            }
        }

        $recordFields = array_keys($recordRules);
        $attachedFields = array_keys($attachedRules);

        $previousStatus = $record->status;
        $previousLoanerId = $record->loanerID ?? $attached->loanerID;
        $promotionTriggered = false;
        $promotionCandidates = [];

        DB::transaction(function () use (
            $record,
            $attached,
            $validated,
            $recordFields,
            $attachedFields,
            $request,
            $previousStatus,
            $previousLoanerId,
            &$promotionTriggered,
            &$promotionCandidates,
        ) {
            $recordPayload = collect($validated)->only($recordFields)->all();
            if ($record->order_type === 'waiting_list') {
                unset($recordPayload['status']);
            }

            // 価格: parent の returnCode が有償のとき loanermaster 版価格、それ以外/親なしは 0
            $parentId = array_key_exists('parentID', $validated)
                ? $validated['parentID']
                : $record->parentID;
            $loanerId = array_key_exists('loanerID', $validated)
                ? $validated['loanerID']
                : ($attached->loanerID ?? $record->loanerID);
            $orderDate = array_key_exists('orderDate', $validated)
                ? $validated['orderDate']
                : $record->orderDate;
            $recordPayload['price'] = $this->resolveLoanerChargePrice($parentId, $loanerId, $orderDate);

            $record->fill($recordPayload);
            $record->lastEditPerson = $request->user()?->kanji_name;
            $record->lastEditDate = now();
            $record->save();

            $attachedPayload = collect($validated)->only($attachedFields)->all();
            if (array_key_exists('loanerID', $validated)) {
                $attachedPayload['loanerID'] = $validated['loanerID'];
            }
            if (
                array_key_exists('productName', $validated)
                && in_array('productName', Schema::getColumnListing('attachedloaners'), true)
            ) {
                $attachedPayload['productName'] = $validated['productName'];
            }
            $attached->fill($attachedPayload);
            $attached->save();

            if ($record->order_type === 'loaner') {
                $newStatus = (int) $record->status;
                $oldStatus = (int) $previousStatus;
                $newLoanerId = $record->loanerID ?? $attached->loanerID;
                $stockStatusId = $this->resolveStockStatusId();

                // loanerID 変更時は旧個体を在庫へ戻し、新個体を貸出中へ
                if (
                    $previousLoanerId !== null && $previousLoanerId !== ''
                    && (string) $previousLoanerId !== (string) ($newLoanerId ?? '')
                ) {
                    $this->setLoanerInventoryStatus($previousLoanerId, 0);
                    if ($newLoanerId !== null && $newLoanerId !== '' && $newStatus !== $stockStatusId) {
                        $this->setLoanerInventoryStatus($newLoanerId, 1);
                    }
                }

                // 案件 status が「在庫有り」へ変わったタイミングで返却処理
                if ($newStatus === $stockStatusId && $oldStatus !== $stockStatusId) {
                    if ($newLoanerId !== null && $newLoanerId !== '') {
                        $this->setLoanerInventoryStatus($newLoanerId, 0);
                    }
                    $promotionTriggered = true;
                    $promotionCandidates = $this->markPromotionCandidatesForReturnedLoaner($record);
                } elseif ($oldStatus === $stockStatusId && $newStatus !== $stockStatusId) {
                    if ($newLoanerId !== null && $newLoanerId !== '') {
                        $this->setLoanerInventoryStatus($newLoanerId, 1);
                    }
                }
            }
        });

        $attached->refresh();
        $record->refresh();

        return response()->json([
            'message' => '貸出詳細を保存しました。',
            'record' => $record->only([
                'orderID',
                'parentID',
                'status',
                'productName',
                'SN',
                'loanerID',
                'price',
                'discount_service',
                'quoteNum',
                'quoteDate',
                'orderNum',
                'orderDate',
                'poNum',
            ]),
            'attached' => [
                'id' => $attached->id,
                'loanerID' => $attached->loanerID,
                'sentDate' => optional($attached->sentDate)->format('Y-m-d'),
                'returnedDate' => optional($attached->returnedDate)->format('Y-m-d'),
                'plannedSentDate' => optional($attached->plannedSentDate)->format('Y-m-d'),
                'plannedReturnedDate' => optional($attached->plannedReturnedDate)->format('Y-m-d'),
                'assignStatus' => $attached->assignStatus ?? null,
                'comment' => $attached->comment,
            ],
            'promotionTriggered' => $promotionTriggered,
            'promotionCandidates' => $promotionCandidates,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'productName' => 'required|string|max:255',
            'receivedDate' => 'nullable|date',
            'status' => 'nullable|integer',
            'returnCode' => 'nullable|integer',
            'SN' => 'nullable|string|max:255',
            'loanerID' => 'nullable|integer',
            'linkMode' => 'required|in:none,parent',
            'parentID' => 'nullable|integer',
            'plannedSentDate' => 'nullable|date',
            'plannedReturnedDate' => 'nullable|date|after_or_equal:plannedSentDate',
            'dealer' => 'nullable|string|max:255',
            'dealer_depart' => 'nullable|string|max:255',
            'contactPerson' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'endUser' => 'nullable|string|max:255',
            'endUser_depart' => 'nullable|string|max:255',
            'endUser_contactPerson' => 'nullable|string|max:255',
            'endUser_phone' => 'nullable|string|max:255',
            'endUser_email' => 'nullable|string|max:255',
            'endUser_zipcode' => 'nullable|string|max:20',
            'endUser_address1' => 'nullable|string|max:255',
            'endUser_address2' => 'nullable|string|max:255',
            'deliveryDestination_company' => 'nullable|string|max:255',
            'deliveryDestination_depart' => 'nullable|string|max:255',
            'deliveryDestination_contactPerson' => 'nullable|string|max:255',
            'deliveryDestination_phone' => 'nullable|string|max:255',
            'deliveryDestination_zipcode' => 'nullable|string|max:20',
            'deliveryDestination_address1' => 'nullable|string|max:255',
            'deliveryDestination_address2' => 'nullable|string|max:255',
            'sourceFileId' => 'nullable|integer',
            'additionalFileIds' => 'nullable|array',
            'additionalFileIds.*' => 'integer',
        ]);

        $available = $this->findAvailableLoaner(
            $validated['productName'],
            isset($validated['loanerID']) ? (int) $validated['loanerID'] : null,
        );
        if (!empty($validated['loanerID']) && !$available) {
            return response()->json([
                'message' => '指定した貸出機は在庫として選択できません。一覧を更新してやり直してください。',
            ], 422);
        }
        $orderType = $available ? 'loaner' : 'waiting_list';
        $user = $request->user();
        $linkMode = $validated['linkMode'];
        $parentId = null;

        $fileIds = collect()
            ->when(!empty($validated['sourceFileId']), fn ($c) => $c->push((int) $validated['sourceFileId']))
            ->merge($validated['additionalFileIds'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($fileIds->isNotEmpty()) {
            $files = AttachedFile::query()
                ->whereIn('id', $fileIds)
                ->where('associatedID', -1)
                ->get();

            if ($files->count() !== $fileIds->count()) {
                return response()->json([
                    'message' => '未登録ファイルの状態が変わったため、画面を再読み込みしてやり直してください。',
                ], 422);
            }
        }

        if ($linkMode === 'parent') {
            if (empty($validated['parentID'])) {
                return response()->json([
                    'message' => '既存案件を選択してください。',
                ], 422);
            }

            $parent = ServiceRecord::where('orderID', $validated['parentID'])->first();
            if (!$parent) {
                return response()->json([
                    'message' => '指定された既存案件は存在しません。',
                ], 404);
            }

            $parentOrderType = $parent->order_type;
            if ($parentOrderType !== null && $parentOrderType !== '' && $parentOrderType !== 'service') {
                return response()->json([
                    'message' => '紐づけ先は service 案件を選択してください。',
                ], 422);
            }

            $parentId = (int) $parent->orderID;
        }

        $status = -1; // waiting_list は status リレーションなし。NOT NULL 制約のため -1 固定
        if ($orderType === 'loaner') {
            if ($linkMode === 'none') {
                $status = $this->resolveUnregisteredStatus()?->processID_new;
                if ($status === null) {
                    return response()->json([
                        'message' => 'statusmaster_loaner に「未登録」ステータスが見つかりません。',
                    ], 422);
                }
            } else {
                $status = $validated['status'] ?? null;
            }
        }

        $attachedLoanerId = null;

        $record = DB::transaction(function () use (
            $validated,
            $available,
            $orderType,
            $status,
            $user,
            $parentId,
            $fileIds,
            &$attachedLoanerId,
        ) {
            $record = ServiceRecord::create([
                'receivedDate' => null,
                'status' => $status,
                'returnCode' => null,
                'RMA' => $orderType === 'loaner' ? 'loaner' : null,
                'productName' => $available?->productName ?? $validated['productName'],
                'SN' => $available?->SN ?? ($validated['SN'] ?? null),
                'loanerID' => $available?->loanerID,
                'order_type' => $orderType,
                'parentID' => $parentId,
                'dealer' => $validated['dealer'] ?? null,
                'dealer_depart' => $validated['dealer_depart'] ?? null,
                'contactPerson' => $validated['contactPerson'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'zipcode' => $validated['zipcode'] ?? null,
                'address1' => $validated['address1'] ?? null,
                'address2' => $validated['address2'] ?? null,
                'endUser' => $validated['endUser'] ?? null,
                'endUser_depart' => $validated['endUser_depart'] ?? null,
                'endUser_contactPerson' => $validated['endUser_contactPerson'] ?? null,
                'endUser_phone' => $validated['endUser_phone'] ?? null,
                'endUser_email' => $validated['endUser_email'] ?? null,
                'endUser_zipcode' => $validated['endUser_zipcode'] ?? null,
                'endUser_address1' => $validated['endUser_address1'] ?? null,
                'endUser_address2' => $validated['endUser_address2'] ?? null,
                'deliveryDestination_company' => $validated['deliveryDestination_company'] ?? null,
                'deliveryDestination_depart' => $validated['deliveryDestination_depart'] ?? null,
                'deliveryDestination_contactPerson' => $validated['deliveryDestination_contactPerson'] ?? null,
                'deliveryDestination_phone' => $validated['deliveryDestination_phone'] ?? null,
                'deliveryDestination_zipcode' => $validated['deliveryDestination_zipcode'] ?? null,
                'deliveryDestination_address1' => $validated['deliveryDestination_address1'] ?? null,
                'deliveryDestination_address2' => $validated['deliveryDestination_address2'] ?? null,
                'lastEditPerson' => $user?->kanji_name,
                'lastEditDate' => now(),
            ]);

            $attached = $this->createAttachedLoanerReservation(
                $record,
                $available,
                $orderType,
                $validated['productName'],
                $validated['plannedSentDate'] ?? null,
                $validated['plannedReturnedDate'] ?? null,
            );
            $attachedLoanerId = $attached?->id;

            // 貸出登録時は在庫ステータスを「貸出中」へ（waiting_list は個体未確定のため触らない）
            if ($orderType === 'loaner' && $available?->loanerID !== null && $available?->loanerID !== '') {
                $this->setLoanerInventoryStatus($available->loanerID, 1);
            }

            if ($fileIds->isNotEmpty()) {
                AttachedFile::query()
                    ->whereIn('id', $fileIds)
                    ->where('associatedID', -1)
                    ->update(['associatedID' => $record->orderID]);
            }

            return $record;
        });

        $freshRelations = $orderType === 'loaner'
            ? ['returnCodeMaster', 'statusMasterLoaner']
            : ['returnCodeMaster'];

        return response()->json([
            'message' => $orderType === 'loaner'
                ? '貸出機案件を登録しました。'
                : '待機リスト案件を登録しました。',
            'record' => $record->fresh($freshRelations),
            'order_type' => $orderType,
            'attachedLoanerId' => $attachedLoanerId,
            'parentID' => $parentId,
        ], 201);
    }

    public function editPeriod(int $id)
    {
        $attached = AttachedLoaner::with([
            'serviceRecord',
            'loanerMaster:loanerID,productName,item,SN',
        ])->findOrFail($id);

        $columns = Schema::getColumnListing('attachedloaners');
        $hasPlannedSent = in_array('plannedSentDate', $columns, true);
        $hasPlannedReturned = in_array('plannedReturnedDate', $columns, true);

        $parent = null;
        if ($attached->serviceRecord?->parentID) {
            $parent = ServiceRecord::query()
                ->where('orderID', $attached->serviceRecord->parentID)
                ->first(['orderID', 'productName', 'SN', 'dealer', 'contactPerson', 'order_type']);
        }

        $productName = $attached->productName
            ?? $attached->loanerMaster?->productName
            ?? $attached->serviceRecord?->productName;

        $productLoanSchedule = null;
        if ($attached->serviceRecord?->order_type === 'waiting_list' && $productName) {
            $productLoanSchedule = $this->buildProductLoanSchedule(
                $productName,
                (int) $attached->id,
            );
        }

        return Inertia::render('LoanerPeriodEdit', [
            'attached' => [
                'id' => $attached->id,
                'associatedID' => $attached->associatedID,
                'loanerID' => $attached->loanerID,
                'sentDate' => optional($attached->sentDate)->format('Y-m-d'),
                'returnedDate' => optional($attached->returnedDate)->format('Y-m-d'),
                'plannedSentDate' => optional($attached->plannedSentDate)->format('Y-m-d'),
                'plannedReturnedDate' => optional($attached->plannedReturnedDate)->format('Y-m-d'),
                'assignStatus' => $attached->assignStatus ?? null,
                'comment' => $attached->comment,
                'productName' => $productName,
                'item' => $attached->loanerMaster?->item,
                'SN' => $attached->loanerMaster?->SN ?? $attached->serviceRecord?->SN,
                'order_type' => $attached->serviceRecord?->order_type,
                'dealer' => $attached->serviceRecord?->dealer,
                'dealer_depart' => $attached->serviceRecord?->dealer_depart,
                'contactPerson' => $attached->serviceRecord?->contactPerson,
                'parentID' => $attached->serviceRecord?->parentID,
                'status' => $attached->serviceRecord?->status,
            ],
            'parentRecord' => $parent,
            'productLoanSchedule' => $productLoanSchedule,
            'statuses' => StatusLoaner::orderBy('processID_new')->get(['processID_new', 'status']),
            'dateFields' => [
                'hasPlannedSent' => $hasPlannedSent,
                'hasPlannedReturned' => $hasPlannedReturned,
            ],
        ]);
    }

    public function linkParent(Request $request, int $id)
    {
        $validated = $request->validate([
            'parentID' => 'required|integer',
            'status' => 'nullable|integer',
        ]);

        $attached = AttachedLoaner::with('serviceRecord')->findOrFail($id);
        $record = $attached->serviceRecord;

        if (!$record) {
            return response()->json([
                'message' => '紐づく貸出案件（servicerecord）が見つかりません。',
            ], 404);
        }

        if (!in_array($record->order_type, ['loaner', 'waiting_list'], true)) {
            return response()->json([
                'message' => '貸出案件以外には親案件を紐づけできません。',
            ], 422);
        }

        if ($record->parentID) {
            return response()->json([
                'message' => '既に親案件が紐づいています。',
            ], 422);
        }

        $parent = ServiceRecord::where('orderID', $validated['parentID'])->first();
        if (!$parent) {
            return response()->json([
                'message' => '指定された既存案件は存在しません。',
            ], 404);
        }

        $parentOrderType = $parent->order_type;
        if ($parentOrderType !== null && $parentOrderType !== '' && $parentOrderType !== 'service') {
            return response()->json([
                'message' => '紐づけ先は service 案件を選択してください。',
            ], 422);
        }

        $update = [
            'parentID' => (int) $parent->orderID,
            'lastEditPerson' => $request->user()?->kanji_name,
            'lastEditDate' => now(),
        ];

        if ($record->order_type === 'loaner' && array_key_exists('status', $validated) && $validated['status'] !== null) {
            $update['status'] = $validated['status'];
        }

        $record->fill($update);
        $record->save();

        return response()->json([
            'message' => 'service 案件に紐づけました。',
            'parentID' => $record->parentID,
            'status' => $record->status,
            'parentRecord' => [
                'orderID' => $parent->orderID,
                'productName' => $parent->productName,
                'SN' => $parent->SN,
                'dealer' => $parent->dealer,
                'contactPerson' => $parent->contactPerson,
                'order_type' => $parent->order_type,
            ],
        ]);
    }

    public function updatePeriod(Request $request, int $id)
    {
        $attached = AttachedLoaner::with('serviceRecord')->findOrFail($id);
        $columns = Schema::getColumnListing('attachedloaners');
        $hasPlannedSent = in_array('plannedSentDate', $columns, true);
        $hasPlannedReturned = in_array('plannedReturnedDate', $columns, true);
        $record = $attached->serviceRecord;
        $isLoaner = $record?->order_type === 'loaner';

        $rules = [
            'sentDate' => 'nullable|date',
            'returnedDate' => 'nullable|date|after_or_equal:sentDate',
            'comment' => 'nullable|string|max:1000',
            'status' => 'nullable|integer',
        ];

        if ($hasPlannedSent) {
            $rules['plannedSentDate'] = 'nullable|date';
        }
        if ($hasPlannedReturned) {
            $rules['plannedReturnedDate'] = 'nullable|date|after_or_equal:plannedSentDate';
        }

        $validated = $request->validate($rules);

        if ($isLoaner && array_key_exists('status', $validated) && $validated['status'] !== null) {
            $statusExists = StatusLoaner::query()
                ->where('processID_new', $validated['status'])
                ->exists();
            if (!$statusExists) {
                return response()->json([
                    'message' => '指定された status は statusmaster_loaner に存在しません。',
                ], 422);
            }
        }

        $payload = [
            'sentDate' => $validated['sentDate'] ?? null,
            'returnedDate' => $validated['returnedDate'] ?? null,
        ];

        if ($hasPlannedSent) {
            $payload['plannedSentDate'] = $validated['plannedSentDate'] ?? null;
        }
        if ($hasPlannedReturned) {
            $payload['plannedReturnedDate'] = $validated['plannedReturnedDate'] ?? null;
        }
        if (array_key_exists('comment', $validated) && in_array('comment', $columns, true)) {
            $payload['comment'] = $validated['comment'];
        }

        DB::transaction(function () use ($attached, $payload, $record, $isLoaner, $validated, $request) {
            $attached->fill($payload);
            $attached->save();

            if ($record && $isLoaner && array_key_exists('status', $validated)) {
                $record->status = $validated['status'];
                $record->lastEditPerson = $request->user()?->kanji_name;
                $record->lastEditDate = now();
                $record->save();
            }
        });

        $attached->refresh();
        $record?->refresh();

        return response()->json([
            'message' => '貸出期間を更新しました。',
            'attached' => [
                'id' => $attached->id,
                'sentDate' => optional($attached->sentDate)->format('Y-m-d'),
                'returnedDate' => optional($attached->returnedDate)->format('Y-m-d'),
                'plannedSentDate' => optional($attached->plannedSentDate)->format('Y-m-d'),
                'plannedReturnedDate' => optional($attached->plannedReturnedDate)->format('Y-m-d'),
                'comment' => $attached->comment,
                'status' => $record?->status,
            ],
        ]);
    }

    private function createAttachedLoanerReservation(
        ServiceRecord $record,
        ?LoanerMaster $available,
        string $orderType,
        string $requestedProductName,
        ?string $plannedSentDate = null,
        ?string $plannedReturnedDate = null,
    ): ?AttachedLoaner {
        $loanerId = $available?->loanerID;

        // waiting_list で個体未定でも loanerID が必須な場合は、同機種の代表個体を仮紐づけ
        if ($loanerId == null) {
            $fallback = LoanerMaster::query()
                ->where('productName', $requestedProductName)
                ->orderBy('loanerID')
                ->orderBy('id')
                ->first();
            // loanerID が空のマスタもあるため id をフォールバックに使う
            $loanerId = $fallback?->loanerID ?? $fallback?->id;
        }

        if ($loanerId == null && $available) {
            $loanerId = $available->id;
        }

        if ($loanerId == null) {
            return null;
        }

        if ($orderType === 'waiting_list') {
            // waiting_list は同機種の現在貸出終了翌日から（未指定時は自動計算）
            if (!$plannedSentDate || !$plannedReturnedDate) {
                [$autoStart, $autoEnd] = $this->resolveWaitingListDefaultPeriod($requestedProductName);
                $start = $plannedSentDate ?: $autoStart;
                $end = $plannedReturnedDate ?: $autoEnd;
            } else {
                $start = $plannedSentDate;
                $end = $plannedReturnedDate;
            }
        } else {
            $start = $plannedSentDate ?: now()->toDateString();
            $end = $plannedReturnedDate ?: Carbon::parse($start)->addDays(7)->toDateString();
        }

        $payload = [
            'associatedID' => $record->orderID,
            'loanerID' => $loanerId,
            'sentDate' => $start,
            'returnedDate' => $end,
            'comment' => $orderType === 'waiting_list'
                ? 'waiting_list reservation'
                : 'loaner reservation',
        ];

        $columns = Schema::getColumnListing('attachedloaners');

        if (in_array('plannedSentDate', $columns, true)) {
            $payload['plannedSentDate'] = $start;
        }
        if (in_array('plannedReturnedDate', $columns, true)) {
            $payload['plannedReturnedDate'] = $end;
        }
        if (in_array('assignStatus', $columns, true)) {
            $payload['assignStatus'] = $orderType === 'waiting_list' ? 'waiting' : 'reserved';
        }
        if (in_array('productName', $columns, true)) {
            $payload['productName'] = $available?->productName ?? $requestedProductName;
        }

        return AttachedLoaner::create($payload);
    }

    /**
     * waiting_list の初期期間:
     * 同機種の各貸出機について「現在以降の予約終了日」を取り、
     * 最も早く空く個体の終了翌日を開始日とする（期間は7日）。
     *
     * @return array{0:string,1:string,2:?string} [start, end, basedOnReturnedDate]
     */
    private function resolveWaitingListDefaultPeriod(string $productName): array
    {
        $today = Carbon::today();
        $masters = LoanerMaster::query()
            ->where('productName', $productName)
            ->get(['id', 'loanerID']);

        $unitFreeDates = [];

        foreach ($masters as $master) {
            $ids = array_values(array_filter([$master->loanerID, $master->id], fn ($v) => $v !== null && $v !== ''));
            if ($ids === []) {
                continue;
            }

            $rows = AttachedLoaner::query()
                ->whereIn('loanerID', $ids)
                ->get(['sentDate', 'returnedDate', 'plannedSentDate', 'plannedReturnedDate']);

            $latestEnd = null;
            foreach ($rows as $row) {
                $end = $this->resolveAttachedEndDate($row);
                if (!$end) {
                    continue;
                }
                if ($end->lt($today)) {
                    continue;
                }
                if ($latestEnd === null || $end->gt($latestEnd)) {
                    $latestEnd = $end->copy();
                }
            }

            // その個体が今空いていれば今日から、貸出中なら終了翌日
            $unitFreeDates[] = $latestEnd
                ? $latestEnd->copy()->addDay()
                : $today->copy();
        }

        // productName のみ紐づく予約（loanerID が曖昧な行）も考慮
        $productRows = AttachedLoaner::query()
            ->where('productName', $productName)
            ->when($masters->isNotEmpty(), function ($q) use ($masters) {
                $ids = $masters->flatMap(fn ($m) => array_filter([$m->loanerID, $m->id]))->unique()->values()->all();
                if ($ids !== []) {
                    $q->where(function ($inner) use ($ids) {
                        $inner->whereNull('loanerID')
                            ->orWhereNotIn('loanerID', $ids);
                    });
                }
            })
            ->get(['sentDate', 'returnedDate', 'plannedSentDate', 'plannedReturnedDate']);

        $productLatestEnd = null;
        foreach ($productRows as $row) {
            $end = $this->resolveAttachedEndDate($row);
            if (!$end || $end->lt($today)) {
                continue;
            }
            if ($productLatestEnd === null || $end->gt($productLatestEnd)) {
                $productLatestEnd = $end->copy();
            }
        }
        if ($productLatestEnd) {
            $unitFreeDates[] = $productLatestEnd->copy()->addDay();
        }

        if ($unitFreeDates === []) {
            $start = $today->copy();
            $basedOn = null;
        } else {
            // 最も早く空くタイミング（MIN）
            $start = collect($unitFreeDates)->sortBy(fn (Carbon $d) => $d->timestamp)->first()->copy();
            if ($start->lt($today)) {
                $start = $today->copy();
            }
            $basedOn = $start->equalTo($today)
                ? null
                : $start->copy()->subDay()->toDateString();
        }

        $end = $start->copy()->addDays(7);

        return [
            $start->toDateString(),
            $end->toDateString(),
            $basedOn,
        ];
    }

    /**
     * waiting_list 編集画面用: 同機種の現行貸出終了予定一覧
     */
    private function buildProductLoanSchedule(string $productName, ?int $excludeAttachedId = null): array
    {
        $today = Carbon::today();
        $masters = LoanerMaster::query()
            ->where('productName', $productName)
            ->get(['id', 'loanerID', 'SN', 'item']);

        $loanerIds = $masters
            ->flatMap(fn ($m) => array_filter([$m->loanerID, $m->id]))
            ->unique()
            ->values()
            ->all();

        $query = AttachedLoaner::query()
            ->with(['serviceRecord:orderID,order_type,dealer,status,productName'])
            ->when($excludeAttachedId, fn ($q) => $q->where('id', '!=', $excludeAttachedId))
            ->where(function ($q) use ($productName, $loanerIds) {
                $q->where('productName', $productName);
                if ($loanerIds !== []) {
                    $q->orWhereIn('loanerID', $loanerIds);
                }
            });

        $rows = $query
            ->orderByRaw('COALESCE(plannedReturnedDate, returnedDate) asc')
            ->get();

        $items = [];

        foreach ($rows as $row) {
            $end = $this->resolveAttachedEndDate($row);
            if (!$end || $end->lt($today)) {
                continue;
            }

            $orderType = $row->serviceRecord?->order_type;
            $master = $masters->first(function ($m) use ($row) {
                return (string) $m->loanerID === (string) $row->loanerID
                    || (string) $m->id === (string) $row->loanerID;
            });

            $items[] = [
                'attachedId' => $row->id,
                'associatedID' => $row->associatedID,
                'loanerID' => $row->loanerID,
                'SN' => $master?->SN,
                'item' => $master?->item,
                'order_type' => $orderType,
                'dealer' => $row->serviceRecord?->dealer,
                'endDate' => $end->toDateString(),
                'startDate' => optional($row->plannedSentDate ?? $row->sentDate)->format('Y-m-d'),
            ];
        }

        usort($items, fn ($a, $b) => strcmp($a['endDate'], $b['endDate']));

        $loanerEnds = array_values(array_filter($items, fn ($i) => $i['order_type'] === 'loaner'));
        $earliestLoanerEnd = $loanerEnds[0]['endDate'] ?? ($items[0]['endDate'] ?? null);
        $latestLoanerEnd = $loanerEnds !== []
            ? $loanerEnds[array_key_last($loanerEnds)]['endDate']
            : ($items !== [] ? $items[array_key_last($items)]['endDate'] : null);

        [$suggestedStart, $suggestedEnd, $basedOn] = $this->resolveWaitingListDefaultPeriod($productName);

        return [
            'productName' => $productName,
            'earliestEndDate' => $earliestLoanerEnd,
            'latestEndDate' => $latestLoanerEnd,
            'suggestedStartDate' => $suggestedStart,
            'suggestedEndDate' => $suggestedEnd,
            'basedOnReturnedDate' => $basedOn,
            'items' => $items,
        ];
    }

    private function resolveAttachedEndDate(AttachedLoaner $row): ?Carbon
    {
        $raw = $row->getAttribute('plannedReturnedDate')
            ?? $row->getAttribute('returnedDate')
            ?? null;

        if (!$raw) {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function findAvailableLoaner(string $productName, ?int $loanerId = null): ?LoanerMaster
    {
        $statusColumn = $this->resolveStatusColumn();

        $latest = app(MasterPriceVersionResolver::class)->latestByKey(
            LoanerMaster::query()
                ->whereNotNull('loanerID')
                ->where('loanerID', '!=', '')
                ->where('productName', $productName),
            'loanerID'
        );

        if ($loanerId !== null) {
            $selected = $latest->first(function (LoanerMaster $row) use ($loanerId) {
                return (int) $row->loanerID === $loanerId;
            });

            if (!$selected) {
                return null;
            }

            return (int) ($selected->{$statusColumn} ?? -1) === 0 ? $selected : null;
        }

        return $latest->first(function (LoanerMaster $row) use ($statusColumn) {
            return (int) ($row->{$statusColumn} ?? -1) === 0;
        });
    }

    /**
     * loanermaster.currentStatus を全版へ反映する。
     * 0 = 在庫あり / 1 = 貸出中など在庫なし
     */
    private function setLoanerInventoryStatus(mixed $loanerId, int $status): void
    {
        if ($loanerId === null || $loanerId === '') {
            return;
        }

        $statusColumn = $this->resolveStatusColumn();
        if (!Schema::hasColumn('loanermaster', $statusColumn)) {
            return;
        }

        // 共有項目同期（全版）
        LoanerMaster::syncSharedFieldsAcrossVersions($loanerId, [
            $statusColumn => $status,
        ]);

        // sync 対象外だった場合（列名不一致など）のフォールバック
        if ($statusColumn === 'currentStatus') {
            return;
        }

        LoanerMaster::query()
            ->where(function ($query) use ($loanerId) {
                $query->where('loanerID', $loanerId)
                    ->orWhere('id', $loanerId);
            })
            ->update([$statusColumn => $status]);
    }

    /**
     * statusmaster_loaner の「在庫有り」processID_new。
     * 返却完了（在庫復帰）の判定に使う。
     */
    private function resolveStockStatusId(): int
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $row = StatusLoaner::query()
            ->select(['processID_new', 'status'])
            ->where('processID_new', 0)
            ->first();

        if ($row) {
            return $cached = (int) $row->processID_new;
        }

        $byName = StatusLoaner::query()
            ->select(['processID_new', 'status'])
            ->where('status', 'like', '%在庫%')
            ->orderBy('processID_new')
            ->first();

        return $cached = (int) ($byName?->processID_new ?? 0);
    }

    private function resolveUnregisteredStatus(): ?StatusLoaner
    {
        // processID_new=20 は「案件未登録」（新規登録時の初期 status）。
        $provisional = StatusLoaner::query()
            ->select(['processID_new', 'status'])
            ->where('processID_new', 20)
            ->first();
        if ($provisional) {
            return $provisional;
        }

        return StatusLoaner::query()
            ->select(['processID_new', 'status'])
            ->where('status', 'like', '%未登録%')
            ->orderBy('processID_new')
            ->first();
    }

    /**
     * 返却（status=在庫有り）時に同 productName の waiting_list を繰り上がり候補としてマークする。
     *
     * @return array<int, array<string, mixed>>
     */
    private function markPromotionCandidatesForReturnedLoaner(ServiceRecord $returned): array
    {
        $productName = trim((string) ($returned->productName ?? ''));
        if ($productName === '') {
            return [];
        }

        $hasPromotionReadyAt = Schema::hasColumn('servicerecord', 'promotion_ready_at');
        $hasPromotionSource = Schema::hasColumn('servicerecord', 'promotion_source_orderID');
        if (!$hasPromotionReadyAt && !$hasPromotionSource) {
            return $this->serializePromotionCandidates(
                $this->findWaitingListCandidatesByProductName($productName),
            );
        }

        $candidates = $this->findWaitingListCandidatesByProductName($productName);
        if ($candidates->isEmpty()) {
            return [];
        }

        $now = now();
        $sourceOrderId = (int) $returned->orderID;

        foreach ($candidates as $candidate) {
            $payload = [];
            if ($hasPromotionReadyAt) {
                $payload['promotion_ready_at'] = $now;
            }
            if ($hasPromotionSource) {
                $payload['promotion_source_orderID'] = $sourceOrderId;
            }
            if ($payload !== []) {
                $candidate->fill($payload);
                $candidate->save();
            }
        }

        return $this->serializePromotionCandidates($candidates);
    }

    /**
     * @return \Illuminate\Support\Collection<int, ServiceRecord>
     */
    private function findWaitingListCandidatesByProductName(string $productName)
    {
        $attachedColumns = Schema::getColumnListing('attachedloaners');
        $hasPlannedSent = in_array('plannedSentDate', $attachedColumns, true);

        $query = ServiceRecord::query()
            ->where('order_type', 'waiting_list')
            ->where('productName', $productName)
            ->orderBy('orderID');

        $records = $query->get();
        if ($records->isEmpty()) {
            return $records;
        }

        $orderIds = $records->pluck('orderID')->all();
        $attachedByOrder = AttachedLoaner::query()
            ->whereIn('associatedID', $orderIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('associatedID')
            ->map(fn ($rows) => $rows->first());

        return $records
            ->sortBy(function (ServiceRecord $record) use ($attachedByOrder, $hasPlannedSent) {
                $attached = $attachedByOrder->get($record->orderID);
                $plannedStart = null;
                if ($attached) {
                    $plannedStart = $hasPlannedSent
                        ? ($attached->plannedSentDate ?? $attached->sentDate)
                        : $attached->sentDate;
                }
                $startKey = $plannedStart
                    ? Carbon::parse($plannedStart)->format('Y-m-d')
                    : '9999-12-31';

                return sprintf('%s-%010d', $startKey, (int) $record->orderID);
            })
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ServiceRecord>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function serializePromotionCandidates($candidates): array
    {
        if ($candidates->isEmpty()) {
            return [];
        }

        $attachedColumns = Schema::getColumnListing('attachedloaners');
        $hasPlannedSent = in_array('plannedSentDate', $attachedColumns, true);
        $hasPlannedReturned = in_array('plannedReturnedDate', $attachedColumns, true);

        $attachedByOrder = AttachedLoaner::query()
            ->whereIn('associatedID', $candidates->pluck('orderID')->all())
            ->orderByDesc('id')
            ->get()
            ->groupBy('associatedID')
            ->map(fn ($rows) => $rows->first());

        return $candidates->map(function (ServiceRecord $record) use (
            $attachedByOrder,
            $hasPlannedSent,
            $hasPlannedReturned,
        ) {
            $attached = $attachedByOrder->get($record->orderID);
            $plannedSent = null;
            $plannedReturned = null;
            if ($attached) {
                $plannedSent = $hasPlannedSent
                    ? ($attached->plannedSentDate ?? $attached->sentDate)
                    : $attached->sentDate;
                $plannedReturned = $hasPlannedReturned
                    ? ($attached->plannedReturnedDate ?? $attached->returnedDate)
                    : $attached->returnedDate;
            }

            return [
                'orderID' => $record->orderID,
                'parentID' => $record->parentID,
                'dealer' => $record->dealer,
                'contactPerson' => $record->contactPerson,
                'productName' => $record->productName,
                'plannedSentDate' => $plannedSent
                    ? Carbon::parse($plannedSent)->format('Y-m-d')
                    : null,
                'plannedReturnedDate' => $plannedReturned
                    ? Carbon::parse($plannedReturned)->format('Y-m-d')
                    : null,
                'promotion_ready_at' => optional($record->promotion_ready_at)?->format('Y-m-d H:i:s'),
                'promotion_source_orderID' => $record->promotion_source_orderID,
            ];
        })->values()->all();
    }

    /**
     * 貸出案件の課金価格を算出する。
     * parent の returnCode が 1,2,7,13 のとき loanermaster の版価格、それ以外／親なしは 0。
     * 受注日は loaner 自身 → 親案件の順。未設定なら最新版。
     */
    private function resolveLoanerChargePrice(mixed $parentId, mixed $loanerId, mixed $orderDate = null): float
    {
        if ($parentId === null || $parentId === '') {
            return 0.0;
        }

        $parent = ServiceRecord::query()
            ->where('orderID', $parentId)
            ->first(['returnCode', 'orderDate']);

        if (! $parent) {
            return 0.0;
        }

        $asOf = $orderDate ?: $parent->orderDate;

        return app(MasterPriceVersionResolver::class)
            ->loanerChargePrice($parent->returnCode, $loanerId, $asOf);
    }

    private function serializeLoanerNotes($notes)
    {
        $kanjiName = trim((string) (auth()->user()?->kanji_name ?? ''));

        return collect($notes)->map(function (AttachedNote $note) use ($kanjiName) {
            $whoWrote = trim((string) ($note->whoWrote ?? ''));

            return [
                'id' => $note->id,
                'associatedID' => $note->associatedID,
                'note' => $note->note,
                'whoWrote' => $note->whoWrote,
                'whenWrote' => $note->whenWrote,
                'important' => (bool) $note->important,
                'personal' => (bool) $note->personal,
                'is_mine' => $kanjiName !== '' && $whoWrote !== '' && $whoWrote === $kanjiName,
            ];
        })->values();
    }

    private function resolveStatusColumn(): string
    {
        static $column = null;

        if ($column !== null) {
            return $column;
        }

        $schema = Schema::getColumnListing('loanermaster');

        if (in_array('currentStatus', $schema, true)) {
            return $column = 'currentStatus';
        }

        if (in_array('current_status', $schema, true)) {
            return $column = 'current_status';
        }

        return $column = 'currentStatus';
    }
}

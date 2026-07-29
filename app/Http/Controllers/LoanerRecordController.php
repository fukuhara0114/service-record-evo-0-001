<?php

namespace App\Http\Controllers;

use App\Models\AttachedLoaner;
use App\Models\Dealer;
use App\Models\LoanerMaster;
use App\Models\ServiceRecord;
use App\Models\StatusLoaner;
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

        $loaners = LoanerMaster::query()
            ->whereNotNull('productName')
            ->where('productName', '!=', '')
            ->orderBy('productName')
            ->orderBy('loanerID')
            ->get([
                'loanerID',
                'productName',
                'SN',
                'manageNum',
                'item',
                'groupName',
                $statusColumn,
            ]);

        $loanerProducts = $loaners
            ->groupBy('productName')
            ->map(function ($rows, $productName) use ($statusColumn) {
                $availableCount = $rows->where($statusColumn, 0)->count();

                return [
                    'productName' => $productName,
                    'totalCount' => $rows->count(),
                    'availableCount' => $availableCount,
                    'available' => $availableCount > 0,
                    'order_type' => $availableCount > 0 ? 'loaner' : 'waiting_list',
                ];
            })
            ->values();

        $statuses = StatusLoaner::orderBy('processID')->get();
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'productName' => 'required|string|max:255',
            'receivedDate' => 'nullable|date',
            'status' => 'nullable|integer',
            'returnCode' => 'nullable|integer',
            'SN' => 'nullable|string|max:255',
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
        ]);

        $available = $this->findAvailableLoaner($validated['productName']);
        $orderType = $available ? 'loaner' : 'waiting_list';
        $user = $request->user();
        $linkMode = $validated['linkMode'];
        $parentId = null;

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
                $status = $this->resolveUnregisteredStatus()?->processID;
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
            &$attachedLoanerId,
        ) {
            $record = ServiceRecord::create([
                'receivedDate' => null,
                'status' => $status,
                'returnCode' => null,
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
            'statuses' => StatusLoaner::orderBy('processID')->get(['processID', 'status']),
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
                ->where('processID', $validated['status'])
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

    private function findAvailableLoaner(string $productName): ?LoanerMaster
    {
        $statusColumn = $this->resolveStatusColumn();

        return LoanerMaster::query()
            ->where('productName', $productName)
            ->where($statusColumn, 0)
            ->orderBy('loanerID')
            ->first();
    }

    private function resolveUnregisteredStatus(): ?StatusLoaner
    {
        // 期間付きで新規登録する既定は「案件未登録-期間仮予約」(35)
        $provisional = StatusLoaner::query()->where('processID', 35)->first();
        if ($provisional) {
            return $provisional;
        }

        return StatusLoaner::query()
            ->where('status', 'like', '%未登録%')
            ->orderBy('processID')
            ->first();
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

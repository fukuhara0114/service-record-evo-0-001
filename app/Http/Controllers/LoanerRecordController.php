<?php

namespace App\Http\Controllers;

use App\Models\AttachedLoaner;
use App\Models\AttachedFile;
use App\Models\AttachedNote;
use App\Models\Dealer;
use App\Models\IncidentMaster;
use App\Models\Labor;
use App\Models\LoanerMaster;
use App\Models\MaintenanceContractMaster;
use App\Models\ServiceRecord;
use App\Models\StatusLoaner;
use App\Services\Gmail\AssignNotificationMailer;
use App\Services\LoanerApplicationPdfService;
use App\Services\MasterPriceVersionResolver;
use App\Support\LoanerStatusFlow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                    'certificatedDate',
                    'note1',
                    'note2',
                    'note3',
                    'validDateMin',
                    'validDateMax',
                    $statusColumn,
                ]),
            'loanerID'
        )->sortBy([
            ['productName', 'asc'],
            ['loanerID', 'asc'],
        ])->values();

        $loanerProducts = LoanerMaster::groupForProductSelect($loaners, $statusColumn);

        $statuses = StatusLoaner::mapForDisplay(
            StatusLoaner::orderBy('processID_new')->get(StatusLoaner::selectColumnsForDisplay()),
        );
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
            'loanerID' => 'required|integer',
        ]);

        $loanerId = (int) $validated['loanerID'];
        $unit = $this->latestLoanerUnitById($loanerId);
        if (!$unit) {
            return response()->json(['message' => '指定した貸出機が見つかりません。'], 422);
        }

        $available = $this->isLoanerUnitInStock($unit) ? $unit : null;
        $orderType = $available ? 'loaner' : 'waiting_list';

        $payload = [
            'available' => $available !== null,
            'order_type' => $orderType,
            'requested' => [
                'loanerID' => $loanerId,
            ],
            'loaner' => [
                'loanerID' => $unit->loanerID,
                'productName' => $unit->productName,
                'SN' => $unit->SN,
                'manageNum' => $unit->manageNum,
                'item' => $unit->item,
            ],
        ];

        if ($orderType === 'waiting_list') {
            [$start, $end, $basedOn] = $this->resolveWaitingListDefaultPeriod(
                (string) ($unit->productName ?? ''),
                null,
                $loanerId,
            );
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
        ];

        // URL の {id} = 一覧で選んだ orderID → servicerecord を開く
        [$attached, $record] = $this->resolveLoanerDetailByOrderId($id, $with);
        if (!$record) {
            return response(
                "LoanerDetail: servicerecord not found for orderID={$id}",
                404
            )->header('Content-Type', 'text/plain; charset=UTF-8');
        }
        if (!$attached) {
            return response(
                "LoanerDetail: attachedloaners create failed for orderID={$id} order_type={$record->order_type} productName={$record->productName}",
                404
            )->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $parentReturnCode = null;
        $parentRecord = null;
        if ($record->parentID) {
            $parent = ServiceRecord::query()
                ->with(['statusMaster', 'statusMasterLoaner'])
                ->where('orderID', $record->parentID)
                ->first();

            if ($parent) {
                $parentReturnCode = $parent->returnCode;
                if ($parent->order_type === 'loaner') {
                    $parentStatusLabel = StatusLoaner::resolveLabel($parent->statusMasterLoaner);
                } elseif ($parent->order_type === 'waiting_list') {
                    $parentStatusLabel = null;
                } else {
                    $parentStatusLabel = $parent->statusMaster?->status;
                }

                $sentOutRaw = $parent->sentOut;
                if ($sentOutRaw instanceof \DateTimeInterface) {
                    $sentOut = $sentOutRaw->format('Y-m-d');
                } elseif ($sentOutRaw === null || $sentOutRaw === '') {
                    $sentOut = null;
                } else {
                    $raw = substr((string) $sentOutRaw, 0, 10);
                    $sentOut = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : null;
                }

                $parentRecord = [
                    'orderID' => $parent->orderID,
                    'orderDate' => app(MasterPriceVersionResolver::class)->normalizeDate($parent->orderDate),
                    'status' => $parent->status,
                    'status_label' => $parentStatusLabel,
                    'sentOut' => $sentOut,
                    'returnCode' => $parent->returnCode,
                    'SN' => $parent->SN,
                ];
            }
        }

        $columns = Schema::getColumnListing('attachedloaners');
        $resolver = app(MasterPriceVersionResolver::class);
        $loanerId = $attached->loanerID ?? $record->loanerID;
        $priceAsOf = $resolver->resolveLoanerPriceAsOf($record->orderDate);
        $versionedMaster = $resolver->loanerMaster($loanerId, $priceAsOf);

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
                'enduser_SN' => $attached->getAttribute('repairInstrument-SN'),
            ],
            'record' => array_merge($record->only([
                'orderID',
                'parentID',
                'order_type',
                'original_order_type',
                'status',
                'laborID',
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
                'coNum',
                'shippingOut_requiredDate',
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
                'incident',
                'promotion_ready_at',
                'promotion_source_orderID',
            ]), [
                'orderDate' => $resolver->normalizeDate($record->orderDate),
                'quoteDate' => $resolver->normalizeDate($record->quoteDate),
                'shippingOut_requiredDate' => $resolver->normalizeDate($record->shippingOut_requiredDate),
                'status_label' => $record->order_type === 'loaner'
                    ? StatusLoaner::resolveLabel($record->statusMasterLoaner)
                    : null,
            ]),
            'parentReturnCode' => $parentReturnCode,
            'parentRecord' => $parentRecord,
            'loanerMaster' => $versionedMaster
                ? $this->serializeLoanerMasterRow($versionedMaster, $resolver)
                : null,
            'availableUnits' => $this->serializeAvailableUnitsForProduct(
                $record->productName,
                $record->order_type === 'waiting_list' ? $record : null,
            ),
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
                    ->orderBy('whenWrote')
                    ->orderBy('id')
                    ->get()
            ),
            'statuses' => StatusLoaner::mapForDisplay(
                StatusLoaner::orderBy('processID_new')->get(StatusLoaner::selectColumnsForDisplay()),
            ),
            'statusFlow' => LoanerStatusFlow::meta(),
            'labors' => Labor::query()->orderBy('laborName')->get(['laborID', 'laborName']),
            'dealersMaster' => Dealer::orderBy('dealerName')->get(),
            'incidentsMaster' => IncidentMaster::query()
                ->select(['id', 'incidentNum', 'companyName', 'depart', 'customerNum'])
                ->orderByDesc('incidentNum')
                ->get(),
            // 価格計算用に同一 loanerID の全版を渡す（日付は Y-m-d。選択ダイアログ側で最新版に絞る）
            'loanerUnits' => $this->serializeLoanerUnitsForDetail($resolver),
            'dateFields' => [
                'hasPlannedSent' => in_array('plannedSentDate', $columns, true),
                'hasPlannedReturned' => in_array('plannedReturnedDate', $columns, true),
            ],
        ]);
    }

    /**
     * waiting_list → loaner へ繰り上げる。
     */
    public function promoteFromWaiting(Request $request, int $id)
    {
        [$attached, $record] = $this->resolveAttachedAndRecordForMutation($id);

        if (!$attached || !$record || $record->order_type !== 'waiting_list') {
            return response()->json(['message' => 'waiting_list 案件のみ繰り上げできます。'], 422);
        }

        $validated = $request->validate([
            'loanerID' => 'nullable|integer',
        ]);

        $preferredLoanerId = isset($validated['loanerID']) ? (int) $validated['loanerID'] : null;
        if ($preferredLoanerId === null && $record->promotion_source_orderID) {
            $sourceLoanerId = ServiceRecord::query()
                ->where('orderID', $record->promotion_source_orderID)
                ->value('loanerID');
            if ($sourceLoanerId !== null && $sourceLoanerId !== '') {
                $preferredLoanerId = (int) $sourceLoanerId;
            }
        }

        $isPromotionReady = Schema::hasColumn('servicerecord', 'promotion_ready_at')
            && $record->promotion_ready_at != null
            && $record->promotion_ready_at !== '';

        $available = $this->findUnitForWaitingPromotion($record, $preferredLoanerId, $isPromotionReady);

        if (!$available) {
            return response()->json([
                'message' => isset($validated['loanerID'])
                    ? '指定した貸出機は同 groupName の在庫として選択できません。'
                    : '同 groupName の在庫がありません。在庫復帰後に再度実行してください。',
            ], 422);
        }

        $user = $request->user();
        $initialStatus = $this->resolveInitialLoanerStatusId();

        DB::transaction(function () use ($record, $attached, $available, $user, $initialStatus) {
            $record->order_type = 'loaner';
            $record->status = $initialStatus;
            $record->RMA = 'loaner';
            $record->loanerID = $available->loanerID;
            $record->productName = $available->productName ?? $record->productName;
            $record->SN = $available->SN ?? $record->SN;
            if (Schema::hasColumn('servicerecord', 'promotion_ready_at')) {
                $record->promotion_ready_at = null;
            }
            if (Schema::hasColumn('servicerecord', 'promotion_source_orderID')) {
                $record->promotion_source_orderID = null;
            }
            $record->lastEditPerson = $user?->kanji_name;
            $record->lastEditDate = now();
            $record->save();

            $attached->loanerID = $available->loanerID;
            $attachedColumns = Schema::getColumnListing('attachedloaners');
            if (in_array('assignStatus', $attachedColumns, true)) {
                $attached->assignStatus = 'reserved';
            }
            if (in_array('productName', $attachedColumns, true)) {
                $attached->productName = $available->productName ?? $attached->productName;
            }
            if (in_array('comment', $attachedColumns, true)) {
                $comment = trim((string) ($attached->comment ?? ''));
                $attached->comment = $comment === ''
                    ? 'promoted from waiting_list'
                    : $comment;
            }
            // NOT NULL 制約対策: 空文字由来の null を残さない
            if (
                in_array('assignStatus', $attachedColumns, true)
                && ($attached->assignStatus === null || $attached->assignStatus === '')
            ) {
                $attached->assignStatus = 'reserved';
            }
            $attached->save();

            $this->setLoanerInventoryStatus($available->loanerID, (int) $initialStatus);
        });

        $record->refresh();
        $attached->refresh();

        return response()->json([
            'message' => 'waiting_list を loaner 案件へ繰り上げました。',
            'record' => $record->only([
                'orderID',
                'parentID',
                'order_type',
                'original_order_type',
                'status',
                'laborID',
                'productName',
                'SN',
                'loanerID',
                'promotion_ready_at',
                'promotion_source_orderID',
            ]),
            'attached' => [
                'id' => $attached->id,
                'loanerID' => $attached->loanerID,
                'assignStatus' => $attached->assignStatus ?? null,
            ],
        ]);
    }

    /**
     * 予約入替用: 同機種の waiting_list 候補を返す。
     */
    public function waitingListForSwap(Request $request, int $id)
    {
        [$attached, $record] = $this->resolveAttachedAndRecordForMutation($id);

        if (!$attached || !$record || $record->order_type !== 'loaner') {
            return response()->json(['message' => 'loaner 案件のみ予約入替できます。'], 422);
        }

        if (!$this->canSwapReservationStatus((int) $record->status)) {
            return response()->json(['message' => 'status が 20 以上 150 未満の案件のみ予約入替できます。'], 422);
        }

        $productName = trim((string) ($record->productName ?? ''));
        if ($productName === '') {
            return response()->json(['message' => 'productName が未設定のため候補を取得できません。'], 422);
        }

        $candidates = $this->serializePromotionCandidates(
            $this->findWaitingListCandidatesByProductName($productName),
        );

        return response()->json([
            'productName' => $productName,
            'candidates' => $candidates,
        ]);
    }

    /**
     * 確保済み loaner と waiting_list を入れ替え、機材を waiting 側へ移す。
     */
    public function swapWithWaiting(Request $request, int $id)
    {
        [$attached, $loanerRecord] = $this->resolveAttachedAndRecordForMutation($id);

        if (!$attached || !$loanerRecord || $loanerRecord->order_type !== 'loaner') {
            return response()->json(['message' => 'loaner 案件のみ予約入替できます。'], 422);
        }

        if (!$this->canSwapReservationStatus((int) $loanerRecord->status)) {
            return response()->json(['message' => 'status が 20 以上 150 未満の案件のみ予約入替できます。'], 422);
        }

        $validated = $request->validate([
            'waitingOrderID' => 'required|integer',
        ]);

        $waitingRecord = ServiceRecord::query()
            ->where('orderID', $validated['waitingOrderID'])
            ->where('order_type', 'waiting_list')
            ->first();

        if (!$waitingRecord) {
            return response()->json(['message' => '指定された waiting_list 案件が見つかりません。'], 422);
        }

        $productName = trim((string) ($loanerRecord->productName ?? ''));
        $waitingProduct = trim((string) ($waitingRecord->productName ?? ''));
        if ($productName === '' || strcasecmp($productName, $waitingProduct) !== 0) {
            return response()->json(['message' => '同機種（productName）の waiting_list のみ入替できます。'], 422);
        }

        $loanerId = $loanerRecord->loanerID ?? $attached->loanerID;
        if ($loanerId === null || $loanerId === '') {
            return response()->json(['message' => 'loanerID が未設定のため入替できません。'], 422);
        }

        $waitingAttached = AttachedLoaner::query()
            ->where('associatedID', $waitingRecord->orderID)
            ->orderByDesc('id')
            ->first();

        if (!$waitingAttached) {
            return response()->json(['message' => 'waiting_list 側の attachedloaner が見つかりません。'], 422);
        }

        $user = $request->user();
        $securedStatus = (int) $loanerRecord->status;
        $sn = $loanerRecord->SN;
        $loanerProductName = $loanerRecord->productName;
        $fallbackLoanerId = $this->resolveFallbackLoanerId($productName) ?? $loanerId;

        DB::transaction(function () use (
            $loanerRecord,
            $attached,
            $waitingRecord,
            $waitingAttached,
            $loanerId,
            $fallbackLoanerId,
            $securedStatus,
            $sn,
            $loanerProductName,
            $user,
        ) {
            // 現在の loaner → waiting_list
            $loanerRecord->order_type = 'waiting_list';
            $loanerRecord->status = -1;
            $loanerRecord->loanerID = $fallbackLoanerId;
            if (Schema::hasColumn('servicerecord', 'promotion_ready_at')) {
                $loanerRecord->promotion_ready_at = null;
            }
            if (Schema::hasColumn('servicerecord', 'promotion_source_orderID')) {
                $loanerRecord->promotion_source_orderID = null;
            }
            $loanerRecord->lastEditPerson = $user?->kanji_name;
            $loanerRecord->lastEditDate = now();
            $loanerRecord->save();

            $attachedColumns = Schema::getColumnListing('attachedloaners');
            $attached->loanerID = $fallbackLoanerId;
            if (in_array('assignStatus', $attachedColumns, true)) {
                $attached->assignStatus = 'waiting';
            }
            if (in_array('comment', $attachedColumns, true)) {
                $comment = trim((string) ($attached->comment ?? ''));
                $note = 'swapped to waiting_list';
                $attached->comment = $comment === '' ? $note : $comment.' / '.$note;
            }
            $attached->save();

            // waiting_list → loaner（機材を紐づけ）
            $waitingRecord->order_type = 'loaner';
            $waitingRecord->status = $securedStatus;
            $waitingRecord->RMA = 'loaner';
            $waitingRecord->loanerID = $loanerId;
            $waitingRecord->productName = $loanerProductName ?: $waitingRecord->productName;
            $waitingRecord->SN = $sn;
            if (Schema::hasColumn('servicerecord', 'promotion_ready_at')) {
                $waitingRecord->promotion_ready_at = null;
            }
            if (Schema::hasColumn('servicerecord', 'promotion_source_orderID')) {
                $waitingRecord->promotion_source_orderID = null;
            }
            $waitingRecord->lastEditPerson = $user?->kanji_name;
            $waitingRecord->lastEditDate = now();
            $waitingRecord->save();

            $waitingAttached->loanerID = $loanerId;
            if (in_array('assignStatus', $attachedColumns, true)) {
                $waitingAttached->assignStatus = 'reserved';
            }
            if (in_array('productName', $attachedColumns, true)) {
                $waitingAttached->productName = $loanerProductName ?: $waitingAttached->productName;
            }
            if (in_array('comment', $attachedColumns, true)) {
                $comment = trim((string) ($waitingAttached->comment ?? ''));
                $note = 'swapped from waiting_list';
                $waitingAttached->comment = $comment === '' ? $note : $comment.' / '.$note;
            }
            if (
                in_array('assignStatus', $attachedColumns, true)
                && ($waitingAttached->assignStatus === null || $waitingAttached->assignStatus === '')
            ) {
                $waitingAttached->assignStatus = 'reserved';
            }
            $waitingAttached->save();

            $this->setLoanerInventoryStatus($loanerId, $securedStatus);
        });

        $waitingRecord->refresh();
        $waitingAttached->refresh();

        return response()->json([
            'message' => '予約を入れ替えました。',
            'record' => $waitingRecord->only([
                'orderID',
                'parentID',
                'order_type',
                'original_order_type',
                'status',
                'laborID',
                'productName',
                'SN',
                'loanerID',
            ]),
            'attached' => [
                'id' => $waitingAttached->id,
                'loanerID' => $waitingAttached->loanerID,
                'assignStatus' => $waitingAttached->assignStatus ?? null,
            ],
            // detail 画面の URL {id} は orderID
            'redirectOrderId' => $waitingRecord->orderID,
        ]);
    }

    public function cancelReservation(Request $request, int $id)
    {
        $attachedRow = AttachedLoaner::query()->find($id);
        $orderId = $attachedRow?->associatedID ?: $id;

        $resolved = $this->resolveLoanerDetailByOrderId((int) $orderId, ['serviceRecord']);
        $attached = $resolved[0] ?? $attachedRow;
        $record = $resolved[1] ?? $attached?->serviceRecord;

        if (!$attached || !$record || $record->order_type !== 'waiting_list') {
            return response()->json(['message' => 'waiting_list 案件のみ予約キャンセルできます。'], 422);
        }

        $user = $request->user();
        $loanerId = $record->loanerID ?? $attached->loanerID;

        DB::transaction(function () use ($record, $attached, $user, $loanerId) {
            $record->order_type = 'loaner';
            $record->status = LoanerStatusFlow::COMPLETE;
            $record->RMA = 'loaner';
            if (Schema::hasColumn('servicerecord', 'promotion_ready_at')) {
                $record->promotion_ready_at = null;
            }
            if (Schema::hasColumn('servicerecord', 'promotion_source_orderID')) {
                $record->promotion_source_orderID = null;
            }
            $record->lastEditPerson = $user?->kanji_name;
            $record->lastEditDate = now();
            $record->save();

            $attachedColumns = Schema::getColumnListing('attachedloaners');
            if (in_array('assignStatus', $attachedColumns, true)) {
                $attached->assignStatus = 'cancelled';
            }
            if (in_array('comment', $attachedColumns, true)) {
                $comment = trim((string) ($attached->comment ?? ''));
                $note = 'reservation cancelled from waiting_list';
                $attached->comment = $comment === '' ? $note : $comment;
            }
            $attached->save();

            if ($loanerId !== null && $loanerId !== '') {
                // 完了扱い: associatedID=-1 / currentStatus=0
                $this->releaseLoanerMasterOnComplete($loanerId);
            }
        });

        $record->refresh();
        $attached->refresh();

        return response()->json([
            'message' => '予約をキャンセルしました。',
            'record' => $record->only([
                'orderID',
                'order_type',
                'original_order_type',
                'status',
                'loanerID',
            ]),
            'attached' => [
                'id' => $attached->id,
                'loanerID' => $attached->loanerID,
                'assignStatus' => $attached->assignStatus ?? null,
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeAvailableUnitsForProduct(?string $productName, ?ServiceRecord $waitingRecord = null): array
    {
        $groupName = $waitingRecord ? $this->resolveGroupNameForRecord($waitingRecord) : '';
        $productName = trim((string) $productName);

        // waiting 側に loanerID が無くても productName から groupName を拾えた場合はグループ優先
        if ($groupName === '' && $productName !== '') {
            $groupName = $this->resolveGroupNameForProductName($productName);
        }

        $latest = app(MasterPriceVersionResolver::class)->latestByKey(
            LoanerMaster::query()
                ->whereNotNull('loanerID')
                ->where('loanerID', '!=', ''),
            'loanerID'
        );

        if ($groupName !== '') {
            $latest = $latest
                ->filter(fn (LoanerMaster $row) => strcasecmp(trim((string) ($row->groupName ?? '')), $groupName) === 0)
                ->values();
        } elseif ($productName !== '') {
            $latest = $latest
                ->filter(fn (LoanerMaster $row) => strcasecmp(trim((string) ($row->productName ?? '')), $productName) === 0)
                ->values();
        } else {
            return [];
        }

        $available = $latest
            ->filter(fn (LoanerMaster $row) => $this->isLoanerUnitInStock($row))
            ->values();

        // 繰上可: 返却元個体を候補先頭へ（在庫フラグ未更新でも選べるようにする）
        $sourceLoanerId = null;
        if (
            $waitingRecord
            && $waitingRecord->promotion_ready_at
            && $waitingRecord->promotion_source_orderID
        ) {
            $sourceLoanerId = ServiceRecord::query()
                ->where('orderID', $waitingRecord->promotion_source_orderID)
                ->value('loanerID');
        }

        if ($sourceLoanerId !== null && $sourceLoanerId !== '') {
            $sourceUnit = $latest->first(
                fn (LoanerMaster $row) => (string) $row->loanerID === (string) $sourceLoanerId
            );
            if ($sourceUnit) {
                $available = $available
                    ->reject(fn (LoanerMaster $row) => (string) $row->loanerID === (string) $sourceLoanerId)
                    ->values();
                $available->prepend($sourceUnit);
            }
        }

        return $available
            ->map(fn (LoanerMaster $row) => [
                'loanerID' => $row->loanerID,
                'productName' => $row->productName,
                'SN' => $row->SN,
                'manageNum' => $row->manageNum,
                'item' => $row->item,
                'groupName' => $row->groupName,
                'isPromotionSource' => $sourceLoanerId !== null
                    && (string) $row->loanerID === (string) $sourceLoanerId,
            ])
            ->all();
    }

    private function findLoanerUnitByProductAndId(string $productName, ?int $loanerId = null): ?LoanerMaster
    {
        $latest = app(MasterPriceVersionResolver::class)->latestByKey(
            LoanerMaster::query()
                ->whereNotNull('loanerID')
                ->where('loanerID', '!=', '')
                ->where('productName', $productName),
            'loanerID'
        );

        if ($loanerId !== null) {
            return $latest->first(
                fn (LoanerMaster $row) => (int) $row->loanerID === $loanerId
            );
        }

        return $latest->first();
    }

    /**
     * 代替機申込書 PDF（プレビュー用・保存なし）
     */
    public function applicationForm(Request $request, int $id, LoanerApplicationPdfService $pdfService)
    {
        $this->stringifyEnduserSn($request);

        // 画面からは attached.id で呼ばれる。明細行から orderID を得て案件を開く。
        $attachedRow = AttachedLoaner::query()->find($id);
        $orderId = $attachedRow?->associatedID;
        if ($orderId === null || $orderId === '') {
            $orderId = $id;
        }

        $resolved = $this->resolveLoanerDetailByOrderId((int) $orderId, ['serviceRecord', 'loanerMaster']);
        $attached = $resolved[0] ?? null;
        $record = $resolved[1] ?? null;
        if (!$attached || !$record) {
            return response()->json(['message' => '指定された貸出案件は存在しません。'], 404);
        }

        $payload = $request->validate([
            'chargeType' => 'required|in:paid,free',
            'enduser_SN' => 'nullable|string|max:255',
            'contactPerson' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'fax' => 'nullable|string|max:255',
            'manageNum' => 'nullable|string|max:255',
            'item' => 'nullable|string|max:255',
            'loanerID' => 'nullable',
            'SN' => 'nullable|string|max:255',
            'price' => 'nullable|numeric',
            'orderDate' => 'nullable|date',
            'sentDate' => 'nullable|string|max:32',
            'plannedReturnedDate' => 'nullable|string|max:32',
            'returnedDate' => 'nullable|string|max:32',
            'dealer' => 'nullable|string|max:255',
            'dealer_depart' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'deliveryDestination_company' => 'nullable|string|max:255',
            'deliveryDestination_depart' => 'nullable|string|max:255',
            'deliveryDestination_contactPerson' => 'nullable|string|max:255',
            'deliveryDestination_zipcode' => 'nullable|string|max:20',
            'deliveryDestination_address1' => 'nullable|string|max:255',
            'deliveryDestination_address2' => 'nullable|string|max:255',
            'deliveryDestination_phone' => 'nullable|string|max:255',
            'deliveryDestination_fax' => 'nullable|string|max:255',
            'parentID' => 'nullable|integer',
            'senderName' => 'nullable|string|max:255',
        ]);

        $payload['repairSN'] = trim((string) ($payload['enduser_SN'] ?? ''));

        $senderName = trim((string) ($payload['senderName'] ?? ''));
        if ($senderName === '') {
            $user = Auth::user();
            $senderName = trim((string) ($user->kanji_name ?? $user->name ?? ''));
            if ($senderName === '' && !empty($record->laborID)) {
                $senderName = trim((string) (
                    Labor::query()->where('laborID', $record->laborID)->value('laborName') ?? ''
                ));
            }
        }

        $payload['senderName'] = $senderName;
        $payload['recvName'] = $senderName;

        // 画面未入力でも DB の依頼社・発送先を補完
        $fallbackKeys = [
            'contactPerson', 'phone', 'fax', 'dealer', 'dealer_depart',
            'zipcode', 'address1', 'address2',
            'deliveryDestination_company', 'deliveryDestination_depart',
            'deliveryDestination_contactPerson', 'deliveryDestination_zipcode',
            'deliveryDestination_address1', 'deliveryDestination_address2',
            'deliveryDestination_phone', 'deliveryDestination_fax',
            'SN',
        ];
        foreach ($fallbackKeys as $key) {
            $current = trim((string) ($payload[$key] ?? ''));
            if ($current !== '') {
                continue;
            }
            $fromRecord = $record->{$key} ?? null;
            if ($fromRecord !== null && trim((string) $fromRecord) !== '') {
                $payload[$key] = is_string($fromRecord) ? trim($fromRecord) : $fromRecord;
            }
        }
        if (trim((string) ($payload['manageNum'] ?? '')) === '') {
            $payload['manageNum'] = $attached->loanerMaster?->manageNum
                ?? LoanerMaster::query()->where('loanerID', $record->loanerID)->value('manageNum');
        }
        if (trim((string) ($payload['item'] ?? '')) === '') {
            $payload['item'] = $attached->loanerMaster?->item
                ?? $record->productName;
        }
        if (!isset($payload['loanerID']) || $payload['loanerID'] === '' || $payload['loanerID'] === null) {
            $payload['loanerID'] = $attached->loanerID ?? $record->loanerID;
        }

        if ($payload['chargeType'] === 'free') {
            $payload['price'] = 0;
        } else {
            $explicitPrice = $payload['price'] ?? null;
            if ($explicitPrice !== null && $explicitPrice !== '' && is_numeric($explicitPrice)) {
                $payload['price'] = (float) $explicitPrice;
            } else {
                $loanerId = $payload['loanerID'] ?? $attached->loanerID ?? $record->loanerID;
                $orderDate = $payload['orderDate'] ?? $record->orderDate;
                $parentId = $payload['parentID'] ?? $record->parentID;
                $resolver = app(MasterPriceVersionResolver::class);
                $parentOrderDate = $parentId
                    ? ServiceRecord::query()->where('orderID', $parentId)->value('orderDate')
                    : null;
                $master = null;
                if ($loanerId !== null && $loanerId !== '') {
                    $master = $resolver->loanerMaster(
                        $loanerId,
                        $resolver->resolveLoanerPriceAsOf($orderDate, $parentOrderDate),
                    );
                }
                $payload['price'] = $master?->price ?? $attached->loanerMaster?->price ?? 0;
            }
        }

        try {
            $binary = $pdfService->generate($payload);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => '申込書 PDF の生成に失敗しました。',
                'error' => $e->getMessage(),
            ], 500);
        }

        $filename = 'loaner_application_'.$record->orderID.'_'.date('Ymd_His').'.pdf';
        $wantPng = $request->query('format') === 'png'
            || str_contains((string) $request->header('Accept', ''), 'image/png');

        if ($wantPng) {
            try {
                $png = $pdfService->pdfToPng($binary);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => '申込書プレビュー画像の生成に失敗しました。',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return response($png, 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="'.preg_replace('/\.pdf$/i', '.png', $filename).'"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function updateDetail(Request $request, int $id)
    {
        $this->stringifyEnduserSn($request);

        $attached = AttachedLoaner::with('serviceRecord')->findOrFail($id);
        $record = $attached->serviceRecord;

        if (!$record || !in_array($record->order_type, ['loaner', 'waiting_list'], true)) {
            return response()->json(['message' => '指定された貸出案件は存在しません。'], 404);
        }

        $recordRules = [
            'parentID' => 'nullable|integer|exists:servicerecord,orderID',
            'status' => 'nullable|integer',
            'laborID' => 'nullable|integer',
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
            'coNum' => 'nullable|string|max:255',
            'incident' => 'nullable',
            'shippingOut_requiredDate' => 'nullable|date',
            'receivedDate' => 'nullable|date',
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
        $attachedRules['enduser_SN'] = 'nullable|string|max:255';

        $validated = $request->validate($recordRules + $attachedRules);

        // status=0（在庫有り）は falsy 扱いで欠落しやすいため、リクエスト生値を明示反映
        if ($request->exists('status')) {
            $rawStatus = $request->input('status');
            $validated['status'] = ($rawStatus === '' || $rawStatus === null) ? null : (int) $rawStatus;
        }
        // laborID=0（未定）も同様に生値を明示反映
        if ($request->exists('laborID')) {
            $rawLabor = $request->input('laborID');
            $validated['laborID'] = ($rawLabor === '' || $rawLabor === null) ? null : (int) $rawLabor;
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

        if ($record->order_type === 'loaner') {
            $previousStatus = (int) $record->status;
            $targetStatus = array_key_exists('status', $validated) && $validated['status'] !== null
                ? (int) $validated['status']
                : $previousStatus;

            // laborID はリクエストにあれば常に保存する（返却以外でも値を落とさない）
            // 返却(393) および 返却→受け入れ確認中 では labor 必須
            $requiresLabor = LoanerStatusFlow::isLaborEditableStatus($targetStatus)
                || (
                    LoanerStatusFlow::isReturnedStatus($previousStatus)
                    && (int) $targetStatus === LoanerStatusFlow::ACCEPTANCE
                );

            if ($requiresLabor) {
                $laborId = array_key_exists('laborID', $validated)
                    ? $validated['laborID']
                    : $record->laborID;
                if ($laborId === null || $laborId === '' || (int) $laborId === 0) {
                    return response()->json([
                        'message' => '返却担当の labor を選択してください。',
                    ], 422);
                }
                $laborExists = Labor::query()->where('laborID', $laborId)->exists();
                if (!$laborExists) {
                    return response()->json(['message' => '指定された labor は存在しません。'], 422);
                }
                $validated['laborID'] = (int) $laborId;
                $validated['receivedDate'] = now('Asia/Tokyo')->toDateString();
            } elseif (array_key_exists('laborID', $validated) && $validated['laborID'] !== null && $validated['laborID'] !== '') {
                $laborId = (int) $validated['laborID'];
                if ($laborId !== 0) {
                    $laborExists = Labor::query()->where('laborID', $laborId)->exists();
                    if (!$laborExists) {
                        return response()->json(['message' => '指定された labor は存在しません。'], 422);
                    }
                }
                $validated['laborID'] = $laborId;
            }

            if (LoanerStatusFlow::isShipPrepCompleteStatus($targetStatus)) {
                $shippingDate = $validated['shippingOut_requiredDate'] ?? $record->shippingOut_requiredDate;
                if ($shippingDate === null || $shippingDate === '') {
                    return response()->json([
                        'message' => 'status が「貸出機出荷準備完了＿起伝依頼」のときは発送予定日を設定してください。',
                    ], 422);
                }
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
            $attachedColumns,
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

            // 価格: 有償=受注日版 loanermaster / 無償=0。調整額は discount_service
            $parentId = array_key_exists('parentID', $validated)
                ? $validated['parentID']
                : $record->parentID;
            $loanerId = array_key_exists('loanerID', $validated)
                ? $validated['loanerID']
                : ($attached->loanerID ?? $record->loanerID);
            $orderDate = array_key_exists('orderDate', $validated)
                ? $validated['orderDate']
                : $record->orderDate;
            if ($record->order_type === 'loaner') {
                $incomingPrice = array_key_exists('price', $validated)
                    ? $validated['price']
                    : $record->price;
                if ($incomingPrice === null || (float) $incomingPrice === 0.0) {
                    $recordPayload['price'] = 0;
                } else {
                    $recordPayload['price'] = $this->resolveLoanerMasterPriceByOrderDate($loanerId, $orderDate);
                }
            } else {
                $recordPayload['price'] = $this->resolveLoanerChargePrice($parentId, $loanerId, $orderDate);
            }

            // laborID は fill 後に明示セット（欠落・上書き漏れ防止）
            if (array_key_exists('laborID', $validated)) {
                $recordPayload['laborID'] = $validated['laborID'];
            }

            $record->fill($recordPayload);
            if (array_key_exists('laborID', $validated)) {
                $record->laborID = $validated['laborID'];
            }
            $record->lastEditPerson = $request->user()?->kanji_name;
            $record->lastEditDate = now();
            $record->save();

            $attachedPayload = collect($validated)->only($attachedFields)->all();
            if (array_key_exists('loanerID', $validated)) {
                $attachedPayload['loanerID'] = $validated['loanerID'];
            }
            if (
                array_key_exists('productName', $validated)
                && in_array('productName', $attachedColumns, true)
            ) {
                $attachedPayload['productName'] = $validated['productName'];
            }
            // assignStatus は NOT NULL。空文字→null 変換で落ちないよう保護する
            if (
                array_key_exists('assignStatus', $attachedPayload)
                && ($attachedPayload['assignStatus'] === null || $attachedPayload['assignStatus'] === '')
            ) {
                if ($attached->assignStatus !== null && $attached->assignStatus !== '') {
                    unset($attachedPayload['assignStatus']);
                } else {
                    $attachedPayload['assignStatus'] = $record->order_type === 'waiting_list'
                        ? 'waiting'
                        : 'reserved';
                }
            }
            if (array_key_exists('enduser_SN', $validated) && in_array('repairInstrument-SN', $attachedColumns, true)) {
                $attachedPayload['repairInstrument-SN'] = $validated['enduser_SN'] ?? null;
            }
            $attached->fill($attachedPayload);
            $attached->save();

            $newLoanerId = $record->loanerID ?? $attached->loanerID;

            // 個体差し替え時は旧個体の associatedID をクリア
            if (
                $previousLoanerId !== null && $previousLoanerId !== ''
                && (string) $previousLoanerId !== (string) ($newLoanerId ?? '')
            ) {
                $this->syncLoanerMasterAssociatedIdFromParent($previousLoanerId, null);
            }

            if ($record->order_type === 'loaner') {
                $newStatus = (int) $record->status;
                $oldStatus = (int) $previousStatus;

                // 個体を差し替えたときは旧個体を在庫(0)へ戻す
                if (
                    $previousLoanerId !== null && $previousLoanerId !== ''
                    && (string) $previousLoanerId !== (string) ($newLoanerId ?? '')
                ) {
                    $this->setLoanerInventoryStatus($previousLoanerId, 0);
                }

                if ($newStatus === LoanerStatusFlow::COMPLETE) {
                    // 完了: associatedID=-1 / currentStatus=0
                    $this->releaseLoanerMasterOnComplete($newLoanerId);
                } else {
                    // 親 service の orderID を loanermaster.associatedID へ反映（未設定ならクリア）
                    $this->syncLoanerMasterAssociatedIdFromParent($newLoanerId, $record->parentID);
                    // 詳細の status（processID_new）を loanermaster.currentStatus へ同期
                    $this->syncLoanerMasterCurrentStatus($record, $newLoanerId);
                }

                if (LoanerStatusFlow::crossedToInactiveList($oldStatus, $newStatus)) {
                    $promotionTriggered = true;
                    $promotionCandidates = $this->markPromotionCandidatesForReturnedLoaner($record);
                }
            } else {
                // waiting_list など: 親紐づけのみ反映
                $this->syncLoanerMasterAssociatedIdFromParent($newLoanerId, $record->parentID);
            }
        });

        $attached->refresh();
        $record->refresh();

        $notifyLoanerCheck = $request->boolean('notify_loaner_check')
            && (int) ($record->status ?? 0) === LoanerStatusFlow::ACCEPTANCE
            && $record->laborID !== null
            && $record->laborID !== ''
            && (int) $record->laborID !== 0;
        if ($notifyLoanerCheck) {
            $orderIdForMail = (int) $record->orderID;
            $loanerDetailId = (int) $attached->id;
            dispatch(function () use ($orderIdForMail, $loanerDetailId) {
                $fresh = ServiceRecord::query()->where('orderID', $orderIdForMail)->first();
                if (! $fresh) {
                    return;
                }
                try {
                    app(AssignNotificationMailer::class)->notifyLoanerEquipmentCheck($fresh, $loanerDetailId);
                } catch (\Throwable $e) {
                    Log::error('貸出機材チェック通知メール処理で例外が発生しました', [
                        'orderID' => $orderIdForMail,
                        'error' => $e->getMessage(),
                    ]);
                }
            })->afterResponse();
        }

        $promotionFromLending = $promotionTriggered
            && LoanerStatusFlow::crossedToInactiveList($previousStatus, $record->status);

        $resolver = app(MasterPriceVersionResolver::class);

        return response()->json([
            'message' => '貸出詳細を保存しました。',
            'record' => array_merge($record->only([
                'orderID',
                'parentID',
                'order_type',
                'original_order_type',
                'status',
                'laborID',
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
                'coNum',
                'shippingOut_requiredDate',
                'receivedDate',
            ]), [
                'orderDate' => $resolver->normalizeDate($record->orderDate),
                'quoteDate' => $resolver->normalizeDate($record->quoteDate),
                'shippingOut_requiredDate' => $resolver->normalizeDate($record->shippingOut_requiredDate),
                'receivedDate' => $resolver->normalizeDate($record->receivedDate),
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
                'enduser_SN' => $attached->getAttribute('repairInstrument-SN'),
            ],
            'promotionTriggered' => $promotionTriggered,
            'promotionFromCheck' => $promotionFromLending,
            'promotionFromLending' => $promotionFromLending,
            'promotionCandidates' => $promotionCandidates,
            'promotionSource' => $promotionTriggered
                ? $this->serializePromotionSource($record)
                : null,
        ]);
    }

    public function store(Request $request)
    {
        $this->stringifyEnduserSn($request);
        $validated = $request->validate([
            'productName' => 'nullable|string|max:255',
            'item' => 'nullable|string|max:255',
            'receivedDate' => 'nullable|date',
            'status' => 'nullable|integer',
            'returnCode' => 'nullable|integer',
            'SN' => 'nullable|string|max:255',
            'loanerID' => 'required|integer',
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
            'enduser_SN' => 'nullable|string|max:255',
            'maintenanceContractId' => 'nullable|integer',
            'asWaitingList' => 'nullable|boolean',
        ]);

        $forceWaitingList = $request->boolean('asWaitingList');
        $requestedProductName = trim((string) ($validated['productName'] ?? ''));

        $requestedLoanerId = array_key_exists('loanerID', $validated) && $validated['loanerID'] !== null && $validated['loanerID'] !== ''
            ? (int) $validated['loanerID']
            : null;

        if ($requestedLoanerId === null) {
            return response()->json([
                'message' => '貸出機（loanerID）を選択してください。',
            ], 422);
        }

        $requestedUnit = $this->latestLoanerUnitById($requestedLoanerId);
        if (!$requestedUnit) {
            return response()->json([
                'message' => '指定した貸出機が見つかりません。一覧を更新してやり直してください。',
            ], 422);
        }

        $available = null;
        if (!$forceWaitingList && $this->isLoanerUnitInStock($requestedUnit)) {
            $available = $requestedUnit;
        }

        $sourceUnit = $available ?? $requestedUnit;

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
        $loanerMasterStatus = null;
        if ($orderType === 'loaner') {
            $loanerMasterStatus = $this->resolveInitialLoanerStatusId();
            $status = $loanerMasterStatus;
        }

        $attachedLoanerId = null;

        $record = DB::transaction(function () use (
            $validated,
            $available,
            $sourceUnit,
            $requestedLoanerId,
            $orderType,
            $status,
            $loanerMasterStatus,
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
                'productName' => $sourceUnit?->productName ?? ($validated['productName'] ?? null),
                'SN' => $sourceUnit?->SN ?? ($validated['SN'] ?? null),
                'loanerID' => $sourceUnit?->loanerID ?? $requestedLoanerId,
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
                (string) ($sourceUnit?->productName ?? ($validated['productName'] ?? '')),
                $validated['plannedSentDate'] ?? null,
                $validated['plannedReturnedDate'] ?? null,
                $validated['enduser_SN'] ?? null,
                null,
                $requestedLoanerId,
            );
            $attachedLoanerId = $attached?->id;

            // 貸出登録時は loanermaster.currentStatus を「案件未登録」(20) へ（waiting_list は個体未確定のため触らない）
            if (
                $orderType === 'loaner'
                && $loanerMasterStatus !== null
                && $available?->loanerID !== null
                && $available?->loanerID !== ''
            ) {
                $this->setLoanerInventoryStatus($available->loanerID, $loanerMasterStatus);
            }

            // 親 service があるときは loanermaster.associatedID に親 orderID を入れる
            $this->syncLoanerMasterAssociatedIdFromParent(
                $sourceUnit?->loanerID ?? $requestedLoanerId,
                $parentId,
            );

            if ($fileIds->isNotEmpty()) {
                AttachedFile::query()
                    ->whereIn('id', $fileIds)
                    ->where('associatedID', -1)
                    ->update(['associatedID' => $record->orderID]);
            }

            $maintenanceContractId = $validated['maintenanceContractId'] ?? null;
            if ($maintenanceContractId !== null && $maintenanceContractId !== '') {
                $contract = MaintenanceContractMaster::query()->find((int) $maintenanceContractId);
                if ($contract) {
                    $ref = trim((string) ($contract->RefNumber ?? ''));
                    $start = optional($contract->startDate)->format('Y-m-d') ?: '—';
                    $end = optional($contract->expireDate)->format('Y-m-d') ?: '—';
                    $noteText = '保守契約番号：' . ($ref !== '' ? $ref : '—')
                        . '、保守契約期間：' . $start . '～' . $end;

                    AttachedNote::create([
                        'associatedID' => $record->orderID,
                        'note' => $noteText,
                        'whoWrote' => $user?->kanji_name ?: 'unknown',
                        'whenWrote' => now('Asia/Tokyo')->format('Y-m-d H:i:s'),
                        'important' => false,
                        'personal' => false,
                        'tbc' => null,
                        'done' => null,
                    ]);
                }
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
            'loanerMaster:id,loanerID,productName,item,SN,manageNum',
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
                'manageNum' => $attached->loanerMaster?->manageNum,
                'enduser_SN' => $attached->getAttribute('repairInstrument-SN'),
                'order_type' => $attached->serviceRecord?->order_type,
                'dealer' => $attached->serviceRecord?->dealer,
                'dealer_depart' => $attached->serviceRecord?->dealer_depart,
                'contactPerson' => $attached->serviceRecord?->contactPerson,
                'phone' => $attached->serviceRecord?->phone,
                'email' => $attached->serviceRecord?->email,
                'zipcode' => $attached->serviceRecord?->zipcode,
                'address1' => $attached->serviceRecord?->address1,
                'address2' => $attached->serviceRecord?->address2,
                'endUser' => $attached->serviceRecord?->endUser,
                'endUser_depart' => $attached->serviceRecord?->endUser_depart,
                'endUser_contactPerson' => $attached->serviceRecord?->endUser_contactPerson,
                'endUser_phone' => $attached->serviceRecord?->endUser_phone,
                'endUser_email' => $attached->serviceRecord?->endUser_email,
                'endUser_zipcode' => $attached->serviceRecord?->endUser_zipcode,
                'endUser_address1' => $attached->serviceRecord?->endUser_address1,
                'endUser_address2' => $attached->serviceRecord?->endUser_address2,
                'deliveryDestination_company' => $attached->serviceRecord?->deliveryDestination_company,
                'deliveryDestination_depart' => $attached->serviceRecord?->deliveryDestination_depart,
                'deliveryDestination_contactPerson' => $attached->serviceRecord?->deliveryDestination_contactPerson,
                'deliveryDestination_phone' => $attached->serviceRecord?->deliveryDestination_phone,
                'deliveryDestination_email' => $attached->serviceRecord?->deliveryDestination_email,
                'deliveryDestination_zipcode' => $attached->serviceRecord?->deliveryDestination_zipcode,
                'deliveryDestination_address1' => $attached->serviceRecord?->deliveryDestination_address1,
                'deliveryDestination_address2' => $attached->serviceRecord?->deliveryDestination_address2,
                'parentID' => $attached->serviceRecord?->parentID,
                'status' => $attached->serviceRecord?->status,
            ],
            'parentRecord' => $parent,
            'productLoanSchedule' => $productLoanSchedule,
            'notes' => $this->serializeLoanerNotes(
                AttachedNote::query()
                    ->where('associatedID', $attached->associatedID)
                    ->orderBy('whenWrote')
                    ->orderBy('id')
                    ->get()
            ),
            'statuses' => StatusLoaner::mapForDisplay(
                StatusLoaner::orderBy('processID_new')->get(StatusLoaner::selectColumnsForDisplay()),
            ),
            'dealersMaster' => Dealer::orderBy('dealerName')->get(),
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

        $this->syncLoanerMasterAssociatedIdFromParent(
            $record->loanerID ?? $attached->loanerID,
            $record->parentID,
        );

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

        $this->stringifyEnduserSn($request);

        $rules = [
            'sentDate' => 'nullable|date',
            'returnedDate' => 'nullable|date|after_or_equal:sentDate',
            'comment' => 'nullable|string|max:1000',
            'status' => 'nullable|integer',
            'dealer' => 'nullable|string|max:255',
            'dealer_depart' => 'nullable|string|max:255',
            'contactPerson' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
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
            'deliveryDestination_email' => 'nullable|string|max:255',
            'deliveryDestination_zipcode' => 'nullable|string|max:20',
            'deliveryDestination_address1' => 'nullable|string|max:255',
            'deliveryDestination_address2' => 'nullable|string|max:255',
            'enduser_SN' => 'nullable|string|max:255',
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

        $payload = [];
        if (array_key_exists('sentDate', $validated)) {
            $payload['sentDate'] = $validated['sentDate'] ?? null;
        }
        if (array_key_exists('returnedDate', $validated)) {
            $payload['returnedDate'] = $validated['returnedDate'] ?? null;
        }

        if ($hasPlannedSent && array_key_exists('plannedSentDate', $validated)) {
            $payload['plannedSentDate'] = $validated['plannedSentDate'] ?? null;
        }
        if ($hasPlannedReturned && array_key_exists('plannedReturnedDate', $validated)) {
            $payload['plannedReturnedDate'] = $validated['plannedReturnedDate'] ?? null;
        }
        if (array_key_exists('comment', $validated) && in_array('comment', $columns, true)) {
            $payload['comment'] = $validated['comment'];
        }
        if (array_key_exists('enduser_SN', $validated) && in_array('repairInstrument-SN', $columns, true)) {
            $payload['repairInstrument-SN'] = $validated['enduser_SN'] ?? null;
        }

        $recordFields = [
            'dealer',
            'dealer_depart',
            'contactPerson',
            'phone',
            'email',
            'zipcode',
            'address1',
            'address2',
            'endUser',
            'endUser_depart',
            'endUser_contactPerson',
            'endUser_phone',
            'endUser_email',
            'endUser_zipcode',
            'endUser_address1',
            'endUser_address2',
            'deliveryDestination_company',
            'deliveryDestination_depart',
            'deliveryDestination_contactPerson',
            'deliveryDestination_phone',
            'deliveryDestination_email',
            'deliveryDestination_zipcode',
            'deliveryDestination_address1',
            'deliveryDestination_address2',
        ];
        $recordPayload = collect($validated)->only($recordFields)->all();

        DB::transaction(function () use ($attached, $payload, $record, $isLoaner, $validated, $request, $recordPayload) {
            if ($payload !== []) {
                $attached->fill($payload);
                $attached->save();
            }

            if ($record) {
                $shouldSaveRecord = false;
                if ($recordPayload !== []) {
                    $record->fill($recordPayload);
                    $shouldSaveRecord = true;
                }
                if ($isLoaner && array_key_exists('status', $validated)) {
                    $record->status = $validated['status'];
                    $shouldSaveRecord = true;
                }
                if ($shouldSaveRecord) {
                    $record->lastEditPerson = $request->user()?->kanji_name;
                    $record->lastEditDate = now();
                    $record->save();
                    if ($isLoaner && array_key_exists('status', $validated)) {
                        $loanerId = $record->loanerID ?? $attached->loanerID;
                        if ((int) $record->status === LoanerStatusFlow::COMPLETE) {
                            $this->releaseLoanerMasterOnComplete($loanerId);
                        } else {
                            $this->syncLoanerMasterAssociatedIdFromParent($loanerId, $record->parentID);
                            $this->syncLoanerMasterCurrentStatus($record, $loanerId);
                        }
                    }
                }
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
                'enduser_SN' => $attached->getAttribute('repairInstrument-SN'),
            ],
            'record' => $record?->only([
                'dealer',
                'dealer_depart',
                'contactPerson',
                'phone',
                'email',
                'zipcode',
                'address1',
                'address2',
                'endUser',
                'endUser_depart',
                'endUser_contactPerson',
                'endUser_phone',
                'endUser_email',
                'endUser_zipcode',
                'endUser_address1',
                'endUser_address2',
                'deliveryDestination_company',
                'deliveryDestination_depart',
                'deliveryDestination_contactPerson',
                'deliveryDestination_phone',
                'deliveryDestination_email',
                'deliveryDestination_zipcode',
                'deliveryDestination_address1',
                'deliveryDestination_address2',
            ]),
        ]);
    }

    private function stringifyEnduserSn(Request $request): void
    {
        if (!$request->exists('enduser_SN')) {
            return;
        }

        $sn = $request->input('enduser_SN');
        if ($sn === null || $sn === '') {
            $request->merge(['enduser_SN' => null]);
            return;
        }

        $request->merge(['enduser_SN' => trim((string) $sn)]);
    }

    private function createAttachedLoanerReservation(
        ServiceRecord $record,
        ?LoanerMaster $available,
        string $orderType,
        string $requestedProductName,
        ?string $plannedSentDate = null,
        ?string $plannedReturnedDate = null,
        ?string $repairInstrumentSn = null,
        ?string $requestedItem = null,
        mixed $requestedLoanerId = null,
    ): ?AttachedLoaner {
        $loanerId = $available?->loanerID ?? $requestedLoanerId;

        // waiting_list で個体未定の既存案件向け: 選択 loanerID が無いときだけ同名の代表個体
        if ($loanerId == null) {
            $loanerId = $this->resolveFallbackLoanerId($requestedProductName, $requestedItem);
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
                [$autoStart, $autoEnd] = $this->resolveWaitingListDefaultPeriod(
                    $requestedProductName,
                    $requestedItem,
                    $loanerId,
                );
                $start = $plannedSentDate ?: $autoStart;
                $end = $plannedReturnedDate ?: $autoEnd;
            } else {
                $start = $plannedSentDate;
                $end = $plannedReturnedDate;
            }
        } else {
            $start = $plannedSentDate ?: now()->toDateString();
            $end = $plannedReturnedDate ?: Carbon::parse($start)->addDays(14)->toDateString();
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
        if (in_array('repairInstrument-SN', $columns, true) && $repairInstrumentSn !== null && $repairInstrumentSn !== '') {
            $payload['repairInstrument-SN'] = $repairInstrumentSn;
        }

        return AttachedLoaner::create($payload);
    }

    /**
     * waiting_list の初期期間:
     * 同機種の各貸出機について「現在以降の予約終了日」を取り、
     * 最も早く空く個体の終了翌日を開始日とする（期間は14日）。
     *
     * @return array{0:string,1:string,2:?string} [start, end, basedOnReturnedDate]
     */
    private function resolveWaitingListDefaultPeriod(string $productName, ?string $item = null, mixed $loanerId = null): array
    {
        $today = Carbon::today();
        if ($loanerId !== null && $loanerId !== '') {
            $masters = collect([(object) [
                'id' => $loanerId,
                'loanerID' => $loanerId,
            ]]);
        } else {
            $masters = $this->loanerMastersMatchingSelection($productName, $item)
                ->map(fn (LoanerMaster $row) => (object) [
                    'id' => $row->id,
                    'loanerID' => $row->loanerID,
                ]);
        }

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

        // productName のみ紐づく予約（loanerID 未指定の既存 waiting 向け）
        if (($loanerId === null || $loanerId === '') && $productName !== '') {
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

        $end = $start->copy()->addDays(14);

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

    private function findAvailableLoaner(string $productName, ?int $loanerId = null, ?string $item = null): ?LoanerMaster
    {
        $item = trim((string) $item);
        $item = $item !== '' ? $item : null;
        $name = trim($productName);

        if ($loanerId !== null) {
            $selected = $this->latestLoanerUnitById($loanerId);

            return $this->isLoanerUnitInStock($selected) ? $selected : null;
        }

        if ($item === null && $name === '') {
            return null;
        }

        return $this->loanerMastersMatchingSelection($name, $item)
            ->first(fn (LoanerMaster $row) => $this->isLoanerUnitInStock($row));
    }

    /**
     * 選択機種（item 優先）に一致する最新個体。
     *
     * @return \Illuminate\Support\Collection<int, LoanerMaster>
     */
    private function loanerMastersMatchingSelection(string $productName, ?string $item = null)
    {
        $item = trim((string) $item);
        $item = $item !== '' ? $item : null;
        $name = trim($productName);

        return app(MasterPriceVersionResolver::class)->latestByKey(
            LoanerMaster::query()
                ->whereNotNull('loanerID')
                ->where('loanerID', '!=', ''),
            'loanerID'
        )->filter(
            fn (LoanerMaster $row) => LoanerMaster::matchesProductSelection($row, $item, $name)
                && !LoanerMaster::isExcludedFromProductSelect($row->item ?? null)
        )->values();
    }

    private function latestLoanerUnitById(int $loanerId): ?LoanerMaster
    {
        return app(MasterPriceVersionResolver::class)->latestByKey(
            LoanerMaster::query()
                ->where('loanerID', $loanerId),
            'loanerID'
        )->first();
    }

    private function isLoanerUnitInStock(?LoanerMaster $row): bool
    {
        if (!$row) {
            return false;
        }

        if (LoanerMaster::isExcludedFromProductSelect($row->item ?? null)) {
            return false;
        }

        $statusColumn = $this->resolveStatusColumn();

        return LoanerMaster::isInStockStatus($row->getAttribute($statusColumn));
    }

    /**
     * 貸出詳細の status（servicerecord.status = processID_new）を
     * loanermaster.currentStatus へ同期する。
     * 案件が完了(400)のときは個体を在庫(0)へ戻す。
     */
    private function syncLoanerMasterCurrentStatus(ServiceRecord $record, mixed $loanerId = null): void
    {
        if ($record->order_type !== 'loaner') {
            return;
        }

        $id = $loanerId ?? $record->loanerID;
        if ($id === null || $id === '' || (int) $id === 0) {
            return;
        }

        if ($record->status === null || $record->status === '') {
            return;
        }

        $recordStatus = (int) $record->status;
        $masterStatus = LoanerStatusFlow::isActiveListStatus($recordStatus)
            ? $recordStatus
            : LoanerStatusFlow::STOCK;

        $this->setLoanerInventoryStatus($id, $masterStatus);
    }

    /**
     * loaner 案件に紐づく loanermaster（同一 loanerID の全版）の associatedID を更新する。
     * 親が service 案件ならその orderID、未設定または service 以外なら null。
     */
    private function syncLoanerMasterAssociatedIdFromParent(mixed $loanerId, mixed $parentId): void
    {
        if ($loanerId === null || $loanerId === '' || (int) $loanerId === 0) {
            return;
        }

        if (! Schema::hasColumn((new LoanerMaster)->getTable(), 'associatedID')) {
            return;
        }

        $associatedId = null;
        if ($parentId !== null && $parentId !== '' && (int) $parentId !== 0) {
            $parent = ServiceRecord::query()
                ->where('orderID', (int) $parentId)
                ->first(['orderID', 'order_type']);

            if ($parent) {
                $parentOrderType = $parent->order_type;
                // store / linkParent と同様: null/空も service 扱い、明示的に service 以外は紐づけない
                if ($parentOrderType === null || $parentOrderType === '' || $parentOrderType === 'service') {
                    $associatedId = (int) $parent->orderID;
                }
            }
        }

        LoanerMaster::query()
            ->where('loanerID', $loanerId)
            ->update(['associatedID' => $associatedId]);
    }

    /**
     * loaner 案件が完了(400)のとき: associatedID=-1 / currentStatus=0（同一 loanerID の全版）。
     */
    private function releaseLoanerMasterOnComplete(mixed $loanerId): void
    {
        if ($loanerId === null || $loanerId === '' || (int) $loanerId === 0) {
            return;
        }

        $this->setLoanerInventoryStatus($loanerId, LoanerStatusFlow::STOCK);

        if (! Schema::hasColumn((new LoanerMaster)->getTable(), 'associatedID')) {
            return;
        }

        LoanerMaster::query()
            ->where('loanerID', $loanerId)
            ->update(['associatedID' => -1]);
    }

    /**
     * loanermaster.currentStatus を全版へ反映する。
     * 値は statusmaster_loaner.processID_new（詳細画面の status と同じ）。
     */
    private function setLoanerInventoryStatus(mixed $loanerId, int $status): void
    {
        if ($loanerId === null || $loanerId === '') {
            return;
        }

        $statusColumn = $this->resolveStatusColumn();
        if (!Schema::hasColumn((new LoanerMaster)->getTable(), $statusColumn)) {
            return;
        }

        if ($statusColumn === 'currentStatus') {
            LoanerMaster::unifyCurrentStatus($loanerId, $status);

            return;
        }

        LoanerMaster::syncSharedFieldsAcrossVersions($loanerId, [
            $statusColumn => $status,
        ]);

        LoanerMaster::query()
            ->where(function ($query) use ($loanerId) {
                $query->where('loanerID', $loanerId)
                    ->orWhere('id', $loanerId);
            })
            ->update([$statusColumn => $status]);
    }

    private function resolveUnregisteredStatus(): ?StatusLoaner
    {
        // processID_new=20 は「案件未登録」（新規登録時の初期 status）。
        $provisional = StatusLoaner::query()
            ->select(StatusLoaner::selectColumnsForDisplay())
            ->where('processID_new', 20)
            ->first();
        if ($provisional) {
            return $provisional;
        }

        return StatusLoaner::query()
            ->select(StatusLoaner::selectColumnsForDisplay())
            ->where('status_new', 'like', '%未登録%')
            ->orderBy('processID_new')
            ->first();
    }

    private function resolveInitialLoanerStatusId(): int
    {
        $resolved = $this->resolveUnregisteredStatus()?->processID_new;

        return $resolved !== null
            ? (int) $resolved
            : LoanerStatusFlow::UNREGISTERED;
    }

    /**
     * mutation API の {id} は attachedloaners.id でも orderID でも可。
     *
     * @return array{0: ?AttachedLoaner, 1: ?ServiceRecord}
     */
    private function resolveAttachedAndRecordForMutation(int $id): array
    {
        $attachedRow = AttachedLoaner::with('serviceRecord')->find($id);
        if ($attachedRow?->serviceRecord) {
            return [$attachedRow, $attachedRow->serviceRecord];
        }

        $orderId = $attachedRow?->associatedID ?: $id;
        $resolved = $this->resolveLoanerDetailByOrderId((int) $orderId, ['serviceRecord']);

        return [$resolved[0] ?? $attachedRow, $resolved[1] ?? null];
    }

    /**
     * 予約入替できる status（processID_new >= 20 かつ < 150）。
     */
    private function canSwapReservationStatus(int $status): bool
    {
        return $status >= 20 && $status < 150;
    }

    /**
     * statusmaster_loaner の表記が「確保済み」か（processID は環境により 0 / 20 等）。
     */
    private function isSecuredStockStatus(int $status): bool
    {
        $row = StatusLoaner::query()
            ->select(StatusLoaner::selectColumnsForDisplay())
            ->where('processID_new', $status)
            ->first();

        $label = StatusLoaner::resolveLabel($row);
        if (is_string($label) && $label !== '' && str_contains($label, '確保済み')) {
            return true;
        }

        return $status === LoanerStatusFlow::STOCK;
    }

    /**
     * 返却（在庫復帰）時に同 groupName の waiting_list を繰り上がり候補としてマークする。
     * groupName が空の個体は従来どおり productName で探す。
     *
     * @return array<int, array<string, mixed>>
     */
    private function markPromotionCandidatesForReturnedLoaner(ServiceRecord $returned): array
    {
        $candidates = $this->findWaitingListCandidatesForReturnedLoaner($returned);
        if ($candidates->isEmpty()) {
            return [];
        }

        $hasPromotionReadyAt = Schema::hasColumn('servicerecord', 'promotion_ready_at');
        $hasPromotionSource = Schema::hasColumn('servicerecord', 'promotion_source_orderID');
        if (!$hasPromotionReadyAt && !$hasPromotionSource) {
            return $this->serializePromotionCandidates($candidates);
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
    private function findWaitingListCandidatesForReturnedLoaner(ServiceRecord $returned)
    {
        $groupName = $this->resolveGroupNameForRecord($returned);
        if ($groupName !== '') {
            return $this->findWaitingListCandidatesByGroupName($groupName);
        }

        $productName = trim((string) ($returned->productName ?? ''));
        if ($productName === '') {
            return collect();
        }

        return $this->findWaitingListCandidatesByProductName($productName);
    }

    /**
     * @return \Illuminate\Support\Collection<int, ServiceRecord>
     */
    private function findWaitingListCandidatesByGroupName(string $groupName)
    {
        $loanerIds = $this->loanerIdsForGroupName($groupName);
        if ($loanerIds === []) {
            return collect();
        }

        $orderIdsFromAttached = AttachedLoaner::query()
            ->whereIn('loanerID', $loanerIds)
            ->pluck('associatedID')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $records = ServiceRecord::query()
            ->where('order_type', 'waiting_list')
            ->where(function ($q) use ($loanerIds, $orderIdsFromAttached) {
                $q->whereIn('loanerID', $loanerIds);
                if ($orderIdsFromAttached !== []) {
                    $q->orWhereIn('orderID', $orderIdsFromAttached);
                }
            })
            ->orderBy('orderID')
            ->get();

        return $this->sortWaitingListCandidates($records);
    }

    /**
     * @return \Illuminate\Support\Collection<int, ServiceRecord>
     */
    private function findWaitingListCandidatesByProductName(string $productName)
    {
        $records = ServiceRecord::query()
            ->where('order_type', 'waiting_list')
            ->where('productName', $productName)
            ->orderBy('orderID')
            ->get();

        return $this->sortWaitingListCandidates($records);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ServiceRecord>  $records
     * @return \Illuminate\Support\Collection<int, ServiceRecord>
     */
    private function sortWaitingListCandidates($records)
    {
        if ($records->isEmpty()) {
            return $records;
        }

        $attachedColumns = Schema::getColumnListing('attachedloaners');
        $hasPlannedSent = in_array('plannedSentDate', $attachedColumns, true);
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

        $loanerIds = $candidates
            ->map(function (ServiceRecord $record) use ($attachedByOrder) {
                $attached = $attachedByOrder->get($record->orderID);

                return $attached?->loanerID ?? $record->loanerID;
            })
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->unique()
            ->values()
            ->all();

        $mastersById = $this->latestUnitsByLoanerIds($loanerIds);

        return $candidates->map(function (ServiceRecord $record) use (
            $attachedByOrder,
            $hasPlannedSent,
            $hasPlannedReturned,
            $mastersById,
        ) {
            $attached = $attachedByOrder->get($record->orderID);
            $loanerId = $attached?->loanerID ?? $record->loanerID;
            $master = $loanerId !== null && $loanerId !== ''
                ? $mastersById->get((string) $loanerId)
                : null;
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
                'attachedId' => $attached?->id,
                'parentID' => $record->parentID,
                'dealer' => $record->dealer,
                'dealer_depart' => $record->dealer_depart,
                'contactPerson' => $record->contactPerson,
                'userSN' => $attached?->getAttribute('repairInstrument-SN'),
                'loanerID' => $loanerId,
                'item' => $master?->item,
                'productName' => $record->productName ?? $master?->productName,
                'SN' => $record->SN ?? $master?->SN,
                'groupName' => $master?->groupName,
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
     * @return array<string, mixed>
     */
    private function serializePromotionSource(ServiceRecord $returned): array
    {
        $loanerId = $this->resolveLoanerIdForRecord($returned);
        $master = $loanerId !== null && $loanerId !== ''
            ? $this->latestLoanerUnitById((int) $loanerId)
            : null;

        $files = AttachedFile::query()
            ->where('associatedID', $returned->orderID)
            ->select(['id', 'associatedID', 'documentType', 'documentName', 'fileType', 'sortNum'])
            ->orderByRaw('CASE WHEN sortNum IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sortNum')
            ->orderBy('id')
            ->get();

        return [
            'orderID' => $returned->orderID,
            'loanerID' => $loanerId,
            'item' => $master?->item,
            'productName' => $master?->productName ?? $returned->productName,
            'SN' => $master?->SN ?? $returned->SN,
            'manageNum' => $master?->manageNum,
            'groupName' => $master?->groupName,
            'certificatedDate' => optional($master?->certificatedDate)->format('Y-m-d'),
            'note1' => $master?->note1,
            'note2' => $master?->note2,
            'note3' => $master?->note3,
            'files' => $files,
        ];
    }

    private function resolveLoanerIdForRecord(ServiceRecord $record): mixed
    {
        if ($record->loanerID !== null && $record->loanerID !== '') {
            return $record->loanerID;
        }

        return AttachedLoaner::query()
            ->where('associatedID', $record->orderID)
            ->orderByDesc('id')
            ->value('loanerID');
    }

    private function resolveGroupNameForRecord(ServiceRecord $record): string
    {
        $groupName = $this->resolveGroupNameForLoanerId($this->resolveLoanerIdForRecord($record));
        if ($groupName !== '') {
            return $groupName;
        }

        return $this->resolveGroupNameForProductName($record->productName ?? null);
    }

    private function resolveGroupNameForProductName(?string $productName): string
    {
        $productName = trim((string) $productName);
        if ($productName === '') {
            return '';
        }

        $unit = app(MasterPriceVersionResolver::class)->latestByKey(
            LoanerMaster::query()
                ->whereNotNull('loanerID')
                ->where('loanerID', '!=', ''),
            'loanerID'
        )->first(function (LoanerMaster $row) use ($productName) {
            return strcasecmp(trim((string) ($row->productName ?? '')), $productName) === 0
                && trim((string) ($row->groupName ?? '')) !== '';
        });

        return trim((string) ($unit?->groupName ?? ''));
    }

    private function resolveGroupNameForLoanerId(mixed $loanerId): string
    {
        if ($loanerId === null || $loanerId === '') {
            return '';
        }

        $unit = $this->latestLoanerUnitById((int) $loanerId);

        return trim((string) ($unit?->groupName ?? ''));
    }

    /**
     * @return array<int, mixed>
     */
    private function loanerIdsForGroupName(string $groupName): array
    {
        $groupName = trim($groupName);
        if ($groupName === '') {
            return [];
        }

        return $this->latestLoanerUnitsForGroupName($groupName)
            ->pluck('loanerID')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, LoanerMaster>
     */
    private function latestLoanerUnitsForGroupName(string $groupName)
    {
        $groupName = trim($groupName);
        if ($groupName === '') {
            return collect();
        }

        return app(MasterPriceVersionResolver::class)->latestByKey(
            LoanerMaster::query()
                ->whereNotNull('loanerID')
                ->where('loanerID', '!=', ''),
            'loanerID'
        )->filter(
            fn (LoanerMaster $row) => strcasecmp(trim((string) ($row->groupName ?? '')), $groupName) === 0
                && !LoanerMaster::isExcludedFromProductSelect($row->item ?? null)
        )->values();
    }

    /**
     * @param  array<int, mixed>  $loanerIds
     * @return \Illuminate\Support\Collection<string, LoanerMaster>
     */
    private function latestUnitsByLoanerIds(array $loanerIds)
    {
        if ($loanerIds === []) {
            return collect();
        }

        return app(MasterPriceVersionResolver::class)->latestByKey(
            LoanerMaster::query()->whereIn('loanerID', $loanerIds),
            'loanerID'
        )->keyBy(fn (LoanerMaster $row) => (string) $row->loanerID);
    }

    private function findUnitForWaitingPromotion(
        ServiceRecord $waiting,
        ?int $preferredLoanerId,
        bool $isPromotionReady,
    ): ?LoanerMaster {
        $groupName = $this->resolveGroupNameForRecord($waiting);

        if ($preferredLoanerId !== null) {
            $preferred = $this->latestLoanerUnitById($preferredLoanerId);
            if ($preferred && $this->unitMatchesPromotionGroup($preferred, $groupName, $waiting)) {
                if ($this->isLoanerUnitInStock($preferred) || $isPromotionReady) {
                    return $preferred;
                }
            }
        }

        if ($groupName !== '') {
            $inGroup = $this->latestLoanerUnitsForGroupName($groupName);
            $inStock = $inGroup->first(fn (LoanerMaster $row) => $this->isLoanerUnitInStock($row));
            if ($inStock) {
                return $inStock;
            }
            if ($isPromotionReady) {
                return $inGroup->first();
            }

            return null;
        }

        $productName = trim((string) ($waiting->productName ?? ''));
        if ($productName === '') {
            return null;
        }

        $available = $this->findAvailableLoaner($productName, $preferredLoanerId);
        if ($available) {
            return $available;
        }
        if ($isPromotionReady && $preferredLoanerId !== null) {
            return $this->findLoanerUnitByProductAndId($productName, $preferredLoanerId);
        }
        if ($isPromotionReady) {
            return $this->findLoanerUnitByProductAndId($productName, null);
        }

        return null;
    }

    private function unitMatchesPromotionGroup(LoanerMaster $unit, string $groupName, ServiceRecord $waiting): bool
    {
        if ($groupName !== '') {
            return strcasecmp(trim((string) ($unit->groupName ?? '')), $groupName) === 0;
        }

        return strcasecmp(
            trim((string) ($unit->productName ?? '')),
            trim((string) ($waiting->productName ?? '')),
        ) === 0;
    }

    /**
     * 有償 loaner のマスタ価格。版は当該案件の受注日（未定・2000年以前は最新版）。
     * 親 service の受注日・returnCode は見ない。
     */
    private function resolveLoanerMasterPriceByOrderDate(mixed $loanerId, mixed $orderDate = null): float
    {
        $resolver = app(MasterPriceVersionResolver::class);
        $asOf = $resolver->resolveLoanerPriceAsOf($orderDate);
        $master = $resolver->loanerMaster($loanerId, $asOf);

        return (float) ($master->price ?? 0);
    }

    /**
     * waiting_list など親付き案件の課金価格。
     * parent の returnCode が 1,2,7,13 のとき loanermaster の版価格、それ以外／親なしは 0。
     * 版の基点は loaner 自身の受注日。発送予定日・出荷日は使わない。未設定・2000年以前なら最新版。
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

        $resolver = app(MasterPriceVersionResolver::class);
        $asOf = $resolver->resolveLoanerPriceAsOf($orderDate);

        return $resolver->loanerChargePrice($parent->returnCode, $loanerId, $asOf);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeLoanerUnitsForDetail(MasterPriceVersionResolver $resolver): array
    {
        $statusColumn = $this->resolveStatusColumn();

        return LoanerMaster::query()
            ->whereNotNull('loanerID')
            ->where('loanerID', '!=', '')
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
                'certificatedDate',
                'note1',
                'note2',
                'note3',
                'price',
                'validDateMin',
                'validDateMax',
                $statusColumn,
            ])
            ->map(fn (LoanerMaster $row) => $this->serializeLoanerMasterRow($row, $resolver, $statusColumn))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLoanerMasterRow(
        LoanerMaster $row,
        MasterPriceVersionResolver $resolver,
        ?string $statusColumn = null,
    ): array {
        $payload = [
            'id' => $row->id,
            'loanerID' => $row->loanerID,
            'productName' => $row->productName,
            'item' => $row->item,
            'SN' => $row->SN,
            'manageNum' => $row->manageNum,
            'groupName' => $row->groupName,
            'certificatedDate' => $resolver->normalizeDate($row->certificatedDate),
            'note1' => $row->note1,
            'note2' => $row->note2,
            'note3' => $row->note3,
            'price' => $row->price === null || $row->price === '' ? null : (float) $row->price,
            'validDateMin' => $resolver->normalizeDate($row->validDateMin),
            'validDateMax' => $resolver->normalizeDate($row->validDateMax),
        ];

        $statusKey = $statusColumn ?: $this->resolveStatusColumn();
        $payload[$statusKey] = $row->getAttribute($statusKey);

        return $payload;
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
                'whenWrote' => AttachedNote::formatWhenWrote($note->whenWrote),
                'important' => (bool) $note->important,
                'personal' => (bool) $note->personal,
                'tbc' => $this->nullableTrue($note->getAttributes()['tbc'] ?? null),
                'done' => $this->nullableTrue($note->getAttributes()['done'] ?? null),
                'is_mine' => $kanjiName !== '' && $whoWrote !== '' && $whoWrote === $kanjiName,
            ];
        })
            ->sortBy(function (array $note) {
                $when = $note['whenWrote'] ?? null;
                if ($when instanceof \DateTimeInterface) {
                    $whenStr = $when->format('Y-m-d H:i:s.u');
                } else {
                    $whenStr = (string) $when;
                }

                return sprintf('%s-%010d', $whenStr, (int) ($note['id'] ?? 0));
            })
            ->values();
    }

    private function nullableTrue(mixed $value): ?bool
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        return null;
    }

    /**
     * 一覧で選ばれた orderID の servicerecord（loaner / waiting_list）を開く。
     * 画面用の attachedloaners 行は、その orderID に紐づくものを使う。
     *
     * @param  list<string>  $with
     * @return array{0: ?AttachedLoaner, 1: ?ServiceRecord}
     */
    private function resolveLoanerDetailByOrderId(int $orderId, array $with = []): array
    {
        $record = ServiceRecord::query()
            ->where('orderID', $orderId)
            ->whereIn('order_type', ['loaner', 'waiting_list'])
            ->first();

        if (!$record) {
            return [null, null];
        }

        $attached = AttachedLoaner::with($with)
            ->where('associatedID', $record->orderID)
            ->orderByDesc('id')
            ->first();

        if (!$attached) {
            $created = $this->createAttachedLoanerReservation(
                $record,
                null,
                (string) $record->order_type,
                (string) ($record->productName ?? ''),
            );
            if ($created) {
                $attached = AttachedLoaner::with($with)->find($created->id);
            }
        }

        // マスタに個体が無く create できなくても、orderID の案件は開けるように最低限の明細を作る
        if (!$attached) {
            $attached = $this->ensureMinimalAttachedLoaner($record, $with);
        }

        return [$attached, $record];
    }

    /**
     * orderID に紐づく attachedloaners が無いときの最低限行。
     * waiting_list は loanerID 未設定・機種名の大文字小文字差でも落ちないようにする。
     *
     * @param  list<string>  $with
     */
    private function ensureMinimalAttachedLoaner(ServiceRecord $record, array $with = []): ?AttachedLoaner
    {
        $columns = Schema::getColumnListing('attachedloaners');
        $loanerId = $record->loanerID;
        if ($loanerId === null || $loanerId === '') {
            $loanerId = $this->resolveFallbackLoanerId((string) ($record->productName ?? ''));
        }

        $payload = [
            'associatedID' => $record->orderID,
            'comment' => $record->order_type === 'waiting_list'
                ? 'waiting_list reservation'
                : 'loaner reservation',
        ];

        if (in_array('loanerID', $columns, true)) {
            // NOT NULL 対策: どうしても無ければ 0
            $payload['loanerID'] = ($loanerId === null || $loanerId === '') ? 0 : $loanerId;
        }
        if (in_array('productName', $columns, true)) {
            $payload['productName'] = $record->productName;
        }
        if (in_array('assignStatus', $columns, true)) {
            $payload['assignStatus'] = $record->order_type === 'waiting_list' ? 'waiting' : 'reserved';
        }

        $start = now('Asia/Tokyo')->toDateString();
        $end = Carbon::parse($start)->addDays(14)->toDateString();
        if (in_array('sentDate', $columns, true)) {
            $payload['sentDate'] = $start;
        }
        if (in_array('returnedDate', $columns, true)) {
            $payload['returnedDate'] = $end;
        }
        if (in_array('plannedSentDate', $columns, true)) {
            $payload['plannedSentDate'] = $start;
        }
        if (in_array('plannedReturnedDate', $columns, true)) {
            $payload['plannedReturnedDate'] = $end;
        }

        try {
            $created = AttachedLoaner::create($payload);
        } catch (\Throwable $e) {
            Log::error('貸出明細の最低限作成に失敗しました', [
                'orderID' => $record->orderID,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return AttachedLoaner::with($with)->find($created->id);
    }

    /**
     * 同機種の代表 loanerID。部分一致や別機種へのフォールバックはしない。
     */
    private function resolveFallbackLoanerId(string $productName, ?string $item = null): mixed
    {
        $matched = $this->loanerMastersMatchingSelection($productName, $item)->first();

        return $matched?->loanerID ?? $matched?->id;
    }

    private function resolveStatusColumn(): string
    {
        static $column = null;

        if ($column !== null) {
            return $column;
        }

        $schema = Schema::getColumnListing((new LoanerMaster)->getTable());

        if (in_array('currentStatus', $schema, true)) {
            return $column = 'currentStatus';
        }

        if (in_array('current_status', $schema, true)) {
            return $column = 'current_status';
        }

        return $column = 'currentStatus';
    }
}

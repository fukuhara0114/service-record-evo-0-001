<?php

namespace App\Http\Controllers;

use App\Models\AttachedLoaner;
use App\Models\Dealer;
use App\Models\LoanerMaster;
use App\Models\ServiceRecord;
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

        $statuses = \App\Models\StatusLoaner::orderBy('processID')->get();
        $dealers = Dealer::orderBy('dealerName')->get();

        return Inertia::render('ServiceRecordLoanerCreate', [
            'loanerProducts' => $loanerProducts,
            'loaners' => $loaners,
            'statuses' => $statuses,
            'dealersMaster' => $dealers,
        ]);
    }

    public function availability(Request $request)
    {
        $validated = $request->validate([
            'productName' => 'required|string|max:255',
        ]);

        $available = $this->findAvailableLoaner($validated['productName']);

        return response()->json([
            'available' => $available !== null,
            'order_type' => $available ? 'loaner' : 'waiting_list',
            'loaner' => $available ? [
                'loanerID' => $available->loanerID,
                'productName' => $available->productName,
                'SN' => $available->SN,
                'manageNum' => $available->manageNum,
                'item' => $available->item,
            ] : null,
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

        $status = null;
        if ($orderType === 'loaner') {
            $status = $validated['status'] ?? null;
        }

        $attachedLoanerId = null;

        $record = DB::transaction(function () use (
            $validated,
            $available,
            $orderType,
            $status,
            $user,
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

            $attached = $this->createAttachedLoanerReservation($record, $available, $orderType, $validated['productName']);
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
        ], 201);
    }

    private function createAttachedLoanerReservation(
        ServiceRecord $record,
        ?LoanerMaster $available,
        string $orderType,
        string $requestedProductName,
    ): ?AttachedLoaner {
        $loanerId = $available?->loanerID;

        // waiting_list で個体未定でも loanerID が必須な場合は、同機種の代表個体を仮紐づけ
        if ($loanerId == null) {
            $fallback = LoanerMaster::query()
                ->where('productName', $requestedProductName)
                ->orderBy('loanerID')
                ->first();
            $loanerId = $fallback?->loanerID;
        }

        if ($loanerId == null) {
            return null;
        }

        $start = now()->toDateString();
        $end = now()->addDays(7)->toDateString();

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

    private function findAvailableLoaner(string $productName): ?LoanerMaster
    {
        $statusColumn = $this->resolveStatusColumn();

        return LoanerMaster::query()
            ->where('productName', $productName)
            ->where($statusColumn, 0)
            ->orderBy('loanerID')
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

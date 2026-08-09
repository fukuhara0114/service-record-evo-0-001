<?php

namespace App\Http\Controllers;

use App\Models\AttachedLoaner;
use App\Models\LoanerMaster;
use App\Models\ServiceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class LoanerCalendarController extends Controller
{
    public function index()
    {
        return Inertia::render('LoanerCalendarSample', [
            'loaners' => $this->loanerOptions(),
        ]);
    }

    public function events(Request $request)
    {
        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'loanerID' => 'nullable|integer',
            'productName' => 'nullable|string|max:255',
        ]);

        $query = AttachedLoaner::query()
            ->with([
                'serviceRecord',
                'loanerMaster:loanerID,productName,item,SN',
            ]);

        if (!empty($validated['loanerID'])) {
            $query->where('loanerID', $validated['loanerID']);
        }

        if (!empty($validated['productName'])) {
            $productName = $validated['productName'];
            $query->where(function ($q) use ($productName) {
                $q->where('productName', $productName)
                    ->orWhereHas('loanerMaster', function ($master) use ($productName) {
                        $master->where('productName', $productName);
                    })
                    ->orWhereHas('serviceRecord', function ($record) use ($productName) {
                        $record->where('productName', $productName);
                    });
            });
        }

        $startColumn = $this->resolveStartColumn();
        $endColumn = $this->resolveEndColumn();

        if (!empty($validated['start'])) {
            $query->where(function ($q) use ($validated, $startColumn, $endColumn) {
                $q->where($endColumn, '>=', $validated['start'])
                    ->orWhere(function ($inner) use ($validated, $startColumn, $endColumn) {
                        $inner->whereNull($endColumn)
                            ->where($startColumn, '>=', $validated['start']);
                    });
            });
        }

        if (!empty($validated['end'])) {
            $query->where($startColumn, '<=', $validated['end']);
        }

        $events = $query
            ->orderBy($startColumn)
            ->limit(500)
            ->get()
            ->map(function (AttachedLoaner $row) {
                $start = $row->calendar_start;
                if (!$start) {
                    return null;
                }

                $record = $row->serviceRecord;
                $master = $row->loanerMaster;
                $orderType = $record?->order_type ?? null;
                $statusRaw = $record?->getAttribute('status');
                $status = ($statusRaw !== null && $statusRaw !== '') ? (int) $statusRaw : null;
                $colors = $this->resolveEventColors($orderType, $status);
                $titleParts = array_filter([
                    $record?->productName ?? $row->productName ?? $master?->productName,
                    $master?->item,
                    $row->loanerID ? ('ID:' . $row->loanerID) : null,
                    $record?->dealer,
                    $record?->dealer_depart,
                    $record?->contactPerson,
                ], fn ($part) => $part !== null && $part !== '');

                return [
                    'id' => (string) $row->id,
                    'title' => implode(' / ', $titleParts) ?: ('予約 #' . $row->id),
                    'start' => $start,
                    'end' => $row->calendar_end,
                    'allDay' => true,
                    'color' => $colors['background'],
                    'backgroundColor' => $colors['background'],
                    'borderColor' => $colors['border'],
                    'textColor' => '#ffffff',
                    'classNames' => [$colors['class']],
                    'extendedProps' => [
                        'associatedID' => $row->associatedID,
                        'loanerID' => $row->loanerID,
                        'order_type' => $orderType,
                        'status' => $status,
                        'assignStatus' => $row->assignStatus ?? null,
                        'dealer' => $record?->dealer,
                        'dealer_depart' => $record?->dealer_depart,
                        'contactPerson' => $record?->contactPerson,
                        'email' => $record?->email,
                        'phone' => $record?->phone,
                        'productName' => $record?->productName ?? $row->productName ?? $master?->productName,
                        'item' => $master?->item,
                        'SN' => $record?->SN ?? $master?->SN,
                        'sentDate' => optional($row->sentDate)->format('Y-m-d'),
                        'returnedDate' => optional($row->returnedDate)->format('Y-m-d'),
                        'plannedSentDate' => optional($row->plannedSentDate)->format('Y-m-d'),
                        'plannedReturnedDate' => optional($row->plannedReturnedDate)->format('Y-m-d'),
                        'comment' => $row->comment,
                        'colorClass' => $colors['class'],
                    ],
                ];
            })
            ->filter()
            ->values();

        return response()->json(['events' => $events]);
    }

    /**
     * loaner 案件のみ StatusLoaner の processID_new で色分けする。
     * status 20: 案件未登録 → 赤
     * status 200以上: 出荷準備以降 → 青
     */
    private function resolveEventColors(?string $orderType, ?int $status): array
    {
        if ($orderType === 'loaner') {
            if ($status === 20) {
                return [
                    'background' => '#dc2626',
                    'border' => '#b91c1c',
                    'class' => 'loaner-status-20',
                ];
            }

            if ($status !== null && $status >= 200) {
                return [
                    'background' => '#2563eb',
                    'border' => '#1d4ed8',
                    'class' => 'loaner-status-200',
                ];
            }

            return [
                'background' => '#94a3b8',
                'border' => '#64748b',
                'class' => 'loaner-status-other',
            ];
        }

        if ($orderType === 'waiting_list') {
            return [
                'background' => '#d97706',
                'border' => '#b45309',
                'class' => 'loaner-status-waiting',
            ];
        }

        return [
            'background' => '#94a3b8',
            'border' => '#64748b',
            'class' => 'loaner-status-legacy',
        ];
    }

    private function loanerOptions()
    {
        return LoanerMaster::query()
            ->whereNotNull('loanerID')
            ->orderBy('productName')
            ->orderBy('loanerID')
            ->limit(500)
            ->get(['loanerID', 'productName', 'item', 'SN'])
            ->map(fn ($row) => [
                'loanerID' => $row->loanerID,
                'label' => trim(implode(' / ', array_filter([
                    $row->productName,
                    $row->item,
                    $row->SN ? ('SN:' . $row->SN) : null,
                    'ID:' . $row->loanerID,
                ]))),
            ])
            ->values();
    }

    private function resolveStartColumn(): string
    {
        $columns = Schema::getColumnListing('attachedloaners');
        if (in_array('plannedSentDate', $columns, true)) {
            return 'plannedSentDate';
        }
        return 'sentDate';
    }

    private function resolveEndColumn(): string
    {
        $columns = Schema::getColumnListing('attachedloaners');
        if (in_array('plannedReturnedDate', $columns, true)) {
            return 'plannedReturnedDate';
        }
        return 'returnedDate';
    }
}

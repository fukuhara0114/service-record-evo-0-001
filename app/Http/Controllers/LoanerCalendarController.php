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
        ]);

        $query = AttachedLoaner::query()
            ->with([
                'serviceRecord:orderID,productName,order_type,dealer,SN,status',
                'loanerMaster:loanerID,productName,item,SN',
            ]);

        if (!empty($validated['loanerID'])) {
            $query->where('loanerID', $validated['loanerID']);
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
                $orderType = $record?->order_type ?? 'loaner';
                $titleParts = array_filter([
                    $master?->productName ?? $record?->productName ?? $row->productName,
                    $master?->item,
                    $row->loanerID ? ('ID:' . $row->loanerID) : null,
                ]);

                return [
                    'id' => (string) $row->id,
                    'title' => implode(' / ', $titleParts) ?: ('予約 #' . $row->id),
                    'start' => $start,
                    'end' => $row->calendar_end,
                    'allDay' => true,
                    'backgroundColor' => $orderType === 'waiting_list' ? '#d97706' : '#2563eb',
                    'borderColor' => $orderType === 'waiting_list' ? '#b45309' : '#1d4ed8',
                    'extendedProps' => [
                        'associatedID' => $row->associatedID,
                        'loanerID' => $row->loanerID,
                        'order_type' => $orderType,
                        'assignStatus' => $row->assignStatus ?? null,
                        'dealer' => $record?->dealer,
                        'SN' => $master?->SN ?? $record?->SN,
                        'sentDate' => optional($row->sentDate)->format('Y-m-d'),
                        'returnedDate' => optional($row->returnedDate)->format('Y-m-d'),
                        'plannedSentDate' => optional($row->plannedSentDate)->format('Y-m-d'),
                        'plannedReturnedDate' => optional($row->plannedReturnedDate)->format('Y-m-d'),
                        'comment' => $row->comment,
                    ],
                ];
            })
            ->filter()
            ->values();

        return response()->json(['events' => $events]);
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

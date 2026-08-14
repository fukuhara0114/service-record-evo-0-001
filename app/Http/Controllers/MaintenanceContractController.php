<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceContractMaster;
use App\Models\MaintenanceContractType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceContractController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today()->toDateString();
        $scope = $request->input('scope', 'active') === 'all' ? 'all' : 'active';

        $filters = [
            'dealer' => trim((string) $request->input('dealer', '')),
            'endUser' => trim((string) $request->input('endUser', '')),
            'instrumentName' => trim((string) $request->input('instrumentName', '')),
            'SN' => trim((string) $request->input('SN', '')),
            'scope' => $scope,
        ];

        $query = MaintenanceContractMaster::query()
            ->with('maintenanceContractType:id,contractType,description');

        if ($scope === 'active') {
            $query->whereNotNull('expireDate')
                ->whereDate('expireDate', '>=', $today);
        }

        if ($filters['dealer'] !== '') {
            $query->where('dealer', 'like', $this->likeContains($filters['dealer']));
        }
        if ($filters['endUser'] !== '') {
            $query->where('endUser', 'like', $this->likeContains($filters['endUser']));
        }
        if ($filters['instrumentName'] !== '') {
            $query->where('instrumentName', 'like', $this->likeContains($filters['instrumentName']));
        }
        if ($filters['SN'] !== '') {
            $query->where('SN', 'like', $this->likeContains($filters['SN']));
        }

        $contracts = $query
            ->orderByRaw('CASE WHEN expireDate IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expireDate')
            ->orderBy('id')
            ->paginate(100)
            ->withQueryString()
            ->through(fn (MaintenanceContractMaster $row) => $this->serializeListRow($row));

        return Inertia::render('MaintenanceContractList', [
            'contracts' => $contracts,
            'filterDate' => $today,
            'filters' => $filters,
        ]);
    }

    public function edit(int $id)
    {
        $contract = MaintenanceContractMaster::query()
            ->with('maintenanceContractType:id,contractType,description')
            ->findOrFail($id);

        return Inertia::render('MaintenanceContractDetail', [
            'contract' => $this->serializeDetail($contract),
            'contractTypes' => MaintenanceContractType::query()
                ->orderBy('id')
                ->get(['id', 'contractType', 'description']),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $contract = MaintenanceContractMaster::query()->findOrFail($id);

        $validated = $request->validate([
            'dealer' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'contact' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'endUser' => 'nullable|string|max:255',
            'endUser_depart' => 'nullable|string|max:255',
            'endUser_contact' => 'nullable|string|max:255',
            'endUser_phone' => 'nullable|string|max:255',
            'endUser_email' => 'nullable|string|max:255',
            'endUser_address' => 'nullable|string|max:1000',
            'instrumentName' => 'nullable|string|max:255',
            'SN' => 'nullable|string|max:255',
            'shippingDate' => 'nullable|date',
            'yayoi_PO' => 'nullable|string|max:255',
            'orderedDate' => 'nullable|date',
            'mapics_PO' => 'nullable|string|max:255',
            'invoice_num' => 'nullable|string|max:255',
            'startDate' => 'nullable|date',
            'expireDate' => 'nullable|date',
            'certificationTicket' => 'nullable|string|max:255',
            'certificationExpireDate' => 'nullable|date',
            'renewalInformation' => 'nullable|string|max:2000',
            'informedDate' => 'nullable|date',
            'renewedDate' => 'nullable|date',
            'contractType' => 'nullable|integer',
            'informed' => 'nullable|boolean',
            'amount' => 'nullable|numeric',
            'status' => 'nullable|string|max:255',
            'RefNumber' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'additional_information' => 'nullable|string|max:5000',
        ]);

        foreach ($validated as $key => $value) {
            if ($value === '') {
                $validated[$key] = null;
            }
        }

        $validated['lastEditPerson'] = trim((string) (auth()->user()?->kanji_name ?? auth()->user()?->name ?? ''));
        $validated['lastEditDate'] = now();

        $contract->fill($validated);
        $contract->save();
        $contract->load('maintenanceContractType:id,contractType,description');

        return response()->json([
            'message' => '保守契約を更新しました。',
            'contract' => $this->serializeDetail($contract),
        ]);
    }

    private function likeContains(string $value): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return '%' . $escaped . '%';
    }

    private function serializeListRow(MaintenanceContractMaster $row): array
    {
        return [
            'id' => $row->id,
            'dealer' => $row->dealer,
            'endUser' => $row->endUser,
            'instrumentName' => $row->instrumentName,
            'SN' => $row->SN,
            'contractType' => $row->contractType,
            'contractTypeName' => $row->maintenanceContractType?->contractType,
            'contractTypeDescription' => $row->maintenanceContractType?->description,
            'startDate' => optional($row->startDate)->format('Y-m-d'),
            'expireDate' => optional($row->expireDate)->format('Y-m-d'),
            'certificationExpireDate' => optional($row->certificationExpireDate)->format('Y-m-d'),
            'status' => $row->status,
            'amount' => $row->amount,
            'RefNumber' => $row->RefNumber,
        ];
    }

    private function serializeDetail(MaintenanceContractMaster $row): array
    {
        return [
            'id' => $row->id,
            'dealer' => $row->dealer,
            'branch' => $row->branch,
            'contact' => $row->contact,
            'phone' => $row->phone,
            'email' => $row->email,
            'address' => $row->address,
            'endUser' => $row->endUser,
            'endUser_depart' => $row->endUser_depart,
            'endUser_contact' => $row->endUser_contact,
            'endUser_phone' => $row->endUser_phone,
            'endUser_email' => $row->endUser_email,
            'endUser_address' => $row->endUser_address,
            'instrumentName' => $row->instrumentName,
            'SN' => $row->SN,
            'shippingDate' => optional($row->shippingDate)->format('Y-m-d'),
            'yayoi_PO' => $row->yayoi_PO,
            'orderedDate' => optional($row->orderedDate)->format('Y-m-d'),
            'mapics_PO' => $row->mapics_PO,
            'invoice_num' => $row->invoice_num,
            'startDate' => optional($row->startDate)->format('Y-m-d'),
            'expireDate' => optional($row->expireDate)->format('Y-m-d'),
            'certificationTicket' => $row->certificationTicket,
            'certificationExpireDate' => optional($row->certificationExpireDate)->format('Y-m-d'),
            'renewalInformation' => $row->renewalInformation,
            'informedDate' => optional($row->informedDate)->format('Y-m-d'),
            'renewedDate' => optional($row->renewedDate)->format('Y-m-d'),
            'contractType' => $row->contractType,
            'contractTypeName' => $row->maintenanceContractType?->contractType,
            'contractTypeDescription' => $row->maintenanceContractType?->description,
            'informed' => (bool) $row->informed,
            'amount' => $row->amount,
            'status' => $row->status,
            'RefNumber' => $row->RefNumber,
            'description' => $row->description,
            'additional_information' => $row->additional_information,
            'lastEditPerson' => $row->lastEditPerson,
            'lastEditDate' => optional($row->lastEditDate)?->format('Y-m-d H:i:s'),
        ];
    }
}

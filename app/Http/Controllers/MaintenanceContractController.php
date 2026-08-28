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
            'expireDateFrom' => $this->normalizeDateInput($request->input('expireDateFrom')),
            'expireDateTo' => $this->normalizeDateInput($request->input('expireDateTo')),
            'certificationExpireDateFrom' => $this->normalizeDateInput($request->input('certificationExpireDateFrom')),
            'certificationExpireDateTo' => $this->normalizeDateInput($request->input('certificationExpireDateTo')),
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

        $this->applyDateRangeFilter(
            $query,
            'expireDate',
            $filters['expireDateFrom'],
            $filters['expireDateTo'],
        );
        $this->applyDateRangeFilter(
            $query,
            'certificationExpireDate',
            $filters['certificationExpireDateFrom'],
            $filters['certificationExpireDateTo'],
        );

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

        $validated = $this->validateContractPayload($request);

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

    /**
     * 詳細画面の「複製を保存」:
     * 選択されたセクションの値だけをコピーして新規 Maintenance Contract を作成する。
     */
    public function duplicate(Request $request, int $id)
    {
        MaintenanceContractMaster::query()->findOrFail($id);

        $sectionRules = [
            'sections' => 'required|array',
            'sections.product' => 'required|boolean',
            'sections.contract' => 'required|boolean',
            'sections.order' => 'required|boolean',
            'sections.dealer' => 'required|boolean',
            'sections.endUser' => 'required|boolean',
            'sections.description' => 'required|boolean',
            'sections.additional_information' => 'required|boolean',
        ];

        $sectionInput = $request->validate($sectionRules);
        $sections = $sectionInput['sections'];

        if (! collect($sections)->contains(true)) {
            return response()->json([
                'message' => 'コピーする項目を1つ以上選択してください。',
            ], 422);
        }

        $payload = $this->validateContractPayload($request);
        $attributes = $this->attributesForDuplicateSections($payload, $sections);
        $attributes['lastEditPerson'] = trim((string) (auth()->user()?->kanji_name ?? auth()->user()?->name ?? ''));
        $attributes['lastEditDate'] = now();

        $contract = new MaintenanceContractMaster();
        $contract->fill($attributes);
        $contract->save();
        $contract->load('maintenanceContractType:id,contractType,description');

        return response()->json([
            'message' => '保守契約を複製しました。',
            'contract' => $this->serializeDetail($contract),
        ], 201);
    }

    private function validateContractPayload(Request $request): array
    {
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

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, bool>  $sections
     * @return array<string, mixed>
     */
    private function attributesForDuplicateSections(array $payload, array $sections): array
    {
        $map = [
            'product' => ['instrumentName', 'SN', 'status'],
            'contract' => [
                'RefNumber',
                'contractType',
                'amount',
                'startDate',
                'expireDate',
                'certificationTicket',
                'certificationExpireDate',
            ],
            'order' => [
                'informedDate',
                'informed',
                'renewalInformation',
                'renewedDate',
                'shippingDate',
                'orderedDate',
                'yayoi_PO',
                'mapics_PO',
                'invoice_num',
            ],
            'dealer' => ['dealer', 'branch', 'contact', 'phone', 'email', 'address'],
            'endUser' => [
                'endUser',
                'endUser_depart',
                'endUser_contact',
                'endUser_phone',
                'endUser_email',
                'endUser_address',
            ],
            'description' => ['description'],
            'additional_information' => ['additional_information'],
        ];

        $attributes = [];
        foreach ($map as $section => $keys) {
            if (empty($sections[$section])) {
                continue;
            }
            foreach ($keys as $key) {
                if (array_key_exists($key, $payload)) {
                    $attributes[$key] = $payload[$key];
                }
            }
        }

        return $attributes;
    }

    /**
     * 新規案件作成画面用:
     * productName先頭5文字（instrumentName 前方一致） / SN 完全一致 / dealer 部分一致 をすべて適用
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'productName' => 'required|string|max:255',
            'SN' => 'required|string|max:255',
            'dealer' => 'required|string|max:255',
            'match' => 'nullable|in:contains,prefix',
        ]);

        $productName = trim((string) $validated['productName']);
        $sn = trim((string) $validated['SN']);
        $dealer = trim((string) $validated['dealer']);
        $contains = ($validated['match'] ?? '') === 'contains';

        if ($productName === '' || $sn === '' || $dealer === '') {
            return response()->json([
                'message' => 'productName / SN / dealer をすべて入力してください。',
                'contracts' => [],
            ], 422);
        }

        $today = Carbon::today()->toDateString();
        $query = MaintenanceContractMaster::query()
            ->with('maintenanceContractType:id,contractType,description')
            ->whereNotNull('expireDate')
            ->whereDate('expireDate', '>', $today);

        if ($contains) {
            $query
                ->whereRaw('LOWER(instrumentName) LIKE ?', [$this->likeContains(mb_strtolower($productName, 'UTF-8'))])
                ->whereRaw('LOWER(SN) LIKE ?', [$this->likeContains(mb_strtolower($sn, 'UTF-8'))])
                ->whereRaw('LOWER(dealer) LIKE ?', [$this->likeContains(mb_strtolower($dealer, 'UTF-8'))]);
        } else {
            $productPrefix = mb_substr($productName, 0, 5);
            $query
                ->where('instrumentName', 'like', $this->likePrefix($productPrefix))
                ->where('SN', $sn)
                ->where('dealer', 'like', $this->likeContains($dealer));
        }

        $contracts = $query
            ->orderBy('expireDate')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (MaintenanceContractMaster $row) => $this->serializeListRow($row))
            ->values();

        return response()->json([
            'contracts' => $contracts,
            'count' => $contracts->count(),
            'filters' => [
                'match' => $contains ? 'contains' : 'prefix',
                'productName' => $productName,
                'SN' => $sn,
                'dealer' => $dealer,
                'expireDateAfter' => $today,
            ],
        ]);
    }

    private function normalizeDateInput(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    private function applyDateRangeFilter($query, string $column, string $from, string $to): void
    {
        if ($from !== '') {
            $query->whereNotNull($column)->whereDate($column, '>=', $from);
        }
        if ($to !== '') {
            $query->whereNotNull($column)->whereDate($column, '<=', $to);
        }
    }

    private function likeContains(string $value): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return '%' . $escaped . '%';
    }

    private function likePrefix(string $value): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return $escaped . '%';
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

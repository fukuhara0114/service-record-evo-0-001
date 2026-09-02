<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceContractMaster;
use App\Models\MaintenanceContractType;
use App\Services\MaintenanceContractCertificatePdfService;
use App\Services\MaintenanceContractCertificationTicketPdfService;
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

    /**
     * 詳細画面の「保守サービス保証書」:
     * テンプレート PDF に詳細内容を追記してプレビュー / ダウンロードする。
     */
    public function certificate(Request $request, int $id, MaintenanceContractCertificatePdfService $pdfService)
    {
        $contract = MaintenanceContractMaster::query()->findOrFail($id);

        $payload = [
            'RefNumber' => $contract->RefNumber,
            'instrumentName' => $contract->instrumentName,
            'SN' => $contract->SN,
            'startDate' => optional($contract->startDate)->format('Y-m-d'),
            'expireDate' => optional($contract->expireDate)->format('Y-m-d'),
            'endUser' => $contract->endUser,
            'endUser_depart' => $contract->endUser_depart,
            'endUser_address' => $contract->endUser_address,
            'endUser_phone' => $contract->endUser_phone,
            'dealer' => $contract->dealer,
            'branch' => $contract->branch,
            'contact' => $contract->contact,
            'phone' => $contract->phone,
        ];

        // 未保存の画面値があれば優先（プレビュー時点のフォーム内容を反映）
        $overrides = $request->validate([
            'RefNumber' => 'nullable|string|max:255',
            'instrumentName' => 'nullable|string|max:255',
            'SN' => 'nullable|string|max:255',
            'startDate' => 'nullable|date',
            'expireDate' => 'nullable|date',
            'endUser' => 'nullable|string|max:255',
            'endUser_depart' => 'nullable|string|max:255',
            'endUser_address' => 'nullable|string|max:1000',
            'endUser_phone' => 'nullable|string|max:255',
            'dealer' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'contact' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
        ]);
        foreach ($overrides as $key => $value) {
            if ($request->exists($key)) {
                $payload[$key] = $value;
            }
        }

        try {
            $binary = $pdfService->generate($payload);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => '保守サービス保証書 PDF の生成に失敗しました。',
                'error' => $e->getMessage(),
            ], 500);
        }

        $ref = trim((string) ($payload['RefNumber'] ?? ''));
        $filename = $ref !== ''
            ? 'maintenance_contract-'.$ref.'.pdf'
            : 'maintenance_contract-'.$contract->id.'.pdf';

        $wantPng = $request->query('format') === 'png'
            || str_contains((string) $request->header('Accept', ''), 'image/png');

        if ($wantPng) {
            try {
                $png = $pdfService->pdfToPng($binary);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => '保守サービス保証書プレビュー画像の生成に失敗しました。',
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
            'X-Filename' => $filename,
        ]);
    }

    /**
     * 詳細画面の「再校正チケット」:
     * テンプレート PDF に詳細内容を追記し、有効年数分のページを生成する。
     */
    public function certificationTicket(Request $request, int $id, MaintenanceContractCertificationTicketPdfService $pdfService)
    {
        $contract = MaintenanceContractMaster::query()
            ->with('maintenanceContractType:id,contractType,description')
            ->findOrFail($id);

        $payload = [
            'RefNumber' => $contract->RefNumber,
            'instrumentName' => $contract->instrumentName,
            'SN' => $contract->SN,
            'startDate' => optional($contract->startDate)->format('Y-m-d'),
            'expireDate' => optional($contract->expireDate)->format('Y-m-d'),
            'dealer' => $contract->dealer,
            'branch' => $contract->branch,
            'phone' => $contract->phone,
            'description' => $contract->description,
            'additional_information' => $contract->additional_information,
            'contractTypeDescription' => $contract->maintenanceContractType?->description,
        ];

        $overrides = $request->validate([
            'RefNumber' => 'nullable|string|max:255',
            'instrumentName' => 'nullable|string|max:255',
            'SN' => 'nullable|string|max:255',
            'startDate' => 'nullable|date',
            'expireDate' => 'nullable|date',
            'dealer' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'additional_information' => 'nullable|string|max:5000',
            'contractTypeDescription' => 'nullable|string|max:5000',
        ]);
        foreach ($overrides as $key => $value) {
            if ($request->exists($key)) {
                $payload[$key] = $value;
            }
        }

        try {
            $binary = $pdfService->generate($payload);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => '再校正チケット PDF の生成に失敗しました。',
                'error' => $e->getMessage(),
            ], 500);
        }

        $ref = trim((string) ($payload['RefNumber'] ?? ''));
        $filename = $ref !== ''
            ? 'maintenance_contract-'.$ref.'.pdf'
            : 'certification_ticket-'.$contract->id.'.pdf';

        $wantPreview = $request->query('format') === 'preview';

        if ($wantPreview) {
            try {
                $pages = $pdfService->pdfToPngPages($binary);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => '再校正チケットプレビュー画像の生成に失敗しました。',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return response()->json([
                'pages' => array_map(
                    static fn (string $png, int $index): array => [
                        'page' => $index + 1,
                        'image' => base64_encode($png),
                    ],
                    $pages,
                    array_keys($pages),
                ),
            ], 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Filename' => $filename,
        ]);
    }

    private function validateContractPayload(Request $request): array
    {
        // DB型に合わせて空文字・不正日付・tinyintフラグを正規化してから検証する（テーブル変更なし）
        $dateKeys = [
            'shippingDate',
            'orderedDate',
            'startDate',
            'expireDate',
            'certificationExpireDate',
            'renewalInformation',
            'informedDate',
            'renewedDate',
        ];

        $normalized = $request->all();
        foreach ($dateKeys as $key) {
            if (array_key_exists($key, $normalized)) {
                $normalized[$key] = $this->normalizeNullableDate($normalized[$key]);
            }
        }
        if (array_key_exists('certificationTicket', $normalized)) {
            $normalized['certificationTicket'] = $this->normalizeNullableBoolean($normalized['certificationTicket']);
        }
        if (array_key_exists('informed', $normalized)) {
            $normalized['informed'] = $this->normalizeNullableBoolean($normalized['informed']);
        }
        $request->merge($normalized);

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
            // DB: tinyint(1)
            'certificationTicket' => 'nullable|boolean',
            'certificationExpireDate' => 'nullable|date',
            // DB: date
            'renewalInformation' => 'nullable|date',
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

        if (array_key_exists('certificationTicket', $validated) && $validated['certificationTicket'] !== null) {
            $validated['certificationTicket'] = $validated['certificationTicket'] ? 1 : 0;
        }
        if (array_key_exists('informed', $validated) && $validated['informed'] !== null) {
            $validated['informed'] = $validated['informed'] ? 1 : 0;
        }

        return $validated;
    }

    private function normalizeNullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // MySQL のゼロ日付・プレースホルダ日付は未設定として扱う
        if (preg_match('/^0{4}-0{2}-0{2}/', $raw) === 1) {
            return null;
        }

        try {
            $date = Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }

        if ((int) $date->format('Y') < 1901) {
            return null;
        }

        return $date->toDateString();
    }

    private function normalizeNullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) === 1;
        }

        $raw = strtolower(trim((string) $value));
        if (in_array($raw, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }
        if (in_array($raw, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }

        return null;
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
            'certificationTicket' => (bool) $row->certificationTicket,
            'certificationExpireDate' => optional($row->certificationExpireDate)->format('Y-m-d'),
            'renewalInformation' => optional($row->renewalInformation)->format('Y-m-d'),
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

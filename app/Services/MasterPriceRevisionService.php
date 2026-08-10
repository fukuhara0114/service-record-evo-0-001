<?php

namespace App\Services;

use App\Models\LoanerMaster;
use App\Models\PartMaster;
use App\Models\ServiceMaster;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * servicemaster / partmaster / loanermaster を同一改定日で同時に版切替する。
 *
 * - 主キーは各テーブルの surrogate `id`（価格版の行ID）
 * - 業務キー（serviceID / partID / loanerID）は版をまたいで同一
 * - loaner は一個体につき一つの loanerID（servicerecord.loanerID と紐づく）
 * - 業務キー空欄の入力は MAX+1 で自動採番して新規として挿入する
 */
class MasterPriceRevisionService
{
    public const OPEN_END_DATE = '2099-12-31';

    public function __construct(
        private readonly MasterPriceVersionResolver $resolver,
    ) {}

    /**
     * 画面表示用: 各キーの現行（最新）版一覧。
     */
    public function currentSnapshots(): array
    {
        $loanerIdRepair = $this->ensureUniqueOpenLoanerIds();

        return [
            'services' => $this->latestServiceRows(),
            'parts' => $this->latestPartRows(),
            'loaners' => $this->latestLoanerRows(),
            'meta' => [
                'partHasSurrogateId' => true,
                'serviceHasSurrogateId' => true,
                'loanerHasSurrogateId' => true,
                'loanerIdRepair' => $loanerIdRepair,
            ],
        ];
    }

    /**
     * 有効な loaner 個体のうち loanerID 空欄だけを自動採番する。
     *
     * 注意: 同一 loanerID の複数行は「価格版」であり別個体ではない。
     * serviceID / partID と同様、版をまたいで loanerID は維持する（振り直ししない）。
     *
     * @return array{assignedNull:int,reassignedDuplicates:int}
     */
    public function ensureUniqueOpenLoanerIds(): array
    {
        return DB::transaction(function () {
            $today = Carbon::today()->toDateString();
            $openNullRows = DB::table('loanermaster')
                ->where(function ($query) use ($today) {
                    $query->whereNull('validDateMax')
                        ->orWhere('validDateMax', '>=', $today);
                })
                ->where(function ($query) {
                    $query->whereNull('loanerID')
                        ->orWhere('loanerID', '');
                })
                ->orderBy('id')
                ->get(['id', 'loanerID']);

            $next = $this->nextBusinessId('loanermaster', 'loanerID');
            $assignedNull = 0;

            foreach ($openNullRows as $row) {
                DB::table('loanermaster')->where('id', $row->id)->update(['loanerID' => $next]);
                $next++;
                $assignedNull++;
            }

            return [
                'assignedNull' => $assignedNull,
                // 互換のためキーは残すが、版の loanerID は振り直さない
                'reassignedDuplicates' => 0,
            ];
        });
    }

    /**
     * @param  array{effectiveDate:string,services?:array,parts?:array,loaners?:array}  $payload
     */
    public function revise(array $payload): array
    {
        $effective = Carbon::parse($payload['effectiveDate'])->startOfDay();
        $closeDate = $effective->copy()->subDay()->toDateString();
        $openDate = $effective->toDateString();
        $openEnd = self::OPEN_END_DATE;

        [$servicesExisting, $servicesNew] = $this->partitionByBusinessKey(
            $payload['services'] ?? [],
            'serviceID'
        );
        [$partsExisting, $partsNew] = $this->partitionByBusinessKey(
            $payload['parts'] ?? [],
            'partID'
        );
        [$loanersExisting, $loanersNew] = $this->partitionByBusinessKey(
            $payload['loaners'] ?? [],
            'loanerID'
        );

        return DB::transaction(function () use (
            $servicesExisting,
            $servicesNew,
            $partsExisting,
            $partsNew,
            $loanersExisting,
            $loanersNew,
            $closeDate,
            $openDate,
            $openEnd,
        ) {
            $this->ensureUniqueOpenLoanerIds();

            $serviceCount = 0;
            $partCount = 0;
            $loanerCount = 0;
            $serviceCreated = 0;
            $partCreated = 0;
            $loanerCreated = 0;

            foreach ($this->latestServiceRows(false) as $current) {
                $input = $servicesExisting->get((string) $current['serviceID'], []);
                $this->closeAndInsertService($current, $input, $closeDate, $openDate, $openEnd);
                $serviceCount++;
            }

            foreach ($this->latestPartRows(false) as $current) {
                $input = $partsExisting->get((string) $current['partID'], []);
                $this->closeAndInsertPart($current, $input, $closeDate, $openDate, $openEnd);
                $partCount++;
            }

            foreach ($this->latestLoanerRows(false) as $current) {
                $input = $loanersExisting->get((string) $current['loanerID'], []);
                $this->closeAndInsertLoaner($current, $input, $closeDate, $openDate, $openEnd);
                $loanerCount++;
            }

            $nextServiceId = $this->nextBusinessId('servicemaster', 'serviceID');
            foreach ($servicesNew as $input) {
                $this->insertNewService($input, $nextServiceId, $openDate, $openEnd);
                $nextServiceId++;
                $serviceCreated++;
            }

            $nextPartId = $this->nextBusinessId('partmaster', 'partID');
            foreach ($partsNew as $input) {
                $this->insertNewPart($input, $nextPartId, $openDate, $openEnd);
                $nextPartId++;
                $partCreated++;
            }

            $nextLoanerId = $this->nextBusinessId('loanermaster', 'loanerID');
            foreach ($loanersNew as $input) {
                $explicitLoanerId = $input['loanerID'] ?? null;
                if ($explicitLoanerId === null || $explicitLoanerId === '' || (is_string($explicitLoanerId) && trim($explicitLoanerId) === '')) {
                    $assigned = $nextLoanerId;
                    $nextLoanerId++;
                } else {
                    $assigned = (int) $explicitLoanerId;
                    if ($assigned >= $nextLoanerId) {
                        $nextLoanerId = $assigned + 1;
                    }
                }
                $this->insertNewLoaner($input, $assigned, $openDate, $openEnd);
                $loanerCreated++;
            }

            return [
                'effectiveDate' => $openDate,
                'closedOn' => $closeDate,
                'counts' => [
                    'services' => $serviceCount,
                    'parts' => $partCount,
                    'loaners' => $loanerCount,
                    'servicesCreated' => $serviceCreated,
                    'partsCreated' => $partCreated,
                    'loanersCreated' => $loanerCreated,
                ],
            ];
        });
    }

    /**
     * @return array{0:Collection<string,array>,1:Collection<int,array>}
     */
    private function partitionByBusinessKey(array $rows, string $keyColumn): array
    {
        $existing = collect();
        $new = collect();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = $row[$keyColumn] ?? null;
            if ($key === null || $key === '' || (is_string($key) && trim($key) === '')) {
                $new->push($row);
                continue;
            }
            $existing->put((string) $key, $row);
        }

        return [$existing, $new];
    }

    private function nextBusinessId(string $table, string $column): int
    {
        return ((int) DB::table($table)->max($column)) + 1;
    }

    private function latestServiceRows(bool $forDisplay = true): Collection
    {
        $rows = ServiceMaster::query()
            ->when($forDisplay, fn ($q) => $q->where(function ($query) {
                $query->whereNull('productName')
                    ->orWhere('productName', 'not like', '*%');
            }))
            ->orderByDesc('validDateMin')
            ->orderByDesc('id')
            ->get();

        return $rows
            ->unique('serviceID')
            ->values()
            ->map(fn (ServiceMaster $row) => $this->serializeService($row));
    }

    private function latestPartRows(bool $forDisplay = true): Collection
    {
        return PartMaster::query()
            ->orderByDesc('validDateMin')
            ->orderByDesc('id')
            ->get()
            ->unique('partID')
            ->values()
            ->map(fn (PartMaster $row) => $this->serializePart($row));
    }

    private function latestLoanerRows(bool $forDisplay = true): Collection
    {
        // 一個体 = 一つの loanerID。価格版が複数ある場合は最新版のみ（loanerID は版をまたいで同一）。
        return $this->resolver->latestByKey(
            LoanerMaster::query()
                ->whereNotNull('loanerID')
                ->where('loanerID', '!=', ''),
            'loanerID'
        )
            ->sortBy([
                ['productName', 'asc'],
                ['item', 'asc'],
                ['SN', 'asc'],
                ['loanerID', 'asc'],
            ])
            ->values()
            ->map(fn (LoanerMaster $row) => $this->serializeLoaner($row));
    }

    private function serializeService(ServiceMaster $row): array
    {
        return [
            'id' => $row->id,
            'serviceID' => $row->serviceID,
            'productName' => $row->productName,
            'entityID' => $row->entityID,
            'priceC_0' => $row->priceC_0,
            'priceR_0' => $row->priceR_0,
            'priceC_1' => $row->priceC_1,
            'priceR_1' => $row->priceR_1,
            'priceC_2' => $row->priceC_2,
            'priceR_2' => $row->priceR_2,
            'priceC_3' => $row->priceC_3,
            'priceR_3' => $row->priceR_3,
            'priceR_onSite' => $row->priceR_onSite,
            'price_postData' => $row->price_postData,
            'price_a2la' => $row->price_a2la,
            'productType' => $row->productType,
            'note' => $row->note,
            'validDateMin' => $this->resolver->normalizeDate($row->validDateMin),
            'validDateMax' => $this->resolver->normalizeDate($row->validDateMax),
        ];
    }

    private function serializePart(PartMaster $row): array
    {
        return [
            'id' => $row->id,
            'partID' => $row->partID,
            'partName' => $row->partName,
            'description' => $row->description,
            'price_market' => $row->price_market,
            'price_discounted' => $row->price_discounted,
            'price_discounted_1' => $row->price_discounted_1,
            'associatedInstruments' => $row->associatedInstruments,
            'type' => $row->type,
            'note' => $row->note,
            'validDateMin' => $this->resolver->normalizeDate($row->validDateMin),
            'validDateMax' => $this->resolver->normalizeDate($row->validDateMax),
        ];
    }

    private function serializeLoaner(LoanerMaster $row): array
    {
        return [
            'id' => $row->id,
            'loanerID' => $row->loanerID,
            'productName' => $row->productName,
            'item' => $row->item,
            'SN' => $row->SN,
            'manageNum' => $row->manageNum,
            'groupName' => $row->groupName,
            'price' => $row->price,
            'validDateMin' => $this->resolver->normalizeDate($row->validDateMin),
            'validDateMax' => $this->resolver->normalizeDate($row->validDateMax),
        ];
    }

    private function closeAndInsertService(array $current, array $input, string $closeDate, string $openDate, string $openEnd): void
    {
        $this->assertCanOpenOn($openDate, 'servicemaster', 'serviceID', $current['serviceID']);
        $this->closeCurrentRows('servicemaster', 'serviceID', $current['serviceID'], $closeDate, $current['id'] ?? null);

        $payload = [
            'serviceID' => $current['serviceID'],
            'productName' => $current['productName'],
            'productType' => $current['productType'],
            'entityID' => $current['entityID'],
            'priceC_0' => $input['priceC_0'] ?? $current['priceC_0'],
            'priceR_0' => $input['priceR_0'] ?? $current['priceR_0'],
            'priceC_1' => $input['priceC_1'] ?? $current['priceC_1'],
            'priceR_1' => $input['priceR_1'] ?? $current['priceR_1'],
            'priceC_2' => $input['priceC_2'] ?? $current['priceC_2'],
            'priceR_2' => $input['priceR_2'] ?? $current['priceR_2'],
            'priceC_3' => $input['priceC_3'] ?? $current['priceC_3'],
            'priceR_3' => $input['priceR_3'] ?? $current['priceR_3'],
            'priceR_onSite' => $input['priceR_onSite'] ?? $current['priceR_onSite'],
            'price_postData' => $input['price_postData'] ?? $current['price_postData'],
            'price_a2la' => $input['price_a2la'] ?? $current['price_a2la'],
            'note' => $current['note'],
            'validDateMin' => $openDate,
            'validDateMax' => $openEnd,
        ];

        DB::table('servicemaster')->insert($this->filterExistingColumns('servicemaster', $payload));
    }

    private function closeAndInsertPart(array $current, array $input, string $closeDate, string $openDate, string $openEnd): void
    {
        $this->assertCanOpenOn($openDate, 'partmaster', 'partID', $current['partID']);
        $this->closeCurrentRows('partmaster', 'partID', $current['partID'], $closeDate, $current['id'] ?? null);

        $payload = [
            'partID' => $current['partID'],
            'partName' => $current['partName'] ?? '',
            'description' => $current['description'] ?? '',
            'price_market' => $input['price_market'] ?? $current['price_market'] ?? 0,
            'price_discounted' => $input['price_discounted'] ?? $current['price_discounted'] ?? 0,
            'price_discounted_1' => $input['price_discounted_1'] ?? $current['price_discounted_1'] ?? 0,
            'associatedInstruments' => $current['associatedInstruments'] ?? '',
            'type' => $current['type'] ?? '',
            'note' => $current['note'],
            'validDateMin' => $openDate,
            'validDateMax' => $openEnd,
        ];

        DB::table('partmaster')->insert($this->filterExistingColumns('partmaster', $payload));
    }

    private function closeAndInsertLoaner(array $current, array $input, string $closeDate, string $openDate, string $openEnd): void
    {
        $this->assertCanOpenOn($openDate, 'loanermaster', 'loanerID', $current['loanerID']);
        $this->closeCurrentRows('loanermaster', 'loanerID', $current['loanerID'], $closeDate, $current['id'] ?? null);

        // Eloquent の date cast だと 0000-00-00 が -0001-11-30 になり INSERT が失敗するため raw を使う
        $base = DB::table('loanermaster')->where('id', $current['id'])->first();
        $payload = [
            'loanerID' => $current['loanerID'],
            'item' => $base->item ?? $current['item'],
            'productName' => $current['productName'],
            'inventory' => $base->inventory ?? null,
            'manageNum' => $current['manageNum'],
            'SN' => $current['SN'],
            'certificatedDate' => $this->sanitizeSqlDate($base->certificatedDate ?? null),
            'currentStatus' => $base->currentStatus ?? '0',
            'note1' => $base->note1 ?? null,
            'note2' => $base->note2 ?? null,
            'note3' => $base->note3 ?? null,
            'sentDate' => $this->sanitizeSqlDate($base->sentDate ?? null),
            'returnedDate' => $this->sanitizeSqlDate($base->returnedDate ?? null),
            'book' => $base->book ?? null,
            'price' => $input['price'] ?? $current['price'],
            'associatedID' => $base->associatedID ?? null,
            'lastEditPerson' => auth()->user()?->kanji_name,
            'lastEditDate' => now(),
            'property' => ($base->property ?? null) ?: 'サービス',
            'groupName' => $current['groupName'] ?? ($base->groupName ?? ''),
            'validDateMin' => $openDate,
            'validDateMax' => $openEnd,
        ];

        DB::table('loanermaster')->insert($this->filterExistingColumns('loanermaster', $payload));

        // 共有項目（状態・ノート・日付など）は全版で同一にする
        LoanerMaster::syncSharedFieldsAcrossVersions($current['loanerID'], [
            'currentStatus' => $payload['currentStatus'] ?? null,
            'note1' => $payload['note1'] ?? null,
            'note2' => $payload['note2'] ?? null,
            'note3' => $payload['note3'] ?? null,
            'sentDate' => $payload['sentDate'] ?? null,
            'returnedDate' => $payload['returnedDate'] ?? null,
            'lastEditPerson' => $payload['lastEditPerson'] ?? null,
            'lastEditDate' => $payload['lastEditDate'] ?? null,
            'certificatedDate' => $payload['certificatedDate'] ?? null,
        ]);
    }

    /**
     * MySQL が拒否するゼロ日付・異常日付を null に正規化する。
     */
    private function sanitizeSqlDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            if ((int) $value->format('Y') < 1000) {
                return null;
            }

            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);
        if ($text === '' || str_starts_with($text, '0000-') || str_starts_with($text, '-')) {
            return null;
        }

        try {
            $parsed = Carbon::parse($text);
            if ((int) $parsed->format('Y') < 1000) {
                return null;
            }

            return $parsed->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function insertNewService(array $input, int $serviceId, string $openDate, string $openEnd): void
    {
        $productName = trim((string) ($input['productName'] ?? ''));
        if ($productName === '') {
            throw new RuntimeException('新規 service 行には productName が必要です（serviceID 空欄の行）。');
        }

        $payload = [
            'serviceID' => $serviceId,
            'productName' => $productName,
            'productType' => $input['productType'] ?? null,
            'entityID' => $input['entityID'] ?? null,
            'priceC_0' => $input['priceC_0'] ?? 0,
            'priceR_0' => $input['priceR_0'] ?? 0,
            'priceC_1' => $input['priceC_1'] ?? null,
            'priceR_1' => $input['priceR_1'] ?? null,
            'priceC_2' => $input['priceC_2'] ?? null,
            'priceR_2' => $input['priceR_2'] ?? null,
            'priceC_3' => $input['priceC_3'] ?? null,
            'priceR_3' => $input['priceR_3'] ?? null,
            'priceR_onSite' => $input['priceR_onSite'] ?? 0,
            'price_postData' => $input['price_postData'] ?? null,
            'price_a2la' => $input['price_a2la'] ?? 0,
            'note' => $input['note'] ?? null,
            'validDateMin' => $openDate,
            'validDateMax' => $openEnd,
        ];

        DB::table('servicemaster')->insert($this->filterExistingColumns('servicemaster', $payload));
    }

    private function insertNewPart(array $input, int $partId, string $openDate, string $openEnd): void
    {
        $partName = trim((string) ($input['partName'] ?? ''));
        if ($partName === '') {
            throw new RuntimeException('新規 part 行には partName が必要です（partID 空欄の行）。');
        }

        $payload = [
            'partID' => $partId,
            'partName' => $partName,
            // NOT NULL 列は空文字/0で埋める
            'description' => (string) ($input['description'] ?? ''),
            'price_market' => $input['price_market'] ?? 0,
            'price_discounted' => $input['price_discounted'] ?? 0,
            'price_discounted_1' => $input['price_discounted_1'] ?? 0,
            'associatedInstruments' => (string) ($input['associatedInstruments'] ?? ''),
            'type' => (string) ($input['type'] ?? ''),
            'note' => $input['note'] ?? null,
            'validDateMin' => $openDate,
            'validDateMax' => $openEnd,
        ];

        DB::table('partmaster')->insert($this->filterExistingColumns('partmaster', $payload));
    }

    private function insertNewLoaner(array $input, int $loanerId, string $openDate, string $openEnd): void
    {
        $productName = trim((string) ($input['productName'] ?? ''));
        if ($productName === '') {
            throw new RuntimeException('新規 loaner 行には productName が必要です（loanerID 空欄の行）。');
        }

        $payload = [
            'loanerID' => $loanerId,
            'item' => $input['item'] ?? null,
            'productName' => $productName,
            'SN' => $input['SN'] ?? null,
            'manageNum' => $input['manageNum'] ?? null,
            'groupName' => (string) ($input['groupName'] ?? ''),
            'price' => $input['price'] ?? 0,
            'currentStatus' => $input['currentStatus'] ?? '0',
            'property' => $input['property'] ?? 'サービス',
            'lastEditPerson' => auth()->user()?->kanji_name,
            'lastEditDate' => now(),
            'validDateMin' => $openDate,
            'validDateMax' => $openEnd,
        ];

        DB::table('loanermaster')->insert($this->filterExistingColumns('loanermaster', $payload));
    }

    private function closeCurrentRows(
        string $table,
        string $keyColumn,
        mixed $keyValue,
        string $closeDate,
        mixed $preferId = null,
    ): void {
        $query = DB::table($table)->where($keyColumn, $keyValue);

        if ($preferId !== null) {
            $query->where('id', $preferId);
        } else {
            $query->where(function ($builder) use ($closeDate) {
                $builder->whereNull('validDateMax')
                    ->orWhere('validDateMax', '>=', Carbon::parse($closeDate)->addDay()->toDateString());
            });
        }

        $updated = $query->update(['validDateMax' => $closeDate]);

        if ($updated < 1 && $preferId !== null) {
            DB::table($table)->where('id', $preferId)->update(['validDateMax' => $closeDate]);
        }
    }

    private function assertCanOpenOn(string $openDate, string $table, string $keyColumn, mixed $keyValue): void
    {
        $future = DB::table($table)
            ->where($keyColumn, $keyValue)
            ->where('validDateMin', '>', $openDate)
            ->exists();

        if ($future) {
            throw new RuntimeException("{$table}.{$keyColumn}={$keyValue} に改定日より未来の版が既に存在します。");
        }
    }

    private function filterExistingColumns(string $table, array $payload): array
    {
        $columns = Schema::getColumnListing($table);

        return collect($payload)
            ->filter(fn ($value, $key) => in_array($key, $columns, true))
            ->all();
    }
}

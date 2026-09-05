<?php

namespace App\Models;

use App\Support\LoanerStatusFlow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LoanerMaster extends Model
{
    protected $table = 'loanermaster002';

    // 版ごとのサロゲートキー（業務キーは loanerID）
    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    /**
     * 版をまたいで同一である必要がある項目。
     * いずれかが変わった場合、同じ loanerID の全版へ同期する。
     */
    public const SHARED_VERSION_FIELDS = [
        'currentStatus',
        'note1',
        'note2',
        'note3',
        'sentDate',
        'returnedDate',
        'lastEditPerson',
        'lastEditDate',
        'certificatedDate',
    ];

    protected $fillable = [
        'loanerID',
        'item',
        'productName',
        'inventory',
        'manageNum',
        'SN',
        'certificatedDate',
        'currentStatus',
        'note1',
        'note2',
        'note3',
        'sentDate',
        'returnedDate',
        'book',
        'price',
        'associatedID',
        'lastEditPerson',
        'lastEditDate',
        'property',
        'groupName',
        'validDateMin',
        'validDateMax',
    ];

    protected $casts = [
        'validDateMin' => 'date',
        'validDateMax' => 'date',
        'certificatedDate' => 'date',
        'sentDate' => 'date',
        'returnedDate' => 'date',
        'lastEditDate' => 'datetime',
    ];

    /** 機種選択一覧から除外する item 文言か */
    public static function isExcludedFromProductSelect(?string $item): bool
    {
        $text = (string) $item;

        return str_contains($text, '使用不可') || str_contains($text, 'サービス終了');
    }

    /**
     * 機種選択のグループキー。item（機種）を優先し、空のときだけ productName に倒す。
     */
    public static function productSelectGroupKey(?string $item, ?string $productName): string
    {
        $itemCode = trim((string) $item);
        if ($itemCode !== '') {
            return 'item:'.mb_strtolower($itemCode, 'UTF-8');
        }

        $name = trim((string) $productName);
        if ($name !== '') {
            return 'productName:'.mb_strtolower($name, 'UTF-8');
        }

        return '';
    }

    /**
     * 選択した機種（item 優先、なければ productName）と個体が一致するか。
     */
    public static function matchesProductSelection(?self $row, ?string $item, ?string $productName): bool
    {
        if (!$row) {
            return false;
        }

        $selectedItem = trim((string) $item);
        $rowItem = trim((string) ($row->item ?? ''));
        if ($selectedItem !== '') {
            return strcasecmp($rowItem, $selectedItem) === 0;
        }

        $selectedName = trim((string) $productName);
        if ($selectedName === '') {
            return false;
        }

        return strcasecmp(trim((string) ($row->productName ?? '')), $selectedName) === 0;
    }

    /**
     * 機種選択ダイアログ用: item 単位で在庫台数を集計する。
     *
     * @param  Collection<int, self>  $loaners
     * @return Collection<int, array<string, mixed>>
     */
    public static function groupForProductSelect(Collection $loaners, string $statusColumn): Collection
    {
        return $loaners
            ->filter(fn (self $row) => !static::isExcludedFromProductSelect($row->item ?? null))
            ->groupBy(fn (self $row) => static::productSelectGroupKey($row->item ?? null, $row->productName ?? null))
            ->filter(fn (Collection $rows, $key) => $key !== '' && $key !== null)
            ->map(function (Collection $rows, string $selectionKey) use ($statusColumn) {
                $availableRows = $rows->filter(
                    fn (self $row) => static::isInStockStatus($row->{$statusColumn} ?? null)
                );
                $availableCount = $availableRows->count();

                $item = $rows
                    ->map(fn (self $row) => trim((string) ($row->item ?? '')))
                    ->first(fn ($value) => $value !== '');

                $productSource = $availableRows->first() ?? $rows->first();
                $productName = trim((string) ($productSource?->productName ?? ''));

                return [
                    'selectionKey' => $selectionKey,
                    'item' => $item !== null && $item !== '' ? $item : null,
                    'productName' => $productName !== '' ? $productName : null,
                    'totalCount' => $rows->count(),
                    'availableCount' => $availableCount,
                    'available' => $availableCount > 0,
                    'order_type' => $availableCount > 0 ? 'loaner' : 'waiting_list',
                ];
            })
            ->values();
    }

    /**
     * 在庫か。currentStatus が数値の 0 のときだけ true。
     * 空文字 / null / 1（旧フラグ）は在庫ではない。
     */
    public static function isInStockStatus(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (!is_numeric($value)) {
            return false;
        }

        return (int) $value === 0;
    }

    protected static bool $syncingSharedFields = false;

    protected static function booted(): void
    {
        // 新規版は既存版の currentStatus を継承する（有効期間切替で状態が割れないようにする）
        static::creating(function (LoanerMaster $master) {
            if ($master->loanerID === null || $master->loanerID === '') {
                return;
            }

            $existing = static::canonicalCurrentStatus($master->loanerID);
            if ($existing !== null && $existing !== '') {
                $master->setAttribute('currentStatus', $existing);
            }
        });

        static::saved(function (LoanerMaster $master) {
            if (static::$syncingSharedFields) {
                return;
            }

            if ($master->loanerID === null || $master->loanerID === '') {
                return;
            }

            $changed = [];
            foreach (static::SHARED_VERSION_FIELDS as $field) {
                if ($master->wasChanged($field)) {
                    $changed[$field] = $master->getAttribute($field);
                }
            }

            // currentStatus の手動変更は同一 loanerID の全版へ反映する。
            // 案件 status との同期は ServiceRecord 保存時に行う（ここでは上書きしない）。
            if ($master->wasChanged('currentStatus')) {
                static::unifyCurrentStatus($master->loanerID, $master->getAttribute('currentStatus'));
                unset($changed['currentStatus']);
            } elseif (
                $master->wasRecentlyCreated
                && $master->getAttribute('currentStatus') !== null
                && $master->getAttribute('currentStatus') !== ''
            ) {
                static::unifyCurrentStatus($master->loanerID, $master->getAttribute('currentStatus'));
                unset($changed['currentStatus']);
            }

            if ($changed === []) {
                return;
            }

            static::syncSharedFieldsAcrossVersions(
                $master->loanerID,
                $changed,
                $master->getKey(),
            );
        });
    }

    /**
     * Loaner 案件の status を、紐づく loanermaster.currentStatus へ同期する。
     * 完了(400)以上のときは在庫(0)。
     */
    public static function syncCurrentStatusFromLoanerRecord(ServiceRecord $record, mixed $loanerId = null): void
    {
        if (ServiceRecord::normalizeOrderType($record->order_type) !== 'loaner') {
            return;
        }

        $id = $loanerId ?? $record->loanerID;
        if ($id === null || $id === '' || (int) $id === 0) {
            return;
        }

        if ($record->status === null || $record->status === '') {
            return;
        }

        static::unifyCurrentStatus(
            $id,
            LoanerStatusFlow::masterCurrentStatusFromCaseStatus($record->status),
        );
    }

    /**
     * 紐づく loanermaster.associatedID に当該案件の orderID を書く。
     * loaner 案件 → その loaner 案件の orderID。
     * service && RMA=loaner → その service 案件の orderID。
     */
    public static function assignAssociatedOrderId(ServiceRecord $record, mixed $loanerId = null): void
    {
        if (! LoanerStatusFlow::shouldBindMasterAssociatedIdOnSave($record->order_type, $record->RMA)) {
            return;
        }

        $orderId = $record->orderID;
        if ($orderId === null || $orderId === '' || (int) $orderId === 0) {
            return;
        }

        try {
            if (! Schema::hasColumn((new static)->getTable(), 'associatedID')) {
                return;
            }

            $ids = $loanerId !== null && $loanerId !== '' && (int) $loanerId !== 0
                ? [$loanerId]
                : static::linkedLoanerIdsForRecord($record);

            foreach ($ids as $id) {
                if ($id === null || $id === '' || (int) $id === 0) {
                    continue;
                }

                static::query()
                    ->where('loanerID', $id)
                    ->update(['associatedID' => (int) $orderId]);
            }
        } catch (\Throwable $e) {
            Log::error('loanermaster associatedID の書き込みに失敗しました', [
                'orderID' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 案件に紐づく loanerID 一覧。
     * loaner 案件は自身の個体のみ。旧貸出（service && RMA=loaner）は子 loaner / attached も含む。
     *
     * @return list<int|string>
     */
    public static function linkedLoanerIdsForRecord(ServiceRecord $record): array
    {
        $ids = collect();

        $ownId = $record->loanerID;
        if ($ownId !== null && $ownId !== '' && (int) $ownId !== 0) {
            $ids->push($ownId);
        }

        $orderId = $record->orderID;
        if ($orderId === null || $orderId === '' || (int) $orderId === 0) {
            return $ids
                ->filter(fn ($id) => $id !== null && $id !== '' && (int) $id !== 0)
                ->unique()
                ->values()
                ->all();
        }

        $ids = $ids->concat(
            AttachedLoaner::query()
                ->where('associatedID', $record->orderID)
                ->whereNotNull('loanerID')
                ->where('loanerID', '!=', '')
                ->pluck('loanerID'),
        );

        $isLoanerCase = ServiceRecord::normalizeOrderType($record->order_type) === 'loaner';
        $isLegacyServiceLoaner = LoanerStatusFlow::isServiceLikeOrderType($record->order_type)
            && LoanerStatusFlow::isLoanerRma($record->RMA);

        if ($isLegacyServiceLoaner && ! $isLoanerCase) {
            $ids = $ids->concat(
                ServiceRecord::query()
                    ->where('parentID', $record->orderID)
                    ->where('order_type', 'loaner')
                    ->whereNotNull('loanerID')
                    ->where('loanerID', '!=', '')
                    ->pluck('loanerID'),
            );

            if (Schema::hasColumn((new static)->getTable(), 'associatedID')) {
                $ids = $ids->concat(
                    static::query()
                        ->where('associatedID', $record->orderID)
                        ->whereNotNull('loanerID')
                        ->where('loanerID', '!=', '')
                        ->pluck('loanerID'),
                );
            }
        }

        return $ids
            ->filter(fn ($id) => $id !== null && $id !== '' && (int) $id !== 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * この個体を使う他のアクティブ loaner 案件が無ければ currentStatus を在庫(0)へ戻す。
     */
    public static function releaseCurrentStatusIfUnlinked(mixed $loanerId, mixed $exceptOrderId = null): void
    {
        if ($loanerId === null || $loanerId === '' || (int) $loanerId === 0) {
            return;
        }

        $query = ServiceRecord::query()
            ->where('order_type', 'loaner')
            ->where('loanerID', $loanerId)
            ->where('status', '>=', LoanerStatusFlow::STOCK)
            ->where('status', '<', LoanerStatusFlow::COMPLETE);

        if ($exceptOrderId !== null && $exceptOrderId !== '') {
            $query->where('orderID', '!=', $exceptOrderId);
        }

        if ($query->exists()) {
            return;
        }

        static::unifyCurrentStatus($loanerId, LoanerStatusFlow::STOCK);
    }

    /**
     * 紐づく Loaner 案件があるとき、その status に合わせて currentStatus を上書きする。
     * アクティブ案件（0以上400未満）を優先し、完了(400)以上だけなら在庫(0)。
     */
    public static function applyLinkedLoanerCaseCurrentStatus(mixed $loanerId): bool
    {
        $required = static::currentStatusRequiredByLinkedLoanerCase($loanerId);
        if ($required === null) {
            return false;
        }

        static::unifyCurrentStatus($loanerId, $required);

        return true;
    }

    /**
     * 紐づく Loaner 案件が要求する currentStatus。案件が無ければ null（手動値を維持）。
     */
    public static function currentStatusRequiredByLinkedLoanerCase(mixed $loanerId): ?int
    {
        if ($loanerId === null || $loanerId === '' || (int) $loanerId === 0) {
            return null;
        }

        $cases = ServiceRecord::query()
            ->where('order_type', 'loaner')
            ->where('loanerID', $loanerId)
            ->orderByDesc('lastEditDate')
            ->orderByDesc('orderID')
            ->get(['status']);

        if ($cases->isEmpty()) {
            return null;
        }

        $active = $cases->first(function (ServiceRecord $row) {
            $status = (int) $row->status;

            return $status >= LoanerStatusFlow::STOCK && $status < LoanerStatusFlow::COMPLETE;
        });

        if ($active && $active->status !== null && $active->status !== '') {
            return LoanerStatusFlow::masterCurrentStatusFromCaseStatus($active->status);
        }

        $completed = $cases->contains(
            fn (ServiceRecord $row) => LoanerStatusFlow::isCompleteOrBeyond($row->status),
        );

        return $completed ? LoanerStatusFlow::STOCK : null;
    }

    /**
     * 同じ loanerID の全版の currentStatus を同一値にする。
     */
    public static function unifyCurrentStatus(mixed $loanerId, mixed $status): int
    {
        if ($loanerId === null || $loanerId === '') {
            return 0;
        }

        if (!Schema::hasColumn((new static)->getTable(), 'currentStatus')) {
            return 0;
        }

        static::$syncingSharedFields = true;

        try {
            return static::query()
                ->where('loanerID', $loanerId)
                ->update(['currentStatus' => $status]);
        } finally {
            static::$syncingSharedFields = false;
        }
    }

    public static function canonicalCurrentStatus(mixed $loanerId): mixed
    {
        if ($loanerId === null || $loanerId === '') {
            return null;
        }

        return static::query()
            ->where('loanerID', $loanerId)
            ->orderByDesc('lastEditDate')
            ->orderByDesc('id')
            ->value('currentStatus');
    }

    /**
     * 同じ loanerID を持つ全版へ共有項目を反映する。
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function syncSharedFieldsAcrossVersions(
        mixed $loanerId,
        array $attributes,
        mixed $exceptId = null,
    ): int {
        if ($loanerId === null || $loanerId === '') {
            return 0;
        }

        $payload = static::normalizeSharedAttributes($attributes);
        if ($payload === []) {
            return 0;
        }

        static::$syncingSharedFields = true;

        try {
            $query = static::query()->where('loanerID', $loanerId);
            if ($exceptId !== null && $exceptId !== '') {
                $query->whereKeyNot($exceptId);
            }

            return $query->update($payload);
        } finally {
            static::$syncingSharedFields = false;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function normalizeSharedAttributes(array $attributes): array
    {
        static $existingColumns = null;
        if ($existingColumns === null) {
            $existingColumns = array_flip(Schema::getColumnListing('loanermaster008'));
        }

        $payload = [];
        foreach (static::SHARED_VERSION_FIELDS as $field) {
            if (!array_key_exists($field, $attributes)) {
                continue;
            }
            if (!isset($existingColumns[$field])) {
                continue;
            }

            $value = $attributes[$field];
            if ($value instanceof \DateTimeInterface) {
                $payload[$field] = in_array($field, ['lastEditDate'], true)
                    ? $value->format('Y-m-d H:i:s')
                    : $value->format('Y-m-d');
            } else {
                $payload[$field] = $value;
            }
        }

        return $payload;
    }

    /**
     * 業務キー loanerID で紐づく案件（版をまたぐ）。
     */
    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class, 'loanerID', 'loanerID');
    }
}

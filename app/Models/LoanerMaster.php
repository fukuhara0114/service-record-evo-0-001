<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LoanerMaster extends Model
{
    protected $table = 'loanermaster008';

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

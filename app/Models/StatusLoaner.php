<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StatusLoaner extends Model
{
    protected $table = 'statusmaster_loaner';

    protected $primaryKey = 'processID_new';

    public $timestamps = false;

    public $incrementing = false;

    /** @var array<int, string>|null */
    private static ?array $selectColumnsCache = null;

    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class, 'status', 'processID_new');
    }

    /**
     * 画面表記用ラベル。statusmaster_loaner.status_new のみを使う。
     */
    public function displayLabel(): ?string
    {
        return static::resolveLabel($this);
    }

    /**
     * @param  self|array<string, mixed>|object|null  $row
     */
    public static function resolveLabel(mixed $row): ?string
    {
        if ($row === null) {
            return null;
        }

        $statusNew = trim((string) (data_get($row, 'status_new') ?? ''));

        return $statusNew !== '' ? $statusNew : null;
    }

    /**
     * @return array<int, string>
     */
    public static function selectColumnsForDisplay(): array
    {
        if (self::$selectColumnsCache !== null) {
            return self::$selectColumnsCache;
        }

        $columns = ['processID_new'];
        if (Schema::hasTable('statusmaster_loaner') && Schema::hasColumn('statusmaster_loaner', 'status_new')) {
            $columns[] = 'status_new';
        }

        return self::$selectColumnsCache = $columns;
    }

    /**
     * JSON 化時も表記名は status_new のみ。互換のため status キーにも同値を載せる。
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $label = $this->displayLabel();
        $array['status_new'] = $label;
        $array['status'] = $label;

        return $array;
    }

    /**
     * API / Inertia 向け: status_new のみを表記名とする。
     *
     * @return array<string, mixed>
     */
    public function toDisplayArray(): array
    {
        $payload = $this->only(static::selectColumnsForDisplay());
        $label = $this->displayLabel();
        $payload['status_new'] = $label;
        // 既存フロントの status 参照向け互換（値は status_new と同値）
        $payload['status'] = $label;

        return $payload;
    }

    /**
     * @param  Collection<int, self>|iterable<int, self>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function mapForDisplay(iterable $rows): Collection
    {
        return collect($rows)->map(
            fn (self $row) => $row->toDisplayArray(),
        )->values();
    }
}

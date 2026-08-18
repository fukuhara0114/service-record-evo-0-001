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
     * 画面表記用ラベル。status_new を優先し、空なら status にフォールバックする。
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
        if ($statusNew !== '') {
            return $statusNew;
        }

        $status = trim((string) (data_get($row, 'status') ?? ''));

        return $status !== '' ? $status : null;
    }

    /**
     * @return array<int, string>
     */
    public static function selectColumnsForDisplay(): array
    {
        if (self::$selectColumnsCache !== null) {
            return self::$selectColumnsCache;
        }

        $columns = ['processID_new', 'status'];
        if (Schema::hasTable('statusmaster_loaner') && Schema::hasColumn('statusmaster_loaner', 'status_new')) {
            $columns[] = 'status_new';
        }

        return self::$selectColumnsCache = $columns;
    }

    /**
     * JSON 化時も表記名（status_new 優先）を status に載せる。
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $label = $this->displayLabel();
        if ($label !== null) {
            $array['status'] = $label;
        }

        return $array;
    }

    /**
     * API / Inertia 向け: status キーを表記名（status_new 優先）に差し替える。
     *
     * @return array<string, mixed>
     */
    public function toDisplayArray(): array
    {
        $payload = $this->only(static::selectColumnsForDisplay());
        $payload['status'] = $this->displayLabel();

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

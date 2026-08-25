<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class StatusLoaner extends Model
{
    protected $table = 'statusmaster_loaner';

    protected $primaryKey = 'processID_new';

    public $timestamps = false;

    public $incrementing = false;

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

        $statusNew = static::extractStatusNew($row);

        return $statusNew !== '' ? $statusNew : null;
    }

    /**
     * MySQL 5.7 / 8 共通。Schema::hasColumn に依存しない（5.7 で列検出が外れるため）。
     *
     * @return array<int, string>
     */
    public static function selectColumnsForDisplay(): array
    {
        return ['processID_new', 'status_new'];
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
        unset($array['status']);
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
        $label = $this->displayLabel();

        return [
            'processID_new' => $this->getAttribute('processID_new'),
            'status_new' => $label,
            'status' => $label,
        ];
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

    /**
     * PDO / MySQL 5.7 でキーの大文字小文字が揺れる場合も status_new だけ拾う。
     */
    private static function extractStatusNew(mixed $row): string
    {
        if (is_object($row) && method_exists($row, 'getAttributes')) {
            foreach ($row->getAttributes() as $key => $value) {
                if (strcasecmp((string) $key, 'status_new') === 0) {
                    return trim((string) ($value ?? ''));
                }
            }
        }

        foreach (['status_new', 'statusNew', 'STATUS_NEW'] as $key) {
            $value = data_get($row, $key);
            if ($value !== null && $value !== '') {
                return trim((string) $value);
            }
        }

        if (is_array($row)) {
            foreach ($row as $key => $value) {
                if (strcasecmp((string) $key, 'status_new') === 0) {
                    return trim((string) ($value ?? ''));
                }
            }
        }

        return '';
    }
}

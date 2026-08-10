<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class LoanerMaster extends Model
{
    protected $table = 'loanermaster';

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

    protected static bool $syncingSharedFields = false;

    protected static function booted(): void
    {
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
            $existingColumns = array_flip(Schema::getColumnListing('loanermaster'));
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

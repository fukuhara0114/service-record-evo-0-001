<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ServiceMaster extends Authenticatable
{
    protected $table = 'servicemaster';

    // 版ごとのサロゲートキー（業務キーは serviceID）
    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'serviceID',
        'productName',
        'productType',
        'entityID',
        'priceC_0',
        'priceR_0',
        'priceC_1',
        'priceR_1',
        'priceC_2',
        'priceR_2',
        'priceC_3',
        'priceR_3',
        'priceR_onSite',
        'price_postData',
        'price_a2la',
        'validDateMin',
        'validDateMax',
        'note',
    ];

    protected $casts = [
        'validDateMin' => 'date',
        'validDateMax' => 'date',
    ];

    /**
     * Inertia JSON は Y-m-d 固定。ISO UTC にすると TZ 差で 5.7/8 の版判定がずれる。
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    /**
     * 業務キー serviceID で紐づく案件（版をまたぐ）。
     */
    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class, 'serviceID', 'serviceID');
    }
}

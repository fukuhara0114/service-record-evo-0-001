<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class ServiceMaster extends Authenticatable
{
    // テーブル名
    protected $table = 'servicemaster';

    // 主キー（重要）
    protected $primaryKey = 'serviceID';

    // オートインクリメント
    public $incrementing = false;

    // 主キーの型
    protected $keyType = 'int';

    // timestamps無効（created_at, updated_at無し）
    public $timestamps = false;

    // 代入可能カラム
    protected $fillable = [
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
        'entityID',
    ];

    public function serviceRecords()
    {
        return $this->hasMany(ServiceRecord::class, 'serviceID', 'serviceID');
    }
}

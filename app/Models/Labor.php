<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Labor extends Authenticatable
{
    // テーブル名
    protected $table = 'labormaster';

    // 主キー（重要）
    protected $primaryKey = 'laborID';

    // オートインクリメント
    public $incrementing = false;

    // 主キーの型
    protected $keyType = 'int';

    // timestamps無効（created_at, updated_at無し）
    public $timestamps = false;

    // 代入可能カラム
    protected $fillable = [
        'laborName',
        'laborID'
    ];

    public function serviceRecords()
    {
        return $this->hasMany(ServiceRecord::class, 'laborID', 'laborID');
    }
}

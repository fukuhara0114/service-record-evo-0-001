<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable; // 必須
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    // テーブル名
    protected $table = 'users';

    // 主キー（重要）
    protected $primaryKey = 'userID';

    // オートインクリメント
    public $incrementing = true;

    // 主キーの型
    protected $keyType = 'int';

    // timestamps無効（created_at, updated_at無し）
    public $timestamps = false;

    // 代入可能カラム
    protected $fillable = [
        'name',
        'kanji_name',
        'email',
        'password',
        'permission',
        'laborID',
        'receive_info',
    ];

    // JSONなどに出さない（セキュリティ）
    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'receive_info' => 'integer',
        ];
    }

    public function serviceRecords()
    {
        return $this->hasMany(ServiceRecord::class, 'user', 'userID');
    }
}

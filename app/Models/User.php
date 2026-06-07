<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable; // 必須
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    // 既存のテーブル名が 'users' ではない場合は明記する（例: 'm_users' など）
    protected $table = 'users'; 

    // 主キーが 'id' ではない場合は明記する
    protected $primaryKey = 'userID';

    public $timestamps = false;

    // Vueなどから一括登録（保存）を許可するカラムを指定
    protected $fillable = [
        'name',
        'kanji_name',
        'email',
        'password',
        'permission',
        'laborID'
    ];

    // パスワードなど、Vue側にうっかり返したくない秘密の情報はここに隠しておきます
    protected $hidden = [
        'password',
    ];
}
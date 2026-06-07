<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    // 紐付けるテーブル名を指定
    protected $table = 'users';

    // 主キーを 'userID' に変更 (デフォルトは id)
    protected $primaryKey = 'userID';

    // タイムスタンプカラムがテーブルに無い場合は false にする
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
}

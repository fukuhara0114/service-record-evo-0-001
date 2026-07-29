<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status_loaner extends Model
{
    // 💡 【重要】実際のテーブル名「attachedfiles」をすべて小文字で正確に指定します
    protected $table = 'statusmaster_loaner';

    // テーブルの主キーが「id」であることを明示
    protected $primaryKey = 'processID';

    // もしテーブルに「created_at」「updated_at」カラムがない場合は以下を必須で追加
    public $timestamps = false;
    public $incrementing = false;

    public function serviceRecords()
    {
        return $this->hasMany(ServiceRecord::class, 'status', 'processID');
    }
}
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ServiceRecord;

class ReturnCode extends Model
{
    // 💡 【重要】実際のテーブル名「attachedfiles」をすべて小文字で正確に指定します
    protected $table = 'returncodemaster';

    // テーブルの主キーが「id」であることを明示
    protected $primaryKey = 'id';

    // もしテーブルに「created_at」「updated_at」カラムがない場合は以下を必須で追加
    public $timestamps = false;


    
    public function serviceRecords()
    {
        return $this->hasMany(ServiceRecord::class, 'returnCode', 'id');
    }

}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanerMaster extends Model
{
    protected $table = 'loanermaster';

    // 版ごとのサロゲートキー（業務キーは loanerID）
    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

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

    /**
     * 業務キー loanerID で紐づく案件（版をまたぐ）。
     */
    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class, 'loanerID', 'loanerID');
    }
}

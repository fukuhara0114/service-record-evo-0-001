<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanerMaster extends Model
{
    protected $table = 'loanermaster';

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
        'certificationDate',
        'currentStatus',
        'note1',
        'note2',
        'note3',
        'sentDatek',
        'returnedDate',
        'price',
        'lasetEditPerson',
        'lastEditDate',
        'groupName',
    ];

    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class, 'loanerID', 'loanerID');
    }
}

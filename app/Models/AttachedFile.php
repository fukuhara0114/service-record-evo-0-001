<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachedFile extends Model
{
    protected $table = 'attachedfiles';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'associatedID',
        'content',
        'documentType',
        'documentName',
        'fileType',
        'sortNum',
    ];

    public function serviceRecord(): BelongsTo
    {
        return $this->belongsTo(ServiceRecord::class, 'associatedID', 'orderID');
    }
}

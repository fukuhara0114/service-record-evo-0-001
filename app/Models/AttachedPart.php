<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachedPart extends Model
{
    protected $table = 'attachedparts';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'associatedID',
        'partID',
    ];

    public function serviceRecord(): BelongsTo
    {
        return $this->belongsTo(ServiceRecord::class, 'associatedID', 'orderID');
    }

    public function partMaster(): BelongsTo
    {
        return $this->belongsTo(PartMaster::class, 'partID', 'partID');
    }
}

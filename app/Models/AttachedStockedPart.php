<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachedStockedPart extends Model
{
    protected $table = 'attachedstockedparts';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'associatedID',
        'partID',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function serviceRecord(): BelongsTo
    {
        return $this->belongsTo(ServiceRecord::class, 'associatedID', 'orderID');
    }

    public function stockedPartMaster(): BelongsTo
    {
        return $this->belongsTo(StockedPartMaster::class, 'partID', 'partID');
    }
}

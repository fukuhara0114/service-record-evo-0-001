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

    /**
     * 業務キー partID で紐づく（版をまたぐ。価格版の解決は MasterPriceVersionResolver を使う）。
     */
    public function partMaster(): BelongsTo
    {
        return $this->belongsTo(PartMaster::class, 'partID', 'partID');
    }
}

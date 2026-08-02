<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockedPartMaster extends Model
{
    protected $table = 'stockedpartmaster';

    protected $primaryKey = 'partID';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'partID',
        'partName',
        'description',
    ];

    public function attachedStockedParts(): HasMany
    {
        return $this->hasMany(AttachedStockedPart::class, 'partID', 'partID');
    }
}

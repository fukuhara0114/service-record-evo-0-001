<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartMaster extends Model
{
    protected $table = 'partmaster';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'partID',
        'partName',
        'description',
    ];

    public function attachedParts(): HasMany
    {
        return $this->hasMany(AttachedPart::class, 'partID', 'partID');
    }
}

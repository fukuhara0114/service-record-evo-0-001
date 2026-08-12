<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachedNote extends Model
{
    protected $table = 'attachednotes';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'associatedID',
        'note',
        'whoWrote',
        'whenWrote',
        'important',
        'personal',
        'tbc',
        'done',
    ];

    protected $casts = [
        'important' => 'boolean',
        'personal' => 'boolean',
        'whenWrote' => 'datetime',
        // tbc / done は NULL / true を区別するため boolean cast しない
    ];

    public function serviceRecord(): BelongsTo
    {
        return $this->belongsTo(ServiceRecord::class, 'associatedID', 'orderID');
    }
}

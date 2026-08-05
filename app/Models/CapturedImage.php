<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapturedImage extends Model
{
    protected $table = 'captured_image';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'title',
        'file_name',
        'captured_at',
        'associatedID',
        'captured_by',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'associatedID' => 'integer',
    ];

    public function serviceRecord(): BelongsTo
    {
        return $this->belongsTo(ServiceRecord::class, 'associatedID', 'orderID');
    }
}

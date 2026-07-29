<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusLoaner extends Model
{
    protected $table = 'statusmaster_loaner';

    protected $primaryKey = 'processID';

    public $timestamps = false;

    public $incrementing = false;

    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class, 'status', 'processID');
    }
}

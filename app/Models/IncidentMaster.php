<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentMaster extends Model
{
    protected $table = 'incidentmaster';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'incidentNum',
        'companyName',
        'depart',
        'customerNum',
    ];
}

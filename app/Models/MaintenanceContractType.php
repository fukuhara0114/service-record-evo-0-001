<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceContractType extends Model
{
    protected $table = 'maintenance_contract_type';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'contractType',
        'description',
    ];

    /**
     * この契約種別を持つ保守契約マスタ。
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(MaintenanceContractMaster::class, 'contractType', 'id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceContractMaster extends Model
{
    protected $table = 'maintenancecontractmaster';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'dealer',
        'branch',
        'contact',
        'phone',
        'email',
        'address',
        'endUser',
        'endUser_depart',
        'endUser_contact',
        'endUser_phone',
        'endUser_email',
        'endUser_address',
        'instrumentName',
        'SN',
        'shippingDate',
        'yayoi_PO',
        'orderedDate',
        'mapics_PO',
        'invoice_num',
        'startDate',
        'expireDate',
        'certificationTicket',
        'certificationExpireDate',
        'renewalInformation',
        'informedDate',
        'renewedDate',
        'contractType',
        'informed',
        'amount',
        'status',
        'RefNumber',
        'description',
        'additional_information',
        'lastEditPerson',
        'lastEditDate',
    ];

    protected $casts = [
        'shippingDate' => 'date',
        'orderedDate' => 'date',
        'startDate' => 'date',
        'expireDate' => 'date',
        'certificationExpireDate' => 'date',
        'informedDate' => 'date',
        'renewedDate' => 'date',
        'lastEditDate' => 'datetime',
        'amount' => 'decimal:2',
        'informed' => 'boolean',
    ];

    /**
     * contractType（FK）→ maintenance_contract_type.id
     * 名称: maintenanceContractType.contractType
     * 詳細: maintenanceContractType.description
     */
    public function maintenanceContractType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceContractType::class, 'contractType', 'id');
    }
}

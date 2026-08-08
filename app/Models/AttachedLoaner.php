<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachedLoaner extends Model
{
    protected $table = 'attachedloaners';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'XR',
        'admin',
        'associatedID',
        'loanerID',
        'dealer-id',
        'endUser-id',
        'sentDate',
        'returnedDate',
        'plannedSentDate',
        'plannedReturnedDate',
        'assignStatus',
        'productName',
        'repairInstrument-SN',
        'comment',
        'contact-person-dealer',
        'address-dealer',
        'endUser',
        'contant-person-endUser',
        'phone-dealer',
        'email-dealer',
        'email-endUser',
        'phone-endUser',
        'zip-endUser',
        'address1-endUser',
        'address2-endUser',
    ];

    protected $casts = [
        'sentDate' => 'date',
        'returnedDate' => 'date',
        'plannedSentDate' => 'date',
        'plannedReturnedDate' => 'date',
    ];

    public function serviceRecord(): BelongsTo
    {
        return $this->belongsTo(ServiceRecord::class, 'associatedID', 'orderID');
    }

    /**
     * 業務キー loanerID で紐づく（版をまたぐ。価格版の解決は MasterPriceVersionResolver を使う）。
     */
    public function loanerMaster(): BelongsTo
    {
        return $this->belongsTo(LoanerMaster::class, 'loanerID', 'loanerID');
    }

    /**
     * Calendar 表示用の開始日（予定優先、なければ現行 sentDate）
     */
    public function getCalendarStartAttribute(): ?string
    {
        $date = ($this->attributes['plannedSentDate'] ?? null)
            ? $this->plannedSentDate
            : $this->sentDate;

        return $date?->format('Y-m-d');
    }

    public function getCalendarEndAttribute(): ?string
    {
        $rawEnd = $this->attributes['plannedReturnedDate']
            ?? $this->attributes['returnedDate']
            ?? $this->attributes['plannedSentDate']
            ?? $this->attributes['sentDate']
            ?? null;

        if (!$rawEnd) {
            return null;
        }

        $date = $this->asDate($rawEnd);

        // FullCalendar の end は exclusive のため +1 day
        return $date->copy()->addDay()->format('Y-m-d');
    }
}

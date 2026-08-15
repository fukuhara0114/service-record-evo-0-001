<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GmailImapCursor extends Model
{
    protected $table = 'gmail_imap_cursors';

    protected $primaryKey = 'mailbox';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'mailbox',
        'last_uid',
        'last_polled_at',
    ];

    protected function casts(): array
    {
        return [
            'last_uid' => 'integer',
            'last_polled_at' => 'datetime',
        ];
    }
}

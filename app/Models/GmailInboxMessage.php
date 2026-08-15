<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GmailInboxMessage extends Model
{
    protected $table = 'gmail_inbox_messages';

    protected $fillable = [
        'message_id',
        'imap_uid',
        'mailbox',
        'subject',
        'from_address',
        'received_at',
        'has_deeplink',
        'deeplink_url',
        'deeplink_urls',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'imap_uid' => 'integer',
            'received_at' => 'datetime',
            'has_deeplink' => 'boolean',
            'deeplink_urls' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}

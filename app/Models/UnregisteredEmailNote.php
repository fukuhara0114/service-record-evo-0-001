<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnregisteredEmailNote extends Model
{
    protected $table = 'unregisteredemailnotes';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $hidden = [
        'mailLinkHash',
    ];

    protected $fillable = [
        'mailLink',
        'mailLinkHash',
        'whoWrote',
        'whenWrote',
        'subject',
        'fromAddress',
    ];

    protected $casts = [
        'whenWrote' => 'datetime',
    ];

    public static function hashMailLink(string $mailLink): string
    {
        return hash('sha256', $mailLink);
    }
}

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

    /**
     * JSON 化時に UTC(Z) へ変換せず、DB の壁時計をそのまま返す。
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function formatWhenWrote(mixed $when): ?string
    {
        if ($when === null || $when === '') {
            return null;
        }
        if ($when instanceof \DateTimeInterface) {
            return $when->format('Y-m-d H:i:s');
        }
        $text = trim((string) $when);
        return $text === '' ? null : $text;
    }

    public function serviceRecord(): BelongsTo
    {
        return $this->belongsTo(ServiceRecord::class, 'associatedID', 'orderID');
    }
}

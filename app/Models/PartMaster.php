<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartMaster extends Model
{
    protected $table = 'partmaster';

    // 版ごとのサロゲートキー（業務キーは partID）
    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'partID',
        'partName',
        'description',
        'price_market',
        'price_discounted',
        'price_discounted_1',
        'associatedInstruments',
        'type',
        'note',
        'validDateMin',
        'validDateMax',
    ];

    protected $casts = [
        'validDateMin' => 'date',
        'validDateMax' => 'date',
    ];

    /**
     * 業務キー partID で紐づく添付部品（版をまたぐ）。
     */
    public function attachedParts(): HasMany
    {
        return $this->hasMany(AttachedPart::class, 'partID', 'partID');
    }
}

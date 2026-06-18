<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TontineProduit extends Model
{
    protected $table = 'tontine_produits';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tontine_id', 'produit_id', 'quantite', 'prix_unitaire', 'sous_total',
    ];

    protected $casts = [
        'prix_unitaire' => 'decimal:2',
        'sous_total'    => 'decimal:2',
        'quantite'      => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    public function produit() { return $this->belongsTo(Produit::class); }
    public function tontine() { return $this->belongsTo(Tontine::class); }
}

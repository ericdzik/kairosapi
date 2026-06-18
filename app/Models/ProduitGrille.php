<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProduitGrille extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['produit_id', 'duree_mois', 'montant_mise'];

    protected $casts = [
        'duree_mois'   => 'integer',
        'montant_mise' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    public function produit() { return $this->belongsTo(Produit::class); }
}

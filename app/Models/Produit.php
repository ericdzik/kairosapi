<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Produit extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code', 'nom', 'categorie', 'prix_unitaire', 'prix_vente_directe',
        'duree_mois', 'description', 'image', 'actif',
    ];

    protected $casts = [
        'actif'          => 'boolean',
        'prix_unitaire'  => 'decimal:2',
        'duree_mois'     => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Produit $produit) {
            if (empty($produit->id)) {
                $produit->id = (string) Str::uuid();
            }
            // Générer un code automatique si non fourni
            if (empty($produit->code)) {
                $produit->code = 'PRD-' . strtoupper(Str::random(6));
            }
        });
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function grilles()
    {
        return $this->hasMany(ProduitGrille::class)->orderBy('duree_mois');
    }
}

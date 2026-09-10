<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Vente extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'client_id', 'client_nom', 'client_telephone',
        'produit_id', 'commercial_id', 'validateur_id',
        'quantite', 'montant', 'date_vente',
        'statut', 'motif_annulation', 'valide_at', 'notes',
        'latitude', 'longitude', 'adresse_complete',
    ];

    protected $casts = [
        'date_vente' => 'date',
        'valide_at'  => 'datetime',
        'montant'    => 'decimal:2',
        'quantite'   => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn(Vente $v) => $v->id ??= (string) Str::uuid());
    }

    public function client()     { return $this->belongsTo(Client::class); }
    public function produit()    { return $this->belongsTo(Produit::class); }
    public function commercial() { return $this->belongsTo(User::class, 'commercial_id'); }
    public function validateur() { return $this->belongsTo(User::class, 'validateur_id'); }
}

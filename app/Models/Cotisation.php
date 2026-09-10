<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Cotisation extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tontine_id', 'client_id', 'commercial_id', 'validateur_id',
        'nombre_mises', 'montant_unitaire', 'montant_total', 'montant_verse',
        'date_cotisation', 'statut', 'motif_rejet', 'valide_at',
    ];

    protected $casts = [
        'date_cotisation'  => 'date',
        'valide_at'        => 'datetime',
        'montant_unitaire' => 'decimal:2',
        'montant_total'    => 'decimal:2',
        'nombre_mises'     => 'integer',
        'montant_verse'    => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn(Cotisation $c) => $c->id ??= (string) Str::uuid());
    }

    public function tontine()    { return $this->belongsTo(Tontine::class); }
    public function client()     { return $this->belongsTo(Client::class); }
    public function commercial() { return $this->belongsTo(User::class, 'commercial_id'); }
    public function validateur() { return $this->belongsTo(User::class, 'validateur_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'nom', 'prenom', 'telephone', 'quartier',
        'commercial_id', 'notes',
        'photo', 'latitude', 'longitude', 'adresse_complete',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn(Client $c) => $c->id ??= (string) Str::uuid());
    }

    public function commercial()
    {
        return $this->belongsTo(User::class, 'commercial_id');
    }

    public function tontines()
    {
        return $this->hasMany(Tontine::class);
    }

    public function ventes()
    {
        return $this->hasMany(Vente::class);
    }

    public function cotisations()
    {
        return $this->hasMany(Cotisation::class);
    }

    public function tontineActive()
    {
        return $this->tontines()->where('statut', 'en_cours')->latest()->first();
    }
}

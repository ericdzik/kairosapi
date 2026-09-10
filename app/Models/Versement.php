<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Versement extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'commercial_id', 'secretaire_id', 'date',
        'montant_attendu', 'montant_verse', 'ecart', 'statut', 'notes',
    ];

    protected $casts = [
        'date'            => 'date',
        'montant_attendu' => 'decimal:2',
        'montant_verse'   => 'decimal:2',
        'ecart'           => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    public function commercial() { return $this->belongsTo(User::class, 'commercial_id'); }
    public function secretaire() { return $this->belongsTo(User::class, 'secretaire_id'); }
}

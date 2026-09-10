<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Depense extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType   = 'string';
    public $incrementing = false;

    protected $fillable = [
        'categorie', 'libelle', 'montant', 'date_depense', 'notes', 'created_by',
    ];

    protected $casts = [
        'date_depense' => 'date',
        'montant'      => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn(Depense $d) => $d->id ??= (string) Str::uuid());
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Catégories disponibles
    public static function categories(): array
    {
        return ['salaire', 'carnet', 'approvisionnement', 'transport', 'loyer', 'communication', 'autre'];
    }
}

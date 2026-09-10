<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Tontine extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'client_id', 'produit_id', 'commercial_id',
        'duree_mois', 'montant_mise', 'date_debut', 'date_fin', 'statut', 'notes',
        'date_livraison', 'livre_par_id', 'notes_livraison',
    ];

    protected $casts = [
        'date_debut'      => 'date',
        'date_fin'        => 'date',
        'date_livraison'  => 'date',
        'duree_mois'      => 'integer',
        'montant_mise'    => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Tontine $t) {
            $t->id ??= (string) Str::uuid();
            if ($t->date_debut && $t->duree_mois) {
                $t->date_fin = Carbon::parse($t->date_debut)->addMonths($t->duree_mois);
            }
        });
    }

    public function client()    { return $this->belongsTo(Client::class); }
    public function produit()   { return $this->belongsTo(Produit::class); }
    public function commercial(){ return $this->belongsTo(User::class, 'commercial_id'); }
    public function livrePar()  { return $this->belongsTo(User::class, 'livre_par_id'); }
    public function cotisations(){ return $this->hasMany(Cotisation::class); }
    public function produits()  { return $this->hasMany(TontineProduit::class); }

    public function totalMisesAttendues(): int
    {
        return $this->duree_mois * 31;
    }

    /**
     * Valeur totale des produits (plafond réel de la tontine)
     */
    public function valeurTotaleProduits(): float
    {
        return (float) $this->produits()->sum('sous_total');
    }

    /**
     * Montant total attendu = min(duree*31*mise, valeur produits)
     * Le total des cotisations ne peut pas dépasser le prix des produits
     */
    public function montantTotalAttendu(): float
    {
        $parMise        = (float) ($this->duree_mois * 31 * $this->montant_mise);
        $valeurProduits = $this->valeurTotaleProduits();

        // Si valeur produits définie, on plafonne à ce montant
        if ($valeurProduits > 0) {
            return min($parMise, $valeurProduits);
        }

        return $parMise;
    }

    public function montantTotalVerse(): float
    {
        // montant_verse si défini, sinon montant_total (mise complète)
        return (float) $this->cotisations()
            ->where('statut', 'valide')
            ->selectRaw('SUM(COALESCE(montant_verse, montant_total)) as total')
            ->value('total');
    }

    public function totalMisesValidees(): float
    {
        return (float) $this->cotisations()->where('statut', 'valide')->sum('nombre_mises');
    }

    public function progression(): float
    {
        $total = $this->montantTotalAttendu();
        if ($total == 0) return 0;
        return round(($this->montantTotalVerse() / $total) * 100, 2);
    }

    public function estPretALivrer(): bool
    {
        return $this->progression() >= 100.0;
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProduitController extends Controller
{
    private function withImageUrl(Produit $produit): Produit
    {
        $produit->image_url = $produit->image
            ? Storage::disk('public')->url($produit->image)
            : null;
        return $produit;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Produit::with('grilles');

        if ($request->filled('categorie')) $query->where('categorie', $request->categorie);
        if ($request->filled('actif'))     $query->where('actif', filter_var($request->actif, FILTER_VALIDATE_BOOLEAN));
        if ($request->filled('search'))    $query->where('nom', 'ilike', '%' . $request->search . '%');

        $produits = $query->orderBy('nom')->paginate(20);

        // Ajouter image_url à chaque produit
        $produits->getCollection()->transform(function ($p) {
            $p->image_url = $p->image ? Storage::disk('public')->url($p->image) : null;
            return $p;
        });

        return response()->json($produits);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'                => 'required|string|max:150',
            'categorie'          => 'nullable|string|max:100',
            'prix_unitaire'      => 'required|numeric|min:0',
            'prix_vente_directe' => 'nullable|numeric|min:0',
            'description'        => 'nullable|string',
            'code'               => 'nullable|string|max:20|unique:produits,code',
            'image'              => 'nullable|image|max:5120',
            'grilles'            => 'nullable|array',
            'grilles.*.duree_mois'   => 'required|integer|min:1|max:12',
            'grilles.*.montant_mise' => 'required|numeric|min:1',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('produits/images', 'public');
        }

        $produit = null;
        DB::transaction(function () use ($data, $imagePath, &$produit) {
            $produit = Produit::create([
                'nom'                => $data['nom'],
                'categorie'          => $data['categorie'] ?? null,
                'prix_unitaire'      => $data['prix_unitaire'],
                'prix_vente_directe' => $data['prix_vente_directe'] ?? null,
                'description'        => $data['description'] ?? null,
                'code'               => $data['code'] ?? null,
                'image'              => $imagePath,
            ]);

            if (!empty($data['grilles'])) {
                foreach ($data['grilles'] as $grille) {
                    $produit->grilles()->create([
                        'duree_mois'   => $grille['duree_mois'],
                        'montant_mise' => $grille['montant_mise'],
                    ]);
                }
            }
        });

        $produit->load('grilles');
        AuditService::log('create', 'Produit', $produit->id);

        return response()->json($this->withImageUrl($produit), 201);
    }

    public function show(Produit $produit): JsonResponse
    {
        $produit->load('grilles');
        return response()->json($this->withImageUrl($produit));
    }

    public function update(Request $request, Produit $produit): JsonResponse
    {
        $data = $request->validate([
            'nom'                => 'sometimes|string|max:150',
            'categorie'          => 'nullable|string|max:100',
            'prix_unitaire'      => 'sometimes|numeric|min:0',
            'prix_vente_directe' => 'nullable|numeric|min:0',
            'description'        => 'nullable|string',
            'code'               => 'nullable|string|max:20|unique:produits,code,' . $produit->id,
            'image'              => 'nullable|image|max:5120',
            'grilles'            => 'nullable|array',
            'grilles.*.duree_mois'   => 'required|integer|min:1|max:12',
            'grilles.*.montant_mise' => 'required|numeric|min:1',
        ]);

        if ($request->hasFile('image')) {
            if ($produit->image) Storage::disk('public')->delete($produit->image);
            $data['image'] = $request->file('image')->store('produits/images', 'public');
        }

        DB::transaction(function () use ($data, $produit) {
            $fields = array_filter([
                'nom'                => $data['nom'] ?? null,
                'categorie'          => $data['categorie'] ?? null,
                'prix_unitaire'      => $data['prix_unitaire'] ?? null,
                'prix_vente_directe' => $data['prix_vente_directe'] ?? null,
                'description'        => $data['description'] ?? null,
                'code'               => $data['code'] ?? null,
                'image'              => $data['image'] ?? null,
            ], fn($v) => $v !== null);

            $produit->update($fields);

            if (isset($data['grilles'])) {
                $produit->grilles()->delete();
                foreach ($data['grilles'] as $grille) {
                    $produit->grilles()->create([
                        'duree_mois'   => $grille['duree_mois'],
                        'montant_mise' => $grille['montant_mise'],
                    ]);
                }
            }
        });

        $produit->load('grilles');
        AuditService::log('update', 'Produit', $produit->id);

        return response()->json($this->withImageUrl($produit));
    }

    public function destroy(Produit $produit): JsonResponse
    {
        if ($produit->image) Storage::disk('public')->delete($produit->image);
        AuditService::log('delete', 'Produit', $produit->id);
        $produit->delete();
        return response()->json(['message' => 'Produit supprimé.']);
    }

    public function toggle(Produit $produit): JsonResponse
    {
        $produit->update(['actif' => !$produit->actif]);
        AuditService::log('toggle', 'Produit', $produit->id);
        return response()->json(['actif' => $produit->actif]);
    }
}

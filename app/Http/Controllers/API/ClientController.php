<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Client::with(['commercial:id,nom,prenom']);

        if ($user->isCommercial()) {
            $query->where('commercial_id', $user->id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('nom', 'ilike', "%$s%")
                ->orWhere('prenom', 'ilike', "%$s%")
                ->orWhere('telephone', 'ilike', "%$s%")
            );
        }
        if ($request->filled('commercial_id')) {
            $query->where('commercial_id', $request->commercial_id);
        }

        return response()->json($query->orderBy('nom')->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'nom'              => 'required|string|max:100',
            'prenom'           => 'required|string|max:100',
            'telephone'        => 'nullable|string|max:20',
            'quartier'         => 'nullable|string|max:150',
            'notes'            => 'nullable|string',
            'commercial_id'    => 'nullable|uuid|exists:users,id',
            'photo'            => 'nullable|image|max:5120',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
            'adresse_complete' => 'nullable|string|max:500',
        ]);

        if ($user->isCommercial()) {
            $data['commercial_id'] = $user->id;
        } elseif (empty($data['commercial_id'])) {
            $data['commercial_id'] = $user->id;
        }

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('clients/photos', 'public');
        }

        $client = Client::create($data);
        $client->load('commercial:id,nom,prenom');

        AuditService::log('create', 'Client', $client->id);

        return response()->json($client, 201);
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        $user = $request->user();
        if ($user->isCommercial() && $client->commercial_id !== $user->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $client->load([
            'commercial:id,nom,prenom',
            'tontines.produit:id,nom,prix_unitaire',
            'ventes.produit:id,nom',
        ]);

        // Ajouter l'URL complète de la photo
        if ($client->photo) {
            $client->photo_url = Storage::disk('public')->url($client->photo);
        }

        return response()->json($client);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'nom'              => 'sometimes|string|max:100',
            'prenom'           => 'sometimes|string|max:100',
            'telephone'        => 'nullable|string|max:20',
            'quartier'         => 'nullable|string|max:150',
            'notes'            => 'nullable|string',
            'photo'            => 'nullable|image|max:5120',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
            'adresse_complete' => 'nullable|string|max:500',
        ]);

        if ($request->hasFile('photo')) {
            if ($client->photo) {
                Storage::disk('public')->delete($client->photo);
            }
            $data['photo'] = $request->file('photo')->store('clients/photos', 'public');
        }

        $client->update($data);
        AuditService::log('update', 'Client', $client->id);

        return response()->json($client);
    }

    public function destroy(Client $client): JsonResponse
    {
        if ($client->photo) {
            Storage::disk('public')->delete($client->photo);
        }
        AuditService::log('delete', 'Client', $client->id);
        $client->delete();
        return response()->json(['message' => 'Client supprimé.']);
    }
}

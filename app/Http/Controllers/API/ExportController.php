<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Cotisation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    /**
     * GET /api/exports/rapport-journalier
     * Export PDF du rapport journalier
     */
    public function rapportJournalier(Request $request): Response
    {
        $date = $request->get('date', today()->toDateString());

        $cotisations = Cotisation::with(['client:id,nom,prenom', 'commercial:id,nom,prenom'])
            ->whereDate('date_cotisation', $date)
            ->where('statut', 'valide')
            ->orderBy('created_at')
            ->get();

        $data = [
            'date'         => $date,
            'cotisations'  => $cotisations,
            'total_mises'  => $cotisations->sum('nombre_mises'),
            'montant_total'=> $cotisations->sum('montant_total'),
            'genere_par'   => $request->user()->nom . ' ' . $request->user()->prenom,
            'genere_le'    => now()->format('d/m/Y H:i'),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.rapport-journalier', $data);

        return $pdf->download("rapport-journalier-{$date}.pdf");
    }

    /**
     * GET /api/exports/mises
     * Export Excel des mises avec filtres
     */
    public function mises(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        // Placeholder — sera implémenté avec Maatwebsite Excel
        $filename = 'mises-' . now()->format('Y-m-d') . '.xlsx';

        return response()->download(
            $this->genererExcelMises($request),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * GET /api/exports/pointage/{client}
     * Export PDF de la grille de pointage d'un client
     */
    public function pointage(Request $request, Client $client): Response
    {
        $client->load(['commercial:id,nom,prenom', 'produit:id,nom,prix_unitaire']);

        $cotisations = Cotisation::where('client_id', $client->id)
            ->where('statut', 'valide')
            ->orderBy('date_cotisation')
            ->get();

        $data = [
            'client'      => $client,
            'cotisations' => $cotisations,
            'total_mises' => $cotisations->sum('nombre_mises'),
            'montant'     => $cotisations->sum('montant_total'),
            'progression' => $client->progression(),
            'genere_le'   => now()->format('d/m/Y H:i'),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.pointage-client', $data);

        return $pdf->download("pointage-{$client->nom}-{$client->prenom}.pdf");
    }

    private function genererExcelMises(Request $request): string
    {
        // Génération temporaire CSV en attendant l'implémentation Excel complète
        $query = Cotisation::with(['client:id,nom,prenom', 'commercial:id,nom,prenom']);

        if ($request->filled('date_debut')) $query->whereDate('date_cotisation', '>=', $request->date_debut);
        if ($request->filled('date_fin'))   $query->whereDate('date_cotisation', '<=', $request->date_fin);
        if ($request->filled('statut'))     $query->where('statut', $request->statut);

        $cotisations = $query->orderByDesc('date_cotisation')->get();

        $tmpFile = tempnam(sys_get_temp_dir(), 'mises_');
        $handle  = fopen($tmpFile, 'w');

        fputcsv($handle, ['Date', 'Client', 'Commercial', 'Nb Mises', 'Montant', 'Statut']);
        foreach ($cotisations as $c) {
            fputcsv($handle, [
                $c->date_cotisation->format('d/m/Y'),
                $c->client->nom . ' ' . $c->client->prenom,
                $c->commercial->nom . ' ' . $c->commercial->prenom,
                $c->nombre_mises,
                $c->montant_total,
                $c->statut,
            ]);
        }
        fclose($handle);

        return $tmpFile;
    }
}

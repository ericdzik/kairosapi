<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        h1 { color: #1a237e; font-size: 16px; }
        .info { margin-bottom: 15px; }
        .info span { display: inline-block; margin-right: 20px; }
        .progress-bar { background: #eee; border-radius: 4px; height: 14px; width: 100%; }
        .progress-fill { background: #1a237e; height: 14px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #1a237e; color: white; padding: 6px; text-align: center; font-size: 10px; }
        td { padding: 4px 6px; border: 1px solid #ddd; text-align: center; }
        .coche { color: #2e7d32; font-weight: bold; }
        .vide { color: #ccc; }
        .total-row { background: #e8eaf6; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <h1>KAIROS BUSINESS GROUP — Fiche de Pointage</h1>

    <div class="info">
        <span><strong>Client :</strong> {{ $client->nom }} {{ $client->prenom }}</span>
        <span><strong>Produit :</strong> {{ $client->produit->nom }}</span>
        <span><strong>Durée :</strong> {{ $client->duree_mois }} mois</span>
        <span><strong>Début :</strong> {{ $client->date_debut->format('d/m/Y') }}</span>
        <span><strong>Progression :</strong> {{ $progression }}%</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Nb Mises</th>
                <th>Montant (FCFA)</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotisations as $c)
            <tr>
                <td>{{ $c->date_cotisation->format('d/m/Y') }}</td>
                <td>{{ $c->nombre_mises }}</td>
                <td>{{ number_format($c->montant_total, 0, ',', ' ') }}</td>
                <td class="coche">✓</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td>TOTAL</td>
                <td>{{ $total_mises }}</td>
                <td>{{ number_format($montant, 0, ',', ' ') }}</td>
                <td>{{ $progression }}%</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">Généré le {{ $genere_le }} — KAIROS BUSINESS GROUP</div>
</body>
</html>

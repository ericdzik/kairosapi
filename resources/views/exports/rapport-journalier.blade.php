<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        h1 { color: #1a237e; font-size: 18px; }
        .meta { color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #1a237e; color: white; padding: 8px; text-align: left; }
        td { padding: 6px 8px; border-bottom: 1px solid #eee; }
        tr:nth-child(even) { background: #f5f5f5; }
        .total { font-weight: bold; background: #e8eaf6; }
        .footer { margin-top: 30px; font-size: 10px; color: #999; }
    </style>
</head>
<body>
    <h1>KAIROS BUSINESS GROUP — Rapport Journalier</h1>
    <div class="meta">
        <strong>Date :</strong> {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} &nbsp;|&nbsp;
        <strong>Généré par :</strong> {{ $genere_par }} &nbsp;|&nbsp;
        <strong>Le :</strong> {{ $genere_le }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Client</th>
                <th>Commercial</th>
                <th>Nb Mises</th>
                <th>Montant (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotisations as $c)
            <tr>
                <td>{{ $c->client->nom }} {{ $c->client->prenom }}</td>
                <td>{{ $c->commercial->nom }} {{ $c->commercial->prenom }}</td>
                <td>{{ $c->nombre_mises }}</td>
                <td>{{ number_format($c->montant_total, 0, ',', ' ') }}</td>
            </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">TOTAL</td>
                <td>{{ $total_mises }}</td>
                <td>{{ number_format($montant_total, 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">Document généré automatiquement par KAIROS BUSINESS GROUP</div>
</body>
</html>

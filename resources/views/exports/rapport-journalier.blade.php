<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; padding: 20px; }

        /* En-tête */
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; border-bottom: 3px solid #1a237e; padding-bottom: 12px; }
        .header-title h1 { color: #1a237e; font-size: 16px; margin-bottom: 2px; }
        .header-title p  { color: #666; font-size: 10px; }
        .header-meta     { text-align: right; font-size: 10px; color: #555; }
        .header-meta strong { color: #1a237e; }

        /* Résumé global */
        .resume { display: flex; gap: 12px; margin-bottom: 18px; }
        .resume-card { flex: 1; background: #e8eaf6; border-left: 4px solid #1a237e; padding: 10px 12px; border-radius: 4px; }
        .resume-card.green  { background: #e8f5e9; border-color: #388e3c; }
        .resume-card.orange { background: #fff8e1; border-color: #f57c00; }
        .resume-card.red    { background: #fce4ec; border-color: #c62828; }
        .resume-card .val   { font-size: 15px; font-weight: bold; color: #1a237e; }
        .resume-card.green  .val { color: #388e3c; }
        .resume-card.orange .val { color: #f57c00; }
        .resume-card.red    .val { color: #c62828; }
        .resume-card .lbl   { font-size: 9px; color: #666; margin-top: 2px; }

        /* Section commercial */
        .commercial-section { margin-bottom: 22px; page-break-inside: avoid; }
        .commercial-header  { background: #1a237e; color: white; padding: 7px 10px; border-radius: 4px 4px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .commercial-header .name { font-size: 12px; font-weight: bold; }
        .commercial-header .stats { font-size: 10px; color: #b0bec5; }

        /* Versement badge */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge.conforme  { background: #e8f5e9; color: #388e3c; }
        .badge.ecart-pos { background: #e3f2fd; color: #1565c0; }
        .badge.ecart-neg { background: #fce4ec; color: #c62828; }
        .badge.non-verse { background: #fff8e1; color: #f57c00; }

        /* Tableau */
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.inner { margin-bottom: 8px; }
        thead th { background: #3949ab; color: white; padding: 5px 7px; text-align: left; }
        thead th.right { text-align: right; }
        tbody td { padding: 4px 7px; border-bottom: 1px solid #e0e0e0; }
        tbody td.right { text-align: right; }
        tbody tr:nth-child(even) { background: #f5f5f5; }
        .subtotal td { font-weight: bold; background: #e8eaf6; }
        .section-label { font-size: 10px; font-weight: bold; color: #555; margin: 8px 0 4px; padding-left: 4px; border-left: 3px solid #3949ab; }

        /* Versement ligne */
        .versement-row { display: flex; gap: 16px; background: #f9f9f9; border: 1px solid #e0e0e0; padding: 8px 10px; border-radius: 0 0 4px 4px; font-size: 10px; }
        .versement-row .item { flex: 1; }
        .versement-row .item strong { display: block; font-size: 12px; }
        .versement-row .item .lbl  { color: #888; font-size: 9px; }
        .ecart-pos { color: #1565c0; font-weight: bold; }
        .ecart-neg { color: #c62828; font-weight: bold; }
        .ecart-ok  { color: #388e3c; font-weight: bold; }

        /* Totaux finaux */
        .totaux-finaux { margin-top: 16px; border-top: 2px solid #1a237e; padding-top: 10px; }
        .totaux-finaux table { width: 50%; margin-left: auto; }
        .totaux-finaux td { padding: 4px 8px; }
        .totaux-finaux .grand-total td { background: #1a237e; color: white; font-weight: bold; font-size: 12px; }

        /* Footer */
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 9px; color: #999; text-align: center; }
    </style>
</head>
<body>

{{-- ── En-tête ─────────────────────────────────────────────────────────────── --}}
<div class="header">
    <div class="header-title">
        <h1>KAIROS BUSINESS GROUP</h1>
        <p>Rapport Journalier des Opérations</p>
    </div>
    <div class="header-meta">
        <div><strong>Date :</strong> {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</div>
        <div><strong>Généré par :</strong> {{ $genere_par }}</div>
        <div><strong>Le :</strong> {{ $genere_le }}</div>
    </div>
</div>

{{-- ── Résumé global ───────────────────────────────────────────────────────── --}}
<div class="resume">
    <div class="resume-card">
        <div class="val">{{ number_format($total_attendu, 0, ',', ' ') }} FCFA</div>
        <div class="lbl">Total attendu</div>
    </div>
    <div class="resume-card green">
        <div class="val">{{ number_format($total_verse, 0, ',', ' ') }} FCFA</div>
        <div class="lbl">Total versé</div>
    </div>
    @php $ecartGlobal = $total_verse - $total_attendu; @endphp
    <div class="resume-card {{ $ecartGlobal == 0 ? 'green' : ($ecartGlobal > 0 ? '' : 'red') }}">
        <div class="val">{{ ($ecartGlobal >= 0 ? '+' : '') . number_format($ecartGlobal, 0, ',', ' ') }} FCFA</div>
        <div class="lbl">Écart global</div>
    </div>
    <div class="resume-card orange">
        <div class="val">{{ $nb_commerciaux }}</div>
        <div class="lbl">Commercial(aux)</div>
    </div>
</div>

{{-- ── Détail par commercial ──────────────────────────────────────────────── --}}
@foreach($commerciaux as $commercial)
@php
    $versement = $commercial['versement'] ?? null;
    $ecart = $versement ? ($versement['ecart'] ?? 0) : null;
    $statutVers = $versement ? ($versement['statut'] ?? null) : null;
    $badgeClass = match($statutVers) {
        'conforme'      => 'conforme',
        'ecart_positif' => 'ecart-pos',
        'ecart_negatif' => 'ecart-neg',
        default         => 'non-verse',
    };
    $badgeLabel = match($statutVers) {
        'conforme'      => 'Conforme',
        'ecart_positif' => 'Écart +',
        'ecart_negatif' => 'Écart -',
        default         => 'Non versé',
    };
@endphp
<div class="commercial-section">
    <div class="commercial-header">
        <span class="name">{{ $commercial['commercial']['nom'] }} {{ $commercial['commercial']['prenom'] }}</span>
        <span class="stats">
            {{ count($commercial['cotisations']) }} mise(s) &nbsp;•&nbsp;
            {{ count($commercial['ventes']) }} vente(s) &nbsp;•&nbsp;
            Attendu : {{ number_format($commercial['montant_attendu'], 0, ',', ' ') }} FCFA
            &nbsp;&nbsp;
            <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
        </span>
    </div>

    {{-- Cotisations --}}
    @if(count($commercial['cotisations']) > 0)
    <div class="section-label">Mises / Cotisations</div>
    <table class="inner">
        <thead>
            <tr>
                <th>Client</th>
                <th>Tontine / Produit</th>
                <th class="right">Nb mises</th>
                <th class="right">Montant (FCFA)</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($commercial['cotisations'] as $c)
            <tr>
                <td>{{ ($c['client']['nom'] ?? '') }} {{ ($c['client']['prenom'] ?? '') }}</td>
                <td>{{ $c['tontine']['montant_mise'] ?? '—' }} FCFA/mise</td>
                <td class="right">{{ $c['nombre_mises'] }}</td>
                <td class="right">{{ number_format($c['montant_total'], 0, ',', ' ') }}</td>
                <td>{{ ucfirst($c['statut']) }}</td>
            </tr>
            @endforeach
            <tr class="subtotal">
                <td colspan="2">Sous-total mises</td>
                <td class="right">{{ collect($commercial['cotisations'])->sum('nombre_mises') }}</td>
                <td class="right">{{ number_format(collect($commercial['cotisations'])->sum('montant_total'), 0, ',', ' ') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    @endif

    {{-- Ventes --}}
    @if(count($commercial['ventes']) > 0)
    <div class="section-label">Ventes directes</div>
    <table class="inner">
        <thead>
            <tr>
                <th>Client</th>
                <th>Produit</th>
                <th class="right">Qté</th>
                <th class="right">Montant (FCFA)</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($commercial['ventes'] as $v)
            <tr>
                <td>{{ ($v['client']['nom'] ?? $v['client_nom'] ?? '') }} {{ $v['client']['prenom'] ?? '' }}</td>
                <td>{{ $v['produit']['nom'] ?? '—' }}</td>
                <td class="right">{{ $v['quantite'] }}</td>
                <td class="right">{{ number_format($v['montant'], 0, ',', ' ') }}</td>
                <td>{{ ucfirst($v['statut']) }}</td>
            </tr>
            @endforeach
            <tr class="subtotal">
                <td colspan="3">Sous-total ventes</td>
                <td class="right">{{ number_format(collect($commercial['ventes'])->sum('montant'), 0, ',', ' ') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    @endif

    {{-- Versement --}}
    <div class="versement-row">
        <div class="item">
            <span class="lbl">Montant attendu</span>
            <strong>{{ number_format($commercial['montant_attendu'], 0, ',', ' ') }} FCFA</strong>
        </div>
        @if($versement)
        <div class="item">
            <span class="lbl">Montant versé</span>
            <strong>{{ number_format($versement['montant_verse'], 0, ',', ' ') }} FCFA</strong>
        </div>
        <div class="item">
            <span class="lbl">Écart</span>
            <strong class="{{ $ecart == 0 ? 'ecart-ok' : ($ecart > 0 ? 'ecart-pos' : 'ecart-neg') }}">
                {{ ($ecart >= 0 ? '+' : '') . number_format($ecart, 0, ',', ' ') }} FCFA
            </strong>
        </div>
        @if($versement['notes'])
        <div class="item" style="flex: 2;">
            <span class="lbl">Notes</span>
            <strong>{{ $versement['notes'] }}</strong>
        </div>
        @endif
        @else
        <div class="item" style="color: #f57c00; font-weight: bold;">
            Versement non enregistré
        </div>
        @endif
    </div>
</div>
@endforeach

{{-- ── Totaux finaux ───────────────────────────────────────────────────────── --}}
<div class="totaux-finaux">
    <table>
        <tbody>
            <tr>
                <td>Total mises</td>
                <td class="right">{{ number_format($total_mises, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td>Total ventes</td>
                <td class="right">{{ number_format($total_ventes, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr class="grand-total">
                <td>TOTAL ATTENDU</td>
                <td class="right">{{ number_format($total_attendu, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr class="grand-total">
                <td>TOTAL VERSÉ</td>
                <td class="right">{{ number_format($total_verse, 0, ',', ' ') }} FCFA</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="footer">
    Document généré automatiquement par KAIROS BUSINESS GROUP &nbsp;•&nbsp; {{ $genere_le }}
</div>

</body>
</html>

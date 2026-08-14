<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Clôture caisse — {{ $report['session']['number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 28px; }
        h1 { font-size: 20px; margin: 0; color: #1f6b4a; }
        h2 { font-size: 12px; margin: 18px 0 8px; border-bottom: 1px solid #d6d0c4; padding-bottom: 4px; text-transform: uppercase; letter-spacing: .08em; color: #5a554c; }
        .muted { color: #6b655c; }
        .brand { font-size: 10px; letter-spacing: .18em; text-transform: uppercase; color: #8a8378; margin-bottom: 4px; }
        .kpi { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .kpi td { width: 25%; vertical-align: top; padding: 8px 6px; border: 1px solid #e6e0d6; }
        .kpi .label { font-size: 9px; text-transform: uppercase; letter-spacing: .1em; color: #6b655c; }
        .kpi .value { font-size: 13px; font-weight: bold; margin-top: 4px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border-bottom: 1px solid #e6e0d6; padding: 5px 4px; text-align: left; }
        table.data th { font-size: 9px; text-transform: uppercase; color: #6b655c; }
        table.data td.num, table.data th.num { text-align: right; }
        ul.summary { padding-left: 16px; margin: 6px 0; }
        ul.summary li { margin-bottom: 4px; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .meta td.k { width: 32%; color: #6b655c; }
        .footer { margin-top: 28px; font-size: 9px; color: #8a8378; border-top: 1px solid #e6e0d6; padding-top: 8px; }
        .badge { display: inline-block; padding: 1px 6px; border: 1px solid #d6d0c4; font-size: 9px; text-transform: uppercase; }
        .warn { color: #9a3412; }
        .crit { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $fmtQty = fn ($v) => number_format((float) $v, 3, ',', '');
@endphp

<div class="brand">Manolya Pharma</div>
<h1>Clôture de caisse</h1>
<p class="muted">{{ $report['tenant'] }} · Session {{ $report['session']['number'] }}</p>

<h2>Identité &amp; période</h2>
<table class="meta">
    <tr><td class="k">Caissier(ère)</td><td>{{ $report['cashier']['name'] ?? '—' }}</td></tr>
    <tr><td class="k">Identifiant</td><td>{{ $report['cashier']['email'] ?? '—' }}</td></tr>
    @if (!empty($report['cashier']['phone']))
        <tr><td class="k">Téléphone</td><td>{{ $report['cashier']['phone'] }}</td></tr>
    @endif
    <tr><td class="k">Site</td><td>{{ $report['session']['site'] ?? '—' }}</td></tr>
    <tr><td class="k">Entrepôt</td><td>{{ $report['session']['warehouse'] ?? '—' }}</td></tr>
    <tr><td class="k">Ouverture</td><td>{{ $report['session']['opened_at'] ?? '—' }}</td></tr>
    <tr><td class="k">Clôture</td><td>{{ $report['session']['closed_at'] ?? '—' }}</td></tr>
    <tr><td class="k">Clôturée par</td><td>{{ $report['closer']['name'] ?? $report['cashier']['name'] ?? '—' }}</td></tr>
</table>

<h2>Résumé</h2>
<ul class="summary">
    @foreach ($report['summary_lines'] as $line)
        <li>{{ $line }}</li>
    @endforeach
</ul>

<h2>Caisse (espèces)</h2>
<table class="kpi">
    <tr>
        <td><div class="label">Fond de caisse</div><div class="value">{{ $fmt($report['cashbox']['opening_float']) }} Fc</div></td>
        <td><div class="label">Ventes cash</div><div class="value">{{ $fmt($report['cashbox']['cash_sales']) }} Fc</div></td>
        <td><div class="label">Remb. cash</div><div class="value">{{ $fmt($report['cashbox']['cash_refunds']) }} Fc</div></td>
        <td><div class="label">Attendu</div><div class="value">{{ $fmt($report['cashbox']['expected_cash']) }} Fc</div></td>
    </tr>
    <tr>
        <td><div class="label">Compté</div><div class="value">{{ $fmt($report['cashbox']['closing_counted']) }} Fc</div></td>
        <td><div class="label">Écart</div><div class="value {{ abs($report['cashbox']['variance']) > 0.009 ? 'crit' : '' }}">{{ $fmt($report['cashbox']['variance']) }} Fc</div></td>
        <td><div class="label">Tickets</div><div class="value">{{ $report['payments']['sales_count'] }}</div></td>
        <td><div class="label">Retours</div><div class="value">{{ $report['payments']['returns_count'] }}</div></td>
    </tr>
</table>
@if (!empty($report['session']['closing_notes']))
    <p class="muted">Notes de clôture : {{ $report['session']['closing_notes'] }}</p>
@endif

<h2>Paiements (toutes méthodes)</h2>
<table class="data">
    <thead>
    <tr>
        <th>Mode</th>
        <th class="num">Montant</th>
    </tr>
    </thead>
    <tbody>
    <tr><td>Espèces</td><td class="num">{{ $fmt($report['payments']['cash']) }} Fc</td></tr>
    <tr><td>Carte</td><td class="num">{{ $fmt($report['payments']['card']) }} Fc</td></tr>
    <tr><td>Mobile Money</td><td class="num">{{ $fmt($report['payments']['mobile_money']) }} Fc</td></tr>
    <tr><td><strong>Total CA session</strong></td><td class="num"><strong>{{ $fmt($report['payments']['grand_total']) }} Fc</strong></td></tr>
    </tbody>
</table>

<h2>Articles vendus</h2>
@if (count($report['articles']))
    <table class="data">
        <thead>
        <tr>
            <th>SKU</th>
            <th>Article</th>
            <th class="num">Qté</th>
            <th class="num">Montant</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($report['articles'] as $row)
            <tr>
                <td>{{ $row['sku'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="num">{{ $fmtQty($row['qty']) }}</td>
                <td class="num">{{ $fmt($row['revenue']) }} Fc</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <p class="muted">Aucun article vendu sur cette session.</p>
@endif

@if (count($report['returns']))
    <h2>Retours / remboursements</h2>
    <table class="data">
        <thead>
        <tr>
            <th>N°</th>
            <th>Mode</th>
            <th>Date</th>
            <th class="num">Montant</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($report['returns'] as $ret)
            <tr>
                <td>{{ $ret['number'] }}</td>
                <td>{{ $ret['method'] }}</td>
                <td>{{ $ret['processed_at'] }}</td>
                <td class="num">{{ $fmt($ret['total']) }} Fc</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>Stock — ruptures &amp; seuils</h2>
@if (count($report['stock_alerts']))
    <table class="data">
        <thead>
        <tr>
            <th>Niveau</th>
            <th>SKU</th>
            <th>Produit</th>
            <th class="num">Stock</th>
            <th class="num">Seuil</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($report['stock_alerts'] as $a)
            <tr>
                <td class="{{ $a['level'] === 'rupture' ? 'crit' : ($a['level'] === 'critique' ? 'warn' : '') }}">
                    <span class="badge">{{ $a['level'] }}</span>
                </td>
                <td>{{ $a['sku'] }}</td>
                <td>{{ $a['name'] }}</td>
                <td class="num">{{ $fmtQty($a['qty']) }}</td>
                <td class="num">{{ $fmtQty($a['threshold']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <p class="muted">Aucun produit en rupture ou sous seuil au moment de la clôture.</p>
@endif

<div class="footer">
    Généré le {{ $report['generated_at'] }} · Traçabilité de session — usage interne pharmacie uniquement · Manolya Pharma
</div>
</body>
</html>

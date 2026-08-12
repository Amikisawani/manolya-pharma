<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport quotidien — {{ $report['tenant'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 28px; }
        h1 { font-size: 20px; margin: 0; color: #1f6b4a; }
        h2 { font-size: 13px; margin: 18px 0 8px; border-bottom: 1px solid #d6d0c4; padding-bottom: 4px; text-transform: uppercase; letter-spacing: .08em; color: #5a554c; }
        .muted { color: #6b655c; }
        .kpi { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .kpi td { width: 25%; vertical-align: top; padding: 8px 6px; border: 1px solid #e6e0d6; }
        .kpi .label { font-size: 9px; text-transform: uppercase; letter-spacing: .1em; color: #6b655c; }
        .kpi .value { font-size: 14px; font-weight: bold; margin-top: 4px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border-bottom: 1px solid #e6e0d6; padding: 5px 4px; text-align: left; }
        table.data th { font-size: 9px; text-transform: uppercase; color: #6b655c; }
        ul.summary { padding-left: 16px; margin: 6px 0; }
        ul.summary li { margin-bottom: 4px; }
        .footer { margin-top: 28px; font-size: 9px; color: #8a8378; border-top: 1px solid #e6e0d6; padding-top: 8px; }
        .brand { font-size: 10px; letter-spacing: .18em; text-transform: uppercase; color: #8a8378; margin-bottom: 4px; }
    </style>
</head>
<body>
    @php
        $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="brand">Manolya Pharma</div>
    <h1>Rapport quotidien</h1>
    <p class="muted">{{ $report['tenant'] }} · {{ $report['period_label'] }}</p>

    <h2>Résumé exécutif</h2>
    <ul class="summary">
        @foreach ($report['summary_lines'] as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>

    <h2>Indicateurs</h2>
    <table class="kpi">
        <tr>
            <td><div class="label">CA</div><div class="value">{{ $fmt($report['ca']) }} Fc</div></td>
            <td><div class="label">Marge</div><div class="value">{{ $fmt($report['profit']) }} Fc</div></td>
            <td><div class="label">Dépenses</div><div class="value">{{ $fmt($report['expenses']) }} Fc</div></td>
            <td><div class="label">Net</div><div class="value">{{ $fmt($report['net']) }} Fc</div></td>
        </tr>
        <tr>
            <td><div class="label">Ventes</div><div class="value">{{ $report['sales_count'] }}</div></td>
            <td><div class="label">Panier moyen</div><div class="value">{{ $fmt($report['avg_basket']) }} Fc</div></td>
            <td><div class="label">Alertes ouvertes</div><div class="value">{{ count($report['open_alerts']) }}</div></td>
            <td><div class="label">Lots expirés</div><div class="value">{{ count($report['expired_lots']) }}</div></td>
        </tr>
    </table>

    <h2>Top produits</h2>
    @if (count($report['top_products']))
        <table class="data">
            <thead><tr><th>Produit</th><th>Qté</th><th>CA</th></tr></thead>
            <tbody>
            @foreach ($report['top_products'] as $p)
                <tr>
                    <td>{{ $p['name'] }}</td>
                    <td>{{ $p['qty'] }}</td>
                    <td>{{ $fmt($p['revenue']) }} Fc</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Aucune vente sur la période.</p>
    @endif

    <h2>Stock critique</h2>
    @if (count($report['critical_products']))
        <table class="data">
            <thead><tr><th>SKU</th><th>Produit</th><th>Stock</th><th>Seuil</th></tr></thead>
            <tbody>
            @foreach ($report['critical_products'] as $p)
                <tr>
                    <td>{{ $p['sku'] }}</td>
                    <td>{{ $p['name'] }}</td>
                    <td>{{ $p['qty'] }}</td>
                    <td>{{ $p['critical'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Aucun produit sous seuil critique.</p>
    @endif

    <h2>Alertes ouvertes</h2>
    @if (count($report['open_alerts']))
        <table class="data">
            <thead><tr><th>Sévérité</th><th>Type</th><th>Titre</th></tr></thead>
            <tbody>
            @foreach ($report['open_alerts'] as $a)
                <tr>
                    <td>{{ $a['severity'] }}</td>
                    <td>{{ $a['type'] }}</td>
                    <td>{{ $a['title'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Aucune alerte ouverte.</p>
    @endif

    <div class="footer">
        Généré le {{ $report['generated_at'] }} · Document confidentiel — usage interne pharmacie uniquement.
    </div>
</body>
</html>

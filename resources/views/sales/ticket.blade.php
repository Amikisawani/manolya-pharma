<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $ticket['number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 28px; }
        h1 { font-size: 22px; margin: 0; color: #1f6b4a; }
        h2 { font-size: 12px; margin: 18px 0 8px; border-bottom: 1px solid #d6d0c4; padding-bottom: 4px; text-transform: uppercase; letter-spacing: .08em; color: #5a554c; }
        .muted { color: #6b655c; }
        .brand { font-size: 10px; letter-spacing: .18em; text-transform: uppercase; color: #8a8378; margin-bottom: 4px; }
        .subtitle { font-size: 11px; color: #6b655c; margin-top: 2px; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .meta td.k { width: 32%; color: #6b655c; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th, table.data td { border-bottom: 1px solid #e6e0d6; padding: 6px 4px; text-align: left; vertical-align: top; }
        table.data th { font-size: 9px; text-transform: uppercase; color: #6b655c; }
        table.data td.num, table.data th.num { text-align: right; }
        .totals { width: 46%; margin-left: auto; border-collapse: collapse; margin-top: 12px; }
        .totals td { padding: 4px 0; }
        .totals td.k { color: #6b655c; }
        .totals td.num { text-align: right; }
        .totals tr.grand td { font-weight: bold; font-size: 13px; border-top: 1px solid #d6d0c4; padding-top: 8px; }
        .footer { margin-top: 28px; font-size: 9px; color: #8a8378; border-top: 1px solid #e6e0d6; padding-top: 8px; text-align: center; }
        .number { font-family: DejaVu Sans Mono, monospace; font-size: 12px; margin-top: 8px; }
    </style>
</head>
<body>
@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $fmtQty = fn ($v) => number_format((float) $v, 3, ',', '');
@endphp

<div class="brand">Manolya Pharma</div>
<h1>Facture</h1>
<div class="subtitle">{{ $ticket['tenant'] }}</div>
<div class="number">{{ $ticket['number'] }}</div>
<p class="muted">Émise le {{ $ticket['completed_at'] }} · Devise {{ $ticket['currency'] }}</p>

<h2>Identité</h2>
<table class="meta">
    <tr><td class="k">Caissier(ère)</td><td>{{ $ticket['cashier']['name'] ?? '—' }}</td></tr>
    @if (!empty($ticket['cashier']['email']))
        <tr><td class="k">Identifiant</td><td>{{ $ticket['cashier']['email'] }}</td></tr>
    @endif
    @if (!empty($ticket['cashier']['phone']))
        <tr><td class="k">Téléphone</td><td>{{ $ticket['cashier']['phone'] }}</td></tr>
    @endif
    <tr><td class="k">Site</td><td>{{ $ticket['site'] ?? '—' }}</td></tr>
    <tr><td class="k">Entrepôt</td><td>{{ $ticket['warehouse'] ?? '—' }}</td></tr>
    @if (!empty($ticket['session']))
        <tr><td class="k">Session caisse</td><td>{{ $ticket['session'] }}</td></tr>
    @endif
</table>

<h2>Articles</h2>
<table class="data">
    <thead>
        <tr>
            <th>Produit</th>
            <th class="num">Qté</th>
            <th class="num">P.U.</th>
            <th class="num">Montant</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($ticket['lines'] as $line)
        <tr>
            <td>
                <strong>{{ $line['name'] }}</strong>
                <div class="muted">
                    @if (!empty($line['sku']))SKU {{ $line['sku'] }}@endif
                    @if (!empty($line['lot'])) · Lot {{ $line['lot'] }}@endif
                    @if ((float) $line['qty_returned'] > 0) · Retourné {{ $fmtQty($line['qty_returned']) }}@endif
                </div>
            </td>
            <td class="num">{{ $fmtQty($line['qty']) }}</td>
            <td class="num">{{ $fmt($line['unit_price']) }} Fc</td>
            <td class="num">{{ $fmt($line['line_total']) }} Fc</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="k">Sous-total</td>
        <td class="num">{{ $fmt($ticket['subtotal']) }} Fc</td>
    </tr>
    @if ((float) $ticket['discount_total'] > 0)
        <tr>
            <td class="k">Remise</td>
            <td class="num">− {{ $fmt($ticket['discount_total']) }} Fc</td>
        </tr>
    @endif
    <tr class="grand">
        <td class="k">Total</td>
        <td class="num">{{ $fmt($ticket['grand_total']) }} Fc</td>
    </tr>
</table>

<h2>Paiements</h2>
<table class="data">
    <thead>
        <tr>
            <th>Mode</th>
            <th class="num">Montant</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($ticket['payments'] as $payment)
        <tr>
            <td>
                {{ $payment['method'] }}
                @if (!empty($payment['provider']))
                    <span class="muted">· {{ $payment['provider'] }}</span>
                @endif
            </td>
            <td class="num">{{ $fmt($payment['amount']) }} Fc</td>
        </tr>
    @empty
        <tr><td colspan="2" class="muted">Aucun paiement enregistré</td></tr>
    @endforelse
    </tbody>
</table>

<div class="footer">
    Merci de votre confiance · Manolya Pharma<br>
    Document généré le {{ $ticket['generated_at'] }} · Facture de vente
</div>
</body>
</html>

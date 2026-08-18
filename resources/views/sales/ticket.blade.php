<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $ticket['number'] }}</title>
    <style>
        @page { margin: 4mm 3mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            color: #111;
            margin: 0;
            width: 52mm;
        }
        h1 { font-size: 14pt; margin: 0; text-align: center; text-transform: uppercase; }
        .pharmacy { font-size: 11pt; text-align: center; font-weight: bold; margin-top: 2mm; }
        .meta { font-size: 9.5pt; text-align: center; margin-top: 1mm; }
        .rule { border: 0; border-top: 1px dashed #111; margin: 3mm 0; }
        .kv { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        .kv td { padding: 0.6mm 0; vertical-align: top; }
        .kv td.k { font-weight: bold; width: 38%; }
        .kv td.v { text-align: right; }
        .line { margin-bottom: 2.2mm; }
        .line-name { font-size: 10.5pt; font-weight: bold; }
        .line-qty { font-size: 10pt; }
        .right { text-align: right; }
        .row { width: 100%; border-collapse: collapse; font-size: 10pt; }
        .row td { padding: 0.7mm 0; }
        .total td { font-size: 13pt; font-weight: bold; padding-top: 2mm; }
        .foot { text-align: center; font-size: 9.5pt; margin-top: 2mm; }
    </style>
</head>
<body>
@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $fmtQty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ''), '0'), ',');
@endphp

<h1>Manolya Pharma</h1>
<div class="pharmacy">{{ $ticket['tenant'] }}</div>
@if (!empty($ticket['site']))
    <div class="meta">{{ $ticket['site'] }}</div>
@endif
<hr class="rule">

<table class="kv">
    <tr><td class="k">N°</td><td class="v">{{ $ticket['number'] }}</td></tr>
    <tr><td class="k">Date</td><td class="v">{{ $ticket['completed_at'] }}</td></tr>
    <tr><td class="k">Caisse</td><td class="v">{{ $ticket['cashier']['name'] ?? '—' }}</td></tr>
    @if (!empty($ticket['session']))
        <tr><td class="k">Sess.</td><td class="v">{{ $ticket['session'] }}</td></tr>
    @endif
</table>

<hr class="rule">

@foreach ($ticket['lines'] as $line)
    <div class="line">
        <div class="line-name">{{ $line['name'] }}</div>
        <table class="row">
            <tr>
                <td>{{ $fmtQty($line['qty']) }} x {{ $fmt($line['unit_price']) }} Fc</td>
                <td class="right">{{ $fmt($line['line_total']) }} Fc</td>
            </tr>
        </table>
        @if (!empty($line['lot']))
            <div class="meta" style="text-align:left">Lot {{ $line['lot'] }}</div>
        @endif
    </div>
@endforeach

<hr class="rule">

<table class="row">
    <tr>
        <td>Sous-total</td>
        <td class="right">{{ $fmt($ticket['subtotal']) }} Fc</td>
    </tr>
    @if ((float) $ticket['discount_total'] > 0)
        <tr>
            <td>Remise</td>
            <td class="right">− {{ $fmt($ticket['discount_total']) }} Fc</td>
        </tr>
    @endif
    <tr class="total">
        <td>TOTAL</td>
        <td class="right">{{ $fmt($ticket['grand_total']) }} Fc</td>
    </tr>
</table>

<hr class="rule">

@foreach ($ticket['payments'] as $payment)
    <table class="row">
        <tr>
            <td>{{ $payment['method'] }}</td>
            <td class="right">{{ $fmt($payment['amount']) }} Fc</td>
        </tr>
    </table>
@endforeach

<hr class="rule">
<div class="foot">Merci !<br>{{ $ticket['generated_at'] }}</div>
</body>
</html>

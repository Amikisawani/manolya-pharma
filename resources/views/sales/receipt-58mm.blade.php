<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $receipt->saleNumber }}</title>
    <style>{!! file_get_contents(resource_path('css/receipt-print-58mm.css')) !!}</style>
</head>
<body>
<article class="mp-print-ticket" aria-label="Ticket de caisse 58 millimètres">
    @if ($receipt->statusLabel)
        <p class="mp-print-status">{{ $receipt->statusLabel }}</p>
    @endif
    @if ($receipt->isReprint)
        <p class="mp-print-status">DUPLICATA</p>
    @endif

    <h1 class="mp-print-brand">{{ $receipt->brandName }}</h1>
    <p class="mp-print-pharmacy">{{ $receipt->pharmacyName }}</p>
    @if ($receipt->siteName)
        <p class="mp-print-meta">{{ $receipt->siteName }}</p>
    @endif
    @if ($receipt->address)
        <p class="mp-print-meta">{{ $receipt->address }}</p>
    @endif

    <hr class="mp-print-rule">

    <table class="mp-print-kv">
        <tr>
            <td class="k">N°</td>
            <td class="v">{{ $receipt->saleNumber }}</td>
        </tr>
        <tr>
            <td class="k">Date</td>
            <td class="v">{{ $receipt->soldAtDate }}</td>
        </tr>
        <tr>
            <td class="k">Heure</td>
            <td class="v">{{ $receipt->soldAtTime }}</td>
        </tr>
        <tr>
            <td class="k">Caisse</td>
            <td class="v">{{ $receipt->cashierName }}</td>
        </tr>
        <tr>
            <td class="k">Paiem.</td>
            <td class="v">{{ $receipt->paymentLabel }}</td>
        </tr>
        @if ($receipt->registerNumber)
            <tr>
                <td class="k">Sess.</td>
                <td class="v">{{ $receipt->registerNumber }}</td>
            </tr>
        @endif
    </table>

    <hr class="mp-print-rule">

    @foreach ($receipt->lines as $line)
        <div class="mp-print-line">
            <div class="mp-print-line-name">{{ $line['name'] }}</div>
            <div class="mp-print-line-qty">
                <span>{{ $line['quantity_label'] }}</span>
                <span>{{ $line['line_total'] }}</span>
            </div>
            @if (! empty($line['lot']))
                <div class="mp-print-lot">Lot {{ $line['lot'] }}</div>
            @endif
        </div>
    @endforeach

    <hr class="mp-print-rule">

    <div>
        <div class="mp-print-total-row">
            <span>Sous-total</span>
            <span>{{ $receipt->subtotal }}</span>
        </div>
        @if ($receipt->discount)
            <div class="mp-print-total-row">
                <span>Remise</span>
                <span>- {{ $receipt->discount }}</span>
            </div>
        @endif
        <hr class="mp-print-rule mp-print-rule-solid">
        <div class="mp-print-due">
            <span>TOTAL</span>
            <span>{{ $receipt->grandTotal }}</span>
        </div>
        <div class="mp-print-total-row">
            <span>Payé</span>
            <span>{{ $receipt->amountPaid }}</span>
        </div>
        @if ($receipt->change)
            <div class="mp-print-total-row">
                <span>Monnaie</span>
                <span>{{ $receipt->change }}</span>
            </div>
        @endif
    </div>

    <hr class="mp-print-rule">

    <p class="mp-print-foot">{{ $receipt->itemCountLabel }}</p>
    <p class="mp-print-foot mp-print-thanks">Merci !</p>
    <p class="mp-print-foot">{{ $receipt->footerMessage }}</p>
</article>
</body>
</html>

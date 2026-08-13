import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

type CurrencyShare = {
    code: string;
    symbol: string;
    rates: { USD: number; EUR: number };
};

const defaults: CurrencyShare = {
    code: 'CDF',
    symbol: 'Fc',
    rates: { USD: 2350, EUR: 2702.5 },
};

function normalizeCurrency(shared: unknown): CurrencyShare {
    if (!shared || typeof shared !== 'object') {
        return defaults;
    }

    const value = shared as Partial<CurrencyShare> & { rates?: Partial<CurrencyShare['rates']> };

    return {
        code: value.code ?? defaults.code,
        symbol: value.symbol ?? defaults.symbol,
        rates: {
            USD: Number(value.rates?.USD ?? defaults.rates.USD) || defaults.rates.USD,
            EUR: Number(value.rates?.EUR ?? defaults.rates.EUR) || defaults.rates.EUR,
        },
    };
}

export function useMoney() {
    const page = usePage();

    const currency = computed<CurrencyShare>(() =>
        normalizeCurrency((page.props as { currency?: unknown }).currency),
    );

    const formatFc = (amount: string | number | null | undefined): string => {
        const n = Number(amount || 0);
        return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n)} ${currency.value.symbol}`;
    };

    const toUsd = (amount: string | number | null | undefined): string => {
        const rate = currency.value.rates.USD || 1;
        const n = Number(amount || 0) / rate;
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n);
    };

    const toEur = (amount: string | number | null | undefined): string => {
        const rate = currency.value.rates.EUR || 1;
        const n = Number(amount || 0) / rate;
        return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(n);
    };

    const equivalents = (amount: string | number | null | undefined): string =>
        `≈ ${toUsd(amount)} · ${toEur(amount)}`;

    return { currency, formatFc, toUsd, toEur, equivalents };
}

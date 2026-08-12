import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

type CurrencyShare = {
    code: string;
    symbol: string;
    rates: { USD: number; EUR: number };
};

export function useMoney() {
    const page = usePage();

    const currency = computed<CurrencyShare>(() => {
        const shared = (page.props as { currency?: CurrencyShare }).currency;
        return (
            shared ?? {
                code: 'CDF',
                symbol: 'Fc',
                rates: { USD: 2850, EUR: 3100 },
            }
        );
    });

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

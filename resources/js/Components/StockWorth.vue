<script setup lang="ts">
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type StockWorth = {
    cost: string | number;
    sale_value: string | number;
    expected_profit: string | number;
    units: string | number;
    products: number;
};

const page = usePage();

const worth = computed(() => {
    const value = (page.props as { stockWorth?: StockWorth | null }).stockWorth;

    return value ?? null;
});

const summary = computed(() => {
    if (!worth.value) {
        return '';
    }

    const products = new Intl.NumberFormat('fr-FR').format(worth.value.products);
    const units = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 }).format(Number(worth.value.units));

    return `${products} produits · ${units} unités en stock`;
});
</script>

<template>
    <section
        v-if="worth"
        class="border px-5 py-5"
        style="border-color: var(--mp-line); background: rgba(31, 107, 74, 0.06)"
    >
        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">
            Stock présent
        </p>
        <p class="mt-1 text-sm text-[color:var(--mp-muted)]">{{ summary }}</p>

        <div class="mt-4 grid gap-6 md:grid-cols-3">
            <div>
                <div class="mp-metric-label">Ce que nous valons</div>
                <MoneyAmount class="mt-2" :amount="worth.cost" size="lg" />
                <p class="mt-1 text-xs text-[color:var(--mp-muted)]">
                    Montant des produits présents, au prix d’achat de chaque lot.
                </p>
            </div>
            <div>
                <div class="mp-metric-label">Ce que nous pouvons atteindre</div>
                <MoneyAmount class="mt-2" :amount="worth.sale_value" size="lg" />
                <p class="mt-1 text-xs text-[color:var(--mp-muted)]">
                    Si tout ce stock est vendu aux prix de vente actuels.
                </p>
            </div>
            <div>
                <div class="mp-metric-label">Bénéfice prévu</div>
                <MoneyAmount class="mt-2" :amount="worth.expected_profit" size="lg" />
                <p class="mt-1 text-xs text-[color:var(--mp-muted)]">
                    Écart entre le prix de vente et le prix d’achat du stock présent.
                </p>
            </div>
        </div>
    </section>
</template>

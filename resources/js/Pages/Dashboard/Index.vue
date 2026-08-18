<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import ApexCharts from 'apexcharts';

const props = defineProps<{
    kpis: Record<string, string | number>;
    criticalProducts: Array<Record<string, unknown>>;
    topProductsToday: Array<Record<string, unknown>>;
    topCategories: Array<Record<string, unknown>>;
    pendingClosures?: Array<Record<string, any>>;
    rejectedClosures?: Array<Record<string, any>>;
    canReviewSessions?: boolean;
    canApproveSessions?: boolean;
    chartPlaceholder: { labels: string[]; series: number[] };
}>();

const actingOn = ref<number | string | null>(null);

const postAction = (url: string, id: number | string) => {
    actingOn.value = id;
    router.post(url, {}, { onFinish: () => { actingOn.value = null; } });
};

const chartEl = ref<HTMLElement | null>(null);
let chart: ApexCharts | null = null;

const renderChart = () => {
    if (!chartEl.value) return;
    chart?.destroy();
    chart = new ApexCharts(chartEl.value, {
        chart: {
            type: 'area',
            height: 240,
            toolbar: { show: false },
            fontFamily: 'DM Sans, sans-serif',
            background: 'transparent',
        },
        series: [{ name: 'CA', data: props.chartPlaceholder.series }],
        xaxis: {
            categories: props.chartPlaceholder.labels,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: '#9a938b', fontSize: '11px' } },
        },
        yaxis: {
            labels: {
                style: { colors: '#9a938b', fontSize: '11px' },
                formatter: (v: number) => new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(v),
            },
        },
        colors: ['#1f6b4a'],
        dataLabels: { enabled: false },
        stroke: { curve: 'straight', width: 1.5 },
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.28, opacityTo: 0.02, stops: [0, 90, 100] },
        },
        grid: { borderColor: '#ddd4c8', strokeDashArray: 3 },
        tooltip: {
            y: {
                formatter: (v: number) =>
                    `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v)} Fc`,
            },
        },
    });
    chart.render();
};

onMounted(renderChart);
watch(() => props.chartPlaceholder, renderChart, { deep: true });
</script>

<template>
    <Head title="Tableau de bord" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">
                        Pilotage
                    </p>
                    <h1 class="mp-display mt-1 text-4xl text-[color:var(--mp-ink)]">Manolya Pharma</h1>
                    <p class="mt-2 max-w-xl text-sm text-[color:var(--mp-muted)]">
                        L’essentiel de votre officine, en un regard.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="canReviewSessions"
                        :href="route('reports.cash-sessions.index')"
                        class="mp-btn mp-btn-ghost"
                    >
                        Rapport caisse
                    </Link>
                    <Link :href="route('pos.index')" class="mp-btn mp-btn-primary">Nouvelle vente</Link>
                </div>
            </div>
        </template>

        <section class="grid gap-x-10 gap-y-2 border-y py-2 md:grid-cols-2 xl:grid-cols-4" style="border-color: var(--mp-line)">
            <div class="mp-metric">
                <div class="mp-metric-label">CA du jour</div>
                <MoneyAmount class="mt-2" :amount="kpis.ca_today" size="lg" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Profit du jour</div>
                <MoneyAmount class="mt-2" :amount="kpis.profit_today" size="lg" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">CA du mois</div>
                <MoneyAmount class="mt-2" :amount="kpis.ca_month" size="lg" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Profit du mois</div>
                <MoneyAmount class="mt-2" :amount="kpis.profit_month" size="lg" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Dépenses</div>
                <MoneyAmount class="mt-2" :amount="kpis.expenses_month" size="md" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Valeur stock</div>
                <MoneyAmount class="mt-2" :amount="kpis.stock_value" size="md" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Ruptures</div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">{{ kpis.stockouts }}</div>
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Critiques / expirés</div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ kpis.critical_count ?? criticalProducts.length }}
                    <span class="text-[color:var(--mp-faint)]">/</span>
                    {{ kpis.expired_batches }}
                </div>
                <div class="mp-money-fx">Expire ≤ 30 j : {{ kpis.expiring_soon }}</div>
            </div>
        </section>

        <section v-if="canReviewSessions && pendingClosures?.length" class="mt-10">
            <h2 class="mp-section-title">Fermetures de caisse à confirmer</h2>
            <div v-for="session in pendingClosures" :key="session.id" class="mp-row">
                <div>
                    <div class="font-medium">
                        {{ session.opener_name }} demande confirmation de la fermeture
                    </div>
                    <div class="text-xs text-[color:var(--mp-muted)]">
                        {{ session.number }} · {{ session.business_date }} · {{ session.opened_at }}
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('reports.cash-sessions.show', session.id)"
                        class="mp-btn mp-btn-ghost"
                    >
                        Voir
                    </Link>
                    <button
                        v-if="canApproveSessions"
                        type="button"
                        class="mp-btn mp-btn-primary"
                        :disabled="actingOn === session.id"
                        @click="postAction(route('reports.cash-sessions.confirm', session.id), session.id)"
                    >
                        Valider
                    </button>
                    <button
                        v-if="canApproveSessions"
                        type="button"
                        class="mp-btn mp-btn-ghost"
                        :disabled="actingOn === session.id"
                        @click="postAction(route('reports.cash-sessions.reject', session.id), session.id)"
                    >
                        Rejeter
                    </button>
                </div>
            </div>
        </section>

        <section v-if="canApproveSessions && rejectedClosures?.length" class="mt-10">
            <h2 class="mp-section-title">Sessions à clôturer (demande rejetée)</h2>
            <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
                Vous ne pouvez pas vous déconnecter tant que ces caisses ne sont pas fermées.
            </p>
            <div v-for="session in rejectedClosures" :key="session.id" class="mp-row">
                <div>
                    <div class="font-medium">
                        {{ session.opener_name }} — session en cours (demande rejetée)
                    </div>
                    <div class="text-xs text-[color:var(--mp-muted)]">
                        {{ session.number }} · {{ session.business_date }}
                    </div>
                </div>
                <button
                    type="button"
                    class="mp-btn mp-btn-primary"
                    :disabled="actingOn === session.id"
                    @click="postAction(route('reports.cash-sessions.confirm', session.id), session.id)"
                >
                    Clôturer
                </button>
            </div>
        </section>

        <section class="mt-10 grid gap-10 xl:grid-cols-5">
            <div class="xl:col-span-3">
                <h2 class="mp-section-title">Évolution du CA · 7 jours</h2>
                <div ref="chartEl" class="mt-4" />
            </div>
            <div class="xl:col-span-2">
                <h2 class="mp-section-title">Produits critiques</h2>
                <div class="mt-2">
                    <div v-for="p in criticalProducts" :key="String(p.id)" class="mp-row">
                        <span class="text-sm">{{ p.commercial_name }}</span>
                        <span class="mp-badge mp-badge-danger">{{ p.stock_qty }}</span>
                    </div>
                    <p v-if="!criticalProducts.length" class="py-6 text-sm text-[color:var(--mp-muted)]">
                        Aucun produit critique
                    </p>
                </div>
            </div>
        </section>

        <section class="mt-10 grid gap-10 xl:grid-cols-2">
            <div>
                <h2 class="mp-section-title">Top ventes du jour</h2>
                <div class="mt-2">
                    <div v-for="(p, i) in topProductsToday" :key="i" class="mp-row text-sm">
                        <span>{{ p.commercial_name ?? p.name }}</span>
                        <span class="tabular-nums text-[color:var(--mp-muted)]">{{ p.qty_sold ?? p.qty }}</span>
                    </div>
                    <p v-if="!topProductsToday.length" class="py-6 text-sm text-[color:var(--mp-muted)]">Pas encore de ventes</p>
                </div>
            </div>
            <div>
                <h2 class="mp-section-title">Top catégories</h2>
                <div class="mt-2">
                    <div v-for="(c, i) in topCategories" :key="i" class="mp-row">
                        <span class="text-sm">{{ c.name }}</span>
                        <MoneyAmount :amount="(c.revenue ?? c.total) as string | number" size="sm" align="right" />
                    </div>
                    <p v-if="!topCategories.length" class="py-6 text-sm text-[color:var(--mp-muted)]">Données insuffisantes</p>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>

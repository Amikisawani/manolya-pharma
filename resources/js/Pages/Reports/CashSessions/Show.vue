<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    session: Record<string, any>;
    sales: { data: Array<Record<string, any>> };
    returns: Array<Record<string, any>>;
    summary: Record<string, string | number>;
    filters: { q?: string; from?: string; to?: string };
    canApprove: boolean;
}>();

const q = ref(props.filters.q ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const confirmForm = useForm({
    closing_counted: Number(props.session.closing_counted || props.summary.expected_cash || 0),
    closing_notes: props.session.closing_notes ?? '',
});

const unlockForm = useForm({
    user_id: props.session.opened_by,
    business_date: '',
});

const methodLabel = (method: string) =>
    ({ cash: 'Espèces', card: 'Carte', mobile_money: 'Mobile Money' }[method] ?? method);

const searchSales = () =>
    router.get(
        route('reports.cash-sessions.show', props.session.id),
        { q: q.value || undefined, from: from.value || undefined, to: to.value || undefined },
        { preserveState: true },
    );

const confirm = () => confirmForm.post(route('reports.cash-sessions.confirm', props.session.id));
const reject = () => confirmForm.post(route('reports.cash-sessions.reject', props.session.id));
const unlock = () => unlockForm.post(route('reports.cash-sessions.unlock'));
</script>

<template>
    <Head :title="`Rapport ${session.number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Link :href="route('reports.cash-sessions.index')" class="text-xs text-[color:var(--mp-accent)]">
                        ← Rapport caisse
                    </Link>
                    <h1 class="mp-display mt-2 text-4xl">{{ session.number }}</h1>
                    <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
                        {{ session.site_name }} · {{ session.business_date }} · {{ session.status_label }}
                    </p>
                </div>
            </div>
        </template>

        <section class="grid gap-x-8 border-y py-2 md:grid-cols-2 xl:grid-cols-4" style="border-color: var(--mp-line)">
            <div class="mp-metric">
                <div class="mp-metric-label">Ouverture</div>
                <div class="mt-2 text-xl font-semibold">{{ session.opened_at ?? '—' }}</div>
                <div class="mt-1 text-xs text-[color:var(--mp-muted)]">{{ session.opener_name }}</div>
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Fermeture</div>
                <div class="mt-2 text-xl font-semibold">{{ session.closed_at ?? '—' }}</div>
                <div class="mt-1 text-xs text-[color:var(--mp-muted)]">{{ session.closer_name ?? 'En cours' }}</div>
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Total ventes</div>
                <MoneyAmount class="mt-2" :amount="summary.grand_total" size="lg" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Espèces attendues</div>
                <MoneyAmount class="mt-2" :amount="summary.expected_cash" size="lg" />
            </div>
        </section>

        <section class="mt-8 grid gap-6 md:grid-cols-3">
            <div class="mp-metric">
                <div class="mp-metric-label">Ventes</div>
                <div class="mt-2 text-2xl font-semibold">{{ summary.sales_count }}</div>
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Carte</div>
                <MoneyAmount class="mt-2" :amount="summary.card_sales" size="md" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Mobile Money</div>
                <MoneyAmount class="mt-2" :amount="summary.momo_sales" size="md" />
            </div>
        </section>

        <section
            v-if="canApprove && session.status === 'closure_requested'"
            class="mt-10 max-w-md space-y-3 border p-5"
            style="border-color: var(--mp-line)"
        >
            <h2 class="mp-section-title">
                {{ session.opener_name }} demande confirmation de la fermeture
            </h2>
            <p class="text-sm text-[color:var(--mp-muted)]">
                Demandée par {{ session.requester_name ?? session.opener_name }}
                <span v-if="session.closure_requested_at"> · {{ session.closure_requested_at }}</span>
            </p>
            <div>
                <label class="mp-metric-label">Espèces comptées (Fc)</label>
                <input v-model.number="confirmForm.closing_counted" type="number" min="0" step="1" class="mp-input mt-1" />
            </div>
            <textarea v-model="confirmForm.closing_notes" class="mp-input" rows="2" placeholder="Notes" />
            <div class="flex flex-wrap gap-2">
                <button class="mp-btn mp-btn-primary" type="button" :disabled="confirmForm.processing" @click="confirm">
                    Valider
                </button>
                <button class="mp-btn mp-btn-ghost" type="button" :disabled="confirmForm.processing" @click="reject">
                    Rejeter
                </button>
            </div>
        </section>

        <section
            v-else-if="canApprove && session.status === 'open' && session.close_request_rejected"
            class="mt-10 max-w-md space-y-3 border p-5"
            style="border-color: var(--mp-line)"
        >
            <h2 class="mp-section-title">
                {{ session.opener_name }} — session en cours (demande rejetée)
            </h2>
            <p class="text-sm text-[color:var(--mp-muted)]">
                Le caissier ne peut plus redemander la fermeture. Clôturez la session vous-même.
            </p>
            <div>
                <label class="mp-metric-label">Espèces comptées (Fc)</label>
                <input v-model.number="confirmForm.closing_counted" type="number" min="0" step="1" class="mp-input mt-1" />
            </div>
            <textarea v-model="confirmForm.closing_notes" class="mp-input" rows="2" placeholder="Notes" />
            <button class="mp-btn mp-btn-primary" type="button" :disabled="confirmForm.processing" @click="confirm">
                Clôturer
            </button>
        </section>

        <section
            v-if="canApprove && session.status === 'closed'"
            class="mt-10 max-w-md space-y-3 border p-5"
            style="border-color: var(--mp-line)"
        >
            <h2 class="mp-section-title">Réouvrir la caisse aujourd’hui</h2>
            <p class="text-sm text-[color:var(--mp-muted)]">
                Sans cette action, le caissier reste sur « Fermé » jusqu’à 8h le lendemain.
            </p>
            <button class="mp-btn mp-btn-primary" type="button" :disabled="unlockForm.processing" @click="unlock">
                Autoriser une nouvelle session
            </button>
        </section>

        <section class="mt-10">
            <h2 class="mp-section-title">Ventes de la session</h2>
            <form class="mb-4 mt-3 flex flex-wrap gap-2" @submit.prevent="searchSales">
                <input v-model="q" class="mp-input max-w-xs" placeholder="N° ticket ou produit…" />
                <input v-model="from" type="date" class="mp-input max-w-[160px]" />
                <input v-model="to" type="date" class="mp-input max-w-[160px]" />
                <button class="mp-btn mp-btn-ghost" type="submit">Filtrer</button>
            </form>
            <div v-for="sale in sales.data" :key="sale.id" class="mp-row">
                <div>
                    <div class="font-medium">{{ sale.number }}</div>
                    <div class="text-xs text-[color:var(--mp-muted)]">
                        {{ sale.completed_at }} · {{ sale.cashier_name }}
                        <span v-for="p in sale.payments" :key="p.method" class="ml-2">{{ methodLabel(p.method) }}</span>
                    </div>
                </div>
                <div class="text-right">
                    <MoneyAmount :amount="sale.grand_total" size="sm" align="right" />
                    <Link :href="route('sales.show', sale.id)" class="text-xs text-[color:var(--mp-accent)]">Ticket</Link>
                </div>
            </div>
            <p v-if="!sales.data.length" class="py-6 text-sm text-[color:var(--mp-muted)]">Aucune vente</p>
        </section>
    </AuthenticatedLayout>
</template>

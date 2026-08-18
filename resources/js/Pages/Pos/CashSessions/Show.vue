<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    session: Record<string, any>;
    sales: Array<Record<string, any>>;
    returns: Array<Record<string, any>>;
    summary: Record<string, number>;
    canRequestClose?: boolean;
}>();

const form = useForm({
    closing_counted: Number(props.summary.expected_cash || props.session.opening_float || 0),
    closing_notes: '',
});

const close = () => form.post(route('pos.sessions.close', props.session.id));

const statusLabel = (status: string) =>
    ({ open: 'Ouverte', closure_requested: 'Fermeture demandée', closed: 'Fermée' }[status] ?? status);
</script>

<template>
    <Head :title="`Session ${session.number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Link :href="route('pos.sessions.index')" class="text-xs text-[color:var(--mp-accent)]">← Sessions</Link>
                    <h1 class="mp-display mt-2 text-4xl">{{ session.number }}</h1>
                    <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
                        {{ session.site?.name }} ·
                        <span
                            class="mp-badge"
                            :class="{
                                'mp-badge-ok': session.status === 'open',
                                'mp-badge-warn': session.status === 'closure_requested',
                            }"
                        >
                            {{ statusLabel(session.status) }}
                        </span>
                    </p>
                </div>
                <Link :href="route('pos.index')" class="mp-btn mp-btn-primary">Caisse</Link>
            </div>
        </template>

        <section class="grid gap-x-8 border-y py-2 md:grid-cols-2 xl:grid-cols-4" style="border-color: var(--mp-line)">
            <div class="mp-metric">
                <div class="mp-metric-label">Fond ouverture</div>
                <MoneyAmount class="mt-2" :amount="session.opening_float" size="lg" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Espèces encaissées</div>
                <MoneyAmount class="mt-2" :amount="summary.cash_sales" size="lg" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Remboursements cash</div>
                <MoneyAmount class="mt-2" :amount="summary.cash_refunds" size="lg" />
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

        <form
            v-if="canRequestClose && session.status === 'open'"
            class="mt-10 max-w-md space-y-3 border p-5"
            style="border-color: var(--mp-line)"
            @submit.prevent="close"
        >
            <h2 class="mp-section-title">Demander la fermeture</h2>
            <p class="text-sm text-[color:var(--mp-muted)]">
                La clôture sera confirmée par le propriétaire ou l’admin. La caisse reste utilisable en attendant.
            </p>
            <div>
                <label class="mp-metric-label">Espèces comptées (Fc)</label>
                <input v-model.number="form.closing_counted" type="number" min="0" step="1" class="mp-input mt-1" required />
            </div>
            <textarea v-model="form.closing_notes" class="mp-input" rows="2" placeholder="Notes de clôture / écart" />
            <button class="mp-btn mp-btn-primary w-full" :disabled="form.processing">Demander la fermeture</button>
        </form>

        <div
            v-else-if="session.status === 'closure_requested'"
            class="mt-10 border p-5"
            style="border-color: var(--mp-line)"
        >
            <h2 class="mp-section-title">Fermeture en attente</h2>
            <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
                Demande envoyée. Le propriétaire ou l’admin doit confirmer pour verrouiller la caisse.
            </p>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div>
                    <div class="mp-metric-label">Compté (demandé)</div>
                    <MoneyAmount class="mt-1" :amount="session.closing_counted" size="md" />
                </div>
                <div>
                    <div class="mp-metric-label">Attendu</div>
                    <MoneyAmount class="mt-1" :amount="session.expected_cash" size="md" />
                </div>
                <div>
                    <div class="mp-metric-label">Écart</div>
                    <MoneyAmount class="mt-1" :amount="session.variance" size="md" />
                </div>
            </div>
        </div>

        <div v-else-if="session.status === 'closed'" class="mt-10 border p-5" style="border-color: var(--mp-line)">
            <h2 class="mp-section-title">Résultat de clôture</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div>
                    <div class="mp-metric-label">Compté</div>
                    <MoneyAmount class="mt-1" :amount="session.closing_counted" size="md" />
                </div>
                <div>
                    <div class="mp-metric-label">Attendu</div>
                    <MoneyAmount class="mt-1" :amount="session.expected_cash" size="md" />
                </div>
                <div>
                    <div class="mp-metric-label">Écart</div>
                    <MoneyAmount class="mt-1" :amount="session.variance" size="md" />
                </div>
            </div>
            <p v-if="session.closing_notes" class="mt-3 text-sm text-[color:var(--mp-muted)]">{{ session.closing_notes }}</p>
        </div>

        <section class="mt-10">
            <h2 class="mp-section-title">Ventes de la session</h2>
            <div v-for="sale in sales" :key="sale.id" class="mp-row">
                <div>
                    <div class="font-medium">{{ sale.number }}</div>
                    <div class="text-xs text-[color:var(--mp-muted)]">{{ sale.completed_at }}</div>
                </div>
                <div class="text-right">
                    <MoneyAmount :amount="sale.grand_total" size="sm" align="right" />
                    <Link :href="route('sales.show', sale.id)" class="text-xs text-[color:var(--mp-accent)]">Ticket</Link>
                </div>
            </div>
            <p v-if="!sales.length" class="py-6 text-sm text-[color:var(--mp-muted)]">Aucune vente</p>
        </section>
    </AuthenticatedLayout>
</template>

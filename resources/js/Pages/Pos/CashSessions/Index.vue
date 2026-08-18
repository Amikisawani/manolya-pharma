<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    sessions: { data: Array<Record<string, any>> };
    openSession: Record<string, any> | null;
    sessionGate: {
        state: 'open' | 'continue' | 'closed';
        label: string;
        disabled: boolean;
        can_request_close: boolean;
        closure_pending: boolean;
        business_date: string;
    };
    warehouses: Array<{ id: string; name: string; site_id: string }>;
}>();

const form = useForm({
    warehouse_id: props.warehouses[0]?.id ?? '',
    opening_float: 0,
    opening_notes: '',
});

const open = () => form.post(route('pos.sessions.store'));

const statusLabel = (status: string) =>
    ({ open: 'Ouverte', closure_requested: 'Fermeture demandée', closed: 'Fermée' }[status] ?? status);
</script>

<template>
    <Head title="Sessions de caisse" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Caisse</p>
                    <h1 class="mp-display mt-1 text-4xl">Sessions</h1>
                </div>
                <Link :href="route('pos.index')" class="mp-btn mp-btn-primary">Aller à la caisse</Link>
            </div>
        </template>

        <div class="grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <div v-if="sessionGate.state === 'continue' && openSession" class="border p-5" style="border-color: var(--mp-line)">
                    <div class="mp-badge" :class="sessionGate.closure_pending ? 'mp-badge-warn' : 'mp-badge-ok'">
                        {{ sessionGate.closure_pending ? 'Fermeture demandée' : 'Session ouverte' }}
                    </div>
                    <div class="mt-3 font-semibold">{{ openSession.number }}</div>
                    <MoneyAmount class="mt-2" :amount="openSession.opening_float" size="md" />
                    <p class="mt-1 text-xs text-[color:var(--mp-muted)]">Fond de caisse à l’ouverture</p>
                    <Link :href="route('pos.index')" class="mp-btn mp-btn-primary mt-4 w-full">
                        Continuer la session
                    </Link>
                    <Link
                        v-if="sessionGate.can_request_close"
                        :href="route('pos.sessions.show', openSession.id)"
                        class="mp-btn mp-btn-ghost mt-2 w-full"
                    >
                        Demander la fermeture
                    </Link>
                </div>

                <div
                    v-else-if="sessionGate.state === 'closed'"
                    class="border p-5"
                    style="border-color: var(--mp-line)"
                >
                    <div class="mp-badge">Fermée</div>
                    <p class="mt-3 text-sm text-[color:var(--mp-muted)]">
                        Une seule session par jour. Réouverture à minuit, ou via le tableau de bord propriétaire / admin.
                    </p>
                    <button class="mp-btn mp-btn-primary mt-4 w-full" type="button" disabled>Fermé</button>
                </div>

                <form v-else class="space-y-3 border p-5" style="border-color: var(--mp-line)" @submit.prevent="open">
                    <h2 class="mp-section-title">Ouvrir une session</h2>
                    <select v-model="form.warehouse_id" class="mp-input">
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                    <div>
                        <label class="mp-metric-label">Fond de caisse (Fc)</label>
                        <input v-model.number="form.opening_float" type="number" min="0" step="1" class="mp-input mt-1" required />
                    </div>
                    <textarea v-model="form.opening_notes" class="mp-input" rows="2" placeholder="Notes d’ouverture" />
                    <button class="mp-btn mp-btn-primary w-full" :disabled="form.processing">Ouvrir la session</button>
                </form>
            </div>

            <div class="lg:col-span-8">
                <h2 class="mp-section-title">Historique</h2>
                <div v-for="s in sessions.data" :key="s.id" class="mp-row">
                    <div>
                        <div class="font-semibold">{{ s.number }}</div>
                        <div class="text-xs text-[color:var(--mp-muted)]">
                            {{ s.opener?.name }} · {{ statusLabel(s.status) }} · {{ s.opened_at }}
                        </div>
                    </div>
                    <div class="text-right">
                        <MoneyAmount v-if="s.variance !== null" :amount="s.variance" size="sm" align="right" />
                        <Link :href="route('pos.sessions.show', s.id)" class="text-sm text-[color:var(--mp-accent)]">Détail</Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

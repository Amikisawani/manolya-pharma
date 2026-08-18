<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    sessions: { data: Array<Record<string, any>>; links?: Array<Record<string, any>> };
    filters: { date?: string; q?: string; from?: string; to?: string };
    canApprove: boolean;
}>();

const date = ref(props.filters.date ?? '');
const q = ref(props.filters.q ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const filter = () =>
    router.get(
        route('reports.cash-sessions.index'),
        {
            date: date.value || undefined,
            q: q.value || undefined,
            from: date.value ? undefined : from.value || undefined,
            to: date.value ? undefined : to.value || undefined,
        },
        { preserveState: true },
    );
</script>

<template>
    <Head title="Rapport sessions de caisse" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Pilotage</p>
                <h1 class="mp-display mt-1 text-4xl">Rapport de caisse</h1>
                <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
                    Chaque session, par date. Une seule ouverture par jour et par caissier.
                </p>
            </div>
        </template>

        <form class="mb-6 flex flex-wrap items-end gap-2" @submit.prevent="filter">
            <div>
                <label class="mp-metric-label">Date de session</label>
                <input v-model="date" type="date" class="mp-input mt-1 max-w-[180px]" />
            </div>
            <div>
                <label class="mp-metric-label">Du</label>
                <input v-model="from" type="date" class="mp-input mt-1 max-w-[160px]" :disabled="!!date" />
            </div>
            <div>
                <label class="mp-metric-label">Au</label>
                <input v-model="to" type="date" class="mp-input mt-1 max-w-[160px]" :disabled="!!date" />
            </div>
            <div>
                <label class="mp-metric-label">N° / caissier</label>
                <input v-model="q" class="mp-input mt-1 max-w-xs" placeholder="CS-… ou nom" />
            </div>
            <button class="mp-btn mp-btn-primary" type="submit">Rechercher</button>
        </form>

        <table class="mp-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Session</th>
                    <th>Caissier</th>
                    <th>Ouverture</th>
                    <th>Fermeture</th>
                    <th>Statut</th>
                    <th>Ventes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="session in sessions.data" :key="session.id">
                    <td>{{ session.business_date }}</td>
                    <td class="font-medium">{{ session.number }}</td>
                    <td>{{ session.opener_name ?? '—' }}</td>
                    <td class="text-[color:var(--mp-muted)]">{{ session.opened_at ?? '—' }}</td>
                    <td class="text-[color:var(--mp-muted)]">{{ session.closed_at ?? '—' }}</td>
                    <td>
                        <span
                            class="mp-badge"
                            :class="{
                                'mp-badge-ok': session.status === 'open',
                                'mp-badge-warn': session.status === 'closure_requested',
                            }"
                        >
                            {{ session.status_label }}
                        </span>
                    </td>
                    <td>
                        <MoneyAmount :amount="session.sales_total" size="sm" />
                        <div class="text-xs text-[color:var(--mp-faint)]">{{ session.sales_count }} vente(s)</div>
                    </td>
                    <td class="text-right">
                        <Link
                            :href="route('reports.cash-sessions.show', session.id)"
                            class="text-sm text-[color:var(--mp-accent)]"
                        >
                            Voir
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-if="!sessions.data.length" class="py-10 text-sm text-[color:var(--mp-muted)]">
            Aucune session pour ces critères.
        </p>
    </AuthenticatedLayout>
</template>

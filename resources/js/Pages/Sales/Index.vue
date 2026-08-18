<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    sales: { data: Array<Record<string, any>> };
    filters: { q?: string; from?: string; to?: string };
}>();

const q = ref(props.filters.q ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const filter = () =>
    router.get(route('sales.index'), { q: q.value, from: from.value, to: to.value }, { preserveState: true });

const methodLabel = (method: string) =>
    ({ cash: 'Espèces', card: 'Carte', mobile_money: 'Mobile Money' }[method] ?? method);
</script>

<template>
    <Head title="Ventes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Commercial</p>
                    <h1 class="mp-display mt-1 text-4xl">Historique des ventes</h1>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        :href="route('sales.export', { q: q || undefined, from: from || undefined, to: to || undefined })"
                        class="mp-btn mp-btn-ghost"
                    >
                        Export Excel
                    </a>
                    <Link :href="route('pos.index')" class="mp-btn mp-btn-primary">Nouvelle vente</Link>
                </div>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap gap-2">
            <input v-model="q" class="mp-input max-w-xs" placeholder="N° ticket…" @keyup.enter="filter" />
            <input v-model="from" type="date" class="mp-input max-w-[160px]" />
            <input v-model="to" type="date" class="mp-input max-w-[160px]" />
            <button class="mp-btn mp-btn-ghost" type="button" @click="filter">Filtrer</button>
        </div>

        <table class="mp-table">
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Date</th>
                    <th>Caissier</th>
                    <th>Paiement</th>
                    <th>Total</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="sale in sales.data" :key="sale.id">
                    <td class="font-medium">{{ sale.number }}</td>
                    <td class="text-[color:var(--mp-muted)]">{{ sale.completed_at ?? sale.created_at }}</td>
                    <td>{{ sale.cashier?.name ?? '—' }}</td>
                    <td class="text-xs text-[color:var(--mp-muted)]">
                        <span v-for="p in sale.payments" :key="p.id" class="mr-2">{{ methodLabel(p.method) }}</span>
                    </td>
                    <td><MoneyAmount :amount="sale.grand_total" size="sm" /></td>
                    <td class="text-right">
                        <div class="flex flex-wrap justify-end gap-x-3 gap-y-1 text-sm">
                            <Link :href="route('sales.show', sale.id)" class="text-[color:var(--mp-accent)]">Voir</Link>
                            <Link :href="route('sales.reprint', sale.id)" class="text-[color:var(--mp-accent)]">Réimprimer</Link>
                            <Link :href="`${route('sales.show', sale.id)}#details`" class="text-[color:var(--mp-muted)]">Détails</Link>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-if="!sales.data.length" class="py-10 text-sm text-[color:var(--mp-muted)]">Aucune vente</p>
    </AuthenticatedLayout>
</template>

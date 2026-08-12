<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    movements: { data: Array<Record<string, any>> };
    filters: { q?: string; type?: string };
    types: string[];
}>();

const q = ref(props.filters.q ?? '');
const type = ref(props.filters.type ?? '');

const filter = () =>
    router.get(route('stock.movements.index'), { q: q.value, type: type.value }, { preserveState: true });

const isOut = (t: string) => t.startsWith('OUT_');
</script>

<template>
    <Head title="Mouvements de stock" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Link :href="route('stock.batches.index')" class="text-xs text-[color:var(--mp-accent)]">← Lots</Link>
                    <h1 class="mp-display mt-2 text-4xl">Mouvements</h1>
                    <p class="mt-2 text-sm text-[color:var(--mp-muted)]">Traçabilité complète des entrées / sorties</p>
                </div>
                <a :href="route('stock.movements.export')" class="mp-btn mp-btn-ghost">Export Excel</a>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap gap-2">
            <input v-model="q" class="mp-input max-w-xs" placeholder="Produit ou lot…" @keyup.enter="filter" />
            <select v-model="type" class="mp-input max-w-[220px]" @change="filter">
                <option value="">Tous types</option>
                <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
            </select>
            <button class="mp-btn mp-btn-ghost" type="button" @click="filter">Filtrer</button>
        </div>

        <table class="mp-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Produit</th>
                    <th>Lot</th>
                    <th>Qté</th>
                    <th>Coût</th>
                    <th>Par</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="m in movements.data" :key="m.id">
                    <td class="whitespace-nowrap text-xs text-[color:var(--mp-muted)]">{{ m.occurred_at }}</td>
                    <td>
                        <span class="mp-badge" :class="isOut(m.type) ? 'mp-badge-warn' : 'mp-badge-ok'">{{ m.type }}</span>
                    </td>
                    <td>
                        <div class="font-medium">{{ m.product?.commercial_name }}</div>
                        <div class="text-xs text-[color:var(--mp-faint)]">{{ m.product?.sku }}</div>
                    </td>
                    <td>{{ m.batch?.lot_number }}</td>
                    <td class="tabular-nums font-medium" :class="isOut(m.type) ? 'text-[color:var(--mp-danger)]' : 'text-[color:var(--mp-success)]'">
                        {{ isOut(m.type) ? '−' : '+' }}{{ m.quantity }}
                    </td>
                    <td><MoneyAmount :amount="m.unit_cost" size="sm" :show-fx="false" /></td>
                    <td class="text-sm text-[color:var(--mp-muted)]">{{ m.user?.name ?? 'Système' }}</td>
                </tr>
            </tbody>
        </table>
        <p v-if="!movements.data.length" class="py-10 text-sm text-[color:var(--mp-muted)]">Aucun mouvement</p>
    </AuthenticatedLayout>
</template>

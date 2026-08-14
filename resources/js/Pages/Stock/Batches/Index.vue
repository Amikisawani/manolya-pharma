<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { formatQty } from '@/Composables/useQuantity';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    batches: { data: Array<Record<string, any>> };
    filters: Record<string, any>;
}>();

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

const adjustForm = useForm({
    batch_id: '',
    quantity_delta: 0,
    reason: '',
});

const filter = () => {
    router.get(
        route('stock.batches.index'),
        {
            q: q.value,
            status: status.value,
            expiring: props.filters.expiring ? 1 : undefined,
            expired: props.filters.expired ? 1 : undefined,
        },
        { preserveState: true },
    );
};

const submitAdjust = () => adjustForm.post(route('stock.adjustments.store'), { onSuccess: () => adjustForm.reset() });
</script>

<template>
    <Head title="Stock" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Inventaire permanent</p>
                <h1 class="mp-display mt-1 text-4xl">Stock & lots</h1>
            </div>
        </template>

        <div class="mb-8 grid gap-8 lg:grid-cols-12">
            <div class="flex flex-wrap gap-2 lg:col-span-8">
                <input v-model="q" class="mp-input max-w-xs" placeholder="Lot ou produit…" @keyup.enter="filter" />
                <select v-model="status" class="mp-input max-w-[180px]" @change="filter">
                    <option value="">Tous statuts</option>
                    <option value="active">Actif</option>
                    <option value="expired">Expiré</option>
                    <option value="quarantine">Quarantaine</option>
                    <option value="depleted">Épuisé</option>
                </select>
                <button class="mp-btn mp-btn-ghost" type="button" @click="filter">Filtrer</button>
            </div>

            <form class="space-y-2 border p-4 lg:col-span-4" style="border-color: var(--mp-line)" @submit.prevent="submitAdjust">
                <div class="mp-section-title text-base">Ajustement</div>
                <select v-model="adjustForm.batch_id" class="mp-input" required>
                    <option value="">Choisir un lot</option>
                    <option v-for="b in batches.data" :key="b.id" :value="b.id">
                        {{ b.lot_number }} — {{ b.product?.commercial_name }}
                    </option>
                </select>
                <input v-model.number="adjustForm.quantity_delta" type="number" step="0.001" class="mp-input" placeholder="Delta (+/-)" required />
                <input v-model="adjustForm.reason" class="mp-input" placeholder="Motif obligatoire" required />
                <button class="mp-btn mp-btn-primary w-full" :disabled="adjustForm.processing">Appliquer</button>
            </form>
        </div>

        <table class="mp-table">
            <thead>
                <tr>
                    <th>Lot</th>
                    <th>Produit</th>
                    <th>Entrepôt</th>
                    <th>Qté</th>
                    <th>Coût</th>
                    <th>Expiration</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="batch in batches.data" :key="batch.id">
                    <td class="font-medium">{{ batch.lot_number }}</td>
                    <td>{{ batch.product?.commercial_name }}</td>
                    <td>{{ batch.warehouse?.name }}</td>
                    <td class="tabular-nums">{{ formatQty(batch.quantity_on_hand) }}</td>
                    <td><MoneyAmount :amount="batch.unit_cost" size="sm" /></td>
                    <td>{{ batch.expires_at }}</td>
                    <td>
                        <span
                            class="mp-badge"
                            :class="{
                                'mp-badge-ok': batch.status === 'active',
                                'mp-badge-danger': batch.status === 'expired',
                                'mp-badge-warn': batch.status === 'quarantine',
                            }"
                        >
                            {{ batch.status }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </AuthenticatedLayout>
</template>

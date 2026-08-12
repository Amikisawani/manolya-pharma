<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    order: Record<string, any>;
    warehouses: Array<{ id: string; name: string }>;
}>();

const receiveForm = useForm({
    warehouse_id: props.warehouses[0]?.id ?? '',
    lines: (props.order.lines || []).map((line: any) => ({
        purchase_order_line_id: line.id,
        quantity: Math.max(Number(line.quantity_ordered) - Number(line.quantity_received || 0), 0),
        lot_number: '',
        expires_at: '',
        unit_cost: Number(line.unit_cost || 0),
    })),
});

const receive = () => {
    receiveForm
        .transform((data) => ({
            ...data,
            lines: data.lines.filter(
                (l: { quantity: number; lot_number: string; expires_at: string }) =>
                    Number(l.quantity) > 0 && l.lot_number && l.expires_at,
            ),
        }))
        .post(route('purchasing.orders.receive', props.order.id));
};
</script>

<template>
    <Head :title="`BC ${order.number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Link :href="route('purchasing.orders.index')" class="text-xs text-[color:var(--mp-accent)]">← Achats</Link>
                    <h1 class="mp-display mt-2 text-4xl">{{ order.number }}</h1>
                    <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
                        {{ order.supplier?.name }} · <span class="mp-badge">{{ order.status }}</span>
                    </p>
                </div>
                <MoneyAmount :amount="order.total" size="xl" align="right" />
            </div>
        </template>

        <section>
            <h2 class="mp-section-title">Lignes commandées</h2>
            <table class="mp-table mt-3">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Commandé</th>
                        <th>Reçu</th>
                        <th>Coût</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="line in order.lines" :key="line.id">
                        <td>{{ line.product?.commercial_name }}</td>
                        <td>{{ line.quantity_ordered }}</td>
                        <td>{{ line.quantity_received }}</td>
                        <td><MoneyAmount :amount="line.unit_cost" size="sm" /></td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section
            v-if="['approved', 'partially_received'].includes(order.status)"
            class="mt-10 border p-5"
            style="border-color: var(--mp-line)"
        >
            <h2 class="mp-section-title">Réceptionner des lots</h2>
            <p class="mt-1 text-sm text-[color:var(--mp-muted)]">Saisissez n° de lot et date d’expiration pour chaque ligne reçue.</p>

            <div class="mt-4">
                <label class="mp-metric-label">Entrepôt</label>
                <select v-model="receiveForm.warehouse_id" class="mp-input mt-1 max-w-md" required>
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
            </div>

            <div v-for="(line, idx) in receiveForm.lines" :key="line.purchase_order_line_id" class="mt-4 grid gap-2 border-t pt-4 md:grid-cols-5" style="border-color: var(--mp-line)">
                <div class="md:col-span-2 text-sm font-medium">{{ order.lines[idx]?.product?.commercial_name }}</div>
                <input v-model.number="line.quantity" type="number" min="0" step="0.001" class="mp-input" placeholder="Qté" />
                <input v-model="line.lot_number" class="mp-input" placeholder="N° lot" />
                <input v-model="line.expires_at" type="date" class="mp-input" />
            </div>

            <button class="mp-btn mp-btn-primary mt-6" type="button" :disabled="receiveForm.processing" @click="receive">
                Enregistrer la réception
            </button>
        </section>

        <section v-if="order.goods_receipts?.length" class="mt-10">
            <h2 class="mp-section-title">Réceptions</h2>
            <div v-for="gr in order.goods_receipts" :key="gr.id" class="mp-row text-sm">
                <span>{{ gr.number }}</span>
                <span class="text-[color:var(--mp-muted)]">{{ gr.received_at }}</span>
            </div>
        </section>
    </AuthenticatedLayout>
</template>

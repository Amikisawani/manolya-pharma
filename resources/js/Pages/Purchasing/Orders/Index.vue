<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    orders: { data: Array<Record<string, any>> };
    filters: Record<string, any>;
    suppliers?: Array<{ id: string; name: string }>;
    products?: Array<Record<string, any>>;
}>();

const status = ref(props.filters.status ?? '');
const form = useForm({
    supplier_id: '',
    expected_at: '',
    lines: [{ product_id: '', quantity_ordered: 1, unit_cost: 0 }],
});

const filter = () => router.get(route('purchasing.orders.index'), { status: status.value }, { preserveState: true });
const addLine = () => form.lines.push({ product_id: '', quantity_ordered: 1, unit_cost: 0 });
const submit = () => form.post(route('purchasing.orders.store'), { onSuccess: () => form.reset() });
</script>

<template>
    <Head title="Achats" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Approvisionnement</p>
                    <h1 class="mp-display mt-1 text-4xl">Achats</h1>
                </div>
                <Link :href="route('purchasing.suppliers.index')" class="mp-btn mp-btn-ghost">Fournisseurs</Link>
            </div>
        </template>

        <div class="grid gap-10 xl:grid-cols-12">
            <form class="space-y-3 border p-5 xl:col-span-4" style="border-color: var(--mp-line)" @submit.prevent="submit">
                <h2 class="mp-section-title">Nouveau bon de commande</h2>
                <select v-model="form.supplier_id" class="mp-input" required>
                    <option value="">Fournisseur</option>
                    <option v-for="s in suppliers || []" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <input v-model="form.expected_at" type="date" class="mp-input" />
                <div v-for="(line, idx) in form.lines" :key="idx" class="space-y-2 border p-3" style="border-color: var(--mp-line)">
                    <select v-model="line.product_id" class="mp-input" required>
                        <option value="">Produit</option>
                        <option v-for="p in products || []" :key="p.id" :value="p.id">{{ p.commercial_name }}</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model.number="line.quantity_ordered" type="number" min="0.001" step="0.001" class="mp-input" placeholder="Qté" />
                        <input v-model.number="line.unit_cost" type="number" min="0" step="1" class="mp-input" placeholder="Coût Fc" />
                    </div>
                </div>
                <button type="button" class="mp-btn mp-btn-ghost w-full" @click="addLine">+ Ligne</button>
                <button class="mp-btn mp-btn-primary w-full" :disabled="form.processing">Créer le BC</button>
            </form>

            <div class="xl:col-span-8">
                <div class="mb-4">
                    <select v-model="status" class="mp-input max-w-xs" @change="filter">
                        <option value="">Tous statuts</option>
                        <option value="draft">Brouillon</option>
                        <option value="submitted">Soumis</option>
                        <option value="approved">Approuvé</option>
                        <option value="partially_received">Réception partielle</option>
                        <option value="received">Reçu</option>
                    </select>
                </div>

                <div v-for="o in orders.data" :key="o.id" class="mp-row">
                    <div>
                        <div class="font-semibold">{{ o.number }}</div>
                        <div class="text-xs text-[color:var(--mp-muted)]">{{ o.supplier?.name }} · {{ o.status }}</div>
                        <MoneyAmount class="mt-1" :amount="o.total" size="sm" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('purchasing.orders.show', o.id)" class="mp-btn mp-btn-ghost">Détail</Link>
                        <Link
                            v-if="o.status === 'draft'"
                            :href="route('purchasing.orders.submit', o.id)"
                            method="post"
                            as="button"
                            class="mp-btn mp-btn-ghost"
                        >
                            Soumettre
                        </Link>
                        <Link
                            v-if="['draft', 'submitted'].includes(o.status)"
                            :href="route('purchasing.orders.approve', o.id)"
                            method="post"
                            as="button"
                            class="mp-btn mp-btn-primary"
                        >
                            Approuver
                        </Link>
                    </div>
                </div>
                <p v-if="!orders.data.length" class="py-10 text-sm text-[color:var(--mp-muted)]">Aucune commande</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

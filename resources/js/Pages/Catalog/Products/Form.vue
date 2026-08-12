<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    product: Record<string, any> | null;
    categories: Array<{ id: string; name: string }>;
    suppliers: Array<{ id: string; name: string }>;
}>();

const form = useForm({
    category_id: props.product?.category_id ?? '',
    sku: props.product?.sku ?? '',
    commercial_name: props.product?.commercial_name ?? '',
    generic_name: props.product?.generic_name ?? '',
    barcode: props.product?.barcode ?? '',
    manufacturer: props.product?.manufacturer ?? '',
    preferred_supplier_id: props.product?.preferred_supplier_id ?? '',
    purchase_price: props.product?.purchase_price ?? 0,
    sale_price: props.product?.sale_price ?? 0,
    currency_code: props.product?.currency_code ?? 'CDF',
    min_stock: props.product?.min_stock ?? 0,
    critical_stock: props.product?.critical_stock ?? 0,
    allocation_strategy: props.product?.allocation_strategy ?? 'fefo',
    description: props.product?.description ?? '',
});

const submit = () => {
    if (props.product) {
        form.put(route('catalog.products.update', props.product.id));
    } else {
        form.post(route('catalog.products.store'));
    }
};
</script>

<template>
    <Head :title="product ? 'Modifier produit' : 'Nouveau produit'" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="mp-display text-3xl">{{ product ? 'Modifier le produit' : 'Nouveau produit' }}</h1>
                <Link :href="route('catalog.products.index')" class="mt-1 inline-block text-sm text-[color:var(--mp-accent-strong)]">← Retour catalogue</Link>
            </div>
        </template>

        <form class="grid max-w-3xl gap-4 border p-6 md:grid-cols-2" style="border-color: var(--mp-line)" @submit.prevent="submit">
            <div class="md:col-span-2">
                <label class="text-sm">Nom commercial</label>
                <input v-model="form.commercial_name" class="mp-input mt-1" required />
            </div>
            <div>
                <label class="text-sm">SKU</label>
                <input v-model="form.sku" class="mp-input mt-1" required />
            </div>
            <div>
                <label class="text-sm">DCI / générique</label>
                <input v-model="form.generic_name" class="mp-input mt-1" />
            </div>
            <div>
                <label class="text-sm">Catégorie</label>
                <select v-model="form.category_id" class="mp-input mt-1">
                    <option value="">—</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div>
                <label class="text-sm">Fournisseur préféré</label>
                <select v-model="form.preferred_supplier_id" class="mp-input mt-1">
                    <option value="">—</option>
                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div>
                <label class="text-sm">Prix d’achat</label>
                <input v-model="form.purchase_price" type="number" step="0.01" class="mp-input mt-1" required />
            </div>
            <div>
                <label class="text-sm">Prix de vente</label>
                <input v-model="form.sale_price" type="number" step="0.01" class="mp-input mt-1" required />
            </div>
            <div>
                <label class="text-sm">Stock min</label>
                <input v-model="form.min_stock" type="number" step="0.001" class="mp-input mt-1" />
            </div>
            <div>
                <label class="text-sm">Stock critique</label>
                <input v-model="form.critical_stock" type="number" step="0.001" class="mp-input mt-1" />
            </div>
            <div>
                <label class="text-sm">Stratégie d’allocation</label>
                <select v-model="form.allocation_strategy" class="mp-input mt-1">
                    <option value="FEFO">FEFO</option>
                    <option value="FIFO">FIFO</option>
                    <option value="LIFO">LIFO</option>
                </select>
            </div>
            <div>
                <label class="text-sm">Devise</label>
                <input v-model="form.currency_code" class="mp-input mt-1" maxlength="3" />
            </div>
            <div class="md:col-span-2">
                <label class="text-sm">Description</label>
                <textarea v-model="form.description" class="mp-input mt-1" rows="3" />
            </div>
            <div class="md:col-span-2">
                <button class="mp-btn mp-btn-primary" :disabled="form.processing">Enregistrer</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>

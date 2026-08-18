<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    product: Record<string, any> | null;
    categories: Array<{ id: string; name: string }>;
    suppliers: Array<{ id: string; name: string }>;
    warehouses?: Array<{ id: string; name: string; code: string; is_default: boolean }>;
}>();

const isCreate = computed(() => !props.product);

const defaultWarehouse = computed(
    () => props.warehouses?.find((w) => w.is_default)?.id ?? props.warehouses?.[0]?.id ?? '',
);

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
    allocation_strategy: (props.product?.allocation_strategy ?? 'fefo').toLowerCase(),
    description: props.product?.description ?? '',
    warehouse_id: defaultWarehouse.value,
    lot_number: '',
    initial_qty: '',
    expires_at: '',
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
                <Link :href="route('catalog.products.index')" class="mt-1 inline-block text-sm text-[color:var(--mp-accent)]">← Retour catalogue</Link>
            </div>
        </template>

        <form class="grid max-w-3xl gap-4 border p-6 md:grid-cols-2" style="border-color: var(--mp-line)" @submit.prevent="submit">
            <p class="md:col-span-2 text-sm text-[color:var(--mp-muted)]">
                La fiche catalogue ne se vend pas toute seule : un <strong>lot en stock</strong> est obligatoire pour la caisse.
            </p>

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
                <label class="text-sm">Code-barres</label>
                <input v-model="form.barcode" class="mp-input mt-1" />
            </div>
            <div>
                <label class="text-sm">Fabricant</label>
                <input v-model="form.manufacturer" class="mp-input mt-1" />
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
                <label class="text-sm">Prix d’achat (Fc)</label>
                <input v-model="form.purchase_price" type="number" step="0.01" min="0" class="mp-input mt-1" required />
            </div>
            <div>
                <label class="text-sm">Prix de vente (Fc)</label>
                <input v-model="form.sale_price" type="number" step="0.01" min="0" class="mp-input mt-1" required />
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
                    <option value="fefo">FEFO (péremption d’abord)</option>
                    <option value="fifo">FIFO (premier entré)</option>
                    <option value="lifo">LIFO</option>
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

            <fieldset v-if="isCreate" class="md:col-span-2 grid gap-4 border p-4 md:grid-cols-2" style="border-color: var(--mp-line)">
                <legend class="px-1 text-sm font-semibold">Stock initial — pour vendre tout de suite</legend>
                <p class="md:col-span-2 text-xs text-[color:var(--mp-muted)]">
                    Renseignez la quantité et le lot. Sans cela, le produit apparaît au catalogue mais la caisse refusera la vente (stock insuffisant).
                </p>
                <div>
                    <label class="text-sm">Quantité initiale</label>
                    <input v-model="form.initial_qty" type="number" min="0" step="1" class="mp-input mt-1" placeholder="ex. 50" />
                </div>
                <div>
                    <label class="text-sm">N° de lot</label>
                    <input v-model="form.lot_number" class="mp-input mt-1" placeholder="ex. LOT-PARA-01" />
                </div>
                <div>
                    <label class="text-sm">Date de péremption</label>
                    <input v-model="form.expires_at" type="date" class="mp-input mt-1" />
                </div>
                <div>
                    <label class="text-sm">Entrepôt</label>
                    <select v-model="form.warehouse_id" class="mp-input mt-1">
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }} ({{ w.code }})</option>
                    </select>
                </div>
            </fieldset>

            <div class="md:col-span-2">
                <button class="mp-btn mp-btn-primary" :disabled="form.processing">
                    {{ isCreate ? 'Enregistrer le produit' : 'Mettre à jour' }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { toQtyNumber } from '@/Composables/useQuantity';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

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
    purchase_price: toQtyNumber(props.product?.purchase_price),
    sale_price: toQtyNumber(props.product?.sale_price),
    currency_code: props.product?.currency_code ?? 'CDF',
    min_stock: toQtyNumber(props.product?.min_stock),
    critical_stock: toQtyNumber(props.product?.critical_stock),
    allocation_strategy: props.product?.allocation_strategy ?? 'fefo',
    description: props.product?.description ?? '',
});

const categoryQuery = ref(
    props.categories.find((c) => c.id === props.product?.category_id)?.name ?? '',
);
const categoryOpen = ref(false);
const categoryBox = ref<HTMLElement | null>(null);

const filteredCategories = computed(() => {
    const q = categoryQuery.value.trim().toLowerCase();
    if (!q) {
        return props.categories.slice(0, 12);
    }

    return props.categories
        .filter((c) => c.name.toLowerCase().includes(q))
        .slice(0, 20);
});

const selectedCategoryLabel = computed(
    () => props.categories.find((c) => c.id === form.category_id)?.name ?? '',
);

const pickCategory = (category: { id: string; name: string }) => {
    form.category_id = category.id;
    categoryQuery.value = category.name;
    categoryOpen.value = false;
};

const clearCategory = () => {
    form.category_id = '';
    categoryQuery.value = '';
    categoryOpen.value = true;
};

const onCategoryInput = () => {
    categoryOpen.value = true;
    const exact = props.categories.find(
        (c) => c.name.toLowerCase() === categoryQuery.value.trim().toLowerCase(),
    );
    form.category_id = exact?.id ?? '';
};

const onDocumentClick = (event: MouseEvent) => {
    if (!categoryBox.value?.contains(event.target as Node)) {
        categoryOpen.value = false;
        if (form.category_id) {
            categoryQuery.value = selectedCategoryLabel.value;
        }
    }
};

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));

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
            <div class="relative md:col-span-2" ref="categoryBox">
                <label class="text-sm">Catégorie</label>
                <div class="mt-1 flex gap-2">
                    <input
                        v-model="categoryQuery"
                        type="search"
                        class="mp-input flex-1"
                        placeholder="Rechercher une catégorie…"
                        autocomplete="off"
                        @focus="categoryOpen = true"
                        @input="onCategoryInput"
                    />
                    <button
                        v-if="form.category_id || categoryQuery"
                        type="button"
                        class="mp-btn mp-btn-ghost"
                        @click="clearCategory"
                    >
                        Effacer
                    </button>
                </div>
                <p v-if="selectedCategoryLabel" class="mt-1 text-xs text-[color:var(--mp-muted)]">
                    Sélection : {{ selectedCategoryLabel }}
                </p>
                <div
                    v-if="categoryOpen"
                    class="absolute z-20 mt-1 max-h-64 w-full overflow-auto border bg-[#fffcf7] shadow-sm"
                    style="border-color: var(--mp-line)"
                >
                    <button
                        v-for="c in filteredCategories"
                        :key="c.id"
                        type="button"
                        class="mp-row w-full text-left text-sm"
                        :class="form.category_id === c.id ? 'bg-[color:var(--mp-accent-soft)]' : ''"
                        @click="pickCategory(c)"
                    >
                        {{ c.name }}
                    </button>
                    <p v-if="!filteredCategories.length" class="px-3 py-4 text-sm text-[color:var(--mp-muted)]">
                        Aucune catégorie trouvée
                    </p>
                </div>
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
                <input v-model.number="form.purchase_price" type="number" step="0.01" class="mp-input mt-1" required />
            </div>
            <div>
                <label class="text-sm">Prix de vente</label>
                <input v-model.number="form.sale_price" type="number" step="0.01" class="mp-input mt-1" required />
            </div>
            <div>
                <label class="text-sm">Stock min</label>
                <input v-model.number="form.min_stock" type="number" step="1" min="0" class="mp-input mt-1" />
            </div>
            <div>
                <label class="text-sm">Stock critique</label>
                <input v-model.number="form.critical_stock" type="number" step="1" min="0" class="mp-input mt-1" />
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

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    products: {
        data: Array<Record<string, any>>;
        links: Array<Record<string, any>>;
    };
    filters: { q?: string };
}>();

const q = ref(props.filters.q ?? '');
const importForm = useForm<{ file: File | null }>({ file: null });
const fileInput = ref<HTMLInputElement | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const search = () => router.get(route('catalog.products.index'), { q: q.value || undefined }, { preserveState: true, replace: true });

watch(q, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => search(), 280);
});

onBeforeUnmount(() => {
    if (searchTimer) clearTimeout(searchTimer);
});

const onFile = (e: Event) => {
    const input = e.target as HTMLInputElement;
    importForm.file = input.files?.[0] ?? null;
    if (!importForm.file || importForm.processing) {
        return;
    }
    importForm.post(route('catalog.products.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset();
        },
        onFinish: () => {
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};
</script>

<template>
    <Head title="Catalogue" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Catalogue</p>
                    <h1 class="mp-display mt-1 text-4xl">Médicaments</h1>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a :href="route('catalog.products.template')" class="mp-btn mp-btn-ghost">Modèle Excel 50 médicaments</a>
                    <a :href="route('catalog.products.export', { format: 'xlsx' })" class="mp-btn mp-btn-ghost">Export Excel</a>
                    <a :href="route('catalog.products.export', { format: 'csv' })" class="mp-btn mp-btn-ghost">Export CSV</a>
                    <button
                        class="mp-btn mp-btn-ghost"
                        type="button"
                        :disabled="importForm.processing"
                        @click="fileInput?.click()"
                    >
                        {{ importForm.processing ? 'Import en cours…' : 'Import Excel/CSV' }}
                    </button>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".xlsx,.csv,text/csv"
                        class="hidden"
                        :disabled="importForm.processing"
                        @change="onFile"
                    />
                    <Link :href="route('catalog.products.create')" class="mp-btn mp-btn-primary">Nouveau produit</Link>
                </div>
            </div>
        </template>

        <p class="mb-4 text-xs text-[color:var(--mp-faint)]">
            Un produit catalogue n’est vendable qu’avec un lot en stock. Le modèle Excel inclut
            <span class="font-medium">initial_qty</span>, <span class="font-medium">lot_number</span> et
            <span class="font-medium">expires_at</span> pour créer les lots à l’import.
            Colonnes : sku, commercial_name, generic_name, barcode, manufacturer, purchase_price, sale_price,
            currency_code, min_stock, critical_stock, allocation_strategy, category, description, supplier,
            initial_qty, lot_number, expires_at, warehouse
            · Formats .xlsx / .csv (séparateur ;)
        </p>
        <p
            v-if="importForm.errors.file"
            class="mb-4 text-sm"
            style="color: var(--mp-danger)"
        >
            {{ importForm.errors.file }}
        </p>

        <div class="mb-6">
            <input v-model="q" class="mp-input max-w-md" placeholder="Recherche dynamique…" />
            <p class="mt-1 text-xs text-[color:var(--mp-faint)]">Filtre automatique pendant la saisie</p>
        </div>

        <div class="overflow-x-auto">
            <table class="mp-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Nom</th>
                        <th>Catégorie</th>
                        <th>Prix vente</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="product in products.data" :key="product.id">
                        <td class="font-medium">{{ product.sku }}</td>
                        <td>{{ product.commercial_name }}</td>
                        <td class="text-[color:var(--mp-muted)]">{{ product.category?.name ?? '—' }}</td>
                        <td><MoneyAmount :amount="product.sale_price" size="sm" /></td>
                        <td class="text-right">
                            <Link :href="route('catalog.products.edit', product.id)" class="text-sm text-[color:var(--mp-accent)]">
                                Modifier
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>

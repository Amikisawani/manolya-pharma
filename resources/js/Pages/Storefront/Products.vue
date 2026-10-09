<script setup lang="ts">
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

type ProductCard = {
    id: string;
    name: string;
    generic_name?: string | null;
    category?: string | null;
    price: string;
};

type Paginator = {
    data: ProductCard[];
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    pharmacy: { name: string };
    categories: { id: string; name: string }[];
    filters: { q: string; category: string };
    products: Paginator | null;
}>();

const q = ref(props.filters.q);
const category = ref(props.filters.category);

watch(
    () => props.filters,
    (value) => {
        q.value = value.q;
        category.value = value.category;
    },
);

const apply = () => {
    router.get(
        route('storefront.products'),
        { q: q.value || undefined, category: category.value || undefined },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <StorefrontLayout title="Produits">
        <section class="mx-auto max-w-6xl px-5 py-14 lg:px-8">
            <h1 class="mp-display text-4xl">Produits</h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-[color:var(--mp-muted)]">
                Catalogue de {{ pharmacy.name }}. Les prix sont indicatifs, en Fc. Pour un conseil ou une disponibilité,
                passez à l’officine ou écrivez-nous — la commande en ligne n’est pas encore ouverte.
            </p>

            <form class="mt-8 flex flex-col gap-3 sm:flex-row" @submit.prevent="apply">
                <input
                    v-model="q"
                    type="search"
                    placeholder="Nom, DCI, SKU"
                    class="w-full border px-3 py-2.5 text-sm sm:max-w-sm"
                    style="border-color: var(--mp-line); background: transparent"
                />
                <select
                    v-model="category"
                    class="border px-3 py-2.5 text-sm"
                    style="border-color: var(--mp-line); background: transparent"
                    @change="apply"
                >
                    <option value="">Toutes les catégories</option>
                    <option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
                <button class="mp-btn mp-btn-primary" type="submit">Rechercher</button>
            </form>

            <div v-if="products?.data?.length" class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="product in products.data"
                    :key="product.id"
                    class="border px-4 py-4"
                    style="border-color: var(--mp-line)"
                >
                    <p class="text-xs uppercase tracking-wide text-[color:var(--mp-faint)]">{{ product.category }}</p>
                    <h2 class="mt-2 font-semibold">{{ product.name }}</h2>
                    <p v-if="product.generic_name" class="mt-1 text-sm text-[color:var(--mp-muted)]">{{ product.generic_name }}</p>
                    <p class="mt-4 text-sm font-semibold">{{ product.price }}</p>
                </article>
            </div>
            <p v-else class="mt-10 text-sm text-[color:var(--mp-muted)]">
                <template v-if="filters.q || filters.category">
                    Aucun produit ne correspond à cette recherche. Essayez un autre nom, une DCI, ou toutes les catégories.
                </template>
                <template v-else>
                    Aucun produit public pour le moment. Le catalogue se synchronise avec le stock de l’officine.
                </template>
            </p>

            <nav v-if="products && products.links.length > 3" class="mt-8 flex flex-wrap gap-2 text-sm">
                <template v-for="(link, index) in products.links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="border px-2 py-1"
                        :class="link.active ? 'bg-[color:var(--mp-accent)] text-white' : ''"
                        style="border-color: var(--mp-line)"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="border px-2 py-1 text-[color:var(--mp-faint)]"
                        style="border-color: var(--mp-line)"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </section>
    </StorefrontLayout>
</template>

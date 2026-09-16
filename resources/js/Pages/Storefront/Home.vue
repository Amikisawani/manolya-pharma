<script setup lang="ts">
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { Link } from '@inertiajs/vue3';

type ProductCard = {
    id: string;
    name: string;
    generic_name?: string | null;
    category?: string | null;
    price: string;
};

defineProps<{
    pharmacy: {
        name: string;
        tagline: string;
        city: string;
        country: string;
    };
    featured: ProductCard[];
}>();
</script>

<template>
    <StorefrontLayout title="Officine">
        <section class="mx-auto max-w-6xl px-5 py-14 lg:px-8 lg:py-20">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[color:var(--mp-faint)]">
                {{ pharmacy.city }} · {{ pharmacy.country }}
            </p>
            <h1 class="mp-display mt-4 max-w-3xl text-4xl leading-tight lg:text-6xl">
                {{ pharmacy.name }}
            </h1>
            <p class="mt-5 max-w-xl text-lg leading-relaxed text-[color:var(--mp-muted)]">
                {{ pharmacy.tagline }}. Médicaments suivis par lots, conseil à l’officine, montants en francs congolais.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <Link :href="route('storefront.products')" class="mp-btn mp-btn-primary">Voir les produits</Link>
                <Link :href="route('storefront.contact')" class="mp-btn" style="border: 1px solid var(--mp-line)">Nous écrire</Link>
            </div>
            <p class="mt-8 max-w-lg border px-4 py-3 text-sm text-[color:var(--mp-muted)]" style="border-color: var(--mp-line)">
                Les commandes à distance arrivent bientôt. En attendant, le catalogue vous présente ce que l’officine
                dispense, et l’équipe vous reçoit sur place.
            </p>
        </section>

        <section class="border-y py-12" style="border-color: var(--mp-line); background: var(--mp-bg-accent)">
            <div class="mx-auto grid max-w-6xl gap-8 px-5 lg:grid-cols-3 lg:px-8">
                <div>
                    <h2 class="mp-section-title">Lots et péremption</h2>
                    <p class="mt-2 text-sm leading-relaxed text-[color:var(--mp-muted)]">
                        Chaque médicament est rattaché à un lot. La vente suit le FEFO : les dates les plus proches partent en premier.
                    </p>
                </div>
                <div>
                    <h2 class="mp-section-title">Prix en Fc</h2>
                    <p class="mt-2 text-sm leading-relaxed text-[color:var(--mp-muted)]">
                        Les tarifs du catalogue public sont affichés en francs congolais, avec équivalents USD / EUR dans l’officine.
                    </p>
                </div>
                <div>
                    <h2 class="mp-section-title">Traçabilité</h2>
                    <p class="mt-2 text-sm leading-relaxed text-[color:var(--mp-muted)]">
                        L’application interne Manolya suit caisse, stock et audit. Ce site montre l’officine ; l’équipe travaille derrière.
                    </p>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-5 py-14 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <h2 class="mp-display text-3xl">Quelques spécialités</h2>
                <Link :href="route('storefront.products')" class="text-sm text-[color:var(--mp-accent)]">Tout le catalogue</Link>
            </div>
            <div v-if="featured.length" class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="product in featured"
                    :key="product.id"
                    class="border px-4 py-4"
                    style="border-color: var(--mp-line)"
                >
                    <p class="text-xs uppercase tracking-wide text-[color:var(--mp-faint)]">{{ product.category }}</p>
                    <h3 class="mt-2 font-semibold">{{ product.name }}</h3>
                    <p v-if="product.generic_name" class="mt-1 text-sm text-[color:var(--mp-muted)]">{{ product.generic_name }}</p>
                    <p class="mt-4 text-sm font-semibold">{{ product.price }}</p>
                </article>
            </div>
            <p v-else class="mt-6 text-sm text-[color:var(--mp-muted)]">
                Le catalogue public se remplit à partir du stock de l’officine. Revenez bientôt, ou
                <Link :href="route('storefront.contact')" class="text-[color:var(--mp-accent)]">écrivez-nous</Link>.
            </p>
        </section>
    </StorefrontLayout>
</template>

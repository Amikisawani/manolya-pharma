<script setup lang="ts">
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineProps<{
    pharmacy: {
        name: string;
        tagline: string;
        city: string;
        country: string;
    };
}>();
</script>

<template>
    <StorefrontLayout title="L’officine">
        <section class="mx-auto max-w-3xl px-5 py-14 lg:px-8 lg:py-20">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[color:var(--mp-faint)]">À propos</p>
            <h1 class="mp-display mt-4 text-4xl lg:text-5xl">{{ pharmacy.name }}</h1>
            <p class="mt-6 text-lg leading-relaxed text-[color:var(--mp-muted)]">
                {{ pharmacy.tagline }}. Nous tenons une officine à {{ pharmacy.city }}, {{ pharmacy.country }},
                pour dispenser des médicaments avec un suivi de lots et un conseil clair.
            </p>
            <div class="mt-10 space-y-6 text-sm leading-relaxed text-[color:var(--mp-muted)]">
                <p>
                    Manolya a été conçue pour le marché congolais : affichage en francs congolais, traçabilité des
                    mouvements, et un espace équipe séparé pour la caisse, le stock et les clôtures.
                </p>
                <p>
                    Ce site présente l’entreprise et ses produits. Il n’est pas la caisse. Les ventes se font encore
                    à l’officine. Les commandes à distance seront ajoutées ensuite, sur le même catalogue.
                </p>
                <p>
                    Le personnel utilise l’application interne (connexion équipe) pour encaisser, recevoir les
                    commandes fournisseurs et clôturer la caisse.
                </p>
            </div>
        </section>
    </StorefrontLayout>
</template>

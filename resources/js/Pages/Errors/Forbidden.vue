<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    message?: string;
}>();

const page = usePage();
const isAuthenticated = computed(() => Boolean((page.props.auth as { user?: unknown })?.user));
</script>

<template>
    <Head title="Accès refusé" />

    <AuthenticatedLayout v-if="isAuthenticated">
        <div class="mx-auto max-w-lg py-16 text-center">
            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">403</p>
            <h1 class="mp-display mt-2 text-4xl">Accès refusé</h1>
            <p class="mt-4 text-sm leading-relaxed text-[color:var(--mp-muted)]">
                {{ message || 'Votre rôle ne permet pas d’ouvrir cette page. Demandez au propriétaire si vous avez besoin d’un accès.' }}
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <Link :href="route('dashboard')" class="mp-btn mp-btn-primary">Retour au tableau de bord</Link>
                <Link :href="route('profile.edit')" class="mp-btn mp-btn-ghost">Mon compte</Link>
            </div>
        </div>
    </AuthenticatedLayout>

    <GuestLayout v-else>
        <h1 class="mp-display text-3xl">Accès refusé</h1>
        <p class="mt-3 text-sm text-[color:var(--mp-muted)]">
            {{ message || 'Vous n’êtes pas autorisé à voir cette page.' }}
        </p>
        <Link :href="route('login')" class="mp-btn mp-btn-primary mt-8 inline-flex">Se connecter</Link>
    </GuestLayout>
</template>

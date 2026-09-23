<script setup lang="ts">
import BrandLockup from '@/Components/BrandLockup.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps<{
    title: string;
}>();

const page = usePage();
const open = ref(false);
const teamHref = computed(() => {
    const user = page.props.auth?.user;
    const roles = (page.props.auth as { roles?: string[] } | undefined)?.roles ?? [];

    if (user && roles.includes('super_admin')) {
        return route('admin.dashboard');
    }

    return user ? route('dashboard') : route('login');
});

const links = [
    { href: 'storefront.home', label: 'Accueil', match: '/' },
    { href: 'storefront.about', label: 'L’officine', match: '/a-propos' },
    { href: 'storefront.products', label: 'Produits', match: '/produits' },
    { href: 'storefront.contact', label: 'Contact', match: '/contact' },
];

const currentPath = computed(() => page.url.split('?')[0]);

const isActive = (match: string) =>
    match === '/' ? currentPath.value === '/' : currentPath.value.startsWith(match);
</script>

<template>
    <div class="min-h-screen" style="background: var(--mp-bg); color: var(--mp-ink)">
        <Head :title="title" />

        <header class="border-b" style="border-color: var(--mp-line)">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4 lg:px-8">
                <Link :href="route('storefront.home')" class="shrink-0">
                    <BrandLockup />
                </Link>
                <nav class="hidden items-center gap-6 text-sm md:flex">
                    <Link
                        v-for="link in links"
                        :key="link.href"
                        :href="route(link.href)"
                        class="text-sm"
                        :class="isActive(link.match) ? 'text-[color:var(--mp-ink)]' : 'text-[color:var(--mp-muted)] hover:text-[color:var(--mp-ink)]'"
                    >
                        {{ link.label }}
                    </Link>
                    <Link :href="teamHref" class="mp-btn mp-btn-primary">Espace équipe</Link>
                </nav>
                <button type="button" class="mp-btn md:hidden" style="border: 1px solid var(--mp-line)" @click="open = !open">
                    Menu
                </button>
            </div>
            <div v-if="open" class="space-y-2 border-t px-5 py-4 md:hidden" style="border-color: var(--mp-line)">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="route(link.href)"
                    class="block py-1 text-sm"
                    @click="open = false"
                >
                    {{ link.label }}
                </Link>
                <Link :href="teamHref" class="mp-btn mp-btn-primary mt-2">Espace équipe</Link>
            </div>
        </header>

        <main>
            <slot />
        </main>

        <footer class="mt-16 border-t px-5 py-10 text-sm" style="border-color: var(--mp-line); color: var(--mp-muted)">
            <div class="mx-auto flex max-w-6xl flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <BrandLockup size="sm" />
                    <p class="mt-3 max-w-md leading-relaxed">
                        Pharmacie Manolya — officine claire et traçable. Les ventes se font à l’officine ;
                        les commandes à distance arrivent bientôt.
                    </p>
                </div>
                <p class="text-xs text-[color:var(--mp-faint)]">Devise affichée en Fc · Kinshasa, République démocratique du Congo</p>
            </div>
        </footer>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingMobile = ref(false);
const page = usePage();

const nav = [
    { label: 'Tableau de bord', route: 'dashboard', match: 'dashboard' },
    { label: 'Caisse', route: 'pos.index', match: 'pos.index|pos.store|pos.search' },
    { label: 'Sessions', route: 'pos.sessions.index', match: 'pos.sessions.*' },
    { label: 'Ventes', route: 'sales.index', match: 'sales.*' },
    { label: 'Catalogue', route: 'catalog.products.index', match: 'catalog.*' },
    { label: 'Stock', route: 'stock.batches.index', match: 'stock.batches.*' },
    { label: 'Mouvements', route: 'stock.movements.index', match: 'stock.movements.*' },
    { label: 'Achats', route: 'purchasing.orders.index', match: 'purchasing.*' },
    { label: 'Inventaires', route: 'inventory.counts.index', match: 'inventory.*' },
    { label: 'Finance', route: 'finance.index', match: 'finance.*' },
    { label: 'Documents', route: 'documents.index', match: 'documents.*' },
    { label: 'Audit', route: 'audit.index', match: 'audit.*' },
    { label: 'Alertes', route: 'alerts.index', match: 'alerts.*' },
    { label: 'Sites', route: 'settings.sites.index', match: 'settings.sites.*' },
    { label: 'Compte', route: 'profile.edit', match: 'profile.*' },
];

const userName = computed(() => (page.props.auth as { user?: { name?: string } })?.user?.name ?? '');
const pharmacy = computed(
    () => (page.props.auth as { user?: { tenant?: { name?: string } } })?.user?.tenant?.name ?? 'Manolya Pharma',
);
const flashSuccess = computed(() => (page.props as { flash?: { success?: string } }).flash?.success);
const flashError = computed(() => (page.props as { flash?: { error?: string } }).flash?.error);

const isActive = (pattern: string) => {
    try {
        return pattern.split('|').some((p) => route().current(p.trim()));
    } catch {
        return false;
    }
};
</script>

<template>
    <div class="min-h-screen">
        <div class="flex min-h-screen">
            <aside
                class="hidden w-[15.5rem] shrink-0 flex-col lg:flex"
                style="background: var(--mp-sidebar)"
            >
                <div class="px-5 pb-6 pt-8">
                    <div class="mp-display text-[1.65rem] leading-none text-[color:var(--mp-sidebar-active)]">Manolya</div>
                    <div class="mt-2 text-[0.65rem] font-medium uppercase tracking-[0.28em]" style="color: var(--mp-sidebar-muted)">
                        Pharma · Congo
                    </div>
                    <div class="mt-5 border-t pt-4 text-xs" style="border-color: #24302a; color: var(--mp-sidebar-muted)">
                        {{ pharmacy }}
                    </div>
                </div>

                <nav class="flex-1 space-y-0.5 px-2 pb-6">
                    <Link
                        v-for="item in nav"
                        :key="item.route"
                        :href="route(item.route)"
                        class="flex items-center px-3 py-2 text-[0.8125rem] transition"
                        :class="isActive(item.match)
                            ? 'bg-[#1c2923] text-[color:var(--mp-sidebar-active)]'
                            : 'text-[color:var(--mp-sidebar-ink)] hover:bg-[#161e1a]'"
                    >
                        <span
                            class="mr-2.5 inline-block h-1 w-1 rounded-full"
                            :style="{ background: isActive(item.match) ? '#3d9b6e' : 'transparent' }"
                        />
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="border-t px-5 py-4 text-sm" style="border-color: #24302a; color: var(--mp-sidebar-muted)">
                    <div class="text-[color:var(--mp-sidebar-active)]">{{ userName }}</div>
                    <Link :href="route('logout')" method="post" as="button" class="mt-2 text-xs hover:text-white">
                        Déconnexion
                    </Link>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header
                    class="flex items-center justify-between border-b px-4 py-3 lg:px-10"
                    style="border-color: var(--mp-line); background: rgba(247, 244, 239, 0.86); backdrop-filter: blur(8px)"
                >
                    <div class="flex items-center gap-3">
                        <button
                            class="border px-3 py-2 text-sm lg:hidden"
                            style="border-color: var(--mp-line-strong)"
                            @click="showingMobile = !showingMobile"
                        >
                            Menu
                        </button>
                        <div class="hidden text-xs uppercase tracking-[0.16em] text-[color:var(--mp-faint)] sm:block">
                            Franc congolais (Fc)
                        </div>
                    </div>
                    <Link :href="route('pos.index')" class="mp-btn mp-btn-primary">Ouvrir la caisse</Link>
                </header>

                <div v-if="showingMobile" class="border-b px-3 py-3 lg:hidden" style="border-color: var(--mp-line); background: #fffcf7">
                    <Link
                        v-for="item in nav"
                        :key="item.route"
                        :href="route(item.route)"
                        class="block px-3 py-2 text-sm"
                        :class="isActive(item.match) ? 'text-[color:var(--mp-accent)]' : 'text-[color:var(--mp-muted)]'"
                        @click="showingMobile = false"
                    >
                        {{ item.label }}
                    </Link>
                </div>

                <header v-if="$slots.header" class="px-4 pb-1 pt-8 lg:px-10">
                    <slot name="header" />
                </header>

                <main class="flex-1 px-4 py-5 lg:px-10 lg:py-7">
                    <div
                        v-if="flashSuccess"
                        class="mb-5 border px-4 py-3 text-sm"
                        style="border-color: #a8d5c0; background: var(--mp-accent-soft); color: var(--mp-accent)"
                    >
                        {{ flashSuccess }}
                    </div>
                    <div
                        v-if="flashError"
                        class="mb-5 border px-4 py-3 text-sm"
                        style="border-color: #f0c4c0; background: #fbebe9; color: var(--mp-danger)"
                    >
                        {{ flashError }}
                    </div>
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>

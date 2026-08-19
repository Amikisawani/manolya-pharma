<script setup lang="ts">
import BrandLockup from '@/Components/BrandLockup.vue';
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

type NavItem = {
    label: string;
    route: string;
    match: string;
    /** Une permission suffit (OR). Vide = toujours visible. */
    permissions?: string[];
};

const showingMobile = ref(false);
const page = usePage();

const auth = computed(() => page.props.auth as {
    user?: { name?: string; tenant?: { name?: string } };
    permissions?: string[];
    roles?: string[];
});

const permissions = computed(() => auth.value.permissions ?? []);
const roles = computed(() => auth.value.roles ?? []);

const canAny = (required?: string[]) => {
    if (!required || required.length === 0) {
        return true;
    }
    if (roles.value.includes('owner') || roles.value.includes('super_admin')) {
        return true;
    }
    return required.some((p) => permissions.value.includes(p));
};

const navAll: NavItem[] = [
    { label: 'Tableau de bord', route: 'dashboard', match: 'dashboard', permissions: ['dashboard.view'] },
    { label: 'Caisse', route: 'pos.index', match: 'pos.index|pos.store|pos.search', permissions: ['sales.pos'] },
    { label: 'Sessions', route: 'pos.sessions.index', match: 'pos.sessions.*', permissions: ['sales.pos', 'sales.view'] },
    { label: 'Rapport caisse', route: 'reports.cash-sessions.index', match: 'reports.cash-sessions.*', permissions: ['cash_sessions.report'] },
    { label: 'Ventes', route: 'sales.index', match: 'sales.*', permissions: ['sales.view'] },
    { label: 'Catalogue', route: 'catalog.products.index', match: 'catalog.*', permissions: ['products.view'] },
    { label: 'Stock', route: 'stock.batches.index', match: 'stock.batches.*', permissions: ['batches.view'] },
    { label: 'Mouvements', route: 'stock.movements.index', match: 'stock.movements.*', permissions: ['batches.view'] },
    { label: 'Achats', route: 'purchasing.orders.index', match: 'purchasing.*', permissions: ['purchase_orders.view', 'suppliers.view'] },
    { label: 'Inventaires', route: 'inventory.counts.index', match: 'inventory.*', permissions: ['stock_counts.view'] },
    { label: 'Finance', route: 'finance.index', match: 'finance.*', permissions: ['finance.reports.view', 'expenses.view'] },
    { label: 'Documents', route: 'documents.index', match: 'documents.*', permissions: ['documents.view'] },
    { label: 'Audit', route: 'audit.index', match: 'audit.*', permissions: ['audit.view'] },
    { label: 'Alertes', route: 'alerts.index', match: 'alerts.*', permissions: ['alerts.view'] },
    { label: 'Sites', route: 'settings.sites.index', match: 'settings.sites.*', permissions: ['sites.manage'] },
    { label: 'Compte', route: 'profile.edit', match: 'profile.*' },
];

const nav = computed(() => navAll.filter((item) => canAny(item.permissions)));
const canOpenPos = computed(() => canAny(['sales.pos']));

const userName = computed(() => auth.value.user?.name ?? '');
const pharmacy = computed(() => auth.value.user?.tenant?.name ?? 'Manolya Pharma');
const flashSuccess = computed(() => (page.props as { flash?: { success?: string } }).flash?.success);
const flashError = computed(() => (page.props as { flash?: { error?: string } }).flash?.error);
const cashSessionDuty = computed(() => (page.props as {
    cashSessionDuty?: { must_close?: boolean; count?: number; message?: string | null };
}).cashSessionDuty ?? { must_close: false, count: 0, message: null });

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
        <aside
            class="fixed inset-y-0 left-0 z-40 hidden h-dvh w-[15.5rem] flex-col print:hidden lg:flex"
            style="background: var(--mp-sidebar)"
        >
            <div class="shrink-0 px-5 pb-5 pt-8">
                <BrandLockup variant="dark" class="text-[color:var(--mp-sidebar-active)]" />
                <div class="mt-5 border-t pt-4 text-xs" style="border-color: #24302a; color: var(--mp-sidebar-muted)">
                    {{ pharmacy }}
                </div>
            </div>

            <nav class="mp-scroll-sidebar min-h-0 flex-1 space-y-0.5 overflow-y-auto overflow-x-hidden px-2 py-1">
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

            <div class="shrink-0 border-t px-5 py-4 text-sm" style="border-color: #24302a; color: var(--mp-sidebar-muted)">
                <div class="text-[color:var(--mp-sidebar-active)]">{{ userName }}</div>
                <p v-if="cashSessionDuty.must_close" class="mt-2 text-xs" style="color: #f0c4c0">
                    {{ cashSessionDuty.message }}
                </p>
                <Link
                    v-if="!cashSessionDuty.must_close"
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="mt-2 text-xs hover:text-white"
                >
                    Déconnexion
                </Link>
                <button
                    v-else
                    type="button"
                    class="mt-2 cursor-not-allowed text-xs opacity-50"
                    disabled
                >
                    Déconnexion
                </button>
            </div>
        </aside>

        <div class="flex min-h-dvh min-w-0 flex-col lg:pl-[15.5rem] print:pl-0">
            <header
                class="sticky top-0 z-30 flex items-center justify-between border-b px-4 py-3 print:hidden lg:px-10"
                style="border-color: var(--mp-line); background: rgba(247, 244, 239, 0.92); backdrop-filter: blur(10px)"
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
                        Franc (Fc)
                    </div>
                </div>
                <Link
                    v-if="canOpenPos"
                    :href="route('pos.index')"
                    class="mp-btn mp-btn-primary"
                >
                    Ouvrir la caisse
                </Link>
            </header>

            <div
                v-if="showingMobile"
                class="mp-scroll-main max-h-[60vh] overflow-y-auto border-b px-3 py-3 print:hidden lg:hidden"
                style="border-color: var(--mp-line); background: #fffcf7"
            >
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

            <header v-if="$slots.header" class="px-4 pb-1 pt-8 print:hidden lg:px-10">
                <slot name="header" />
            </header>

            <main class="flex-1 px-4 py-5 print:p-0 lg:px-10 lg:py-7">
                <div
                    v-if="flashSuccess"
                    class="mb-5 border px-4 py-3 text-sm print:hidden"
                    style="border-color: #a8d5c0; background: var(--mp-accent-soft); color: var(--mp-accent)"
                >
                    {{ flashSuccess }}
                </div>
                <div
                    v-if="flashError"
                    class="mb-5 border px-4 py-3 text-sm print:hidden"
                    style="border-color: #f0c4c0; background: #fbebe9; color: var(--mp-danger)"
                >
                    {{ flashError }}
                </div>
                <div
                    v-if="cashSessionDuty.must_close"
                    class="mb-5 border px-4 py-3 text-sm print:hidden"
                    style="border-color: #f0c4c0; background: #fbebe9; color: var(--mp-danger)"
                >
                    {{ cashSessionDuty.message }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>

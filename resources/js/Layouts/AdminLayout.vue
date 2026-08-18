<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const flashSuccess = computed(() => (page.props as { flash?: { success?: string } }).flash?.success);
const flashError = computed(() => (page.props as { flash?: { error?: string } }).flash?.error);
</script>

<template>
    <div class="min-h-screen" style="background: #101412; color: #e8f5ef">
        <aside
            class="fixed inset-y-0 left-0 hidden w-56 flex-col border-r lg:flex"
            style="border-color: #24302a; background: #0e1311"
        >
            <div class="px-5 pb-4 pt-8">
                <div class="mp-display text-2xl text-[color:var(--mp-sidebar-active)]">Manolya</div>
                <div class="mt-2 text-[0.65rem] font-semibold uppercase tracking-[0.24em]" style="color: #7a857e">
                    Super admin
                </div>
            </div>
            <nav class="flex-1 space-y-1 px-3 py-2 text-sm">
                <Link
                    :href="route('admin.dashboard')"
                    class="block px-3 py-2"
                    :class="route().current('admin.dashboard') ? 'bg-[#1c2923] text-[#e8f5ef]' : 'text-[#9aaba2] hover:bg-[#161e1a]'"
                >
                    Tableau de bord
                </Link>
                <Link
                    :href="route('admin.cash-sessions.index')"
                    class="block px-3 py-2"
                    :class="route().current('admin.cash-sessions.*') ? 'bg-[#1c2923] text-[#e8f5ef]' : 'text-[#9aaba2] hover:bg-[#161e1a]'"
                >
                    Rapport caisse
                </Link>
                <Link
                    :href="route('admin.users.index')"
                    class="block px-3 py-2"
                    :class="route().current('admin.users.*') ? 'bg-[#1c2923] text-[#e8f5ef]' : 'text-[#9aaba2] hover:bg-[#161e1a]'"
                >
                    Comptes pharmacie
                </Link>
                <Link
                    :href="route('admin.reset.edit')"
                    class="block px-3 py-2"
                    :class="route().current('admin.reset.*') ? 'bg-[#1c2923] text-[#e8f5ef]' : 'text-[#9aaba2] hover:bg-[#161e1a]'"
                >
                    Remise à zéro
                </Link>
            </nav>
            <div class="border-t px-5 py-4 text-xs" style="border-color: #24302a; color: #7a857e">
                <Link :href="route('admin.logout')" method="post" as="button" class="hover:text-white">
                    Déconnexion
                </Link>
            </div>
        </aside>

        <div class="lg:pl-56">
            <header
                class="flex items-center justify-between border-b px-4 py-3 lg:px-8"
                style="border-color: #24302a; background: rgba(14, 19, 17, 0.92)"
            >
                <div class="text-xs uppercase tracking-[0.18em]" style="color: #7a857e">Plateforme</div>
                <Link :href="route('login')" class="text-xs" style="color: #9aaba2">App pharmacie →</Link>
            </header>

            <main class="px-4 py-8 lg:px-8">
                <div
                    v-if="flashSuccess"
                    class="mb-6 border px-4 py-3 text-sm"
                    style="border-color: #1f6b4a; color: #a8d5c0"
                >
                    {{ flashSuccess }}
                </div>
                <div
                    v-if="flashError"
                    class="mb-6 border px-4 py-3 text-sm"
                    style="border-color: #b42318; color: #f0c4c0"
                >
                    {{ flashError }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    stats: {
        tenants: number;
        users: number;
        products: number;
        sales: number;
        pending_closures?: number;
        rejected_closures?: number;
    };
    tenant: { id: string; name: string } | null;
    admin: { name: string; email: string };
    pendingClosures?: Array<Record<string, any>>;
    rejectedClosures?: Array<Record<string, any>>;
}>();

const actingOn = ref<number | string | null>(null);

const postAction = (url: string, id: number | string) => {
    actingOn.value = id;
    router.post(url, {}, { onFinish: () => { actingOn.value = null; } });
};
</script>

<template>
    <Head title="Admin — Tableau de bord" />
    <AdminLayout>
        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em]" style="color: #7a857e">Plateforme</p>
        <h1 class="mp-display mt-1 text-4xl text-[#e8f5ef]">Tableau de bord</h1>
        <p class="mt-2 text-sm" style="color: #9aaba2">
            {{ admin.name }} · {{ admin.email }}
            <span v-if="tenant"> · Officine : {{ tenant.name }}</span>
        </p>

        <div class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="border p-5" style="border-color: #24302a">
                <div class="text-xs uppercase tracking-[0.16em]" style="color: #7a857e">Tenants</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.tenants }}</div>
            </div>
            <div class="border p-5" style="border-color: #24302a">
                <div class="text-xs uppercase tracking-[0.16em]" style="color: #7a857e">Users pharmacie</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.users }}</div>
            </div>
            <div class="border p-5" style="border-color: #24302a">
                <div class="text-xs uppercase tracking-[0.16em]" style="color: #7a857e">Produits</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.products }}</div>
            </div>
            <div class="border p-5" style="border-color: #24302a">
                <div class="text-xs uppercase tracking-[0.16em]" style="color: #7a857e">Ventes</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.sales }}</div>
            </div>
            <div class="border p-5" style="border-color: #24302a">
                <div class="text-xs uppercase tracking-[0.16em]" style="color: #7a857e">Fermetures à confirmer</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.pending_closures ?? pendingClosures?.length ?? 0 }}</div>
            </div>
            <div class="border p-5" style="border-color: #24302a">
                <div class="text-xs uppercase tracking-[0.16em]" style="color: #7a857e">Demandes rejetées</div>
                <div class="mt-2 text-3xl font-semibold">{{ stats.rejected_closures ?? rejectedClosures?.length ?? 0 }}</div>
            </div>
        </div>

        <section v-if="pendingClosures?.length" class="mt-10">
            <h2 class="text-sm font-semibold uppercase tracking-[0.14em]" style="color: #7a857e">Demandes de clôture</h2>
            <div
                v-for="session in pendingClosures"
                :key="session.id"
                class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t py-3"
                style="border-color: #24302a"
            >
                <div>
                    <div class="font-semibold">
                        {{ session.opener_name }} demande confirmation de la fermeture
                    </div>
                    <div class="text-xs" style="color: #9aaba2">
                        {{ session.number }} · {{ session.site_name }} · {{ session.business_date }}
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('admin.cash-sessions.show', session.id)" class="text-sm" style="color: #a8d5c0">
                        Voir
                    </Link>
                    <button
                        type="button"
                        class="px-3 py-1.5 text-sm font-semibold"
                        style="background: #1f6b4a; color: #f7f4ef"
                        :disabled="actingOn === session.id"
                        @click="postAction(route('admin.cash-sessions.confirm', session.id), session.id)"
                    >
                        Valider
                    </button>
                    <button
                        type="button"
                        class="border px-3 py-1.5 text-sm"
                        style="border-color: #b42318; color: #f0c4c0"
                        :disabled="actingOn === session.id"
                        @click="postAction(route('admin.cash-sessions.reject', session.id), session.id)"
                    >
                        Rejeter
                    </button>
                </div>
            </div>
        </section>

        <section v-if="rejectedClosures?.length" class="mt-10">
            <h2 class="text-sm font-semibold uppercase tracking-[0.14em]" style="color: #f0c4c0">
                Sessions à clôturer (demande rejetée)
            </h2>
            <p class="mt-2 text-sm" style="color: #f0c4c0">
                Vous ne pouvez pas vous déconnecter tant que ces caisses ne sont pas fermées.
            </p>
            <div
                v-for="session in rejectedClosures"
                :key="session.id"
                class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t py-3"
                style="border-color: #24302a"
            >
                <div>
                    <div class="font-semibold">
                        {{ session.opener_name }} — session en cours (demande rejetée)
                    </div>
                    <div class="text-xs" style="color: #9aaba2">
                        {{ session.number }} · {{ session.site_name }} · {{ session.business_date }}
                    </div>
                </div>
                <button
                    type="button"
                    class="px-3 py-1.5 text-sm font-semibold"
                    style="background: #1f6b4a; color: #f7f4ef"
                    :disabled="actingOn === session.id"
                    @click="postAction(route('admin.cash-sessions.confirm', session.id), session.id)"
                >
                    Clôturer
                </button>
            </div>
        </section>

        <div class="mt-10 flex flex-wrap gap-3">
            <Link
                :href="route('admin.cash-sessions.index')"
                class="px-4 py-2.5 text-sm font-semibold"
                style="background: #1f6b4a; color: #f7f4ef"
            >
                Rapport caisse
            </Link>
            <Link
                :href="route('admin.users.index')"
                class="px-4 py-2.5 text-sm font-semibold"
                style="background: #1f6b4a; color: #f7f4ef"
            >
                Gérer les comptes
            </Link>
            <Link
                :href="route('admin.reset.edit')"
                class="border px-4 py-2.5 text-sm"
                style="border-color: #b42318; color: #f0c4c0"
            >
                Remettre à zéro
            </Link>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    stats: {
        tenants: number;
        users: number;
        products: number;
        sales: number;
    };
    tenant: { id: string; name: string } | null;
    admin: { name: string; email: string };
}>();
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
        </div>

        <div class="mt-10 flex flex-wrap gap-3">
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

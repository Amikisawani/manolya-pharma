<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    session: Record<string, any>;
    sales: { data: Array<Record<string, any>> };
    returns: Array<Record<string, any>>;
    summary: Record<string, string | number>;
    filters: { q?: string; from?: string; to?: string };
}>();

const q = ref(props.filters.q ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const confirmForm = useForm({
    closing_counted: Number(props.session.closing_counted || props.summary.expected_cash || 0),
    closing_notes: props.session.closing_notes ?? '',
});

const unlockForm = useForm({
    user_id: props.session.opened_by,
    business_date: '',
});

const fmt = (v: string | number | null | undefined) =>
    new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Number(v || 0));

const searchSales = () =>
    router.get(
        route('admin.cash-sessions.show', props.session.id),
        { q: q.value || undefined, from: from.value || undefined, to: to.value || undefined },
        { preserveState: true },
    );

const confirm = () => confirmForm.post(route('admin.cash-sessions.confirm', props.session.id));
const reject = () => confirmForm.post(route('admin.cash-sessions.reject', props.session.id));
const unlock = () => unlockForm.post(route('admin.cash-sessions.unlock'));
</script>

<template>
    <Head :title="`Admin — ${session.number}`" />
    <AdminLayout>
        <Link :href="route('admin.cash-sessions.index')" class="text-xs" style="color: #a8d5c0">← Rapport caisse</Link>
        <h1 class="mp-display mt-2 text-4xl text-[#e8f5ef]">{{ session.number }}</h1>
        <p class="mt-2 text-sm" style="color: #9aaba2">
            {{ session.site_name }} · {{ session.business_date }} · {{ session.status_label }}
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="border p-5" style="border-color: #24302a">
                <div class="text-xs uppercase tracking-[0.16em]" style="color: #7a857e">Ouverture</div>
                <div class="mt-2 text-xl">{{ session.opened_at ?? '—' }}</div>
                <div class="mt-1 text-xs" style="color: #9aaba2">{{ session.opener_name }}</div>
            </div>
            <div class="border p-5" style="border-color: #24302a">
                <div class="text-xs uppercase tracking-[0.16em]" style="color: #7a857e">Fermeture</div>
                <div class="mt-2 text-xl">{{ session.closed_at ?? '—' }}</div>
                <div class="mt-1 text-xs" style="color: #9aaba2">{{ session.closer_name ?? 'En cours' }}</div>
            </div>
            <div class="border p-5" style="border-color: #24302a">
                <div class="text-xs uppercase tracking-[0.16em]" style="color: #7a857e">Total ventes</div>
                <div class="mt-2 text-xl">{{ fmt(summary.grand_total) }} Fc</div>
            </div>
            <div class="border p-5" style="border-color: #24302a">
                <div class="text-xs uppercase tracking-[0.16em]" style="color: #7a857e">Espèces attendues</div>
                <div class="mt-2 text-xl">{{ fmt(summary.expected_cash) }} Fc</div>
            </div>
        </div>

        <section
            v-if="session.status === 'closure_requested'"
            class="mt-10 max-w-md space-y-3 border p-5"
            style="border-color: #24302a"
        >
            <h2 class="text-sm font-semibold">
                {{ session.opener_name }} demande confirmation de la fermeture
            </h2>
            <input v-model.number="confirmForm.closing_counted" type="number" min="0" class="w-full border bg-transparent px-3 py-2 text-sm" style="border-color: #24302a; color: #e8f5ef" />
            <textarea v-model="confirmForm.closing_notes" rows="2" class="w-full border bg-transparent px-3 py-2 text-sm" style="border-color: #24302a; color: #e8f5ef" />
            <div class="flex gap-2">
                <button class="px-4 py-2 text-sm font-semibold" style="background: #1f6b4a; color: #f7f4ef" type="button" @click="confirm">
                    Valider
                </button>
                <button class="border px-4 py-2 text-sm" style="border-color: #b42318; color: #f0c4c0" type="button" @click="reject">
                    Rejeter
                </button>
            </div>
        </section>

        <section
            v-else-if="session.status === 'open' && session.close_request_rejected"
            class="mt-10 max-w-md space-y-3 border p-5"
            style="border-color: #24302a"
        >
            <h2 class="text-sm font-semibold">
                {{ session.opener_name }} — session en cours (demande rejetée)
            </h2>
            <p class="text-sm" style="color: #f0c4c0">
                Le caissier ne peut plus redemander la fermeture. Clôturez la session vous-même avant de vous déconnecter.
            </p>
            <input v-model.number="confirmForm.closing_counted" type="number" min="0" class="w-full border bg-transparent px-3 py-2 text-sm" style="border-color: #24302a; color: #e8f5ef" />
            <textarea v-model="confirmForm.closing_notes" rows="2" class="w-full border bg-transparent px-3 py-2 text-sm" style="border-color: #24302a; color: #e8f5ef" />
            <button class="px-4 py-2 text-sm font-semibold" style="background: #1f6b4a; color: #f7f4ef" type="button" @click="confirm">
                Clôturer
            </button>
        </section>

        <section v-if="session.status === 'closed'" class="mt-10 max-w-md border p-5" style="border-color: #24302a">
            <h2 class="text-sm font-semibold">Réactiver la caisse</h2>
            <button class="mt-3 px-4 py-2 text-sm font-semibold" style="background: #1f6b4a; color: #f7f4ef" type="button" @click="unlock">
                Autoriser une nouvelle session
            </button>
        </section>

        <section class="mt-10">
            <h2 class="text-sm font-semibold uppercase tracking-[0.14em]" style="color: #7a857e">Ventes</h2>
            <form class="mt-3 flex flex-wrap gap-2" @submit.prevent="searchSales">
                <input v-model="q" class="border bg-transparent px-3 py-2 text-sm" style="border-color: #24302a; color: #e8f5ef" placeholder="N° ticket…" />
                <input v-model="from" type="date" class="border bg-transparent px-3 py-2 text-sm" style="border-color: #24302a; color: #e8f5ef" />
                <input v-model="to" type="date" class="border bg-transparent px-3 py-2 text-sm" style="border-color: #24302a; color: #e8f5ef" />
                <button class="border px-3 py-2 text-sm" style="border-color: #24302a" type="submit">Filtrer</button>
            </form>
            <div v-for="sale in sales.data" :key="sale.id" class="mt-3 flex justify-between border-t py-3" style="border-color: #24302a">
                <div>
                    <div>{{ sale.number }}</div>
                    <div class="text-xs" style="color: #9aaba2">{{ sale.completed_at }} · {{ sale.cashier_name }}</div>
                </div>
                <div>{{ fmt(sale.grand_total) }} Fc</div>
            </div>
            <p v-if="!sales.data.length" class="py-6 text-sm" style="color: #9aaba2">Aucune vente</p>
        </section>
    </AdminLayout>
</template>

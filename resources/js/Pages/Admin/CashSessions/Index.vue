<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    sessions: { data: Array<Record<string, any>> };
    filters: { date?: string; q?: string; from?: string; to?: string };
    tenant: { id: string; name: string };
    cashiers: Array<{ id: string; name: string }>;
}>();

const date = ref(props.filters.date ?? '');
const q = ref(props.filters.q ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const unlockUser = ref(props.cashiers[0]?.id ?? '');

const unlockForm = useForm({
    user_id: props.cashiers[0]?.id ?? '',
    business_date: '',
});

const filter = () =>
    router.get(
        route('admin.cash-sessions.index'),
        {
            date: date.value || undefined,
            q: q.value || undefined,
            from: date.value ? undefined : from.value || undefined,
            to: date.value ? undefined : to.value || undefined,
        },
        { preserveState: true },
    );

const unlock = () => {
    unlockForm.user_id = unlockUser.value;
    unlockForm.post(route('admin.cash-sessions.unlock'));
};

const fmt = (v: string | number | null | undefined) =>
    new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Number(v || 0));
</script>

<template>
    <Head title="Admin — Rapport caisse" />
    <AdminLayout>
        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em]" style="color: #7a857e">Pilotage</p>
        <h1 class="mp-display mt-1 text-4xl text-[#e8f5ef]">Rapport de caisse</h1>
        <p class="mt-2 text-sm" style="color: #9aaba2">{{ tenant.name }} · une session par caissier et par jour</p>

        <form class="mt-8 flex flex-wrap items-end gap-2" @submit.prevent="filter">
            <input v-model="date" type="date" class="border bg-transparent px-3 py-2 text-sm" style="border-color: #24302a; color: #e8f5ef" />
            <input v-model="from" type="date" class="border bg-transparent px-3 py-2 text-sm" style="border-color: #24302a; color: #e8f5ef" :disabled="!!date" />
            <input v-model="to" type="date" class="border bg-transparent px-3 py-2 text-sm" style="border-color: #24302a; color: #e8f5ef" :disabled="!!date" />
            <input v-model="q" class="border bg-transparent px-3 py-2 text-sm" style="border-color: #24302a; color: #e8f5ef" placeholder="N° / caissier" />
            <button
                class="px-4 py-2 text-sm font-semibold"
                style="background: #1f6b4a; color: #f7f4ef"
                type="submit"
            >
                Rechercher
            </button>
        </form>

        <div class="mt-8 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-[0.68rem] uppercase tracking-[0.12em]" style="color: #7a857e">
                        <th class="py-3">Date</th>
                        <th>Session</th>
                        <th>Caissier</th>
                        <th>Ouverture</th>
                        <th>Fermeture</th>
                        <th>Statut</th>
                        <th>CA</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="session in sessions.data" :key="session.id" class="border-t" style="border-color: #24302a">
                        <td class="py-3">{{ session.business_date }}</td>
                        <td>{{ session.number }}</td>
                        <td>{{ session.opener_name ?? '—' }}</td>
                        <td style="color: #9aaba2">{{ session.opened_at ?? '—' }}</td>
                        <td style="color: #9aaba2">{{ session.closed_at ?? '—' }}</td>
                        <td>{{ session.status_label }}</td>
                        <td>{{ fmt(session.sales_total) }} Fc</td>
                        <td class="text-right">
                            <Link
                                :href="route('admin.cash-sessions.show', session.id)"
                                class="text-sm"
                                style="color: #a8d5c0"
                            >
                                Voir
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!sessions.data.length" class="py-10 text-sm" style="color: #9aaba2">Aucune session.</p>
        </div>

        <section class="mt-10 max-w-md border p-5" style="border-color: #24302a">
            <h2 class="text-sm font-semibold">Réactiver une caisse aujourd’hui</h2>
            <p class="mt-2 text-xs" style="color: #9aaba2">
                Après clôture, le bouton « Fermé » se déverrouille à minuit. Vous pouvez l’autoriser plus tôt.
            </p>
            <select v-model="unlockUser" class="mt-3 w-full border bg-transparent px-3 py-2 text-sm" style="border-color: #24302a; color: #e8f5ef">
                <option v-for="cashier in cashiers" :key="cashier.id" :value="cashier.id">{{ cashier.name }}</option>
            </select>
            <button
                class="mt-3 px-4 py-2 text-sm font-semibold"
                style="background: #1f6b4a; color: #f7f4ef"
                type="button"
                :disabled="unlockForm.processing || !unlockUser"
                @click="unlock"
            >
                Autoriser une nouvelle session
            </button>
        </section>
    </AdminLayout>
</template>

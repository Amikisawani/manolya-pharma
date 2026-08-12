<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    overview?: Record<string, string | number>;
    charts?: {
        labels: string[];
        ca: number[];
        profit: number[];
        expenses_by_category: Array<{ category: string; total: string | number }>;
    };
    expenses?: { data: Array<Record<string, any>> };
    reports?: Array<Record<string, any>>;
}>();

const form = useForm({
    category: '',
    amount: 0,
    currency_code: 'CDF',
    spent_at: new Date().toISOString().slice(0, 10),
    description: '',
});

const dailyForm = useForm({ send: true });
const monthlyForm = useForm({ send: true });

const maxCa = computed(() => Math.max(...(props.charts?.ca ?? [1]), 1));
const submit = () =>
    form.post(route('finance.expenses.store'), {
        onSuccess: () => form.reset('category', 'amount', 'description'),
    });

const generateDaily = () => dailyForm.post(route('finance.reports.daily'));
const generateMonthly = () => monthlyForm.post(route('finance.reports.monthly'));

const typeLabel = (type: string) => (type === 'monthly' ? 'Mensuel' : 'Quotidien');
</script>

<template>
    <Head title="Finance" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Trésorerie</p>
                <h1 class="mp-display mt-1 text-4xl">Finance</h1>
            </div>
        </template>

        <section class="grid gap-x-10 border-y py-2 md:grid-cols-2 xl:grid-cols-4" style="border-color: var(--mp-line)">
            <div class="mp-metric">
                <div class="mp-metric-label">CA mois</div>
                <MoneyAmount class="mt-2" :amount="overview?.ca_month" size="lg" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Marge mois</div>
                <MoneyAmount class="mt-2" :amount="overview?.profit_month" size="lg" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Dépenses</div>
                <MoneyAmount class="mt-2" :amount="overview?.expenses_month" size="lg" />
            </div>
            <div class="mp-metric">
                <div class="mp-metric-label">Net</div>
                <MoneyAmount class="mt-2" :amount="overview?.net_month" size="lg" />
            </div>
        </section>

        <div class="mt-10 grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-8">
                <h2 class="mp-section-title">CA 30 jours</h2>
                <div class="mt-4 flex h-40 items-end gap-1">
                    <div
                        v-for="(v, i) in charts?.ca || []"
                        :key="i"
                        class="flex-1"
                        :style="{
                            height: `${(v / maxCa) * 100}%`,
                            background: '#1f6b4a',
                            minHeight: '2px',
                            opacity: 0.85,
                        }"
                        :title="String(v)"
                    />
                </div>
            </div>

            <form class="space-y-3 border p-5 lg:col-span-4" style="border-color: var(--mp-line)" @submit.prevent="submit">
                <h2 class="mp-section-title">Nouvelle dépense</h2>
                <input v-model="form.category" class="mp-input" placeholder="Catégorie" required />
                <input v-model.number="form.amount" type="number" min="1" step="1" class="mp-input" placeholder="Montant Fc" required />
                <input v-model="form.spent_at" type="date" class="mp-input" required />
                <textarea v-model="form.description" class="mp-input" rows="2" placeholder="Description" />
                <button class="mp-btn mp-btn-primary w-full" :disabled="form.processing">Enregistrer</button>
            </form>
        </div>

        <section class="mt-10 grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-8">
                <h2 class="mp-section-title">Rapports PDF</h2>
                <div v-for="r in reports || []" :key="r.id" class="mp-row">
                    <div>
                        <div class="font-medium">{{ typeLabel(r.type) }} · {{ r.period_start }}</div>
                        <div class="text-xs text-[color:var(--mp-muted)]">
                            {{ r.status }}
                            <span v-if="r.sent_at"> · envoyé {{ r.sent_at }}</span>
                        </div>
                    </div>
                    <a
                        v-if="r.disk_path"
                        :href="route('finance.reports.download', r.id)"
                        class="text-sm text-[color:var(--mp-accent)]"
                    >
                        Télécharger PDF
                    </a>
                </div>
                <p v-if="!(reports || []).length" class="py-6 text-sm text-[color:var(--mp-muted)]">
                    Aucun rapport généré pour l’instant.
                </p>
            </div>
            <div class="space-y-3 border p-5 lg:col-span-4" style="border-color: var(--mp-line)">
                <h2 class="mp-section-title">Générer</h2>
                <p class="text-xs text-[color:var(--mp-muted)]">
                    Envoie aussi l’e-mail au propriétaire (avec PDF joint).
                </p>
                <button class="mp-btn mp-btn-primary w-full" type="button" :disabled="dailyForm.processing" @click="generateDaily">
                    Rapport du jour
                </button>
                <button class="mp-btn mp-btn-ghost w-full" type="button" :disabled="monthlyForm.processing" @click="generateMonthly">
                    Rapport mensuel (mois préc.)
                </button>
            </div>
        </section>

        <section class="mt-10">
            <h2 class="mp-section-title">Dépenses récentes</h2>
            <div v-for="e in expenses?.data || []" :key="e.id" class="mp-row">
                <div>
                    <div class="text-sm font-medium">{{ e.category }}</div>
                    <div class="text-xs text-[color:var(--mp-muted)]">{{ e.description || '—' }}</div>
                </div>
                <MoneyAmount :amount="e.amount" size="sm" align="right" />
            </div>
        </section>
    </AuthenticatedLayout>
</template>

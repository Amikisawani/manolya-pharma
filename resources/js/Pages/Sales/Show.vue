<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps<{
    sale: Record<string, any>;
    canRefund?: boolean;
    hasOpenSession?: boolean;
}>();

const methodLabel = (method: string) =>
    ({ cash: 'Espèces', card: 'Carte', mobile_money: 'Mobile Money' }[method] ?? method);

const printTicket = () => window.print();

const qtyByLine = reactive<Record<string, number>>(
    Object.fromEntries(
        (props.sale.lines ?? []).map((line: Record<string, any>) => {
            const max = Math.max(0, Number(line.quantity) - Number(line.quantity_returned ?? 0));
            return [line.id, max > 0 ? max : 0];
        }),
    ),
);

const returnForm = useForm({
    restock: true,
    reason: '',
    refund_method: 'cash',
    lines: [] as Array<{ sale_line_id: string; quantity: number }>,
});

const returnableLines = computed(() =>
    (props.sale.lines ?? []).filter(
        (line: Record<string, any>) => Number(line.quantity) - Number(line.quantity_returned ?? 0) > 0,
    ),
);

const submitReturn = () => {
    returnForm.lines = returnableLines.value
        .map((line: Record<string, any>) => ({
            sale_line_id: line.id,
            quantity: Number(qtyByLine[line.id] || 0),
        }))
        .filter((l: { sale_line_id: string; quantity: number }) => l.quantity > 0);

    returnForm.post(route('sales.returns.store', props.sale.id));
};
</script>

<template>
    <Head :title="`Ticket ${sale.number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3 print:hidden">
                <div>
                    <Link :href="route('sales.index')" class="text-xs text-[color:var(--mp-accent)]">← Ventes</Link>
                    <h1 class="mp-display mt-2 text-4xl">{{ sale.number }}</h1>
                    <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
                        {{ sale.completed_at }} · {{ sale.cashier?.name }}
                        <span v-if="sale.cash_register_session?.number">
                            · Session {{ sale.cash_register_session.number }}
                        </span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <button class="mp-btn mp-btn-ghost" type="button" @click="printTicket">Imprimer</button>
                    <Link :href="route('pos.index')" class="mp-btn mp-btn-primary">Retour caisse</Link>
                </div>
            </div>
        </template>

        <article
            class="ticket-print mx-auto max-w-xl border p-6"
            style="border-color: var(--mp-line); background: #fffcf7"
        >
            <div class="text-center">
                <div class="mp-display text-3xl">Manolya Pharma</div>
                <div class="mt-1 text-xs uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Facture / ticket</div>
                <div class="mt-4 font-mono text-sm">{{ sale.number }}</div>
                <div class="text-xs text-[color:var(--mp-muted)]">{{ sale.completed_at }}</div>
            </div>

            <div class="mt-5 space-y-1 border-t pt-4 text-xs text-[color:var(--mp-muted)]" style="border-color: var(--mp-line)">
                <div class="flex justify-between gap-3">
                    <span>Caissier(ère)</span>
                    <span class="text-right font-medium text-[color:var(--mp-ink)]">{{ sale.cashier?.name ?? '—' }}</span>
                </div>
                <div v-if="sale.cashier?.email" class="flex justify-between gap-3">
                    <span>Identifiant</span>
                    <span class="text-right">{{ sale.cashier.email }}</span>
                </div>
                <div v-if="sale.cashier?.phone" class="flex justify-between gap-3">
                    <span>Téléphone</span>
                    <span class="text-right">{{ sale.cashier.phone }}</span>
                </div>
                <div v-if="sale.site?.name" class="flex justify-between gap-3">
                    <span>Site</span>
                    <span class="text-right">{{ sale.site.name }}</span>
                </div>
                <div v-if="sale.warehouse?.name" class="flex justify-between gap-3">
                    <span>Entrepôt</span>
                    <span class="text-right">{{ sale.warehouse.name }}</span>
                </div>
                <div v-if="sale.cash_register_session?.number" class="flex justify-between gap-3">
                    <span>Session caisse</span>
                    <span class="text-right font-mono">{{ sale.cash_register_session.number }}</span>
                </div>
            </div>

            <div class="mt-6 border-t pt-4" style="border-color: var(--mp-line)">
                <div class="mb-2 flex justify-between text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-[color:var(--mp-faint)]">
                    <span>Article</span>
                    <span>Montant</span>
                </div>
                <div v-for="line in sale.lines" :key="line.id" class="mp-row text-sm">
                    <div>
                        <div class="font-medium">{{ line.product?.commercial_name }}</div>
                        <div class="text-xs text-[color:var(--mp-faint)]">
                            {{ line.quantity }} × {{ Number(line.unit_price).toLocaleString('fr-FR') }} Fc
                            <span v-if="line.product?.sku"> · {{ line.product.sku }}</span>
                            <span v-if="line.batch?.lot_number"> · Lot {{ line.batch.lot_number }}</span>
                            <span v-if="Number(line.quantity_returned) > 0">
                                · Retourné {{ line.quantity_returned }}
                            </span>
                        </div>
                    </div>
                    <MoneyAmount :amount="line.line_total" size="sm" align="right" :show-fx="false" />
                </div>
            </div>

            <div class="mt-4 space-y-2 border-t pt-4" style="border-color: var(--mp-line)">
                <div class="flex justify-between text-sm">
                    <span class="text-[color:var(--mp-muted)]">Sous-total</span>
                    <MoneyAmount :amount="sale.subtotal" size="sm" align="right" :show-fx="false" />
                </div>
                <div v-if="Number(sale.discount_total) > 0" class="flex justify-between text-sm">
                    <span class="text-[color:var(--mp-muted)]">Remise</span>
                    <MoneyAmount :amount="sale.discount_total" size="sm" align="right" :show-fx="false" />
                </div>
                <div class="flex items-start justify-between pt-2">
                    <span class="font-semibold">Total</span>
                    <MoneyAmount :amount="sale.grand_total" size="lg" align="right" />
                </div>
            </div>

            <div class="mt-6 border-t pt-4 text-sm" style="border-color: var(--mp-line)">
                <div class="mp-metric-label">Paiements</div>
                <div v-for="p in sale.payments" :key="p.id" class="mt-2 flex justify-between">
                    <span>
                        {{ methodLabel(p.method) }}
                        <span v-if="p.provider" class="text-xs text-[color:var(--mp-faint)]"> · {{ p.provider }}</span>
                    </span>
                    <MoneyAmount :amount="p.amount" size="sm" align="right" :show-fx="false" />
                </div>
            </div>

            <p class="mt-8 text-center text-xs text-[color:var(--mp-faint)]">
                Merci de votre confiance · Manolya Pharma
            </p>
        </article>

        <section
            v-if="sale.returns?.length"
            class="mx-auto mt-10 max-w-xl print:hidden"
        >
            <h2 class="mp-section-title">Retours déjà effectués</h2>
            <div v-for="ret in sale.returns" :key="ret.id" class="mp-row">
                <div>
                    <div class="font-medium">{{ ret.number }}</div>
                    <div class="text-xs text-[color:var(--mp-muted)]">
                        {{ ret.processed_at }} · {{ methodLabel(ret.refund_method) }}
                    </div>
                </div>
                <MoneyAmount :amount="ret.refund_total" size="sm" align="right" />
            </div>
        </section>

        <section
            v-if="canRefund && returnableLines.length"
            class="mx-auto mt-10 max-w-xl space-y-4 border p-5 print:hidden"
            style="border-color: var(--mp-line)"
        >
            <h2 class="mp-section-title">Retour / remboursement</h2>
            <p v-if="!hasOpenSession" class="text-xs text-[color:var(--mp-muted)]">
                Astuce : ouvrez une session de caisse pour rattacher le remboursement cash à la clôture.
            </p>

            <div v-for="line in returnableLines" :key="line.id" class="mp-row items-center">
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium">{{ line.product?.commercial_name }}</div>
                    <div class="text-xs text-[color:var(--mp-faint)]">
                        Max {{ Number(line.quantity) - Number(line.quantity_returned ?? 0) }}
                    </div>
                </div>
                <input
                    v-model.number="qtyByLine[line.id]"
                    type="number"
                    min="0"
                    step="1"
                    :max="Number(line.quantity) - Number(line.quantity_returned ?? 0)"
                    class="mp-input w-24 text-center"
                />
            </div>

            <div>
                <label class="mp-metric-label">Mode de remboursement</label>
                <select v-model="returnForm.refund_method" class="mp-input mt-1">
                    <option value="cash">Espèces</option>
                    <option value="card">Carte</option>
                    <option value="mobile_money">Mobile Money</option>
                </select>
            </div>
            <div>
                <label class="mp-metric-label">Motif</label>
                <input v-model="returnForm.reason" type="text" class="mp-input mt-1" placeholder="Ex. produit endommagé" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="returnForm.restock" type="checkbox" />
                Remettre en stock
            </label>
            <button
                class="mp-btn mp-btn-primary w-full"
                type="button"
                :disabled="returnForm.processing"
                @click="submitReturn"
            >
                Valider le retour
            </button>
        </section>
    </AuthenticatedLayout>
</template>

<style>
@media print {
    @page {
        margin: 12mm;
    }

    body {
        background: #fff !important;
    }

    body * {
        visibility: hidden;
    }

    .ticket-print,
    .ticket-print * {
        visibility: visible;
    }

    .ticket-print {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: none;
        margin: 0;
        border: 1px solid #222 !important;
        background: #fff !important;
        box-shadow: none !important;
    }
}
</style>

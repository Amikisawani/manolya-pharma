<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import Receipt80mm from '@/Components/Receipt80mm.vue';
import { useThermalPrint } from '@/Composables/useThermalPrint';
import type { ThermalReceiptPayload } from '@/types/receipt';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps<{
    sale: Record<string, any>;
    receipt: ThermalReceiptPayload;
    printOnLoad?: boolean;
    isReprint?: boolean;
    canRefund?: boolean;
    hasOpenSession?: boolean;
}>();

const { printReceipt } = useThermalPrint(Boolean(props.printOnLoad));

const methodLabel = (method: string) =>
    ({ cash: 'Espèces', card: 'Carte', mobile_money: 'Mobile Money' }[method] ?? method);

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
            <div class="flex flex-wrap items-end justify-between gap-3">
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
                <div class="flex flex-wrap gap-2">
                    <button class="mp-btn mp-btn-primary" type="button" @click="printReceipt">
                        🖨 Imprimer le ticket
                    </button>
                    <Link :href="route('pos.index')" class="mp-btn mp-btn-ghost">Retour caisse</Link>
                </div>
            </div>
        </template>

        <div class="mp-ticket-stage">
            <p class="mp-no-print mb-3 text-center text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-[color:var(--mp-faint)]">
                Aperçu du ticket 80 mm
            </p>
            <div class="mp-ticket-paper">
                <Receipt80mm :receipt="receipt" />
            </div>
            <p class="mp-no-print mt-3 max-w-[80mm] text-center text-xs text-[color:var(--mp-muted)]">
                Choisissez l’imprimante thermique 80 mm dans la boîte de dialogue, sans ajuster à la page.
            </p>
        </div>

        <section
            v-if="sale.returns?.length"
            id="details"
            class="mp-no-print mx-auto mt-10 max-w-xl"
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
            class="mp-no-print mx-auto mt-10 max-w-xl space-y-4 border p-5"
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

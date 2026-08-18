<script setup lang="ts">
import type { ThermalReceiptPayload } from '@/types/receipt';

defineProps<{
    receipt: ThermalReceiptPayload;
}>();
</script>

<template>
    <article id="receipt-80mm" class="mp-ticket" aria-label="Ticket de caisse 80 millimètres">
        <p v-if="receipt.status_label" class="mp-ticket-status">{{ receipt.status_label }}</p>
        <p v-if="receipt.is_reprint" class="mp-ticket-status">DUPLICATA</p>

        <img
            v-if="receipt.logo_src"
            class="mp-ticket-logo"
            :src="receipt.logo_src"
            alt=""
        />

        <h1 class="mp-ticket-brand">{{ receipt.brand_name }}</h1>
        <p class="mp-ticket-pharmacy">{{ receipt.pharmacy_name }}</p>
        <p v-if="receipt.site_name" class="mp-ticket-meta">{{ receipt.site_name }}</p>
        <p v-if="receipt.address" class="mp-ticket-meta">{{ receipt.address }}</p>
        <p v-if="receipt.phone" class="mp-ticket-meta">Tél. {{ receipt.phone }}</p>
        <p v-if="receipt.email" class="mp-ticket-meta">{{ receipt.email }}</p>
        <p v-for="line in receipt.legal_lines" :key="line.label" class="mp-ticket-legal">
            {{ line.label }} : {{ line.value }}
        </p>

        <hr class="mp-ticket-rule" />

        <dl class="mp-ticket-kv">
            <dt>Vente n°</dt>
            <dd>{{ receipt.sale_number }}</dd>
            <dt>Date</dt>
            <dd>{{ receipt.sold_at_date }}</dd>
            <dt>Heure</dt>
            <dd>{{ receipt.sold_at_time }}</dd>
            <dt>Caissier</dt>
            <dd>{{ receipt.cashier_name }}</dd>
            <dt>Client</dt>
            <dd>{{ receipt.customer_name }}</dd>
            <dt>Paiement</dt>
            <dd>{{ receipt.payment_label }}</dd>
            <template v-if="receipt.register_number">
                <dt>Caisse</dt>
                <dd>{{ receipt.register_number }}</dd>
            </template>
            <template v-if="receipt.transaction_ref">
                <dt>Réf.</dt>
                <dd>{{ receipt.transaction_ref }}</dd>
            </template>
        </dl>

        <hr class="mp-ticket-rule" />

        <div v-for="(line, index) in receipt.lines" :key="index" class="mp-ticket-line">
            <div class="mp-ticket-line-name">{{ line.name }}</div>
            <div class="mp-ticket-line-qty">
                <span>{{ line.quantity_label }}</span>
                <span>{{ line.line_total }}</span>
            </div>
            <div v-if="line.lot" class="mp-ticket-lot">Lot {{ line.lot }}</div>
        </div>

        <hr class="mp-ticket-rule" />

        <div class="mp-ticket-totals">
            <div class="mp-ticket-total-row">
                <span>Sous-total</span>
                <span>{{ receipt.subtotal }}</span>
            </div>
            <div v-if="receipt.discount" class="mp-ticket-total-row">
                <span>Remise</span>
                <span>- {{ receipt.discount }}</span>
            </div>
            <div v-if="receipt.tax" class="mp-ticket-total-row">
                <span>Taxe</span>
                <span>{{ receipt.tax }}</span>
            </div>
            <hr class="mp-ticket-rule mp-ticket-rule-solid" />
            <div class="mp-ticket-due">
                <span>TOTAL À PAYER</span>
                <span>{{ receipt.grand_total }}</span>
            </div>
            <div class="mp-ticket-total-row">
                <span>Payé</span>
                <span>{{ receipt.amount_paid }}</span>
            </div>
            <div v-if="receipt.change" class="mp-ticket-total-row">
                <span>Monnaie</span>
                <span>{{ receipt.change }}</span>
            </div>
        </div>

        <hr class="mp-ticket-rule" />

        <p class="mp-ticket-foot">{{ receipt.item_count_label }}</p>
        <p v-if="receipt.note" class="mp-ticket-foot">{{ receipt.note }}</p>
        <p v-if="receipt.return_policy" class="mp-ticket-foot">{{ receipt.return_policy }}</p>

        <div
            v-if="receipt.show_qr && receipt.qr_svg"
            class="mp-ticket-qr"
            v-html="receipt.qr_svg"
        />

        <hr class="mp-ticket-rule" />

        <p class="mp-ticket-foot mp-ticket-thanks">Merci pour votre confiance !</p>
        <p class="mp-ticket-pharmacy">{{ receipt.brand_name }}</p>
        <p class="mp-ticket-foot">{{ receipt.footer_message }}</p>
    </article>
</template>

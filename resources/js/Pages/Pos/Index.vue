<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    warehouse: { id: string; name: string } | null;
    currency: string;
    openSession: Record<string, any> | null;
    warehouses: Array<{ id: string; name: string; site_id: string }>;
    receiptAutoPrint?: boolean;
}>();

type ProductHit = {
    id: string;
    sku: string;
    commercial_name: string;
    sale_price: string | number;
};

type CartLine = ProductHit & { quantity: number; discount_amount: number };

const query = ref('');
const results = ref<ProductHit[]>([]);
const cart = ref<CartLine[]>([]);
const paymentMethod = ref<'cash' | 'card' | 'mobile_money'>('cash');
const momoProvider = ref<'orange' | 'airtel' | 'mtn'>('orange');
const searching = ref(false);
const customerName = ref('');
const note = ref('');
const tenderedAmount = ref<number | null>(null);
const page = usePage();
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let searchSeq = 0;

const sessionOpen = computed(() => !!props.openSession);

const subtotal = computed(() =>
    cart.value.reduce((sum, line) => sum + Number(line.sale_price) * line.quantity - line.discount_amount, 0),
);

const form = useForm({
    warehouse_id: props.warehouse?.id ?? '',
    discount_total: 0,
    customer_name: '',
    note: '',
    amount_tendered: 0 as number | null,
    lines: [] as Array<Record<string, string | number>>,
    payments: [] as Array<Record<string, string | number>>,
});

const openForm = useForm({
    warehouse_id: props.warehouse?.id ?? props.warehouses[0]?.id ?? '',
    opening_float: 0,
    opening_notes: '',
});

const search = async () => {
    const term = query.value.trim();
    if (!term) {
        results.value = [];
        searching.value = false;
        return;
    }

    const seq = ++searchSeq;
    searching.value = true;
    try {
        const response = await fetch(route('pos.search', { q: term }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (seq === searchSeq) {
            results.value = data.data ?? [];
        }
    } finally {
        if (seq === searchSeq) {
            searching.value = false;
        }
    }
};

watch(query, () => {
    if (searchTimer) clearTimeout(searchTimer);
    if (!query.value.trim()) {
        results.value = [];
        searching.value = false;
        return;
    }
    searching.value = true;
    searchTimer = setTimeout(() => {
        void search();
    }, 220);
});

onBeforeUnmount(() => {
    if (searchTimer) clearTimeout(searchTimer);
});

const addToCart = (product: ProductHit) => {
    if (!sessionOpen.value) return;
    const existing = cart.value.find((l) => l.id === product.id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.value.push({ ...product, quantity: 1, discount_amount: 0 });
    }
};

const bumpQty = (id: string, delta: number) => {
    const line = cart.value.find((l) => l.id === id);
    if (!line) return;
    line.quantity = Math.max(1, line.quantity + delta);
};

const removeLine = (id: string) => {
    cart.value = cart.value.filter((l) => l.id !== id);
};

const checkout = () => {
    if (!sessionOpen.value) return;
    const dueNow = due.value;
    const cashTendered =
        paymentMethod.value === 'cash'
            ? Number(tenderedAmount.value ?? dueNow)
            : dueNow;

    if (paymentMethod.value === 'cash' && cashTendered < dueNow) {
        return;
    }

    form.lines = cart.value.map((l) => ({
        product_id: l.id,
        quantity: l.quantity,
        unit_price: Number(l.sale_price),
        discount_amount: l.discount_amount,
    }));
    form.customer_name = customerName.value.trim();
    form.note = note.value.trim();
    form.amount_tendered = cashTendered;
    form.payments = [
        {
            method: paymentMethod.value,
            amount: dueNow,
            ...(paymentMethod.value === 'mobile_money' ? { provider: momoProvider.value } : {}),
        },
    ];
    form.post(route('pos.store'), {
        onSuccess: () => {
            cart.value = [];
            results.value = [];
            query.value = '';
            customerName.value = '';
            note.value = '';
            tenderedAmount.value = null;
        },
    });
};

const submitOpenSession = () => openForm.post(route('pos.sessions.store'));

const flash = computed(() => (page.props as { flash?: { success?: string } }).flash?.success);
const due = computed(() => Math.max(subtotal.value - Number(form.discount_total), 0));
const cashReceived = computed(() =>
    paymentMethod.value === 'cash' ? Number(tenderedAmount.value ?? due.value) : due.value,
);
const changeDue = computed(() => Math.max(0, cashReceived.value - due.value));
const canCheckout = computed(
    () => cart.value.length > 0 && !form.processing && cashReceived.value >= due.value,
);
</script>

<template>
    <Head title="Caisse" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Point de vente</p>
                    <h1 class="mp-display mt-1 text-4xl">Caisse</h1>
                    <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
                        Entrepôt : {{ warehouse?.name ?? 'Non défini' }}
                        <span v-if="openSession"> · Session {{ openSession.number }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('pos.sessions.index')" class="mp-btn mp-btn-ghost">Sessions</Link>
                    <Link
                        v-if="openSession"
                        :href="route('pos.sessions.show', openSession.id)"
                        class="mp-btn mp-btn-primary"
                    >
                        Clôturer
                    </Link>
                </div>
            </div>
        </template>

        <div
            v-if="flash"
            class="mb-6 border px-4 py-3 text-sm"
            style="border-color: #a8d5c0; background: var(--mp-accent-soft); color: var(--mp-accent)"
        >
            {{ flash }}
        </div>

        <div
            v-if="!sessionOpen"
            class="mx-auto max-w-md space-y-4 border p-6"
            style="border-color: var(--mp-line); background: rgba(255,252,247,0.8)"
        >
            <h2 class="mp-section-title">Ouvrir la caisse</h2>
            <p class="text-sm text-[color:var(--mp-muted)]">
                Une session doit être ouverte avant d’encaisser. Indiquez le fond de caisse.
            </p>
            <form class="space-y-3" @submit.prevent="submitOpenSession">
                <select v-model="openForm.warehouse_id" class="mp-input">
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
                <div>
                    <label class="mp-metric-label">Fond de caisse (Fc)</label>
                    <input v-model.number="openForm.opening_float" type="number" min="0" step="1" class="mp-input mt-1" required />
                </div>
                <textarea v-model="openForm.opening_notes" class="mp-input" rows="2" placeholder="Notes d’ouverture" />
                <button class="mp-btn mp-btn-primary w-full" :disabled="openForm.processing">Ouvrir la session</button>
            </form>
        </div>

        <div v-else class="grid gap-8 lg:grid-cols-12">
            <section class="lg:col-span-7">
                <label class="mp-metric-label">Recherche produit</label>
                <div class="mt-2 relative">
                    <input
                        v-model="query"
                        class="mp-input"
                        placeholder="Tapez un nom, SKU ou code-barres…"
                        autofocus
                    />
                    <span
                        v-if="searching"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[color:var(--mp-faint)]"
                    >
                        …
                    </span>
                </div>
                <p class="mt-1 text-xs text-[color:var(--mp-faint)]">Recherche instantanée pendant la saisie</p>

                <div class="mt-4">
                    <button
                        v-for="p in results"
                        :key="p.id"
                        type="button"
                        class="mp-row w-full text-left"
                        @click="addToCart(p)"
                    >
                        <div>
                            <div class="font-medium">{{ p.commercial_name }}</div>
                            <div class="text-xs text-[color:var(--mp-faint)]">{{ p.sku }}</div>
                        </div>
                        <MoneyAmount :amount="p.sale_price" size="sm" align="right" />
                    </button>
                    <p v-if="!results.length" class="py-16 text-center text-sm text-[color:var(--mp-muted)]">
                        Scannez ou recherchez pour démarrer la vente
                    </p>
                </div>
            </section>

            <section class="border p-5 lg:col-span-5" style="border-color: var(--mp-line); background: rgba(255,252,247,0.8)">
                <h2 class="mp-section-title">Panier</h2>
                <div class="mt-4 space-y-1">
                    <div v-for="line in cart" :key="line.id" class="mp-row">
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ line.commercial_name }}</div>
                            <MoneyAmount :amount="line.sale_price" size="sm" />
                            <div class="mt-2 flex items-center gap-2">
                                <button class="mp-btn mp-btn-ghost px-3" type="button" @click="bumpQty(line.id, -1)">−</button>
                                <input v-model.number="line.quantity" type="number" min="1" class="mp-input w-20 text-center" />
                                <button class="mp-btn mp-btn-ghost px-3" type="button" @click="bumpQty(line.id, 1)">+</button>
                                <button class="text-xs text-[color:var(--mp-danger)]" type="button" @click="removeLine(line.id)">
                                    Retirer
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-if="!cart.length" class="py-8 text-sm text-[color:var(--mp-muted)]">Panier vide</p>
                </div>

                <div class="mt-6 space-y-3 border-t pt-4" style="border-color: var(--mp-line)">
                    <div class="flex items-start justify-between">
                        <span class="text-sm text-[color:var(--mp-muted)]">À encaisser</span>
                        <MoneyAmount :amount="due" size="lg" align="right" />
                    </div>
                    <div>
                        <label class="mp-metric-label">Remise (Fc)</label>
                        <input v-model.number="form.discount_total" type="number" min="0" class="mp-input mt-1" />
                    </div>
                    <div>
                        <label class="mp-metric-label">Client (optionnel)</label>
                        <input
                            v-model="customerName"
                            type="text"
                            class="mp-input mt-1"
                            placeholder="Client comptoir"
                        />
                    </div>
                    <div>
                        <label class="mp-metric-label">Paiement</label>
                        <select v-model="paymentMethod" class="mp-input mt-1">
                            <option value="cash">Espèces</option>
                            <option value="card">Carte</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <div v-if="paymentMethod === 'cash'">
                        <label class="mp-metric-label">Montant reçu (Fc)</label>
                        <input
                            v-model.number="tenderedAmount"
                            type="number"
                            min="0"
                            class="mp-input mt-1"
                            :placeholder="String(due)"
                        />
                        <p v-if="changeDue > 0" class="mt-1 text-xs text-[color:var(--mp-muted)]">
                            Monnaie à rendre : {{ new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(changeDue) }} Fc
                        </p>
                    </div>
                    <div v-if="paymentMethod === 'mobile_money'">
                        <label class="mp-metric-label">Opérateur</label>
                        <select v-model="momoProvider" class="mp-input mt-1">
                            <option value="orange">Orange Money</option>
                            <option value="airtel">Airtel Money</option>
                            <option value="mtn">MTN MoMo</option>
                        </select>
                    </div>
                    <div>
                        <label class="mp-metric-label">Note ticket (optionnel)</label>
                        <input v-model="note" type="text" class="mp-input mt-1" maxlength="255" />
                    </div>
                    <button
                        class="mp-btn mp-btn-primary w-full"
                        type="button"
                        :disabled="!canCheckout"
                        @click="checkout"
                    >
                        Encaisser
                    </button>
                    <p v-if="receiptAutoPrint" class="text-center text-xs text-[color:var(--mp-faint)]">
                        Le ticket s’imprimera automatiquement après validation.
                    </p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    warehouse: { id: string; name: string } | null;
    currencyCode: string;
    openSession: Record<string, any> | null;
    sessionGate: {
        state: 'open' | 'continue' | 'closed';
        label: string;
        disabled: boolean;
        can_request_close: boolean;
        closure_pending: boolean;
        close_request_rejected?: boolean;
        status_message?: string;
        next_opening_label?: string | null;
        business_date: string;
    };
    warehouses: Array<{ id: string; name: string; site_id: string }>;
}>();

type ProductHit = {
    id: string;
    sku: string;
    commercial_name: string;
    sale_price: string | number;
};

type CartLine = ProductHit & { quantity: number; discount_amount: number; unit_price: number };

const query = ref('');
const results = ref<ProductHit[]>([]);
const cart = ref<CartLine[]>([]);
const paymentMethod = ref<'cash' | 'card' | 'mobile_money'>('cash');
const momoProvider = ref<'orange' | 'airtel' | 'mtn'>('orange');
const searching = ref(false);
const cartListOpen = ref(false);
const cartPos = ref({ x: 24, y: 80 });
const cartDragging = ref(false);
const page = usePage();
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let searchSeq = 0;
let dragOffsetX = 0;
let dragOffsetY = 0;
let cartPlaced = false;

const sessionOpen = computed(() => !!props.openSession);

const lineUnitPrice = (line: CartLine): number => {
    const price = Number(line.unit_price);
    return Number.isFinite(price) && price >= 0 ? price : 0;
};

const lineQuantity = (line: CartLine): number => {
    const qty = Number(line.quantity);
    return Number.isFinite(qty) && qty > 0 ? qty : 1;
};

const subtotal = computed(() =>
    cart.value.reduce((sum, line) => sum + lineUnitPrice(line) * lineQuantity(line) - Number(line.discount_amount || 0), 0),
);

const form = useForm({
    warehouse_id: props.warehouse?.id ?? '',
    discount_total: 0,
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

const cartCount = computed(() => cart.value.length);

const placeCartList = () => {
    if (cartPlaced) {
        return;
    }

    const width = Math.min(672, window.innerWidth - 24);
    cartPos.value = {
        x: Math.max(12, Math.round((window.innerWidth - width) / 2)),
        y: 72,
    };
    cartPlaced = true;
};

const openCartList = () => {
    if (cart.value.length === 0) {
        return;
    }

    placeCartList();
    cartListOpen.value = true;
};

const closeCartList = () => {
    cartListOpen.value = false;
};

const clampCartPos = (x: number, y: number) => {
    const maxX = Math.max(12, window.innerWidth - 80);
    const maxY = Math.max(12, window.innerHeight - 56);

    return {
        x: Math.min(Math.max(8, x), maxX),
        y: Math.min(Math.max(8, y), maxY),
    };
};

const onCartDragMove = (event: PointerEvent) => {
    if (!cartDragging.value) {
        return;
    }

    cartPos.value = clampCartPos(event.clientX - dragOffsetX, event.clientY - dragOffsetY);
};

const onCartDragEnd = () => {
    cartDragging.value = false;
    window.removeEventListener('pointermove', onCartDragMove);
    window.removeEventListener('pointerup', onCartDragEnd);
};

const onCartDragStart = (event: PointerEvent) => {
    if (event.button !== 0) {
        return;
    }

    const target = event.target as HTMLElement | null;
    if (target?.closest('button, input, select, textarea, a')) {
        return;
    }

    cartDragging.value = true;
    dragOffsetX = event.clientX - cartPos.value.x;
    dragOffsetY = event.clientY - cartPos.value.y;
    window.addEventListener('pointermove', onCartDragMove);
    window.addEventListener('pointerup', onCartDragEnd);
};

onBeforeUnmount(() => {
    if (searchTimer) clearTimeout(searchTimer);
    window.removeEventListener('pointermove', onCartDragMove);
    window.removeEventListener('pointerup', onCartDragEnd);
});

const addToCart = (product: ProductHit) => {
    if (!sessionOpen.value) return;
    const existing = cart.value.find((l) => l.id === product.id);
    if (existing) {
        existing.quantity = lineQuantity(existing) + 1;
    } else {
        cart.value.push({
            ...product,
            quantity: 1,
            discount_amount: 0,
            unit_price: Number(product.sale_price) || 0,
        });
    }
    openCartList();
};

const bumpQty = (id: string, delta: number) => {
    const line = cart.value.find((l) => l.id === id);
    if (!line) return;
    line.quantity = Math.max(1, lineQuantity(line) + delta);
};

const removeLine = (id: string) => {
    cart.value = cart.value.filter((l) => l.id !== id);
    if (cart.value.length === 0) {
        closeCartList();
    }
};

const checkout = () => {
    if (!sessionOpen.value) return;
    form.lines = cart.value.map((l) => ({
        product_id: l.id,
        quantity: lineQuantity(l),
        unit_price: lineUnitPrice(l),
        discount_amount: Number(l.discount_amount || 0),
    }));
    form.payments = [
        {
            method: paymentMethod.value,
            amount: Math.max(subtotal.value - Number(form.discount_total), 0),
            ...(paymentMethod.value === 'mobile_money' ? { provider: momoProvider.value } : {}),
        },
    ];
    form.post(route('pos.store'), {
        onSuccess: () => {
            cart.value = [];
            results.value = [];
            query.value = '';
            closeCartList();
        },
    });
};

const submitOpenSession = () => openForm.post(route('pos.sessions.store'));

const flash = computed(() => (page.props as { flash?: { success?: string } }).flash?.success);
const due = computed(() => Math.max(subtotal.value - Number(form.discount_total), 0));
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
                        <span v-if="sessionGate.status_message"> · {{ sessionGate.status_message }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('pos.sessions.index')" class="mp-btn mp-btn-ghost">Sessions</Link>
                    <Link
                        v-if="openSession && sessionGate.can_request_close"
                        :href="route('pos.sessions.show', openSession.id)"
                        class="mp-btn mp-btn-primary"
                    >
                        Demander la fermeture
                    </Link>
                    <button
                        v-else-if="sessionGate.closure_pending"
                        class="mp-btn mp-btn-ghost"
                        type="button"
                        disabled
                    >
                        Fermeture en attente
                    </button>
                    <template v-else-if="sessionGate.close_request_rejected">
                        <span class="mp-badge mp-badge-ok">Session en cours</span>
                        <button class="mp-btn mp-btn-ghost" type="button" disabled>
                            Demander la fermeture
                        </button>
                    </template>
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
            v-if="sessionGate.state === 'closed'"
            class="mx-auto max-w-md space-y-4 border p-6"
            style="border-color: var(--mp-line); background: rgba(255,252,247,0.8)"
        >
            <h2 class="mp-section-title">{{ sessionGate.status_message || 'Caisse fermée' }}</h2>
            <p class="text-sm text-[color:var(--mp-muted)]">
                Une seule session par jour. Réouverture le {{ sessionGate.next_opening_label }}, ou si le
                propriétaire / l’admin autorise une nouvelle ouverture.
            </p>
            <button class="mp-btn mp-btn-primary w-full" type="button" disabled>Fermé</button>
        </div>

        <div
            v-else-if="!sessionOpen"
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

        <div v-else class="grid items-start gap-8 lg:grid-cols-12">
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

            <section
                class="mp-pos-cart min-w-0 self-start overflow-hidden border p-5 lg:col-span-5"
                style="border-color: var(--mp-line); background: rgba(255,252,247,0.96)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="mp-section-title">Panier</h2>
                        <p class="mt-1 text-xs text-[color:var(--mp-faint)]">
                            {{ cartCount === 0 ? 'Aucun produit' : `${cartCount} produit${cartCount > 1 ? 's' : ''}` }}
                        </p>
                    </div>
                    <button
                        v-if="cartCount > 0 && !cartListOpen"
                        class="mp-btn mp-btn-ghost shrink-0"
                        type="button"
                        @click="openCartList"
                    >
                        Voir les produits
                    </button>
                </div>

                <div class="mt-5 space-y-3">
                    <div class="flex items-start justify-between">
                        <span class="text-sm text-[color:var(--mp-muted)]">À encaisser</span>
                        <MoneyAmount :amount="due" size="lg" align="right" />
                    </div>
                    <div>
                        <label class="mp-metric-label">Remise (Fc)</label>
                        <input v-model.number="form.discount_total" type="number" min="0" class="mp-input mt-1" />
                    </div>
                    <div>
                        <label class="mp-metric-label">Paiement</label>
                        <select v-model="paymentMethod" class="mp-input mt-1">
                            <option value="cash">Espèces</option>
                            <option value="card">Carte</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <div v-if="paymentMethod === 'mobile_money'">
                        <label class="mp-metric-label">Opérateur</label>
                        <select v-model="momoProvider" class="mp-input mt-1">
                            <option value="orange">Orange Money</option>
                            <option value="airtel">Airtel Money</option>
                            <option value="mtn">MTN MoMo</option>
                        </select>
                    </div>
                    <button
                        class="mp-btn mp-btn-primary w-full"
                        type="button"
                        :disabled="!cart.length || form.processing"
                        @click="checkout"
                    >
                        Encaisser
                    </button>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="sessionOpen && cartListOpen && cartCount > 0"
                class="mp-pos-cart-float"
                :class="{ 'is-dragging': cartDragging }"
                :style="{ left: `${cartPos.x}px`, top: `${cartPos.y}px` }"
                role="dialog"
                aria-label="Produits du panier"
            >
                <div class="mp-pos-cart-float-head" @pointerdown="onCartDragStart">
                    <div class="min-w-0">
                        <p class="mp-metric-label">Produits du panier</p>
                        <p class="mt-0.5 truncate text-sm font-medium">
                            {{ cartCount }} article{{ cartCount > 1 ? 's' : '' }} · glisser pour déplacer
                        </p>
                    </div>
                    <button class="mp-btn mp-btn-ghost shrink-0 px-3" type="button" @click="closeCartList">
                        Fermer
                    </button>
                </div>
                <div class="mp-scroll-main mp-pos-cart-float-body">
                    <article v-for="line in cart" :key="line.id" class="mp-pos-cart-line">
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ line.commercial_name }}</div>
                            <div class="text-xs text-[color:var(--mp-faint)]">{{ line.sku }}</div>
                        </div>
                        <div>
                            <label class="mp-metric-label" :for="`line-amount-${line.id}`">Montant (Fc)</label>
                            <input
                                :id="`line-amount-${line.id}`"
                                v-model.number="line.unit_price"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                class="mp-input mt-1 w-full min-w-0 tabular-nums"
                            />
                        </div>
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="flex shrink-0 items-center gap-1.5">
                                <button class="mp-btn mp-btn-ghost px-2.5" type="button" @click="bumpQty(line.id, -1)">−</button>
                                <input
                                    v-model.number="line.quantity"
                                    type="number"
                                    min="1"
                                    class="mp-input mp-qty-input"
                                    aria-label="Quantité"
                                />
                                <button class="mp-btn mp-btn-ghost px-2.5" type="button" @click="bumpQty(line.id, 1)">+</button>
                            </div>
                            <button class="shrink-0 text-xs text-[color:var(--mp-danger)]" type="button" @click="removeLine(line.id)">
                                Retirer
                            </button>
                        </div>
                    </article>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

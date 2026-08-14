<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatQty, toQtyNumber } from '@/Composables/useQuantity';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    count: Record<string, any>;
}>();

const submitForm = useForm({
    lines: (props.count.lines || []).map((l: any) => ({
        id: l.id,
        counted_qty: toQtyNumber(l.counted_qty ?? l.expected_qty ?? 0),
    })),
});

const submitCount = () => submitForm.post(route('inventory.counts.submit', props.count.id));
</script>

<template>
    <Head title="Détail inventaire" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Link :href="route('inventory.counts.index')" class="text-xs text-[color:var(--mp-accent)]">← Inventaires</Link>
                    <h1 class="mp-display mt-2 text-4xl">{{ count.warehouse?.name }}</h1>
                    <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
                        {{ count.type }} · <span class="mp-badge">{{ count.status }}</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        v-if="count.status === 'open' || count.status === 'counting'"
                        class="mp-btn mp-btn-primary"
                        type="button"
                        :disabled="submitForm.processing"
                        @click="submitCount"
                    >
                        Soumettre le comptage
                    </button>
                    <Link
                        v-if="['submitted', 'review'].includes(count.status)"
                        :href="route('inventory.counts.validate', count.id)"
                        method="post"
                        as="button"
                        class="mp-btn mp-btn-primary"
                    >
                        Valider les écarts
                    </Link>
                </div>
            </div>
        </template>

        <table class="mp-table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Lot</th>
                    <th>Attendu</th>
                    <th>Compté</th>
                    <th>Écart</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(line, idx) in submitForm.lines" :key="line.id">
                    <td>{{ count.lines[idx]?.product?.commercial_name }}</td>
                    <td class="text-[color:var(--mp-muted)]">{{ count.lines[idx]?.batch?.lot_number ?? '—' }}</td>
                    <td class="tabular-nums">{{ formatQty(count.lines[idx]?.expected_qty) }}</td>
                    <td>
                        <input
                            v-model.number="line.counted_qty"
                            type="number"
                            step="0.001"
                            class="mp-input max-w-[120px]"
                            :disabled="!['open', 'counting'].includes(count.status)"
                        />
                    </td>
                    <td class="tabular-nums">
                        {{
                            formatQty(
                                Number(line.counted_qty ?? 0) - Number(count.lines[idx]?.expected_qty ?? 0),
                            )
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-if="!submitForm.lines.length" class="py-10 text-sm text-[color:var(--mp-muted)]">Aucune ligne à compter</p>
    </AuthenticatedLayout>
</template>

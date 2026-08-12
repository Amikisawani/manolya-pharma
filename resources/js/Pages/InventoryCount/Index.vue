<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    counts: { data: Array<Record<string, any>> };
    warehouses: Array<{ id: string; name: string }>;
}>();

const form = useForm({
    warehouse_id: '',
    type: 'full',
});

const create = () => form.post(route('inventory.counts.store'));
</script>

<template>
    <Head title="Inventaires" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Contrôle</p>
                <h1 class="mp-display mt-1 text-4xl">Inventaires</h1>
            </div>
        </template>

        <div class="grid gap-10 lg:grid-cols-12">
            <form class="space-y-3 border p-5 lg:col-span-4" style="border-color: var(--mp-line)" @submit.prevent="create">
                <h2 class="mp-section-title">Nouvel inventaire</h2>
                <select v-model="form.warehouse_id" class="mp-input" required>
                    <option value="">Entrepôt</option>
                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
                <select v-model="form.type" class="mp-input">
                    <option value="full">Complet</option>
                    <option value="partial">Partiel</option>
                    <option value="rotating">Tournant</option>
                </select>
                <button class="mp-btn mp-btn-primary w-full" :disabled="form.processing">Ouvrir</button>
            </form>

            <div class="lg:col-span-8">
                <div v-for="c in counts.data" :key="c.id" class="mp-row">
                    <div>
                        <div class="font-semibold">{{ c.warehouse?.name }}</div>
                        <div class="text-xs text-[color:var(--mp-muted)]">{{ c.type }} · {{ c.status }}</div>
                    </div>
                    <Link :href="route('inventory.counts.show', c.id)" class="mp-btn mp-btn-ghost">Ouvrir</Link>
                </div>
                <p v-if="!counts.data.length" class="py-10 text-sm text-[color:var(--mp-muted)]">Aucun inventaire</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    records: { data: Array<Record<string, any>> };
    filters: Record<string, string>;
}>();

const action = ref(props.filters.action ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const filter = () =>
    router.get(route('audit.index'), { action: action.value, from: from.value, to: to.value }, { preserveState: true });
</script>

<template>
    <Head title="Audit" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Conformité</p>
                <h1 class="mp-display mt-1 text-4xl">Journal d’audit</h1>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap gap-2">
            <input v-model="action" class="mp-input max-w-xs" placeholder="Action…" />
            <input v-model="from" type="date" class="mp-input max-w-[160px]" />
            <input v-model="to" type="date" class="mp-input max-w-[160px]" />
            <button class="mp-btn mp-btn-ghost" type="button" @click="filter">Filtrer</button>
        </div>

        <div class="relative before:absolute before:bottom-2 before:left-1 before:top-2 before:w-px before:bg-[color:var(--mp-line)]">
            <div v-for="record in records.data" :key="record.id" class="relative mb-6 pl-8">
                <div class="absolute left-0 top-2 h-2.5 w-2.5 rounded-full border-2 border-[color:var(--mp-accent)] bg-[color:var(--mp-bg)]" />
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div class="font-semibold">{{ record.action }}</div>
                    <div class="font-mono text-xs text-[color:var(--mp-faint)]">{{ record.created_at }}</div>
                </div>
                <div class="mt-1 text-sm text-[color:var(--mp-muted)]">
                    {{ record.user?.name ?? 'Système' }}
                    <span v-if="record.auditable_type"> · {{ String(record.auditable_type).split('\\').pop() }}</span>
                </div>
                <div v-if="record.ip" class="mt-1 font-mono text-[0.7rem] text-[color:var(--mp-faint)]">{{ record.ip }}</div>
            </div>
            <p v-if="!records.data.length" class="pl-8 text-sm text-[color:var(--mp-muted)]">Aucun événement</p>
        </div>
    </AuthenticatedLayout>
</template>

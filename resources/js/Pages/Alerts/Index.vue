<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    alerts: { data: Array<Record<string, any>> };
    filters: Record<string, string>;
}>();

const status = ref(props.filters.status ?? '');
const severity = ref(props.filters.severity ?? '');

const filter = () =>
    router.get(route('alerts.index'), { status: status.value, severity: severity.value }, { preserveState: true });
</script>

<template>
    <Head title="Alertes" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Surveillance</p>
                <h1 class="mp-display mt-1 text-4xl">Alertes</h1>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap gap-2">
            <select v-model="status" class="mp-input max-w-[160px]" @change="filter">
                <option value="">Tous</option>
                <option value="open">Ouvertes</option>
                <option value="acked">Acquittées</option>
            </select>
            <select v-model="severity" class="mp-input max-w-[160px]" @change="filter">
                <option value="">Sévérité</option>
                <option value="critical">Critique</option>
                <option value="warning">Avertissement</option>
                <option value="info">Info</option>
            </select>
        </div>

        <div>
            <div v-for="alert in alerts.data" :key="alert.id" class="mp-row">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="mp-badge"
                            :class="{
                                'mp-badge-danger': alert.severity === 'critical',
                                'mp-badge-warn': alert.severity === 'warning' || alert.severity === 'medium',
                            }"
                        >
                            {{ alert.severity }}
                        </span>
                        <h2 class="font-semibold">{{ alert.title }}</h2>
                    </div>
                    <p class="mt-2 text-sm text-[color:var(--mp-muted)]">{{ alert.body }}</p>
                    <p class="mt-1 text-xs text-[color:var(--mp-faint)]">{{ alert.raised_at }} · {{ alert.status }}</p>
                </div>
                <Link
                    v-if="alert.status === 'open'"
                    :href="route('alerts.acknowledge', alert.id)"
                    method="post"
                    as="button"
                    class="mp-btn mp-btn-primary"
                >
                    Acquitter
                </Link>
            </div>
            <p v-if="!alerts.data.length" class="py-10 text-sm text-[color:var(--mp-muted)]">Aucune alerte</p>
        </div>
    </AuthenticatedLayout>
</template>

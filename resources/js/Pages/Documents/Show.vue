<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    document: Record<string, any>;
}>();

const reprocess = useForm({});
const runOcr = () => reprocess.post(route('documents.reprocess', props.document.id));

const typeLabel = (type: string) =>
    ({ invoice: 'Facture', delivery: 'Bon de livraison', license: 'Licence', other: 'Autre' }[type] ?? type);

const ocrLabel = (status?: string) =>
    ({ pending: 'En attente', processing: 'En cours', completed: 'Terminé', failed: 'Échec' }[status ?? ''] ?? status);
</script>

<template>
    <Head :title="document.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Link :href="route('documents.index')" class="text-xs text-[color:var(--mp-accent)]">← Documents</Link>
                    <h1 class="mp-display mt-2 text-4xl">{{ document.title }}</h1>
                    <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
                        {{ typeLabel(document.type) }} · version {{ document.current_version }}
                    </p>
                </div>
                <button class="mp-btn mp-btn-ghost" type="button" :disabled="reprocess.processing" @click="runOcr">
                    Relancer OCR
                </button>
            </div>
        </template>

        <section class="mb-8 border p-5" style="border-color: var(--mp-line)">
            <h2 class="mp-section-title">Texte indexé (recherche)</h2>
            <pre class="mt-3 max-h-64 overflow-auto whitespace-pre-wrap text-sm text-[color:var(--mp-muted)]">{{ document.search_text || '—' }}</pre>
        </section>

        <section>
            <h2 class="mp-section-title">Versions</h2>
            <div v-for="v in document.versions || []" :key="v.id" class="mp-row items-start">
                <div class="min-w-0 flex-1">
                    <div class="font-medium">v{{ v.version }} · {{ ocrLabel(v.ocr_status) }}</div>
                    <div class="text-xs text-[color:var(--mp-muted)]">
                        {{ v.mime }} · {{ Math.round((v.size || 0) / 1024) }} Ko
                        <span v-if="v.ocr_engine"> · {{ v.ocr_engine }}</span>
                        <span v-if="v.uploader?.name"> · {{ v.uploader.name }}</span>
                    </div>
                    <p v-if="v.ocr_error" class="mt-1 text-xs text-[color:var(--mp-danger)]">{{ v.ocr_error }}</p>
                    <p v-if="v.ocr_text" class="mt-2 line-clamp-3 text-sm text-[color:var(--mp-muted)]">{{ v.ocr_text }}</p>
                </div>
                <a :href="route('documents.download', [document.id, v.id])" class="text-sm text-[color:var(--mp-accent)]">
                    Télécharger
                </a>
            </div>
        </section>
    </AuthenticatedLayout>
</template>

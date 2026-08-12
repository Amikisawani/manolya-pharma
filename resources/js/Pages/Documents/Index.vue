<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    documents: { data: Array<Record<string, any>> };
    filters: { q?: string };
}>();

const q = ref(props.filters.q ?? '');
const form = useForm({
    type: 'other',
    title: '',
    file: null as File | null,
});

const search = () => router.get(route('documents.index'), { q: q.value }, { preserveState: true });
const onFile = (e: Event) => {
    const input = e.target as HTMLInputElement;
    form.file = input.files?.[0] ?? null;
};
const submit = () =>
    form.post(route('documents.store'), {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });

const ocrLabel = (status?: string) =>
    ({ pending: 'OCR en attente', processing: 'OCR…', completed: 'OCR ok', failed: 'OCR échec' }[status ?? ''] ?? status);
</script>

<template>
    <Head title="Documents" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">GED</p>
                <h1 class="mp-display mt-1 text-4xl">Documents</h1>
                <p class="mt-2 text-sm text-[color:var(--mp-muted)]">Upload, OCR async, recherche plein texte</p>
            </div>
        </template>

        <div class="grid gap-10 lg:grid-cols-12">
            <form class="space-y-3 border p-5 lg:col-span-4" style="border-color: var(--mp-line)" @submit.prevent="submit">
                <h2 class="mp-section-title">Téléverser</h2>
                <input v-model="form.title" class="mp-input" placeholder="Titre" required />
                <select v-model="form.type" class="mp-input">
                    <option value="invoice">Facture</option>
                    <option value="delivery">Bon de livraison</option>
                    <option value="license">Licence</option>
                    <option value="other">Autre</option>
                </select>
                <input type="file" class="mp-input" @change="onFile" required />
                <p class="text-xs text-[color:var(--mp-faint)]">PDF, texte, images (max 10 Mo). OCR indexe le contenu extractible.</p>
                <button class="mp-btn mp-btn-primary w-full" :disabled="form.processing || !form.file">Envoyer</button>
            </form>

            <div class="lg:col-span-8">
                <div class="mb-4 flex gap-2">
                    <input
                        v-model="q"
                        class="mp-input"
                        placeholder="Recherche plein texte (titre, OCR…)"
                        @keyup.enter="search"
                    />
                    <button class="mp-btn mp-btn-ghost" type="button" @click="search">Filtrer</button>
                </div>
                <div v-for="doc in documents.data" :key="doc.id" class="mp-row items-start">
                    <div class="min-w-0 flex-1">
                        <Link :href="route('documents.show', doc.id)" class="font-semibold text-[color:var(--mp-ink)] hover:text-[color:var(--mp-accent)]">
                            {{ doc.title }}
                        </Link>
                        <div class="text-xs text-[color:var(--mp-muted)]">
                            {{ doc.type }} · v{{ doc.current_version }} · {{ ocrLabel(doc.ocr_status) }}
                            <span v-if="doc.ocr_engine"> · {{ doc.ocr_engine }}</span>
                        </div>
                        <p v-if="doc.snippet" class="mt-1 text-sm text-[color:var(--mp-muted)]">{{ doc.snippet }}</p>
                    </div>
                </div>
                <p v-if="!documents.data.length" class="py-10 text-sm text-[color:var(--mp-muted)]">Aucun document</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

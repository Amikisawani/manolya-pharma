<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    suppliers: { data: Array<Record<string, any>> };
    filters: { q?: string };
}>();

const q = ref(props.filters.q ?? '');
const form = useForm({
    name: '',
    code: '',
    phone: '',
    email: '',
    address: '',
    payment_terms: '',
});

const search = () => router.get(route('purchasing.suppliers.index'), { q: q.value }, { preserveState: true });
const submit = () => form.post(route('purchasing.suppliers.store'), { onSuccess: () => form.reset() });
</script>

<template>
    <Head title="Fournisseurs" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Link :href="route('purchasing.orders.index')" class="text-xs text-[color:var(--mp-accent)]">← Achats</Link>
                    <h1 class="mp-display mt-2 text-4xl">Fournisseurs</h1>
                </div>
            </div>
        </template>

        <div class="grid gap-10 lg:grid-cols-12">
            <form class="space-y-3 border p-5 lg:col-span-4" style="border-color: var(--mp-line)" @submit.prevent="submit">
                <h2 class="mp-section-title">Nouveau fournisseur</h2>
                <input v-model="form.name" class="mp-input" placeholder="Nom" required />
                <input v-model="form.code" class="mp-input" placeholder="Code" required />
                <input v-model="form.phone" class="mp-input" placeholder="Téléphone" />
                <input v-model="form.email" class="mp-input" placeholder="Email" />
                <input v-model="form.address" class="mp-input" placeholder="Adresse" />
                <input v-model="form.payment_terms" class="mp-input" placeholder="Conditions" />
                <button class="mp-btn mp-btn-primary w-full" :disabled="form.processing">Enregistrer</button>
            </form>

            <div class="lg:col-span-8">
                <div class="mb-4 flex gap-2">
                    <input v-model="q" class="mp-input" placeholder="Rechercher…" @keyup.enter="search" />
                    <button class="mp-btn mp-btn-ghost" type="button" @click="search">Filtrer</button>
                </div>
                <div v-for="s in suppliers.data" :key="s.id" class="mp-row">
                    <div>
                        <div class="font-semibold">{{ s.name }}</div>
                        <div class="text-xs text-[color:var(--mp-muted)]">{{ s.code }} · {{ s.phone || '—' }}</div>
                    </div>
                    <div class="text-xs text-[color:var(--mp-faint)]">{{ s.payment_terms || '' }}</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

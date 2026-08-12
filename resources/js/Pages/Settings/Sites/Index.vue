<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    sites: Array<{
        id: string;
        name: string;
        code: string;
        address: string | null;
        is_main: boolean;
        warehouses: Array<{ id: string; name: string; code: string }>;
    }>;
}>();

const form = useForm({
    name: '',
    code: '',
    address: '',
    warehouse_name: 'Réserve',
    warehouse_code: '',
});

const submit = () => form.post(route('settings.sites.store'), { onSuccess: () => form.reset() });
</script>

<template>
    <Head title="Sites" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Organisation</p>
                <h1 class="mp-display mt-1 text-4xl">Sites & entrepôts</h1>
            </div>
        </template>

        <div class="grid gap-10 xl:grid-cols-2">
            <div>
                <div v-for="site in sites" :key="site.id" class="mp-row">
                    <div>
                        <div class="flex items-center gap-2">
                            <div class="font-medium">{{ site.name }}</div>
                            <span v-if="site.is_main" class="mp-badge mp-badge-ok">Principal</span>
                        </div>
                        <div class="text-xs text-[color:var(--mp-muted)]">{{ site.code }} · {{ site.address }}</div>
                        <div class="mt-2 text-sm text-[color:var(--mp-faint)]">
                            <span v-for="wh in site.warehouses" :key="wh.id" class="mr-3">{{ wh.name }} ({{ wh.code }})</span>
                        </div>
                    </div>
                </div>
            </div>

            <form class="space-y-3 border p-5" style="border-color: var(--mp-line)" @submit.prevent="submit">
                <h2 class="mp-section-title">Nouveau site</h2>
                <input v-model="form.name" class="mp-input" placeholder="Nom du site" required />
                <input v-model="form.code" class="mp-input" placeholder="Code" required />
                <input v-model="form.address" class="mp-input" placeholder="Adresse" />
                <input v-model="form.warehouse_name" class="mp-input" placeholder="Entrepôt initial" required />
                <input v-model="form.warehouse_code" class="mp-input" placeholder="Code entrepôt" required />
                <button class="mp-btn mp-btn-primary" :disabled="form.processing">Créer le site</button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

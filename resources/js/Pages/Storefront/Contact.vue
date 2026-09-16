<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    pharmacy: {
        name: string;
        city: string;
        country: string;
        phone: string;
        email: string;
        address: string;
        hours: string;
    };
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props.flash as { success?: string } | undefined)?.success);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    message: '',
});

const submit = () => {
    form.post(route('storefront.contact.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('message'),
    });
};
</script>

<template>
    <StorefrontLayout title="Contact">
        <section class="mx-auto grid max-w-6xl gap-12 px-5 py-14 lg:grid-cols-2 lg:px-8">
            <div>
                <h1 class="mp-display text-4xl">Contact</h1>
                <p class="mt-4 text-sm leading-relaxed text-[color:var(--mp-muted)]">
                    {{ pharmacy.name }} — {{ pharmacy.city }}, {{ pharmacy.country }}.
                    Les commandes en ligne arriveront plus tard ; pour un besoin urgent, passez à l’officine.
                </p>
                <dl class="mt-8 space-y-4 text-sm">
                    <div>
                        <dt class="mp-metric-label">Adresse</dt>
                        <dd>{{ pharmacy.address }}</dd>
                    </div>
                    <div>
                        <dt class="mp-metric-label">Horaires</dt>
                        <dd>{{ pharmacy.hours }}</dd>
                    </div>
                    <div v-if="pharmacy.phone">
                        <dt class="mp-metric-label">Téléphone</dt>
                        <dd>{{ pharmacy.phone }}</dd>
                    </div>
                    <div v-if="pharmacy.email">
                        <dt class="mp-metric-label">E-mail</dt>
                        <dd>{{ pharmacy.email }}</dd>
                    </div>
                </dl>
            </div>

            <form class="space-y-4 border p-6" style="border-color: var(--mp-line)" @submit.prevent="submit">
                <p v-if="flashSuccess" class="text-sm" style="color: var(--mp-success)">{{ flashSuccess }}</p>
                <div>
                    <label class="mb-1 block text-sm" for="name">Nom</label>
                    <input
                        id="name"
                        v-model="form.name"
                        required
                        maxlength="255"
                        class="w-full border px-3 py-2.5 text-sm"
                        style="border-color: var(--mp-line); background: transparent"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>
                <div>
                    <label class="mb-1 block text-sm" for="email">E-mail</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        maxlength="255"
                        class="w-full border px-3 py-2.5 text-sm"
                        style="border-color: var(--mp-line); background: transparent"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
                <div>
                    <label class="mb-1 block text-sm" for="phone">Téléphone</label>
                    <input
                        id="phone"
                        v-model="form.phone"
                        maxlength="40"
                        class="w-full border px-3 py-2.5 text-sm"
                        style="border-color: var(--mp-line); background: transparent"
                    />
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>
                <div>
                    <label class="mb-1 block text-sm" for="message">Message</label>
                    <textarea
                        id="message"
                        v-model="form.message"
                        required
                        rows="5"
                        maxlength="2000"
                        class="w-full border px-3 py-2.5 text-sm"
                        style="border-color: var(--mp-line); background: transparent"
                    />
                    <InputError class="mt-2" :message="form.errors.message" />
                </div>
                <button class="mp-btn mp-btn-primary" type="submit" :disabled="form.processing">Envoyer</button>
            </form>
        </section>
    </StorefrontLayout>
</template>

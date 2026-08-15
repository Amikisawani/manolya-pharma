<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    defaults: {
        name: string;
        email: string;
        pharmacy_name: string;
        site_name: string;
        site_code: string;
    };
}>();

const form = useForm({
    name: props.defaults.name,
    email: props.defaults.email,
    password: '',
    password_confirmation: '',
    pharmacy_name: props.defaults.pharmacy_name,
    site_name: props.defaults.site_name,
    site_code: props.defaults.site_code,
});

const submit = () => form.post(route('setup.store'));
</script>

<template>
    <GuestLayout>
        <Head title="Installation" />

        <h1 class="mp-display text-3xl">Installation plateforme</h1>
        <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
            Crée le super administrateur (espace /admin) et une officine vierge.
        </p>

        <form class="mt-8 space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="pharmacy_name" value="Nom de la pharmacie" />
                <TextInput id="pharmacy_name" v-model="form.pharmacy_name" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="form.errors.pharmacy_name" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="site_name" value="Site" />
                    <TextInput id="site_name" v-model="form.site_name" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.site_name" />
                </div>
                <div>
                    <InputLabel for="site_code" value="Code site" />
                    <TextInput id="site_code" v-model="form.site_code" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.site_code" />
                </div>
            </div>
            <div>
                <InputLabel for="name" value="Votre nom" />
                <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>
            <div>
                <InputLabel for="password" value="Mot de passe" />
                <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <div>
                <InputLabel for="password_confirmation" value="Confirmer le mot de passe" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    required
                />
            </div>
            <button class="mp-btn mp-btn-primary w-full" :disabled="form.processing">Créer le super admin</button>
        </form>
    </GuestLayout>
</template>

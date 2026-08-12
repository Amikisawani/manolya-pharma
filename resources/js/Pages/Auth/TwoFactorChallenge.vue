<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    setup?: boolean;
    otpAuthUrl?: string;
    secret?: string;
    recoveryCodes?: string[];
}>();

const form = useForm({
    code: '',
});

const submit = () => {
    if (props.setup) {
        form.post(route('two-factor.enable'));
    } else {
        form.post(route('two-factor.verify'));
    }
};
</script>

<template>
    <GuestLayout>
        <Head :title="setup ? 'Configurer 2FA' : 'Vérification 2FA'" />

        <div class="mb-6 text-center">
            <div class="mp-display text-3xl text-[color:var(--mp-ink)]">Manolya Pharma</div>
            <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
                {{ setup ? 'Activez l’authentification à deux facteurs' : 'Saisissez le code de votre application d’authentification' }}
            </p>
        </div>

        <div v-if="setup && secret" class="mb-4 rounded-xl border p-4 text-sm" style="border-color: var(--mp-border)">
            <p class="font-medium">Secret : {{ secret }}</p>
            <p class="mt-2 break-all text-xs text-[color:var(--mp-muted)]">{{ otpAuthUrl }}</p>
            <ul v-if="recoveryCodes?.length" class="mt-3 grid grid-cols-2 gap-1 text-xs">
                <li v-for="code in recoveryCodes" :key="code">{{ code }}</li>
            </ul>
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="code" value="Code 2FA" />
                <TextInput id="code" type="text" class="mt-1 block w-full" v-model="form.code" required autofocus autocomplete="one-time-code" />
                <InputError class="mt-2" :message="form.errors.code" />
            </div>

            <div class="mt-4 flex justify-end">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ setup ? 'Activer' : 'Vérifier' }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>

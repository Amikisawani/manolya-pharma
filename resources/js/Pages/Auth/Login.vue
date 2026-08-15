<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
    activeSession?: { name?: string; email?: string; context?: string } | null;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Connexion" />

        <h1 class="mp-display text-3xl">Connexion</h1>
        <p class="mt-2 text-sm text-[color:var(--mp-muted)]">Accédez à votre pharmacie</p>

        <div
            v-if="activeSession"
            class="mt-4 border px-3 py-2 text-sm text-[color:var(--mp-muted)]"
            style="border-color: var(--mp-line)"
        >
            Session ouverte : {{ activeSession.name }} ({{ activeSession.context }}).
            Se connecter ici remplacera cette session.
        </div>

        <div v-if="status" class="mt-4 text-sm" style="color: var(--mp-success)">{{ status }}</div>

        <form class="mt-8 space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Mot de passe" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <label class="flex items-center gap-2 text-sm text-[color:var(--mp-muted)]">
                <Checkbox v-model:checked="form.remember" name="remember" />
                Se souvenir de moi
            </label>

            <div class="flex items-center justify-between gap-3 pt-2">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-[color:var(--mp-accent)]"
                >
                    Mot de passe oublié ?
                </Link>
                <button class="mp-btn mp-btn-primary" :disabled="form.processing">Se connecter</button>
            </div>
        </form>
    </GuestLayout>
</template>

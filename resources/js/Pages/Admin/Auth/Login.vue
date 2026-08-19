<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    activeSession?: { name?: string; email?: string; context?: string } | null;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('admin.login.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Admin — Connexion" />

    <div class="flex min-h-screen items-center justify-center px-4" style="background: #101412; color: #e8f5ef">
        <div class="w-full max-w-md border p-8" style="border-color: #24302a; background: #141a17">
            <div class="mp-display text-3xl text-[color:var(--mp-sidebar-active)]">Manolya</div>
            <p class="mt-2 text-xs font-semibold uppercase tracking-[0.28em]" style="color: #7a857e">
                Super administration
            </p>
            <p class="mt-4 text-sm" style="color: #9aaba2">
                Espace plateforme — hors application pharmacie.
                Comptes caisse / owner :
                <a href="/login" class="underline" style="color: #a8d5c0">/login</a>
            </p>
            <div
                v-if="activeSession"
                class="mt-4 border px-3 py-2 text-sm"
                style="border-color: #3a463f; color: #9aaba2"
            >
                Session ouverte : {{ activeSession.name }} ({{ activeSession.context }}).
                Se connecter ici remplacera cette session.
            </div>

            <form class="mt-8 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm" for="email">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        class="w-full border px-3 py-2.5 text-sm"
                        style="border-color: #3a463f; background: #0e1311; color: #e8f5ef"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
                <div>
                    <label class="mb-1 block text-sm" for="password">Mot de passe</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full border px-3 py-2.5 text-sm"
                        style="border-color: #3a463f; background: #0e1311; color: #e8f5ef"
                    />
                </div>
                <label class="flex items-center gap-2 text-sm" style="color: #9aaba2">
                    <input v-model="form.remember" type="checkbox" />
                    Se souvenir de moi
                </label>
                <button
                    type="submit"
                    class="w-full px-4 py-2.5 text-sm font-semibold"
                    style="background: #1f6b4a; color: #f7f4ef"
                    :disabled="form.processing"
                >
                    Connexion admin
                </button>
            </form>
        </div>
    </div>
</template>

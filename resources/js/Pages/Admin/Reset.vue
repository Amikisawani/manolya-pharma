<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    owner: { name: string; email: string };
    pharmacy_name: string;
}>();

const form = useForm({
    confirmation: '',
    password: '',
    name: props.owner.name,
    email: props.owner.email,
    new_password: '',
    new_password_confirmation: '',
    pharmacy_name: props.pharmacy_name,
});

const submit = () => {
    if (!confirm('Confirmer la remise à zéro ? Toutes les données métier seront effacées.')) {
        return;
    }
    form.delete(route('admin.reset.destroy'));
};
</script>

<template>
    <Head title="Réinitialiser" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Admin</p>
                <h1 class="mp-display mt-1 text-4xl">Réinitialiser l’appli</h1>
                <p class="mt-2 max-w-xl text-sm text-[color:var(--mp-muted)]">
                    Efface ventes, stock, documents, comptes… puis recrée une officine vierge avec le propriétaire ci-dessous.
                </p>
            </div>
        </template>

        <form class="max-w-lg space-y-4 border p-6" style="border-color: var(--mp-danger)" @submit.prevent="submit">
            <div>
                <InputLabel for="pharmacy_name" value="Nom pharmacie après reset" />
                <TextInput id="pharmacy_name" v-model="form.pharmacy_name" class="mt-1 block w-full" required />
            </div>
            <div>
                <InputLabel for="name" value="Nom owner" />
                <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
            </div>
            <div>
                <InputLabel for="email" value="Email owner" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
            </div>
            <div>
                <InputLabel for="new_password" value="Nouveau mot de passe owner" />
                <TextInput id="new_password" v-model="form.new_password" type="password" class="mt-1 block w-full" required />
                <InputError :message="form.errors.new_password" />
            </div>
            <div>
                <InputLabel for="new_password_confirmation" value="Confirmer" />
                <TextInput
                    id="new_password_confirmation"
                    v-model="form.new_password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    required
                />
            </div>
            <div>
                <InputLabel for="password" value="Votre mot de passe actuel" />
                <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required />
                <InputError :message="form.errors.password" />
            </div>
            <div>
                <InputLabel for="confirmation" value='Tapez REINITIALISER' />
                <TextInput id="confirmation" v-model="form.confirmation" class="mt-1 block w-full" required />
                <InputError :message="form.errors.confirmation" />
            </div>
            <button class="mp-btn w-full" style="background: var(--mp-danger); color: #fff" :disabled="form.processing">
                Remettre à zéro
            </button>
        </form>
    </AuthenticatedLayout>
</template>

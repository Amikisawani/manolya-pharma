<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    admin: { name: string; email: string };
    pharmacy_name: string;
}>();

const form = useForm({
    confirmation: '',
    password: '',
    name: props.admin.name,
    email: props.admin.email,
    new_password: '',
    new_password_confirmation: '',
    pharmacy_name: props.pharmacy_name,
});

const submit = () => {
    if (!confirm('Effacer toutes les données test de la pharmacie ?')) return;
    form.delete(route('admin.reset.destroy'));
};
</script>

<template>
    <Head title="Admin — Remise à zéro" />
    <AdminLayout>
        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em]" style="color: #7a857e">Danger</p>
        <h1 class="mp-display mt-1 text-4xl">Remise à zéro</h1>
        <p class="mt-2 max-w-xl text-sm" style="color: #9aaba2">
            Supprime ventes, stock, documents et comptes pharmacie. Recrée une officine vierge et le super admin.
        </p>

        <form class="mt-8 max-w-lg space-y-4 border p-6" style="border-color: #b42318" @submit.prevent="submit">
            <input v-model="form.pharmacy_name" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Nom pharmacie" required />
            <input v-model="form.name" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Nom super admin" required />
            <input v-model="form.email" type="email" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Email super admin" required />
            <input v-model="form.new_password" type="password" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Nouveau mot de passe admin" required />
            <InputError :message="form.errors.new_password" />
            <input v-model="form.new_password_confirmation" type="password" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Confirmer" required />
            <input v-model="form.password" type="password" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Mot de passe actuel" required />
            <InputError :message="form.errors.password" />
            <input v-model="form.confirmation" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Tapez REINITIALISER" required />
            <InputError :message="form.errors.confirmation" />
            <button class="w-full px-4 py-2.5 text-sm font-semibold" style="background: #b42318; color: #fff" :disabled="form.processing">
                Remettre à zéro
            </button>
        </form>
    </AdminLayout>
</template>

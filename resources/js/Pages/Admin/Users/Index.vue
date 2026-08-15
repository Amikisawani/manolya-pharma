<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    tenant: { id: string; name: string };
    users: Array<{
        id: string;
        name: string;
        email: string;
        phone: string | null;
        is_active: boolean;
        role: string | null;
    }>;
    roles: string[];
}>();

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    phone: '',
    role: 'cashier',
});

const editingId = ref<string | null>(null);
const editForm = useForm({
    name: '',
    email: '',
    phone: '',
    role: 'cashier',
    is_active: true,
    password: '',
});

const submitCreate = () =>
    createForm.post(route('admin.users.store'), {
        onSuccess: () => createForm.reset('name', 'email', 'password', 'phone'),
    });

const startEdit = (user: (typeof props.users)[number]) => {
    editingId.value = user.id;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.phone = user.phone ?? '';
    editForm.role = user.role ?? 'cashier';
    editForm.is_active = user.is_active;
    editForm.password = '';
};

const submitEdit = () => {
    if (!editingId.value) return;
    editForm.put(route('admin.users.update', editingId.value), {
        onSuccess: () => {
            editingId.value = null;
        },
    });
};

const deactivate = (id: string) => {
    if (!confirm('Désactiver ce compte pharmacie ?')) return;
    router.delete(route('admin.users.destroy', id));
};

const roleLabel = (role: string | null) => {
    const map: Record<string, string> = {
        owner: 'Propriétaire',
        pharmacist: 'Pharmacien',
        stock_manager: 'Stock',
        cashier: 'Caissier',
        accountant: 'Comptable',
        auditor: 'Auditeur',
    };
    return role ? map[role] ?? role : '—';
};
</script>

<template>
    <Head title="Admin — Comptes" />
    <AdminLayout>
        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em]" style="color: #7a857e">Comptes</p>
        <h1 class="mp-display mt-1 text-4xl">Utilisateurs pharmacie</h1>
        <p class="mt-2 text-sm" style="color: #9aaba2">
            Officine : {{ tenant.name }}. Ces comptes se connectent sur /login (pas ici).
        </p>

        <div class="mt-10 grid gap-10 xl:grid-cols-2">
            <div>
                <div
                    v-for="user in users"
                    :key="user.id"
                    class="flex items-center justify-between gap-3 border-b py-4"
                    style="border-color: #24302a"
                >
                    <div class="min-w-0">
                        <div class="font-medium">{{ user.name }}</div>
                        <div class="text-xs" style="color: #9aaba2">{{ user.email }}</div>
                        <div class="mt-1 text-xs" style="color: #7a857e">
                            {{ roleLabel(user.role) }} · {{ user.is_active ? 'Actif' : 'Inactif' }}
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" class="border px-3 py-1.5 text-xs" style="border-color: #3a463f" @click="startEdit(user)">
                            Modifier
                        </button>
                        <button type="button" class="border px-3 py-1.5 text-xs" style="border-color: #b42318; color: #f0c4c0" @click="deactivate(user.id)">
                            Désactiver
                        </button>
                    </div>
                </div>
                <p v-if="users.length === 0" class="py-6 text-sm" style="color: #7a857e">Aucun compte pharmacie — créez un propriétaire.</p>

                <form
                    v-if="editingId"
                    class="mt-6 space-y-3 border p-5"
                    style="border-color: #24302a"
                    @submit.prevent="submitEdit"
                >
                    <h2 class="text-lg font-semibold">Modifier</h2>
                    <input v-model="editForm.name" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" required />
                    <input v-model="editForm.email" type="email" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" required />
                    <input v-model="editForm.phone" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Téléphone" />
                    <select v-model="editForm.role" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311">
                        <option v-for="role in roles" :key="role" :value="role">{{ roleLabel(role) }}</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="editForm.is_active" type="checkbox" />
                        Actif
                    </label>
                    <input v-model="editForm.password" type="password" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Nouveau mot de passe (optionnel)" />
                    <InputError :message="editForm.errors.email || editForm.errors.password" />
                    <div class="flex gap-2">
                        <button class="px-4 py-2 text-sm font-semibold" style="background: #1f6b4a" :disabled="editForm.processing">Enregistrer</button>
                        <button type="button" class="border px-4 py-2 text-sm" style="border-color: #3a463f" @click="editingId = null">Annuler</button>
                    </div>
                </form>
            </div>

            <form class="space-y-3 border p-5" style="border-color: #24302a" @submit.prevent="submitCreate">
                <h2 class="text-lg font-semibold">Nouveau compte</h2>
                <input v-model="createForm.name" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Nom" required />
                <input v-model="createForm.email" type="email" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Email" required />
                <InputError :message="createForm.errors.email" />
                <input v-model="createForm.password" type="password" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Mot de passe" required />
                <InputError :message="createForm.errors.password" />
                <input v-model="createForm.phone" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311" placeholder="Téléphone" />
                <select v-model="createForm.role" class="w-full border px-3 py-2 text-sm" style="border-color: #3a463f; background: #0e1311">
                    <option v-for="role in roles" :key="role" :value="role">{{ roleLabel(role) }}</option>
                </select>
                <button class="w-full px-4 py-2.5 text-sm font-semibold" style="background: #1f6b4a; color: #f7f4ef" :disabled="createForm.processing">
                    Créer
                </button>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
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
    if (!confirm('Désactiver ce compte ?')) return;
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
    <Head title="Administration" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-[color:var(--mp-faint)]">Admin</p>
                <h1 class="mp-display mt-1 text-4xl">Comptes utilisateurs</h1>
                <p class="mt-2 text-sm text-[color:var(--mp-muted)]">
                    Créez les accès de votre équipe. L’appli peut rester vierge : pas de données démo obligatoires.
                </p>
            </div>
        </template>

        <div class="grid gap-10 xl:grid-cols-2">
            <div>
                <div v-for="user in users" :key="user.id" class="mp-row items-center">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ user.name }}</div>
                        <div class="text-xs text-[color:var(--mp-muted)]">{{ user.email }}</div>
                        <div class="mt-1 flex flex-wrap gap-2 text-xs">
                            <span class="mp-badge">{{ roleLabel(user.role) }}</span>
                            <span :class="user.is_active ? 'mp-badge mp-badge-ok' : 'mp-badge mp-badge-danger'">
                                {{ user.is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" class="mp-btn mp-btn-ghost" @click="startEdit(user)">Modifier</button>
                        <button type="button" class="mp-btn mp-btn-ghost" @click="deactivate(user.id)">Désactiver</button>
                    </div>
                </div>

                <form
                    v-if="editingId"
                    class="mt-6 space-y-3 border p-5"
                    style="border-color: var(--mp-line)"
                    @submit.prevent="submitEdit"
                >
                    <h2 class="mp-section-title">Modifier le compte</h2>
                    <input v-model="editForm.name" class="mp-input" placeholder="Nom" required />
                    <input v-model="editForm.email" class="mp-input" type="email" placeholder="Email" required />
                    <input v-model="editForm.phone" class="mp-input" placeholder="Téléphone" />
                    <select v-model="editForm.role" class="mp-input">
                        <option v-for="role in roles" :key="role" :value="role">{{ roleLabel(role) }}</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="editForm.is_active" type="checkbox" />
                        Compte actif
                    </label>
                    <input
                        v-model="editForm.password"
                        class="mp-input"
                        type="password"
                        placeholder="Nouveau mot de passe (optionnel)"
                    />
                    <InputError :message="editForm.errors.email || editForm.errors.password" />
                    <div class="flex gap-2">
                        <button class="mp-btn mp-btn-primary" :disabled="editForm.processing">Enregistrer</button>
                        <button type="button" class="mp-btn mp-btn-ghost" @click="editingId = null">Annuler</button>
                    </div>
                </form>
            </div>

            <form class="space-y-3 border p-5" style="border-color: var(--mp-line)" @submit.prevent="submitCreate">
                <h2 class="mp-section-title">Nouveau compte</h2>
                <input v-model="createForm.name" class="mp-input" placeholder="Nom" required />
                <InputError :message="createForm.errors.name" />
                <input v-model="createForm.email" class="mp-input" type="email" placeholder="Email" required />
                <InputError :message="createForm.errors.email" />
                <input v-model="createForm.password" class="mp-input" type="password" placeholder="Mot de passe" required />
                <InputError :message="createForm.errors.password" />
                <input v-model="createForm.phone" class="mp-input" placeholder="Téléphone" />
                <select v-model="createForm.role" class="mp-input">
                    <option v-for="role in roles" :key="role" :value="role">{{ roleLabel(role) }}</option>
                </select>
                <button class="mp-btn mp-btn-primary" :disabled="createForm.processing">Créer le compte</button>
            </form>
        </div>

        <div class="mt-12 border-t pt-8" style="border-color: var(--mp-line)">
            <Link :href="route('admin.reset.edit')" class="mp-btn mp-btn-ghost">Réinitialiser l’application →</Link>
        </div>
    </AuthenticatedLayout>
</template>

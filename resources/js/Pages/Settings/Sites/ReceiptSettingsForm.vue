<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    site: {
        id: string;
        name: string;
        address: string | null;
        phone: string | null;
        email: string | null;
        legal_rccm: string | null;
        legal_id_nat: string | null;
        legal_nif: string | null;
        logo_path: string | null;
        receipt_footer: string | null;
        receipt_return_policy: string | null;
        receipt_auto_print: boolean;
        receipt_show_qr: boolean;
    };
}>();

const logo = ref<File | null>(null);

const form = useForm({
    name: props.site.name,
    address: props.site.address ?? '',
    phone: props.site.phone ?? '',
    email: props.site.email ?? '',
    legal_rccm: props.site.legal_rccm ?? '',
    legal_id_nat: props.site.legal_id_nat ?? '',
    legal_nif: props.site.legal_nif ?? '',
    receipt_footer: props.site.receipt_footer ?? '',
    receipt_return_policy: props.site.receipt_return_policy ?? '',
    receipt_auto_print: props.site.receipt_auto_print ? '1' : '0',
    receipt_show_qr: props.site.receipt_show_qr ? '1' : '0',
    logo: null as File | null,
    remove_logo: false,
});

const onLogo = (event: Event) => {
    const input = event.target as HTMLInputElement;
    logo.value = input.files?.[0] ?? null;
    form.logo = logo.value;
};

const save = () => {
    form.post(route('settings.sites.update', props.site.id), {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <form class="mt-4 space-y-3 border-t pt-4" style="border-color: var(--mp-line)" @submit.prevent="save">
        <h3 class="text-sm font-semibold">Ticket de caisse 80 mm</h3>
        <input v-model="form.name" class="mp-input" placeholder="Nom affiché" required />
        <input v-model="form.address" class="mp-input" placeholder="Adresse" />
        <div class="grid gap-3 sm:grid-cols-2">
            <input v-model="form.phone" class="mp-input" placeholder="Téléphone" />
            <input v-model="form.email" class="mp-input" type="email" placeholder="E-mail" />
        </div>
        <input v-model="form.legal_rccm" class="mp-input" placeholder="RCCM" />
        <input v-model="form.legal_id_nat" class="mp-input" placeholder="ID Nat" />
        <input v-model="form.legal_nif" class="mp-input" placeholder="NIF" />
        <textarea v-model="form.receipt_footer" class="mp-input" rows="2" placeholder="Message de pied de ticket" />
        <textarea v-model="form.receipt_return_policy" class="mp-input" rows="2" placeholder="Conditions de retour (laissé vide = masqué)" />
        <div>
            <label class="mp-metric-label">Impression automatique des tickets</label>
            <select v-model="form.receipt_auto_print" class="mp-input mt-1">
                <option value="0">Non</option>
                <option value="1">Oui</option>
            </select>
        </div>
        <div>
            <label class="mp-metric-label">QR code sur le ticket</label>
            <select v-model="form.receipt_show_qr" class="mp-input mt-1">
                <option value="1">Oui</option>
                <option value="0">Non</option>
            </select>
        </div>
        <div>
            <label class="mp-metric-label">Logo (optionnel, N&B à l’impression)</label>
            <input class="mp-input mt-1" type="file" accept="image/*" @change="onLogo" />
            <p v-if="site.logo_path" class="mt-1 text-xs text-[color:var(--mp-muted)]">Un logo est déjà enregistré.</p>
            <label v-if="site.logo_path" class="mt-2 flex items-center gap-2 text-sm">
                <input v-model="form.remove_logo" type="checkbox" />
                Retirer le logo
            </label>
        </div>
        <p v-if="form.errors.name" class="text-xs text-[color:var(--mp-danger)]">{{ form.errors.name }}</p>
        <button class="mp-btn mp-btn-ghost" :disabled="form.processing">Enregistrer le ticket</button>
    </form>
</template>

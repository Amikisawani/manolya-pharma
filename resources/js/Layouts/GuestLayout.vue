<script setup lang="ts">
import BrandLockup from '@/Components/BrandLockup.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col justify-between px-6 py-10 lg:px-16">
        <div>
            <Link href="/" class="inline-block">
                <BrandLockup size="lg" />
            </Link>
            <p class="mt-8 max-w-md text-sm leading-relaxed text-[color:var(--mp-muted)]">
                Gestion d’officine claire et traçable.
            </p>
        </div>

        <div class="mx-auto w-full max-w-md border-t pt-8" style="border-color: var(--mp-line)">
            <slot />
        </div>

        <div class="pt-10 text-xs text-[color:var(--mp-faint)]">
            Devise affichée en Fc · équivalents USD / EUR
        </div>
    </div>
</template>

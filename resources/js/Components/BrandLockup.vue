<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'light' | 'dark';
        subtitle?: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        variant: 'light',
        subtitle: 'Pharma',
        size: 'md',
    },
);
</script>

<template>
    <span class="inline-flex items-center" :class="size === 'lg' ? 'gap-3.5' : size === 'sm' ? 'gap-2' : 'gap-3'">
        <img
            :src="variant === 'dark' ? '/brand/mark-dark.png' : '/brand/mark.png'"
            alt="Manolya Pharma"
            class="shrink-0 rounded-[0.65rem]"
            :class="size === 'lg' ? 'h-14 w-14' : size === 'sm' ? 'h-8 w-8' : 'h-10 w-10'"
        />
        <span class="min-w-0 text-left">
            <span
                class="mp-display block leading-none"
                :class="size === 'lg' ? 'text-4xl' : size === 'sm' ? 'text-lg' : 'text-[1.65rem]'"
            >
                Manolya
            </span>
            <span
                class="mt-1 block font-semibold uppercase"
                :class="[
                    size === 'lg' ? 'text-[0.7rem] tracking-[0.32em]' : 'text-[0.65rem] tracking-[0.28em]',
                    variant === 'dark' ? '' : 'text-[color:var(--mp-faint)]',
                ]"
                :style="variant === 'dark' ? { color: '#7a857e' } : undefined"
            >
                {{ subtitle }}
            </span>
        </span>
    </span>
</template>

<script setup lang="ts">
import { useMoney } from '@/Composables/useMoney';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        amount: string | number | null | undefined;
        size?: 'sm' | 'md' | 'lg' | 'xl';
        align?: 'left' | 'right';
        showFx?: boolean;
    }>(),
    {
        size: 'md',
        align: 'left',
        showFx: true,
    },
);

const { formatFc, equivalents } = useMoney();

const primaryClass = computed(() => {
    const map = {
        sm: 'text-sm font-medium',
        md: 'text-base font-semibold',
        lg: 'text-2xl font-semibold tracking-tight',
        xl: 'text-3xl font-semibold tracking-tight',
    };
    return map[props.size];
});
</script>

<template>
    <div :class="['mp-money', align === 'right' ? 'text-right' : 'text-left']">
        <div :class="['mp-money-primary', primaryClass]">{{ formatFc(amount) }}</div>
        <div v-if="showFx" class="mp-money-fx">{{ equivalents(amount) }}</div>
    </div>
</template>

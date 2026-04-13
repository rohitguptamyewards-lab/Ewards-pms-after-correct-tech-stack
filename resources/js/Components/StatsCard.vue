<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: String,
    value: [String, Number],
    color: { type: String, default: 'blue' },
    icon: { type: String, default: '' },
});

const palette = {
    blue: { bg: 'bg-gradient-to-br from-[#f5f0ff] to-[#ebe0ff]', border: 'border-[#ddd0f7]', text: 'text-[#4e1a77]', iconBg: 'bg-[#4e1a77]/10', iconText: 'text-[#4e1a77]' },
    green: { bg: 'bg-gradient-to-br from-green-50 to-emerald-50', border: 'border-green-100', text: 'text-green-700', iconBg: 'bg-green-100', iconText: 'text-green-600' },
    red: { bg: 'bg-gradient-to-br from-red-50 to-rose-50', border: 'border-red-100', text: 'text-red-700', iconBg: 'bg-red-100', iconText: 'text-red-600' },
    yellow: { bg: 'bg-gradient-to-br from-yellow-50 to-amber-50', border: 'border-yellow-100', text: 'text-yellow-700', iconBg: 'bg-yellow-100', iconText: 'text-yellow-600' },
    gray: { bg: 'bg-gradient-to-br from-gray-50 to-slate-50', border: 'border-gray-100', text: 'text-gray-700', iconBg: 'bg-gray-100', iconText: 'text-gray-600' },
};

const p = computed(() => palette[props.color] || palette.blue);
</script>

<template>
    <div :class="[p.bg, p.border]" class="rounded-xl border px-4 py-4 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ label }}</p>
                <p :class="p.text" class="mt-1.5 text-2xl font-bold tracking-tight">{{ value }}</p>
            </div>
            <div v-if="icon" :class="[p.iconBg, p.iconText]" class="flex h-10 w-10 items-center justify-center rounded-lg">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="icon" />
                </svg>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    priority: { type: String, default: 'medium' },
});

const config = {
    critical: { classes: 'bg-red-100 text-red-700 ring-1 ring-red-200', dot: 'bg-red-500', label: 'Critical' },
    high: { classes: 'bg-orange-100 text-orange-700 ring-1 ring-orange-200', dot: 'bg-orange-500', label: 'High' },
    medium: { classes: 'bg-yellow-100 text-yellow-700 ring-1 ring-yellow-200', dot: 'bg-yellow-500', label: 'Medium' },
    low: { classes: 'bg-slate-100 text-slate-600 ring-1 ring-slate-200', dot: 'bg-slate-400', label: 'Low' },
};

const c = computed(() => config[props.priority] || config.medium);
</script>

<template>
    <span :class="c.classes" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium">
        <span :class="c.dot" class="h-1.5 w-1.5 rounded-full" />
        {{ c.label }}
    </span>
</template>

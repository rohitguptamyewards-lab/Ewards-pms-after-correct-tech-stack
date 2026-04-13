<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, default: '' },
    type: { type: String, default: 'project' },
});

const colorMap = {
    project: {
        active: 'bg-green-100 text-green-700 ring-1 ring-green-200',
        completed: 'bg-blue-100 text-blue-700 ring-1 ring-blue-200',
        on_hold: 'bg-yellow-100 text-yellow-700 ring-1 ring-yellow-200',
    },
    planner: {
        pending: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        in_progress: 'bg-[#e8ddf0] text-[#4e1a77] ring-1 ring-purple-200',
        done: 'bg-green-100 text-green-700 ring-1 ring-green-200',
        blocked: 'bg-red-100 text-red-700 ring-1 ring-red-200',
    },
    blocker: {
        active: 'bg-red-100 text-red-700 ring-1 ring-red-200',
        resolved: 'bg-green-100 text-green-700 ring-1 ring-green-200',
    },
};

const classes = computed(() => {
    const map = colorMap[props.type] || colorMap.project;
    return map[props.status] || 'bg-gray-100 text-gray-600 ring-1 ring-gray-200';
});

const label = computed(() =>
    props.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'Unknown'
);
</script>

<template>
    <span :class="classes" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
        {{ label }}
    </span>
</template>

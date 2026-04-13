<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    projects: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.role);
const canCreate = computed(() => ['manager', 'analyst_head', 'analyst'].includes(role.value));

// ── Kanban Column Definitions ──────────────────────────
const columns = [
    {
        key: 'yet_to_start',
        label: 'Yet to Start',
        color: '#6b7280',
        defaultStage: 'doc_yet_to_start',
        stages: [
            'doc_yet_to_start',
            'dev_yet_to_start',
            'figma_yet_to_start',
            'testing_yet_to_start',
            'live_testing_yet_to_start',
        ],
    },
    {
        key: 'documentation',
        label: 'Documentation',
        color: '#3b82f6',
        defaultStage: 'doc_wip',
        stages: [
            'gpt_chat_wip',
            'doc_wip',
            'doc_under_review',
            'doc_changes_required',
        ],
    },
    {
        key: 'development',
        label: 'Development',
        color: '#10b981',
        defaultStage: 'development_wip',
        stages: [
            'figma_design_wip',
            'design_under_review',
            'development_wip',
            'dev_testing_wip',
            'dev_changes_required',
            'bug_fixing_wip',
            'bugs_fixed',
            'vibe_code_shared',
            'rnd',
        ],
    },
    {
        key: 'testing',
        label: 'Testing',
        color: '#f59e0b',
        defaultStage: 'testing_wip',
        stages: [
            'testing_wip',
            'testing_wip_internal',
            'bugs_reported_test_internal',
            'bugs_reported_testing',
            'bugs_reported_internal',
        ],
    },
    {
        key: 'live_testing',
        label: 'Live Testing',
        color: '#8b5cf6',
        defaultStage: 'live_testing_wip',
        stages: [
            'live_testing_wip',
            'bugs_reported_live',
        ],
    },
    {
        key: 'ready_review',
        label: 'Ready/Review',
        color: '#06b6d4',
        defaultStage: 'ready_for_internal',
        stages: [
            'ready_for_internal',
            'ready_to_go_for_live',
        ],
    },
    {
        key: 'live',
        label: 'Live',
        color: '#059669',
        defaultStage: 'live',
        stages: [
            'live',
            'yet_to_announce_on_group',
            'yet_to_put_on_cron',
            'ticket_raised_for_revenue',
        ],
    },
];

// ── Stage label helper ─────────────────────────────────
const stageMap = {
    doc_yet_to_start: 'Doc - Yet to Start',
    gpt_chat_wip: 'GPT Chat - WIP',
    doc_wip: 'Doc - WIP',
    figma_yet_to_start: 'Figma - Yet to Start',
    figma_design_wip: 'Figma Design - WIP',
    doc_under_review: 'Doc - Under Review',
    doc_changes_required: 'Doc Changes Required',
    dev_yet_to_start: 'Dev - Yet to Start',
    development_wip: 'Development - WIP',
    testing_yet_to_start: 'Testing - Yet to Start',
    testing_wip: 'Testing - WIP',
    dev_testing_wip: 'Dev + Testing - WIP',
    ready_for_internal: 'Ready for Internal',
    testing_wip_internal: 'Testing - WIP Internal',
    bugs_reported_test_internal: 'Bugs Reported - Test Internal',
    bugs_reported_testing: 'Bugs Reported + Testing',
    bugs_reported_internal: 'Bugs Reported Internal',
    live_testing_yet_to_start: 'Live Testing - Yet to Start',
    ready_to_go_for_live: 'Ready To Go for LIVE',
    live_testing_wip: 'Live Testing - WIP',
    bugs_reported_live: 'Bugs Reported - Live',
    bug_fixing_wip: 'Bug Fixing - WIP',
    bugs_fixed: 'Bugs Fixed',
    dev_changes_required: 'Dev-Changes Required',
    live: 'LIVE',
    rnd: 'R&D',
    yet_to_announce_on_group: 'Yet to Announce on Group',
    yet_to_put_on_cron: 'Yet to Put on Cron',
    ticket_raised_for_revenue: 'Ticket Raised for Revenue',
    design_under_review: 'Design - Under Review',
    vibe_code_shared: 'Vibe Code Shared',
};

function stageLabel(val) {
    return stageMap[val] || val?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || '';
}

// ── Priority config ────────────────────────────────────
const priorityConfig = {
    critical: { classes: 'bg-red-100 text-red-700', label: 'Critical' },
    high: { classes: 'bg-orange-100 text-orange-700', label: 'High' },
    medium: { classes: 'bg-yellow-100 text-yellow-700', label: 'Medium' },
    low: { classes: 'bg-slate-100 text-slate-600', label: 'Low' },
};

// ── Computed: projects grouped into columns ────────────
const columnData = computed(() => {
    const allProjects = props.projects?.data || [];
    return columns.map(col => {
        const projects = allProjects.filter(p => col.stages.includes(p.current_stage));
        return { ...col, projects };
    });
});

// ── Drag and Drop ──────────────────────────────────────
const draggingId = ref(null);
const dragOverColumn = ref(null);
const updating = ref(false);

function onDragStart(event, projectId) {
    draggingId.value = projectId;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(projectId));
}

function onDragEnd() {
    draggingId.value = null;
    dragOverColumn.value = null;
}

function onDragOver(event, columnKey) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    dragOverColumn.value = columnKey;
}

function onDragLeave(event, columnKey) {
    if (dragOverColumn.value === columnKey) {
        dragOverColumn.value = null;
    }
}

async function onDrop(event, column) {
    event.preventDefault();
    dragOverColumn.value = null;

    const projectId = parseInt(event.dataTransfer.getData('text/plain'));
    if (!projectId || updating.value) return;

    // Check if the project is already in this column
    const allProjects = props.projects?.data || [];
    const project = allProjects.find(p => p.id === projectId);
    if (!project) return;
    if (column.stages.includes(project.current_stage)) {
        draggingId.value = null;
        return;
    }

    updating.value = true;
    try {
        await axios.put(`/api/v1/projects/${projectId}/stage`, {
            stage_name: column.defaultStage,
        });
        router.reload({ only: ['projects'] });
    } catch (e) {
        console.error('Stage update failed', e);
    } finally {
        updating.value = false;
        draggingId.value = null;
    }
}
</script>

<template>
    <Head title="Projects - Board" />

    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-bold text-gray-900">Projects</h1>

                <!-- View Toggle -->
                <div class="flex items-center rounded-lg border border-gray-200 bg-white overflow-hidden">
                    <Link
                        href="/projects"
                        class="px-3 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-50 transition-colors"
                    >
                        <span class="flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M12 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M3.375 12H12m0 0v1.5c0 .621.504 1.125 1.125 1.125M12 12c0 .621-.504 1.125-1.125 1.125m1.125 2.625h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M12 15.75c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 0v1.5" />
                            </svg>
                            Table
                        </span>
                    </Link>
                    <span
                        class="px-3 py-1.5 text-xs font-semibold bg-[#4e1a77] text-white"
                    >
                        <span class="flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                            Board
                        </span>
                    </span>
                </div>
            </div>

            <Link
                v-if="canCreate"
                href="/projects/create"
                class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#3d1560] transition-colors"
            >
                + New Project
            </Link>
        </div>

        <!-- Loading overlay indicator -->
        <div v-if="updating" class="flex items-center gap-2 rounded-lg border border-purple-200 bg-purple-50 px-4 py-2 text-sm text-purple-700">
            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            Updating stage...
        </div>

        <!-- Kanban Board -->
        <div class="overflow-x-auto -mx-4 lg:-mx-6 px-4 lg:px-6 pb-4">
            <div class="flex gap-4" style="min-width: max-content;">
                <!-- Column -->
                <div
                    v-for="col in columnData"
                    :key="col.key"
                    class="flex flex-col rounded-xl bg-gray-100/80 border border-gray-200 transition-colors duration-150"
                    :class="{ 'bg-purple-50/60 border-purple-300': dragOverColumn === col.key }"
                    style="min-width: 280px; max-width: 320px; width: 280px;"
                    @dragover="onDragOver($event, col.key)"
                    @dragleave="onDragLeave($event, col.key)"
                    @drop="onDrop($event, col)"
                >
                    <!-- Column Header -->
                    <div
                        class="flex items-center justify-between px-3 py-2.5 rounded-t-xl border-t-[3px]"
                        :style="{ borderTopColor: col.color }"
                    >
                        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-700">
                            {{ col.label }}
                        </h3>
                        <span
                            class="inline-flex items-center justify-center rounded-full px-2 py-0.5 text-[10px] font-bold text-white"
                            :style="{ backgroundColor: col.color }"
                        >
                            {{ col.projects.length }}
                        </span>
                    </div>

                    <!-- Cards Container -->
                    <div class="flex-1 overflow-y-auto px-2 pb-2 space-y-2" style="max-height: calc(100vh - 220px);">
                        <div v-if="!col.projects.length" class="px-3 py-8 text-center text-xs text-gray-400">
                            No projects
                        </div>

                        <!-- Project Card -->
                        <div
                            v-for="project in col.projects"
                            :key="project.id"
                            class="rounded-lg bg-white border border-gray-200 shadow-sm p-3 cursor-grab hover:shadow-md hover:border-gray-300 transition-all duration-150"
                            :class="{ 'opacity-40 shadow-lg': draggingId === project.id }"
                            draggable="true"
                            @dragstart="onDragStart($event, project.id)"
                            @dragend="onDragEnd"
                        >
                            <!-- Project Name -->
                            <Link
                                :href="`/projects/${project.id}`"
                                class="block text-sm font-semibold text-[#4e1a77] hover:underline leading-snug mb-1.5"
                            >
                                {{ project.name }}
                            </Link>

                            <!-- Owner -->
                            <p v-if="project.owner_name" class="text-xs text-gray-500 mb-2">
                                {{ project.owner_name }}
                            </p>

                            <!-- Badges Row -->
                            <div class="flex flex-wrap items-center gap-1.5 mb-2">
                                <!-- Priority Badge -->
                                <span
                                    v-if="project.priority"
                                    :class="(priorityConfig[project.priority] || priorityConfig.medium).classes"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                >
                                    {{ (priorityConfig[project.priority] || priorityConfig.medium).label }}
                                </span>

                                <!-- Current Stage Badge -->
                                <span
                                    v-if="project.current_stage"
                                    class="inline-flex items-center rounded-full bg-gray-100 text-gray-600 px-2 py-0.5 text-[10px] font-medium truncate max-w-[160px]"
                                    :title="stageLabel(project.current_stage)"
                                >
                                    {{ stageLabel(project.current_stage) }}
                                </span>
                            </div>

                            <!-- Footer: Assignees + Planner Progress -->
                            <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-gray-100">
                                <!-- Assignee Avatars -->
                                <div class="flex -space-x-1.5">
                                    <span
                                        v-if="project.analyst_name"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-[9px] font-bold text-blue-700 ring-1 ring-white"
                                        :title="'Analyst: ' + project.analyst_name"
                                    >
                                        {{ (project.analyst_name || '?').charAt(0).toUpperCase() }}
                                    </span>
                                    <span
                                        v-if="project.developer_name"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-green-100 text-[9px] font-bold text-green-700 ring-1 ring-white"
                                        :title="'Developer: ' + project.developer_name"
                                    >
                                        {{ (project.developer_name || '?').charAt(0).toUpperCase() }}
                                    </span>
                                    <span
                                        v-if="project.analyst_testing_name"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-orange-100 text-[9px] font-bold text-orange-700 ring-1 ring-white"
                                        :title="'Analyst Testing: ' + project.analyst_testing_name"
                                    >
                                        {{ (project.analyst_testing_name || '?').charAt(0).toUpperCase() }}
                                    </span>
                                </div>

                                <!-- Planner Progress -->
                                <div v-if="project.planner_count > 0" class="flex items-center gap-1.5">
                                    <div class="w-12 bg-gray-200 rounded-full h-1">
                                        <div
                                            class="bg-[#4e1a77] h-1 rounded-full transition-all"
                                            :style="{ width: (project.planner_done_count / project.planner_count * 100) + '%' }"
                                        />
                                    </div>
                                    <span class="text-[10px] text-gray-500 font-medium whitespace-nowrap">
                                        {{ project.planner_done_count || 0 }}/{{ project.planner_count }} tasks
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

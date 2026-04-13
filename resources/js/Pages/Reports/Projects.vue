<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PriorityBadge from '@/Components/PriorityBadge.vue';
import StageBadge from '@/Components/StageBadge.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    projects: { type: [Array, Object], default: () => [] },
});

/* ── helpers ── */
function plannerProgressPercent(done, total) {
    if (!total) return 0;
    return Math.round((done / total) * 100);
}

function progressPercent(project) {
    const progress = Number(project?.progress_percent);
    if (Number.isFinite(progress)) {
        return Math.max(0, Math.min(100, Math.round(progress)));
    }

    return plannerProgressPercent(project?.planner_done_count || 0, project?.planner_count || 0);
}

function hoursDisplay(h) {
    h = parseFloat(h) || 0;
    const hrs = Math.floor(h);
    const mins = Math.round((h - hrs) * 60);
    if (hrs === 0 && mins === 0) return '0h';
    if (mins === 0) return `${hrs}h`;
    return `${hrs}h ${mins}m`;
}

/* ── view toggle ── */
const viewMode = ref('table'); // 'table' | 'analytics'

/* ── filters ── */
const search = ref('');
const statusFilter = ref('');
const priorityFilter = ref('');
const workTypeFilter = ref('');
const projectNameFilter = ref('');

const allStatuses = computed(() => {
    const set = new Set();
    (props.projects || []).forEach(p => { if (p.status) set.add(p.status); });
    return [...set].sort();
});

const allPriorities = computed(() => {
    const set = new Set();
    (props.projects || []).forEach(p => { if (p.priority) set.add(p.priority); });
    return [...set].sort();
});

const allWorkTypes = computed(() => {
    const set = new Set();
    (props.projects || []).forEach(p => { if (p.work_type) set.add(p.work_type); });
    return [...set].sort();
});

/* ── sorting ── */
const sortKey = ref('name');
const sortAsc = ref(true);

function toggleSort(key) {
    if (sortKey.value === key) {
        sortAsc.value = !sortAsc.value;
    } else {
        sortKey.value = key;
        sortAsc.value = true;
    }
}

function sortIcon(key) {
    if (sortKey.value !== key) return '↕';
    return sortAsc.value ? '↑' : '↓';
}

/* ── filtered + sorted projects ── */
const filteredProjects = computed(() => {
    let list = [...(props.projects || [])];

    if (search.value) {
        const q = search.value.toLowerCase();
        list = list.filter(p => p.name?.toLowerCase().includes(q) || p.owner_name?.toLowerCase().includes(q));
    }
    if (projectNameFilter.value) list = list.filter(p => p.id == projectNameFilter.value);
    if (statusFilter.value) list = list.filter(p => p.status === statusFilter.value);
    if (priorityFilter.value) list = list.filter(p => p.priority === priorityFilter.value);
    if (workTypeFilter.value) list = list.filter(p => p.work_type === workTypeFilter.value);

    const key = sortKey.value;
    list.sort((a, b) => {
        let va = a[key], vb = b[key];
        if (typeof va === 'string') va = va.toLowerCase();
        if (typeof vb === 'string') vb = vb.toLowerCase();
        va = va ?? '';
        vb = vb ?? '';
        if (va < vb) return sortAsc.value ? -1 : 1;
        if (va > vb) return sortAsc.value ? 1 : -1;
        return 0;
    });

    return list;
});

/* ── summary stats ── */
const totalProjects = computed(() => (props.projects || []).length);
const activeProjects = computed(() => (props.projects || []).filter(p => p.status === 'active').length);
const completedProjects = computed(() => (props.projects || []).filter(p => p.status === 'completed').length);
const totalHoursLogged = computed(() =>
    (props.projects || []).reduce((sum, p) => sum + (parseFloat(p.total_hours) || 0), 0)
);

/* ── analytics data ── */
const statusCounts = computed(() => {
    const map = {};
    (props.projects || []).forEach(p => {
        const s = p.status || 'unknown';
        map[s] = (map[s] || 0) + 1;
    });
    return Object.entries(map).sort((a, b) => b[1] - a[1]);
});

const priorityCounts = computed(() => {
    const map = {};
    (props.projects || []).forEach(p => {
        const pr = p.priority || 'unknown';
        map[pr] = (map[pr] || 0) + 1;
    });
    return Object.entries(map).sort((a, b) => b[1] - a[1]);
});

const topProjectsByHours = computed(() => {
    return [...(props.projects || [])]
        .filter(p => parseFloat(p.total_hours) > 0)
        .sort((a, b) => (parseFloat(b.total_hours) || 0) - (parseFloat(a.total_hours) || 0))
        .slice(0, 5);
});

const maxHours = computed(() => {
    if (!topProjectsByHours.value.length) return 1;
    return Math.max(...topProjectsByHours.value.map(p => parseFloat(p.total_hours) || 0), 1);
});

const maxStatusCount = computed(() => {
    if (!statusCounts.value.length) return 1;
    return Math.max(...statusCounts.value.map(([, c]) => c), 1);
});

const maxPriorityCount = computed(() => {
    if (!priorityCounts.value.length) return 1;
    return Math.max(...priorityCounts.value.map(([, c]) => c), 1);
});

const statusBarColor = {
    active: '#22c55e',
    completed: '#3b82f6',
    on_hold: '#eab308',
};

const priorityBarColor = {
    critical: '#ef4444',
    high: '#f97316',
    medium: '#eab308',
    low: '#94a3b8',
};

function formatLabel(str) {
    return (str || 'Unknown').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

/* ── CSV Export ── */
function exportCSV() {
    const headers = ['Project', 'Status', 'Priority', 'Stage', 'Work Type', 'Owner', 'Hours', 'Contributors', 'Progress %'];
    const rows = filteredProjects.value.map(p => [
        `"${(p.name || '').replace(/"/g, '""')}"`,
        p.status || '',
        p.priority || '',
        formatLabel(p.current_stage),
        formatLabel(p.work_type),
        p.owner_name || '',
        (parseFloat(p.total_hours) || 0).toFixed(2),
        p.contributor_count ?? 0,
        progressPercent(p),
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `projects-report-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <Head title="Projects Report" />

    <div class="space-y-6">
        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h1 class="text-xl font-bold text-gray-900">Projects Report</h1>
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500 mr-2">{{ filteredProjects.length }} of {{ totalProjects }} projects</span>
                <button
                    @click="exportCSV"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 hover:border-[#4e1a77] hover:text-[#4e1a77] transition-colors flex items-center gap-1.5"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export CSV
                </button>
                <button
                    @click="viewMode = 'table'"
                    :class="viewMode === 'table' ? 'bg-[#4e1a77] text-white' : 'bg-white text-gray-600 border border-gray-300 hover:border-[#4e1a77]'"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                >
                    Table
                </button>
                <button
                    @click="viewMode = 'analytics'"
                    :class="viewMode === 'analytics' ? 'bg-[#4e1a77] text-white' : 'bg-white text-gray-600 border border-gray-300 hover:border-[#4e1a77]'"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                >
                    Analytics
                </button>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Projects</p>
                <p class="mt-2 text-2xl font-bold text-[#4e1a77]">{{ totalProjects }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Active</p>
                <p class="mt-2 text-2xl font-bold text-green-600">{{ activeProjects }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Completed</p>
                <p class="mt-2 text-2xl font-bold text-blue-600">{{ completedProjects }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Hours Logged</p>
                <p class="mt-2 text-2xl font-bold text-gray-900">{{ hoursDisplay(totalHoursLogged) }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3 flex-wrap">
            <div class="relative flex-1 min-w-[200px] max-w-sm">
                <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search projects or owners..."
                    class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-4 text-sm text-gray-700 placeholder-gray-400 focus:border-[#4e1a77] focus:outline-none focus:ring-1 focus:ring-[#4e1a77]"
                />
            </div>
            <select
                v-model="projectNameFilter"
                class="rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 focus:border-[#4e1a77] focus:outline-none focus:ring-1 focus:ring-[#4e1a77]"
            >
                <option value="">All Projects</option>
                <option v-for="p in (projects || [])" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <select
                v-model="statusFilter"
                class="rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 focus:border-[#4e1a77] focus:outline-none focus:ring-1 focus:ring-[#4e1a77]"
            >
                <option value="">All Statuses</option>
                <option v-for="s in allStatuses" :key="s" :value="s">{{ formatLabel(s) }}</option>
            </select>
            <select
                v-model="priorityFilter"
                class="rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 focus:border-[#4e1a77] focus:outline-none focus:ring-1 focus:ring-[#4e1a77]"
            >
                <option value="">All Priorities</option>
                <option v-for="p in allPriorities" :key="p" :value="p">{{ formatLabel(p) }}</option>
            </select>
            <select
                v-model="workTypeFilter"
                class="rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 focus:border-[#4e1a77] focus:outline-none focus:ring-1 focus:ring-[#4e1a77]"
            >
                <option value="">All Work Types</option>
                <option v-for="wt in allWorkTypes" :key="wt" :value="wt">{{ formatLabel(wt) }}</option>
            </select>
        </div>

        <!-- TABLE VIEW -->
        <div v-if="viewMode === 'table'" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th @click="toggleSort('name')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Project <span class="ml-1 text-[10px]">{{ sortIcon('name') }}</span>
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Priority</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Stage</th>
                        <th @click="toggleSort('work_type')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Work Type <span class="ml-1 text-[10px]">{{ sortIcon('work_type') }}</span>
                        </th>
                        <th @click="toggleSort('owner_name')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Owner <span class="ml-1 text-[10px]">{{ sortIcon('owner_name') }}</span>
                        </th>
                        <th @click="toggleSort('total_hours')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Hours <span class="ml-1 text-[10px]">{{ sortIcon('total_hours') }}</span>
                        </th>
                        <th @click="toggleSort('contributor_count')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Contributors <span class="ml-1 text-[10px]">{{ sortIcon('contributor_count') }}</span>
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Progress</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="p in filteredProjects" :key="p.id" class="hover:bg-[#f5f0ff]/30 transition-colors">
                        <td class="px-5 py-3.5">
                            <Link :href="`/projects/${p.id}`" class="text-sm font-medium text-[#4e1a77] hover:underline">{{ p.name }}</Link>
                        </td>
                        <td class="px-5 py-3.5">
                            <StatusBadge :status="p.status" type="project" />
                        </td>
                        <td class="px-5 py-3.5">
                            <PriorityBadge :priority="p.priority" />
                        </td>
                        <td class="px-5 py-3.5">
                            <StageBadge v-if="p.current_stage" :stage="p.current_stage" />
                            <span v-else class="text-xs text-gray-400">--</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="text-sm text-gray-600 capitalize">{{ formatLabel(p.work_type) }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-gray-600">{{ p.owner_name || '--' }}</td>
                        <td class="px-5 py-3.5">
                            <span class="text-sm font-medium text-[#4e1a77]">{{ hoursDisplay(p.total_hours) }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-gray-900 text-center">{{ p.contributor_count ?? 0 }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <div class="w-20 bg-gray-200 rounded-full h-1.5">
                                    <div
                                        class="bg-[#4e1a77] h-1.5 rounded-full transition-all"
                                        :style="{ width: progressPercent(p) + '%' }"
                                    ></div>
                                </div>
                                <span class="text-xs text-gray-600 whitespace-nowrap">{{ progressPercent(p) }}%</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!filteredProjects.length" class="px-5 py-12 text-center text-sm text-gray-400">No projects match your filters</p>
        </div>

        <!-- ANALYTICS VIEW -->
        <div v-if="viewMode === 'analytics'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Projects by status -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Projects by Status</h3>
                <div v-if="statusCounts.length" class="space-y-3">
                    <div v-for="([status, count]) in statusCounts" :key="status" class="flex items-center gap-3">
                        <span class="text-xs text-gray-600 w-24 text-right shrink-0 capitalize">{{ formatLabel(status) }}</span>
                        <div class="flex-1 bg-gray-100 rounded-full h-6 relative overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500 flex items-center justify-end pr-2"
                                :style="{
                                    width: Math.max((count / maxStatusCount) * 100, 8) + '%',
                                    backgroundColor: statusBarColor[status] || '#4e1a77',
                                }"
                            >
                                <span class="text-xs font-semibold text-white drop-shadow-sm">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">No data</p>
            </div>

            <!-- Projects by priority -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Projects by Priority</h3>
                <div v-if="priorityCounts.length" class="space-y-3">
                    <div v-for="([priority, count]) in priorityCounts" :key="priority" class="flex items-center gap-3">
                        <span class="text-xs text-gray-600 w-24 text-right shrink-0 capitalize">{{ formatLabel(priority) }}</span>
                        <div class="flex-1 bg-gray-100 rounded-full h-6 relative overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500 flex items-center justify-end pr-2"
                                :style="{
                                    width: Math.max((count / maxPriorityCount) * 100, 8) + '%',
                                    backgroundColor: priorityBarColor[priority] || '#4e1a77',
                                }"
                            >
                                <span class="text-xs font-semibold text-white drop-shadow-sm">{{ count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">No data</p>
            </div>

            <!-- Top 5 projects by hours -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-6 lg:col-span-2">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Top 5 Projects by Hours</h3>
                <div v-if="topProjectsByHours.length" class="space-y-3">
                    <div v-for="p in topProjectsByHours" :key="p.id" class="flex items-center gap-3">
                        <span class="text-xs text-gray-700 w-40 text-right shrink-0 truncate font-medium" :title="p.name">{{ p.name }}</span>
                        <div class="flex-1 bg-gray-100 rounded-full h-7 relative overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500 flex items-center justify-end pr-3"
                                :style="{
                                    width: Math.max(((parseFloat(p.total_hours) || 0) / maxHours) * 100, 8) + '%',
                                    backgroundColor: '#4e1a77',
                                }"
                            >
                                <span class="text-xs font-semibold text-white drop-shadow-sm whitespace-nowrap">{{ hoursDisplay(p.total_hours) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">No hours data</p>
            </div>
        </div>
    </div>
</template>

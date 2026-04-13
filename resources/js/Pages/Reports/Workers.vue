<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

const { error: toastError } = useToast();

defineOptions({ layout: AppLayout });

const props = defineProps({
    workers: { type: [Array, Object], default: () => [] },
});

/* ── helpers ── */
function hoursDisplay(h) {
    h = parseFloat(h) || 0;
    const hrs = Math.floor(h);
    const mins = Math.round((h - hrs) * 60);
    if (hrs === 0 && mins === 0) return '0h';
    if (mins === 0) return `${hrs}h`;
    return `${hrs}h ${mins}m`;
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(t) {
    if (!t) return '';
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    return `${hr > 12 ? hr - 12 : hr}:${m} ${hr >= 12 ? 'PM' : 'AM'}`;
}

/* ── filters ── */
const search = ref('');
const roleFilter = ref('');

const allRoles = computed(() => {
    const roles = new Set();
    (props.workers || []).forEach(w => { if (w.role) roles.add(w.role); });
    return [...roles].sort();
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
    if (sortKey.value !== key) return '';
    return sortAsc.value ? ' ↑' : ' ↓';
}

/* ── filtered + sorted workers ── */
const filteredWorkers = computed(() => {
    let list = [...(props.workers || [])];
    if (search.value) {
        const q = search.value.toLowerCase();
        list = list.filter(w => w.name?.toLowerCase().includes(q) || w.email?.toLowerCase().includes(q));
    }
    if (roleFilter.value) {
        list = list.filter(w => w.role === roleFilter.value);
    }
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
const totalMembers = computed(() => (props.workers || []).length);
const activeMembers = computed(() => (props.workers || []).filter(w => w.is_active).length);
const totalHoursAll = computed(() => (props.workers || []).reduce((sum, w) => sum + (parseFloat(w.total_hours) || 0), 0));
const avgHoursPerMember = computed(() => {
    if (!totalMembers.value) return 0;
    return totalHoursAll.value / totalMembers.value;
});

/* ── Member Detail Modal ── */
const selectedMember = ref(null);
const memberDetail = ref(null);
const memberLoading = ref(false);
const detailTab = ref('worklogs');

async function openMemberDetail(worker) {
    selectedMember.value = worker;
    memberDetail.value = null;
    memberLoading.value = true;
    detailTab.value = 'worklogs';
    try {
        const { data } = await axios.get(`/api/v1/reports/members/${worker.id}/worklogs`);
        memberDetail.value = data;
    } catch (e) {
        toastError('Failed to load member detail: ' + (e.response?.data?.message || e.message));
        selectedMember.value = null;
    }
    memberLoading.value = false;
}

function closeMemberDetail() {
    selectedMember.value = null;
    memberDetail.value = null;
}

/* ── CSV Export ── */
function exportCSV() {
    const headers = ['Name', 'Email', 'Role', 'Active', 'Total Hours', 'Month Hours', 'Week Hours', 'Current Projects', 'Joined'];
    const rows = filteredWorkers.value.map(w => [
        `"${(w.name || '').replace(/"/g, '""')}"`,
        w.email || '',
        w.role || '',
        w.is_active ? 'Yes' : 'No',
        (parseFloat(w.total_hours) || 0).toFixed(2),
        (parseFloat(w.month_hours) || 0).toFixed(2),
        (parseFloat(w.week_hours) || 0).toFixed(2),
        w.current_projects ?? 0,
        w.joined_date || '',
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `workers-report-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <Head title="Workers Report" />

    <div class="space-y-6">
        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h1 class="text-xl font-bold text-gray-900">Workers Report</h1>
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">{{ filteredWorkers.length }} of {{ totalMembers }} members shown</span>
                <button
                    @click="exportCSV"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 hover:border-[#4e1a77] hover:text-[#4e1a77] transition-colors flex items-center gap-1.5"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export CSV
                </button>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Members</p>
                <p class="mt-2 text-2xl font-bold text-[#4e1a77]">{{ totalMembers }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Active Members</p>
                <p class="mt-2 text-2xl font-bold text-green-600">{{ activeMembers }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Hours (All Time)</p>
                <p class="mt-2 text-2xl font-bold text-gray-900">{{ hoursDisplay(totalHoursAll) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Avg Hours / Member</p>
                <p class="mt-2 text-2xl font-bold text-gray-900">{{ hoursDisplay(avgHoursPerMember) }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1 max-w-sm">
                <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email..."
                    class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-4 text-sm text-gray-700 placeholder-gray-400 focus:border-[#4e1a77] focus:outline-none focus:ring-1 focus:ring-[#4e1a77]"
                />
            </div>
            <select
                v-model="roleFilter"
                class="rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 focus:border-[#4e1a77] focus:outline-none focus:ring-1 focus:ring-[#4e1a77]"
            >
                <option value="">All Roles</option>
                <option v-for="r in allRoles" :key="r" :value="r" class="capitalize">{{ r }}</option>
            </select>
        </div>

        <!-- Table -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th @click="toggleSort('name')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Name{{ sortIcon('name') }}
                        </th>
                        <th @click="toggleSort('role')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Role{{ sortIcon('role') }}
                        </th>
                        <th @click="toggleSort('current_projects')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Current{{ sortIcon('current_projects') }}
                        </th>
                        <th @click="toggleSort('project_count')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Lifetime{{ sortIcon('project_count') }}
                        </th>
                        <th @click="toggleSort('week_hours')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            This Week{{ sortIcon('week_hours') }}
                        </th>
                        <th @click="toggleSort('month_hours')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            This Month{{ sortIcon('month_hours') }}
                        </th>
                        <th @click="toggleSort('total_hours')" class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 cursor-pointer select-none hover:text-[#4e1a77]">
                            Total{{ sortIcon('total_hours') }}
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="w in filteredWorkers"
                        :key="w.id"
                        @click="openMemberDetail(w)"
                        class="hover:bg-[#f5f0ff]/30 transition-colors cursor-pointer"
                    >
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#e8ddf0] text-xs font-bold text-[#4e1a77] shrink-0">
                                    {{ (w.name || '?').charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ w.name }}</p>
                                    <p class="text-xs text-gray-400">{{ w.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full bg-[#f5f0ff] px-2.5 py-0.5 text-xs font-medium text-[#4e1a77] capitalize">
                                {{ w.role?.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-sm font-medium text-[#4e1a77]">{{ w.current_projects ?? 0 }}</td>
                        <td class="px-5 py-3.5 text-sm text-gray-900">{{ w.project_count ?? 0 }}</td>
                        <td class="px-5 py-3.5">
                            <span class="text-sm font-medium text-[#4e1a77]">{{ hoursDisplay(w.week_hours) }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-gray-700">{{ hoursDisplay(w.month_hours) }}</td>
                        <td class="px-5 py-3.5 text-sm text-gray-900">{{ hoursDisplay(w.total_hours) }}</td>
                        <td class="px-5 py-3.5">
                            <span v-if="w.is_active" class="inline-flex items-center gap-1 text-xs font-medium text-green-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                Active
                            </span>
                            <span v-else class="inline-flex items-center gap-1 text-xs font-medium text-gray-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>
                                Inactive
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!filteredWorkers.length" class="px-5 py-12 text-center text-sm text-gray-400">
                No workers match your filters
            </p>
        </div>

        <!-- ═══ Member Detail Modal ═══ -->
        <div v-if="selectedMember" class="fixed inset-0 z-50 flex items-start justify-center pt-10 bg-black/40 overflow-y-auto" @click.self="closeMemberDetail">
            <div class="w-full max-w-3xl rounded-xl bg-white shadow-xl mx-4 mb-10">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#e8ddf0] text-sm font-bold text-[#4e1a77]">
                            {{ (selectedMember.name || '?').charAt(0).toUpperCase() }}
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">{{ selectedMember.name }}</h2>
                            <p class="text-xs text-gray-500">{{ selectedMember.email }} &middot; <span class="capitalize">{{ selectedMember.role?.replace('_', ' ') }}</span></p>
                        </div>
                    </div>
                    <button @click="closeMemberDetail" class="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Loading -->
                <div v-if="memberLoading" class="flex items-center justify-center py-16">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#4e1a77]"></div>
                    <span class="ml-3 text-sm text-gray-500">Loading...</span>
                </div>

                <template v-if="memberDetail && !memberLoading">
                    <!-- Summary Stats -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 px-6 py-4 border-b border-gray-100">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-[#4e1a77]">{{ memberDetail.currentProjects?.length || 0 }}</p>
                            <p class="text-xs text-gray-500">Current Projects</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-900">{{ memberDetail.lifetimeProjects?.length || 0 }}</p>
                            <p class="text-xs text-gray-500">Lifetime Projects</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-[#4e1a77]">{{ hoursDisplay(selectedMember.week_hours) }}</p>
                            <p class="text-xs text-gray-500">This Week</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-900">{{ hoursDisplay(selectedMember.total_hours) }}</p>
                            <p class="text-xs text-gray-500">All Time</p>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="border-b border-gray-200 px-6">
                        <nav class="flex gap-4">
                            <button
                                @click="detailTab = 'worklogs'"
                                class="py-2.5 text-sm font-medium border-b-2 transition-colors"
                                :class="detailTab === 'worklogs' ? 'border-[#4e1a77] text-[#4e1a77]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            >Recent Worklogs</button>
                            <button
                                @click="detailTab = 'projects'"
                                class="py-2.5 text-sm font-medium border-b-2 transition-colors"
                                :class="detailTab === 'projects' ? 'border-[#4e1a77] text-[#4e1a77]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            >Project Hours</button>
                            <button
                                @click="detailTab = 'current'"
                                class="py-2.5 text-sm font-medium border-b-2 transition-colors"
                                :class="detailTab === 'current' ? 'border-[#4e1a77] text-[#4e1a77]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            >Current Projects</button>
                        </nav>
                    </div>

                    <!-- Recent Worklogs -->
                    <div v-if="detailTab === 'worklogs'" class="px-6 py-4 max-h-96 overflow-y-auto">
                        <div v-if="memberDetail.recentWorklogs?.length" class="space-y-1">
                            <div v-for="log in memberDetail.recentWorklogs" :key="log.id" class="flex items-center justify-between py-2.5 border-b border-gray-50 hover:bg-gray-50 rounded px-2 -mx-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 truncate">{{ log.note || 'No description' }}</p>
                                    <p class="text-xs text-gray-400">
                                        <Link :href="`/projects/${log.project_id}`" class="text-[#4e1a77] hover:underline" @click.stop>{{ log.project_name }}</Link>
                                        &middot; {{ formatDate(log.log_date) }}
                                    </p>
                                </div>
                                <div class="text-right shrink-0 ml-4">
                                    <p class="text-sm font-semibold text-[#4e1a77]">{{ hoursDisplay(log.hours_spent) }}</p>
                                    <p class="text-xs text-gray-400">{{ formatTime(log.start_time) }} - {{ formatTime(log.end_time) }}</p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="py-8 text-center text-sm text-gray-400">No worklogs in the last 30 days</p>
                    </div>

                    <!-- Project Hours Summary -->
                    <div v-if="detailTab === 'projects'" class="px-6 py-4 max-h-96 overflow-y-auto">
                        <div v-if="memberDetail.projectHours?.length" class="space-y-2">
                            <div v-for="ph in memberDetail.projectHours" :key="ph.project_id" class="flex items-center gap-3">
                                <div class="flex-1">
                                    <Link :href="`/projects/${ph.project_id}`" class="text-sm font-medium text-[#4e1a77] hover:underline" @click.stop>{{ ph.project_name }}</Link>
                                    <p class="text-xs text-gray-400">{{ ph.log_count }} entries</p>
                                </div>
                                <div class="w-32">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            class="bg-[#4e1a77] h-2 rounded-full"
                                            :style="{ width: Math.min(100, (parseFloat(ph.total_hours) / Math.max(...memberDetail.projectHours.map(x => parseFloat(x.total_hours) || 1))) * 100) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                                <span class="text-sm font-semibold text-gray-900 w-16 text-right">{{ hoursDisplay(ph.total_hours) }}</span>
                            </div>
                        </div>
                        <p v-else class="py-8 text-center text-sm text-gray-400">No project hours logged</p>
                    </div>

                    <!-- Current Projects -->
                    <div v-if="detailTab === 'current'" class="px-6 py-4 max-h-96 overflow-y-auto">
                        <div v-if="memberDetail.currentProjects?.length" class="space-y-2">
                            <Link
                                v-for="p in memberDetail.currentProjects"
                                :key="p.id"
                                :href="`/projects/${p.id}`"
                                @click.stop
                                class="flex items-center justify-between py-2.5 px-3 -mx-3 rounded-lg hover:bg-[#f5f0ff]/50 transition-colors"
                            >
                                <span class="text-sm font-medium text-gray-900">{{ p.name }}</span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-medium capitalize"
                                    :class="{
                                        'bg-red-100 text-red-700': p.priority === 'critical',
                                        'bg-orange-100 text-orange-700': p.priority === 'high',
                                        'bg-yellow-100 text-yellow-700': p.priority === 'medium',
                                        'bg-gray-100 text-gray-600': p.priority === 'low' || !p.priority,
                                    }"
                                >{{ p.priority || 'normal' }}</span>
                            </Link>
                        </div>
                        <p v-else class="py-8 text-center text-sm text-gray-400">No active projects</p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

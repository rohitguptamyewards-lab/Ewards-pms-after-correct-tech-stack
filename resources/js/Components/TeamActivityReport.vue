<script setup>
import StageBadge from '@/Components/StageBadge.vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    projects: { type: [Array, Object], default: () => [] },
    teamMembers: { type: [Array, Object], default: () => [] },
});

const page = usePage();
const ALLOWED_ROLES = ['manager', 'analyst_head', 'senior_developer'];
const PERIOD_OPTIONS = [
    { key: 'previous_day', label: 'Previous Day' },
    { key: '2days', label: '2 Days' },
    { key: '1week', label: '1 Week' },
    { key: '2weeks', label: '2 Weeks' },
    { key: '3weeks', label: '3 Weeks' },
    { key: '1month', label: '1 Month' },
    { key: 'custom', label: 'Custom' },
];

const projectsList = computed(() => {
    if (Array.isArray(props.projects)) return props.projects;
    if (Array.isArray(props.projects?.data)) return props.projects.data;

    return [];
});

const teamMembersList = computed(() => {
    if (Array.isArray(props.teamMembers)) return props.teamMembers;
    if (Array.isArray(props.teamMembers?.data)) return props.teamMembers.data;

    return [];
});

const role = computed(() => page.props.auth?.user?.role ?? '');
const canViewReport = computed(() => ALLOWED_ROLES.includes(role.value));

const selectedProject = ref('');
const selectedUser = ref('');
const activePeriod = ref('previous_day');
const customFrom = ref('');
const customTo = ref('');
const loading = ref(false);
const error = ref('');
const logs = ref([]);
const totalHours = ref(0);
const entryCount = ref(0);
const generatedAt = ref(new Date());

let debounceTimer = null;
let activeRequestId = 0;

const displayDateFormatter = new Intl.DateTimeFormat('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const generatedAtFormatter = new Intl.DateTimeFormat('en-IN', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function toDateInput(value = new Date()) {
    const date = value instanceof Date ? value : new Date(value);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function daysAgo(days) {
    const date = new Date();
    date.setHours(12, 0, 0, 0);
    date.setDate(date.getDate() - days);

    return toDateInput(date);
}

function formatDate(value) {
    if (!value) return '—';

    const [year, month, day] = value.split('-').map(Number);
    return displayDateFormatter.format(new Date(year, month - 1, day));
}

function getRangeForPeriod(period) {
    switch (period) {
        case 'previous_day':
            return { from: daysAgo(1), to: daysAgo(1) };
        case '2days':
            return { from: daysAgo(1), to: toDateInput() };
        case '1week':
            return { from: daysAgo(6), to: toDateInput() };
        case '2weeks':
            return { from: daysAgo(13), to: toDateInput() };
        case '3weeks':
            return { from: daysAgo(20), to: toDateInput() };
        case '1month':
            return { from: daysAgo(29), to: toDateInput() };
        case 'custom':
            return { from: customFrom.value, to: customTo.value };
        default:
            return { from: daysAgo(1), to: daysAgo(1) };
    }
}

const normalizedRange = computed(() => {
    const range = getRangeForPeriod(activePeriod.value);

    if (!range.from || !range.to) {
        return { from: '', to: '' };
    }

    return range.from <= range.to
        ? range
        : { from: range.to, to: range.from };
});

const requestParams = computed(() => {
    if (!canViewReport.value) return null;
    if (!normalizedRange.value.from || !normalizedRange.value.to) return null;

    return {
        date_from: normalizedRange.value.from,
        date_to: normalizedRange.value.to,
        project_id: selectedProject.value ? Number(selectedProject.value) : undefined,
        user_id: selectedUser.value ? Number(selectedUser.value) : undefined,
    };
});

const dateRangeLabel = computed(() => {
    const { from, to } = normalizedRange.value;

    if (!from || !to) return 'Select a date range';
    if (from === to) return formatDate(from);

    return `${formatDate(from)} - ${formatDate(to)}`;
});

const selectedProjectLabel = computed(() => {
    if (!selectedProject.value) return 'All Projects';

    return projectsList.value.find((project) => String(project.id) === String(selectedProject.value))?.name ?? 'Selected Project';
});

const selectedUserLabel = computed(() => {
    if (!selectedUser.value) return 'All Members';

    return teamMembersList.value.find((member) => String(member.id) === String(selectedUser.value))?.name ?? 'Selected Member';
});

const totalHoursLabel = computed(() => `${totalHours.value.toFixed(2)}h`);
const generatedAtLabel = computed(() => generatedAtFormatter.format(generatedAt.value));

const downloadFileName = computed(() => {
    const { from, to } = normalizedRange.value;

    if (!from || !to) return 'team-activity-report.pdf';
    if (from === to) return `team-activity-${from}.pdf`;

    return `team-activity-${from}-to-${to}.pdf`;
});

const needsCustomDateSelection = computed(() => {
    return activePeriod.value === 'custom' && (!customFrom.value || !customTo.value);
});

function setPeriod(period) {
    if (period === 'custom' && (!customFrom.value || !customTo.value)) {
        const currentRange = normalizedRange.value.from && normalizedRange.value.to
            ? normalizedRange.value
            : getRangeForPeriod('previous_day');

        customFrom.value = currentRange.from;
        customTo.value = currentRange.to;
    }

    activePeriod.value = period;
}

function scheduleFetch(params) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchLogs(params);
    }, 300);
}

async function fetchLogs(params) {
    const requestId = ++activeRequestId;

    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/api/v1/dashboard/activity-report', {
            params,
        });

        if (requestId !== activeRequestId) return;

        logs.value = Array.isArray(data.logs) ? data.logs : [];
        totalHours.value = Number(data.meta?.total_hours ?? data.total_hours ?? 0);
        entryCount.value = Number(data.meta?.entry_count ?? logs.value.length);
        generatedAt.value = new Date();
    } catch (fetchError) {
        if (requestId !== activeRequestId) return;

        logs.value = [];
        totalHours.value = 0;
        entryCount.value = 0;
        error.value = fetchError.response?.status === 403
            ? 'You do not have access to this report.'
            : 'Failed to load the team activity report.';
    } finally {
        if (requestId === activeRequestId) {
            loading.value = false;
        }
    }
}

watch(requestParams, (params) => {
    if (!params) return;
    scheduleFetch(params);
}, { immediate: true });

onBeforeUnmount(() => {
    clearTimeout(debounceTimer);
});

function statusClass(status) {
    if (status === 'done') return 'bg-green-100 text-green-700';
    if (status === 'blocked') return 'bg-red-100 text-red-700';
    if (status === 'in_progress') return 'bg-blue-100 text-blue-700';

    return 'bg-gray-100 text-gray-600';
}

function statusLabel(status) {
    if (status === 'done') return 'Done';
    if (status === 'blocked') return 'Blocked';
    if (status === 'in_progress') return 'In Progress';

    return status ? String(status).replace(/_/g, ' ') : '—';
}

function projectStageValue(log) {
    return log.project_stage_snapshot || '';
}

function projectStageLabel(log) {
    const stage = projectStageValue(log);

    if (stage) {
        return stage
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (character) => character.toUpperCase());
    }

    return statusLabel(log.status);
}

async function downloadPdf() {
    const { jsPDF } = await import('jspdf');
    const { default: autoTable } = await import('jspdf-autotable');

    const doc = new jsPDF({
        orientation: 'landscape',
        unit: 'mm',
        format: 'a4',
    });

    const pageWidth = doc.internal.pageSize.getWidth();
    const rows = logs.value.length
        ? logs.value.map((log) => ([
            formatDate(log.log_date),
            (log.start_time || '').slice(0, 5) || '—',
            (log.end_time || '').slice(0, 5) || '—',
            log.user_name,
            log.project_name,
            log.note || 'No description added',
            `${Number(log.hours_spent || 0).toFixed(2)}h`,
            projectStageLabel(log),
        ]))
        : [[dateRangeLabel.value, '—', '—', selectedUserLabel.value, selectedProjectLabel.value, 'No project worklogs found for the selected filters.', '0.00h', '—']];

    doc.setFontSize(16);
    doc.text('Team Activity Report', 14, 16);

    doc.setFontSize(10);
    doc.setTextColor(90, 90, 90);
    doc.text(`Period: ${dateRangeLabel.value}`, 14, 22);
    doc.text(`Project: ${selectedProjectLabel.value}`, 14, 27);
    doc.text(`Member: ${selectedUserLabel.value}`, 80, 27);
    doc.text(`Generated: ${generatedAtLabel.value}`, pageWidth - 14, 22, { align: 'right' });

    autoTable(doc, {
        startY: 32,
        head: [['Date', 'Time Start', 'Time End', 'Member', 'Project', 'Work Description', 'Hours', 'Project Stage']],
        body: rows,
        margin: { top: 14, right: 14, bottom: 18, left: 14 },
        styles: {
            fontSize: 8,
            cellPadding: 2.5,
            overflow: 'linebreak',
            valign: 'top',
            textColor: [55, 65, 81],
        },
        headStyles: {
            fillColor: [78, 26, 119],
            textColor: [255, 255, 255],
            fontStyle: 'bold',
        },
        columnStyles: {
            0: { cellWidth: 22 },
            1: { cellWidth: 18 },
            2: { cellWidth: 18 },
            3: { cellWidth: 30 },
            4: { cellWidth: 36 },
            5: { cellWidth: 95 },
            6: { cellWidth: 16, halign: 'right' },
            7: { cellWidth: 30 },
        },
    });

    const finalY = (doc.lastAutoTable?.finalY ?? 32) + 8;
    doc.setFontSize(10);
    doc.setTextColor(55, 65, 81);
    doc.text(`Entries: ${entryCount.value}`, 14, finalY);
    doc.text(`Total: ${totalHoursLabel.value}`, pageWidth - 14, finalY, { align: 'right' });
    doc.save(downloadFileName.value);
}

function printReport() {
    window.print();
}
</script>

<template>
    <div
        v-if="canViewReport"
        id="team-activity-print-root"
        class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden"
    >
        <div class="border-b border-gray-100 px-5 py-3.5 flex items-center justify-between bg-gradient-to-r from-white to-[#f5f0ff]/30">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#4e1a77]/10">
                    <svg class="h-4 w-4 text-[#4e1a77]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 002.25 2.25h.75" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Team Activity Report</h2>
                    <p class="text-xs text-gray-400">Project worklogs only. Custom Work entries are excluded.</p>
                </div>
            </div>

            <div class="print-hidden flex items-center gap-2">
                <button
                    type="button"
                    @click="printReport"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
                    </svg>
                    Print
                </button>
                <button
                    type="button"
                    @click="downloadPdf"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#4e1a77] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#3d1560] transition-colors"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Download PDF
                </button>
            </div>
        </div>

        <div class="print-hidden px-5 py-3 border-b border-gray-100 bg-gray-50/50">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Project</label>
                    <select
                        v-model="selectedProject"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#4e1a77]/30 min-w-[160px]"
                    >
                        <option value="">All Projects</option>
                        <option v-for="project in projectsList" :key="project.id" :value="project.id">{{ project.name }}</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Member</label>
                    <select
                        v-model="selectedUser"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#4e1a77]/30 min-w-[160px]"
                    >
                        <option value="">All Members</option>
                        <option v-for="member in teamMembersList" :key="member.id" :value="member.id">{{ member.name }}</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Period</label>
                    <div class="flex items-center gap-1 flex-wrap">
                        <button
                            v-for="option in PERIOD_OPTIONS"
                            :key="option.key"
                            type="button"
                            @click="setPeriod(option.key)"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors border',
                                activePeriod === option.key
                                    ? 'bg-[#4e1a77] text-white border-[#4e1a77]'
                                    : 'bg-white text-gray-600 border-gray-200 hover:bg-[#f5f0ff] hover:border-[#4e1a77]/30'
                            ]"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <div v-if="activePeriod === 'custom'" class="flex items-end gap-2">
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">From</label>
                        <input
                            v-model="customFrom"
                            type="date"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#4e1a77]/30"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">To</label>
                        <input
                            v-model="customTo"
                            type="date"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#4e1a77]/30"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="px-5 py-4">
            <div class="mb-4 rounded-xl border border-[#e8ddf0] bg-[#f8f4fc] px-4 py-3">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#4e1a77]">Share-Ready Snapshot</p>
                        <h3 class="text-lg font-semibold text-gray-900">{{ dateRangeLabel }}</h3>
                        <p class="text-xs text-gray-500">Use this panel for screenshots, printing, or PDF export.</p>
                    </div>

                    <div class="grid gap-3 text-xs text-gray-600 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Project</p>
                            <p class="mt-1 font-medium text-gray-900">{{ selectedProjectLabel }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Member</p>
                            <p class="mt-1 font-medium text-gray-900">{{ selectedUserLabel }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Entries</p>
                            <p class="mt-1 font-medium text-gray-900">{{ entryCount }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Generated</p>
                            <p class="mt-1 font-medium text-gray-900">{{ generatedAtLabel }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="needsCustomDateSelection" class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                Select both start and end dates to load the custom report range.
            </div>

            <div v-else-if="loading" class="space-y-2 py-4">
                <div v-for="item in 5" :key="item" class="h-10 rounded bg-gray-100 animate-pulse" />
            </div>

            <div v-else-if="error" class="rounded-lg border border-red-100 bg-red-50 px-4 py-6 text-center text-sm text-red-600">
                {{ error }}
            </div>

            <div v-else-if="!logs.length" class="rounded-lg border border-dashed border-gray-200 px-5 py-12 text-center">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-100">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </div>
                <p class="mt-2 text-sm text-gray-500">No project worklogs were found for the selected filters.</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="py-2 pr-4 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Date</th>
                            <th class="py-2 pr-4 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Time Start</th>
                            <th class="py-2 pr-4 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Time End</th>
                            <th class="py-2 pr-4 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Member</th>
                            <th class="py-2 pr-4 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Project</th>
                            <th class="py-2 pr-4 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-400">Work Description</th>
                            <th class="py-2 pr-4 text-right text-[10px] font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Hours</th>
                            <th class="py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-400 whitespace-nowrap">Project Stage</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="log in logs" :key="log.id" class="hover:bg-[#f5f0ff]/20 transition-colors">
                            <td class="py-2.5 pr-4 text-gray-500 whitespace-nowrap">{{ formatDate(log.log_date) }}</td>
                            <td class="py-2.5 pr-4 text-gray-600 whitespace-nowrap tabular-nums">{{ (log.start_time || '').slice(0, 5) || '—' }}</td>
                            <td class="py-2.5 pr-4 text-gray-600 whitespace-nowrap tabular-nums">{{ (log.end_time || '').slice(0, 5) || '—' }}</td>
                            <td class="py-2.5 pr-4 font-medium text-gray-800 whitespace-nowrap">{{ log.user_name }}</td>
                            <td class="py-2.5 pr-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full bg-[#4e1a77]/8 px-2 py-0.5 text-[10px] font-medium text-[#4e1a77]">
                                    {{ log.project_name }}
                                </span>
                            </td>
                            <td class="py-2.5 pr-4 max-w-xl whitespace-normal break-words text-gray-700">
                                {{ log.note || 'No description added' }}
                            </td>
                            <td class="py-2.5 pr-4 text-right font-semibold text-gray-800 tabular-nums whitespace-nowrap">
                                {{ Number(log.hours_spent || 0).toFixed(2) }}h
                            </td>
                            <td class="py-2.5">
                                <StageBadge v-if="projectStageValue(log)" :stage="projectStageValue(log)" />
                                <span v-else :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium capitalize', statusClass(log.status)]">
                                    {{ statusLabel(log.status) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200">
                            <td colspan="6" class="py-2.5 text-xs font-semibold text-gray-500">
                                {{ entryCount }} {{ entryCount === 1 ? 'entry' : 'entries' }}
                            </td>
                            <td class="py-2.5 pr-4 text-right text-sm font-bold text-[#4e1a77] tabular-nums whitespace-nowrap">
                                {{ totalHoursLabel }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    :global(body) {
        background: #ffffff;
    }

    :global(body *) {
        visibility: hidden !important;
    }

    #team-activity-print-root,
    #team-activity-print-root * {
        visibility: visible !important;
    }

    #team-activity-print-root {
        position: absolute;
        inset: 0;
        width: 100%;
        border: none !important;
        box-shadow: none !important;
        overflow: visible !important;
    }

    .print-hidden {
        display: none !important;
    }
}
</style>

<script setup>
import { ref, computed, watchEffect } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatsCard from '@/Components/StatsCard.vue';
import StageBadge from '@/Components/StageBadge.vue';
import PriorityBadge from '@/Components/PriorityBadge.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineOptions({ layout: AppLayout });

const props = defineProps({
    projects: { type: Array, default: () => [] },
    deadlines: { type: Array, default: () => [] },
    projectWorklogs: { type: Array, default: () => [] },
    teamUtilization: { type: Array, default: () => [] },
    recentLogs: { type: Array, default: () => [] },
    canViewSensitiveSections: { type: Boolean, default: false },
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.role);
const canViewProjectsReport = computed(() => ['manager', 'analyst_head', 'analyst'].includes(role.value));
const canViewWorkersReport = computed(() => ['manager', 'analyst_head', 'senior_developer'].includes(role.value));
const activeView = ref('timeline');
const viewTabs = computed(() => {
    const tabs = [
        { key: 'timeline', label: 'Timeline / Gantt' },
        { key: 'calendar', label: 'Calendar' },
    ];

    if (props.canViewSensitiveSections) {
        tabs.push(
            { key: 'worklogs', label: 'Project Worklogs' },
            { key: 'utilization', label: 'Team Utilization' },
            { key: 'activity', label: 'Activity Feed' },
        );
    }

    return tabs;
});

watchEffect(() => {
    if (!viewTabs.value.some(tab => tab.key === activeView.value)) {
        activeView.value = 'timeline';
    }
});

// ── Stats ──────────────────────────────────────────────
const totalProjects = computed(() => props.projects.length);
const activeProjects = computed(() => props.projects.filter(p => p.status === 'active').length);
const totalHoursLogged = computed(() => props.projects.reduce((s, p) => s + parseFloat(p.total_hours || 0), 0));
const upcomingDeadlines = computed(() => props.deadlines.filter(d => d.status !== 'done' && d.due_date >= todayStr.value).length);

// ── Timeline / Gantt ───────────────────────────────────
const todayStr = computed(() => new Date().toISOString().split('T')[0]);

const ganttProjects = computed(() => {
    return props.projects
        .filter(p => p.status === 'active' || p.status === 'on_hold')
        .map(p => {
            const start = p.created_at ? p.created_at.split('T')[0] : todayStr.value;
            const end = p.last_deadline || p.next_deadline || todayStr.value;
            return { ...p, start_date: start, end_date: end > start ? end : todayStr.value };
        })
        .sort((a, b) => a.start_date.localeCompare(b.start_date));
});

// Calculate Gantt range (last 60 days to +30 days from today)
const ganttStart = computed(() => {
    const d = new Date();
    d.setDate(d.getDate() - 60);
    return d.toISOString().split('T')[0];
});

const ganttEnd = computed(() => {
    const d = new Date();
    d.setDate(d.getDate() + 30);
    return d.toISOString().split('T')[0];
});

const ganttDays = computed(() => {
    const start = new Date(ganttStart.value);
    const end = new Date(ganttEnd.value);
    return Math.ceil((end - start) / (1000 * 60 * 60 * 24));
});

function ganttBarStyle(project) {
    const rangeStart = new Date(ganttStart.value);
    const rangeEnd = new Date(ganttEnd.value);
    const totalDays = ganttDays.value;

    let projStart = new Date(project.start_date);
    let projEnd = new Date(project.end_date);

    if (projStart < rangeStart) projStart = rangeStart;
    if (projEnd > rangeEnd) projEnd = rangeEnd;
    if (projEnd < projStart) projEnd = projStart;

    const leftDays = (projStart - rangeStart) / (1000 * 60 * 60 * 24);
    const widthDays = Math.max(1, (projEnd - projStart) / (1000 * 60 * 60 * 24));

    return {
        left: (leftDays / totalDays * 100) + '%',
        width: (widthDays / totalDays * 100) + '%',
    };
}

function ganttTodayPosition() {
    const rangeStart = new Date(ganttStart.value);
    const today = new Date();
    const days = (today - rangeStart) / (1000 * 60 * 60 * 24);
    return (days / ganttDays.value * 100) + '%';
}

const ganttMonths = computed(() => {
    const months = [];
    const start = new Date(ganttStart.value);
    const end = new Date(ganttEnd.value);
    const totalDays = ganttDays.value;
    let current = new Date(start.getFullYear(), start.getMonth(), 1);

    while (current <= end) {
        const monthStart = current < start ? start : new Date(current);
        const nextMonth = new Date(current.getFullYear(), current.getMonth() + 1, 1);
        const monthEnd = nextMonth > end ? end : nextMonth;
        const daysFromStart = (monthStart - start) / (1000 * 60 * 60 * 24);
        const monthDays = (monthEnd - monthStart) / (1000 * 60 * 60 * 24);

        months.push({
            label: monthStart.toLocaleDateString('en-IN', { month: 'short', year: '2-digit' }),
            left: (daysFromStart / totalDays * 100) + '%',
            width: (monthDays / totalDays * 100) + '%',
        });
        current = nextMonth;
    }
    return months;
});

const priorityBarColors = {
    critical: 'bg-red-500',
    high: 'bg-orange-500',
    medium: 'bg-[#4e1a77]',
    low: 'bg-blue-400',
};

// ── Calendar ───────────────────────────────────────────
const calendarMonth = ref(new Date().getMonth());
const calendarYear = ref(new Date().getFullYear());

const calendarMonthLabel = computed(() => {
    return new Date(calendarYear.value, calendarMonth.value).toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
});

function prevMonth() {
    if (calendarMonth.value === 0) { calendarMonth.value = 11; calendarYear.value--; }
    else calendarMonth.value--;
}

function nextMonth() {
    if (calendarMonth.value === 11) { calendarMonth.value = 0; calendarYear.value++; }
    else calendarMonth.value++;
}

const calendarDays = computed(() => {
    const year = calendarYear.value;
    const month = calendarMonth.value;
    const firstDay = new Date(year, month, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const days = [];

    // Pad leading empty days
    for (let i = 0; i < firstDay; i++) days.push(null);
    for (let d = 1; d <= daysInMonth; d++) days.push(d);

    return days;
});

function getDeadlinesForDay(day) {
    if (!day) return [];
    const dateStr = `${calendarYear.value}-${String(calendarMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return props.deadlines.filter(d => d.due_date === dateStr);
}

function isToday(day) {
    if (!day) return false;
    const now = new Date();
    return day === now.getDate() && calendarMonth.value === now.getMonth() && calendarYear.value === now.getFullYear();
}

function isPastDate(day) {
    if (!day) return false;
    const date = new Date(calendarYear.value, calendarMonth.value, day);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return date < today;
}

// ── Project Worklogs ───────────────────────────────────
const expandedProject = ref(null);

const groupedWorklogs = computed(() => {
    const groups = {};
    props.projectWorklogs.forEach(wl => {
        if (!groups[wl.project_id]) {
            groups[wl.project_id] = { project_name: wl.project_name, project_id: wl.project_id, employees: [], total_hours: 0 };
        }
        groups[wl.project_id].employees.push(wl);
        groups[wl.project_id].total_hours += parseFloat(wl.total_hours || 0);
    });
    return Object.values(groups).sort((a, b) => b.total_hours - a.total_hours);
});

function toggleProject(id) {
    expandedProject.value = expandedProject.value === id ? null : id;
}

// ── Helpers ────────────────────────────────────────────
function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
}

function hoursDisplay(h) {
    h = parseFloat(h) || 0;
    const hrs = Math.floor(h);
    const mins = Math.round((h - hrs) * 60);
    if (mins === 0) return `${hrs}h`;
    return `${hrs}h ${mins}m`;
}

function formatTime(t) {
    if (!t) return '';
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    return `${hr > 12 ? hr - 12 : hr}:${m} ${hr >= 12 ? 'PM' : 'AM'}`;
}

const statusColors = {
    done: 'bg-green-100 text-green-700',
    in_progress: 'bg-blue-100 text-blue-700',
    blocked: 'bg-red-100 text-red-700',
};

const maxUtilHours = computed(() => {
    return Math.max(1, ...props.teamUtilization.map(t => parseFloat(t.month_hours || 0)));
});

// ── Project Filter ────────────────────────────────────
const projectFilter = ref('');

const filteredGanttProjects = computed(() => {
    if (!projectFilter.value) return ganttProjects.value;
    return ganttProjects.value.filter(p => p.id == projectFilter.value);
});

const filteredGroupedWorklogs = computed(() => {
    if (!projectFilter.value) return groupedWorklogs.value;
    return groupedWorklogs.value.filter(g => g.project_id == projectFilter.value);
});
</script>

<template>
    <Head title="Report Dashboard" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Report Dashboard</h1>
                <p class="text-sm text-gray-500 mt-0.5">Overview of projects, timelines, and team performance</p>
            </div>
            <div class="flex gap-2">
                <Link v-if="canViewProjectsReport" href="/reports/projects" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">Projects Report</Link>
                <Link v-if="canViewWorkersReport" href="/reports/workers" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">Workers Report</Link>
            </div>
        </div>

        <!-- Project Filter -->
        <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-gray-600">Filter by Project:</label>
            <select
                v-model="projectFilter"
                class="rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 focus:border-[#4e1a77] focus:outline-none focus:ring-1 focus:ring-[#4e1a77]"
            >
                <option value="">All Projects</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatsCard label="Total Projects" :value="totalProjects" color="blue" />
            <StatsCard label="Active" :value="activeProjects" color="green" />
            <StatsCard label="Total Hours Logged" :value="hoursDisplay(totalHoursLogged)" color="blue" />
            <StatsCard label="Upcoming Deadlines" :value="upcomingDeadlines" color="yellow" />
        </div>

        <!-- View Tabs -->
        <div class="border-b border-gray-200">
            <nav class="flex gap-0.5">
                <button
                    v-for="v in viewTabs"
                    :key="v.key"
                    @click="activeView = v.key"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
                    :class="activeView === v.key
                        ? 'border-[#4e1a77] text-[#4e1a77] bg-[#f5f0ff]/50'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                >
                    {{ v.label }}
                </button>
            </nav>
        </div>

        <!-- ═══════ TIMELINE / GANTT VIEW ═══════ -->
        <div v-if="activeView === 'timeline'" class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-3.5 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Project Timeline</h2>
                    <div class="flex items-center gap-3 text-[10px]">
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-500"></span> Critical</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-orange-500"></span> High</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-[#4e1a77]"></span> Medium</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-400"></span> Low</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <div class="min-w-[900px]">
                        <!-- Month headers -->
                        <div class="relative h-7 border-b border-gray-100 bg-gray-50">
                            <div
                                v-for="(m, i) in ganttMonths"
                                :key="i"
                                class="absolute top-0 h-full flex items-center border-r border-gray-200 px-2"
                                :style="{ left: m.left, width: m.width }"
                            >
                                <span class="text-[10px] font-semibold text-gray-500 uppercase">{{ m.label }}</span>
                            </div>
                        </div>

                        <!-- Project rows -->
                        <div v-for="p in filteredGanttProjects" :key="p.id" class="flex items-center border-b border-gray-50 hover:bg-gray-50/50 group">
                            <!-- Project label -->
                            <div class="w-52 shrink-0 px-4 py-2.5 border-r border-gray-100">
                                <Link :href="`/projects/${p.id}`" class="text-xs font-medium text-[#4e1a77] group-hover:underline truncate block">{{ p.name }}</Link>
                                <div class="flex items-center gap-1 mt-0.5">
                                    <span class="text-[10px] text-gray-400">{{ p.owner_name }}</span>
                                    <span v-if="p.total_hours > 0" class="text-[10px] text-gray-400">&middot; {{ hoursDisplay(p.total_hours) }}</span>
                                </div>
                            </div>
                            <!-- Gantt bar area -->
                            <div class="flex-1 relative h-10">
                                <!-- Today line -->
                                <div class="absolute top-0 bottom-0 w-px bg-red-400 z-10 opacity-50" :style="{ left: ganttTodayPosition() }"></div>
                                <!-- Bar -->
                                <div
                                    class="absolute top-2 h-6 rounded-md shadow-sm flex items-center px-2 cursor-pointer"
                                    :class="priorityBarColors[p.priority] || 'bg-[#4e1a77]'"
                                    :style="ganttBarStyle(p)"
                                    :title="`${p.name}: ${formatDate(p.start_date)} - ${formatDate(p.end_date)}`"
                                >
                                    <span class="text-[9px] font-medium text-white truncate">
                                        {{ p.planner_done || 0 }}/{{ p.planner_count || 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p v-if="!filteredGanttProjects.length" class="px-5 py-8 text-center text-sm text-gray-400">No active projects to show</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════ CALENDAR VIEW ═══════ -->
        <div v-if="activeView === 'calendar'" class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <!-- Calendar Header -->
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                    <button @click="prevMonth" class="rounded-lg p-1.5 hover:bg-gray-100">
                        <svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                    </button>
                    <h2 class="text-sm font-semibold text-gray-900">{{ calendarMonthLabel }}</h2>
                    <button @click="nextMonth" class="rounded-lg p-1.5 hover:bg-gray-100">
                        <svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </button>
                </div>

                <!-- Day headers -->
                <div class="grid grid-cols-7 border-b border-gray-100">
                    <div v-for="d in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="d" class="px-2 py-2 text-center text-[10px] font-semibold uppercase text-gray-400">
                        {{ d }}
                    </div>
                </div>

                <!-- Calendar grid -->
                <div class="grid grid-cols-7">
                    <div
                        v-for="(day, i) in calendarDays"
                        :key="i"
                        class="min-h-[80px] border-b border-r border-gray-100 p-1.5"
                        :class="{
                            'bg-[#f5f0ff]/30': isToday(day),
                            'bg-gray-50/50': !day,
                        }"
                    >
                        <template v-if="day">
                            <div class="flex items-center justify-between mb-1">
                                <span
                                    class="text-xs font-medium"
                                    :class="isToday(day) ? 'text-[#4e1a77] bg-[#4e1a77]/10 rounded-full w-5 h-5 flex items-center justify-center' : 'text-gray-600'"
                                >{{ day }}</span>
                            </div>
                            <div class="space-y-0.5">
                                <div
                                    v-for="dl in getDeadlinesForDay(day).slice(0, 3)"
                                    :key="dl.id"
                                    class="rounded px-1 py-0.5 text-[9px] font-medium truncate cursor-default"
                                    :class="{
                                        'bg-yellow-100 text-yellow-700': dl.milestone_flag,
                                        'bg-red-100 text-red-700': !dl.milestone_flag && dl.status !== 'done' && isPastDate(day),
                                        'bg-green-100 text-green-700': dl.status === 'done',
                                        'bg-blue-100 text-blue-700': !dl.milestone_flag && dl.status !== 'done' && !isPastDate(day),
                                    }"
                                    :title="`${dl.project_name}: ${dl.title} (${dl.assignee_name || 'Unassigned'})`"
                                >
                                    {{ dl.milestone_flag ? '&#9733; ' : '' }}{{ dl.title }}
                                </div>
                                <div v-if="getDeadlinesForDay(day).length > 3" class="text-[9px] text-gray-400 px-1">
                                    +{{ getDeadlinesForDay(day).length - 3 }} more
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="flex items-center gap-4 px-2 text-[10px] text-gray-500">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-yellow-100 border border-yellow-300"></span> Milestone</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-blue-100 border border-blue-300"></span> Upcoming</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-red-100 border border-red-300"></span> Overdue</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-green-100 border border-green-300"></span> Done</span>
            </div>
        </div>

        <!-- ═══════ PROJECT WORKLOGS VIEW ═══════ -->
        <div v-if="props.canViewSensitiveSections && activeView === 'worklogs'" class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-gray-900">Project-wise Employee Worklogs</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Hours logged per employee per project</p>
                </div>

                <div v-if="filteredGroupedWorklogs.length" class="divide-y divide-gray-100">
                    <div v-for="group in filteredGroupedWorklogs" :key="group.project_id">
                        <!-- Project header -->
                        <div
                            class="flex items-center justify-between px-5 py-3.5 cursor-pointer hover:bg-gray-50 transition-colors"
                            @click="toggleProject(group.project_id)"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#f5f0ff] text-sm font-bold text-[#4e1a77]">
                                    {{ group.project_name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <Link :href="`/projects/${group.project_id}`" class="text-sm font-medium text-[#4e1a77] hover:underline" @click.stop>{{ group.project_name }}</Link>
                                    <p class="text-xs text-gray-400">{{ group.employees.length }} contributor{{ group.employees.length !== 1 ? 's' : '' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <p class="text-lg font-bold text-gray-900">{{ hoursDisplay(group.total_hours) }}</p>
                                <svg class="h-4 w-4 text-gray-400 transition-transform" :class="{ 'rotate-180': expandedProject === group.project_id }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>

                        <!-- Expanded: employees -->
                        <div v-if="expandedProject === group.project_id" class="bg-gray-50/50 px-5 pb-4">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                                        <th class="py-2 pr-3 text-left">Employee</th>
                                        <th class="py-2 pr-3 text-left">Hours</th>
                                        <th class="py-2 pr-3 text-left">Entries</th>
                                        <th class="py-2 pr-3 text-left">First Log</th>
                                        <th class="py-2 text-left">Last Log</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="emp in group.employees" :key="emp.user_id">
                                        <td class="py-2 pr-3">
                                            <div class="flex items-center gap-2">
                                                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-[#e8ddf0] text-[10px] font-bold text-[#4e1a77]">
                                                    {{ (emp.user_name || '?').charAt(0).toUpperCase() }}
                                                </div>
                                                <span class="text-sm text-gray-900">{{ emp.user_name }}</span>
                                            </div>
                                        </td>
                                        <td class="py-2 pr-3 text-sm font-medium text-gray-900">{{ hoursDisplay(emp.total_hours) }}</td>
                                        <td class="py-2 pr-3 text-sm text-gray-600">{{ emp.log_count }}</td>
                                        <td class="py-2 pr-3 text-xs text-gray-500">{{ formatDate(emp.first_log) }}</td>
                                        <td class="py-2 text-xs text-gray-500">{{ formatDate(emp.last_log) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <p v-else class="px-5 py-8 text-center text-sm text-gray-400">No worklog data available</p>
            </div>
        </div>

        <!-- ═══════ TEAM UTILIZATION VIEW ═══════ -->
        <div v-if="props.canViewSensitiveSections && activeView === 'utilization'" class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-gray-900">Team Utilization (Last 30 Days)</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Hours logged per team member</p>
                </div>

                <div v-if="teamUtilization.length" class="px-5 py-4 space-y-3">
                    <div v-for="member in teamUtilization" :key="member.id" class="flex items-center gap-4">
                        <div class="w-36 shrink-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ member.name }}</p>
                            <p class="text-[10px] text-gray-400 capitalize">{{ member.role }} &middot; {{ member.active_projects }} project{{ member.active_projects !== 1 ? 's' : '' }}</p>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 bg-gray-200 rounded-full h-4 relative overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all flex items-center justify-end pr-1"
                                        :class="parseFloat(member.month_hours) > 120 ? 'bg-green-500' : parseFloat(member.month_hours) > 60 ? 'bg-[#4e1a77]' : parseFloat(member.month_hours) > 0 ? 'bg-yellow-500' : 'bg-gray-300'"
                                        :style="{ width: Math.min(100, (parseFloat(member.month_hours) / maxUtilHours * 100)) + '%' }"
                                    >
                                        <span v-if="parseFloat(member.month_hours) > 10" class="text-[9px] font-bold text-white">{{ hoursDisplay(member.month_hours) }}</span>
                                    </div>
                                </div>
                                <span class="text-xs font-medium text-gray-700 w-14 text-right">{{ hoursDisplay(member.month_hours) }}</span>
                            </div>
                            <div class="flex items-center gap-2 mt-1 text-[10px] text-gray-400">
                                <span>This week: {{ hoursDisplay(member.week_hours) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="px-5 py-8 text-center text-sm text-gray-400">No team utilization data</p>
            </div>
        </div>

        <!-- ═══════ ACTIVITY FEED VIEW ═══════ -->
        <div v-if="props.canViewSensitiveSections && activeView === 'activity'" class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-3.5">
                    <h2 class="text-sm font-semibold text-gray-900">Recent Work Activity (Last 7 Days)</h2>
                </div>

                <div v-if="recentLogs.length" class="divide-y divide-gray-100">
                    <div v-for="log in recentLogs" :key="log.id" class="flex items-start gap-3 px-5 py-3">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-[#e8ddf0] text-[10px] font-bold text-[#4e1a77] shrink-0 mt-0.5">
                            {{ (log.user_name || '?').charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-medium text-gray-900">{{ log.user_name }}</span>
                                <span class="text-xs text-gray-400">worked on</span>
                                <Link :href="`/projects/${log.project_id}`" class="text-sm font-medium text-[#4e1a77] hover:underline">{{ log.project_name }}</Link>
                                <span :class="statusColors[log.status] || 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-0.5 text-[10px] font-medium capitalize">{{ log.status?.replace('_', ' ') }}</span>
                            </div>
                            <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                                <span>{{ formatDate(log.log_date) }}</span>
                                <span v-if="log.start_time">&middot; {{ formatTime(log.start_time) }} - {{ formatTime(log.end_time) }}</span>
                                <span>&middot; <strong class="text-gray-700">{{ hoursDisplay(log.hours_spent) }}</strong></span>
                            </div>
                            <p v-if="log.note" class="text-xs text-gray-600 mt-1">{{ log.note }}</p>
                            <p v-if="log.blocker" class="text-xs text-red-600 mt-0.5">Blocker: {{ log.blocker }}</p>
                        </div>
                    </div>
                </div>
                <p v-else class="px-5 py-8 text-center text-sm text-gray-400">No recent activity</p>
            </div>
        </div>
    </div>
</template>

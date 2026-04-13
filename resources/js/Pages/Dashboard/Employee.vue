<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import StageBadge from '@/Components/StageBadge.vue';
import TeamActivityReport from '@/Components/TeamActivityReport.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    myProjects: { type: [Array, Object], default: () => [] },
    myPlanners: { type: [Array, Object], default: () => [] },
    myBlockers: { type: [Array, Object], default: () => [] },
    canViewTeamActivityReport: { type: Boolean, default: false },
    projectsForReport: { type: [Array, Object], default: () => [] },
    teamMembersForReport: { type: [Array, Object], default: () => [] },
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name?.split(' ')[0] || '');

function getGreeting() {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
}

function isOverdue(date) {
    if (!date) return false;
    return new Date(date) < new Date();
}

const workTypeLabels = {
    frontend_work: 'Frontend',
    backend_work: 'Backend',
    figma: 'Figma',
    trigger_part: 'Trigger',
    data_mapping: 'Data Mapping',
    full_stack: 'Full Stack',
    other: 'Other',
};

const workTypeColors = {
    frontend_work: 'bg-blue-100 text-blue-700',
    backend_work: 'bg-green-100 text-green-700',
    figma: 'bg-pink-100 text-pink-700',
    trigger_part: 'bg-orange-100 text-orange-700',
    data_mapping: 'bg-purple-100 text-purple-700',
    full_stack: 'bg-indigo-100 text-indigo-700',
    other: 'bg-gray-100 text-gray-600',
};

const roleLabels = {
    developer: 'Developer',
    analyst: 'Analyst',
    analyst_testing: 'Analyst Testing',
    owner: 'Owner',
    contributor: 'Contributor',
};

const roleColors = {
    developer: 'bg-indigo-100 text-indigo-700 border-indigo-200',
    analyst: 'bg-amber-100 text-amber-700 border-amber-200',
    analyst_testing: 'bg-teal-100 text-teal-700 border-teal-200',
    owner: 'bg-yellow-100 text-yellow-700 border-yellow-200',
};

const activeCount = computed(() => (props.myProjects || []).filter(p => p.status === 'active').length);
const pendingTasks = computed(() => (props.myPlanners || []).length);
</script>

<template>
    <Head title="My Dashboard" />

    <div class="space-y-6">
        <!-- Welcome Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ getGreeting() }}, {{ userName }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    You have <span class="font-semibold text-[#4e1a77]">{{ activeCount }}</span> active project{{ activeCount !== 1 ? 's' : '' }}
                    <span v-if="pendingTasks"> and <span class="font-semibold text-orange-600">{{ pendingTasks }}</span> pending task{{ pendingTasks !== 1 ? 's' : '' }}</span>
                </p>
            </div>
            <Link href="/work-logs" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#3d1560] transition-colors">
                Log Work
            </Link>
        </div>

        <TeamActivityReport
            v-if="canViewTeamActivityReport"
            :projects="projectsForReport"
            :team-members="teamMembersForReport"
        />

        <!-- My Projects -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 px-5 py-3.5 flex items-center justify-between bg-gradient-to-r from-white to-[#f5f0ff]/30">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#4e1a77]/10">
                        <svg class="h-4 w-4 text-[#4e1a77]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                        </svg>
                    </div>
                    <h2 class="text-sm font-semibold text-gray-900">My Projects</h2>
                    <span class="rounded-full bg-[#4e1a77]/10 px-2 py-0.5 text-[10px] font-bold text-[#4e1a77]">{{ myProjects?.length || 0 }}</span>
                </div>
                <Link href="/projects" class="text-xs font-medium text-[#4e1a77] hover:underline">View all</Link>
            </div>
            <div class="divide-y divide-gray-100">
                <Link
                    v-for="p in myProjects"
                    :key="p.id"
                    :href="`/projects/${p.id}`"
                    class="flex items-center justify-between px-5 py-3 hover:bg-[#f5f0ff]/30 transition-colors group"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#4e1a77]/5 text-xs font-bold text-[#4e1a77] shrink-0 group-hover:bg-[#4e1a77]/10 transition-colors">
                            {{ p.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-gray-900 truncate group-hover:text-[#4e1a77] transition-colors">{{ p.name }}</p>
                                <span
                                    v-if="p.work_type"
                                    :class="workTypeColors[p.work_type] || 'bg-gray-100 text-gray-600'"
                                    class="hidden sm:inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
                                >
                                    {{ workTypeLabels[p.work_type] || p.work_type }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span
                                    class="text-[10px] font-medium capitalize rounded-full px-2 py-0.5 border"
                                    :class="roleColors[p.my_role] || 'bg-gray-100 text-gray-600 border-gray-200'"
                                >
                                    {{ roleLabels[p.my_role] || p.my_role }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 ml-3">
                        <StageBadge v-if="p.current_stage" :stage="p.current_stage" />
                        <StatusBadge :status="p.status" type="project" />
                    </div>
                </Link>
                <div v-if="!myProjects?.length" class="px-5 py-12 text-center">
                    <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-400">No projects assigned yet</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- My Pending Tasks -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-3.5 flex items-center gap-2 bg-gradient-to-r from-white to-orange-50/30">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-orange-100">
                        <svg class="h-4 w-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 011.15.586m-5.8 0c-.376.023-.75.05-1.124.08C8.003 3.011 7.5 4.138 7.5 5.25v.816" />
                        </svg>
                    </div>
                    <h2 class="text-sm font-semibold text-gray-900">My Pending Tasks</h2>
                    <span v-if="myPlanners?.length" class="ml-auto rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-bold text-orange-700">{{ myPlanners.length }}</span>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="p in myPlanners" :key="p.id" class="px-5 py-3 hover:bg-orange-50/20 transition-colors">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900">{{ p.title }}</p>
                            <StatusBadge :status="p.status" type="planner" />
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">{{ p.project_name }}</span>
                            <span v-if="p.due_date" class="text-[10px] font-medium" :class="isOverdue(p.due_date) ? 'text-red-600' : 'text-gray-400'">
                                Due: {{ p.due_date }}
                            </span>
                        </div>
                    </div>
                    <div v-if="!myPlanners?.length" class="px-5 py-12 text-center">
                        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-green-100">
                            <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="mt-2 text-sm font-medium text-gray-600">All caught up!</p>
                        <p class="text-xs text-gray-400">No pending tasks</p>
                    </div>
                </div>
            </div>

            <!-- My Blockers -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-3.5 flex items-center gap-2 bg-gradient-to-r from-white to-red-50/30">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-100">
                        <svg class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" />
                        </svg>
                    </div>
                    <h2 class="text-sm font-semibold text-gray-900">My Active Blockers</h2>
                    <span v-if="myBlockers?.length" class="ml-auto rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">{{ myBlockers.length }}</span>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="b in myBlockers" :key="b.id" class="px-5 py-3 hover:bg-red-50/20 transition-colors">
                        <p class="text-sm text-gray-900">{{ b.description }}</p>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600 mt-1.5">{{ b.project_name }}</span>
                    </div>
                    <div v-if="!myBlockers?.length" class="px-5 py-12 text-center">
                        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-green-100">
                            <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="mt-2 text-sm font-medium text-gray-600">No blockers!</p>
                        <p class="text-xs text-gray-400">You're good to go</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

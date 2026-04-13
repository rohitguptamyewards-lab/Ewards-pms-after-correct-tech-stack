<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatsCard from '@/Components/StatsCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PriorityBadge from '@/Components/PriorityBadge.vue';
import StageBadge from '@/Components/StageBadge.vue';
import TeamActivityReport from '@/Components/TeamActivityReport.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    totalProjects: Number,
    activeProjects: Number,
    onHoldProjects: Number,
    activeBlockers: { type: [Array, Object], default: () => [] },
    overduePlanners: { type: [Array, Object], default: () => [] },
    recentProjects: { type: [Array, Object], default: () => [] },
    canViewTeamActivityReport: { type: Boolean, default: false },
    projectsForReport: { type: [Array, Object], default: () => [] },
    teamMembersForReport: { type: [Array, Object], default: () => [] },
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name?.split(' ')[0] || 'Manager');

function getGreeting() {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
}
</script>

<template>
    <Head title="Manager Dashboard" />

    <div class="space-y-6">
        <!-- Welcome Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ getGreeting() }}, {{ userName }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">Here's your project overview for today</p>
            </div>
            <div class="flex gap-2">
                <Link href="/projects/create" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#3d1560] transition-colors">
                    + New Project
                </Link>
                <Link href="/work-logs" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Work Logs
                </Link>
            </div>
        </div>

        <!-- Team Activity Report -->
        <TeamActivityReport
            v-if="canViewTeamActivityReport"
            :projects="projectsForReport"
            :team-members="teamMembersForReport"
        />

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatsCard label="Total Projects" :value="totalProjects" color="blue" icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            <StatsCard label="Active" :value="activeProjects" color="green" icon="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
            <StatsCard label="On Hold" :value="onHoldProjects" color="yellow" icon="M14.25 9v6m-4.5 0V9M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            <StatsCard label="Active Blockers" :value="activeBlockers?.length || 0" color="red" icon="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Recent Projects -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-3.5 flex items-center justify-between bg-gradient-to-r from-white to-[#f5f0ff]/30">
                    <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#4e1a77]/10">
                            <svg class="h-4 w-4 text-[#4e1a77]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                        </div>
                        <h2 class="text-sm font-semibold text-gray-900">Recent Projects</h2>
                    </div>
                    <Link href="/projects" class="text-xs font-medium text-[#4e1a77] hover:underline">View all</Link>
                </div>
                <div class="divide-y divide-gray-100">
                    <Link
                        v-for="p in recentProjects"
                        :key="p.id"
                        :href="`/projects/${p.id}`"
                        class="flex items-center justify-between px-5 py-3 hover:bg-[#f5f0ff]/30 transition-colors group"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#4e1a77]/5 text-xs font-bold text-[#4e1a77] shrink-0 group-hover:bg-[#4e1a77]/10 transition-colors">
                                {{ p.name?.charAt(0)?.toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate group-hover:text-[#4e1a77] transition-colors">{{ p.name }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ p.owner_name }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 ml-3">
                            <StageBadge v-if="p.current_stage" :stage="p.current_stage" />
                            <PriorityBadge :priority="p.priority" />
                        </div>
                    </Link>
                    <div v-if="!recentProjects?.length" class="px-5 py-12 text-center">
                        <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                        </svg>
                        <p class="mt-2 text-sm text-gray-400">No projects yet</p>
                        <Link href="/projects/create" class="mt-2 inline-block text-xs font-medium text-[#4e1a77] hover:underline">Create your first project</Link>
                    </div>
                </div>
            </div>

            <!-- Active Blockers -->
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-3.5 flex items-center gap-2 bg-gradient-to-r from-white to-red-50/30">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-100">
                        <svg class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" />
                        </svg>
                    </div>
                    <h2 class="text-sm font-semibold text-gray-900">Active Blockers</h2>
                    <span v-if="activeBlockers?.length" class="ml-auto rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">{{ activeBlockers.length }}</span>
                </div>
                <div class="divide-y divide-gray-100">
                    <div v-for="b in activeBlockers" :key="b.id" class="px-5 py-3 hover:bg-red-50/20 transition-colors">
                        <p class="text-sm text-gray-900">{{ b.description }}</p>
                        <div class="flex items-center gap-2 mt-1.5">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">{{ b.project_name }}</span>
                            <span class="text-[10px] text-gray-400">{{ b.creator_name }}</span>
                        </div>
                    </div>
                    <div v-if="!activeBlockers?.length" class="px-5 py-12 text-center">
                        <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-green-100">
                            <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="mt-2 text-sm font-medium text-gray-600">All clear!</p>
                        <p class="text-xs text-gray-400">No active blockers</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overdue Planners -->
        <div v-if="overduePlanners?.length" class="rounded-xl border border-orange-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-orange-100 px-5 py-3.5 bg-gradient-to-r from-orange-50/80 to-white flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-orange-100">
                    <svg class="h-4 w-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="text-sm font-semibold text-orange-700">Overdue Planner Items</h2>
                <span class="ml-auto rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-bold text-orange-700">{{ overduePlanners.length }}</span>
            </div>
            <div class="divide-y divide-gray-100">
                <div v-for="p in overduePlanners" :key="p.id" class="flex items-center justify-between px-5 py-3 hover:bg-orange-50/20 transition-colors">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">{{ p.title }}</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">{{ p.project_name }}</span>
                            <span class="text-[10px] text-gray-400">{{ p.assignee_name }}</span>
                        </div>
                    </div>
                    <span class="shrink-0 ml-3 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Due: {{ p.due_date }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

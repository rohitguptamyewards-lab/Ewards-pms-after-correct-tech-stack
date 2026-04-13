<script setup>
import { computed, ref, reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PriorityBadge from '@/Components/PriorityBadge.vue';
import StageBadge from '@/Components/StageBadge.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

const { error: toastError } = useToast();

defineOptions({ layout: AppLayout });

const props = defineProps({
    projects: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    title: { type: String, default: 'Projects' },
    basePath: { type: String, default: '/projects' },
    boardPath: { type: String, default: '/projects/board' },
    showBoardToggle: { type: Boolean, default: true },
    showCreateButton: { type: Boolean, default: true },
    allProjects: { type: Array, default: () => [] },
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.role);
const canCreate = computed(() => ['manager', 'analyst_head', 'analyst'].includes(role.value));
const isManagerOrAnalyst = computed(() => ['manager', 'analyst_head', 'analyst'].includes(role.value));
const canEditPriority = computed(() => ['manager', 'analyst_head', 'analyst', 'senior_developer'].includes(role.value));
const isCustomWorkSection = computed(() => props.basePath === '/projects/custom-worklog');

// ── Stage Category Filter ──────────────────────────────
const stageCategoryFilter = ref('');

const stageCategories = {
    documentation: [
        'doc_yet_to_start','gpt_chat_wip','doc_wip','doc_under_review','doc_changes_required',
        'figma_yet_to_start','figma_design_wip','design_under_review',
    ],
    development: [
        'dev_yet_to_start','development_wip','dev_testing_wip','dev_changes_required',
        'bug_fixing_wip','bugs_fixed','vibe_code_shared','rnd',
    ],
    testing: [
        'testing_yet_to_start','testing_wip','ready_for_internal','testing_wip_internal',
        'bugs_reported_test_internal','bugs_reported_testing','bugs_reported_internal',
    ],
    live: [
        'live','ready_to_go_for_live','live_testing_yet_to_start','live_testing_wip',
        'bugs_reported_live','yet_to_announce_on_group','yet_to_put_on_cron','ticket_raised_for_revenue',
    ],
};

const filteredProjects = computed(() => {
    if (!stageCategoryFilter.value || !props.projects?.data) return props.projects?.data || [];
    const stages = stageCategories[stageCategoryFilter.value] || [];
    return props.projects.data.filter(p => stages.includes(p.current_stage));
});

const categoryCounts = computed(() => {
    const data = props.projects?.data || [];
    const counts = {};
    for (const [cat, stages] of Object.entries(stageCategories)) {
        counts[cat] = data.filter(p => stages.includes(p.current_stage)).length;
    }
    return counts;
});

// Filters
const filterForm = ref({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    priority: props.filters?.priority || '',
    work_type: props.filters?.work_type || '',
});

function applyFilters() {
    const params = {};
    Object.entries(filterForm.value).forEach(([k, v]) => { if (v) params[k] = v; });
    router.get(props.basePath, params, { preserveState: true, replace: true });
}

function clearFilters() {
    filterForm.value = { search: '', status: '', priority: '', work_type: '' };
    stageCategoryFilter.value = '';
    router.get(props.basePath, {}, { preserveState: true, replace: true });
}

// Team members for inline assignment
const teamMembers = ref([]);
const teamLoaded = ref(false);

async function loadTeamMembers() {
    if (teamLoaded.value) return;
    const { data } = await axios.get('/api/v1/team-members');
    teamMembers.value = data;
    teamLoaded.value = true;
}

// ── Inline Editing ────────────────────────────────
const editingCell = ref(null);
const editingPriorityProjectId = ref(null);

function startEditing(projectId, field) {
    loadTeamMembers();
    editingCell.value = `${projectId}-${field}`;
}

async function changeAssignment(project, field, newValue) {
    editingCell.value = null;
    const val = newValue ? parseInt(newValue) : null;
    try {
        await axios.put(`/api/v1/projects/${project.id}`, { [field]: val });
        router.reload({ only: ['projects'] });
    } catch (e) {
        toastError('Assignment update failed: ' + (e.response?.data?.message || e.message));
    }
}

function isEditing(projectId, field) {
    return editingCell.value === `${projectId}-${field}`;
}

function isEditingPriority(projectId) {
    return editingPriorityProjectId.value === projectId;
}

function startEditingPriority(projectId) {
    if (!canEditPriority.value) return;
    editingPriorityProjectId.value = projectId;
}

async function changePriority(project, newPriority) {
    editingPriorityProjectId.value = null;
    if (!newPriority || newPriority === project.priority) return;
    try {
        await axios.put(`/api/v1/projects/${project.id}`, { priority: newPriority });
        router.reload({ only: ['projects'] });
    } catch (e) {
        toastError('Priority update failed: ' + (e.response?.data?.message || e.message));
    }
}

// ── Inline Stage Change ────────────────────────────────
const editingStage = ref(null);

const stageOptions = [
    'doc_yet_to_start','gpt_chat_wip','doc_wip','figma_yet_to_start','figma_design_wip',
    'doc_under_review','doc_changes_required','dev_yet_to_start','development_wip',
    'testing_yet_to_start','testing_wip','dev_testing_wip','ready_for_internal',
    'testing_wip_internal','bugs_reported_test_internal','bugs_reported_testing',
    'bugs_reported_internal','live_testing_yet_to_start','ready_to_go_for_live',
    'live_testing_wip','bugs_reported_live','bug_fixing_wip','bugs_fixed',
    'dev_changes_required','live','need_to_discuss','on_hold','scrapped','rnd',
    'yet_to_announce_on_group','yet_to_put_on_cron','ticket_raised_for_revenue',
    'design_under_review','vibe_code_shared',
];

function stageLabelFor(val) {
    return val?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || val;
}

async function changeStage(project, newStage) {
    editingStage.value = null;
    if (!newStage || newStage === project.current_stage) return;
    try {
        await axios.put(`/api/v1/projects/${project.id}/stage`, { stage_name: newStage });
        router.reload({ only: ['projects'] });
    } catch (e) {
        toastError('Stage update failed: ' + (e.response?.data?.message || e.message));
    }
}

// ── Inline Project Name Editing ────────────────────────
const editingNameId = ref(null);
const editingNameValue = ref('');

function startEditingName(project) {
    if (!isManagerOrAnalyst.value) return;
    editingNameId.value = project.id;
    editingNameValue.value = project.name;
}

async function saveName(project) {
    const newName = editingNameValue.value.trim();
    editingNameId.value = null;
    if (!newName || newName === project.name) return;
    try {
        await axios.put(`/api/v1/projects/${project.id}`, { name: newName });
        router.reload({ only: ['projects'] });
    } catch (e) {
        toastError('Name update failed: ' + (e.response?.data?.message || e.message));
    }
}

function cancelEditName() {
    editingNameId.value = null;
}

// ── Global tooltip (renders via Teleport to avoid overflow clipping) ──────
const tooltip = reactive({ visible: false, text: '', x: 0, y: 0 });

function showTooltip(event, text) {
    tooltip.text = text;
    tooltip.visible = true;
    moveTooltip(event);
}

function moveTooltip(event) {
    tooltip.x = event.clientX;
    tooltip.y = event.clientY;
}

function hideTooltip() {
    tooltip.visible = false;
}

// ── Inline Date Editing ────────────────────────────────────
const editingDate = reactive({}); // { [projectId_field]: true }

function startEditDate(projectId, field) {
    editingDate[`${projectId}_${field}`] = true;
}

async function saveDate(project, field, value) {
    delete editingDate[`${project.id}_${field}`];
    if (value === (project[field] || '')) return;
    try {
        await axios.put(`/api/v1/projects/${project.id}`, { [field]: value || null });
        project[field] = value || null;
    } catch (e) { toastError('Date update failed: ' + (e.response?.data?.message || e.message)); }
}

// ── Subtask Tree (expand/collapse & lazy load children) ────
const expanded = reactive({});
const children = reactive({});
const loadingChildren = reactive({});

async function toggleExpand(project) {
    if (expanded[project.id]) {
        expanded[project.id] = false;
        return;
    }
    if (!children[project.id]) {
        loadingChildren[project.id] = true;
        try {
            const { data } = await axios.get(`/api/v1/projects/${project.id}/children`);
            children[project.id] = data;
        } catch (e) {
            console.error('Failed to load children', e);
            children[project.id] = [];
        }
        loadingChildren[project.id] = false;
    }
    expanded[project.id] = true;
}

// Recursive children expand (for nested subtasks)
const expandedChild = reactive({});
const childChildren = reactive({});
const loadingChildChildren = reactive({});

async function toggleExpandChild(child) {
    if (expandedChild[child.id]) {
        expandedChild[child.id] = false;
        return;
    }
    if (!childChildren[child.id]) {
        loadingChildChildren[child.id] = true;
        try {
            const { data } = await axios.get(`/api/v1/projects/${child.id}/children`);
            childChildren[child.id] = data;
        } catch (e) {
            childChildren[child.id] = [];
        }
        loadingChildChildren[child.id] = false;
    }
    expandedChild[child.id] = true;
}

// ── Comments Panel ────────────────────────────────
const commentPanelProject = ref(null);
const commentsList = ref([]);
const commentsLoading = ref(false);
const newComment = ref('');
const postingComment = ref(false);

async function openComments(project) {
    commentPanelProject.value = project;
    commentsLoading.value = true;
    try {
        const { data } = await axios.get(`/api/v1/projects/${project.id}/updates`);
        commentsList.value = data;
    } catch (e) {
        commentsList.value = [];
    }
    commentsLoading.value = false;
}

function closeComments() {
    commentPanelProject.value = null;
    commentsList.value = [];
    newComment.value = '';
}

async function postComment() {
    if (!newComment.value.trim() || !commentPanelProject.value) return;
    postingComment.value = true;
    try {
        const { data } = await axios.post(`/api/v1/projects/${commentPanelProject.value.id}/updates`, {
            content: newComment.value.trim(),
        });
        commentsList.value.unshift(data);
        newComment.value = '';
        router.reload({ only: ['projects'] });
    } catch (e) {
        toastError('Failed to post comment: ' + (e.response?.data?.message || e.message));
    }
    postingComment.value = false;
}

// ── Dependencies Modal ────────────────────────────────
const depModalProject = ref(null);
const depSearch = ref('');
const depSaving = ref(false);
const depSelected = ref([]);

function openDepModal(project) {
    depModalProject.value = project;
    const linked = project.linked_project_ids;
    depSelected.value = linked ? (Array.isArray(linked) ? [...linked] : JSON.parse(linked || '[]')) : [];
    depSearch.value = '';
}

function closeDepModal() {
    depModalProject.value = null;
}

const filteredDepProjects = computed(() => {
    if (!depSearch.value) return props.allProjects.slice(0, 20);
    const s = depSearch.value.toLowerCase();
    return props.allProjects.filter(p => p.name.toLowerCase().includes(s) && p.id !== depModalProject.value?.id).slice(0, 20);
});

function toggleDep(id) {
    const idx = depSelected.value.indexOf(id);
    if (idx >= 0) depSelected.value.splice(idx, 1);
    else depSelected.value.push(id);
}

async function saveDependencies() {
    if (!depModalProject.value) return;
    depSaving.value = true;
    try {
        await axios.put(`/api/v1/projects/${depModalProject.value.id}`, { linked_project_ids: depSelected.value });
        router.reload({ only: ['projects'] });
    } catch (e) {
        toastError('Failed to save dependencies: ' + (e.response?.data?.message || e.message));
    }
    depSaving.value = false;
    closeDepModal();
}

function getProjectName(id) {
    return props.allProjects.find(p => p.id === id)?.name || `#${id}`;
}

function getDependencies(project) {
    const linked = project.linked_project_ids;
    if (!linked) return [];
    return Array.isArray(linked) ? linked : JSON.parse(linked || '[]');
}

// ── Assignee Hover ────────────────────────────────
const hoveredAssignee = ref(null);

function getAssignees(project) {
    const list = [];
    if (project.owner_name) list.push({ name: project.owner_name, role: 'Owner', id: project.owner_id });
    if (project.analyst_name) list.push({ name: project.analyst_name, role: 'Analyst', id: project.analyst_id });
    if (project.developer_name) list.push({ name: project.developer_name, role: 'Developer', id: project.developer_id });
    if (project.analyst_testing_name) list.push({ name: project.analyst_testing_name, role: 'Tester', id: project.analyst_testing_id });
    return list;
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

const avatarColors = ['bg-purple-500', 'bg-blue-500', 'bg-green-500', 'bg-orange-500', 'bg-pink-500', 'bg-teal-500'];
function avatarColor(name) {
    if (!name) return avatarColors[0];
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return avatarColors[Math.abs(hash) % avatarColors.length];
}

// ── Helpers ────────────────────────────────
function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
}

function formatDateFull(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function isOverdue(dueDate) {
    if (!dueDate) return false;
    return new Date(dueDate) < new Date() && new Date(dueDate).toDateString() !== new Date().toDateString();
}

function truncateWords(text, wordLimit = 4) {
    if (!text) return '';
    const words = text.trim().split(/\s+/);
    if (words.length <= wordLimit) return text;
    return words.slice(0, wordLimit).join(' ') + '..';
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const diff = Date.now() - d.getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    const days = Math.floor(hrs / 24);
    if (days < 30) return `${days}d ago`;
    return formatDateFull(dateStr);
}

const workTypeLabels = {
    frontend_work: 'Frontend', backend_work: 'Backend', figma: 'Figma',
    trigger_part: 'Trigger', data_mapping: 'Data Mapping', full_stack: 'Full Stack', other: 'Other',
};

const workTypeColors = {
    frontend_work: 'bg-blue-100 text-blue-700', backend_work: 'bg-green-100 text-green-700',
    figma: 'bg-pink-100 text-pink-700', trigger_part: 'bg-orange-100 text-orange-700',
    data_mapping: 'bg-purple-100 text-purple-700', full_stack: 'bg-indigo-100 text-indigo-700',
    other: 'bg-gray-100 text-gray-600',
};

const statusOptions = [
    { value: '', label: 'All Status' }, { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' }, { value: 'on_hold', label: 'On Hold' },
];

const priorityOptions = [
    { value: '', label: 'All Priority' }, { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' }, { value: 'high', label: 'High' }, { value: 'critical', label: 'Critical' },
];

const workTypeOptions = [
    { value: '', label: 'All Work Types' },
    ...Object.entries(workTypeLabels).map(([v, l]) => ({ value: v, label: l })),
];
</script>

<template>
    <Head :title="title" />

    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold text-gray-900">{{ title }}</h1>
                <div v-if="showBoardToggle" class="flex rounded-lg border border-gray-300 overflow-hidden">
                    <span class="bg-[#4e1a77] px-3 py-1.5 text-xs font-medium text-white">Table</span>
                    <Link :href="boardPath" class="px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">Board</Link>
                </div>
            </div>
            <Link
                v-if="canCreate && showCreateButton"
                href="/projects/create"
                class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#3d1560] transition-colors"
            >
                + New Project
            </Link>
        </div>

        <!-- Stage Category Tabs -->
        <div class="flex gap-2 flex-wrap">
            <button
                @click="stageCategoryFilter = ''"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                :class="!stageCategoryFilter ? 'bg-[#4e1a77] text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'"
            >
                All <span class="ml-1 text-xs opacity-75">({{ projects?.data?.length || 0 }})</span>
            </button>
            <button
                @click="stageCategoryFilter = 'documentation'"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                :class="stageCategoryFilter === 'documentation' ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'"
            >
                Documentation <span class="ml-1 text-xs opacity-75">({{ categoryCounts.documentation }})</span>
            </button>
            <button
                @click="stageCategoryFilter = 'development'"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                :class="stageCategoryFilter === 'development' ? 'bg-green-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'"
            >
                Development <span class="ml-1 text-xs opacity-75">({{ categoryCounts.development }})</span>
            </button>
            <button
                @click="stageCategoryFilter = 'testing'"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                :class="stageCategoryFilter === 'testing' ? 'bg-orange-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'"
            >
                Testing <span class="ml-1 text-xs opacity-75">({{ categoryCounts.testing }})</span>
            </button>
            <button
                @click="stageCategoryFilter = 'live'"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                :class="stageCategoryFilter === 'live' ? 'bg-emerald-700 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'"
            >
                Live <span class="ml-1 text-xs opacity-75">({{ categoryCounts.live }})</span>
            </button>
        </div>

        <!-- Filters -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Search</label>
                    <input
                        v-model="filterForm.search"
                        placeholder="Search projects..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        @keyup.enter="applyFilters"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Status</label>
                    <select v-model="filterForm.status" @change="applyFilters" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Priority</label>
                    <select v-model="filterForm.priority" @change="applyFilters" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                        <option v-for="o in priorityOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Work Type</label>
                    <select v-model="filterForm.work_type" @change="applyFilters" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                        <option v-for="o in workTypeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <button @click="applyFilters" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560]">Filter</button>
                <button @click="clearFilters" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</button>
            </div>
        </div>

        <!-- Projects Table with Sticky First Column -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-x-auto relative">
            <table class="min-w-[1800px] w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="sticky left-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-[300px] min-w-[300px] border-r border-gray-200">Project</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-[160px] min-w-[160px]">Stage</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-[160px] min-w-[160px]">Assignees</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-[120px] min-w-[120px]">Start Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-[120px] min-w-[120px]">Due Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-[240px] min-w-[240px]">Comments</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-[200px] min-w-[200px]">Dependencies</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-[120px] min-w-[120px]">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-[130px] min-w-[130px]">Work Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-[110px] min-w-[110px]">Priority</th>
                        <th v-if="isCustomWorkSection" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-[130px] min-w-[130px]">Added By</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-[140px] min-w-[140px]">Planners</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-[100px] min-w-[100px]">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template v-for="p in filteredProjects" :key="p.id">
                        <!-- Main Project Row -->
                        <tr class="group hover:bg-[#f5f0ff]/30 transition-colors">
                            <!-- Project Name (Sticky) -->
                            <td class="sticky left-0 z-10 bg-white group-hover:bg-[#f5f0ff]/30 px-4 py-3 border-r border-gray-200 transition-colors">
                                <div class="flex items-center gap-2">
                                    <!-- Expand/Collapse Button -->
                                    <button
                                        v-if="p.children_count > 0"
                                        @click="toggleExpand(p)"
                                        class="shrink-0 w-5 h-5 flex items-center justify-center rounded text-gray-400 hover:text-[#4e1a77] hover:bg-purple-50 transition-colors"
                                    >
                                        <svg v-if="loadingChildren[p.id]" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        <svg v-else class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-90': expanded[p.id] }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                                    </button>
                                    <div v-else class="w-5 shrink-0"></div>

                                    <div class="min-w-0 flex-1">
                                        <!-- Inline Name Editing -->
                                        <template v-if="editingNameId === p.id">
                                            <input
                                                v-model="editingNameValue"
                                                @keyup.enter="saveName(p)"
                                                @keyup.escape="cancelEditName"
                                                @blur="saveName(p)"
                                                class="w-full rounded border border-[#4e1a77] px-2 py-1 text-sm font-medium focus:ring-1 focus:ring-[#4e1a77]"
                                                autofocus
                                            />
                                        </template>
                                        <template v-else>
                                            <div class="flex items-center gap-1.5">
                                                <Link :href="`/projects/${p.id}`" class="text-sm font-medium text-[#4e1a77] hover:underline truncate">
                                                    {{ p.name }}
                                                </Link>
                                                <button
                                                    v-if="isManagerOrAnalyst"
                                                    @click.stop="startEditingName(p)"
                                                    class="opacity-0 group-hover:opacity-100 shrink-0 text-gray-400 hover:text-[#4e1a77] transition-opacity"
                                                    title="Edit name"
                                                >
                                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                                                </button>
                                            </div>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <p class="text-xs text-gray-400">{{ p.owner_name }}</p>
                                                <span v-if="p.children_count > 0" class="text-[10px] bg-purple-100 text-purple-600 px-1.5 py-0.5 rounded-full font-medium">{{ p.children_count }} subtask{{ p.children_count > 1 ? 's' : '' }}</span>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Add Subtask Button -->
                                    <Link
                                        v-if="canCreate"
                                        :href="`/projects/create?parent_id=${p.id}`"
                                        class="opacity-0 group-hover:opacity-100 shrink-0 text-gray-400 hover:text-[#4e1a77] transition-opacity"
                                        title="Add subtask"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    </Link>
                                </div>
                            </td>

                            <!-- Stage -->
                            <td class="px-4 py-3">
                                <template v-if="editingStage === p.id">
                                    <select :value="p.current_stage || ''" @change="changeStage(p, $event.target.value)" @blur="editingStage = null" class="rounded border border-[#4e1a77] px-2 py-1 text-xs focus:ring-1 focus:ring-[#4e1a77] w-full max-w-[180px]" autofocus>
                                        <option value="">No stage</option>
                                        <option v-for="s in stageOptions" :key="s" :value="s">{{ stageLabelFor(s) }}</option>
                                    </select>
                                </template>
                                <template v-else>
                                    <span class="cursor-pointer hover:opacity-75" @click="editingStage = p.id" title="Click to change stage">
                                        <StageBadge v-if="p.current_stage" :stage="p.current_stage" />
                                        <span v-else class="text-xs text-gray-400 hover:text-[#4e1a77]">Set stage</span>
                                    </span>
                                </template>
                            </td>

                            <!-- Assignees -->
                            <td class="px-4 py-3">
                                <div class="flex items-center -space-x-2">
                                    <div
                                        v-for="(a, ai) in getAssignees(p).slice(0, 4)"
                                        :key="ai"
                                        class="relative group/avatar"
                                    >
                                        <div
                                            :class="avatarColor(a.name)"
                                            class="h-7 w-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white ring-2 ring-white cursor-pointer"
                                            :title="a.name + ' (' + a.role + ')'"
                                        >
                                            {{ getInitials(a.name) }}
                                        </div>
                                        <!-- Hover Card -->
                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 pb-2 hidden group-hover/avatar:block z-20">
                                            <div class="bg-white rounded-lg shadow-lg border border-gray-200 px-3 py-2 whitespace-nowrap text-center">
                                                <p class="text-sm font-medium text-gray-900">{{ a.name }}</p>
                                                <p class="text-xs text-gray-500">{{ a.role }}</p>
                                                <Link
                                                    :href="`/reports/workers?member=${a.id}`"
                                                    class="mt-1 inline-block text-xs text-[#4e1a77] hover:underline font-medium"
                                                >
                                                    View Profile
                                                </Link>
                                            </div>
                                        </div>
                                    </div>
                                    <span v-if="!getAssignees(p).length" class="text-xs text-gray-400">-</span>
                                </div>
                            </td>

                            <!-- Start Date -->
                            <td class="px-4 py-3">
                                <input
                                    v-if="editingDate[`${p.id}_start_date`]"
                                    type="date"
                                    :value="p.start_date || ''"
                                    @change="saveDate(p, 'start_date', $event.target.value)"
                                    @blur="saveDate(p, 'start_date', $event.target.value)"
                                    class="rounded border border-[#4e1a77] px-1.5 py-0.5 text-xs focus:ring-1 focus:ring-[#4e1a77] w-[110px]"
                                    autofocus
                                />
                                <span v-else @click="startEditDate(p.id, 'start_date')" class="text-xs cursor-pointer rounded px-1 py-0.5 hover:bg-gray-100 transition-colors" :class="p.start_date ? 'text-gray-600' : 'text-gray-400'">
                                    {{ p.start_date ? formatDate(p.start_date) : 'Set date' }}
                                </span>
                            </td>

                            <!-- Due Date -->
                            <td class="px-4 py-3">
                                <input
                                    v-if="editingDate[`${p.id}_due_date`]"
                                    type="date"
                                    :value="p.due_date || ''"
                                    @change="saveDate(p, 'due_date', $event.target.value)"
                                    @blur="saveDate(p, 'due_date', $event.target.value)"
                                    class="rounded border border-[#4e1a77] px-1.5 py-0.5 text-xs focus:ring-1 focus:ring-[#4e1a77] w-[110px]"
                                    autofocus
                                />
                                <span v-else @click="startEditDate(p.id, 'due_date')" class="text-xs cursor-pointer rounded px-1 py-0.5 hover:bg-gray-100 transition-colors" :class="isOverdue(p.due_date) ? 'text-red-600 font-medium' : (p.due_date ? 'text-gray-600' : 'text-gray-400')">
                                    {{ p.due_date ? formatDate(p.due_date) : 'Set date' }}
                                    <span v-if="isOverdue(p.due_date)" class="text-[10px] text-red-500 ml-0.5">overdue</span>
                                </span>
                            </td>

                            <!-- Comments -->
                            <td class="px-4 py-3">
                                <button
                                    @click="openComments(p)"
                                    class="text-left group/comment w-full max-w-[208px]"
                                >
                                    <div
                                        v-if="p.last_comment"
                                        class="flex items-start gap-1"
                                        @mouseenter="p.last_comment.trim().split(/\s+/).length > 4 && showTooltip($event, p.last_comment)"
                                        @mousemove="p.last_comment.trim().split(/\s+/).length > 4 && moveTooltip($event)"
                                        @mouseleave="hideTooltip"
                                    >
                                        <svg class="h-3.5 w-3.5 text-gray-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
                                        <div>
                                            <p class="text-xs text-gray-600 group-hover/comment:text-[#4e1a77] whitespace-nowrap">{{ truncateWords(p.last_comment) }}</p>
                                            <p class="text-[10px] text-gray-400">{{ timeAgo(p.last_comment_at) }}</p>
                                        </div>
                                    </div>
                                    <div v-else class="flex items-center gap-1 text-xs text-gray-400 hover:text-[#4e1a77]">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                        Add comment
                                    </div>
                                </button>
                                <span v-if="p.comment_count > 0" class="text-[10px] text-gray-400 ml-1">({{ p.comment_count }})</span>
                            </td>

                            <!-- Dependencies -->
                            <td class="px-4 py-3 overflow-hidden">
                                <div class="flex items-center gap-1 max-w-[168px]">
                                    <template v-if="getDependencies(p).length">
                                        <div class="flex flex-wrap gap-1">
                                            <span
                                                v-for="depId in getDependencies(p).slice(0, 2)"
                                                :key="depId"
                                                class="inline-block truncate max-w-[100px] rounded bg-amber-50 border border-amber-200 px-1.5 py-0.5 text-[10px] text-amber-700 font-medium"
                                                :title="getProjectName(depId)"
                                            >
                                                {{ getProjectName(depId) }}
                                            </span>
                                            <span v-if="getDependencies(p).length > 2" class="text-[10px] text-gray-400">+{{ getDependencies(p).length - 2 }}</span>
                                        </div>
                                    </template>
                                    <button
                                        v-if="isManagerOrAnalyst"
                                        @click="openDepModal(p)"
                                        class="shrink-0 text-gray-400 hover:text-[#4e1a77] transition-colors"
                                        :title="getDependencies(p).length ? 'Edit dependencies' : 'Add dependency'"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.54a4.5 4.5 0 00-1.242-7.244l-4.5-4.5a4.5 4.5 0 00-6.364 6.364L4.343 8.57" /></svg>
                                    </button>
                                    <span v-if="!getDependencies(p).length && !isManagerOrAnalyst" class="text-xs text-gray-400">-</span>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-3"><StatusBadge :status="p.status" type="project" /></td>

                            <!-- Work Type -->
                            <td class="px-4 py-3">
                                <span v-if="p.work_type" :class="workTypeColors[p.work_type] || 'bg-gray-100 text-gray-600'" class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase">
                                    {{ workTypeLabels[p.work_type] || p.work_type }}
                                </span>
                                <span v-else class="text-xs text-gray-400">-</span>
                            </td>

                            <!-- Priority -->
                            <td class="px-4 py-3">
                                <template v-if="isEditingPriority(p.id)">
                                    <select :value="p.priority" @change="changePriority(p, $event.target.value)" @blur="editingPriorityProjectId = null" class="rounded border border-[#4e1a77] px-2 py-1 text-xs focus:ring-1 focus:ring-[#4e1a77]" autofocus>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="critical">Critical</option>
                                    </select>
                                </template>
                                <template v-else>
                                    <div class="inline-block" :class="{ 'cursor-pointer': canEditPriority }" @click="startEditingPriority(p.id)">
                                        <PriorityBadge :priority="p.priority" />
                                    </div>
                                </template>
                            </td>

                            <!-- Added By -->
                            <td v-if="isCustomWorkSection" class="px-4 py-3">
                                <span class="text-sm text-gray-600">{{ p.created_by_name || '—' }}</span>
                            </td>

                            <!-- Planners -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-[#4e1a77] h-1.5 rounded-full transition-all" :style="{ width: (p.planner_count ? (p.planner_done_count / p.planner_count * 100) : 0) + '%' }" />
                                    </div>
                                    <span class="text-xs text-gray-600 font-medium">{{ p.planner_done_count || 0 }}/{{ p.planner_count || 0 }}</span>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a v-if="p.document_link" :href="p.document_link" target="_blank" title="Document" class="text-blue-500 hover:text-blue-700">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                    </a>
                                    <a v-if="p.ai_chat_link" :href="p.ai_chat_link" target="_blank" title="AI Chat" class="text-purple-500 hover:text-purple-700">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                                    </a>
                                    <Link v-if="canCreate" :href="`/projects/${p.id}/replicate`" title="Replicate project" class="text-gray-400 hover:text-[#4e1a77] transition-colors">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" /></svg>
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <!-- ═══ Level 1 Children (Subtasks) ═══ -->
                        <template v-if="expanded[p.id] && children[p.id]">
                            <template v-for="c1 in children[p.id]" :key="'c1-' + c1.id">
                                <tr class="group bg-purple-50/30 hover:bg-purple-50/60 transition-colors">
                                    <td class="sticky left-0 z-10 bg-purple-50/30 group-hover:bg-purple-50/60 px-4 py-2.5 border-r border-gray-200 transition-colors">
                                        <div class="flex items-center gap-2 pl-6">
                                            <div class="w-4 border-l-2 border-b-2 border-purple-200 h-4 -mt-3 shrink-0 rounded-bl"></div>
                                            <button
                                                v-if="c1.children_count > 0"
                                                @click="toggleExpandChild(c1)"
                                                class="shrink-0 w-5 h-5 flex items-center justify-center rounded text-gray-400 hover:text-[#4e1a77] hover:bg-purple-100 transition-colors"
                                            >
                                                <svg v-if="loadingChildChildren[c1.id]" class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                <svg v-else class="h-3 w-3 transition-transform" :class="{ 'rotate-90': expandedChild[c1.id] }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                                            </button>
                                            <div v-else class="w-5 shrink-0"></div>
                                            <div class="min-w-0 flex-1">
                                                <Link :href="`/projects/${c1.id}`" class="text-sm font-medium text-[#4e1a77] hover:underline truncate block">{{ c1.name }}</Link>
                                                <p class="text-xs text-gray-400">{{ c1.owner_name }}</p>
                                            </div>
                                            <Link v-if="canCreate" :href="`/projects/create?parent_id=${c1.id}`" class="opacity-0 group-hover:opacity-100 shrink-0 text-gray-400 hover:text-[#4e1a77]" title="Add sub-subtask">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                            </Link>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5"><StageBadge v-if="c1.current_stage" :stage="c1.current_stage" /><span v-else class="text-xs text-gray-400">-</span></td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex -space-x-1">
                                            <div v-for="(a, ai) in getAssignees(c1).slice(0, 3)" :key="ai" :class="avatarColor(a.name)" class="h-6 w-6 rounded-full flex items-center justify-center text-[9px] font-bold text-white ring-2 ring-white" :title="a.name">{{ getInitials(a.name) }}</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5"><span class="text-xs text-gray-600">{{ formatDate(c1.start_date) || '-' }}</span></td>
                                    <td class="px-4 py-2.5"><span class="text-xs" :class="isOverdue(c1.due_date) ? 'text-red-600 font-medium' : 'text-gray-600'">{{ formatDate(c1.due_date) || '-' }}</span></td>
                                    <td class="px-4 py-2.5"><button @click="openComments(c1)" class="text-xs text-gray-500 hover:text-[#4e1a77]">{{ c1.comment_count || 0 }} comment{{ (c1.comment_count || 0) !== 1 ? 's' : '' }}</button></td>
                                    <td class="px-4 py-2.5"><span class="text-xs text-gray-400">-</span></td>
                                    <td class="px-4 py-2.5"><StatusBadge :status="c1.status" type="project" /></td>
                                    <td class="px-4 py-2.5"><span v-if="c1.work_type" :class="workTypeColors[c1.work_type]" class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase">{{ workTypeLabels[c1.work_type] || c1.work_type }}</span><span v-else class="text-xs text-gray-400">-</span></td>
                                    <td class="px-4 py-2.5"><PriorityBadge :priority="c1.priority" /></td>
                                    <td v-if="isCustomWorkSection" class="px-4 py-2.5"></td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-1"><div class="w-12 bg-gray-200 rounded-full h-1"><div class="bg-[#4e1a77] h-1 rounded-full" :style="{ width: (c1.planner_count ? (c1.planner_done_count / c1.planner_count * 100) : 0) + '%' }" /></div><span class="text-[10px] text-gray-500">{{ c1.planner_done_count || 0 }}/{{ c1.planner_count || 0 }}</span></div>
                                    </td>
                                    <td class="px-4 py-2.5"></td>
                                </tr>

                                <!-- ═══ Level 2 Children ═══ -->
                                <template v-if="expandedChild[c1.id] && childChildren[c1.id]">
                                    <tr v-for="c2 in childChildren[c1.id]" :key="'c2-' + c2.id" class="group bg-purple-50/50 hover:bg-purple-100/40 transition-colors">
                                        <td class="sticky left-0 z-10 bg-purple-50/50 group-hover:bg-purple-100/40 px-4 py-2 border-r border-gray-200 transition-colors">
                                            <div class="flex items-center gap-2 pl-14">
                                                <div class="w-4 border-l-2 border-b-2 border-purple-300 h-4 -mt-3 shrink-0 rounded-bl"></div>
                                                <div class="w-5 shrink-0"></div>
                                                <div class="min-w-0 flex-1">
                                                    <Link :href="`/projects/${c2.id}`" class="text-sm text-[#4e1a77] hover:underline truncate block">{{ c2.name }}</Link>
                                                    <p class="text-[10px] text-gray-400">{{ c2.owner_name }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2"><StageBadge v-if="c2.current_stage" :stage="c2.current_stage" /><span v-else class="text-xs text-gray-400">-</span></td>
                                        <td class="px-4 py-2">
                                            <div class="flex -space-x-1">
                                                <div v-for="(a, ai) in getAssignees(c2).slice(0, 3)" :key="ai" :class="avatarColor(a.name)" class="h-5 w-5 rounded-full flex items-center justify-center text-[8px] font-bold text-white ring-2 ring-white" :title="a.name">{{ getInitials(a.name) }}</div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2"><span class="text-xs text-gray-600">{{ formatDate(c2.start_date) || '-' }}</span></td>
                                        <td class="px-4 py-2"><span class="text-xs" :class="isOverdue(c2.due_date) ? 'text-red-600' : 'text-gray-600'">{{ formatDate(c2.due_date) || '-' }}</span></td>
                                        <td class="px-4 py-2"><span class="text-xs text-gray-400">{{ c2.comment_count || 0 }}</span></td>
                                        <td class="px-4 py-2"><span class="text-xs text-gray-400">-</span></td>
                                        <td class="px-4 py-2"><StatusBadge :status="c2.status" type="project" /></td>
                                        <td class="px-4 py-2"><span v-if="c2.work_type" :class="workTypeColors[c2.work_type]" class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase">{{ workTypeLabels[c2.work_type] || c2.work_type }}</span><span v-else class="text-xs text-gray-400">-</span></td>
                                        <td class="px-4 py-2"><PriorityBadge :priority="c2.priority" /></td>
                                        <td v-if="isCustomWorkSection" class="px-4 py-2"></td>
                                        <td class="px-4 py-2"><span class="text-[10px] text-gray-500">{{ c2.planner_done_count || 0 }}/{{ c2.planner_count || 0 }}</span></td>
                                        <td class="px-4 py-2"></td>
                                    </tr>
                                </template>
                            </template>
                        </template>
                    </template>
                </tbody>
            </table>
            <div v-if="!filteredProjects?.length" class="px-5 py-12 text-center text-sm text-gray-400">
                <template v-if="stageCategoryFilter">
                    No projects in "{{ stageCategoryFilter }}" category.
                    <button @click="stageCategoryFilter = ''" class="text-[#4e1a77] underline ml-1">Show all</button>
                </template>
                <template v-else>
                    No projects found.
                    <Link v-if="canCreate && showCreateButton" href="/projects/create" class="text-[#4e1a77] underline ml-1">Create one</Link>
                </template>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="projects.links?.length > 3" class="flex justify-center gap-1">
            <Link
                v-for="link in projects.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded px-3 py-1 text-xs"
                :class="link.active ? 'bg-[#4e1a77] text-white' : link.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300'"
                v-html="link.label"
                preserve-scroll
            />
        </div>
    </div>

    <!-- ═══════════ Comments Slide-Over Panel ═══════════ -->
    <Teleport to="body">
        <div v-if="commentPanelProject" class="fixed inset-0 z-50 flex justify-end">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/30" @click="closeComments"></div>

            <!-- Panel -->
            <div class="relative w-full max-w-md bg-white shadow-xl flex flex-col animate-slide-in">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Comments</h3>
                        <p class="text-xs text-gray-500 mt-0.5 truncate max-w-[280px]">{{ commentPanelProject.name }}</p>
                    </div>
                    <button @click="closeComments" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Comments List -->
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                    <div v-if="commentsLoading" class="text-center py-8">
                        <svg class="mx-auto h-6 w-6 animate-spin text-[#4e1a77]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    </div>
                    <template v-else>
                        <div v-if="!commentsList.length" class="text-center py-8 text-gray-400 text-sm">
                            No comments yet. Start the conversation!
                        </div>
                        <div
                            v-for="comment in commentsList"
                            :key="comment.id"
                            class="rounded-lg bg-gray-50 p-3"
                        >
                            <div class="flex items-center gap-2 mb-1.5">
                                <div :class="avatarColor(comment.author_name || 'System')" class="h-6 w-6 rounded-full flex items-center justify-center text-[9px] font-bold text-white">
                                    {{ getInitials(comment.author_name || 'System') }}
                                </div>
                                <span class="text-sm font-medium text-gray-900">{{ comment.author_name || 'System' }}</span>
                                <span class="text-[10px] text-gray-400 ml-auto">{{ timeAgo(comment.created_at) }}</span>
                            </div>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap pl-8">{{ comment.content }}</p>
                            <span v-if="comment.source && comment.source !== 'manual'" class="ml-8 mt-1 inline-block text-[10px] bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded">{{ comment.source }}</span>
                        </div>
                    </template>
                </div>

                <!-- New Comment Input -->
                <div class="border-t border-gray-200 px-5 py-3">
                    <div class="flex gap-2">
                        <textarea
                            v-model="newComment"
                            @keydown.ctrl.enter="postComment"
                            placeholder="Write a comment..."
                            rows="2"
                            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm resize-none focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        ></textarea>
                        <button
                            @click="postComment"
                            :disabled="!newComment.trim() || postingComment"
                            class="self-end rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560] disabled:opacity-50"
                        >
                            <svg v-if="postingComment" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
                        </button>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">Ctrl+Enter to send</p>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ═══════════ Dependencies Modal ═══════════ -->
    <Teleport to="body">
        <div v-if="depModalProject" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/30" @click="closeDepModal"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 max-h-[70vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Dependencies</h3>
                        <p class="text-xs text-gray-500 mt-0.5 truncate">{{ depModalProject.name }}</p>
                    </div>
                    <button @click="closeDepModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="px-5 py-3 border-b border-gray-100">
                    <input v-model="depSearch" type="text" placeholder="Search projects..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                </div>

                <!-- Selected -->
                <div v-if="depSelected.length" class="px-5 pt-3 flex flex-wrap gap-1.5">
                    <span v-for="id in depSelected" :key="id" class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2.5 py-1 text-xs font-medium text-purple-700">
                        {{ getProjectName(id) }}
                        <button @click="toggleDep(id)" class="text-purple-400 hover:text-purple-700">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </span>
                </div>

                <!-- Project List -->
                <div class="flex-1 overflow-y-auto px-5 py-3 space-y-1">
                    <button
                        v-for="dp in filteredDepProjects"
                        :key="dp.id"
                        @click="toggleDep(dp.id)"
                        class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2"
                        :class="depSelected.includes(dp.id) ? 'bg-purple-50 text-purple-700' : 'text-gray-700 hover:bg-gray-50'"
                    >
                        <svg v-if="depSelected.includes(dp.id)" class="h-4 w-4 text-purple-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <svg v-else class="h-4 w-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><circle cx="12" cy="12" r="9" /></svg>
                        <span class="truncate">{{ dp.name }}</span>
                    </button>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-200 px-5 py-3">
                    <button @click="closeDepModal" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button @click="saveDependencies" :disabled="depSaving" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560] disabled:opacity-50">
                        {{ depSaving ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Global tooltip — rendered in body to escape all overflow contexts -->
    <Teleport to="body">
        <div
            v-if="tooltip.visible"
            class="fixed z-[9999] pointer-events-none"
            :style="{ left: tooltip.x + 12 + 'px', top: tooltip.y - 8 + 'px', transform: 'translateY(-100%)' }"
        >
            <div class="rounded-lg bg-gray-900 px-3 py-2 text-xs text-white shadow-xl max-w-[280px] whitespace-pre-wrap break-words">
                {{ tooltip.text }}
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
@keyframes slide-in {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}
.animate-slide-in {
    animation: slide-in 0.2s ease-out;
}
</style>

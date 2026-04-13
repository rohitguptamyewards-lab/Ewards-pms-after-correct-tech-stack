<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PriorityBadge from '@/Components/PriorityBadge.vue';
import StageBadge from '@/Components/StageBadge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

const { error: toastError, success: toastSuccess } = useToast();

defineOptions({ layout: AppLayout });

const props = defineProps({
    project: Object,
    planners: { type: Array, default: () => [] },
    workers: { type: Array, default: () => [] },
    updates: { type: Array, default: () => [] },
    blockers: { type: Array, default: () => [] },
    tickets: { type: Array, default: () => [] },
    stageHistory: { type: Array, default: () => [] },
    transfers: { type: Array, default: () => [] },
    teamMembers: { type: [Array, Object], default: () => [] },
    attachments: { type: Array, default: () => [] },
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.role);
const currentUserId = computed(() => page.props.auth?.user?.id);
const isManagerOrAnalyst = computed(() => ['manager', 'analyst_head', 'analyst'].includes(role.value));
const canEditPriority = computed(() => ['manager', 'analyst_head', 'analyst', 'senior_developer'].includes(role.value));
const canViewProjectWorklogReports = computed(() => ['manager', 'analyst_head', 'senior_developer'].includes(role.value));

// Parse tags
const parsedTags = computed(() => {
    const t = props.project?.tags;
    if (!t) return [];
    if (Array.isArray(t)) return t;
    if (typeof t === 'string') {
        try { const arr = JSON.parse(t); return Array.isArray(arr) ? arr : []; } catch { return []; }
    }
    return [];
});

// ── Editable Project Name ──────────────────────────────
const editingName = ref(false);
const editNameValue = ref('');

function startEditName() {
    editNameValue.value = props.project?.name || '';
    editingName.value = true;
    nextTick(() => {
        const input = document.getElementById('edit-project-name');
        if (input) { input.focus(); input.select(); }
    });
}

async function saveName() {
    const trimmed = editNameValue.value.trim();
    if (!trimmed || trimmed === props.project?.name) {
        editingName.value = false;
        return;
    }
    try {
        await axios.put(`/api/v1/projects/${props.project.id}`, { name: trimmed });
        window.location.reload();
    } catch (e) {
        console.error('Failed to update name', e);
    }
    editingName.value = false;
}

// ── Tabs (left panel) ──────────────────────────────────
const activeTab = ref('details');
const tabs = computed(() => {
    const allTabs = [
        { key: 'details', label: 'Details' },
        { key: 'planner', label: 'Planner' },
        { key: 'workers', label: 'Workers' },
        { key: 'attachments', label: 'Attachments' },
        { key: 'tickets', label: 'Tickets' },
        { key: 'blockers', label: 'Blockers' },
        { key: 'release-notes', label: 'Release Notes' },
    ];
    if (canViewProjectWorklogReports.value) {
        allTabs.splice(2, 0, { key: 'report', label: 'Report' });
    }
    return allTabs;
});

// Local reactive state
const localPlanners = ref([...(props.planners || [])]);
const localUpdates = ref([...(props.updates || [])]);
const localWorkers = ref([...(props.workers || [])]);
const localBlockers = ref([...(props.blockers || [])]);
const localTickets = ref([...(props.tickets || [])]);
const localStageHistory = ref([...(props.stageHistory || [])]);

// Work type display helpers
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
const taskTypeLabels = {
    new_project: 'New Project', addition_on_existing: 'Addition on Existing',
    bug_fix: 'Bug Fix', data_mapping: 'Data Mapping', integration: 'Integration', other: 'Other',
};

// ── Report Tab Data ────────────────────────────────────
const reportData = ref(null);
const reportLoading = ref(false);
const expandedEmployee = ref(null);

async function loadReportData() {
    if (!canViewProjectWorklogReports.value || reportData.value) return;
    reportLoading.value = true;
    try {
        const { data } = await axios.get(`/api/v1/reports/projects/${props.project.id}/worklogs`);
        reportData.value = data;
    } catch (e) { console.error('Failed to load report data', e); }
    reportLoading.value = false;
}

watch(activeTab, (val) => { if (val === 'report') loadReportData(); });

const totalProjectHours = computed(() => {
    if (!reportData.value?.employeeSummary) return 0;
    return reportData.value.employeeSummary.reduce((sum, e) => sum + parseFloat(e.total_hours || 0), 0);
});
const totalLogEntries = computed(() => {
    if (!reportData.value?.employeeSummary) return 0;
    return reportData.value.employeeSummary.reduce((sum, e) => sum + parseInt(e.log_count || 0), 0);
});

function getEmployeeLogs(userId) {
    return reportData.value?.worklogs?.filter(w => w.user_id === userId) || [];
}
function toggleEmployee(userId) {
    expandedEmployee.value = expandedEmployee.value === userId ? null : userId;
}

// ── Inline Field Editing ───────────────────────────────
const editingField = ref(null);

function startEditField(field) {
    if (field === 'priority') {
        if (canEditPriority.value) editingField.value = field;
        return;
    }
    if (isManagerOrAnalyst.value) editingField.value = field;
}

async function updateAssignment(field, value) {
    editingField.value = null;
    try {
        await axios.put(`/api/v1/projects/${props.project.id}`, { [field]: value ? parseInt(value) : null });
        window.location.reload();
    } catch (e) { console.error('Failed to update assignment', e); }
}

async function updateProjectField(field, value) {
    editingField.value = null;
    try {
        await axios.put(`/api/v1/projects/${props.project.id}`, { [field]: value });
        window.location.reload();
    } catch (e) { console.error('Failed to update field', e); }
}

// ── Stage ───────────────────────────────────────────────
const stageForm = ref({ stage_name: '' });
const showStageHistory = ref(false);

async function updateStage() {
    if (!stageForm.value.stage_name) return;
    await axios.put(`/api/v1/projects/${props.project.id}/stage`, stageForm.value);
    const { data } = await axios.get(`/api/v1/projects/${props.project.id}/stage/history`);
    localStageHistory.value = data;
    stageForm.value = { stage_name: '' };
    window.location.reload();
}

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

// ── Planner ─────────────────────────────────────────────
const plannerForm = ref({ title: '', description: '', assigned_to: '', due_date: '', milestone_flag: false });
const editingPlannerId = ref(null);
const editPlannerForm = ref({ title: '', description: '', assigned_to: '', due_date: '', milestone_flag: false });

function startEditPlanner(p) {
    editingPlannerId.value = p.id;
    editPlannerForm.value = {
        title: p.title || '',
        description: p.description || '',
        assigned_to: p.assigned_to || '',
        due_date: p.due_date || '',
        milestone_flag: p.milestone_flag || false,
    };
}

async function saveEditPlanner(planner) {
    const id = editingPlannerId.value;
    editingPlannerId.value = null;
    try {
        const { data } = await axios.put(`/api/v1/planners/${id}`, {
            ...editPlannerForm.value,
            assigned_to: editPlannerForm.value.assigned_to ? parseInt(editPlannerForm.value.assigned_to) : null,
        });
        Object.assign(planner, data);
    } catch (e) { console.error('Planner update failed', e); }
}

async function addPlanner() {
    if (!plannerForm.value.title) return;
    const { data } = await axios.post('/api/v1/planners', {
        project_id: props.project.id,
        ...plannerForm.value,
        assigned_to: plannerForm.value.assigned_to ? parseInt(plannerForm.value.assigned_to) : null,
    });
    localPlanners.value.push(data);
    plannerForm.value = { title: '', description: '', assigned_to: '', due_date: '', milestone_flag: false };
}

async function updatePlannerStatus(planner, status) {
    await axios.put(`/api/v1/planners/${planner.id}`, { status });
    planner.status = status;
}

async function deletePlanner(id) {
    await axios.delete(`/api/v1/planners/${id}`);
    localPlanners.value = localPlanners.value.filter(p => p.id !== id);
}

// ── Updates / Activity ─────────────────────────────────
const updateForm = ref({ content: '', source: 'manual' });
const postingUpdate = ref(false);
const updateError = ref('');
const mentionQuery = ref('');
const showMentionDropdown = ref(false);
const mentionCursorPos = ref(0);
const updateTextarea = ref(null);

const mentionSuggestions = computed(() => {
    if (!mentionQuery.value) return [];
    const q = mentionQuery.value.toLowerCase();
    const members = Array.isArray(props.teamMembers) ? props.teamMembers : [];
    return members.filter(m => m.name?.toLowerCase().includes(q)).slice(0, 6);
});

function onUpdateInput(e) {
    const val = e.target.value;
    const pos = e.target.selectionStart;
    const textBefore = val.substring(0, pos);
    const atMatch = textBefore.match(/@(\w*)$/);
    if (atMatch) {
        mentionQuery.value = atMatch[1];
        showMentionDropdown.value = true;
        mentionCursorPos.value = pos;
    } else {
        showMentionDropdown.value = false;
        mentionQuery.value = '';
    }
}

function insertMention(member) {
    const val = updateForm.value.content;
    const pos = mentionCursorPos.value;
    const textBefore = val.substring(0, pos);
    const textAfter = val.substring(pos);
    const atStart = textBefore.lastIndexOf('@');
    updateForm.value.content = textBefore.substring(0, atStart) + `@${member.name} ` + textAfter;
    showMentionDropdown.value = false;
    mentionQuery.value = '';
    nextTick(() => { if (updateTextarea.value) updateTextarea.value.focus(); });
}

async function addUpdate() {
    if (!updateForm.value.content.trim()) return;
    postingUpdate.value = true;
    updateError.value = '';
    try {
        const { data } = await axios.post(`/api/v1/projects/${props.project.id}/updates`, {
            content: updateForm.value.content,
            source: updateForm.value.source,
        });
        localUpdates.value.unshift(data);
        updateForm.value = { content: '', source: 'manual' };
    } catch (e) {
        updateError.value = e.response?.data?.message || 'Failed to post update.';
    }
    postingUpdate.value = false;
}

// ── Workers ─────────────────────────────────────────────
const workerForm = ref({ user_ids: [] });
const showWorkerDropdown = ref(false);
const addingContributors = ref(false);

const existingWorkerIds = computed(() => new Set(localWorkers.value.map(w => w.user_id)));

const availableMembers = computed(() => {
    const members = Array.isArray(props.teamMembers) ? props.teamMembers : Object.values(props.teamMembers || {});
    return members.filter(m => !existingWorkerIds.value.has(m.id));
});

function toggleWorkerSelection(id) {
    const idx = workerForm.value.user_ids.indexOf(id);
    if (idx === -1) workerForm.value.user_ids.push(id);
    else workerForm.value.user_ids.splice(idx, 1);
}

function selectedMemberName(id) {
    const members = Array.isArray(props.teamMembers) ? props.teamMembers : Object.values(props.teamMembers || {});
    return members.find(m => m.id === id)?.name || id;
}

async function addContributor() {
    if (!workerForm.value.user_ids.length) return;
    addingContributors.value = true;
    try {
        await Promise.all(
            workerForm.value.user_ids.map(id =>
                axios.post(`/api/v1/projects/${props.project.id}/workers/contributor`, { user_id: id })
            )
        );
        const { data } = await axios.get(`/api/v1/projects/${props.project.id}/workers`);
        localWorkers.value = data;
        workerForm.value = { user_ids: [] };
        showWorkerDropdown.value = false;
    } catch (e) { console.error('Failed to add contributors', e); }
    addingContributors.value = false;
}

async function removeWorker(userId) {
    await axios.delete(`/api/v1/projects/${props.project.id}/workers/${userId}`);
    localWorkers.value = localWorkers.value.filter(w => w.user_id !== userId);
}

// ── Blockers ────────────────────────────────────────────
const blockerForm = ref({ description: '' });

async function addBlocker() {
    if (!blockerForm.value.description) return;
    const { data } = await axios.post(`/api/v1/projects/${props.project.id}/blockers`, blockerForm.value);
    localBlockers.value.unshift(data);
    blockerForm.value = { description: '' };
}

async function resolveBlocker(id) {
    await axios.put(`/api/v1/blockers/${id}/resolve`);
    const b = localBlockers.value.find(x => x.id === id);
    if (b) b.status = 'resolved';
}

// ── Tickets ─────────────────────────────────────────────
const ticketForm = ref({ ticket_id: '', source_type: 'external' });

async function addTicket() {
    if (!ticketForm.value.ticket_id) return;
    const { data } = await axios.post(`/api/v1/projects/${props.project.id}/tickets`, ticketForm.value);
    localTickets.value.unshift(data);
    ticketForm.value = { ticket_id: '', source_type: 'external' };
}

async function removeTicket(id) {
    await axios.delete(`/api/v1/tickets/${id}`);
    localTickets.value = localTickets.value.filter(t => t.id !== id);
}

// ── Attachments ────────────────────────────────────────
const localAttachments = ref([...(props.attachments || [])]);
const uploadingFile = ref(false);
const fileInput = ref(null);

async function uploadAttachment(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploadingFile.value = true;
    const formData = new FormData();
    formData.append('file', file);
    try {
        const { data } = await axios.post(`/api/v1/projects/${props.project.id}/attachments`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        localAttachments.value.unshift(data);
    } catch (e) {
        toastError('Upload failed: ' + (e.response?.data?.message || e.message));
    }
    uploadingFile.value = false;
    if (fileInput.value) fileInput.value.value = '';
}

async function deleteAttachment(id) {
    if (!confirm('Delete this attachment?')) return;
    try {
        await axios.delete(`/api/v1/attachments/${id}`);
        localAttachments.value = localAttachments.value.filter(a => a.id !== id);
    } catch (e) { console.error('Delete failed', e); }
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function fileIcon(mimeType) {
    if (!mimeType) return 'file';
    if (mimeType.startsWith('image/')) return 'image';
    if (mimeType.includes('pdf')) return 'pdf';
    if (mimeType.includes('sheet') || mimeType.includes('excel') || mimeType.includes('csv')) return 'sheet';
    if (mimeType.includes('doc') || mimeType.includes('word')) return 'doc';
    return 'file';
}

const fileIconColors = {
    image: 'text-pink-500 bg-pink-50', pdf: 'text-red-500 bg-red-50',
    sheet: 'text-green-500 bg-green-50', doc: 'text-blue-500 bg-blue-50',
    file: 'text-gray-500 bg-gray-50',
};

// ── Workers: auto-assigned employees ───────────────────
const assignedEmployees = computed(() => {
    const assigned = [];
    const seen = new Set();
    const p = props.project;
    const allMembers = Array.isArray(props.teamMembers) ? props.teamMembers : [];

    if (p?.owner_id) { assigned.push({ user_id: p.owner_id, user_name: p.owner_name, role: 'owner', source: 'project' }); seen.add(p.owner_id); }
    if (p?.analyst_id && !seen.has(p.analyst_id)) {
        const m = allMembers.find(x => x.id === p.analyst_id);
        assigned.push({ user_id: p.analyst_id, user_name: p.analyst_name || m?.name || 'Unknown', role: 'analyst', source: 'project' }); seen.add(p.analyst_id);
    }
    if (p?.developer_id && !seen.has(p.developer_id)) {
        const m = allMembers.find(x => x.id === p.developer_id);
        assigned.push({ user_id: p.developer_id, user_name: p.developer_name || m?.name || 'Unknown', role: 'developer', source: 'project' }); seen.add(p.developer_id);
    }
    if (p?.analyst_testing_id && !seen.has(p.analyst_testing_id)) {
        const m = allMembers.find(x => x.id === p.analyst_testing_id);
        assigned.push({ user_id: p.analyst_testing_id, user_name: p.analyst_testing_name || m?.name || 'Unknown', role: 'analyst_testing', source: 'project' }); seen.add(p.analyst_testing_id);
    }
    for (const w of localWorkers.value) {
        if (!seen.has(w.user_id)) {
            assigned.push({ user_id: w.user_id, user_name: w.user_name, role: w.role, source: 'worker', user_email: w.user_email }); seen.add(w.user_id);
        }
    }
    return assigned;
});

// Transfer flow
const transferForm = ref({ from_user: '', to_user: '', field: '', notes: '' });
const showTransferModal = ref(false);

function startTransfer(employee) {
    transferForm.value = {
        from_user: employee.user_id, from_name: employee.user_name, from_role: employee.role,
        to_user: '', field: employee.source === 'project' ? getRoleField(employee.role) : '', notes: '',
    };
    showTransferModal.value = true;
}

function getRoleField(role) {
    return { analyst: 'analyst_id', developer: 'developer_id', analyst_testing: 'analyst_testing_id' }[role] || '';
}

async function executeTransfer() {
    if (!transferForm.value.to_user || !transferForm.value.notes) return;
    try {
        const field = transferForm.value.field;
        if (field) {
            await axios.put(`/api/v1/projects/${props.project.id}`, { [field]: parseInt(transferForm.value.to_user) });
        }
        if (!field) {
            try { await axios.delete(`/api/v1/projects/${props.project.id}/workers/${transferForm.value.from_user}`); } catch (_) {}
            await axios.post(`/api/v1/projects/${props.project.id}/workers/contributor`, { user_id: parseInt(transferForm.value.to_user) });
        }
        await axios.post(`/api/v1/projects/${props.project.id}/transfers`, { to_user: parseInt(transferForm.value.to_user), notes: transferForm.value.notes });
        showTransferModal.value = false;
        window.location.reload();
    } catch (e) {
        toastError('Transfer failed: ' + (e.response?.data?.message || e.message));
    }
}

const roleColors = {
    owner: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    analyst: 'bg-blue-100 text-blue-700 border-blue-200',
    developer: 'bg-green-100 text-green-700 border-green-200',
    analyst_testing: 'bg-orange-100 text-orange-700 border-orange-200',
    contributor: 'bg-gray-100 text-gray-600 border-gray-200',
};

const statusColors = {
    done: 'bg-green-100 text-green-700',
    in_progress: 'bg-blue-100 text-blue-700',
    blocked: 'bg-red-100 text-red-700',
};

// Utility functions
function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}
function formatTime(t) {
    if (!t || !t.includes(':')) return '';
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    if (isNaN(hr)) return t;
    return `${hr > 12 ? hr - 12 : hr}:${m} ${hr >= 12 ? 'PM' : 'AM'}`;
}
function timeAgo(d) {
    if (!d) return '';
    const diff = Date.now() - new Date(d).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}
function highlightMentions(text) {
    if (!text) return '';
    const escaped = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return escaped.replace(/@(\w[\w\s]*?\w)(?=\s|$|[.,!?])/g, '<span class="inline-flex rounded bg-[#f5f0ff] px-1 text-[#4e1a77] font-medium">@$1</span>');
}
function isOverdue(date) {
    if (!date) return false;
    return new Date(date) < new Date();
}
function hoursDisplay(h) {
    const hrs = Math.floor(h);
    const mins = Math.round((h - hrs) * 60);
    return mins === 0 ? `${hrs}h` : `${hrs}h ${mins}m`;
}
function avatarColor(id) {
    const colors = ['bg-blue-500','bg-green-500','bg-yellow-500','bg-red-500','bg-purple-500','bg-pink-500','bg-indigo-500','bg-teal-500'];
    return colors[(id || 0) % colors.length];
}

// Active blockers count for badge
const activeBlockersCount = computed(() => localBlockers.value.filter(x => x.status === 'active').length);

// ── Release Notes ──────────────────────────────────────
const releaseNotes = ref([]);
const releaseNotesLoaded = ref(false);
const releaseNotesLoading = ref(false);
const releaseNotesError = ref(false);
const showCreateNote = ref(false);
const newNote = ref({ title: '', description: '', links: [] });
const newNoteFiles = ref(null);
const creatingNote = ref(false);
const newLinkForm = ref({ label: '', url: '' });
const canCreateNote = computed(() => !!role.value);
const canDeleteNote = computed(() => ['manager', 'analyst_head'].includes(role.value));

async function loadReleaseNotes() {
    if (releaseNotesLoaded.value) return;
    releaseNotesLoading.value = true;
    releaseNotesError.value = false;
    try {
        const { data } = await axios.get(`/api/v1/projects/${props.project.id}/release-notes`);
        releaseNotes.value = Array.isArray(data) ? data : [];
    } catch (e) {
        console.error('Failed to load release notes', e);
        releaseNotesError.value = true;
    }
    releaseNotesLoading.value = false;
    releaseNotesLoaded.value = true;
}

watch(activeTab, (val) => { if (val === 'release-notes') loadReleaseNotes(); });

function addNoteLink() {
    if (!newLinkForm.value.url) return;
    newNote.value.links.push({ ...newLinkForm.value });
    newLinkForm.value = { label: '', url: '' };
}

function removeNoteLink(idx) {
    newNote.value.links.splice(idx, 1);
}

async function createReleaseNote() {
    if (!newNote.value.title) return;
    creatingNote.value = true;
    try {
        const formData = new FormData();
        formData.append('title', newNote.value.title);
        if (newNote.value.description) formData.append('description', newNote.value.description);

        // Append files
        if (newNoteFiles.value?.files) {
            for (const file of newNoteFiles.value.files) {
                formData.append('files[]', file);
            }
        }

        // Append links
        newNote.value.links.forEach((link, i) => {
            formData.append(`links[${i}][label]`, link.label || '');
            formData.append(`links[${i}][url]`, link.url);
        });

        const { data } = await axios.post(`/api/v1/projects/${props.project.id}/release-notes`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        releaseNotes.value.unshift(data);
        newNote.value = { title: '', description: '', links: [] };
        if (newNoteFiles.value) newNoteFiles.value.value = '';
        showCreateNote.value = false;
    } catch (e) {
        toastError('Failed to create: ' + (e.response?.data?.message || e.message));
    }
    creatingNote.value = false;
}

async function deleteReleaseNote(id) {
    if (!confirm('Delete this release note? This requires lead member permission.')) return;
    try {
        await axios.delete(`/api/v1/release-notes/${id}`);
        releaseNotes.value = releaseNotes.value.filter(n => n.id !== id);
    } catch (e) {
        toastError('Delete failed: ' + (e.response?.data?.message || e.message));
    }
}

async function deleteNoteFile(noteId, fileId) {
    if (!confirm('Delete this file?')) return;
    try {
        await axios.delete(`/api/v1/release-note-files/${fileId}`);
        const note = releaseNotes.value.find(n => n.id === noteId);
        if (note) note.files = note.files.filter(f => f.id !== fileId);
    } catch (e) { toastError('Delete failed: ' + (e.response?.data?.message || e.message)); }
}

async function deleteNoteLink(noteId, linkId) {
    if (!confirm('Delete this link?')) return;
    try {
        await axios.delete(`/api/v1/release-note-links/${linkId}`);
        const note = releaseNotes.value.find(n => n.id === noteId);
        if (note) note.links = note.links.filter(l => l.id !== linkId);
    } catch (e) { toastError('Delete failed: ' + (e.response?.data?.message || e.message)); }
}
</script>

<template>
    <Head :title="project?.name || 'Project'" />

    <div class="min-h-[calc(100vh-4rem)]">
        <!-- ── TOP BAR: Breadcrumb ── -->
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
            <Link href="/projects" class="hover:text-[#4e1a77] transition-colors">
                <svg class="h-4 w-4 inline -mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                Projects
            </Link>
            <svg class="h-3.5 w-3.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            <span class="text-gray-900 font-medium truncate max-w-sm">{{ project?.name }}</span>
        </div>

        <!-- ── TITLE ROW (editable on hover) ── -->
        <div class="mb-4 group/title">
            <div v-if="editingName" class="flex items-center gap-3">
                <input
                    id="edit-project-name"
                    v-model="editNameValue"
                    @keydown.enter="saveName"
                    @keydown.escape="editingName = false"
                    class="text-2xl font-bold text-gray-900 border-b-2 border-[#4e1a77] bg-transparent outline-none py-1 flex-1"
                />
                <button @click="saveName" class="rounded-lg bg-[#4e1a77] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#3d1560]">Save</button>
                <button @click="editingName = false" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
            </div>
            <div v-else class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900">{{ project?.name }}</h1>
                <button
                    v-if="isManagerOrAnalyst"
                    @click="startEditName"
                    class="opacity-0 group-hover/title:opacity-100 transition-opacity rounded-md p-1.5 text-gray-400 hover:text-[#4e1a77] hover:bg-[#f5f0ff]"
                    title="Edit project name"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                </button>
                <!-- Badges inline with title -->
                <div class="flex items-center gap-2 ml-auto">
                    <StageBadge v-if="project?.current_stage" :stage="project.current_stage" />
                    <StatusBadge :status="project?.status" type="project" />
                    <!-- Editable Priority -->
                    <template v-if="canEditPriority && editingField === 'priority'">
                        <select
                            :value="project?.priority"
                            @change="updateProjectField('priority', $event.target.value)"
                            @blur="editingField = null"
                            class="rounded-lg border border-[#4e1a77] px-2 py-1 text-xs font-medium focus:ring-1 focus:ring-[#4e1a77]"
                            autofocus
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </template>
                    <div v-else @click="canEditPriority && startEditField('priority')" :class="{ 'cursor-pointer': canEditPriority }">
                        <PriorityBadge :priority="project?.priority" />
                    </div>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-1">
                Created {{ formatDate(project?.created_at) }}
                <span v-if="project?.owner_name"> &middot; Owner: <strong class="text-gray-700">{{ project.owner_name }}</strong></span>
            </p>
        </div>

        <!-- ── MAIN LAYOUT: Two columns ── -->
        <div class="flex gap-5 items-start">
            <!-- ════ LEFT PANEL (main content) ════ -->
            <div class="flex-1 min-w-0 space-y-4">

                <!-- ── Property Grid (ClickUp-style fields) ── -->
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 divide-x divide-y divide-gray-100">
                        <!-- Stage -->
                        <div class="p-3.5 col-span-2 sm:col-span-1">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Stage</p>
                            <div class="flex items-center gap-2">
                                <StageBadge v-if="project?.current_stage" :stage="project.current_stage" />
                                <button @click="showStageHistory = !showStageHistory" class="text-gray-400 hover:text-[#4e1a77]" title="Change stage">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Analyst -->
                        <div class="p-3.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Analyst</p>
                            <template v-if="editingField === 'analyst_id'">
                                <select :value="project?.analyst_id" @change="updateAssignment('analyst_id', $event.target.value)" @blur="editingField = null" class="w-full rounded border border-[#4e1a77] px-2 py-1 text-xs focus:ring-1 focus:ring-[#4e1a77]" autofocus>
                                    <option value="">Unassigned</option>
                                    <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                            </template>
                            <div v-else class="flex items-center gap-2 cursor-pointer group/field" @click="startEditField('analyst_id')">
                                <div v-if="project?.analyst_id" class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold text-white" :class="avatarColor(project.analyst_id)">{{ (project.analyst_name || '?').charAt(0).toUpperCase() }}</div>
                                <span class="text-sm text-gray-700 group-hover/field:text-[#4e1a77]">{{ project?.analyst_name || '-' }}</span>
                            </div>
                        </div>

                        <!-- Developer -->
                        <div class="p-3.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Developer</p>
                            <template v-if="editingField === 'developer_id'">
                                <select :value="project?.developer_id" @change="updateAssignment('developer_id', $event.target.value)" @blur="editingField = null" class="w-full rounded border border-[#4e1a77] px-2 py-1 text-xs focus:ring-1 focus:ring-[#4e1a77]" autofocus>
                                    <option value="">Unassigned</option>
                                    <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                            </template>
                            <div v-else class="flex items-center gap-2 cursor-pointer group/field" @click="startEditField('developer_id')">
                                <div v-if="project?.developer_id" class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold text-white" :class="avatarColor(project.developer_id)">{{ (project.developer_name || '?').charAt(0).toUpperCase() }}</div>
                                <span class="text-sm text-gray-700 group-hover/field:text-[#4e1a77]">{{ project?.developer_name || '-' }}</span>
                            </div>
                        </div>

                        <!-- Analyst Testing -->
                        <div class="p-3.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Tester</p>
                            <template v-if="editingField === 'analyst_testing_id'">
                                <select :value="project?.analyst_testing_id" @change="updateAssignment('analyst_testing_id', $event.target.value)" @blur="editingField = null" class="w-full rounded border border-[#4e1a77] px-2 py-1 text-xs focus:ring-1 focus:ring-[#4e1a77]" autofocus>
                                    <option value="">Unassigned</option>
                                    <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                            </template>
                            <div v-else class="flex items-center gap-2 cursor-pointer group/field" @click="startEditField('analyst_testing_id')">
                                <div v-if="project?.analyst_testing_id" class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold text-white" :class="avatarColor(project.analyst_testing_id)">{{ (project.analyst_testing_name || '?').charAt(0).toUpperCase() }}</div>
                                <span class="text-sm text-gray-700 group-hover/field:text-[#4e1a77]">{{ project?.analyst_testing_name || '-' }}</span>
                            </div>
                        </div>

                        <!-- Work Type -->
                        <div class="p-3.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Work Type</p>
                            <span v-if="project?.work_type" :class="workTypeColors[project.work_type] || 'bg-gray-100 text-gray-600'" class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase">
                                {{ workTypeLabels[project.work_type] || project.work_type }}
                            </span>
                            <span v-else class="text-sm text-gray-400">-</span>
                        </div>

                        <!-- Task Type -->
                        <div class="p-3.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Task Type</p>
                            <span class="text-sm text-gray-700">{{ taskTypeLabels[project?.task_type] || project?.task_type || '-' }}</span>
                        </div>

                        <!-- Start Date -->
                        <div class="p-3.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Start Date</p>
                            <input
                                v-if="editingField === 'start_date'"
                                type="date"
                                :value="project?.start_date || ''"
                                @change="updateProjectField('start_date', $event.target.value || null)"
                                @blur="updateProjectField('start_date', $event.target.value || null)"
                                class="rounded border border-[#4e1a77] px-2 py-1 text-sm focus:ring-1 focus:ring-[#4e1a77]"
                                autofocus
                            />
                            <span v-else @click="startEditField('start_date')" class="text-sm cursor-pointer rounded px-1 py-0.5 hover:bg-gray-100 transition-colors" :class="project?.start_date ? 'text-gray-700' : 'text-gray-400'">
                                {{ project?.start_date ? formatDate(project.start_date) : 'Set date' }}
                            </span>
                        </div>

                        <!-- Due Date -->
                        <div class="p-3.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Due Date</p>
                            <input
                                v-if="editingField === 'due_date'"
                                type="date"
                                :value="project?.due_date || ''"
                                @change="updateProjectField('due_date', $event.target.value || null)"
                                @blur="updateProjectField('due_date', $event.target.value || null)"
                                class="rounded border border-[#4e1a77] px-2 py-1 text-sm focus:ring-1 focus:ring-[#4e1a77]"
                                autofocus
                            />
                            <span v-else @click="startEditField('due_date')" class="text-sm cursor-pointer rounded px-1 py-0.5 hover:bg-gray-100 transition-colors" :class="project?.due_date && isOverdue(project.due_date) ? 'text-red-600 font-medium' : (project?.due_date ? 'text-gray-700' : 'text-gray-400')">
                                {{ project?.due_date ? formatDate(project.due_date) : 'Set date' }}
                                <span v-if="project?.due_date && isOverdue(project.due_date)" class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-600">OVERDUE</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ── Stage Change Panel (collapsible) ── -->
                <div v-if="showStageHistory" class="rounded-xl border border-[#ddd0f7] bg-[#faf8ff] p-4 shadow-sm space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-[#4e1a77]">Change Stage</h3>
                        <button @click="showStageHistory = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="flex gap-2">
                        <select v-model="stageForm.stage_name" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                            <option value="">Select new stage...</option>
                            <option v-for="s in stageOptions" :key="s" :value="s">{{ stageLabelFor(s) }}</option>
                        </select>
                        <button @click="updateStage" :disabled="!stageForm.stage_name" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560] disabled:opacity-50">Update</button>
                    </div>
                    <div v-if="localStageHistory?.length" class="space-y-1.5 pt-2 border-t border-[#ddd0f7]">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">History</p>
                        <div v-for="s in localStageHistory.slice(0, 8)" :key="s.id" class="flex items-center gap-2 text-xs text-gray-500">
                            <StageBadge :stage="s.stage_name" />
                            <span class="text-gray-400">&middot;</span>
                            <span>{{ s.updater_name }}</span>
                            <span class="text-gray-400">&middot;</span>
                            <span>{{ timeAgo(s.created_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- ── Description & Links ── -->
                <div v-if="project?.description || project?.objective || project?.ticket_link || project?.document_link || project?.ai_chat_link || parsedTags.length" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm space-y-3">
                    <div v-if="project?.description">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1">Description</p>
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ project.description }}</p>
                    </div>
                    <div v-if="project?.objective">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1">Objective</p>
                        <p class="text-sm text-gray-700">{{ project.objective }}</p>
                    </div>
                    <div v-if="parsedTags.length">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1">Tags</p>
                        <div class="flex flex-wrap gap-1">
                            <span v-for="tag in parsedTags" :key="tag" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-600">{{ tag }}</span>
                        </div>
                    </div>
                    <div v-if="project?.ticket_link || project?.document_link || project?.ai_chat_link" class="flex flex-wrap gap-2 pt-2 border-t border-gray-100">
                        <a v-if="project?.ticket_link" :href="project.ticket_link" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:border-[#4e1a77] hover:text-[#4e1a77] transition-colors">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" /></svg>
                            Ticket
                        </a>
                        <a v-if="project?.document_link" :href="project.document_link" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:border-blue-500 hover:text-blue-600 transition-colors">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            Document
                        </a>
                        <a v-if="project?.ai_chat_link" :href="project.ai_chat_link" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-purple-600 hover:border-purple-500 transition-colors">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                            AI Chat
                        </a>
                    </div>
                </div>

                <!-- ── Sub-Tabs ── -->
                <div class="border-b border-gray-200">
                    <nav class="flex gap-0.5 overflow-x-auto">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            @click="activeTab = tab.key"
                            class="px-3.5 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
                            :class="activeTab === tab.key
                                ? 'border-[#4e1a77] text-[#4e1a77]'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        >
                            {{ tab.label }}
                            <span v-if="tab.key === 'blockers' && activeBlockersCount" class="ml-1 rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-600">{{ activeBlockersCount }}</span>
                            <span v-if="tab.key === 'attachments' && localAttachments.length" class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500">{{ localAttachments.length }}</span>
                        </button>
                    </nav>
                </div>

                <!-- ═══ DETAILS TAB ═══ -->
                <div v-if="activeTab === 'details'" class="text-sm text-gray-500 py-6 text-center">
                    Project details are shown above. Use the tabs to manage planner, workers, attachments, tickets, and blockers.
                </div>

                <!-- ═══ PLANNER TAB ═══ -->
                <div v-if="activeTab === 'planner'" class="space-y-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap gap-2">
                            <input v-model="plannerForm.title" placeholder="Task title *" class="flex-1 min-w-[180px] rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" @keydown.enter="addPlanner" />
                            <select v-model="plannerForm.assigned_to" class="rounded-lg border border-gray-300 px-2 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                                <option value="">Assignee</option>
                                <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                            <input v-model="plannerForm.due_date" type="date" class="rounded-lg border border-gray-300 px-2 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                            <label class="flex items-center gap-1.5 text-xs text-gray-500">
                                <input v-model="plannerForm.milestone_flag" type="checkbox" class="rounded border-gray-300 text-[#4e1a77] focus:ring-[#4e1a77]" />
                                Milestone
                            </label>
                            <button @click="addPlanner" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560]">Add</button>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                        <div
                            v-for="p in localPlanners"
                            :key="p.id"
                            :class="{ 'bg-red-50/50': p.due_date && isOverdue(p.due_date) && p.status !== 'done' }"
                        >
                            <!-- Edit form -->
                            <div v-if="editingPlannerId === p.id" class="px-4 py-3 space-y-2">
                                <div class="flex flex-wrap gap-2">
                                    <input v-model="editPlannerForm.title" placeholder="Task title *" class="flex-1 min-w-[180px] rounded-lg border border-[#4e1a77] px-3 py-1.5 text-sm focus:ring-1 focus:ring-[#4e1a77]" />
                                    <select v-model="editPlannerForm.assigned_to" class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                                        <option value="">Assignee</option>
                                        <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                                    </select>
                                    <input v-model="editPlannerForm.due_date" type="date" class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                                    <label class="flex items-center gap-1.5 text-xs text-gray-500">
                                        <input v-model="editPlannerForm.milestone_flag" type="checkbox" class="rounded border-gray-300 text-[#4e1a77] focus:ring-[#4e1a77]" />
                                        Milestone
                                    </label>
                                </div>
                                <textarea v-model="editPlannerForm.description" rows="2" placeholder="Description (optional)" class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm resize-none focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                                <div class="flex justify-end gap-2">
                                    <button @click="editingPlannerId = null" class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                                    <button @click="saveEditPlanner(p)" :disabled="!editPlannerForm.title" class="rounded-lg bg-[#4e1a77] px-3 py-1 text-xs font-medium text-white hover:bg-[#3d1560] disabled:opacity-50">Save</button>
                                </div>
                            </div>

                            <!-- Display row -->
                            <div v-else class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span v-if="p.milestone_flag" class="text-yellow-500 shrink-0" title="Milestone">&#9733;</span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ p.title }}</p>
                                        <p class="text-xs text-gray-400">
                                            {{ p.assignee_name || 'Unassigned' }}
                                            <span v-if="p.due_date"> &middot; Due: {{ p.due_date }}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <select :value="p.status" @change="updatePlannerStatus(p, $event.target.value)" class="rounded border border-gray-200 px-2 py-1 text-xs focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="done">Done</option>
                                        <option value="blocked">Blocked</option>
                                    </select>
                                    <button @click="startEditPlanner(p)" class="text-gray-400 hover:text-[#4e1a77] transition-colors" title="Edit">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
                                    </button>
                                    <button @click="deletePlanner(p.id)" class="text-gray-300 hover:text-red-500 transition-colors" title="Delete">&#10005;</button>
                                </div>
                            </div>
                        </div>
                        <p v-if="!localPlanners?.length" class="px-4 py-8 text-center text-sm text-gray-400">No planner items yet</p>
                    </div>
                </div>

                <!-- ═══ REPORT TAB ═══ -->
                <div v-if="canViewProjectWorklogReports && activeTab === 'report'" class="space-y-3">
                    <div v-if="reportLoading" class="flex items-center justify-center py-12">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#4e1a77]"></div>
                        <span class="ml-3 text-sm text-gray-500">Loading report data...</span>
                    </div>

                    <template v-if="reportData && !reportLoading">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-xl border border-[#ddd0f7] bg-[#f5f0ff] px-3 py-2.5">
                                <p class="text-[10px] font-medium text-gray-500 uppercase">Total Hours</p>
                                <p class="text-xl font-bold text-[#4e1a77]">{{ hoursDisplay(totalProjectHours) }}</p>
                            </div>
                            <div class="rounded-xl border border-green-100 bg-green-50 px-3 py-2.5">
                                <p class="text-[10px] font-medium text-gray-500 uppercase">Contributors</p>
                                <p class="text-xl font-bold text-green-700">{{ reportData.employeeSummary?.length || 0 }}</p>
                            </div>
                            <div class="rounded-xl border border-blue-100 bg-blue-50 px-3 py-2.5">
                                <p class="text-[10px] font-medium text-gray-500 uppercase">Log Entries</p>
                                <p class="text-xl font-bold text-blue-700">{{ totalLogEntries }}</p>
                            </div>
                            <div class="rounded-xl border border-yellow-100 bg-yellow-50 px-3 py-2.5">
                                <p class="text-[10px] font-medium text-gray-500 uppercase">Planner</p>
                                <p class="text-xl font-bold text-yellow-700">{{ localPlanners.filter(p => p.status === 'done').length }}/{{ localPlanners.length }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 px-4 py-3">
                                <h2 class="text-sm font-semibold text-gray-900">Employee Contributions</h2>
                            </div>
                            <div v-if="reportData.employeeSummary?.length" class="divide-y divide-gray-100">
                                <div v-for="emp in reportData.employeeSummary" :key="emp.user_id">
                                    <div class="flex items-center justify-between px-4 py-3.5 cursor-pointer hover:bg-gray-50 transition-colors" @click="toggleEmployee(emp.user_id)">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white" :class="avatarColor(emp.user_id)">{{ (emp.user_name || '?').charAt(0).toUpperCase() }}</div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">{{ emp.user_name }}</p>
                                                <p class="text-xs text-gray-400 capitalize">{{ emp.user_role }} &middot; {{ emp.log_count }} entries</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div class="flex items-center gap-1">
                                                <span v-if="emp.done_count > 0" class="rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700">{{ emp.done_count }}</span>
                                                <span v-if="emp.in_progress_count > 0" class="rounded-full bg-blue-100 px-1.5 py-0.5 text-[10px] font-medium text-blue-700">{{ emp.in_progress_count }}</span>
                                                <span v-if="emp.blocked_count > 0" class="rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-700">{{ emp.blocked_count }}</span>
                                            </div>
                                            <p class="text-base font-bold text-[#4e1a77]">{{ hoursDisplay(emp.total_hours) }}</p>
                                            <svg class="h-4 w-4 text-gray-400 transition-transform" :class="{ 'rotate-180': expandedEmployee === emp.user_id }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                        </div>
                                    </div>
                                    <div v-if="expandedEmployee === emp.user_id" class="bg-gray-50/50 px-4 pb-3">
                                        <table class="min-w-full">
                                            <thead>
                                                <tr class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                                                    <th class="py-2 pr-3 text-left">Date</th>
                                                    <th class="py-2 pr-3 text-left">Time</th>
                                                    <th class="py-2 pr-3 text-left">Hours</th>
                                                    <th class="py-2 pr-3 text-left">Status</th>
                                                    <th class="py-2 text-left">Note</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                <tr v-for="log in getEmployeeLogs(emp.user_id)" :key="log.id" class="text-sm">
                                                    <td class="py-2 pr-3 text-gray-600 whitespace-nowrap">{{ formatDate(log.log_date) }}</td>
                                                    <td class="py-2 pr-3 text-gray-500 whitespace-nowrap text-xs">{{ formatTime(log.start_time) }} - {{ formatTime(log.end_time) }}</td>
                                                    <td class="py-2 pr-3 font-medium text-gray-900 whitespace-nowrap">{{ hoursDisplay(log.hours_spent) }}</td>
                                                    <td class="py-2 pr-3"><span :class="statusColors[log.status] || 'bg-gray-100 text-gray-600'" class="rounded-full px-2 py-0.5 text-[10px] font-medium capitalize">{{ log.status?.replace('_', ' ') }}</span></td>
                                                    <td class="py-2 text-gray-600">
                                                        <p v-if="log.note" class="text-xs">{{ log.note }}</p>
                                                        <p v-if="log.blocker" class="text-xs text-red-600 mt-0.5">Blocker: {{ log.blocker }}</p>
                                                        <span v-if="!log.note && !log.blocker" class="text-xs text-gray-300">-</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="px-4 py-8 text-center text-sm text-gray-400">No work logs recorded yet</p>
                        </div>
                    </template>
                </div>

                <!-- ═══ WORKERS TAB ═══ -->
                <div v-if="activeTab === 'workers'" class="space-y-3">
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-900">Assigned Team</h2>
                            <span class="rounded-full bg-[#4e1a77]/10 px-2 py-0.5 text-xs font-medium text-[#4e1a77]">{{ assignedEmployees.length }}</span>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <div v-for="emp in assignedEmployees" :key="emp.user_id" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white" :class="avatarColor(emp.user_id)">{{ (emp.user_name || '?').charAt(0).toUpperCase() }}</div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ emp.user_name }}</p>
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize" :class="roleColors[emp.role] || roleColors.contributor">{{ emp.role?.replace('_', ' ') }}</span>
                                    </div>
                                </div>
                                <button v-if="isManagerOrAnalyst && emp.role !== 'owner'" @click="startTransfer(emp)" class="rounded-lg border border-orange-200 px-2.5 py-1 text-xs font-medium text-orange-600 hover:bg-orange-50 transition-colors">Transfer</button>
                            </div>
                            <p v-if="!assignedEmployees.length" class="px-4 py-8 text-center text-sm text-gray-400">No employees assigned</p>
                        </div>
                    </div>

                    <div v-if="isManagerOrAnalyst" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Add Contributors</h3>

                        <!-- Selected tags -->
                        <div v-if="workerForm.user_ids.length" class="flex flex-wrap gap-1.5 mb-2">
                            <span
                                v-for="id in workerForm.user_ids" :key="id"
                                class="inline-flex items-center gap-1 rounded-full bg-[#f5f0ff] border border-[#ddd0f7] px-2.5 py-1 text-xs font-medium text-[#4e1a77]"
                            >
                                {{ selectedMemberName(id) }}
                                <button @click="toggleWorkerSelection(id)" class="text-[#4e1a77]/60 hover:text-[#4e1a77]">&times;</button>
                            </span>
                        </div>

                        <!-- Dropdown trigger -->
                        <div class="relative">
                            <button
                                @click="showWorkerDropdown = !showWorkerDropdown"
                                class="w-full flex items-center justify-between rounded-lg border border-gray-300 px-3 py-2 text-sm text-left hover:border-[#4e1a77] focus:outline-none focus:border-[#4e1a77]"
                                :class="showWorkerDropdown ? 'border-[#4e1a77] ring-1 ring-[#4e1a77]' : ''"
                            >
                                <span :class="workerForm.user_ids.length ? 'text-gray-700' : 'text-gray-400'">
                                    {{ workerForm.user_ids.length ? `${workerForm.user_ids.length} member(s) selected` : 'Select team members...' }}
                                </span>
                                <svg class="h-4 w-4 text-gray-400 transition-transform" :class="{ 'rotate-180': showWorkerDropdown }" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </button>

                            <!-- Dropdown list -->
                            <div v-if="showWorkerDropdown" class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg max-h-52 overflow-y-auto">
                                <p v-if="!availableMembers.length" class="px-3 py-3 text-xs text-gray-400 text-center">All members already added</p>
                                <label
                                    v-for="m in availableMembers" :key="m.id"
                                    class="flex items-center gap-2.5 px-3 py-2 hover:bg-[#f5f0ff] cursor-pointer transition-colors"
                                >
                                    <input
                                        type="checkbox"
                                        :value="m.id"
                                        :checked="workerForm.user_ids.includes(m.id)"
                                        @change="toggleWorkerSelection(m.id)"
                                        class="rounded border-gray-300 text-[#4e1a77] focus:ring-[#4e1a77]"
                                    />
                                    <span class="text-sm text-gray-700">{{ m.name }}</span>
                                    <span class="ml-auto text-[10px] capitalize text-gray-400">{{ m.role?.replace('_', ' ') }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Add button -->
                        <div class="flex justify-end mt-3">
                            <button
                                @click="addContributor"
                                :disabled="!workerForm.user_ids.length || addingContributors"
                                class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560] disabled:opacity-50"
                            >
                                {{ addingContributors ? 'Adding...' : `Add ${workerForm.user_ids.length > 1 ? workerForm.user_ids.length + ' Members' : 'Member'}` }}
                            </button>
                        </div>
                    </div>

                    <div v-if="transfers?.length" class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-4 py-3"><h3 class="text-sm font-semibold text-gray-900">Transfer History</h3></div>
                        <div class="divide-y divide-gray-100">
                            <div v-for="t in transfers" :key="t.id" class="px-4 py-3">
                                <p class="text-sm text-gray-700"><strong>{{ t.from_name }}</strong> &rarr; <strong>{{ t.to_name }}</strong></p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ t.notes }} &middot; {{ timeAgo(t.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ ATTACHMENTS TAB ═══ -->
                <div v-if="activeTab === 'attachments'" class="space-y-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <label class="flex items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 px-4 py-5 cursor-pointer hover:border-[#4e1a77] hover:bg-[#f5f0ff]/30 transition-colors">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                            <span class="text-sm text-gray-500">{{ uploadingFile ? 'Uploading...' : 'Click to upload (max 20MB)' }}</span>
                            <input ref="fileInput" type="file" class="hidden" @change="uploadAttachment" :disabled="uploadingFile" />
                        </label>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                        <div v-for="a in localAttachments" :key="a.id" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="flex h-9 w-9 items-center justify-center rounded-lg shrink-0" :class="fileIconColors[fileIcon(a.mime_type)]">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <a :href="`/storage/${a.stored_path}`" target="_blank" class="text-sm font-medium text-gray-900 hover:text-[#4e1a77] truncate block">{{ a.original_name }}</a>
                                    <p class="text-xs text-gray-400">{{ formatFileSize(a.size) }}<span v-if="a.uploader_name"> &middot; {{ a.uploader_name }}</span><span v-if="a.created_at"> &middot; {{ formatDate(a.created_at) }}</span></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 ml-3">
                                <a :href="`/storage/${a.stored_path}`" target="_blank" class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">Download</a>
                                <button v-if="isManagerOrAnalyst" @click="deleteAttachment(a.id)" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                            </div>
                        </div>
                        <p v-if="!localAttachments?.length" class="px-4 py-8 text-center text-sm text-gray-400">No files attached yet</p>
                    </div>
                </div>

                <!-- ═══ TICKETS TAB ═══ -->
                <div v-if="activeTab === 'tickets'" class="space-y-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex gap-2">
                            <input v-model="ticketForm.ticket_id" placeholder="Ticket ID (e.g. JIRA-123)" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" @keydown.enter="addTicket" />
                            <select v-model="ticketForm.source_type" class="rounded-lg border border-gray-300 px-2 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                                <option value="external">External</option>
                                <option value="internal">Internal</option>
                            </select>
                            <button @click="addTicket" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560]">Link</button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                        <div v-for="t in localTickets" :key="t.id" class="flex items-center justify-between px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ t.ticket_id }}</p>
                                <span class="text-xs capitalize rounded-full px-2 py-0.5" :class="t.source_type === 'internal' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600'">{{ t.source_type }}</span>
                            </div>
                            <button @click="removeTicket(t.id)" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                        </div>
                        <p v-if="!localTickets?.length" class="px-4 py-8 text-center text-sm text-gray-400">No tickets linked</p>
                    </div>
                </div>

                <!-- ═══ BLOCKERS TAB ═══ -->
                <div v-if="activeTab === 'blockers'" class="space-y-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex gap-2">
                            <textarea v-model="blockerForm.description" rows="2" placeholder="Describe the blocker..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm resize-none focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                            <button @click="addBlocker" class="self-end rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Report</button>
                        </div>
                    </div>

                    <div v-if="localBlockers.filter(x => x.status === 'active').length" class="rounded-xl border border-red-200 bg-white shadow-sm">
                        <div class="border-b border-red-100 px-4 py-2.5 bg-red-50/50 rounded-t-xl">
                            <h3 class="text-sm font-semibold text-red-700">Active Blockers</h3>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <div v-for="b in localBlockers.filter(x => x.status === 'active')" :key="b.id" class="flex items-center justify-between px-4 py-3">
                                <div>
                                    <p class="text-sm text-gray-900">{{ b.description }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ b.creator_name }} &middot; {{ timeAgo(b.created_at) }}</p>
                                </div>
                                <button @click="resolveBlocker(b.id)" class="rounded-lg border border-green-300 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-50">Resolve</button>
                            </div>
                        </div>
                    </div>

                    <div v-if="localBlockers.filter(x => x.status === 'resolved').length" class="rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-4 py-2.5"><h3 class="text-sm font-semibold text-gray-900">Resolved</h3></div>
                        <div class="divide-y divide-gray-100">
                            <div v-for="b in localBlockers.filter(x => x.status === 'resolved')" :key="b.id" class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-green-500">&#10003;</span>
                                    <p class="text-sm text-gray-500 line-through">{{ b.description }}</p>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5 ml-5">Resolved by {{ b.resolver_name }}</p>
                            </div>
                        </div>
                    </div>

                    <p v-if="!localBlockers.length" class="text-center text-sm text-gray-400 py-6">No blockers reported</p>
                </div>

                <!-- ═══ RELEASE NOTES TAB ═══ -->
                <div v-if="activeTab === 'release-notes'" class="space-y-3">
                    <!-- Create Button -->
                    <div v-if="canCreateNote && !showCreateNote" class="flex justify-end">
                        <button @click="showCreateNote = true" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560] flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            New Release Note
                        </button>
                    </div>

                    <!-- Create Form -->
                    <div v-if="showCreateNote" class="rounded-xl border border-[#ddd0f7] bg-[#faf8ff] p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-[#4e1a77]">New Release Note</h3>
                            <button @click="showCreateNote = false" class="text-gray-400 hover:text-gray-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 mb-1 block">Title *</label>
                            <input v-model="newNote.title" placeholder="e.g. v2.1.0 Release" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 mb-1 block">Description</label>
                            <textarea v-model="newNote.description" rows="3" placeholder="Release description, changelog, notes..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm resize-none focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 mb-1 block">Attach Files</label>
                            <input ref="newNoteFiles" type="file" multiple class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#f5f0ff] file:text-[#4e1a77] hover:file:bg-[#e8ddf0]" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 mb-1 block">Links</label>
                            <div class="flex gap-2 mb-2">
                                <input v-model="newLinkForm.label" placeholder="Label (optional)" class="flex-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                                <input v-model="newLinkForm.url" placeholder="URL *" class="flex-[2] rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" @keydown.enter="addNoteLink" />
                                <button @click="addNoteLink" class="rounded-lg border border-[#4e1a77] px-3 py-1.5 text-xs font-medium text-[#4e1a77] hover:bg-[#f5f0ff]">Add</button>
                            </div>
                            <div v-if="newNote.links.length" class="flex flex-wrap gap-2">
                                <span v-for="(link, idx) in newNote.links" :key="idx" class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs text-blue-700">
                                    {{ link.label || link.url }}
                                    <button @click="removeNoteLink(idx)" class="text-blue-400 hover:text-blue-700">&times;</button>
                                </span>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button @click="showCreateNote = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                            <button @click="createReleaseNote" :disabled="!newNote.title || creatingNote" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560] disabled:opacity-50">
                                {{ creatingNote ? 'Creating...' : 'Create' }}
                            </button>
                        </div>
                    </div>

                    <!-- Loading -->
                    <div v-if="releaseNotesLoading" class="flex items-center justify-center py-12">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#4e1a77]"></div>
                    </div>

                    <!-- Error -->
                    <div v-if="releaseNotesError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-700">
                        Failed to load release notes. Please refresh the page or run <code class="bg-red-100 px-1 rounded">php artisan migrate</code> if the feature has not been set up yet.
                    </div>

                    <!-- Notes List -->
                    <div v-for="note in releaseNotes" :key="note.id" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                        <div class="px-5 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="text-base font-semibold text-gray-900">{{ note.title }}</h3>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        By {{ note.author_name }} <span class="capitalize">({{ note.author_role }})</span>
                                        &middot; {{ formatDate(note.created_at) }}
                                    </p>
                                </div>
                                <button v-if="canDeleteNote" @click="deleteReleaseNote(note.id)" class="shrink-0 rounded-lg border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50 transition-colors">Delete</button>
                            </div>
                            <p v-if="note.description" class="text-sm text-gray-700 mt-3 whitespace-pre-wrap">{{ note.description }}</p>
                        </div>

                        <!-- Files -->
                        <div v-if="note.files?.length" class="border-t border-gray-100 px-5 py-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-2">Files</p>
                            <div class="space-y-1.5">
                                <div v-for="file in note.files" :key="file.id" class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="flex h-7 w-7 items-center justify-center rounded shrink-0" :class="fileIconColors[fileIcon(file.mime_type)]">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                                        </div>
                                        <a :href="`/storage/${file.stored_path}`" target="_blank" class="text-sm text-gray-700 hover:text-[#4e1a77] truncate">{{ file.original_name }}</a>
                                        <span class="text-xs text-gray-400 shrink-0">{{ formatFileSize(file.size) }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <a :href="`/storage/${file.stored_path}`" target="_blank" class="text-xs text-[#4e1a77] hover:underline">Download</a>
                                        <button v-if="canDeleteNote" @click="deleteNoteFile(note.id, file.id)" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Links -->
                        <div v-if="note.links?.length" class="border-t border-gray-100 px-5 py-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-2">Links</p>
                            <div class="flex flex-wrap gap-2">
                                <div v-for="link in note.links" :key="link.id" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs group hover:border-[#4e1a77] transition-colors">
                                    <svg class="h-3.5 w-3.5 text-gray-400 group-hover:text-[#4e1a77]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.556a4.5 4.5 0 00-1.242-7.244l-4.5-4.5a4.5 4.5 0 00-6.364 6.364L4.343 8.06" /></svg>
                                    <a :href="link.url" target="_blank" class="text-gray-700 hover:text-[#4e1a77]">{{ link.label || link.url }}</a>
                                    <button v-if="canDeleteNote" @click="deleteNoteLink(note.id, link.id)" class="text-gray-300 hover:text-red-500 ml-1">&times;</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-if="releaseNotesLoaded && !releaseNotes.length && !releaseNotesLoading" class="text-center text-sm text-gray-400 py-8">No release notes yet</p>
                </div>
            </div>

            <!-- ════ RIGHT PANEL: Activity Sidebar ════ -->
            <div class="w-80 xl:w-96 shrink-0 hidden lg:block sticky top-4">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm flex flex-col" style="max-height: calc(100vh - 6rem);">
                    <!-- Activity Header -->
                    <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between shrink-0">
                        <h2 class="text-sm font-semibold text-gray-900">Activity</h2>
                        <span class="text-xs text-gray-400">{{ localUpdates.length }} updates</span>
                    </div>

                    <!-- Post Update Form -->
                    <div class="border-b border-gray-100 px-4 py-3 shrink-0">
                        <div class="relative">
                            <textarea
                                ref="updateTextarea"
                                v-model="updateForm.content"
                                @input="onUpdateInput"
                                @keydown.escape="showMentionDropdown = false"
                                @keydown.ctrl.enter="addUpdate"
                                @keydown.meta.enter="addUpdate"
                                rows="2"
                                placeholder="Write an update... @mention someone"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm resize-none focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] bg-gray-50"
                            />
                            <!-- @mention dropdown -->
                            <div v-if="showMentionDropdown && mentionSuggestions.length" class="absolute left-0 bottom-full mb-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg z-20 max-h-40 overflow-y-auto">
                                <button
                                    v-for="m in mentionSuggestions"
                                    :key="m.id"
                                    @mousedown.prevent="insertMention(m)"
                                    class="flex items-center gap-2 w-full px-3 py-2 text-sm text-left hover:bg-[#f5f0ff] transition-colors"
                                >
                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[9px] font-bold text-white" :class="avatarColor(m.id)">{{ (m.name || '?').charAt(0).toUpperCase() }}</span>
                                    <span class="text-gray-900">{{ m.name }}</span>
                                    <span class="text-xs text-gray-400 capitalize ml-auto">{{ m.role }}</span>
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center justify-end mt-2">
                            <button
                                @click="addUpdate"
                                :disabled="postingUpdate || !updateForm.content.trim()"
                                class="rounded-lg bg-[#4e1a77] px-3 py-1 text-xs font-medium text-white hover:bg-[#3d1560] disabled:opacity-50 transition-colors"
                            >
                                {{ postingUpdate ? 'Posting...' : 'Post' }}
                            </button>
                        </div>
                        <p v-if="updateError" class="mt-1 text-xs text-red-600">{{ updateError }}</p>
                    </div>

                    <!-- Activity Feed -->
                    <div class="flex-1 overflow-y-auto px-4 py-3 space-y-3">
                        <div
                            v-for="u in localUpdates"
                            :key="u.id"
                            class="relative pl-8"
                        >
                            <!-- Timeline dot -->
                            <div class="absolute left-0 top-1 flex h-6 w-6 items-center justify-center rounded-full text-[9px] font-bold"
                                :class="u.source === 'system' ? 'bg-gray-200 text-gray-500' : 'text-white ' + avatarColor(u.created_by)"
                            >
                                {{ u.source === 'system' ? 'S' : (u.author_name || '?').charAt(0).toUpperCase() }}
                            </div>

                            <div>
                                <div class="flex items-center gap-1.5 mb-0.5">
                                    <span class="text-xs font-medium" :class="u.source === 'system' ? 'text-gray-500' : 'text-gray-900'">
                                        {{ u.source === 'system' ? 'System' : u.author_name }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 ml-auto">{{ timeAgo(u.created_at) }}</span>
                                </div>
                                <p
                                    class="text-xs leading-relaxed whitespace-pre-wrap"
                                    :class="u.source === 'system' ? 'text-gray-500 italic' : 'text-gray-700'"
                                    v-html="highlightMentions(u.content)"
                                ></p>
                            </div>
                        </div>
                        <p v-if="!localUpdates?.length" class="text-center text-xs text-gray-400 py-6">No activity yet</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Mobile Activity (shown below on small screens) ── -->
        <div class="lg:hidden mt-5">
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Activity</h2>
                    <span class="text-xs text-gray-400">{{ localUpdates.length }} updates</span>
                </div>
                <div class="p-4">
                    <div class="relative">
                        <textarea
                            v-model="updateForm.content"
                            @input="onUpdateInput"
                            @keydown.ctrl.enter="addUpdate"
                            rows="2"
                            placeholder="Write an update..."
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm resize-none focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        />
                    </div>
                    <div class="flex justify-end mt-2">
                        <button @click="addUpdate" :disabled="postingUpdate || !updateForm.content.trim()" class="rounded-lg bg-[#4e1a77] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#3d1560] disabled:opacity-50">{{ postingUpdate ? 'Posting...' : 'Post' }}</button>
                    </div>
                </div>
                <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                    <div v-for="u in localUpdates" :key="u.id" class="px-4 py-3">
                        <div class="flex items-center gap-2 mb-1">
                            <div class="flex h-6 w-6 items-center justify-center rounded-full text-[9px] font-bold" :class="u.source === 'system' ? 'bg-gray-200 text-gray-500' : 'text-white ' + avatarColor(u.created_by)">
                                {{ u.source === 'system' ? 'S' : (u.author_name || '?').charAt(0).toUpperCase() }}
                            </div>
                            <span class="text-xs font-medium text-gray-900">{{ u.source === 'system' ? 'System' : u.author_name }}</span>
                            <span class="text-[10px] text-gray-400 ml-auto">{{ timeAgo(u.created_at) }}</span>
                        </div>
                        <p class="text-xs text-gray-700 whitespace-pre-wrap" :class="{ 'italic text-gray-500': u.source === 'system' }" v-html="highlightMentions(u.content)"></p>
                    </div>
                    <p v-if="!localUpdates?.length" class="px-4 py-6 text-center text-xs text-gray-400">No activity yet</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Transfer Modal (Teleport) ── -->
    <Teleport to="body">
        <div v-if="showTransferModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Transfer Role</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Transfer <strong>{{ transferForm.from_name }}</strong>'s
                    <span class="capitalize font-medium">{{ transferForm.from_role?.replace('_', ' ') }}</span> role.
                </p>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-medium text-gray-500 mb-1 block">New Employee</label>
                        <select v-model="transferForm.to_user" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                            <option value="">Select employee...</option>
                            <option v-for="m in teamMembers" :key="m.id" :value="m.id" :disabled="m.id === transferForm.from_user">{{ m.name }} ({{ m.role }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 mb-1 block">Transfer Reason</label>
                        <textarea v-model="transferForm.notes" rows="2" placeholder="e.g. Workload rebalancing..." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm resize-none focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-5">
                    <button @click="showTransferModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button @click="executeTransfer" :disabled="!transferForm.to_user || !transferForm.notes" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560] disabled:opacity-50">Transfer</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

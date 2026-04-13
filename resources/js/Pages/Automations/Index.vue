<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

const { error: toastError, success: toastSuccess } = useToast();

defineOptions({ layout: AppLayout });

const props = defineProps({
    automations: { type: Array, default: () => [] },
    teamMembers: { type: Array, default: () => [] },
});

// ── Create/Edit Modal ──────────────────────────
const showModal = ref(false);
const editingId = ref(null);
const saving = ref(false);

const form = ref(defaultForm());

function defaultForm() {
    return {
        name: '',
        description: '',
        trigger_type: 'schedule',
        trigger_config: {
            frequency: 'weekly',
            day_of_week: 1,
            time: '09:00',
            condition: 'stage_is_live',
            lookback_days: 7,
            from_stage: '*',
            to_stage: 'live',
            from_status: '*',
            to_status: 'completed',
        },
        action_type: 'send_email',
        action_config: {
            recipients: 'all_assignees',
            roles: [],
            user_ids: [],
            subject: '',
            email_body: '',
            title: '',
            message: '',
        },
    };
}

function openCreate() {
    editingId.value = null;
    form.value = defaultForm();
    showModal.value = true;
}

function openEdit(a) {
    editingId.value = a.id;
    form.value = {
        name: a.name,
        description: a.description || '',
        trigger_type: a.trigger_type,
        trigger_config: { ...defaultForm().trigger_config, ...a.trigger_config },
        action_type: a.action_type,
        action_config: { ...defaultForm().action_config, ...a.action_config },
    };
    showModal.value = true;
}

async function save() {
    saving.value = true;
    const payload = {
        name: form.value.name,
        description: form.value.description,
        trigger_type: form.value.trigger_type,
        trigger_config: buildTriggerConfig(),
        action_type: form.value.action_type,
        action_config: buildActionConfig(),
    };

    try {
        if (editingId.value) {
            await axios.put(`/api/v1/automations/${editingId.value}`, payload);
        } else {
            await axios.post('/api/v1/automations', payload);
        }
        showModal.value = false;
        router.reload();
    } catch (e) {
        console.error('Save failed', e);
        toastError(e.response?.data?.message || 'Save failed');
    }
    saving.value = false;
}

function buildTriggerConfig() {
    const tc = form.value.trigger_config;
    if (form.value.trigger_type === 'schedule') {
        return {
            frequency: tc.frequency,
            day_of_week: tc.day_of_week,
            day_of_month: tc.day_of_month || 1,
            time: tc.time,
            condition: tc.condition,
            lookback_days: tc.lookback_days,
        };
    }
    if (form.value.trigger_type === 'stage_change') {
        return { from_stage: tc.from_stage, to_stage: tc.to_stage };
    }
    if (form.value.trigger_type === 'status_change') {
        return { from_status: tc.from_status, to_status: tc.to_status };
    }
    return {};
}

function buildActionConfig() {
    const ac = form.value.action_config;
    const base = { recipients: ac.recipients };
    if (ac.recipients === 'specific_roles') base.roles = ac.roles;
    if (ac.recipients === 'specific_users') base.user_ids = ac.user_ids;

    if (form.value.action_type === 'send_email') {
        base.subject = ac.subject || `Automation: ${form.value.name}`;
        base.email_body = ac.email_body || '';
    }
    if (form.value.action_type === 'send_notification') {
        base.title = ac.title || form.value.name;
        base.message = ac.message || 'Automation triggered for {{project_name}}';
    }
    return base;
}

async function toggleActive(a) {
    await axios.post(`/api/v1/automations/${a.id}/toggle`);
    router.reload();
}

async function deleteAutomation(a) {
    if (!confirm(`Delete "${a.name}"?`)) return;
    await axios.delete(`/api/v1/automations/${a.id}`);
    router.reload();
}

async function runNow(a) {
    try {
        const { data } = await axios.post(`/api/v1/automations/${a.id}/run`);
        toastSuccess(data.message || 'Automation triggered.');
        router.reload();
    } catch (e) {
        toastError('Run failed: ' + (e.response?.data?.message || e.message));
    }
}

// ── Logs Panel ──────────────────────────────
const logsAutomation = ref(null);
const logs = ref([]);
const logsLoading = ref(false);

async function openLogs(a) {
    logsAutomation.value = a;
    logsLoading.value = true;
    try {
        const { data } = await axios.get(`/api/v1/automations/${a.id}/logs`);
        logs.value = data;
    } catch { logs.value = []; }
    logsLoading.value = false;
}

function closeLogs() {
    logsAutomation.value = null;
}

// ── Helpers ──────────────────────────────
const triggerLabels = {
    schedule: 'Scheduled',
    stage_change: 'Stage Change',
    status_change: 'Status Change',
    blocker_created: 'Blocker Created',
};

const triggerColors = {
    schedule: 'bg-blue-100 text-blue-700',
    stage_change: 'bg-green-100 text-green-700',
    status_change: 'bg-orange-100 text-orange-700',
    blocker_created: 'bg-red-100 text-red-700',
};

const actionLabels = { send_email: 'Send Email', send_notification: 'In-App Notification' };

const conditionLabels = {
    stage_is_live: 'Projects that went Live',
    stage_changed: 'Projects with stage changes',
    overdue: 'Overdue projects',
    all_active: 'All active projects',
    blockers_open: 'Projects with open blockers',
};

const dayLabels = { 1: 'Monday', 2: 'Tuesday', 3: 'Wednesday', 4: 'Thursday', 5: 'Friday', 6: 'Saturday', 7: 'Sunday' };

function describeTrigger(a) {
    const tc = a.trigger_config || {};
    if (a.trigger_type === 'schedule') {
        const freq = tc.frequency === 'weekly' ? `Every ${dayLabels[tc.day_of_week] || 'Monday'}` : tc.frequency === 'daily' ? 'Every day' : `Monthly on day ${tc.day_of_month || 1}`;
        return `${freq} at ${tc.time || '09:00'} — ${conditionLabels[tc.condition] || tc.condition}`;
    }
    if (a.trigger_type === 'stage_change') {
        const from = tc.from_stage === '*' ? 'any stage' : stageLabelFor(tc.from_stage);
        const to = tc.to_stage === '*' ? 'any stage' : stageLabelFor(tc.to_stage);
        return `When stage changes from ${from} to ${to}`;
    }
    if (a.trigger_type === 'status_change') {
        const from = tc.from_status === '*' ? 'any' : tc.from_status;
        const to = tc.to_status === '*' ? 'any' : tc.to_status;
        return `When status changes from ${from} to ${to}`;
    }
    return 'When a new blocker is created';
}

function stageLabelFor(val) {
    return val?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || val;
}

function timeAgo(dateStr) {
    if (!dateStr) return 'Never';
    const d = new Date(dateStr);
    const diff = Date.now() - d.getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return new Date(dateStr).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

const stageOptions = [
    '*','doc_yet_to_start','gpt_chat_wip','doc_wip','figma_yet_to_start','figma_design_wip',
    'doc_under_review','doc_changes_required','dev_yet_to_start','development_wip',
    'testing_yet_to_start','testing_wip','dev_testing_wip','ready_for_internal',
    'testing_wip_internal','bugs_reported_testing','live_testing_yet_to_start',
    'ready_to_go_for_live','live_testing_wip','bugs_reported_live','bug_fixing_wip',
    'bugs_fixed','dev_changes_required','live','on_hold','scrapped','rnd',
];

function toggleRole(role) {
    const idx = form.value.action_config.roles.indexOf(role);
    if (idx >= 0) form.value.action_config.roles.splice(idx, 1);
    else form.value.action_config.roles.push(role);
}

function toggleUser(id) {
    const idx = form.value.action_config.user_ids.indexOf(id);
    if (idx >= 0) form.value.action_config.user_ids.splice(idx, 1);
    else form.value.action_config.user_ids.push(id);
}
</script>

<template>
    <Head title="Automations" />

    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Automations</h1>
                <p class="text-sm text-gray-500 mt-0.5">Create rules to automate emails and notifications</p>
            </div>
            <button @click="openCreate" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#3d1560] transition-colors">
                + New Automation
            </button>
        </div>

        <!-- Automations List -->
        <div v-if="automations.length" class="space-y-3">
            <div
                v-for="a in automations"
                :key="a.id"
                class="rounded-xl border border-gray-200 bg-white shadow-sm hover:shadow-md transition-shadow"
            >
                <div class="px-5 py-4">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2.5">
                                <h3 class="text-sm font-bold text-gray-900">{{ a.name }}</h3>
                                <span :class="triggerColors[a.trigger_type]" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase">{{ triggerLabels[a.trigger_type] }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="a.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                                    {{ a.is_active ? 'Active' : 'Paused' }}
                                </span>
                            </div>
                            <p v-if="a.description" class="text-xs text-gray-500 mt-1">{{ a.description }}</p>
                            <div class="flex items-center gap-4 mt-2">
                                <div class="flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                                    <span class="text-xs text-gray-600">{{ describeTrigger(a) }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
                                    <span class="text-xs text-gray-600">{{ actionLabels[a.action_type] }} to {{ a.action_config?.recipients?.replace(/_/g, ' ') || 'all assignees' }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span class="text-xs text-gray-500">Last run: {{ timeAgo(a.last_run_at) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0 ml-4">
                            <button @click="toggleActive(a)" :title="a.is_active ? 'Pause' : 'Activate'" class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors" :class="a.is_active ? 'text-green-600' : 'text-gray-400'">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" /></svg>
                            </button>
                            <button v-if="a.trigger_type === 'schedule'" @click="runNow(a)" title="Run now" class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" /></svg>
                            </button>
                            <button @click="openLogs(a)" title="View logs" class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.888L8.25 21v-3.375h-.375a3 3 0 01-3-3V8.25m8.625 9.75H18a2.25 2.25 0 002.25-2.25V5.25A2.25 2.25 0 0018 3h-7.5A2.25 2.25 0 008.25 5.25v1.5" /></svg>
                            </button>
                            <button @click="openEdit(a)" title="Edit" class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                            </button>
                            <button @click="deleteAutomation(a)" title="Delete" class="p-1.5 rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600 transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Recent logs preview -->
                    <div v-if="a.recent_logs?.length" class="mt-3 pt-3 border-t border-gray-100">
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] font-semibold text-gray-400 uppercase">Recent runs:</span>
                            <div class="flex gap-1.5">
                                <span
                                    v-for="log in a.recent_logs.slice(0, 5)"
                                    :key="log.id"
                                    class="h-2 w-2 rounded-full"
                                    :class="log.status === 'success' ? 'bg-green-400' : 'bg-red-400'"
                                    :title="log.message + ' — ' + timeAgo(log.created_at)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="rounded-xl border border-gray-200 bg-white shadow-sm px-5 py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            <h3 class="mt-3 text-sm font-semibold text-gray-900">No automations yet</h3>
            <p class="mt-1 text-xs text-gray-500">Create your first automation to send weekly reports or trigger emails on project events.</p>
            <button @click="openCreate" class="mt-4 rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-semibold text-white hover:bg-[#3d1560]">+ Create Automation</button>
        </div>
    </div>

    <!-- ═══════════ Create/Edit Modal ═══════════ -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/30" @click="showModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-xl mx-4 max-h-[85vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 rounded-t-xl z-10">
                    <h2 class="text-lg font-bold text-gray-900">{{ editingId ? 'Edit Automation' : 'New Automation' }}</h2>
                </div>

                <div class="px-6 py-5 space-y-5">
                    <!-- Name & Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name *</label>
                        <input v-model="form.name" type="text" placeholder="e.g. Weekly Live Projects Report" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <input v-model="form.description" type="text" placeholder="Optional description" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]" />
                    </div>

                    <!-- TRIGGER SECTION -->
                    <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4">
                        <h3 class="text-sm font-bold text-blue-800 mb-3">When (Trigger)</h3>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Trigger Type</label>
                            <select v-model="form.trigger_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                                <option value="schedule">Scheduled (cron)</option>
                                <option value="stage_change">Stage Change (event)</option>
                                <option value="status_change">Status Change (event)</option>
                                <option value="blocker_created">Blocker Created (event)</option>
                            </select>
                        </div>

                        <!-- Schedule options -->
                        <template v-if="form.trigger_type === 'schedule'">
                            <div class="grid grid-cols-3 gap-3 mt-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Frequency</label>
                                    <select v-model="form.trigger_config.frequency" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                                <div v-if="form.trigger_config.frequency === 'weekly'">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Day</label>
                                    <select v-model="form.trigger_config.day_of_week" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        <option v-for="(label, val) in dayLabels" :key="val" :value="Number(val)">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Time</label>
                                    <input v-model="form.trigger_config.time" type="time" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 mt-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Condition</label>
                                    <select v-model="form.trigger_config.condition" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        <option value="stage_is_live">Projects that went Live</option>
                                        <option value="stage_changed">Projects with stage changes</option>
                                        <option value="overdue">Overdue projects</option>
                                        <option value="all_active">All active projects</option>
                                        <option value="blockers_open">Projects with open blockers</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Lookback (days)</label>
                                    <input v-model.number="form.trigger_config.lookback_days" type="number" min="1" max="90" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                            </div>
                        </template>

                        <!-- Stage change options -->
                        <template v-if="form.trigger_type === 'stage_change'">
                            <div class="grid grid-cols-2 gap-3 mt-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">From Stage</label>
                                    <select v-model="form.trigger_config.from_stage" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        <option value="*">Any stage</option>
                                        <option v-for="s in stageOptions.filter(x=>x!=='*')" :key="s" :value="s">{{ stageLabelFor(s) }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">To Stage</label>
                                    <select v-model="form.trigger_config.to_stage" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        <option value="*">Any stage</option>
                                        <option v-for="s in stageOptions.filter(x=>x!=='*')" :key="s" :value="s">{{ stageLabelFor(s) }}</option>
                                    </select>
                                </div>
                            </div>
                        </template>

                        <!-- Status change options -->
                        <template v-if="form.trigger_type === 'status_change'">
                            <div class="grid grid-cols-2 gap-3 mt-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">From Status</label>
                                    <select v-model="form.trigger_config.from_status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        <option value="*">Any</option>
                                        <option value="active">Active</option>
                                        <option value="completed">Completed</option>
                                        <option value="on_hold">On Hold</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">To Status</label>
                                    <select v-model="form.trigger_config.to_status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        <option value="*">Any</option>
                                        <option value="active">Active</option>
                                        <option value="completed">Completed</option>
                                        <option value="on_hold">On Hold</option>
                                    </select>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- ACTION SECTION -->
                    <div class="rounded-lg border border-green-200 bg-green-50/50 p-4">
                        <h3 class="text-sm font-bold text-green-800 mb-3">Then (Action)</h3>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Action Type</label>
                            <select v-model="form.action_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                                <option value="send_email">Send Email</option>
                                <option value="send_notification">In-App Notification</option>
                            </select>
                        </div>

                        <div class="mt-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Recipients</label>
                            <select v-model="form.action_config.recipients" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                <option value="all_assignees">All project assignees</option>
                                <option value="specific_roles">Specific roles</option>
                                <option value="specific_users">Specific users</option>
                            </select>
                        </div>

                        <!-- Role checkboxes -->
                        <div v-if="form.action_config.recipients === 'specific_roles'" class="mt-2 flex flex-wrap gap-2">
                            <label v-for="r in ['manager','analyst_head','analyst','senior_developer','developer','employee']" :key="r" class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs cursor-pointer transition-colors" :class="form.action_config.roles.includes(r) ? 'border-purple-400 bg-purple-50 text-purple-700' : 'border-gray-200 text-gray-600'">
                                <input type="checkbox" :checked="form.action_config.roles.includes(r)" @change="toggleRole(r)" class="hidden" />
                                {{ r.replace(/_/g, ' ') }}
                            </label>
                        </div>

                        <!-- User checkboxes -->
                        <div v-if="form.action_config.recipients === 'specific_users'" class="mt-2 max-h-32 overflow-y-auto space-y-1">
                            <label v-for="m in teamMembers" :key="m.id" class="flex items-center gap-2 rounded px-2 py-1 text-sm cursor-pointer hover:bg-gray-50" :class="form.action_config.user_ids.includes(m.id) ? 'bg-purple-50' : ''">
                                <input type="checkbox" :checked="form.action_config.user_ids.includes(m.id)" @change="toggleUser(m.id)" class="rounded border-gray-300 text-[#4e1a77] focus:ring-[#4e1a77]" />
                                {{ m.name }} <span class="text-xs text-gray-400">({{ m.role }})</span>
                            </label>
                        </div>

                        <!-- Email specific -->
                        <template v-if="form.action_type === 'send_email'">
                            <div class="mt-3">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Email Subject</label>
                                <input v-model="form.action_config.subject" type="text" placeholder="e.g. Weekly Live Projects Summary" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                            </div>
                            <div class="mt-3">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Email Body (optional)</label>
                                <textarea v-model="form.action_config.email_body" rows="2" placeholder="Custom message (HTML supported). Project list is auto-appended." class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm resize-none" />
                            </div>
                        </template>

                        <!-- Notification specific -->
                        <template v-if="form.action_type === 'send_notification'">
                            <div class="mt-3">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Notification Title</label>
                                <input v-model="form.action_config.title" type="text" placeholder="e.g. Project {{project_name}} is live" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                            </div>
                            <div class="mt-3">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Message</label>
                                <input v-model="form.action_config.message" type="text" placeholder="Use {{project_name}} as placeholder" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                            </div>
                        </template>
                    </div>
                </div>

                <div class="sticky bottom-0 bg-white border-t border-gray-200 px-6 py-3 rounded-b-xl flex justify-end gap-2">
                    <button @click="showModal = false" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button @click="save" :disabled="saving || !form.name" class="rounded-lg bg-[#4e1a77] px-5 py-2 text-sm font-semibold text-white hover:bg-[#3d1560] disabled:opacity-50">
                        {{ saving ? 'Saving...' : (editingId ? 'Update' : 'Create') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ═══════════ Logs Slide-Over ═══════════ -->
    <Teleport to="body">
        <div v-if="logsAutomation" class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/30" @click="closeLogs"></div>
            <div class="relative w-full max-w-md bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Automation Logs</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ logsAutomation.name }}</p>
                    </div>
                    <button @click="closeLogs" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
                    <div v-if="logsLoading" class="text-center py-8">
                        <svg class="mx-auto h-6 w-6 animate-spin text-[#4e1a77]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    </div>
                    <div v-else-if="!logs.length" class="text-center py-8 text-gray-400 text-sm">No runs yet.</div>
                    <div v-for="log in logs" :key="log.id" class="rounded-lg border p-3" :class="log.status === 'success' ? 'border-green-200 bg-green-50/50' : 'border-red-200 bg-red-50/50'">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold" :class="log.status === 'success' ? 'text-green-700' : 'text-red-700'">{{ log.status }}</span>
                            <span class="text-[10px] text-gray-400">{{ timeAgo(log.created_at) }}</span>
                        </div>
                        <p class="text-xs text-gray-600">{{ log.message }}</p>
                        <p v-if="log.details?.project_ids?.length" class="text-[10px] text-gray-400 mt-1">{{ log.details.project_ids.length }} project(s)</p>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

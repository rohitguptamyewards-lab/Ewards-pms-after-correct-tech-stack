<script setup>
import { ref, computed, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

const { error: toastError } = useToast();

defineOptions({ layout: AppLayout });

const props = defineProps({
    workLogs: { type: Object, default: () => ({ data: [], links: [] }) },
    projects: { type: [Array, Object], default: () => [] },
    teamMembers: { type: [Array, Object], default: () => [] },
    weekTotal: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.role);
const canViewAllWorklogs = computed(() => ['manager', 'analyst_head', 'senior_developer'].includes(role.value));
const canUseHiddenEodCopy = computed(() => !canViewAllWorklogs.value);
const CUSTOM_PROJECT_VALUE = '__new_project__';
const projectsList = computed(() => {
    if (Array.isArray(props.projects)) return props.projects;
    if (Array.isArray(props.projects?.data)) return props.projects.data;
    return [];
});

// --- Mode: 'manual' or 'timer' ---
const mode = ref('manual');

// --- Form state ---
const form = ref({
    project_ids: [],   // multi-select (up to 3)
    project_id: '',    // kept for custom work only
    project_name: '',
    log_date: new Date().toISOString().split('T')[0],
    start_time: '',
    end_time: '',
    note: '',
    blocker: '',
});

const showProjectDropdown = ref(false);

function toggleProjectSelection(id) {
    if (id === CUSTOM_PROJECT_VALUE) {
        form.value.project_id = form.value.project_id === CUSTOM_PROJECT_VALUE ? '' : CUSTOM_PROJECT_VALUE;
        form.value.project_ids = [];
        return;
    }
    form.value.project_id = '';
    form.value.project_name = '';
    const idx = form.value.project_ids.indexOf(id);
    if (idx !== -1) {
        form.value.project_ids.splice(idx, 1);
    } else if (form.value.project_ids.length < 3) {
        form.value.project_ids.push(id);
    }
}

function removeProjectSelection(id) {
    form.value.project_ids = form.value.project_ids.filter(p => p !== id);
}

function projectName(id) {
    return projectsList.value.find(p => p.id == id)?.name || id;
}

const formErrors = ref({});
const submitting = ref(false);
const editErrors = ref({});
const editSubmitting = ref(false);
const editOpen = ref(false);
const editForm = ref({
    id: null,
    project_id: '',
    log_date: '',
    start_time: '',
    end_time: '',
    status: 'done',
    note: '',
    blocker: '',
});

// --- Timer state ---
const timerRunning = ref(false);
const timerStartedAt = ref(null);
const timerElapsed = ref(0);
const copyNotice = ref('');
let timerInterval = null;
let copyNoticeTimer = null;

function padZero(n) {
    return String(n).padStart(2, '0');
}

function formatElapsed(totalSeconds) {
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    return `${padZero(h)}:${padZero(m)}:${padZero(s)}`;
}

function nowTimeString() {
    const now = new Date();
    return `${padZero(now.getHours())}:${padZero(now.getMinutes())}`;
}

function todayString() {
    return new Date().toISOString().split('T')[0];
}

function startTimer() {
    if (!canSubmitProjectSelection.value) return;

    timerRunning.value = true;
    timerStartedAt.value = new Date();
    timerElapsed.value = 0;
    form.value.start_time = nowTimeString();
    form.value.log_date = todayString();

    timerInterval = setInterval(() => {
        const now = new Date();
        timerElapsed.value = Math.floor((now - timerStartedAt.value) / 1000);
    }, 1000);
}

async function stopTimer() {
    clearInterval(timerInterval);
    timerInterval = null;
    timerRunning.value = false;
    form.value.end_time = nowTimeString();

    await addEntry();
    timerElapsed.value = 0;
    timerStartedAt.value = null;
}

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
    if (copyNoticeTimer) clearTimeout(copyNoticeTimer);
});

// --- Duration calculation ---
function calcDurationSeconds(start, end) {
    if (!start || !end) return 0;
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    const diff = (eh * 60 + em) - (sh * 60 + sm);
    return diff > 0 ? diff * 60 : 0;
}

const calculatedDuration = computed(() => {
    const sec = calcDurationSeconds(form.value.start_time, form.value.end_time);
    if (sec <= 0) return '';
    return formatElapsed(sec);
});

const canSubmitProjectSelection = computed(() => {
    if (form.value.project_ids.length > 0) return true;
    if (form.value.project_id === CUSTOM_PROJECT_VALUE) return !!form.value.project_name?.trim();
    return false;
});

function buildWorkLogPayloads() {
    const base = {
        log_date: form.value.log_date,
        start_time: form.value.start_time,
        end_time: form.value.end_time,
        note: form.value.note,
        blocker: form.value.blocker,
    };
    if (form.value.project_id === CUSTOM_PROJECT_VALUE) {
        return [{ ...base, project_name: form.value.project_name.trim() }];
    }
    return form.value.project_ids.map(id => ({ ...base, project_id: parseInt(id, 10) }));
}

// --- Add entry ---
async function addEntry() {
    if (!canSubmitProjectSelection.value || !form.value.note?.trim() || !form.value.start_time || !form.value.end_time) return;
    submitting.value = true;
    formErrors.value = {};

    try {
        const payloads = buildWorkLogPayloads();
        await Promise.all(payloads.map(p => axios.post('/api/v1/work-logs', p)));

        const keptIds = [...form.value.project_ids];
        form.value = {
            project_ids: keptIds,
            project_id: '',
            project_name: '',
            log_date: new Date().toISOString().split('T')[0],
            start_time: '',
            end_time: '',
            note: '',
            blocker: '',
        };
        showProjectDropdown.value = false;
        router.reload({ only: ['workLogs', 'weekTotal', 'projects'] });
    } catch (err) {
        if (err.response?.status === 422) {
            formErrors.value = err.response.data.errors || {};
        }
    } finally {
        submitting.value = false;
    }
}

// --- Delete entry ---
async function deleteEntry(id) {
    if (!confirm('Delete this work log?')) return;
    await axios.delete(`/api/v1/work-logs/${id}`);
    router.reload({ only: ['workLogs', 'weekTotal'] });
}

function openEdit(log) {
    editErrors.value = {};
    editForm.value = {
        id: log.id,
        project_id: log.project_id ? String(log.project_id) : '',
        log_date: (log.log_date || '').split('T')[0],
        start_time: (log.start_time || '').slice(0, 5),
        end_time: (log.end_time || '').slice(0, 5),
        status: log.status || 'done',
        note: log.note || '',
        blocker: log.blocker || '',
    };
    editOpen.value = true;
}

function closeEdit() {
    editOpen.value = false;
    editErrors.value = {};
}

// ── Inline description edit (Clockify-style) ──────────────
const inlineEditId = ref(null);
const inlineEditValue = ref('');

// ── Inline time edit ──────────────────────────────────────
const inlineTimeId = ref(null);
const inlineTimeStart = ref('');
const inlineTimeEnd = ref('');

function startInlineTime(log) {
    inlineTimeId.value = log.id;
    inlineTimeStart.value = (log.start_time || '').slice(0, 5);
    inlineTimeEnd.value = (log.end_time || '').slice(0, 5);
}

async function saveInlineTime(log) {
    const id = inlineTimeId.value;
    inlineTimeId.value = null;
    if (inlineTimeStart.value === (log.start_time || '').slice(0, 5) && inlineTimeEnd.value === (log.end_time || '').slice(0, 5)) return;
    try {
        await axios.put(`/api/v1/work-logs/${id}`, { start_time: inlineTimeStart.value, end_time: inlineTimeEnd.value });
        log.start_time = inlineTimeStart.value;
        log.end_time = inlineTimeEnd.value;
        router.reload({ only: ['workLogs', 'weekTotal'] });
    } catch (e) { toastError('Failed to save time: ' + (e.response?.data?.message || e.message)); }
}

function cancelInlineTime() {
    inlineTimeId.value = null;
}

function startInlineEdit(log) {
    inlineEditId.value = log.id;
    inlineEditValue.value = log.note || '';
}

async function saveInlineDesc(log) {
    const id = inlineEditId.value;
    const newNote = inlineEditValue.value.trim();
    inlineEditId.value = null;
    if (newNote === (log.note || '').trim()) return;
    try {
        await axios.put(`/api/v1/work-logs/${id}`, { note: newNote });
        log.note = newNote;
    } catch (e) { toastError('Failed to save description: ' + (e.response?.data?.message || e.message)); }
}

function cancelInlineEdit() {
    inlineEditId.value = null;
}

const editDuration = computed(() => {
    const sec = calcDurationSeconds(editForm.value.start_time, editForm.value.end_time);
    if (sec <= 0) return '';
    return formatElapsed(sec);
});

async function saveEdit() {
    if (!editForm.value.id || !editForm.value.project_id || !editForm.value.log_date || !editForm.value.start_time || !editForm.value.end_time) {
        return;
    }

    editSubmitting.value = true;
    editErrors.value = {};

    try {
        await axios.put(`/api/v1/work-logs/${editForm.value.id}`, {
            project_id: parseInt(editForm.value.project_id, 10),
            log_date: editForm.value.log_date,
            start_time: editForm.value.start_time,
            end_time: editForm.value.end_time,
            note: editForm.value.note,
        });

        closeEdit();
        router.reload({ only: ['workLogs', 'weekTotal'] });
    } catch (err) {
        if (err.response?.status === 422) {
            editErrors.value = err.response.data.errors || {};
        }
    } finally {
        editSubmitting.value = false;
    }
}

// --- Group logs by date ---
const groupedLogs = computed(() => {
    const groups = {};
    (props.workLogs?.data || []).forEach(log => {
        const date = log.log_date?.split('T')[0] || log.log_date;
        if (!groups[date]) groups[date] = { date, logs: [], total: 0 };
        groups[date].logs.push(log);
        groups[date].total += parseFloat(log.hours_spent || 0);
    });
    return Object.values(groups).sort((a, b) => b.date.localeCompare(a.date));
});

// --- Date/time formatting ---
function formatDate(d) {
    if (!d) return '';
    const date = new Date(d + 'T00:00:00');
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (date.toDateString() === today.toDateString()) return 'Today';
    if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

function formatTime(t) {
    if (!t || !t.includes(':')) return '';
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    if (isNaN(hr)) return t;
    const ampm = hr >= 12 ? 'PM' : 'AM';
    const hr12 = hr % 12 || 12;
    return `${hr12}:${m} ${ampm}`;
}

function hoursToHMS(hours) {
    const total = Math.round(parseFloat(hours) * 3600);
    return formatElapsed(total);
}

function dailyTotalHMS(totalHours) {
    return hoursToHMS(totalHours);
}

function weekTotalFormatted() {
    return hoursToHMS(props.weekTotal);
}

// --- Project color dots ---
const dotColors = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6', '#6366f1'];

function projectDotColor(projectId) {
    return dotColors[(projectId || 0) % dotColors.length];
}

// --- Filter state ---
const filterForm = ref({ ...props.filters });

function applyFilters() {
    router.get('/work-logs', filterForm.value, { preserveState: true });
}

function clearFilters() {
    filterForm.value = {};
    router.get('/work-logs', {}, { preserveState: true });
}

// --- 3-dot menu ---
const openMenuId = ref(null);

function toggleMenu(id) {
    openMenuId.value = openMenuId.value === id ? null : id;
}

function closeMenu() {
    openMenuId.value = null;
}

function toLocalDateInput(value = new Date()) {
    const date = value instanceof Date ? value : new Date(value);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function isTodayGroup(groupDate) {
    return groupDate === toLocalDateInput();
}

function formatClipboardDate(date) {
    if (!date) return '';

    const [year, month, day] = String(date).split('-');
    return `${day}/${month}/${year}`;
}

function formatClipboardTime(time) {
    if (!time) return '';

    const [hours = '00', minutes = '00'] = String(time).split(':');
    return `${hours.padStart(2, '0')}:${minutes.padStart(2, '0')}`;
}

function normalizeClipboardDescription(note) {
    const normalized = String(note || '').replace(/\s+/g, ' ').trim();
    return normalized || 'No description';
}

function buildTodayClipboardText(group) {
    const lines = (group.logs || []).map((log, index) => {
        const description = normalizeClipboardDescription(log.note);
        const startTime = formatClipboardTime(log.start_time);
        const endTime = formatClipboardTime(log.end_time);

        return `${index + 1}.${description}(${startTime}-${endTime})`;
    });

    return [`EOD(${formatClipboardDate(group.date)})`, ...lines].join('\n');
}

async function writeTextToClipboard(text) {
    if (navigator?.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
}

function showCopyNotice(message) {
    copyNotice.value = message;

    if (copyNoticeTimer) clearTimeout(copyNoticeTimer);
    copyNoticeTimer = setTimeout(() => {
        copyNotice.value = '';
        copyNoticeTimer = null;
    }, 2000);
}

async function copyTodayWorklogs(group) {
    if (!canUseHiddenEodCopy.value || !isTodayGroup(group.date) || !group.logs?.length) return;

    try {
        await writeTextToClipboard(buildTodayClipboardText(group));
        showCopyNotice('Copied to clipboard');
    } catch (error) {
        showCopyNotice('Copy failed');
    }
}

// --- Resume a log entry (start timer with same project + note) ---
function resumeLog(log) {
    mode.value = 'timer';
    form.value.project_ids = log.project_id ? [log.project_id] : [];
    form.value.project_id = '';
    form.value.note = log.note || '';
    form.value.log_date = todayString();
    if (!timerRunning.value) {
        startTimer();
    }
}

// --- Selected project name for display ---
const selectedProjectName = computed(() => {
    if (!form.value.project_id) return '';
    if (form.value.project_id === CUSTOM_PROJECT_VALUE) {
        return form.value.project_name || '';
    }

    const p = projectsList.value.find(
        proj => proj.id == form.value.project_id
    );
    return p ? p.name : '';
});
</script>

<template>
    <Head title="Work Logs" />

    <div class="space-y-0" @click="closeMenu">
        <!-- ===================== TOP BAR ===================== -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm mb-5">
            <!-- Mode toggle row -->
            <div class="flex items-center justify-end border-b border-gray-100 px-4 py-1.5">
                <div class="inline-flex items-center rounded-md bg-gray-100 p-0.5 text-xs">
                    <button
                        @click="mode = 'manual'"
                        class="rounded px-3 py-1 font-medium transition-colors"
                        :class="mode === 'manual' ? 'bg-white text-[#4e1a77] shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    >
                        Manual
                    </button>
                    <button
                        @click="mode = 'timer'"
                        class="rounded px-3 py-1 font-medium transition-colors"
                        :class="mode === 'timer' ? 'bg-white text-[#4e1a77] shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    >
                        Timer
                    </button>
                </div>
            </div>

            <!-- Input row -->
            <div class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
                <!-- Note input (required) -->
                <div class="flex-1 min-w-0">
                    <input
                        v-model="form.note"
                        type="text"
                        placeholder="What have you worked on? *"
                        class="w-full border-0 border-b bg-transparent px-0 py-1.5 text-sm text-gray-800 placeholder-gray-400 focus:border-b focus:border-[#4e1a77] focus:outline-none focus:ring-0"
                        :class="formErrors.note ? 'border-red-400' : 'border-transparent'"
                    />
                    <p v-if="formErrors.note" class="text-xs text-red-500 mt-0.5">{{ formErrors.note[0] }}</p>
                </div>

                <!-- Project multi-select (up to 3) -->
                <div class="shrink-0 relative">
                    <!-- Selected tags -->
                    <div v-if="form.project_ids.length || form.project_id === CUSTOM_PROJECT_VALUE" class="flex flex-wrap gap-1 mb-1">
                        <span
                            v-for="id in form.project_ids" :key="id"
                            class="inline-flex items-center gap-1 rounded-full bg-[#f5f0ff] border border-[#ddd0f7] px-2 py-0.5 text-xs font-medium text-[#4e1a77] max-w-[140px]"
                        >
                            <span class="truncate">{{ projectName(id) }}</span>
                            <button type="button" @click.stop="removeProjectSelection(id)" class="text-[#4e1a77]/60 hover:text-[#4e1a77] leading-none">&times;</button>
                        </span>
                        <span v-if="form.project_id === CUSTOM_PROJECT_VALUE" class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-xs font-medium text-amber-700">
                            Custom
                            <button type="button" @click.stop="form.project_id = ''; form.project_name = ''" class="text-amber-500 hover:text-amber-700 leading-none">&times;</button>
                        </span>
                    </div>

                    <!-- Trigger button -->
                    <button
                        type="button"
                        @click.stop="showProjectDropdown = !showProjectDropdown"
                        class="flex items-center gap-1.5 rounded-md border bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:outline-none focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        :class="(formErrors.project_id || formErrors.project_name) ? 'border-red-400' : (showProjectDropdown ? 'border-[#4e1a77]' : 'border-gray-200')"
                    >
                        <span :class="canSubmitProjectSelection ? 'text-gray-700' : 'text-gray-400'">
                            {{ form.project_ids.length ? `${form.project_ids.length}/3 project${form.project_ids.length > 1 ? 's' : ''}` : (form.project_id === CUSTOM_PROJECT_VALUE ? 'Custom work' : 'Project *') }}
                        </span>
                        <svg class="h-3.5 w-3.5 text-gray-400 transition-transform shrink-0" :class="{ 'rotate-180': showProjectDropdown }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                    </button>

                    <!-- Dropdown -->
                    <div
                        v-if="showProjectDropdown"
                        class="absolute top-full left-0 mt-1 z-30 w-64 rounded-lg border border-gray-200 bg-white shadow-lg max-h-60 overflow-y-auto"
                        @click.stop
                    >
                        <div class="px-3 py-1.5 border-b border-gray-100">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Select up to 3 projects</p>
                        </div>
                        <!-- Custom work option -->
                        <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-amber-50 cursor-pointer transition-colors border-b border-gray-100">
                            <input
                                type="checkbox"
                                :checked="form.project_id === CUSTOM_PROJECT_VALUE"
                                @change="toggleProjectSelection(CUSTOM_PROJECT_VALUE)"
                                class="rounded border-gray-300 text-amber-500 focus:ring-amber-400"
                            />
                            <span class="text-sm text-amber-700 font-medium">+ Custom work</span>
                        </label>
                        <!-- Real projects -->
                        <label
                            v-for="p in projectsList"
                            :key="p.id"
                            class="flex items-center gap-2.5 px-3 py-2 hover:bg-[#f5f0ff] cursor-pointer transition-colors"
                            :class="{ 'opacity-40 pointer-events-none': form.project_ids.length >= 3 && !form.project_ids.includes(p.id) && form.project_id !== CUSTOM_PROJECT_VALUE }"
                        >
                            <input
                                type="checkbox"
                                :checked="form.project_ids.includes(p.id)"
                                :disabled="form.project_id === CUSTOM_PROJECT_VALUE || (form.project_ids.length >= 3 && !form.project_ids.includes(p.id))"
                                @change="toggleProjectSelection(p.id)"
                                class="rounded border-gray-300 text-[#4e1a77] focus:ring-[#4e1a77]"
                            />
                            <span class="text-sm text-gray-700 truncate">{{ p.name }}</span>
                        </label>
                        <div class="px-3 py-1.5 border-t border-gray-100 flex justify-end">
                            <button type="button" @click="showProjectDropdown = false" class="text-xs text-[#4e1a77] font-medium hover:underline">Done</button>
                        </div>
                    </div>
                </div>

                <!-- Custom work name input -->
                <div v-if="form.project_id === CUSTOM_PROJECT_VALUE" class="shrink-0 lg:min-w-[200px]">
                    <input
                        v-model="form.project_name"
                        type="text"
                        placeholder="Custom work name *"
                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        :class="{ 'border-red-400': formErrors.project_name }"
                    />
                </div>

                <!-- Divider (desktop) -->
                <div class="hidden lg:block h-8 w-px bg-gray-200"></div>

                <!-- MANUAL: time inputs + date + duration + ADD -->
                <template v-if="mode === 'manual'">
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            v-model="form.start_time"
                            type="time"
                            class="rounded-md border border-gray-200 bg-gray-50 px-2 py-2 text-sm text-gray-700 focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        />
                        <span class="text-gray-400 text-sm">-</span>
                        <input
                            v-model="form.end_time"
                            type="time"
                            class="rounded-md border border-gray-200 bg-gray-50 px-2 py-2 text-sm text-gray-700 focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        />
                        <input
                            v-model="form.log_date"
                            type="date"
                            class="rounded-md border border-gray-200 bg-gray-50 px-2 py-2 text-sm text-gray-700 focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        />
                    </div>

                    <!-- Duration display -->
                    <div class="shrink-0 min-w-[72px] text-center">
                        <span class="font-mono text-sm font-semibold" :class="calculatedDuration ? 'text-[#4e1a77]' : 'text-gray-300'">
                            {{ calculatedDuration || '00:00:00' }}
                        </span>
                    </div>

                    <!-- ADD button -->
                    <button
                        @click="addEntry"
                        :disabled="submitting || !canSubmitProjectSelection || !form.note?.trim() || !form.start_time || !form.end_time"
                        class="shrink-0 rounded-lg bg-[#4e1a77] px-6 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#3d1560] disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        ADD
                    </button>
                </template>

                <!-- TIMER: elapsed display + start/stop -->
                <template v-else>
                    <div class="flex items-center gap-3">
                        <!-- Timer display -->
                        <div class="min-w-[88px] text-center">
                            <span class="font-mono text-lg font-semibold" :class="timerRunning ? 'text-[#4e1a77]' : 'text-gray-400'">
                                {{ formatElapsed(timerElapsed) }}
                            </span>
                        </div>

                        <!-- Start / Stop -->
                        <button
                            v-if="!timerRunning"
                            @click="startTimer"
                            :disabled="!canSubmitProjectSelection || !form.note?.trim()"
                            class="shrink-0 rounded-lg bg-[#4e1a77] px-6 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#3d1560] disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            START
                        </button>
                        <button
                            v-else
                            @click="stopTimer"
                            class="shrink-0 rounded-lg bg-red-600 px-6 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700"
                        >
                            STOP
                        </button>
                    </div>
                </template>
            </div>

            <!-- Error row -->
            <div v-if="Object.keys(formErrors).length" class="border-t border-red-100 bg-red-50 px-4 py-2 text-xs text-red-600">
                <span v-for="(errs, field) in formErrors" :key="field">{{ errs[0] }} </span>
            </div>
        </div>

        <!-- ===================== FILTERS BAR (privileged roles) ===================== -->
        <div v-if="canViewAllWorklogs" class="rounded-xl border border-gray-200 bg-white shadow-sm mb-4 overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 bg-gradient-to-r from-white to-[#f5f0ff]/30 border-b border-gray-100">
                <svg class="h-4 w-4 text-[#4e1a77]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                </svg>
                <span class="text-sm font-semibold text-gray-900">Filters</span>
                <span v-if="filterForm.user_id || filterForm.project_id || filterForm.date_from || filterForm.date_to" class="rounded-full bg-[#4e1a77] px-1.5 py-0.5 text-[9px] font-bold text-white">Active</span>
            </div>
            <div class="flex flex-wrap gap-3 items-end p-4">
                <div class="min-w-[180px]">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Team Member</label>
                    <select
                        v-model="filterForm.user_id"
                        @change="applyFilters"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                    >
                        <option value="">All Members</option>
                        <option v-for="m in teamMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                    </select>
                </div>
                <div class="min-w-[180px]">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Project</label>
                    <select
                        v-model="filterForm.project_id"
                        @change="applyFilters"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                    >
                        <option value="">All Projects</option>
                        <option v-for="p in projectsList" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                    <input
                        v-model="filterForm.date_from"
                        type="date"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                    <input
                        v-model="filterForm.date_to"
                        type="date"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                    />
                </div>
                <button
                    @click="applyFilters"
                    class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560] transition-colors"
                >
                    Apply
                </button>
                <button
                    v-if="filterForm.user_id || filterForm.project_id || filterForm.date_from || filterForm.date_to"
                    @click="clearFilters"
                    class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    Clear All
                </button>
            </div>
        </div>

        <!-- ===================== WEEK HEADER ===================== -->
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-gray-900">This week</h2>
            <div class="text-right">
                <span class="text-sm text-gray-500">Total: </span>
                <span class="font-mono text-base font-bold text-[#4e1a77]">{{ weekTotalFormatted() }}</span>
            </div>
        </div>

        <!-- ===================== GROUPED WORK LOG ENTRIES ===================== -->
        <div class="space-y-4">
            <div v-for="group in groupedLogs" :key="group.date">
                <!-- Date header -->
                <div class="flex items-center justify-between px-1 py-2">
                    <span
                        class="text-xs font-semibold text-gray-500 uppercase tracking-wide"
                        @click="copyTodayWorklogs(group)"
                    >
                        {{ formatDate(group.date) }}
                    </span>
                    <span class="font-mono text-xs font-bold text-[#4e1a77]">{{ dailyTotalHMS(group.total) }}</span>
                </div>

                <!-- Entries - Clockify style rows -->
                <div class="space-y-1">
                    <div
                        v-for="log in group.logs"
                        :key="log.id"
                        class="group flex items-center bg-white border border-gray-200 rounded-lg px-4 py-2.5 hover:border-gray-300 hover:shadow-sm transition-all"
                    >
                        <!-- Description + Project -->
                        <div class="flex-1 min-w-0 flex items-center gap-3">
                            <!-- Inline editable description -->
                            <input
                                v-if="inlineEditId === log.id"
                                :value="inlineEditValue"
                                @input="inlineEditValue = $event.target.value"
                                @blur="saveInlineDesc(log)"
                                @keydown.enter="saveInlineDesc(log)"
                                @keydown.esc="cancelInlineEdit"
                                class="flex-1 min-w-0 rounded border border-[#4e1a77] px-2 py-0.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#4e1a77]"
                                autofocus
                                @click.stop
                            />
                            <p
                                v-else
                                @click.stop="startInlineEdit(log)"
                                class="text-sm truncate cursor-text hover:text-[#4e1a77] transition-colors"
                                :class="log.note ? 'text-gray-800' : 'text-gray-400 italic'"
                                title="Click to edit description"
                            >
                                {{ log.note || 'Add description...' }}
                            </p>
                            <Link
                                :href="`/projects/${log.project_id}`"
                                class="hidden sm:inline-flex items-center gap-1.5 shrink-0 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 hover:bg-[#4e1a77]/10 hover:text-[#4e1a77] transition-colors"
                            >
                                <span
                                    class="inline-block h-2 w-2 rounded-full shrink-0"
                                    :style="{ backgroundColor: projectDotColor(log.project_id) }"
                                ></span>
                                {{ log.project_name }}
                            </Link>
                            <span v-if="canViewAllWorklogs && log.user_name" class="hidden md:inline text-[10px] text-gray-400 shrink-0">{{ log.user_name }}</span>
                        </div>

                        <!-- Right side: tag icon, time range, calendar, duration, play, 3-dot -->
                        <div class="flex items-center gap-2 sm:gap-3 shrink-0 ml-3">
                            <!-- Tag icon -->
                            <svg class="hidden lg:block h-4 w-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                            </svg>

                            <!-- Billable icon -->
                            <span class="hidden lg:inline text-gray-300 text-sm font-medium">&#8377;</span>

                            <!-- Time range (click to edit) -->
                            <template v-if="inlineTimeId === log.id">
                                <input
                                    v-model="inlineTimeStart"
                                    type="time"
                                    class="rounded border border-[#4e1a77] px-1 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-[#4e1a77] w-[90px]"
                                    @keydown.enter="saveInlineTime(log)"
                                    @keydown.esc="cancelInlineTime"
                                    @click.stop
                                />
                                <span class="text-gray-400 text-xs">-</span>
                                <input
                                    v-model="inlineTimeEnd"
                                    type="time"
                                    class="rounded border border-[#4e1a77] px-1 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-[#4e1a77] w-[90px]"
                                    @keydown.enter="saveInlineTime(log)"
                                    @keydown.esc="cancelInlineTime"
                                    @blur="saveInlineTime(log)"
                                    @click.stop
                                />
                            </template>
                            <span
                                v-else
                                @click.stop="startInlineTime(log)"
                                class="hidden sm:inline text-xs text-gray-500 font-medium whitespace-nowrap cursor-text hover:text-[#4e1a77] transition-colors"
                                title="Click to edit time"
                            >
                                {{ formatTime(log.start_time) }} - {{ formatTime(log.end_time) }}
                            </span>

                            <!-- Calendar icon -->
                            <svg class="hidden md:block h-4 w-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>

                            <!-- Duration -->
                            <span class="font-mono text-sm font-bold text-gray-900 min-w-[72px] text-right">
                                {{ hoursToHMS(log.hours_spent) }}
                            </span>

                            <!-- Play/resume icon -->
                            <button
                                @click.stop="resumeLog(log)"
                                class="hidden sm:flex h-7 w-7 items-center justify-center rounded-full text-[#4e1a77] hover:bg-[#4e1a77]/10 transition-colors"
                                title="Start similar"
                            >
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5.14v14l11-7-11-7z" />
                                </svg>
                            </button>

                            <!-- 3-dot menu -->
                            <div class="relative">
                                <button
                                    @click.stop="toggleMenu(log.id)"
                                    class="flex h-7 w-7 items-center justify-center rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all"
                                    title="Actions"
                                >
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <circle cx="4" cy="10" r="1.5" />
                                        <circle cx="10" cy="10" r="1.5" />
                                        <circle cx="16" cy="10" r="1.5" />
                                    </svg>
                                </button>
                                <div
                                    v-if="openMenuId === log.id"
                                    class="absolute right-0 top-8 z-10 w-32 rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                                >
                                    <button
                                        @click.stop="openEdit(log); closeMenu()"
                                        class="flex w-full items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.651-1.651a2.121 2.121 0 113 3L10.582 16.768a4.5 4.5 0 01-1.897 1.13L6 18l.102-2.685a4.5 4.5 0 011.13-1.897L16.862 4.487z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125L16.875 4.5" />
                                        </svg>
                                        Edit
                                    </button>
                                    <button
                                        @click.stop="deleteEntry(log.id); closeMenu()"
                                        class="flex w-full items-center gap-2 px-3 py-2 text-xs text-red-600 hover:bg-red-50"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div
            v-if="editOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="closeEdit"
        >
            <div class="w-full max-w-lg rounded-xl border border-gray-200 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Edit Work Log</h3>
                    <button @click="closeEdit" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4 px-5 py-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">Project</label>
                        <select
                            v-model="editForm.project_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                            :class="{ 'border-red-400': editErrors.project_id }"
                        >
                            <option value="">Select project</option>
                            <option v-for="p in projectsList" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500">Date</label>
                            <input
                                v-model="editForm.log_date"
                                type="date"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                                :class="{ 'border-red-400': editErrors.log_date }"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500">Start</label>
                            <input
                                v-model="editForm.start_time"
                                type="time"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                                :class="{ 'border-red-400': editErrors.start_time }"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500">End</label>
                            <input
                                v-model="editForm.end_time"
                                type="time"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                                :class="{ 'border-red-400': editErrors.end_time }"
                            />
                        </div>
                    </div>

                    <div class="rounded-lg border border-[#e8ddf0] bg-[#f5f0ff] px-3 py-2">
                        <span class="text-xs text-gray-600">Duration: </span>
                        <span class="font-mono text-sm font-semibold text-[#4e1a77]">{{ editDuration || '00:00:00' }}</span>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">Description</label>
                        <textarea
                            v-model="editForm.note"
                            rows="3"
                            placeholder="What did you work on?"
                            class="w-full resize-none rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                            :class="{ 'border-red-400': editErrors.note }"
                        />
                    </div>

                    <div v-if="Object.keys(editErrors).length" class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-600">
                        <span v-for="(errs, field) in editErrors" :key="field">{{ errs[0] }} </span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-5 py-3">
                    <button
                        @click="closeEdit"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveEdit"
                        :disabled="editSubmitting || !editForm.project_id || !editForm.start_time || !editForm.end_time || !editForm.log_date"
                        class="rounded-lg bg-[#4e1a77] px-4 py-1.5 text-xs font-semibold text-white hover:bg-[#3d1560] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span v-if="editSubmitting">Saving...</span>
                        <span v-else>Save</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!groupedLogs.length" class="rounded-xl border border-gray-200 bg-white px-5 py-16 text-center shadow-sm mt-4">
            <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm text-gray-400">No work logs found.</p>
            <p class="mt-1 text-xs text-gray-400">Start tracking your time using the bar above.</p>
        </div>

        <!-- Pagination -->
        <div v-if="workLogs.links?.length > 3" class="flex justify-center gap-1 mt-4">
            <Link
                v-for="link in workLogs.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded px-3 py-1 text-xs"
                :class="link.active ? 'bg-[#4e1a77] text-white' : link.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300'"
                v-html="link.label"
                preserve-scroll
            />
        </div>

        <div
            v-if="copyNotice"
            class="pointer-events-none fixed bottom-4 right-4 rounded-lg bg-gray-900 px-3 py-2 text-xs font-medium text-white shadow-lg"
        >
            {{ copyNotice }}
        </div>
    </div>
</template>

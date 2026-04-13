<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineOptions({ layout: AppLayout });

const props = defineProps({
    projects: { type: [Array, Object], default: () => [] },
    lastEndTime: { type: String, default: null },
});
const CUSTOM_PROJECT_VALUE = '__new_project__';
const projectsList = computed(() => {
    if (Array.isArray(props.projects)) return props.projects;
    if (Array.isArray(props.projects?.data)) return props.projects.data;
    return [];
});

const form = useForm({
    project_id: '',
    project_name: '',
    log_date: new Date().toISOString().split('T')[0],
    start_time: props.lastEndTime ? props.lastEndTime.substring(0, 5) : '',
    end_time: '',
    status: 'done',
    note: '',
    blocker: '',
});

const calculatedHours = computed(() => {
    if (!form.start_time || !form.end_time) return null;
    const [sh, sm] = form.start_time.split(':').map(Number);
    const [eh, em] = form.end_time.split(':').map(Number);
    const diff = (eh * 60 + em) - (sh * 60 + sm);
    if (diff <= 0) return null;
    return (diff / 60).toFixed(2);
});

const canSubmitProjectSelection = computed(() => {
    if (!form.project_id) return false;
    if (form.project_id === CUSTOM_PROJECT_VALUE) {
        return !!form.project_name?.trim();
    }
    return true;
});

function submit() {
    form.transform(data => {
        if (data.project_id === CUSTOM_PROJECT_VALUE) {
            return {
                ...data,
                project_id: null,
                project_name: data.project_name.trim(),
            };
        }

        return {
            ...data,
            project_id: parseInt(data.project_id),
            project_name: '',
        };
    }).post('/work-logs');
}
</script>

<template>
    <Head title="Log Work" />

    <div class="mx-auto max-w-2xl space-y-4">
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <Link href="/work-logs" class="hover:text-[#4e1a77] transition-colors">Work Logs</Link>
            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-gray-900 font-medium">Log Work</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 bg-gradient-to-r from-white to-[#f5f0ff]/30">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#4e1a77]/10">
                        <svg class="h-5 w-5 text-[#4e1a77]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">Log Work</h1>
                        <p class="text-xs text-gray-500">Record your work activity</p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-5 px-6 py-5">
                <!-- Project -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project *</label>
                    <select v-model="form.project_id" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] transition-colors" :class="{ 'border-red-400 bg-red-50': form.errors.project_id || form.errors.project_name }">
                        <option value="">Select project</option>
                        <option :value="CUSTOM_PROJECT_VALUE">+ Add custom work</option>
                        <option v-for="p in projectsList" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <p v-if="form.errors.project_id" class="mt-1 text-xs text-red-500">{{ form.errors.project_id }}</p>
                    <p v-if="form.errors.project_name" class="mt-1 text-xs text-red-500">{{ form.errors.project_name }}</p>
                    <div v-if="form.project_id === CUSTOM_PROJECT_VALUE" class="mt-2 relative">
                        <input
                            v-model="form.project_name"
                            type="text"
                            placeholder="Enter custom work name"
                            class="block w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] transition-colors"
                            :class="{ 'border-red-400 bg-red-50': form.errors.project_name }"
                        />
                        <svg class="absolute left-3 top-3 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.651-1.651a2.121 2.121 0 013 3L10.582 16.768a4.5 4.5 0 01-1.897 1.13L6 18l.102-2.685a4.5 4.5 0 011.13-1.897L16.862 4.487z" />
                        </svg>
                    </div>
                </div>

                <!-- Date -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                    <input v-model="form.log_date" type="date" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] transition-colors" :class="{ 'border-red-400 bg-red-50': form.errors.log_date }" />
                    <p v-if="form.errors.log_date" class="mt-1 text-xs text-red-500">{{ form.errors.log_date }}</p>
                </div>

                <!-- Start / End Time -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
                        <input v-model="form.start_time" type="time" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] transition-colors" :class="{ 'border-red-400 bg-red-50': form.errors.start_time }" />
                        <p v-if="form.errors.start_time" class="mt-1 text-xs text-red-500">{{ form.errors.start_time }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
                        <input v-model="form.end_time" type="time" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] transition-colors" :class="{ 'border-red-400 bg-red-50': form.errors.end_time }" />
                        <p v-if="form.errors.end_time" class="mt-1 text-xs text-red-500">{{ form.errors.end_time }}</p>
                    </div>
                </div>

                <!-- Calculated hours display -->
                <div v-if="calculatedHours" class="rounded-lg bg-gradient-to-r from-[#f5f0ff] to-[#ebe0ff] border border-[#e8ddf0] px-4 py-3 flex items-center gap-2">
                    <svg class="h-4 w-4 text-[#4e1a77]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm text-[#4e1a77]">Duration: <strong>{{ calculatedHours }} hours</strong></p>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                    <select v-model="form.status" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] transition-colors">
                        <option value="done">Done</option>
                        <option value="in_progress">In Progress</option>
                        <option value="blocked">Blocked</option>
                    </select>
                </div>

                <!-- Note (required) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                    <textarea v-model="form.note" rows="3" placeholder="What did you work on?" class="block w-full resize-none rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] transition-colors" :class="{ 'border-red-400 bg-red-50': form.errors.note }" />
                    <p v-if="form.errors.note" class="mt-1 text-xs text-red-500">{{ form.errors.note }}</p>
                </div>

                <!-- Blocker (shown when status is blocked) -->
                <div v-if="form.status === 'blocked'" class="rounded-lg border border-red-200 bg-red-50/50 p-4">
                    <label class="block text-sm font-medium text-red-600 mb-1">Blocker Description *</label>
                    <textarea v-model="form.blocker" rows="2" placeholder="Describe what's blocking you..." class="block w-full resize-none rounded-lg border border-red-300 bg-white px-3 py-2.5 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors" :class="{ 'border-red-400': form.errors.blocker }" />
                    <p v-if="form.errors.blocker" class="mt-1 text-xs text-red-500">{{ form.errors.blocker }}</p>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                    <Link href="/work-logs" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors">Cancel</Link>
                    <button type="submit" :disabled="form.processing || !canSubmitProjectSelection || !form.note?.trim()" class="rounded-lg bg-[#4e1a77] px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#3d1560] disabled:opacity-50 transition-colors">
                        Log Work
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

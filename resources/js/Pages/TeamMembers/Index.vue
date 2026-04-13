<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { useToast } from '@/composables/useToast';

const { error: toastError, success: toastSuccess } = useToast();

defineOptions({ layout: AppLayout });

const props = defineProps({
    members: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.role);
const isAdmin = computed(() => ['manager', 'analyst_head'].includes(role.value));

// Filters
const filterForm = ref({
    search: props.filters?.search || '',
    role: props.filters?.role || '',
    is_active: props.filters?.is_active ?? '',
});

function applyFilters() {
    const params = {};
    Object.entries(filterForm.value).forEach(([k, v]) => { if (v !== '') params[k] = v; });
    router.get('/team-members', params, { preserveState: true, replace: true });
}

function clearFilters() {
    filterForm.value = { search: '', role: '', is_active: '' };
    router.get('/team-members', {}, { preserveState: true, replace: true });
}

// Inline editing
const editingId = ref(null);
const editForm = ref({});

function startEdit(member) {
    editingId.value = member.id;
    editForm.value = {
        name: member.name,
        email: member.email,
        role: member.role,
    };
}

async function saveEdit(member) {
    try {
        await axios.put(`/api/v1/team-members/${member.id}`, editForm.value);
        editingId.value = null;
        router.reload({ only: ['members'] });
    } catch (e) {
        toastError('Update failed: ' + (e.response?.data?.message || e.message));
    }
}

function cancelEdit() {
    editingId.value = null;
}

// Toggle active
async function toggleActive(member) {
    try {
        await axios.post(`/api/v1/team-members/${member.id}/toggle-active`);
        router.reload({ only: ['members'] });
    } catch (e) {
        toastError('Toggle failed: ' + (e.response?.data?.message || e.message));
    }
}

// Reset password
const resetPasswordId = ref(null);
const newPassword = ref('');
const showPassword = ref(false);

async function resetPassword(member) {
    if (!newPassword.value || newPassword.value.length < 6) return;
    try {
        await axios.put(`/api/v1/team-members/${member.id}`, { password: newPassword.value });
        toastSuccess('Password updated.');
        resetPasswordId.value = null;
        newPassword.value = '';
    } catch (e) {
        toastError('Password reset failed: ' + (e.response?.data?.message || e.message));
    }
}

function hoursDisplay(h) {
    h = parseFloat(h) || 0;
    const hrs = Math.floor(h);
    const mins = Math.round((h - hrs) * 60);
    if (hrs === 0 && mins === 0) return '0h';
    if (mins === 0) return `${hrs}h`;
    return `${hrs}h ${mins}m`;
}

const roleColors = {
    manager: 'bg-purple-100 text-purple-700',
    analyst_head: 'bg-purple-100 text-purple-700',
    analyst: 'bg-blue-100 text-blue-700',
    senior_developer: 'bg-indigo-100 text-indigo-700',
    developer: 'bg-cyan-100 text-cyan-700',
    employee: 'bg-gray-100 text-gray-600',
};

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Team Members" />

    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-900">Team Members</h1>
            <Link
                v-if="isAdmin"
                href="/team-members/create"
                class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#3d1560] transition-colors"
            >
                + Add Member
            </Link>
        </div>

        <!-- Filters -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Search</label>
                    <input
                        v-model="filterForm.search"
                        placeholder="Search by name or email..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]"
                        @keyup.enter="applyFilters"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Role</label>
                    <select v-model="filterForm.role" @change="applyFilters" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                        <option value="">All Roles</option>
                        <option value="manager">Manager</option>
                        <option value="analyst_head">Analyst Head</option>
                        <option value="analyst">Analyst</option>
                        <option value="senior_developer">Senior Developer</option>
                        <option value="developer">Developer</option>
                        <option value="employee">Employee</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 mb-1 block">Status</label>
                    <select v-model="filterForm.is_active" @change="applyFilters" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77]">
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <button @click="applyFilters" class="rounded-lg bg-[#4e1a77] px-4 py-2 text-sm font-medium text-white hover:bg-[#3d1560]">Filter</button>
                <button @click="clearFilters" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Clear</button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-[#ddd0f7] bg-[#f5f0ff] px-4 py-2.5">
                <p class="text-xs text-gray-500">Total</p>
                <p class="text-lg font-bold text-[#4e1a77]">{{ members.length }}</p>
            </div>
            <div class="rounded-xl border border-green-100 bg-green-50 px-4 py-2.5">
                <p class="text-xs text-gray-500">Active</p>
                <p class="text-lg font-bold text-green-700">{{ members.filter(m => m.is_active).length }}</p>
            </div>
            <div class="rounded-xl border border-purple-100 bg-purple-50 px-4 py-2.5">
                <p class="text-xs text-gray-500">Managers</p>
                <p class="text-lg font-bold text-purple-700">{{ members.filter(m => m.role === 'manager').length }}</p>
            </div>
            <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-2.5">
                <p class="text-xs text-gray-500">Analysts</p>
                <p class="text-lg font-bold text-blue-700">{{ members.filter(m => m.role === 'analyst').length }}</p>
            </div>
        </div>

        <!-- Members Table -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Member</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Projects</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Total Hours</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Joined</th>
                        <th v-if="isAdmin" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="m in members" :key="m.id" class="hover:bg-[#f5f0ff]/20 transition-colors" :class="{ 'opacity-50': !m.is_active }">
                        <!-- Member info -->
                        <td class="px-4 py-3">
                            <template v-if="editingId === m.id">
                                <input v-model="editForm.name" class="rounded border border-[#4e1a77] px-2 py-1 text-sm w-full max-w-[180px] focus:ring-1 focus:ring-[#4e1a77]" />
                                <input v-model="editForm.email" class="mt-1 rounded border border-gray-300 px-2 py-1 text-xs w-full max-w-[180px] focus:ring-1 focus:ring-[#4e1a77]" />
                            </template>
                            <template v-else>
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold"
                                         :class="roleColors[m.role] || 'bg-gray-100 text-gray-600'">
                                        {{ (m.name || '?').charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ m.name }}</p>
                                        <p class="text-xs text-gray-400">{{ m.email }}</p>
                                    </div>
                                </div>
                            </template>
                        </td>
                        <!-- Role -->
                        <td class="px-4 py-3">
                            <template v-if="editingId === m.id">
                                <select v-model="editForm.role" class="rounded border border-[#4e1a77] px-2 py-1 text-xs focus:ring-1 focus:ring-[#4e1a77]">
                                    <option value="manager">Manager</option>
                                    <option value="analyst_head">Analyst Head</option>
                                    <option value="analyst">Analyst</option>
                                    <option value="senior_developer">Senior Developer</option>
                                    <option value="developer">Developer</option>
                                    <option value="employee">Employee</option>
                                </select>
                            </template>
                            <template v-else>
                                <span :class="roleColors[m.role] || 'bg-gray-100 text-gray-600'" class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase">{{ m.role }}</span>
                            </template>
                        </td>
                        <!-- Status -->
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase"
                                :class="m.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                            >{{ m.is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <!-- Projects -->
                        <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ m.project_count || 0 }}</td>
                        <!-- Hours -->
                        <td class="px-4 py-3 text-sm text-gray-600">{{ hoursDisplay(m.total_hours) }}</td>
                        <!-- Joined -->
                        <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(m.created_at) }}</td>
                        <!-- Actions -->
                        <td v-if="isAdmin" class="px-4 py-3">
                            <template v-if="editingId === m.id">
                                <div class="flex items-center gap-1.5">
                                    <button @click="saveEdit(m)" class="rounded bg-[#4e1a77] px-2.5 py-1 text-[10px] font-medium text-white hover:bg-[#3d1560]">Save</button>
                                    <button @click="cancelEdit" class="rounded border border-gray-300 px-2.5 py-1 text-[10px] font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex items-center gap-1.5">
                                    <button @click="startEdit(m)" class="rounded border border-gray-200 px-2 py-1 text-[10px] font-medium text-gray-600 hover:bg-gray-50" title="Edit">
                                        Edit
                                    </button>
                                    <button
                                        @click="toggleActive(m)"
                                        class="rounded border px-2 py-1 text-[10px] font-medium"
                                        :class="m.is_active ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-green-200 text-green-600 hover:bg-green-50'"
                                        :title="m.is_active ? 'Deactivate' : 'Activate'"
                                    >
                                        {{ m.is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <template v-if="resetPasswordId === m.id">
                                        <div class="relative inline-flex items-center">
                                            <input v-model="newPassword" :type="showPassword ? 'text' : 'password'" placeholder="New password" class="rounded border border-gray-300 px-2 py-1 pr-7 text-[10px] w-28 focus:ring-1 focus:ring-[#4e1a77]" />
                                            <button type="button" @click="showPassword = !showPassword" class="absolute right-1 text-gray-400 hover:text-gray-600" title="Toggle password visibility">
                                                <svg v-if="showPassword" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                                <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                            </button>
                                        </div>
                                        <button @click="resetPassword(m)" class="rounded bg-orange-500 px-2 py-1 text-[10px] font-medium text-white hover:bg-orange-600">Set</button>
                                        <button @click="resetPasswordId = null; newPassword = ''; showPassword = false" class="text-[10px] text-gray-400 hover:text-gray-600">Cancel</button>
                                    </template>
                                    <button v-else @click="resetPasswordId = m.id" class="rounded border border-orange-200 px-2 py-1 text-[10px] font-medium text-orange-600 hover:bg-orange-50" title="Reset Password">
                                        Password
                                    </button>
                                </div>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!members?.length" class="px-5 py-12 text-center text-sm text-gray-400">No team members found</p>
        </div>
    </div>
</template>

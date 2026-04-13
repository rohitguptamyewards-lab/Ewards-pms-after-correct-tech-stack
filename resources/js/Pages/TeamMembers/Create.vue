<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineOptions({ layout: AppLayout });

const form = ref({
    name: '',
    email: '',
    password: '',
    role: 'employee',
    joined_date: '',
});

const errors = ref({});
const submitting = ref(false);
const showPassword = ref(false);

const roleOptions = [
    { value: 'manager',          label: 'Manager',          description: 'Full admin access - manage everything' },
    { value: 'analyst_head',     label: 'Analyst Head',     description: 'Admin level - full access to projects, team, reports' },
    { value: 'analyst',          label: 'Analyst',          description: 'Can create projects, view all, manage assignments' },
    { value: 'senior_developer', label: 'Senior Developer', description: 'Senior dev - assigned to projects, can mentor' },
    { value: 'developer',        label: 'Developer',        description: 'Assigned to projects, logs work, manages tasks' },
    { value: 'employee',         label: 'Employee',         description: 'Basic access - view assigned projects, log work' },
];

const currentRoleDescription = computed(() => {
    const match = roleOptions.find(r => r.value === form.value.role);
    return match || null;
});

function togglePassword() {
    showPassword.value = !showPassword.value;
}

async function submit() {
    errors.value = {};
    submitting.value = true;

    try {
        router.post('/team-members', form.value, {
            onError: (errs) => {
                errors.value = errs;
            },
            onFinish: () => {
                submitting.value = false;
            },
        });
    } catch (e) {
        submitting.value = false;
    }
}
</script>

<template>
    <Head title="Add Team Member" />

    <div class="space-y-4">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <Link href="/team-members" class="hover:text-[#4e1a77]">Team Members</Link>
            <span>/</span>
            <span class="text-gray-900 font-medium">Add Member</span>
        </div>

        <h1 class="text-xl font-bold text-gray-900">Add Team Member</h1>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-5">

                <!-- Name & Email row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] outline-none"
                            placeholder="e.g. John Doe"
                            required
                        />
                        <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] outline-none"
                            placeholder="e.g. john@example.com"
                            required
                        />
                        <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email }}</p>
                    </div>
                </div>

                <!-- Password & Joined Date row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- Password with toggle -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 pr-10 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] outline-none"
                                placeholder="Min 6 characters"
                                required
                                minlength="6"
                            />
                            <button
                                type="button"
                                @click="togglePassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-[#4e1a77] transition-colors"
                                tabindex="-1"
                            >
                                <!-- Eye open icon (password hidden - click to show) -->
                                <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <!-- Eye slash icon (password visible - click to hide) -->
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="errors.password" class="mt-1 text-xs text-red-600">{{ errors.password }}</p>
                    </div>

                    <!-- Joined Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Joined Date</label>
                        <input
                            v-model="form.joined_date"
                            type="date"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] outline-none"
                        />
                        <p v-if="errors.joined_date" class="mt-1 text-xs text-red-600">{{ errors.joined_date }}</p>
                    </div>
                </div>

                <!-- Role -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role <span class="text-red-500">*</span></label>
                    <select
                        v-model="form.role"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-[#4e1a77] focus:ring-1 focus:ring-[#4e1a77] outline-none"
                    >
                        <option v-for="role in roleOptions" :key="role.value" :value="role.value">
                            {{ role.label }}
                        </option>
                    </select>
                    <p v-if="errors.role" class="mt-1 text-xs text-red-600">{{ errors.role }}</p>

                    <!-- Role Description -->
                    <div v-if="currentRoleDescription" class="mt-2 rounded-lg bg-gray-50 p-3 text-xs text-gray-500">
                        <p>
                            <strong class="text-[#4e1a77]">{{ currentRoleDescription.label }}</strong>
                            &mdash; {{ currentRoleDescription.description }}
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="rounded-lg bg-[#4e1a77] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#3d1560] transition-colors disabled:opacity-50"
                    >
                        {{ submitting ? 'Creating...' : 'Create Member' }}
                    </button>
                    <Link href="/team-members" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </div>
</template>

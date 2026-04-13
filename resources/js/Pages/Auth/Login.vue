<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div class="flex min-h-screen bg-white">
        <!-- Left Panel - Form -->
        <div class="flex w-full flex-col justify-center px-8 sm:px-12 lg:w-[45%] lg:px-16 xl:px-24">
            <div class="w-full max-w-md mx-auto">
                <!-- Logo -->
                <img src="/ewards-logo.svg" alt="eWards" class="h-9 mb-8" />

                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 leading-tight">
                    Streamline and Empower<br />Your Project Operations
                </h1>
                <p class="mt-2 text-sm text-gray-500">Sign in to manage everything from one place.</p>

                <form @submit.prevent="submit" class="mt-8 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Username <span class="text-orange-500">*</span>
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            autofocus
                            placeholder="Enter your email"
                            class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-gray-50/50 px-4 py-3 text-sm focus:border-[#4e1a77] focus:bg-white focus:ring-1 focus:ring-[#4e1a77] transition-colors"
                            :class="{ 'border-red-400 bg-red-50': form.errors.email }"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Password <span class="text-orange-500">*</span>
                        </label>
                        <div class="relative mt-1.5">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Enter your password"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50/50 px-4 py-3 pr-10 text-sm focus:border-[#4e1a77] focus:bg-white focus:ring-1 focus:ring-[#4e1a77] transition-colors"
                                :class="{ 'border-red-400 bg-red-50': form.errors.password }"
                            />
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg v-if="showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2">
                            <input v-model="form.remember" type="checkbox" class="rounded border-gray-300 text-[#4e1a77] focus:ring-[#4e1a77]" />
                            <span class="text-sm text-gray-600">Remember me</span>
                        </label>
                        <a href="#" class="text-sm font-semibold text-[#4e1a77] underline hover:text-[#3d1560]">Forgot Password?</a>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex w-full items-center justify-center rounded-lg bg-[#4e1a77] px-4 py-3 text-sm font-bold uppercase tracking-wider text-white shadow-sm hover:bg-[#3d1560] disabled:opacity-50 transition-colors"
                    >
                        <svg v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                        LOG IN
                    </button>
                </form>

                <p class="mt-6 text-center text-xs text-gray-400">
                    Demo: manager@example.com / password
                </p>
            </div>
        </div>

        <!-- Right Panel - Branding -->
        <div class="hidden lg:flex lg:w-[55%] items-center justify-center relative overflow-hidden bg-gradient-to-br from-[#e8d5f5] via-[#d4b8e8] to-[#c49ddb]">
            <!-- Decorative blobs -->
            <div class="absolute -top-20 -right-20 h-80 w-80 rounded-full bg-[#4e1a77]/10 blur-3xl"></div>
            <div class="absolute -bottom-20 -left-20 h-60 w-60 rounded-full bg-[#f5941e]/10 blur-3xl"></div>

            <div class="relative z-10 max-w-lg px-12 text-center">
                <!-- Testimonial card -->
                <div class="mb-8 rounded-2xl bg-white/80 backdrop-blur-sm p-6 shadow-lg text-left">
                    <p class="text-gray-700 text-sm leading-relaxed italic">
                        "Managing projects has never been easier. Real-time tracking, smart analytics, and seamless team collaboration - all in one place."
                    </p>
                    <div class="mt-4 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#4e1a77] text-white text-sm font-bold">PM</div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Project Manager</p>
                            <p class="text-xs text-gray-500">eWards PMS</p>
                        </div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="rounded-2xl bg-white/60 backdrop-blur-sm p-6 shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Project Tracking</p>
                        <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-[10px] font-bold text-green-700">LIVE</span>
                    </div>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p class="text-2xl font-bold text-[#4e1a77]">8</p>
                            <p class="text-[10px] text-gray-500 mt-1">Stages</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-[#f5941e]">24/7</p>
                            <p class="text-[10px] text-gray-500 mt-1">Tracking</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-green-600">100%</p>
                            <p class="text-[10px] text-gray-500 mt-1">Visibility</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

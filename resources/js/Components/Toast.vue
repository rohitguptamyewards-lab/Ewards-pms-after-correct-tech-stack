<script setup>
import { useToast } from '@/composables/useToast';
const { toasts, remove } = useToast();

const icons = {
    success: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    error:   'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    info:    'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zM12 8.25h.008v.008H12V8.25z',
};
const colors = {
    success: 'bg-green-50 border-green-200 text-green-800',
    error:   'bg-red-50 border-red-200 text-red-800',
    info:    'bg-blue-50 border-blue-200 text-blue-800',
};
const iconColors = {
    success: 'text-green-500',
    error:   'text-red-500',
    info:    'text-blue-500',
};
</script>

<template>
    <div class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none" style="max-width: 380px;">
        <TransitionGroup name="toast">
            <div
                v-for="t in toasts"
                :key="t.id"
                class="pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg text-sm"
                :class="colors[t.type]"
            >
                <svg class="h-5 w-5 shrink-0 mt-0.5" :class="iconColors[t.type]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="icons[t.type]" />
                </svg>
                <span class="flex-1 leading-snug">{{ t.message }}</span>
                <button @click="remove(t.id)" class="shrink-0 opacity-50 hover:opacity-100 ml-1">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from { opacity: 0; transform: translateY(8px); }
.toast-leave-to   { opacity: 0; transform: translateX(16px); }
</style>

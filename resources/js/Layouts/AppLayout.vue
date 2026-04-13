<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import Toast from '@/Components/Toast.vue';

const page = usePage();
const url = computed(() => page.url);
const user = computed(() => page.props.auth?.user);
const role = computed(() => user.value?.role);
const flash = computed(() => page.props.flash);

const sidebarOpen = ref(false);

// ── Dark Mode ──────────────────────────────
const darkMode = ref(false);

function initDarkMode() {
    const stored = localStorage.getItem('pms-dark-mode');
    darkMode.value = stored === 'true';
    applyDarkMode();
}

function toggleDarkMode() {
    darkMode.value = !darkMode.value;
    localStorage.setItem('pms-dark-mode', darkMode.value);
    applyDarkMode();
}

function applyDarkMode() {
    if (darkMode.value) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
}

// ── Notifications ──────────────────────────
const notifications = ref([]);
const unreadCount = ref(0);
const notifOpen = ref(false);
let notifInterval = null;

async function fetchNotifications() {
    try {
        const { data } = await axios.get('/api/v1/notifications');
        notifications.value = data.notifications || [];
        unreadCount.value = data.unread_count || 0;
    } catch (e) { /* silent */ }
}

async function markRead(id) {
    await axios.put(`/api/v1/notifications/${id}/read`);
    const n = notifications.value.find(n => n.id === id);
    if (n) n.read_at = new Date().toISOString();
    unreadCount.value = Math.max(0, unreadCount.value - 1);
}

async function markAllRead() {
    await axios.put('/api/v1/notifications/read-all');
    notifications.value.forEach(n => { if (!n.read_at) n.read_at = new Date().toISOString(); });
    unreadCount.value = 0;
}

function notifLink(n) {
    try { return JSON.parse(n.data)?.link || '/'; } catch { return '/'; }
}

function notifClick(n) {
    if (!n.read_at) markRead(n.id);
    notifOpen.value = false;
    const link = notifLink(n);
    router.visit(link);
}

function timeAgo(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - d) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

const notifIcon = {
    stage_change: 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
    blocker: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z',
    assignment: 'M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z',
    mention: 'M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zm0 0c0 1.657 1.007 3 2.25 3S21 13.657 21 12a9 9 0 10-2.636 6.364M16.5 12V8.25',
};

const notifColor = {
    stage_change: 'bg-blue-100 text-blue-600',
    blocker: 'bg-red-100 text-red-600',
    assignment: 'bg-green-100 text-green-600',
    mention: 'bg-purple-100 text-purple-600',
};

onMounted(() => {
    initDarkMode();
    fetchNotifications();
    notifInterval = setInterval(fetchNotifications, 30000);
});

onUnmounted(() => {
    if (notifInterval) clearInterval(notifInterval);
});

// ── Navigation ─────────────────────────────
function isActive(href) {
    if (!href) return false;
    if (href === '/') return url.value === '/';
    if (href === '/projects') {
        return url.value === '/projects'
            || (url.value.startsWith('/projects/') && !url.value.startsWith('/projects/board') && !url.value.startsWith('/projects/custom-worklog'));
    }
    if (href === '/projects/board') return url.value.startsWith('/projects/board');
    if (href === '/projects/custom-worklog') return url.value.startsWith('/projects/custom-worklog');
    return url.value.startsWith(href);
}

function logout() {
    router.post('/logout');
}

const navItems = computed(() => {
    const items = [
        { label: 'Dashboard', href: '/', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1' },
        { label: 'Projects', href: '/projects', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
        { label: 'Board', href: '/projects/board', icon: 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2' },
        { label: 'Custom Work', href: '/projects/custom-worklog', icon: 'M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 011.15.586m-5.8 0c-.376.023-.75.05-1.124.08C8.003 3.011 7.5 4.138 7.5 5.25v8.284c0 1.007.07 1.993.202 2.958a1.08 1.08 0 001.073.91h6.45a1.08 1.08 0 001.073-.91c.132-.965.202-1.95.202-2.958V5.25c0-1.112-.503-2.24-1.726-2.334-.375-.03-.748-.057-1.124-.08' },
        { label: 'Work Logs', href: '/work-logs', icon: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z' },
        { label: 'Release Notes', href: '/release-notes', icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 002.25 2.25h.75' },
    ];

    if (['manager', 'analyst_head', 'analyst'].includes(role.value)) {
        items.push(
            { label: 'TEAM', header: true },
            { label: 'Team Members', href: '/team-members', icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z' },
        );
    }

    if (['manager', 'analyst_head', 'analyst', 'senior_developer'].includes(role.value)) {
        items.push(
            { label: 'REPORTS', header: true },
            { label: 'Report Dashboard', href: '/reports/dashboard', icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
        );

        if (['manager', 'analyst_head', 'analyst'].includes(role.value)) {
            items.push(
                { label: 'Projects Report', href: '/reports/projects', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
            );
        }
    }

    if (['manager', 'analyst_head', 'senior_developer'].includes(role.value)) {
        if (!items.find(i => i.label === 'REPORTS')) {
            items.push({ label: 'REPORTS', header: true });
        }
        items.push(
            { label: 'Workers Report', href: '/reports/workers', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
        );
    }

    if (['manager', 'analyst_head'].includes(role.value)) {
        items.push(
            { label: 'SETTINGS', header: true },
            { label: 'Automations', href: '/automations', icon: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
        );
    }

    return items;
});
</script>

<template>
    <Head :title="$page.props.title || ''" />

    <div class="flex h-screen overflow-hidden" :class="darkMode ? 'bg-gray-900' : 'bg-gray-50'">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-30 flex w-56 flex-col transition-transform duration-200 lg:relative lg:translate-x-0"
            :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full', darkMode ? 'bg-[#1a0a2e] text-white' : 'bg-[#2c0f47] text-white']"
        >
            <!-- Logo -->
            <div class="flex h-14 items-center gap-2 border-b border-white/10 px-4">
                <img src="/ewards-logo-white.svg" alt="eWards" class="h-6" />
            </div>
            <p class="px-4 pt-1.5 pb-2 text-[9px] font-semibold uppercase tracking-widest text-white/40">PMS Dashboard</p>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto px-2 py-1 space-y-0.5">
                <template v-for="item in navItems" :key="item.label">
                    <div v-if="item.header" class="px-3 pt-5 pb-1 text-[10px] font-bold uppercase tracking-widest text-white/40">
                        {{ item.label }}
                    </div>
                    <Link
                        v-else
                        :href="item.href"
                        @click="sidebarOpen = false"
                        class="relative flex items-center gap-2.5 rounded-r-lg px-3 py-2 text-sm transition-colors"
                        :class="isActive(item.href)
                            ? 'bg-white/10 text-white font-semibold border-l-[3px] border-[#f5941e]'
                            : 'text-white/70 hover:bg-white/5 hover:text-white border-l-[3px] border-transparent'"
                    >
                        <svg class="h-4.5 w-4.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                        {{ item.label }}
                    </Link>
                </template>
            </nav>

            <!-- User -->
            <div class="border-t border-white/10 px-3 py-3">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#f5941e] text-xs font-bold text-white">
                        {{ user?.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="truncate text-xs font-medium">{{ user?.name }}</p>
                        <p class="truncate text-[10px] text-white/50 capitalize">{{ role }}</p>
                    </div>
                    <button @click="logout" class="rounded p-1 text-white/40 hover:bg-white/10 hover:text-white" title="Logout">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Mobile overlay -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-20 bg-black/50 lg:hidden" @click="sidebarOpen = false" />

        <!-- Main content -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- Top bar -->
            <header class="flex h-14 items-center gap-3 border-b px-4 shadow-sm" :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'">
                <button @click="sidebarOpen = !sidebarOpen" class="rounded-lg p-1.5 lg:hidden" :class="darkMode ? 'text-gray-400 hover:bg-gray-700' : 'text-gray-500 hover:bg-gray-100'">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <div class="flex-1" />

                <!-- Top bar actions -->
                <div class="flex items-center gap-2">
                    <!-- Dark Mode Toggle -->
                    <button
                        @click="toggleDarkMode"
                        class="rounded-lg p-2 transition-colors"
                        :class="darkMode ? 'text-yellow-400 hover:bg-gray-700' : 'text-gray-400 hover:bg-gray-100'"
                        :title="darkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                    >
                        <!-- Sun icon (dark mode active) -->
                        <svg v-if="darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                        </svg>
                        <!-- Moon icon (light mode active) -->
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                    </button>

                    <!-- Notification Bell -->
                    <div class="relative">
                        <button
                            @click="notifOpen = !notifOpen"
                            class="relative rounded-lg p-2 transition-colors"
                            :class="darkMode ? 'text-gray-400 hover:bg-gray-700' : 'text-gray-400 hover:bg-gray-100'"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            <!-- Unread badge -->
                            <span
                                v-if="unreadCount > 0"
                                class="absolute -top-0.5 -right-0.5 flex h-4.5 w-4.5 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white ring-2"
                                :class="darkMode ? 'ring-gray-800' : 'ring-white'"
                            >
                                {{ unreadCount > 9 ? '9+' : unreadCount }}
                            </span>
                        </button>

                        <!-- Notification Dropdown -->
                        <div
                            v-if="notifOpen"
                            class="absolute right-0 top-12 z-50 w-80 rounded-xl border shadow-xl overflow-hidden"
                            :class="darkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'"
                        >
                            <div class="flex items-center justify-between px-4 py-3 border-b" :class="darkMode ? 'border-gray-700' : 'border-gray-100'">
                                <h3 class="text-sm font-semibold" :class="darkMode ? 'text-white' : 'text-gray-900'">Notifications</h3>
                                <button
                                    v-if="unreadCount > 0"
                                    @click="markAllRead"
                                    class="text-[11px] font-medium text-[#4e1a77] hover:underline"
                                >
                                    Mark all read
                                </button>
                            </div>
                            <div class="max-h-80 overflow-y-auto divide-y" :class="darkMode ? 'divide-gray-700' : 'divide-gray-100'">
                                <div
                                    v-for="n in notifications.slice(0, 15)"
                                    :key="n.id"
                                    @click="notifClick(n)"
                                    class="flex items-start gap-3 px-4 py-3 cursor-pointer transition-colors"
                                    :class="[
                                        !n.read_at ? (darkMode ? 'bg-[#4e1a77]/10' : 'bg-[#f5f0ff]/50') : '',
                                        darkMode ? 'hover:bg-gray-700' : 'hover:bg-gray-50'
                                    ]"
                                >
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg shrink-0" :class="notifColor[n.type] || 'bg-gray-100 text-gray-500'">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="notifIcon[n.type] || notifIcon.assignment" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold truncate" :class="darkMode ? 'text-white' : 'text-gray-900'">{{ n.title }}</p>
                                        <p class="text-[11px] mt-0.5 line-clamp-2" :class="darkMode ? 'text-gray-400' : 'text-gray-500'">{{ n.message }}</p>
                                        <p class="text-[10px] mt-1" :class="darkMode ? 'text-gray-500' : 'text-gray-400'">{{ timeAgo(n.created_at) }}</p>
                                    </div>
                                    <div v-if="!n.read_at" class="mt-2 h-2 w-2 rounded-full bg-[#4e1a77] shrink-0"></div>
                                </div>
                            </div>
                            <div v-if="!notifications.length" class="px-4 py-8 text-center">
                                <svg class="mx-auto h-8 w-8" :class="darkMode ? 'text-gray-600' : 'text-gray-300'" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                </svg>
                                <p class="mt-2 text-xs" :class="darkMode ? 'text-gray-500' : 'text-gray-400'">No notifications yet</p>
                            </div>
                        </div>
                    </div>

                    <!-- User info -->
                    <div class="flex items-center gap-2 ml-1">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#4e1a77] text-xs font-bold text-white">
                            {{ user?.name?.charAt(0)?.toUpperCase() || '?' }}
                        </div>
                        <span class="hidden sm:inline text-sm font-medium" :class="darkMode ? 'text-gray-200' : 'text-gray-700'">{{ user?.name }}</span>
                    </div>
                </div>
            </header>

            <!-- Flash Messages -->
            <div v-if="flash?.success" class="mx-4 mt-3 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-700">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="mx-4 mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700">
                {{ flash.error }}
            </div>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-6" @click="notifOpen = false">
                <slot />
            </main>
        </div>
    </div>
    <Toast />
</template>

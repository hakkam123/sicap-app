<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => user.value?.role === 'admin');

// Sidebar responsive drawer state
const isMobileSidebarOpen = ref(false);

// Collapsible menu groups state
const isMasterDataOpen = ref(
    route().current('areas.*') ||
    route().current('machines.*') ||
    route().current('part-numbers.*')
);

const toggleMasterData = () => {
    isMasterDataOpen.value = !isMasterDataOpen.value;
};

// Flash messages dismissal
const dismissedFlash = ref({ success: false, error: false });
const dismissSuccess = () => { dismissedFlash.value.success = true; };
const dismissError = () => { dismissedFlash.value.error = true; };

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex">
        <!-- Mobile Sidebar Backdrop -->
        <div
            v-if="isMobileSidebarOpen"
            @click="isMobileSidebarOpen = false"
            class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden transition-opacity"
        ></div>

        <!-- Sidebar Navigation -->
        <aside
            :class="[
                'fixed top-0 bottom-0 left-0 z-50 w-64 bg-slate-900 text-slate-100 flex flex-col transition-transform duration-300 ease-in-out shadow-xl lg:translate-x-0',
                isMobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <!-- Brand Logo Header -->
            <div class="h-16 px-6 bg-slate-950 flex items-center justify-between border-b border-slate-800">
                <Link :href="route('dashboard')" class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center text-white font-black text-xl shadow-md">
                        S
                    </div>
                    <div>
                        <span class="text-base font-bold tracking-tight text-white block leading-none">SICAP</span>
                        <span class="text-[10px] text-slate-400 tracking-wider uppercase font-semibold">Sparepart Consume</span>
                    </div>
                </Link>
                <button
                    @click="isMobileSidebarOpen = false"
                    class="lg:hidden text-slate-400 hover:text-white p-1 rounded-md"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <div class="flex-1 overflow-y-auto px-4 py-6 space-y-1.5 custom-scrollbar">
                
                <!-- Dashboard -->
                <Link
                    :href="route('dashboard')"
                    :class="[
                        'flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs font-semibold transition-all',
                        route().current('dashboard')
                            ? 'bg-blue-600 text-white shadow-md'
                            : 'text-slate-300 hover:text-white hover:bg-slate-800/80'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </Link>

                <!-- Section Divider: Operations -->
                <div class="pt-4 pb-1 px-3">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Operasional</p>
                </div>

                <!-- Data Consume -->
                <Link
                    :href="route('consume.index')"
                    :class="[
                        'flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs font-semibold transition-all',
                        route().current('consume.*')
                            ? 'bg-blue-600 text-white shadow-md'
                            : 'text-slate-300 hover:text-white hover:bg-slate-800/80'
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Consume</span>
                </Link>

                <!-- Section Divider: Master & Setup (Admin Only) -->
                <template v-if="isAdmin">
                    <div class="pt-5 pb-1 px-3">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Master & Konfigurasi</p>
                    </div>

                    <!-- Master Data (Collapsible) -->
                    <div>
                        <button
                            type="button"
                            @click="toggleMasterData"
                            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-800/80 transition-all"
                        >
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <span>Master Data</span>
                            </div>
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                :class="['h-3.5 w-3.5 text-slate-400 transition-transform duration-200', isMasterDataOpen ? 'rotate-180' : '']"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div v-show="isMasterDataOpen" class="mt-1 pl-7 space-y-1">
                            <Link
                                :href="route('areas.index')"
                                :class="[
                                    'flex items-center gap-2 px-3 py-2 rounded-md text-xs transition-all',
                                    route().current('areas.*')
                                        ? 'bg-blue-600 text-white font-semibold shadow-sm'
                                        : 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                                ]"
                            >
                                <span>Area</span>
                            </Link>
                            <Link
                                :href="route('machines.index')"
                                :class="[
                                    'flex items-center gap-2 px-3 py-2 rounded-md text-xs transition-all',
                                    route().current('machines.*')
                                        ? 'bg-blue-600 text-white font-semibold shadow-sm'
                                        : 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                                ]"
                            >
                                <span>Machine</span>
                            </Link>
                            <Link
                                :href="route('part-numbers.index')"
                                :class="[
                                    'flex items-center gap-2 px-3 py-2 rounded-md text-xs transition-all',
                                    route().current('part-numbers.*')
                                        ? 'bg-blue-600 text-white font-semibold shadow-sm'
                                        : 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                                ]"
                            >
                                <span>Part Number</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Mapping (Collapsible) -->
                    <!-- Mapping Part -->
                    <Link
                        :href="route('mapping.index')"
                        :class="[
                            'flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs font-semibold transition-all',
                            route().current('mapping.*')
                                ? 'bg-blue-600 text-white shadow-md'
                                : 'text-slate-300 hover:text-white hover:bg-slate-800/80'
                        ]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span>Mapping Part</span>
                    </Link>

                    <!-- User Management -->
                    <Link
                        :href="route('users.index')"
                        :class="[
                            'flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs font-semibold transition-all',
                            route().current('users.*')
                                ? 'bg-blue-600 text-white shadow-md'
                                : 'text-slate-300 hover:text-white hover:bg-slate-800/80'
                        ]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span>User Management</span>
                    </Link>
                </template>

            </div>

            <!-- Footer / User Snapshot in Sidebar -->
            <div class="p-4 bg-slate-950 border-t border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-blue-700 text-white font-bold flex items-center justify-center text-xs shrink-0">
                        {{ user?.name ? user.name.charAt(0).toUpperCase() : 'U' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-white truncate">{{ user?.name }}</p>
                        <span
                            class="inline-block text-[10px] px-1.5 py-0.2 rounded font-medium"
                            :class="isAdmin ? 'bg-purple-900/60 text-purple-300' : 'bg-blue-900/60 text-blue-300'"
                        >
                            {{ isAdmin ? 'Administrator' : 'User' }}
                        </span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-64">
            <!-- Top Header Bar -->
            <header class="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <!-- Mobile Hamburger Button -->
                    <button
                        type="button"
                        @click="isMobileSidebarOpen = true"
                        class="lg:hidden p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Optional Breadcrumb / Page Header Slot -->
                    <div>
                        <slot name="header" />
                    </div>
                </div>

                <!-- Right Header Actions (User Dropdown) -->
                <div class="flex items-center gap-4">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="flex items-center gap-2.5 py-1.5 px-3 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors focus:outline-none"
                            >
                                <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-xs">
                                    {{ user?.name ? user.name.charAt(0).toUpperCase() : 'U' }}
                                </div>
                                <span class="hidden sm:inline-block max-w-[120px] truncate">{{ user?.name }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </template>

                        <template #content>
                            <div class="px-4 py-2 text-xs border-b border-slate-100">
                                <p class="font-semibold text-slate-900">{{ user?.name }}</p>
                                <p class="text-slate-500 truncate">{{ user?.email }}</p>
                            </div>

                            <DropdownLink :href="route('profile.edit')">
                                Profil
                            </DropdownLink>

                            <DropdownLink :href="route('logout')" method="post" as="button">
                                Keluar
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <!-- Global Flash Messages Notification Banner -->
            <div class="px-4 sm:px-6 lg:px-8 pt-4">
                <!-- Flash Success -->
                <div
                    v-if="$page.props.flash?.success && !dismissedFlash.success"
                    class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg flex items-center justify-between shadow-sm"
                >
                    <div class="flex items-center gap-2.5 text-xs font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ $page.props.flash.success }}</span>
                    </div>
                    <button @click="dismissSuccess" class="text-emerald-500 hover:text-emerald-700 p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Flash Error -->
                <div
                    v-if="$page.props.flash?.error && !dismissedFlash.error"
                    class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg flex items-center justify-between shadow-sm mt-2"
                >
                    <div class="flex items-center gap-2.5 text-xs font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ $page.props.flash.error }}</span>
                    </div>
                    <button @click="dismissError" class="text-red-500 hover:text-red-700 p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Page Content Body -->
            <main class="flex-1">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #0f172a;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #334155;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #475569;
}
</style>


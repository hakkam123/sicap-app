<script setup>
import { computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ToastContainer from '@/Components/UI/ToastContainer.vue';
import { useToast } from '@/composables/useToast';
import { LogIn, LayoutDashboard, Layers } from 'lucide-vue-next';

const page = usePage();
const toast = useToast();
const user = computed(() => page.props.auth?.user);

// Sync Inertia flash messages to toast container
watch(
    () => page.props.flash?.success,
    (val) => {
        if (val) toast.success(val, { title: 'Berhasil' });
    },
    { immediate: true }
);

watch(
    () => page.props.flash?.error,
    (val) => {
        if (val) toast.error(val, { title: 'Perhatian' });
    },
    { immediate: true }
);
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col font-sans antialiased text-slate-800">
        <!-- Top Public Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-18 sm:h-20 flex items-center justify-between gap-4">
                
                <!-- Brand & Logo -->
                <div class="flex items-center gap-3.5">
                    <Link :href="route('katalog.index')" class="flex items-center gap-3 hover:opacity-95 transition-opacity">
                        <div class="hidden sm:block border-l border-slate-200 pl-3.5">
                            <h1 class="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-1.5">
                                <Layers class="w-4 h-4 text-slate-900" />
                                Katalog Sparepart & Addressing
                            </h1>
                        </div>
                    </Link>
                </div>

                <!-- Right Action (Masuk Sistem) -->
                <div class="flex items-center gap-3">
                    <template v-if="user">
                        <Link
                            :href="route('dashboard')"
                            class="inline-flex items-center gap-2 px-3.5 py-2 bg-slate-900 text-white text-xs font-semibold rounded-lg hover:bg-slate-800 transition shadow-xs"
                        >
                            <LayoutDashboard class="w-3.5 h-3.5" />
                            <span>Buka Dashboard</span>
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="inline-flex items-center gap-2 px-3.5 py-2 bg-slate-900 text-white text-xs font-semibold rounded-lg hover:bg-slate-800 transition shadow-xs"
                        >
                            <span>Masuk Sistem</span>
                        </Link>
                    </template>
                </div>

            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
            <div class="max-w-7xl mx-auto px-4">
                &copy; {{ new Date().getFullYear() }} <strong>PT Astra Visteon Indonesia</strong> &bull; Consumption Part Application
            </div>
        </footer>

        <!-- Toast Notifications -->
        <ToastContainer />
    </div>
</template>


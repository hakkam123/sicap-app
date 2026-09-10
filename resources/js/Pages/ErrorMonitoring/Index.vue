<script setup>
import { ref, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ConfirmModal from '@/Components/UI/ConfirmModal.vue';
import { 
    X as XMarkIcon, 
    AlertTriangle, 
    AlertCircle,
    AlertOctagon,
    Search, 
    RotateCcw,
    Clock,
    Activity,
    CheckCircle2,
    Trash2,
    Check,
    Calendar,
    Globe,
    Terminal,
    Copy,
    CheckCheck,
    ShieldAlert,
    ExternalLink,
    Filter
} from 'lucide-vue-next';

const props = defineProps({
    logs: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({
            total_errors: 0,
            errors_today: 0,
            critical_errors: 0,
            not_found_errors: 0,
            unresolved_errors: 0,
        }),
    },
    features: {
        type: Array,
        default: () => [],
    },
    severities: {
        type: Array,
        default: () => [],
    },
    statusCodes: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ 
            feature: '',
            severity: '',
            status_code: '',
            status: '',
            search: '',
            date_from: '',
            date_to: '',
            per_page: 10,
        }),
    },
});

// Filter States & Form
const isSearching = ref(false);
const filterForm = ref({
    feature: props.filters.feature || '',
    severity: props.filters.severity || '',
    status_code: props.filters.status_code || '',
    status: props.filters.status || '',
    search: props.filters.search || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    per_page: props.filters.per_page || 10,
});

// Methods untuk Filter
const applyFilters = () => {
    isSearching.value = true;
    router.get(
        route('error-monitoring.index'),
        { ...filterForm.value },
        { 
            preserveState: true, 
            preserveScroll: true, 
            replace: true,
            onFinish: () => (isSearching.value = false)
        }
    );
};

const resetFilters = () => {
    filterForm.value = {
        feature: '',
        severity: '',
        status_code: '',
        status: '',
        search: '',
        date_from: '',
        date_to: '',
        per_page: 10,
    };
    applyFilters();
};

// Formatter Helpers
const formatDate = (isoString) => {
    if (!isoString) return '-';
    const d = new Date(isoString);
    return d.toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatTimeAgo = (isoString) => {
    if (!isoString) return '';
    const diff = (Date.now() - new Date(isoString).getTime()) / 1000;
    if (diff < 60) return 'Baru saja';
    if (diff < 3600) return `${Math.floor(diff / 60)} mnt lalu`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`;
    return `${Math.floor(diff / 86400)} hari lalu`;
};

const getFeatureBadgeClass = (feature) => {
    switch (feature) {
        case 'consume':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'part_number':
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'area':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'machine':
            return 'bg-cyan-100 text-cyan-800 border-cyan-200';
        case 'mapping':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'user':
            return 'bg-indigo-100 text-indigo-800 border-indigo-200';
        case 'sync_api':
            return 'bg-rose-100 text-rose-800 border-rose-200';
        case 'auth':
            return 'bg-orange-100 text-orange-800 border-orange-200';
        default:
            return 'bg-slate-100 text-slate-800 border-slate-200';
    }
};

const getSeverityBadge = (severity, statusCode) => {
    if (statusCode >= 500 || severity === 'critical') {
        return {
            label: statusCode ? `${statusCode} Server Error` : 'Critical',
            class: 'bg-red-100 text-red-800 border-red-200',
        };
    }
    if (statusCode === 404) {
        return {
            label: '404 Not Found',
            class: 'bg-amber-100 text-amber-800 border-amber-200',
        };
    }
    if (statusCode === 403) {
        return {
            label: '403 Forbidden',
            class: 'bg-purple-100 text-purple-800 border-purple-200',
        };
    }
    if (statusCode === 401) {
        return {
            label: '401 Unauthorized',
            class: 'bg-orange-100 text-orange-800 border-orange-200',
        };
    }
    if (statusCode === 422) {
        return {
            label: '422 Validation',
            class: 'bg-blue-100 text-blue-800 border-blue-200',
        };
    }
    return {
        label: statusCode ? `HTTP ${statusCode}` : (severity || 'Error').toUpperCase(),
        class: 'bg-slate-100 text-slate-800 border-slate-200',
    };
};

const getMethodBadgeClass = (method) => {
    switch (method?.toUpperCase()) {
        case 'GET':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'POST':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'PUT':
        case 'PATCH':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'DELETE':
            return 'bg-red-50 text-red-700 border-red-200';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-200';
    }
};

// Detail Modal State & Logic
const isDetailOpen = ref(false);
const activeDetailTab = ref('overview'); // 'overview' | 'trace' | 'payload'
const selectedLog = ref(null);
const isLoadingDetail = ref(false);
const isCopied = ref(false);

const openDetailModal = async (log) => {
    activeDetailTab.value = 'overview';
    isCopied.value = false;
    isLoadingDetail.value = true;
    selectedLog.value = log;
    isDetailOpen.value = true;

    try {
        const res = await fetch(route('error-monitoring.show', log.id), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (res.ok) {
            selectedLog.value = await res.json();
        }
    } catch (e) {
        // fallback to table row data
    } finally {
        isLoadingDetail.value = false;
    }
};

const closeDetailModal = () => {
    isDetailOpen.value = false;
    selectedLog.value = null;
};

const copyStackTrace = () => {
    if (!selectedLog.value?.stack_trace) return;
    navigator.clipboard.writeText(selectedLog.value.stack_trace);
    isCopied.value = true;
    setTimeout(() => {
        isCopied.value = false;
    }, 2000);
};

// Resolve & Delete Actions
const toggleResolve = (log) => {
    router.post(route('error-monitoring.resolve', log.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedLog.value && selectedLog.value.id === log.id) {
                selectedLog.value.status = selectedLog.value.status === 'resolved' ? 'unresolved' : 'resolved';
            }
        },
    });
};

// Confirmation Modals
const showResolveAllModal = ref(false);
const showClearAllModal = ref(false);
const logToDelete = ref(null);
const isActionLoading = ref(false);

const confirmDeleteLog = (log) => {
    logToDelete.value = log;
};

const handleDeleteLog = () => {
    if (!logToDelete.value) return;
    isActionLoading.value = true;
    router.delete(route('error-monitoring.destroy', logToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            isActionLoading.value = false;
            logToDelete.value = null;
            if (isDetailOpen.value) closeDetailModal();
        },
    });
};

const handleResolveAll = () => {
    isActionLoading.value = true;
    router.post(route('error-monitoring.resolve-all'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isActionLoading.value = false;
            showResolveAllModal.value = false;
        },
    });
};

const handleClearAll = () => {
    isActionLoading.value = true;
    router.delete(route('error-monitoring.clear-all'), {
        preserveScroll: true,
        onFinish: () => {
            isActionLoading.value = false;
            showClearAllModal.value = false;
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Error Monitoring & Log Sistem" />

        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800 flex items-center gap-2.5">
                        <Activity class="w-6 h-6 text-red-600" />
                        <span>Error Monitoring & Log Sistem</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Pemantauan riwayat log error aplikasi, HTTP status (500, 404, 422), unhandled exceptions, dan integrasi API.
                    </p>
                </div>

                <!-- Global Action Buttons -->
                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        v-if="stats.unresolved_errors > 0"
                        type="button"
                        @click="showResolveAllModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow-2xs transition cursor-pointer"
                    >
                        <CheckCheck class="w-4 h-4" />
                        <span>Tandai Semua Selesai</span>
                    </button>

                    <button
                        v-if="stats.total_errors > 0"
                        type="button"
                        @click="showClearAllModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg text-xs font-semibold shadow-2xs transition cursor-pointer"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        <span>Bersihkan Semua Log</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="max-w-7xl mx-auto space-y-5">

                <!-- 1. KPI SUMMARY CARDS -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Total Errors -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                                Total Error Tercatat
                            </span>
                            <p class="text-2xl font-bold text-slate-900 mt-1 tabular-nums">
                                {{ stats.total_errors }}
                            </p>
                            <span class="text-[11px] text-slate-400 mt-0.5 block">
                                Sepanjang waktu
                            </span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-700">
                            <AlertOctagon class="w-6 h-6" />
                        </div>
                    </div>

                    <!-- Errors Today -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                                Error Hari Ini
                            </span>
                            <p class="text-2xl font-bold text-blue-600 mt-1 tabular-nums">
                                {{ stats.errors_today }}
                            </p>
                            <span class="text-[11px] text-slate-400 mt-0.5 block">
                                Kejadian 24 jam terakhir
                            </span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                            <Calendar class="w-6 h-6" />
                        </div>
                    </div>

                    <!-- Critical / 500 Errors -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                                Server Error (500)
                            </span>
                            <p class="text-2xl font-bold text-red-600 mt-1 tabular-nums">
                                {{ stats.critical_errors }}
                            </p>
                            <span class="text-[11px] text-slate-400 mt-0.5 block">
                                Exception & Query failure
                            </span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center text-red-600">
                            <AlertTriangle class="w-6 h-6" />
                        </div>
                    </div>

                    <!-- Unresolved Errors -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                                Belum Ditangani
                            </span>
                            <div class="flex items-center gap-2 mt-1">
                                <p class="text-2xl font-bold text-amber-600 tabular-nums">
                                    {{ stats.unresolved_errors }}
                                </p>
                                <span v-if="stats.unresolved_errors > 0" class="relative flex h-2.5 w-2.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                                </span>
                            </div>
                            <span class="text-[11px] text-slate-400 mt-0.5 block">
                                Membutuhkan investigasi
                            </span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                            <Clock class="w-6 h-6" />
                        </div>
                    </div>
                </div>

                <!-- 2. FILTER BAR -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                        
                        <!-- Search Pencarian -->
                        <div class="lg:col-span-2">
                            <label for="filter_search" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                Pencarian Pesan / URL / IP
                            </label>
                            <div class="relative">
                                <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
                                <input
                                    id="filter_search"
                                    type="text"
                                    v-model="filterForm.search"
                                    placeholder="Cari pesan error, URL, class, IP..."
                                    @keydown.enter="applyFilters"
                                    class="w-full pl-8 pr-3 py-1.5 border border-slate-300 rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                                />
                            </div>
                        </div>

                        <!-- Dropdown Fitur -->
                        <div>
                            <label for="filter_feature" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                Fitur / Modul
                            </label>
                            <select
                                id="filter_feature"
                                v-model="filterForm.feature"
                                @change="applyFilters"
                                class="w-full py-1.5 px-2.5 border border-slate-300 bg-white rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                            >
                                <option v-for="f in features" :key="f.value" :value="f.value">
                                    {{ f.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Dropdown Severity / Status Code -->
                        <div>
                            <label for="filter_status_code" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                Status Code
                            </label>
                            <select
                                id="filter_status_code"
                                v-model="filterForm.status_code"
                                @change="applyFilters"
                                class="w-full py-1.5 px-2.5 border border-slate-300 bg-white rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                            >
                                <option v-for="sc in statusCodes" :key="sc.value" :value="sc.value">
                                    {{ sc.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Dropdown Status Penanganan -->
                        <div>
                            <label for="filter_status" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                Penanganan
                            </label>
                            <select
                                id="filter_status"
                                v-model="filterForm.status"
                                @change="applyFilters"
                                class="w-full py-1.5 px-2.5 border border-slate-300 bg-white rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                            >
                                <option value="">Semua Status</option>
                                <option value="unresolved">Belum Ditangani</option>
                                <option value="resolved">Selesai Ditangani</option>
                            </select>
                        </div>

                        <!-- Tombol Cari & Reset -->
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="applyFilters"
                                :disabled="isSearching"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 py-1.5 px-3 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold shadow-sm transition disabled:opacity-60 cursor-pointer"
                                title="Terapkan filter pencarian"
                            >
                                <Search class="w-3.5 h-3.5" />
                                <span>Cari</span>
                            </button>

                            <button
                                type="button"
                                @click="resetFilters"
                                class="inline-flex items-center justify-center py-1.5 px-2.5 border border-slate-300 bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 rounded-lg text-xs transition cursor-pointer"
                                title="Reset filter"
                            >
                                <RotateCcw class="w-3.5 h-3.5" />
                            </button>
                        </div>

                    </div>
                </div>

                <!-- 3. DATA TABLE CARD -->
                <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-slate-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50/80">
                                <tr>
                                    <th scope="col" class="px-4 py-3.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider w-12">
                                        No
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-44">
                                        Waktu Kejadian
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider w-36">
                                        Status Code
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider w-36">
                                        Fitur
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Pesan Error & URL
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-36">
                                        User / IP
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider w-32">
                                        Status
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider w-36">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-100">
                                <tr v-if="logs.data.length === 0">
                                    <td colspan="8" class="px-4 py-12 text-center text-slate-400 text-sm">
                                        <div class="flex flex-col items-center justify-center">
                                            <CheckCircle2 class="w-8 h-8 text-emerald-500 mb-2" />
                                            <span class="font-semibold text-slate-700">Tidak ada log error yang ditemukan.</span>
                                            <span class="text-xs text-slate-400 mt-0.5">Sistem berjalan dengan normal tanpa kendala yang belum terselesaikan.</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-for="(log, idx) in logs.data"
                                    :key="log.id"
                                    class="hover:bg-slate-50/70 transition-colors"
                                >
                                    <!-- No -->
                                    <td class="px-4 py-3 text-xs text-slate-500 text-center font-medium">
                                        {{ (logs.current_page - 1) * logs.per_page + idx + 1 }}
                                    </td>

                                    <!-- Waktu -->
                                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                                        <div class="font-medium text-slate-900">{{ formatDate(log.created_at) }}</div>
                                        <div class="text-[11px] text-slate-400">{{ formatTimeAgo(log.created_at) }}</div>
                                    </td>

                                    <!-- Status Code Badge -->
                                    <td class="px-4 py-3 text-xs text-center whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold border"
                                            :class="getSeverityBadge(log.severity, log.status_code).class"
                                        >
                                            {{ getSeverityBadge(log.severity, log.status_code).label }}
                                        </span>
                                    </td>

                                    <!-- Fitur -->
                                    <td class="px-4 py-3 text-xs text-center whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold border"
                                            :class="getFeatureBadgeClass(log.feature)"
                                        >
                                            {{ log.feature_label || log.feature || 'Sistem' }}
                                        </span>
                                    </td>

                                    <!-- Pesan Error & URL -->
                                    <td class="px-4 py-3 text-xs max-w-md">
                                        <div class="flex items-center gap-1.5 mb-0.5">
                                            <span
                                                v-if="log.method"
                                                class="px-1.5 py-0.2 rounded font-mono font-bold text-[10px] border"
                                                :class="getMethodBadgeClass(log.method)"
                                            >
                                                {{ log.method }}
                                            </span>
                                            <span class="font-mono text-slate-600 text-[11px] truncate max-w-xs" :title="log.url">
                                                {{ log.url ? new URL(log.url, 'http://localhost').pathname : '-' }}
                                            </span>
                                        </div>
                                        <p class="font-semibold text-slate-900 line-clamp-1 break-words" :title="log.message">
                                            {{ log.message }}
                                        </p>
                                        <p v-if="log.file" class="text-[10px] text-slate-400 font-mono truncate mt-0.5" :title="`${log.file}:${log.line}`">
                                            {{ log.file }}:{{ log.line }}
                                        </p>
                                    </td>

                                    <!-- User / IP -->
                                    <td class="px-4 py-3 text-xs whitespace-nowrap">
                                        <div class="font-medium text-slate-800 truncate">
                                            {{ log.user?.name || 'Guest / Sistem' }}
                                        </div>
                                        <div class="text-[10px] font-mono text-slate-400">
                                            {{ log.user_ip || '-' }}
                                        </div>
                                    </td>

                                    <!-- Status Penanganan -->
                                    <td class="px-4 py-3 text-xs text-center whitespace-nowrap">
                                        <button
                                            type="button"
                                            @click="toggleResolve(log)"
                                            class="cursor-pointer transition-transform hover:scale-105"
                                            :title="log.status === 'resolved' ? 'Klik untuk tandai belum selesai' : 'Klik untuk tandai selesai'"
                                        >
                                            <span
                                                v-if="log.status === 'resolved'"
                                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200"
                                            >
                                                <Check class="w-3 h-3" />
                                                <span>Selesai</span>
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200"
                                            >
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                <span>Unresolved</span>
                                            </span>
                                        </button>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="px-4 py-3 text-xs text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                type="button"
                                                @click="openDetailModal(log)"
                                                class="px-2.5 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition inline-flex items-center gap-1 shadow-2xs cursor-pointer"
                                            >
                                                <span>Detail</span>
                                            </button>

                                            <button
                                                type="button"
                                                @click="confirmDeleteLog(log)"
                                                class="p-1 rounded-md text-slate-400 hover:text-red-600 hover:bg-red-50 transition cursor-pointer"
                                                title="Hapus log"
                                            >
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/40">
                        <Pagination :links="logs.links" />
                    </div>
                </div>

            </div>
        </div>

        <!-- 4. DETAIL MODAL POPUP -->
        <Teleport to="body">
            <div
                v-if="isDetailOpen && selectedLog"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
                @click.self="closeDetailModal"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="closeDetailModal" />

                <!-- Modal Dialog Box -->
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
                    
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50/80">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border"
                                :class="selectedLog.status_code >= 500 ? 'bg-red-100 text-red-700 border-red-200' : 'bg-amber-100 text-amber-700 border-amber-200'"
                            >
                                <AlertTriangle class="w-5 h-5" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-sm font-bold text-slate-900">
                                        {{ selectedLog.error_type || 'System Error' }}
                                    </h3>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border"
                                        :class="getFeatureBadgeClass(selectedLog.feature)"
                                    >
                                        {{ selectedLog.feature_label || selectedLog.feature }}
                                    </span>
                                    <span
                                        v-if="selectedLog.status === 'resolved'"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800"
                                    >
                                        <Check class="w-3 h-3" />
                                        Selesai Ditangani
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800"
                                    >
                                        Belum Ditangani
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-0.5 font-mono">
                                    ID: {{ selectedLog.id }} • Waktu: {{ selectedLog.created_at }}
                                </p>
                            </div>
                        </div>

                        <button
                            @click="closeDetailModal"
                            class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition cursor-pointer"
                        >
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="flex items-center px-6 border-b border-slate-200 bg-white gap-6 text-xs font-semibold">
                        <button
                            type="button"
                            @click="activeDetailTab = 'overview'"
                            class="py-3 border-b-2 transition cursor-pointer"
                            :class="activeDetailTab === 'overview' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-700'"
                        >
                            Ringkasan & Konteks
                        </button>
                        <button
                            type="button"
                            @click="activeDetailTab = 'trace'"
                            class="py-3 border-b-2 transition cursor-pointer flex items-center gap-1.5"
                            :class="activeDetailTab === 'trace' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-700'"
                        >
                            <Terminal class="w-3.5 h-3.5" />
                            <span>Stack Trace</span>
                        </button>
                        <button
                            type="button"
                            @click="activeDetailTab = 'payload'"
                            class="py-3 border-b-2 transition cursor-pointer"
                            :class="activeDetailTab === 'payload' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-700'"
                        >
                            Request Body & Headers
                        </button>
                    </div>

                    <!-- Scrollable Modal Content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-5">

                        <!-- TAB 1: OVERVIEW -->
                        <div v-if="activeDetailTab === 'overview'" class="space-y-4">
                            <!-- Error Message Banner -->
                            <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-900 space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-red-700 block">
                                    Pesan Kesalahan (Exception Message):
                                </span>
                                <p class="text-xs font-mono font-bold break-words leading-relaxed">
                                    {{ selectedLog.message }}
                                </p>
                            </div>

                            <!-- Metadata Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs">
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Exception Class</span>
                                    <span class="font-mono text-slate-800 break-all text-[11px] font-semibold">{{ selectedLog.exception_class || '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">File & Line</span>
                                    <span class="font-mono text-slate-800 break-all text-[11px]">{{ selectedLog.file }}:{{ selectedLog.line }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">HTTP Method & URL</span>
                                    <span class="font-mono text-slate-800 break-all text-[11px] font-bold">
                                        {{ selectedLog.method }} {{ selectedLog.url || '-' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Pelaku / User</span>
                                    <span class="text-slate-800 font-semibold">{{ selectedLog.user?.name || 'Guest / Unauthenticated' }}</span>
                                    <span v-if="selectedLog.user?.email" class="text-slate-400 text-[10px] block">({{ selectedLog.user.email }})</span>
                                </div>
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">IP Address</span>
                                    <span class="font-mono text-slate-800">{{ selectedLog.user_ip || '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Status Penanganan</span>
                                    <span class="font-semibold" :class="selectedLog.status === 'resolved' ? 'text-emerald-700' : 'text-amber-700'">
                                        {{ selectedLog.status === 'resolved' ? 'Selesai Ditangani' : 'Belum Ditangani' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Resolved info if applicable -->
                            <div v-if="selectedLog.status === 'resolved'" class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-900">
                                <div class="flex items-center gap-1.5 font-bold text-emerald-950">
                                    <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                                    <span>Ditandai Selesai oleh {{ selectedLog.resolver?.name || 'Admin' }} pada {{ selectedLog.resolved_at || '-' }}</span>
                                </div>
                                <p v-if="selectedLog.resolution_notes" class="text-[11px] text-emerald-800 mt-1">
                                    Catatan: {{ selectedLog.resolution_notes }}
                                </p>
                            </div>

                            <!-- User Agent -->
                            <div v-if="selectedLog.user_agent" class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                                    User Agent Klien:
                                </span>
                                <p class="text-[11px] font-mono text-slate-600 break-all">
                                    {{ selectedLog.user_agent }}
                                </p>
                            </div>
                        </div>

                        <!-- TAB 2: STACK TRACE -->
                        <div v-if="activeDetailTab === 'trace'" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    Full Stack Trace
                                </span>
                                <button
                                    type="button"
                                    @click="copyStackTrace"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold transition cursor-pointer"
                                >
                                    <Check v-if="isCopied" class="w-3.5 h-3.5 text-emerald-400" />
                                    <Copy v-else class="w-3.5 h-3.5" />
                                    <span>{{ isCopied ? 'Tersalin!' : 'Salin Stack Trace' }}</span>
                                </button>
                            </div>

                            <pre class="p-4 bg-slate-950 text-slate-100 rounded-xl text-[11px] font-mono overflow-x-auto max-h-96 leading-relaxed border border-slate-800 select-all">{{ selectedLog.stack_trace || 'Tidak ada stack trace yang tersedia.' }}</pre>
                        </div>

                        <!-- TAB 3: PAYLOAD -->
                        <div v-if="activeDetailTab === 'payload'" class="space-y-3">
                            <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Request Headers, Query & Body
                            </span>

                            <pre class="p-4 bg-slate-950 text-slate-100 rounded-xl text-[11px] font-mono overflow-x-auto max-h-96 leading-relaxed border border-slate-800 select-all">{{ typeof selectedLog.request_payload === 'object' ? JSON.stringify(selectedLog.request_payload, null, 2) : (selectedLog.request_payload || 'Tidak ada payload request yang terekam.') }}</pre>
                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="toggleResolve(selectedLog)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                                :class="selectedLog.status === 'resolved' ? 'bg-amber-100 text-amber-800 hover:bg-amber-200 border border-amber-300' : 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-2xs'"
                            >
                                <Check class="w-3.5 h-3.5" />
                                <span>{{ selectedLog.status === 'resolved' ? 'Kembalikan ke Belum Selesai' : 'Tandai Selesai Ditangani' }}</span>
                            </button>

                            <button
                                type="button"
                                @click="confirmDeleteLog(selectedLog)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-red-600 hover:bg-red-50 hover:text-red-700 transition cursor-pointer"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                                <span>Hapus Log</span>
                            </button>
                        </div>

                        <SecondaryButton @click="closeDetailModal" class="text-xs px-4 py-2">
                            Tutup
                        </SecondaryButton>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- CONFIRMATION MODALS -->
        <!-- 1. Confirm Delete Single Log -->
        <ConfirmModal
            :show="!!logToDelete"
            title="Konfirmasi Hapus Log Error"
            :message="`Apakah Anda yakin ingin menghapus log error '${logToDelete?.message?.substring(0, 80)}...'?`"
            confirm-label="Hapus Log"
            variant="danger"
            :loading="isActionLoading"
            @confirm="handleDeleteLog"
            @cancel="logToDelete = null"
        />

        <!-- 2. Confirm Resolve All -->
        <ConfirmModal
            :show="showResolveAllModal"
            title="Tandai Semua Log Sebagai Selesai"
            message="Apakah Anda yakin ingin menandai seluruh error yang belum selesai sebagai Selesai Ditangani?"
            confirm-label="Ya, Tandai Semua Selesai"
            variant="warning"
            :loading="isActionLoading"
            @confirm="handleResolveAll"
            @cancel="showResolveAllModal = false"
        />

        <!-- 3. Confirm Clear All -->
        <ConfirmModal
            :show="showClearAllModal"
            title="Bersihkan Semua Log Error"
            message="Tindakan ini akan menghapus seluruh riwayat log error dari database secara permanen. Apakah Anda yakin?"
            confirm-label="Hapus Semua Log"
            variant="danger"
            :loading="isActionLoading"
            @confirm="handleClearAll"
            @cancel="showClearAllModal = false"
        />

    </AppLayout>
</template>

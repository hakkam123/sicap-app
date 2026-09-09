<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { 
    X as XMarkIcon, 
    FileText, 
    CheckCircle2, 
    AlertTriangle, 
    Search, 
    RotateCcw 
} from 'lucide-vue-next';

const props = defineProps({
    logs: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ 
            status: '',
            search: '',
            date_from: '',
            date_to: '',
            per_page: 10,
        }),
    },
});

const page = usePage();

// Filter States & Form
const isSearching = ref(false);
const filterForm = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    per_page: props.filters.per_page || 10,
});

const statusOptions = [
    { label: 'Semua Status', value: '' },
    { label: 'Pending', value: 'pending' },
    { label: 'Processing', value: 'processing' },
    { label: 'Success', value: 'success' },
    { label: 'Failed', value: 'failed' },
];

// Methods untuk Filter
const applyFilters = () => {
    isSearching.value = true;
    router.get(
        route('import-logs.index'),
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
        search: '',
        status: '',
        date_from: '',
        date_to: '',
        per_page: 10,
    };
    applyFilters();
};

// Formatter
const formatDate = (isoString) => {
    if (!isoString) return '-';
    const d = new Date(isoString);
    return d.toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};

const formatFullDate = (isoString) => {
    if (!isoString) return '-';
    const d = new Date(isoString);
    return d.toLocaleString('id-ID', {
        dateStyle: 'full',
        timeStyle: 'medium',
    });
};

// Detail Modal State & Logic
const isDetailOpen = ref(false);
const selectedLog = ref(null);

const openDetailModal = (log) => {
    selectedLog.value = log;
    isDetailOpen.value = true;
};

const closeDetailModal = () => {
    isDetailOpen.value = false;
    selectedLog.value = null;
};

const progressPercentage = computed(() => {
    if (!selectedLog.value || !selectedLog.value.total_rows || selectedLog.value.total_rows === 0) return 0;
    return Math.min(100, Math.round((selectedLog.value.processed_rows / selectedLog.value.total_rows) * 100));
});

// Parse error messages into structured rows
const parsedErrors = computed(() => {
    if (!selectedLog.value || !selectedLog.value.error_message) return [];

    let rawList = [];
    try {
        const decoded = JSON.parse(selectedLog.value.error_message);
        if (Array.isArray(decoded)) {
            rawList = decoded;
        } else if (typeof decoded === 'string') {
            rawList = [decoded];
        } else {
            rawList = [JSON.stringify(decoded)];
        }
    } catch (e) {
        rawList = selectedLog.value.error_message.split('\n').filter(line => line.trim().length > 0);
    }

    return rawList.map((err, index) => {
        const str = String(err);
        const match = str.match(/^Baris\s+(\d+):\s*(.+)$/i);
        if (match) {
            const rowNum = match[1];
            const msg = match[2];
            let field = 'Data Row';

            if (/part\s*number/i.test(msg) || /pn/i.test(msg)) {
                field = 'Part Number';
            } else if (/tanggal/i.test(msg) || /date/i.test(msg)) {
                field = 'Date';
            } else if (/quantity/i.test(msg) || /qty/i.test(msg)) {
                field = 'Quantity';
            } else if (/amount/i.test(msg)) {
                field = 'Amount';
            }

            return {
                id: index + 1,
                row: rowNum,
                field: field,
                message: msg,
            };
        }

        return {
            id: index + 1,
            row: '-',
            field: 'General',
            message: str,
        };
    });
});
</script>

<template>
    <AppLayout>
        <Head title="Riwayat Import Log" />

        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800">
                        Riwayat Import Log
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Monitoring status proses background job dan audit rincian data import Excel.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="max-w-7xl mx-auto space-y-4">

                <!-- Filter Bar Card -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <div class="flex flex-col lg:flex-row gap-3 items-end">
                        
                        <!-- Search Pencarian -->
                        <div class="flex-1 min-w-[200px]">
                            <label for="filter_search" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                                Pencarian
                            </label>
                            <div class="relative">
                                <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
                                <input
                                    id="filter_search"
                                    type="text"
                                    v-model="filterForm.search"
                                    placeholder="Ketik nama file..."
                                    @keydown.enter="applyFilters"
                                    class="w-full pl-8 pr-3 py-2 border border-slate-300 rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                                />
                            </div>
                        </div>

                        <!-- Dropdown Status -->
                        <div class="w-full lg:w-32">
                            <label for="filter_status" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                                Status
                            </label>
                            <select
                                id="filter_status"
                                v-model="filterForm.status"
                                @change="applyFilters"
                                class="w-full py-2 px-3 border border-slate-300 bg-white rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                            >
                                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Date From -->
                        <div class="w-full lg:w-36">
                            <label for="filter_date_from" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                                Dari Tanggal
                            </label>
                            <input
                                id="filter_date_from"
                                type="date"
                                v-model="filterForm.date_from"
                                class="w-full py-2 px-3 border border-slate-300 rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                            />
                        </div>

                        <!-- Date To -->
                        <div class="w-full lg:w-36">
                            <label for="filter_date_to" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                                Sampai Tanggal
                            </label>
                            <input
                                id="filter_date_to"
                                type="date"
                                v-model="filterForm.date_to"
                                class="w-full py-2 px-3 border border-slate-300 rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                            />
                        </div>

                        <!-- Tampilkan (Per Page) -->
                        <div class="w-full lg:w-20">
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                                Tampilkan
                            </label>
                            <select
                                v-model="filterForm.per_page"
                                @change="applyFilters"
                                class="w-full py-2 px-3 border border-slate-300 bg-white rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                            >
                                <option :value="10">10</option>
                                <option :value="15">15</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                                <option :value="100">100</option>
                            </select>
                        </div>

                        <!-- Buttons Cari & Reset -->
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button
                                type="button"
                                @click="applyFilters"
                                :disabled="isSearching"
                                class="inline-flex items-center justify-center gap-1.5 py-2 px-4 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold shadow-sm transition disabled:opacity-60"
                                title="Terapkan filter pencarian"
                            >
                                <Search class="w-3.5 h-3.5" />
                                <span>Cari</span>
                            </button>

                            <button
                                type="button"
                                @click="resetFilters"
                                class="inline-flex items-center justify-center py-2 px-3 border border-slate-300 bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 rounded-lg text-xs transition"
                                title="Reset seluruh filter"
                            >
                                <RotateCcw class="w-3.5 h-3.5" />
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Table Card -->
                <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-slate-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50/80">
                                <tr>
                                    <th scope="col" class="px-4 py-3.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider w-12">
                                        No
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Nama File
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Total Baris
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Baris Diproses
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Diupload Oleh
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Waktu Upload
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-100">
                                <tr v-if="logs.data.length === 0">
                                    <td colspan="8" class="px-4 py-12 text-center text-slate-400 text-sm">
                                        <div class="flex flex-col items-center justify-center">
                                            <FileText class="w-8 h-8 text-slate-300 mb-2" />
                                            <span>Tidak ada riwayat import ditemukan.</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-for="(log, idx) in logs.data"
                                    :key="log.id"
                                    class="hover:bg-slate-50/70 transition-colors"
                                >
                                    <td class="px-4 py-3 text-xs text-slate-500 text-center font-medium">
                                        {{ (logs.current_page - 1) * logs.per_page + idx + 1 }}
                                    </td>
                                    <td class="px-4 py-3 text-xs font-medium text-slate-900 max-w-xs truncate" :title="log.filename">
                                        <div class="flex items-center gap-2">
                                            <FileText class="w-4 h-4 text-slate-400 shrink-0" />
                                            <span class="truncate font-semibold">{{ log.filename }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-center whitespace-nowrap">
                                        <span
                                            v-if="log.status === 'success'"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200/60"
                                        >
                                            Success
                                        </span>
                                        <span
                                            v-else-if="log.status === 'processing'"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200/60 animate-pulse"
                                        >
                                            Processing
                                        </span>
                                        <span
                                            v-else-if="log.status === 'pending'"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200"
                                        >
                                            Pending
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 border border-red-200/60"
                                        >
                                            Failed
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-center font-medium text-slate-700 tabular-nums">
                                        {{ log.total_rows }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-center font-bold tabular-nums" :class="log.status === 'success' ? 'text-emerald-700' : 'text-slate-700'">
                                        {{ log.processed_rows }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-600">
                                        {{ log.user?.name || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">
                                        {{ formatDate(log.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-right whitespace-nowrap">
                                        <button
                                            type="button"
                                            @click="openDetailModal(log)"
                                            class="px-2.5 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition-colors inline-flex items-center gap-1.5 cursor-pointer shadow-2xs"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            Detail
                                        </button>
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

        <!-- Detail Log Modal Popup -->
        <Teleport to="body">
            <div
                v-if="isDetailOpen && selectedLog"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
                @click.self="closeDetailModal"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="closeDetailModal" />

                <!-- Modal Dialog -->
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
                    
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50/80">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center shrink-0 border border-slate-200">
                                <FileText class="w-5 h-5 text-slate-700" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-bold text-slate-900 truncate" :title="selectedLog.filename">
                                        {{ selectedLog.filename }}
                                    </h3>
                                    <span
                                        v-if="selectedLog.status === 'success'"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800"
                                    >
                                        Success
                                    </span>
                                    <span
                                        v-else-if="selectedLog.status === 'processing'"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 animate-pulse"
                                    >
                                        Processing
                                    </span>
                                    <span
                                        v-else-if="selectedLog.status === 'pending'"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700"
                                    >
                                        Pending
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800"
                                    >
                                        Failed
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-0.5 font-mono">
                                    Log ID: {{ selectedLog.id }}
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

                    <!-- Scrollable Modal Content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">

                        <!-- Metadata Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-slate-50/80 p-4 rounded-xl border border-slate-200/80">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                                    Diupload Oleh
                                </span>
                                <p class="text-xs font-bold text-slate-800 mt-1">
                                    {{ selectedLog.user?.name || '-' }}
                                </p>
                                <p class="text-[11px] text-slate-400">
                                    {{ selectedLog.user?.email || '' }}
                                </p>
                            </div>

                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                                    Waktu Upload
                                </span>
                                <p class="text-xs font-semibold text-slate-800 mt-1">
                                    {{ formatFullDate(selectedLog.created_at) }}
                                </p>
                            </div>

                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                                    Terakhir Diperbarui
                                </span>
                                <p class="text-xs font-semibold text-slate-800 mt-1">
                                    {{ formatFullDate(selectedLog.updated_at) }}
                                </p>
                            </div>
                        </div>

                        <!-- Progress Bar & Count -->
                        <div class="p-4 bg-white rounded-xl border border-slate-200 shadow-2xs">
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-700 mb-2">
                                <span>Progres Pemrosesan Baris</span>
                                <span class="text-xs font-bold text-slate-900 tabular-nums">
                                    {{ selectedLog.processed_rows }} / {{ selectedLog.total_rows }} baris ({{ progressPercentage }}%)
                                </span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                                <div
                                    class="h-2.5 rounded-full transition-all duration-500"
                                    :class="[
                                        selectedLog.status === 'success' ? 'bg-emerald-500' :
                                        selectedLog.status === 'failed' ? 'bg-red-500' : 'bg-blue-500'
                                    ]"
                                    :style="{ width: `${progressPercentage}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Success State Card -->
                        <div
                            v-if="selectedLog.status === 'success'"
                            class="bg-emerald-50/80 rounded-xl p-4 border border-emerald-200 flex items-start gap-3.5 text-emerald-800"
                        >
                            <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
                            <div>
                                <h4 class="text-xs font-bold text-emerald-900">
                                    Proses Import Selesai dengan Berhasil
                                </h4>
                                <p class="text-xs text-emerald-700 mt-0.5 leading-relaxed">
                                    Semua data konsumsi pada file ini telah berhasil divalidasi dan disimpan ke database tanpa kendala.
                                </p>
                            </div>
                        </div>

                        <!-- Error Messages Table (If Failed / Has Errors) -->
                        <div
                            v-if="selectedLog.status === 'failed' || parsedErrors.length > 0"
                            class="space-y-3"
                        >
                            <div class="bg-red-50/80 rounded-xl p-4 border border-red-200 flex items-start gap-3 text-red-800">
                                <AlertTriangle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                                <div>
                                    <h4 class="text-xs font-bold text-red-900">
                                        Ditemukan {{ parsedErrors.length }} Kesalahan Validasi Baris
                                    </h4>
                                    <p class="text-xs text-red-700 mt-0.5 leading-relaxed">
                                        Karena sistem menggunakan transaksi database atomik, data yang salah tidak dimasukkan untuk mencegah kerusakan integritas data.
                                    </p>
                                </div>
                            </div>

                            <!-- Errors Table -->
                            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th scope="col" class="px-3.5 py-2.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider w-16">
                                                Baris
                                            </th>
                                            <th scope="col" class="px-3.5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-32">
                                                Field
                                            </th>
                                            <th scope="col" class="px-3.5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">
                                                Pesan Error / Keterangan
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-100">
                                        <tr
                                            v-for="err in parsedErrors"
                                            :key="err.id"
                                            class="hover:bg-red-50/30 transition-colors"
                                        >
                                            <td class="px-3.5 py-2.5 text-xs font-mono font-bold text-slate-800 text-center">
                                                {{ err.row }}
                                            </td>
                                            <td class="px-3.5 py-2.5 text-xs">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-red-100 text-red-800">
                                                    {{ err.field }}
                                                </span>
                                            </td>
                                            <td class="px-3.5 py-2.5 text-xs text-red-700 font-medium">
                                                {{ err.message }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex justify-end">
                        <SecondaryButton @click="closeDetailModal" class="text-xs px-4 py-2">
                            Tutup
                        </SecondaryButton>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
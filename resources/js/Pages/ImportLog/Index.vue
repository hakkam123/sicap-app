<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useExportWithToast } from '@/composables/useExportWithToast';
import { 
    X as XMarkIcon, 
    FileText, 
    CheckCircle2, 
    AlertTriangle, 
    Search, 
    RotateCcw,
    Download
} from 'lucide-vue-next';

const props = defineProps({
    logs: {
        type: Object,
        required: true,
    },
    features: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ 
            feature: '',
            status: '',
            search: '',
            date_from: '',
            date_to: '',
            per_page: 10,
        }),
    },
});

const { download } = useExportWithToast();

// Table Columns
const tableColumns = [
    { key: 'filename', label: 'Nama File', minWidth: 'min-w-[200px]' },
    { key: 'feature', label: 'Fitur', align: 'center', width: 'w-28' },
    { key: 'status', label: 'Status', align: 'center', width: 'w-28' },
    { key: 'total_rows', label: 'Total Baris', align: 'center', width: 'w-24' },
    { key: 'success_rows', label: 'Berhasil', align: 'center', width: 'w-24' },
    { key: 'failed_rows', label: 'Gagal', align: 'center', width: 'w-24' },
    { key: 'user.name', label: 'Diupload Oleh', width: 'w-36' },
    { key: 'created_at', label: 'Waktu Upload', width: 'w-36' },
];

const getFeatureLabel = (feature) => {
    const map = {
        consume: 'Consume',
        part_number: 'Part Number',
        area: 'Area',
        machine: 'Machine',
        mapping: 'Mapping',
        user: 'User',
    };
    return map[feature] || (feature ? feature.toUpperCase() : '-');
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'success':
            return 'Success';
        case 'processing':
            return 'Processing';
        case 'pending':
            return 'Pending';
        case 'failed':
            return 'Failed';
        default:
            return status || '-';
    }
};

// Filter States & Form
const isSearching = ref(false);
const filterForm = ref({
    feature: props.filters.feature || '',
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
        feature: '',
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
    if (!selectedLog.value || !selectedLog.value.total_rows || selectedLog.value.total_rows === 0) {
        return selectedLog.value?.status === 'success' ? 100 : 0;
    }
    return Math.min(100, Math.round((selectedLog.value.processed_rows / selectedLog.value.total_rows) * 100));
});

// Parse error details
const parsedErrors = computed(() => {
    if (!selectedLog.value) return [];
    const details = selectedLog.value.error_details;

    if (Array.isArray(details) && details.length > 0) {
        return details.map((err, idx) => {
            if (typeof err === 'object' && err !== null) {
                return {
                    id: idx + 1,
                    row: err.row ?? '-',
                    field: err.field ?? 'General',
                    value: err.value ?? '-',
                    message: err.message ?? JSON.stringify(err),
                };
            }
            return {
                id: idx + 1,
                row: '-',
                field: 'General',
                value: '-',
                message: String(err),
            };
        });
    }

    if (selectedLog.value.error_message) {
        return selectedLog.value.error_message.split('\n').filter(l => l.trim().length > 0).map((msg, idx) => {
            const match = msg.match(/^Baris\s+(\d+):\s*(.+)$/i);
            return {
                id: idx + 1,
                row: match ? match[1] : '-',
                field: 'General',
                value: '-',
                message: match ? match[2] : msg,
            };
        });
    }

    return [];
});

const handleDownloadErrorExcel = (log) => {
    if (!log) return;
    const url = route('error-monitoring.download-errors', log.id);
    const filename = `laporan_error_${log.feature || 'import'}_${log.id.substring(0, 8)}.xlsx`;
    download({
        url,
        filename,
        title: 'Download Laporan Error',
        loadingMessage: 'Menyiapkan file laporan kesalahan...',
        successMessage: 'Laporan kesalahan berhasil diunduh.',
    });
};
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

                        <!-- Dropdown Fitur -->
                        <div class="w-full lg:w-40">
                            <label for="filter_feature" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                                Fitur / Modul
                            </label>
                            <select
                                id="filter_feature"
                                v-model="filterForm.feature"
                                @change="applyFilters"
                                class="w-full py-2 px-3 border border-slate-300 bg-white rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                            >
                                <option v-for="f in features" :key="f.value" :value="f.value">
                                    {{ f.label }}
                                </option>
                            </select>
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
                <DataTable
                    :columns="tableColumns"
                    :data="logs"
                >
                    <template #cell-filename="{ value }">
                        <span class="font-medium text-slate-900 truncate" :title="value">{{ value }}</span>
                    </template>

                    <template #cell-feature="{ row }">
                        <span class="text-slate-700">{{ row.feature_label || getFeatureLabel(row.feature) }}</span>
                    </template>

                    <template #cell-status="{ value }">
                        <span class="text-slate-700">{{ getStatusLabel(value) }}</span>
                    </template>

                    <template #cell-total_rows="{ value }">
                        <span class="text-slate-700 tabular-nums">{{ value || 0 }}</span>
                    </template>

                    <template #cell-success_rows="{ value }">
                        <span class="text-slate-700 tabular-nums font-semibold">{{ value || 0 }}</span>
                    </template>

                    <template #cell-failed_rows="{ value }">
                        <span class="text-slate-700 tabular-nums font-semibold">{{ value || 0 }}</span>
                    </template>

                    <template #cell-user.name="{ row }">
                        <span class="text-slate-700">{{ row.user?.name || '-' }}</span>
                    </template>

                    <template #cell-created_at="{ value }">
                        <span class="text-slate-700 whitespace-nowrap">{{ formatDate(value) }}</span>
                    </template>

                    <template #actions="{ row }">
                        <div class="flex items-center justify-end">
                            <button
                                v-if="row.failed_rows > 0 || row.status === 'failed'"
                                type="button"
                                @click="handleDownloadErrorExcel(row)"
                                class="text-red-600 hover:text-red-900 hover:underline font-semibold inline-flex items-center gap-1 text-xs"
                            >
                                Excel Error
                            </button>
                            <button
                                type="button"
                                @click="openDetailModal(row)"
                                class="text-blue-600 hover:text-blue-900 hover:underline font-semibold inline-flex items-center gap-1 text-xs"
                                :class="{ 'ml-3': row.failed_rows > 0 || row.status === 'failed' }"
                            >
                                Detail
                            </button>
                        </div>
                    </template>

                    <template #empty>
                        <div class="flex flex-col items-center justify-center py-6 text-slate-400">
                            <FileText class="w-8 h-8 text-slate-300 mb-2" />
                            <span class="font-semibold text-slate-700">Tidak ada riwayat import ditemukan.</span>
                        </div>
                    </template>
                </DataTable>
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
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
                    
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
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 bg-slate-50/80 p-4 rounded-xl border border-slate-200/80">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                                    Diupload Oleh
                                </span>
                                <p class="text-xs font-bold text-slate-800 mt-1 truncate">
                                    {{ selectedLog.user?.name || '-' }}
                                </p>
                                <p class="text-[11px] text-slate-400 truncate">
                                    {{ selectedLog.user?.email || '' }}
                                </p>
                            </div>

                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                                    Waktu Mulai
                                </span>
                                <p class="text-xs font-semibold text-slate-800 mt-1">
                                    {{ formatFullDate(selectedLog.started_at || selectedLog.created_at) }}
                                </p>
                            </div>

                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                                    Waktu Selesai
                                </span>
                                <p class="text-xs font-semibold text-slate-800 mt-1">
                                    {{ formatFullDate(selectedLog.finished_at) }}
                                </p>
                            </div>

                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                                    Hasil Baris
                                </span>
                                <p class="text-xs font-bold mt-1">
                                    <span class="text-emerald-700">{{ selectedLog.success_rows }} sukses</span> / 
                                    <span class="text-red-700">{{ selectedLog.failed_rows }} gagal</span>
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
                                    Semua data pada file ini telah berhasil divalidasi dan disimpan ke database tanpa kendala.
                                </p>
                            </div>
                        </div>

                        <!-- Error Messages Table (If Failed / Has Errors) -->
                        <div
                            v-if="selectedLog.status === 'failed' || parsedErrors.length > 0"
                            class="space-y-3"
                        >
                            <div class="bg-red-50/80 rounded-xl p-4 border border-red-200 flex items-start justify-between gap-3 text-red-800">
                                <div class="flex items-start gap-3">
                                    <AlertTriangle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                                    <div>
                                        <h4 class="text-xs font-bold text-red-900">
                                            Ditemukan {{ parsedErrors.length }} Kesalahan Validasi Baris Data
                                        </h4>
                                        <p class="text-xs text-red-700 mt-0.5 leading-relaxed">
                                            Silakan perbaiki data yang ditandai pada tabel di bawah atau unduh file laporan error untuk memeriksa kesalahan secara lengkap.
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="handleDownloadErrorExcel(selectedLog)"
                                    class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold shadow-sm transition"
                                >
                                    <Download class="w-3.5 h-3.5" />
                                    <span>Download Report</span>
                                </button>
                            </div>

                            <!-- Errors Table -->
                            <div class="overflow-x-auto border border-slate-200 rounded-xl max-h-80 overflow-y-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50 sticky top-0 z-10">
                                        <tr>
                                            <th scope="col" class="px-3.5 py-2.5 text-center text-xs font-bold text-slate-500 uppercase tracking-wider w-16">
                                                Baris
                                            </th>
                                            <th scope="col" class="px-3.5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-36">
                                                Kolom / Field
                                            </th>
                                            <th scope="col" class="px-3.5 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-40">
                                                Nilai Input
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
                                            <td class="px-3.5 py-2.5 text-xs text-slate-700 font-mono">
                                                {{ err.value }}
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
                    <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                        <div>
                            <button
                                v-if="selectedLog.failed_rows > 0 || selectedLog.status === 'failed'"
                                type="button"
                                @click="handleDownloadErrorExcel(selectedLog)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-lg text-xs font-bold transition"
                            >
                                <Download class="w-3.5 h-3.5" />
                                <span>Unduh Laporan Error (.xlsx)</span>
                            </button>
                        </div>
                        <SecondaryButton @click="closeDetailModal" class="text-xs px-4 py-2">
                            Tutup
                        </SecondaryButton>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
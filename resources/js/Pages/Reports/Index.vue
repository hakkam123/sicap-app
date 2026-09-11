<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import {
    Search,
    RotateCcw,
    FileSpreadsheet,
    FileText,
} from 'lucide-vue-next';

const props = defineProps({
    consumptions: {
        type: Object,
        required: true,
    },
    areas: {
        type: Array,
        required: true,
    },
    machines: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            area_id: '',
            machine_id: '',
            date_from: '',
            date_to: '',
            per_page: 15,
        }),
    },
    summary: {
        type: Object,
        default: () => ({
            total_qty: 0,
            total_amount: 0,
        }),
    },
});

// State Filter Form
const filterForm = ref({
    search: props.filters.search || '',
    area_id: props.filters.area_id || '',
    machine_id: props.filters.machine_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    per_page: props.filters.per_page || 15,
});

// Dependent Machine Dropdown Data
const machineOptions = ref([...props.machines]);
const isLoadingMachines = ref(false);

const handleAreaChange = async () => {
    filterForm.value.machine_id = '';
    if (!filterForm.value.area_id) {
        machineOptions.value = [];
        return;
    }

    isLoadingMachines.value = true;
    try {
        const response = await fetch(`/consume/machines-by-area?area_id=${filterForm.value.area_id}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });
        if (response.ok) {
            machineOptions.value = await response.json();
        } else {
            machineOptions.value = [];
        }
    } catch {
        machineOptions.value = [];
    } finally {
        isLoadingMachines.value = false;
    }
};

// Filter Actions
const isSearching = ref(false);

const applyFilters = () => {
    isSearching.value = true;
    router.get(
        route('reports.index'),
        {
            search: filterForm.value.search || '',
            area_id: filterForm.value.area_id || '',
            machine_id: filterForm.value.machine_id || '',
            date_from: filterForm.value.date_from || '',
            date_to: filterForm.value.date_to || '',
            per_page: filterForm.value.per_page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                isSearching.value = false;
            },
        }
    );
};

const resetFilters = () => {
    filterForm.value = {
        search: '',
        area_id: '',
        machine_id: '',
        date_from: '',
        date_to: '',
        per_page: 15,
    };
    machineOptions.value = [];
    router.get(route('reports.index'), {}, { preserveState: false });
};

import { useExportWithToast } from '@/composables/useExportWithToast';

const { isExporting, download: downloadExport } = useExportWithToast();

// Export Handlers with Progress Toast
const exportExcel = () => {
    const params = new URLSearchParams({
        type: 'excel',
        ...(filterForm.value.search && { search: filterForm.value.search }),
        ...(filterForm.value.area_id && { area_id: filterForm.value.area_id }),
        ...(filterForm.value.machine_id && { machine_id: filterForm.value.machine_id }),
        ...(filterForm.value.date_from && { date_from: filterForm.value.date_from }),
        ...(filterForm.value.date_to && { date_to: filterForm.value.date_to }),
    });

    const url = `${route('reports.export')}?${params.toString()}`;
    const filename = `laporan_konsumsi_${filterForm.value.date_from || 'semua'}_${filterForm.value.date_to || 'semua'}.xlsx`;

    downloadExport({
        url,
        filename,
        title: 'Ekspor Laporan Excel',
        loadingMessage: 'Menyiapkan file laporan Excel konsumsi part...',
        successMessage: 'Laporan Excel konsumsi part berhasil diunduh.',
    });
};

const exportPdf = () => {
    const params = new URLSearchParams({
        type: 'pdf',
        ...(filterForm.value.search && { search: filterForm.value.search }),
        ...(filterForm.value.area_id && { area_id: filterForm.value.area_id }),
        ...(filterForm.value.machine_id && { machine_id: filterForm.value.machine_id }),
        ...(filterForm.value.date_from && { date_from: filterForm.value.date_from }),
        ...(filterForm.value.date_to && { date_to: filterForm.value.date_to }),
    });

    const url = `${route('reports.export')}?${params.toString()}`;
    const filename = `laporan_konsumsi_${filterForm.value.date_from || 'semua'}_${filterForm.value.date_to || 'semua'}.pdf`;

    downloadExport({
        url,
        filename,
        title: 'Ekspor Laporan PDF',
        loadingMessage: 'Menyiapkan file dokumen PDF laporan konsumsi part...',
        successMessage: 'Dokumen PDF laporan konsumsi part berhasil diunduh.',
    });
};

// Table Columns & Formatters
const tableColumns = [
    { key: 'consumed_at', label: 'Tanggal', width: 'w-32' },
    { key: 'part_number.pn_baan', label: 'PN BAAN', width: 'w-44' },
    { key: 'part_number.description', label: 'Deskripsi' },
    { key: 'area.name', label: 'Area', width: 'w-36' },
    { key: 'machine.name', label: 'Machine', width: 'w-36' },
    { key: 'quantity', label: 'Qty', align: 'right', width: 'w-24' },
    { key: 'amount', label: 'Amount', align: 'right', width: 'w-36' },
];

const formatDate = (isoString) => {
    if (!isoString) return '-';
    return new Date(isoString).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const formatRupiah = (val) => {
    if (val === null || val === undefined || val === '') return 'Rp 0';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Math.abs(Number(val)));
};

const formatNumber = (val) => {
    if (val === null || val === undefined) return '0';
    return new Intl.NumberFormat('id-ID').format(Math.abs(Number(val)));
};
</script>

<template>
    <AppLayout>
        <Head title="Laporan Konsumsi Sparepart" />

        <template #header>
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-800">
                    Laporan Konsumsi Sparepart
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Rekapitulasi dan analisis seluruh pemakaian sparepart, filter multi-kriteria, dan ekspor dokumen.
                </p>
            </div>
        </template>

        <div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- 1. FILTER & ACTION CARD -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 space-y-4">
                <!-- Header Toolbar with Export Buttons -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                            Filter & Ekspor Laporan
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            Saring data konsumsi berdasarkan kriteria pencarian dan unduh laporan
                        </p>
                    </div>

                    <!-- Export Action Buttons -->
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            @click="exportExcel"
                            :disabled="isExporting"
                            class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white rounded-lg text-xs font-semibold shadow-sm transition disabled:opacity-60 cursor-pointer"
                            title="Unduh laporan dalam format spreadsheet Excel"
                        >
                            <FileSpreadsheet class="w-3.5 h-3.5" />
                            <span>Export Excel</span>
                        </button>

                        <button
                            type="button"
                            @click="exportPdf"
                            :disabled="isExporting"
                            class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-600 hover:bg-red-700 active:bg-red-800 text-white rounded-lg text-xs font-semibold shadow-sm transition disabled:opacity-60 cursor-pointer"
                            title="Unduh laporan dalam format dokumen PDF"
                        >
                            <FileText class="w-3.5 h-3.5" />
                            <span>Export PDF</span>
                        </button>
                    </div>
                </div>

                <!-- Filter Form Bar -->
                <div class="flex flex-col lg:flex-row gap-3 items-end">
                    
                    <!-- Search Pencarian -->
                    <div class="flex-1 min-w-[200px] w-full">
                        <label for="filter_search" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                            Pencarian
                        </label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
                            <input
                                id="filter_search"
                                type="text"
                                v-model="filterForm.search"
                                placeholder="Ketik PN BAAN atau nama part..."
                                @keydown.enter="applyFilters"
                                class="w-full pl-8 pr-3 py-2 border border-slate-300 rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                            />
                        </div>
                    </div>

                    <!-- Dropdown Area -->
                    <div class="w-full lg:w-40">
                        <label for="filter_area" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                            Area
                        </label>
                        <select
                            id="filter_area"
                            v-model="filterForm.area_id"
                            @change="handleAreaChange"
                            class="w-full py-2 px-3 border border-slate-300 bg-white rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                        >
                            <option value="">-- Semua Area --</option>
                            <option v-for="area in areas" :key="area.id" :value="area.id">
                                {{ area.name }} ({{ area.code }})
                            </option>
                        </select>
                    </div>

                    <!-- Dropdown Machine -->
                    <div class="w-full lg:w-40">
                        <label for="filter_machine" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                            Machine
                        </label>
                        <select
                            id="filter_machine"
                            v-model="filterForm.machine_id"
                            :disabled="!filterForm.area_id || isLoadingMachines"
                            class="w-full py-2 px-3 border border-slate-300 bg-white rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900 disabled:bg-slate-100 disabled:text-slate-400"
                        >
                            <option value="">
                                {{ filterForm.area_id ? '-- Semua Machine --' : 'Pilih area dulu' }}
                            </option>
                            <option v-for="m in machineOptions" :key="m.id" :value="m.id">
                                {{ m.name }} ({{ m.code }})
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
                    <div class="flex items-center gap-1.5 shrink-0 w-full sm:w-auto">
                        <button
                            type="button"
                            @click="applyFilters"
                            :disabled="isSearching"
                            class="inline-flex items-center justify-center gap-1.5 py-2 px-4 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold shadow-sm transition disabled:opacity-60 cursor-pointer"
                            title="Terapkan filter pencarian"
                        >
                            <Search class="w-3.5 h-3.5" />
                            <span>Cari</span>
                        </button>

                        <button
                            type="button"
                            @click="resetFilters"
                            class="inline-flex items-center justify-center py-2 px-3 border border-slate-300 bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 rounded-lg text-xs transition cursor-pointer"
                            title="Reset seluruh filter"
                        >
                            <RotateCcw class="w-3.5 h-3.5" />
                        </button>
                    </div>

                </div>
            </div>

            <!-- 2. TABEL DATA LAPORAN -->
            <DataTable
                :columns="tableColumns"
                :data="consumptions"
            >
                <template #cell-consumed_at="{ value }">
                    <span class="whitespace-nowrap text-slate-700">
                        {{ formatDate(value) }}
                    </span>
                </template>

                <template #cell-part_number\.pn_baan="{ row }">
                    <span class="font-mono font-bold text-slate-700 whitespace-nowrap">
                        {{ row.part_number?.pn_baan || '-' }}
                    </span>
                </template>

                <template #cell-part_number\.description="{ row }">
                    <span class="max-w-sm truncate block text-slate-600" :title="row.part_number?.description">
                        {{ row.part_number?.description || '-' }}
                    </span>
                </template>

                <template #cell-area\.name="{ row }">
                    <span v-if="row.area" class="text-slate-700">
                        {{ row.area.name }}
                    </span>
                    <span v-else class="text-slate-400 italic text-[11px]">-</span>
                </template>

                <template #cell-machine\.name="{ row }">
                    <span v-if="row.machine" class="text-slate-700">
                        {{ row.machine.name }}
                    </span>
                    <span v-else class="text-slate-400 italic text-[11px]">-</span>
                </template>

                <template #cell-quantity="{ value }">
                    <span class="font-bold text-slate-900">
                        {{ formatNumber(value) }}
                    </span>
                </template>

                <template #cell-amount="{ value }">
                    <span class="font-semibold text-slate-900 whitespace-nowrap">
                        {{ formatRupiah(value) }}
                    </span>
                </template>

                <template #empty>
                    <p>Tidak ada transaksi konsumsi yang memenuhi kriteria filter yang dipilih.</p>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>


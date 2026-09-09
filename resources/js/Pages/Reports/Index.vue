<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import {
    FileSpreadsheet,
    FileText,
    Search,
    RotateCcw,
    Layers,
    Boxes,
    Calendar,
    Cpu,
    Coins,
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

// Export Handlers
const exportExcel = () => {
    const params = new URLSearchParams({
        type: 'excel',
        ...(filterForm.value.search && { search: filterForm.value.search }),
        ...(filterForm.value.area_id && { area_id: filterForm.value.area_id }),
        ...(filterForm.value.machine_id && { machine_id: filterForm.value.machine_id }),
        ...(filterForm.value.date_from && { date_from: filterForm.value.date_from }),
        ...(filterForm.value.date_to && { date_to: filterForm.value.date_to }),
    });

    window.location.href = `${route('reports.export')}?${params.toString()}`;
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

    window.location.href = `${route('reports.export')}?${params.toString()}`;
};

// Formatters
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

            <!-- 1. ACTION BAR + FILTER BAR (Satu Section) -->
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col gap-4">
                
                <!-- Row 1: Tombol Export -->
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="exportExcel"
                            class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow-sm transition"
                            title="Unduh laporan dalam format spreadsheet Excel"
                        >
                            <FileSpreadsheet class="w-4 h-4" />
                            <span>Export Excel</span>
                        </button>

                        <button
                            type="button"
                            @click="exportPdf"
                            class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-semibold shadow-sm transition"
                            title="Unduh laporan dalam format dokumen PDF"
                        >
                            <FileText class="w-4 h-4" />
                            <span>Export PDF</span>
                        </button>
                    </div>

                    <!-- Per Page Option -->
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            Baris:
                        </span>
                        <select
                            v-model="filterForm.per_page"
                            @change="applyFilters"
                            class="py-1 px-2 border border-slate-300 bg-white rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                        >
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>
                </div>

                <!-- Divider -->
                <div class="border-t border-slate-100"></div>

                <!-- Row 2: Filter Inputs dengan Label Uppercase -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                    
                    <!-- Search PN / Deskripsi -->
                    <div class="lg:col-span-3">
                        <label for="filter_search" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                            Pencarian PN / Deskripsi
                        </label>
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
                            <input
                                id="filter_search"
                                type="text"
                                v-model="filterForm.search"
                                placeholder="Ketik PN atau nama part..."
                                @keydown.enter="applyFilters"
                                class="w-full pl-8 pr-3 py-2 border border-slate-300 rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                            />
                        </div>
                    </div>

                    <!-- Dropdown Area -->
                    <div class="lg:col-span-2">
                        <label for="filter_area" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
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

                    <!-- Dropdown Machine (Dependent) -->
                    <div class="lg:col-span-2">
                        <label for="filter_machine" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                            Machine / Station
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
                    <div class="lg:col-span-2">
                        <label for="filter_date_from" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
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
                    <div class="lg:col-span-2">
                        <label for="filter_date_to" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                            Sampai Tanggal
                        </label>
                        <input
                            id="filter_date_to"
                            type="date"
                            v-model="filterForm.date_to"
                            class="w-full py-2 px-3 border border-slate-300 rounded-lg text-xs focus:ring-slate-900 focus:border-slate-900"
                        />
                    </div>

                    <!-- Native Buttons: Cari & Reset -->
                    <div class="lg:col-span-1 flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="applyFilters"
                            :disabled="isSearching"
                            class="flex-1 inline-flex items-center justify-center gap-1 py-2 px-3 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold shadow-sm transition disabled:opacity-60"
                            title="Terapkan filter pencarian"
                        >
                            <Search class="w-3.5 h-3.5" />
                            <span>Cari</span>
                        </button>

                        <button
                            type="button"
                            @click="resetFilters"
                            class="inline-flex items-center justify-center p-2 border border-slate-300 bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 rounded-lg text-xs transition"
                            title="Reset seluruh filter"
                        >
                            <RotateCcw class="w-3.5 h-3.5" />
                        </button>
                    </div>

                </div>
            </div>

            <!-- 2. SUMMARY CARDS (2 Card Kecil) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <!-- Card 1: Total Qty -->
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                Total Qty Terpakai
                            </p>
                            <p class="text-2xl font-bold text-slate-900 mt-2 tracking-tight">
                                {{ formatNumber(summary.total_qty) }}
                            </p>
                            <p class="text-[11px] text-slate-400 mt-1">
                                Akumulasi unit sparepart dari data yang difilter
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                            <Boxes class="w-5 h-5" />
                        </div>
                    </div>
                </div>

                <!-- Card 2: Total Amount -->
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                Total Nilai Pemakaian (Amount)
                            </p>
                            <p class="text-2xl font-bold text-slate-900 mt-2 tracking-tight">
                                {{ formatRupiah(summary.total_amount) }}
                            </p>
                            <p class="text-[11px] text-slate-400 mt-1">
                                Estimasi nilai pemakaian dari data yang difilter
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <Coins class="w-5 h-5" />
                        </div>
                    </div>
                </div>

            </div>

            <!-- 3. TABEL DATA LAPORAN -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                
                <!-- Table Header Bar -->
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">
                            Daftar Transaksi Konsumsi
                        </h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Menampilkan {{ consumptions.from || 0 }} - {{ consumptions.to || 0 }} dari {{ consumptions.total }} baris data
                        </p>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider w-12">
                                    No
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Tanggal
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    PN BAAN
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Deskripsi
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Area
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Machine
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Qty
                                </th>
                                <th scope="col" class="px-5 py-3 text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    Amount
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 bg-white">
                            <!-- Empty State -->
                            <tr v-if="consumptions.data.length === 0">
                                <td colspan="8" class="px-5 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center mb-2.5">
                                            <Calendar class="w-5 h-5 text-slate-400" />
                                        </div>
                                        <p class="text-xs font-semibold text-slate-700">
                                            Tidak ada data laporan
                                        </p>
                                        <p class="text-[11px] text-slate-400 mt-0.5">
                                            Tidak ada transaksi konsumsi yang memenuhi kriteria filter yang dipilih.
                                        </p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-for="(item, index) in consumptions.data"
                                :key="item.id"
                                class="hover:bg-slate-50/70 transition-colors"
                            >
                                <td class="px-4 py-3 text-center text-xs text-slate-400">
                                    {{ (consumptions.from || 1) + index }}
                                </td>

                                <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
                                    {{ item.consumed_at ? new Date(item.consumed_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-' }}
                                </td>

                                <td class="px-4 py-3 text-xs font-mono font-bold text-blue-700 whitespace-nowrap">
                                    {{ item.part_number?.pn_baan ?? '-' }}
                                </td>

                                <td class="px-4 py-3 text-xs text-slate-700 max-w-sm truncate" :title="item.part_number?.description">
                                    {{ item.part_number?.description ?? '-' }}
                                </td>

                                <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
                                    <span v-if="item.area" class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                                        {{ item.area.name }}
                                    </span>
                                    <span v-else class="text-slate-400">—</span>
                                </td>

                                <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
                                    {{ item.machine?.name ?? '-' }}
                                </td>

                                <td class="px-4 py-3 text-right text-xs font-bold text-slate-900 tabular-nums">
                                    {{ formatNumber(item.quantity) }}
                                </td>

                                <td class="px-5 py-3 text-right text-xs font-semibold text-slate-700 tabular-nums whitespace-nowrap font-mono">
                                    {{ formatRupiah(item.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer / Pagination -->
                <div v-if="consumptions.data.length > 0" class="p-4 border-t border-slate-100 bg-slate-50/30">
                    <Pagination :links="consumptions.links" />
                </div>

            </div>

        </div>
    </AppLayout>
</template>


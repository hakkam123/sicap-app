<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { X as XMarkIcon, RefreshCw, Clock } from 'lucide-vue-next';

// Chart.js & vue-chartjs integration
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';
import { Line, Bar } from 'vue-chartjs';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    chartData: {
        type: Array,
        default: () => [],
    },
    trendData: {
        type: Array,
        default: () => [],
    },
    topParts: {
        type: Array,
        default: () => [],
    },
    topConsumes: {
        type: Array,
        default: () => [],
    },
    areaConsumption: {
        type: Object,
        default: () => ({ fa: 0, smt: 0, common: 0 }),
    },
    byArea: {
        type: Array,
        default: () => [],
    },
    areas: {
        type: Array,
        default: () => [],
    },
    machines: {
        type: Array,
        default: () => [],
    },
    lastSyncAt: {
        type: String,
        default: null,
    },
    filters: {
        type: Object,
        default: () => ({
            area_id: '',
            machine_id: '',
            date_from: '',
            date_to: '',
        }),
    },
});

const page = usePage();

// Filter State
const filterForm = ref({
    area_id: props.filters.area_id || '',
    machine_id: props.filters.machine_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

// Daily Trend Computed (handles trendData and chartData)
const dailyTrend = computed(() => (props.trendData && props.trendData.length > 0) ? props.trendData : props.chartData);

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
                'Accept': 'application/json',
            },
        });
        if (response.ok) {
            machineOptions.value = await response.json();
        } else {
            machineOptions.value = [];
        }
    } catch (e) {
        machineOptions.value = [];
    } finally {
        isLoadingMachines.value = false;
    }
};

const isLoading = ref(false);

const applyFilters = () => {
    isLoading.value = true;
    router.get(
        route('dashboard'),
        {
            area_id: filterForm.value.area_id,
            machine_id: filterForm.value.machine_id,
            date_from: filterForm.value.date_from,
            date_to: filterForm.value.date_to,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                isLoading.value = false;
            },
        }
    );
};

const resetFilters = () => {
    filterForm.value = {
        area_id: '',
        machine_id: '',
        date_from: '',
        date_to: '',
    };
    isLoading.value = true;
    router.get(route('dashboard'), {}, {
        preserveState: false,
        onFinish: () => {
            isLoading.value = false;
        },
    });
};

// Formatters (Always Positive)
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

// Format Rupiah Singkat untuk Sumbu Y (contoh: Rp 1,2jt, Rp 500rb)
const formatShortRupiah = (val) => {
    val = Math.abs(Number(val));
    if (val === 0) return 'Rp 0';
    if (val >= 1_000_000_000) {
        return 'Rp ' + (val / 1_000_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + 'M';
    }
    if (val >= 1_000_000) {
        return 'Rp ' + (val / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + 'jt';
    }
    if (val >= 1_000) {
        return 'Rp ' + (val / 1_000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + 'rb';
    }
    return 'Rp ' + val.toLocaleString('id-ID');
};

// Chart Data & Options: Tren Konsumsi Harian (Nominal Rupiah)
const chartConfig = computed(() => {
    const rawData = dailyTrend.value || [];
    const labels = rawData.map((d) => d.date);
    const dataAmount = rawData.map((d) => Math.abs(Number(d.amount || 0)));

    return {
        data: {
            labels,
            datasets: [
                {
                    label: 'Nominal Pemakaian (IDR)',
                    backgroundColor: 'rgba(59, 130, 246, 0.12)',
                    borderColor: '#2563eb',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#1d4ed8',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#1d4ed8',
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    hitRadius: 10,
                    fill: true,
                    tension: 0.3,
                    data: dataAmount,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        font: { size: 12, weight: '600' },
                    },
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const item = rawData[context.dataIndex];
                            const amtStr = `Nominal: ${formatRupiah(item.amount)}`;
                            const qtyStr = item.qty !== undefined ? ` (Qty: ${formatNumber(item.qty)})` : '';
                            return `${amtStr}${qtyStr}`;
                        },
                        afterLabel: function () {
                            return 'Klik untuk lihat laporan';
                        },
                    },
                },
            },
            onClick: (event, elements) => {
                if (elements.length > 0) {
                    const idx = elements[0].index;
                    const item = rawData[idx];
                    if (item && item.date) {
                        router.visit(route('reports.index'), {
                            method: 'get',
                            data: {
                                date_from: item.date,
                                date_to: item.date,
                                area_id: filterForm.value.area_id || '',
                                machine_id: filterForm.value.machine_id || '',
                            },
                        });
                    }
                }
            },
            onHover: (event, elements) => {
                event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } },
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Nominal Pemakaian (IDR)',
                        font: { size: 11, weight: '600' },
                        color: '#64748b',
                    },
                    grid: { color: 'rgba(226, 232, 240, 0.8)' },
                    ticks: {
                        font: { size: 11 },
                        callback: function (value) {
                            return formatShortRupiah(value);
                        },
                    },
                },
            },
        },
    };
});

// Vertical Bar Chart: Konsumsi per Area (FA, SMT, Common)
const barChartRef = ref(null);

const areaBarChartConfig = computed(() => {
    const faAmount = Math.abs(Number(props.areaConsumption?.fa || 0));
    const smtAmount = Math.abs(Number(props.areaConsumption?.smt || 0));
    const commonAmount = Math.abs(Number(props.areaConsumption?.common || 0));

    return {
        data: {
            labels: ['FA', 'SMT', 'Common'],
            datasets: [
                {
                    label: 'Nominal Konsumsi',
                    data: [faAmount, smtAmount, commonAmount],
                    backgroundColor: [
                        '#3b82f6', // FA (blue-500)
                        '#10b981', // SMT (emerald-500)
                        '#8b5cf6', // Common (violet-500)
                    ],
                    borderColor: [
                        '#2563eb',
                        '#059669',
                        '#7c3aed',
                    ],
                    borderWidth: 1.5,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 48,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return `Nominal: ${formatRupiah(ctx.raw)}`;
                        },
                        afterLabel: function () {
                            return 'Klik untuk lihat detail transaksi';
                        },
                    },
                },
            },
            onClick: (event, elements) => {
                if (elements.length > 0) {
                    const idx = elements[0].index;
                    const categories = ['fa', 'smt', 'common'];
                    const selected = categories[idx];
                    if (selected) {
                        openCategoryDrillDown(selected);
                    }
                }
            },
            onHover: (event, elements) => {
                event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { size: 12, weight: '700' },
                        color: '#334155',
                    },
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Nominal (IDR)',
                        font: { size: 10, weight: '600' },
                        color: '#64748b',
                    },
                    grid: { color: 'rgba(226, 232, 240, 0.8)' },
                    ticks: {
                        font: { size: 11 },
                        callback: function (value) {
                            return formatShortRupiah(value);
                        },
                    },
                },
            },
        },
    };
});

const barChartConfig = areaBarChartConfig;

// Drill-down State & Actions
const drillDown = ref({
    show: false,
    loading: false,
    type: '',
    id: '',
    title: '',
    rows: [],
    total_qty: 0,
    total_amount: 0,
    page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
});

const openCategoryDrillDown = async (category) => {
    const labels = {
        fa: 'FA (Fabrication)',
        smt: 'SMT (Surface Mount)',
        common: 'Common (FA & SMT)',
    };
    drillDown.value = {
        show: true,
        loading: true,
        type: 'category',
        id: category,
        title: `Konsumsi Area — ${labels[category] || category.toUpperCase()}`,
        rows: [],
        total_qty: 0,
        total_amount: 0,
        page: 1,
        last_page: 1,
        total: 0,
        from: 0,
        to: 0,
    };
    await fetchDrillDown('category', category, 1);
};

const openAreaDrillDown = async (area) => {
    const type = area.area_id ? 'area' : 'unassigned';
    const id = area.area_id ?? 'null';
    drillDown.value = {
        show: true,
        loading: true,
        type,
        id,
        title: area.area_name || area.area_code,
        rows: [],
        total_qty: 0,
        total_amount: 0,
        page: 1,
        last_page: 1,
        total: 0,
        from: 0,
        to: 0,
    };
    await fetchDrillDown(type, id, 1);
};

const openPartDrillDown = async (item) => {
    drillDown.value = {
        show: true,
        loading: true,
        type: 'part',
        id: item.part_number_id,
        title: item.pn_baan,
        rows: [],
        total_qty: 0,
        total_amount: 0,
        page: 1,
        last_page: 1,
        total: 0,
        from: 0,
        to: 0,
    };
    await fetchDrillDown('part', item.part_number_id, 1);
};

const fetchDrillDown = async (type, id, pageNum = 1) => {
    drillDown.value.loading = true;
    try {
        const params = new URLSearchParams({
            type,
            id,
            page: pageNum,
            per_page: 15,
            ...(filterForm.value.date_from && { date_from: filterForm.value.date_from }),
            ...(filterForm.value.date_to && { date_to: filterForm.value.date_to }),
        });
        const res = await fetch(`/dashboard/drill-down?${params}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });
        const data = await res.json();
        drillDown.value = {
            ...drillDown.value,
            loading: false,
            title: data.title,
            rows: data.rows,
            total_qty: data.total_qty,
            total_amount: data.total_amount,
            page: data.pagination ? data.pagination.current_page : 1,
            last_page: data.pagination ? data.pagination.last_page : 1,
            total: data.pagination ? data.pagination.total : data.rows.length,
            from: data.pagination ? data.pagination.from : 1,
            to: data.pagination ? data.pagination.to : data.rows.length,
        };
    } catch {
        drillDown.value.loading = false;
    }
};

const closeDrillDown = () => {
    drillDown.value.show = false;
};

// Top 10 Consume Sort by Total Amount
const topConsumeSort = ref('desc');

const topItems = computed(() =>
    (props.topParts && props.topParts.length > 0)
        ? props.topParts
        : (props.topConsumes || [])
);

const sortedTopConsumes = computed(() => {
    const data = [...topItems.value];

    return data.sort((a, b) => {
        const amountA = Number(a.total_amount || 0);
        const amountB = Number(b.total_amount || 0);

        return topConsumeSort.value === 'asc'
            ? amountA - amountB
            : amountB - amountA;
    });
});

const isSyncing = ref(false);

const triggerSync = () => {
    isSyncing.value = true;
    router.post(route('consume.sync-api'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isSyncing.value = false;
        },
    });
};

const refreshData = () => {
    router.reload({ preserveScroll: true });
};

</script>

<template>
    <AppLayout>
        <Head title="Dashboard" />

        <template #header>
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-800">
                    Dashboard
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Ringkasan operasional konsumsi, grafik tren harian, dan analisis part paling sering digunakan.
                </p>
            </div>
        </template>

        <div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- ACTION & INFO BAR (Di atas Filter Card) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <!-- Floating Last Sync Info Badge -->
                <div class="flex items-center gap-2">
                    <div
                        v-if="lastSyncAt"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100/90 border border-slate-200/90 text-slate-700 text-xs select-none"
                    >
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span class="text-slate-500 font-medium">Last sync at</span>
                        <span class="font-bold text-slate-800 font-mono tracking-tight">{{ lastSyncAt }}</span>
                    </div>

                    <div
                        v-else
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200/80 text-slate-400 text-xs italic"
                    >
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                        <span>Belum ada riwayat sync</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-2 flex-wrap">
                    <button
                        type="button"
                        @click="refreshData"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 border border-slate-200 bg-white text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition cursor-pointer shadow-2xs"
                        title="Muat ulang metrik dashboard"
                    >
                        <RefreshCw class="w-3.5 h-3.5 text-slate-500" />
                        Refresh
                    </button>

                    <button
                        type="button"
                        @click="triggerSync"
                        :disabled="isSyncing"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-semibold rounded-lg transition shadow-sm cursor-pointer disabled:opacity-60"
                        title="Tarik data terbaru dari API eksternal"
                    >
                        <RefreshCw :class="['w-3.5 h-3.5', isSyncing ? 'animate-spin' : '']" />
                        <span>{{ isSyncing ? 'Menarik Data...' : 'Sync API' }}</span>
                    </button>
                </div>
            </div>

            <!-- 1. Filter Bar -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 items-end">
                    
                    <!-- Area Filter -->
                    <div class="lg:col-span-3">
                        <label for="filter_area" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                            Area
                        </label>
                        <select
                            id="filter_area"
                            v-model="filterForm.area_id"
                            @change="handleAreaChange"
                            class="w-full py-2 px-3 border border-slate-300 bg-white rounded-lg text-xs focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">-- Semua Area --</option>
                            <option v-for="area in areas" :key="area.id" :value="area.id">
                                {{ area.name }} ({{ area.code }})
                            </option>
                        </select>
                    </div>

                    <!-- Machine Filter (Dependent on Area) -->
                    <div class="lg:col-span-3">
                        <label for="filter_machine" class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                            Machine / Station
                        </label>
                        <select
                            id="filter_machine"
                            v-model="filterForm.machine_id"
                            :disabled="!filterForm.area_id || isLoadingMachines"
                            class="w-full py-2 px-3 border border-slate-300 bg-white rounded-lg text-xs focus:ring-blue-500 focus:border-blue-500 disabled:bg-slate-100 disabled:text-slate-400"
                        >
                            <option value="">{{ filterForm.area_id ? '-- Semua Machine --' : 'Pilih area dulu' }}</option>
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
                            class="w-full py-2 px-3 border border-slate-300 rounded-lg text-xs focus:ring-blue-500 focus:border-blue-500"
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
                            class="w-full py-2 px-3 border border-slate-300 rounded-lg text-xs focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <!-- Buttons -->
                    <div class="lg:col-span-2 flex items-center gap-2">
                        <PrimaryButton
                            type="button"
                            @click="applyFilters"
                            :disabled="isLoading"
                            class="flex-1 justify-center text-xs py-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-60"
                        >
                            <svg v-if="isLoading" class="animate-spin -ml-1 mr-1.5 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ isLoading ? 'Memuat...' : 'Terapkan' }}
                        </PrimaryButton>
                        <SecondaryButton
                            type="button"
                            @click="resetFilters"
                            :disabled="isLoading"
                            class="text-xs py-2 disabled:opacity-60"
                            title="Reset Filter"
                        >
                            Reset
                        </SecondaryButton>
                    </div>
                </div>
            </div>

            <!-- Loading indicator bar -->
            <div v-if="isLoading" class="w-full bg-slate-100 h-1 rounded-full overflow-hidden">
                <div class="bg-blue-600 h-full animate-pulse w-full"></div>
            </div>

            <div
                :class="{ 'opacity-60 pointer-events-none transition-opacity duration-200': isLoading }"
                class="space-y-6"
            >
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Total Qty -->
            <div class="bg-white rounded-lg border border-slate-200 px-5 py-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                            Total Qty Terpakai
                        </p>

                        <p class="text-2xl font-bold text-slate-900 mt-2 tracking-tight">
                            {{ formatNumber(summary.total_qty) }}
                        </p>

                        <p class="text-[11px] text-slate-400 mt-1">
                            Unit sparepart terpakai
                        </p>
                    </div>

                    <div class="text-slate-300">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="w-5 h-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.7"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                            />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Amount -->
            <div class="bg-white rounded-lg border border-slate-200 px-5 py-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                            Total Nilai Pemakaian
                        </p>

                        <p class="text-2xl font-bold text-slate-900 mt-2 tracking-tight">
                            {{ formatRupiah(summary.total_amount) }}
                        </p>

                        <p class="text-[11px] text-slate-400 mt-1">
                            Estimasi nilai sparepart
                        </p>
                    </div>

                    <div class="text-slate-300">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="w-5 h-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.7"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Transactions -->
            <div class="bg-white rounded-lg border border-slate-200 px-5 py-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                            Total Transaksi
                        </p>

                        <p class="text-2xl font-bold text-slate-900 mt-2 tracking-tight">
                            {{ formatNumber(summary.total_transactions) }}
                        </p>

                        <p class="text-[11px] text-slate-400 mt-1">
                            Rekaman konsumsi tercatat
                        </p>
                    </div>

                    <div class="text-slate-300">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="w-5 h-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.7"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                            />
                        </svg>
                    </div>
                </div>
            </div>
        </div>


            <!-- 3 & 4. Charts: Tren Harian (2/3) & Konsumsi per Area (1/3) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Line chart: col-span-2 (2/3 lebar) -->
                <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">
                                Tren Konsumsi Harian
                            </h3>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Pergerakan nominal biaya pemakaian sparepart per tanggal (klik titik untuk melihat laporan detail).
                            </p>
                        </div>
                    </div>

                    <div class="h-64 w-full">
                        <Line
                            v-if="dailyTrend.length > 0"
                            :data="chartConfig.data"
                            :options="chartConfig.options"
                        />
                        <div
                            v-else
                            class="h-full flex flex-col items-center justify-center text-slate-400 text-xs"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                            </svg>
                            Tidak ada data konsumsi pada rentang filter ini.
                        </div>
                    </div>
                </div>

                <!-- Bar chart: col-span-1 (1/3 lebar) -->
                <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">Konsumsi Area</h3>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Total nominal berdasarkan part mapping FA, SMT, & Common (klik bar atau tombol untuk lihat detail).
                                </p>
                            </div>
                        </div>

                        <div class="h-64 w-full">
                            <Bar
                                v-if="((areaConsumption?.fa || 0) + (areaConsumption?.smt || 0) + (areaConsumption?.common || 0)) > 0"
                                :data="barChartConfig.data"
                                :options="barChartConfig.options"
                                ref="barChartRef"
                            />
                            <div
                                v-else
                                class="h-full flex flex-col items-center justify-center text-slate-400 text-xs"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                                </svg>
                                Tidak ada data konsumsi pada rentang ini.
                            </div>
                        </div>
                    </div>

                    <!-- Mini Summary per Area (Clickable to open Drill-down Modal) -->
                    <div class="mt-4 pt-3 border-t border-slate-100 grid grid-cols-3 gap-2 text-center">
                        <button
                            type="button"
                            @click="openCategoryDrillDown('fa')"
                            class="p-1.5 rounded-lg bg-blue-50/60 hover:bg-blue-100/80 border border-blue-100/80 hover:border-blue-300 transition-all cursor-pointer text-center group"
                            title="Klik untuk lihat transaksi FA"
                        >
                            <div class="flex items-center justify-center gap-1 mb-0.5">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                <span class="text-[10px] font-bold text-blue-700">FA</span>
                            </div>
                            <span class="block text-xs font-bold text-slate-800 truncate group-hover:text-blue-900" :title="formatRupiah(areaConsumption?.fa || 0)">
                                {{ formatShortRupiah(areaConsumption?.fa || 0) }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="openCategoryDrillDown('smt')"
                            class="p-1.5 rounded-lg bg-emerald-50/60 hover:bg-emerald-100/80 border border-emerald-100/80 hover:border-emerald-300 transition-all cursor-pointer text-center group"
                            title="Klik untuk lihat transaksi SMT"
                        >
                            <div class="flex items-center justify-center gap-1 mb-0.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span class="text-[10px] font-bold text-emerald-700">SMT</span>
                            </div>
                            <span class="block text-xs font-bold text-slate-800 truncate group-hover:text-emerald-900" :title="formatRupiah(areaConsumption?.smt || 0)">
                                {{ formatShortRupiah(areaConsumption?.smt || 0) }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="openCategoryDrillDown('common')"
                            class="p-1.5 rounded-lg bg-purple-50/60 hover:bg-purple-100/80 border border-purple-100/80 hover:border-purple-300 transition-all cursor-pointer text-center group"
                            title="Klik untuk lihat transaksi Common"
                        >
                            <div class="flex items-center justify-center gap-1 mb-0.5">
                                <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                                <span class="text-[10px] font-bold text-purple-700">Common</span>
                            </div>
                            <span class="block text-xs font-bold text-slate-800 truncate group-hover:text-purple-900" :title="formatRupiah(areaConsumption?.common || 0)">
                                {{ formatShortRupiah(areaConsumption?.common || 0) }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 5. Top 10 Konsumsi per Part Number -->
            <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
                <!-- Header -->
                <div class="px-5 py-4 border-b border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">
                                Top 10 Konsumsi per Part Number
                            </h3>
                            <p class="text-[11px] text-slate-400 mt-1">
                                Berdasarkan total nominal nilai pemakaian (amount) sparepart.
                            </p>
                        </div>

                        <!-- Sort Control -->
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-medium text-slate-400">
                                Urutkan
                            </span>

                            <div class="inline-flex rounded-md border border-slate-200 bg-slate-50 p-0.5">
                                <button
                                    type="button"
                                    @click="topConsumeSort = 'desc'"
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded text-[11px] font-semibold transition-colors',
                                        topConsumeSort === 'desc'
                                            ? 'bg-white text-slate-900 shadow-sm border border-slate-200'
                                            : 'text-slate-500 hover:text-slate-800'
                                    ]"
                                    title="Nominal terbesar ke terkecil"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="w-3.5 h-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M3 7h18M3 12h12M3 17h6"
                                        />
                                    </svg>
                                    Terbesar
                                </button>

                                <button
                                    type="button"
                                    @click="topConsumeSort = 'asc'"
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded text-[11px] font-semibold transition-colors',
                                        topConsumeSort === 'asc'
                                            ? 'bg-white text-slate-900 shadow-sm border border-slate-200'
                                            : 'text-slate-500 hover:text-slate-800'
                                    ]"
                                    title="Nominal terkecil ke terbesar"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="w-3.5 h-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M3 7h6M3 12h12M3 17h18"
                                        />
                                    </svg>
                                    Terkecil
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/70">
                                <th
                                    scope="col"
                                    class="px-5 py-3 text-center text-[10px] font-bold text-slate-400 uppercase tracking-wider w-14"
                                >
                                    No.
                                </th>

                                <th
                                    scope="col"
                                    class="px-4 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider"
                                >
                                    PN BAAN
                                </th>

                                <th
                                    scope="col"
                                    class="px-4 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider"
                                >
                                    Deskripsi
                                </th>

                                <th
                                    scope="col"
                                    class="px-4 py-3 text-center text-[10px] font-bold text-slate-400 uppercase tracking-wider"
                                >
                                    Frekuensi
                                </th>

                                <th
                                    scope="col"
                                    class="px-4 py-3 text-right text-[10px] font-bold text-slate-400 uppercase tracking-wider"
                                >
                                    Total Qty
                                </th>

                                <th
                                    scope="col"
                                    class="px-5 py-3 text-right text-[10px] font-bold text-slate-400 uppercase tracking-wider"
                                >
                                    Total Amount
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <!-- Empty -->
                            <tr v-if="sortedTopConsumes.length === 0">
                                <td
                                    colspan="6"
                                    class="px-5 py-12 text-center"
                                >
                                    <div class="flex flex-col items-center">
                                        <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center mb-2">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="w-4 h-4 text-slate-400"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="1.7"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                                                />
                                            </svg>
                                        </div>

                                        <p class="text-xs font-medium text-slate-500">
                                            Tidak ada data konsumsi
                                        </p>

                                        <p class="text-[11px] text-slate-400 mt-0.5">
                                            Coba ubah periode atau filter yang digunakan.
                                        </p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Rows -->
                            <tr
                                v-for="(item, idx) in sortedTopConsumes"
                                :key="item.part_number_id"
                                @click="openPartDrillDown(item)"
                                class="group hover:bg-slate-50/70 transition-colors cursor-pointer"
                                title="Klik untuk lihat detail"
                            >
                                <!-- Nomor Urut (Plain, tanpa badge warna) -->
                                <td class="px-5 py-3 text-center">
                                    <span class="text-xs font-semibold text-slate-500 tabular-nums">
                                        {{ idx + 1 }}
                                    </span>
                                </td>

                                <!-- PN (Badge Mono) -->
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded font-mono text-xs font-semibold bg-slate-100 text-slate-800 border border-slate-200/80">
                                        {{ item.pn_baan }}
                                    </span>
                                </td>

                                <!-- Description -->
                                <td
                                    class="px-4 py-3 max-w-sm"
                                    :title="item.description"
                                >
                                    <span class="block truncate text-xs text-slate-600">
                                        {{ item.description || '-' }}
                                    </span>
                                </td>

                                <!-- Frequency -->
                                <td class="px-4 py-3 text-center">
                                    <span class="text-xs text-slate-500">
                                        {{ formatNumber(item.count) }}
                                        <span class="text-slate-400">×</span>
                                    </span>
                                </td>

                                <!-- Quantity -->
                                <td class="px-4 py-3 text-right">
                                    <span class="text-sm font-bold text-slate-900 tabular-nums">
                                        {{ formatNumber(item.total_qty) }}
                                    </span>
                                </td>

                                <!-- Amount -->
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <span class="text-xs font-semibold text-slate-700 tabular-nums">
                                        {{ formatRupiah(item.total_amount) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer -->
                <div
                    v-if="sortedTopConsumes.length > 0"
                    class="px-5 py-3 border-t border-slate-100 bg-slate-50/40"
                >
                    <p class="text-[10px] text-slate-400">
                        Menampilkan {{ sortedTopConsumes.length }} part dengan nilai pemakaian tertinggi
                        berdasarkan filter yang dipilih.
                    </p>


                </div>
            </div>

            </div>
        </div>

        <!-- Drill-down Modal -->
        <Teleport to="body">
            <div
                v-if="drillDown.show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="closeDrillDown"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeDrillDown" />

                <!-- Modal -->
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] flex flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">Detail Konsumsi</h3>
                            <p class="text-xs text-slate-500 mt-0.5 font-mono">{{ drillDown.title }}</p>
                        </div>
                        <button
                            @click="closeDrillDown"
                            class="p-2 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition"
                        >
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Summary -->
                    <div class="px-6 py-3 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex flex-wrap gap-6">
                            <div>
                                <span class="text-xs text-slate-500">Total Qty:</span>
                                <span class="text-xs font-bold text-slate-900 ml-1">{{ formatNumber(drillDown.total_qty) }}</span>
                            </div>
                            <div>
                                <span class="text-xs text-slate-500">Total Nominal:</span>
                                <span class="text-xs font-bold text-slate-900 ml-1">{{ formatRupiah(drillDown.total_amount) }}</span>
                            </div>
                        </div>
                        <div class="text-xs text-slate-500 font-medium">
                            Total <span class="font-bold text-slate-800">{{ drillDown.total }}</span> transaksi
                        </div>
                    </div>

                    <!-- Loading -->
                    <div v-if="drillDown.loading" class="flex-1 flex items-center justify-center py-12">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900"></div>
                    </div>

                    <!-- Table -->
                    <div v-else class="flex-1 overflow-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Tanggal</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">PN BAAN</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Deskripsi</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Area</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Machine</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Qty</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Nominal</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Source</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="drillDown.rows.length === 0">
                                    <td colspan="8" class="px-4 py-8 text-center text-xs text-slate-400">
                                        Tidak ada data transaksi.
                                    </td>
                                </tr>
                                <tr
                                    v-for="(row, i) in drillDown.rows"
                                    :key="i"
                                    class="hover:bg-slate-50 transition-colors"
                                >
                                    <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">{{ row.date }}</td>
                                    <td class="px-4 py-3 text-xs font-mono font-bold text-blue-700">{{ row.pn_baan }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-700 max-w-xs truncate" :title="row.description">{{ row.description }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-600">{{ row.area ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-600">{{ row.machine ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs text-right font-bold text-slate-900">{{ formatNumber(row.qty) }}</td>
                                    <td class="px-4 py-3 text-xs text-right text-slate-700 whitespace-nowrap">{{ formatRupiah(row.amount) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            :class="{
                                                'bg-green-100 text-green-800': row.source === 'manual',
                                                'bg-blue-100 text-blue-800': row.source === 'import_excel',
                                                'bg-purple-100 text-purple-800': row.source === 'api',
                                            }"
                                            class="text-xs px-2 py-0.5 rounded-full font-medium capitalize"
                                        >
                                            {{ row.source === 'import_excel' ? 'Import' : row.source === 'api' ? 'API Sync' : 'Manual' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Footer -->
                    <div
                        v-if="drillDown.last_page > 1"
                        class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between"
                    >
                        <p class="text-xs text-slate-500">
                            Menampilkan <span class="font-medium text-slate-800">{{ drillDown.from || 0 }}</span> - <span class="font-medium text-slate-800">{{ drillDown.to || 0 }}</span> dari <span class="font-medium text-slate-800">{{ drillDown.total }}</span> transaksi
                        </p>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="fetchDrillDown(drillDown.type, drillDown.id, drillDown.page - 1)"
                                :disabled="drillDown.page <= 1 || drillDown.loading"
                                class="px-3 py-1.5 text-xs font-medium border border-slate-300 rounded-md bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            >
                                Sebelumnya
                            </button>
                            <span class="text-xs text-slate-600 font-medium px-1">
                                {{ drillDown.page }} / {{ drillDown.last_page }}
                            </span>
                            <button
                                type="button"
                                @click="fetchDrillDown(drillDown.type, drillDown.id, drillDown.page + 1)"
                                :disabled="drillDown.page >= drillDown.last_page || drillDown.loading"
                                class="px-3 py-1.5 text-xs font-medium border border-slate-300 rounded-md bg-white text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            >
                                Selanjutnya
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

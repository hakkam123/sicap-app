<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// Chart.js & vue-chartjs integration
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';
import { Line } from 'vue-chartjs';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
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
    topConsumes: {
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

const applyFilters = () => {
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
    router.get(route('dashboard'), {}, { preserveState: false });
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

// Chart Data & Options (Facing upwards with positive Qty)
const chartConfig = computed(() => {
    const labels = props.chartData.map((d) => d.date);
    const dataQty = props.chartData.map((d) => Math.abs(d.qty));

    return {
        data: {
            labels,
            datasets: [
                {
                    label: 'Qty Terpakai',
                    backgroundColor: 'rgba(59, 130, 246, 0.12)',
                    borderColor: '#2563eb',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#1d4ed8',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#1d4ed8',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.3,
                    data: dataQty,
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
                            const item = props.chartData[context.dataIndex];
                            const qtyStr = `Qty Terpakai: ${formatNumber(item.qty)}`;
                            const amtStr = item.amount ? ` (Nominal: ${formatRupiah(item.amount)})` : '';
                            return `${qtyStr}${amtStr}`;
                        },
                    },
                },
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
                        text: 'Qty Terpakai',
                        font: { size: 11, weight: '600' },
                        color: '#64748b',
                    },
                    grid: { color: 'rgba(226, 232, 240, 0.8)' },
                    ticks: {
                        font: { size: 11 },
                        callback: function(value) {
                            return Math.abs(value);
                        },
                    },
                },
            },
        },
    };
});
</script>

<template>
    <AppLayout>
        <Head title="Dashboard" />

        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800">
                        Dashboard
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Ringkasan operasional konsumsi, grafik tren harian, dan analisis part paling sering digunakan.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">

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
                            class="flex-1 justify-center text-xs py-2 bg-slate-900 hover:bg-slate-800"
                        >
                            Terapkan
                        </PrimaryButton>
                        <SecondaryButton
                            type="button"
                            @click="resetFilters"
                            class="text-xs py-2"
                            title="Reset Filter"
                        >
                            Reset
                        </SecondaryButton>
                    </div>
                </div>
            </div>

            <!-- 2. Summary Metric Cards (Positive Values) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Total Qty Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center gap-4 border-l-4 border-blue-500">
                    <div class="p-3 rounded-xl bg-blue-50 text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Total Qty Terpakai
                        </p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">
                            {{ formatNumber(summary.total_qty) }}
                        </p>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            Total akumulasi unit sparepart yang terpakai
                        </p>
                    </div>
                </div>

                <!-- Total Amount Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center gap-4 border-l-4 border-emerald-500">
                    <div class="p-3 rounded-xl bg-emerald-50 text-emerald-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Total Nilai Pemakaian (Rp)
                        </p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">
                            {{ formatRupiah(summary.total_amount) }}
                        </p>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            Estimasi nilai pemakaian sparepart
                        </p>
                    </div>
                </div>

                <!-- Total Transactions Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center gap-4 border-l-4 border-purple-500">
                    <div class="p-3 rounded-xl bg-purple-50 text-purple-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                            Total Transaksi
                        </p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">
                            {{ formatNumber(summary.total_transactions) }}
                        </p>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            Rekaman konsumsi tercatat
                        </p>
                    </div>
                </div>
            </div>

            <!-- 3. Line Chart (Facing Upwards) -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">
                            Tren Konsumsi Harian
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Pergerakan kuantitas sparepart terpakai per tanggal (grafik menghadap ke atas).
                        </p>
                    </div>
                </div>

                <div class="h-72 w-full">
                    <Line
                        v-if="chartData.length > 0"
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

            <!-- 4. Top 10 Consume per Part Number (Positive Qty) -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="mb-4">
                    <h3 class="text-sm font-bold text-slate-800">
                        Top 10 Konsumsi Terbanyak per Part Number
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Daftar sparepart dengan jumlah pemakaian (kuantitas unit) paling tinggi.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-12">
                                    No
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">
                                    PN BAAN
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">
                                    Deskripsi
                                </th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">
                                    Frekuensi
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">
                                    Total Qty Terpakai
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">
                                    Total Nominal (Rp)
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <tr v-if="topConsumes.length === 0">
                                <td colspan="6" class="px-4 py-6 text-center text-slate-400 text-xs">
                                    Tidak ada data peringkat konsumsi pada filter ini.
                                </td>
                            </tr>
                            <tr
                                v-for="(item, idx) in topConsumes"
                                :key="item.part_number_id"
                                class="hover:bg-slate-50 transition-colors"
                            >
                                <td class="px-4 py-3 text-xs text-slate-500 font-medium">
                                    {{ idx + 1 }}
                                </td>
                                <td class="px-4 py-3 text-xs font-bold text-blue-700 font-mono">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-800 border border-blue-200">
                                        {{ item.pn_baan }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-700 max-w-sm truncate" :title="item.description">
                                    {{ item.description }}
                                </td>
                                <td class="px-4 py-3 text-xs text-center text-slate-600 font-medium">
                                    {{ item.count }} kali
                                </td>
                                <td class="px-4 py-3 text-xs text-right font-bold text-slate-900">
                                    {{ formatNumber(item.total_qty) }}
                                </td>
                                <td class="px-4 py-3 text-xs text-right font-semibold text-slate-800 whitespace-nowrap">
                                    {{ formatRupiah(item.total_amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

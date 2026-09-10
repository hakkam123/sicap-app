<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import ConfirmModal from '@/Components/UI/ConfirmModal.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { 
    Search, 
    RotateCcw, 
    Plus, 
    RefreshCw, 
    AlertCircle, 
    X,
    Clock,
    Trash2,
    ChevronDown,
    ChevronUp,
    Check,
    Info
} from 'lucide-vue-next';

const props = defineProps({
    consumes: {
        type: Object,
        required: true,
    },
    areas: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            area_id: '',
            machine_id: '',
            date_from: '',
            date_to: '',
            per_page: 10,
        }),
    },
    importLogs: {
        type: Array,
        default: () => [],
    },
    partNumbers: {
        type: Array,
        default: () => [],
    },
    lastSyncAt: {
        type: String,
        default: null,
    },
    syncSchedules: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const isAdmin = computed(() => {
    const user = page.props.auth?.user;
    return user?.role === 'admin' || user?.is_admin === true;
});

// ==========================================
// 1. FILTER STATE & ACTIONS
// ==========================================
const filters = ref({
    search: props.filters.search || '',
    area_id: props.filters.area_id || '',
    machine_id: props.filters.machine_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    per_page: props.filters.per_page || 10,
});

const machineOptions = ref([]);

const loadMachinesForArea = async (areaId) => {
    if (!areaId) {
        machineOptions.value = [];
        filters.value.machine_id = '';
        return;
    }
    try {
        const res = await fetch(`/consume/machines-by-area?area_id=${areaId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });
        const data = await res.json();
        machineOptions.value = data;
    } catch (e) {
        machineOptions.value = [];
    }
};

// Initial load for machines if area_id is set
if (props.filters.area_id) {
    loadMachinesForArea(props.filters.area_id);
}

const handleAreaFilterChange = () => {
    filters.value.machine_id = '';
    loadMachinesForArea(filters.value.area_id);
};

const applyFilters = () => {
    router.get(
        route('consume.index'),
        {
            search: filters.value.search || '',
            area_id: filters.value.area_id || '',
            machine_id: filters.value.machine_id || '',
            date_from: filters.value.date_from || '',
            date_to: filters.value.date_to || '',
            per_page: filters.value.per_page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const resetFilters = () => {
    filters.value.search = '';
    filters.value.area_id = '';
    filters.value.machine_id = '';
    filters.value.date_from = '';
    filters.value.date_to = '';
    filters.value.per_page = 10;
    machineOptions.value = [];
    applyFilters();
};

const reloadData = () => {
    router.reload({ preserveScroll: true });
};

// ==========================================
// 2. TABLE COLUMNS & FORMATTERS
// ==========================================
const tableColumns = [
    { key: 'consumed_at', label: 'TANGGAL' },
    { key: 'part_number.pn_baan', label: 'PN BAAN' },
    { key: 'part_number.description', label: 'DESKRIPSI' },
    { key: 'area.name', label: 'AREA' },
    { key: 'machine.name', label: 'MACHINE' },
    { key: 'quantity', label: 'QTY' },
    { key: 'amount', label: 'AMOUNT' },
];

const formatRupiah = (val) => {
    if (val === null || val === undefined || val === '') return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Math.abs(Number(val)));
};

const formatDate = (isoString) => {
    if (!isoString) return '-';
    const d = new Date(isoString);
    return d.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatDateTime = (isoString) => {
    if (!isoString) return '-';
    const d = new Date(isoString);
    return d.toLocaleString('id-ID', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

// ==========================================
// 3. DELETE CONFIRMATION MODAL
// ==========================================
const confirmingConsumeDeletion = ref(false);
const consumeToDelete = ref(null);
const isDeletingConsume = ref(false);

const confirmDelete = (consume) => {
    consumeToDelete.value = consume;
    confirmingConsumeDeletion.value = true;
};

const closeDeleteModal = () => {
    confirmingConsumeDeletion.value = false;
    consumeToDelete.value = null;
};

const deleteConsume = () => {
    if (consumeToDelete.value) {
        isDeletingConsume.value = true;
        router.delete(route('consume.destroy', consumeToDelete.value.id), {
            onFinish: () => {
                isDeletingConsume.value = false;
                closeDeleteModal();
            },
        });
    }
};

// ==========================================
// 4. MODAL INPUT & EDIT MANUAL
// ==========================================
const isManualModalOpen = ref(false);
const editingConsumeId = ref(null);
const isEditing = computed(() => !!editingConsumeId.value);
const manualSearchQuery = ref('');
const isPartDropdownOpen = ref(false);
const manualMachines = ref([]);

const manualForm = useForm({
    part_number_id: '',
    area_id: '',
    machine_id: '',
    quantity: 1,
    amount: '',
    consumed_at: new Date().toISOString().split('T')[0],
});

const filteredPartNumbers = computed(() => {
    const q = manualSearchQuery.value.trim().toLowerCase();
    if (!q) return props.partNumbers.slice(0, 30);
    return props.partNumbers.filter((p) =>
        p.pn_baan.toLowerCase().includes(q) ||
        (p.description && p.description.toLowerCase().includes(q))
    ).slice(0, 30);
});

const selectedPart = computed(() => {
    return props.partNumbers.find(p => p.id === manualForm.part_number_id);
});

const selectPart = (part) => {
    manualForm.part_number_id = part.id;
    manualSearchQuery.value = part.pn_baan;
    isPartDropdownOpen.value = false;
};

const handleManualAreaChange = async () => {
    manualForm.machine_id = '';
    manualMachines.value = [];
    if (manualForm.area_id) {
        try {
            const res = await fetch(`/consume/machines-by-area?area_id=${manualForm.area_id}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            manualMachines.value = await res.json();
        } catch (e) {
            manualMachines.value = [];
        }
    }
};

const openManualModal = () => {
    editingConsumeId.value = null;
    manualForm.reset();
    manualForm.clearErrors();
    manualForm.quantity = 1;
    manualForm.amount = '';
    manualForm.consumed_at = new Date().toISOString().split('T')[0];
    manualSearchQuery.value = '';
    manualMachines.value = [];
    isManualModalOpen.value = true;
};

const openEditModal = async (item) => {
    editingConsumeId.value = item.id;
    manualForm.clearErrors();
    manualForm.part_number_id = item.part_number_id;
    manualForm.area_id = item.area_id || '';
    manualForm.machine_id = item.machine_id || '';
    manualForm.quantity = Math.abs(Number(item.quantity)) || 1;
    manualForm.amount = item.amount !== null && item.amount !== undefined ? Math.abs(Number(item.amount)) : '';
    manualForm.consumed_at = item.consumed_at ? item.consumed_at.split('T')[0] : new Date().toISOString().split('T')[0];

    const part = props.partNumbers.find(p => p.id === item.part_number_id) || item.part_number;
    if (part) {
        manualSearchQuery.value = `${part.pn_baan} - ${part.description || ''}`;
    } else {
        manualSearchQuery.value = '';
    }

    if (item.area_id) {
        try {
            const res = await fetch(`/consume/machines-by-area?area_id=${item.area_id}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            manualMachines.value = await res.json();
        } catch (e) {
            manualMachines.value = [];
        }
    } else {
        manualMachines.value = [];
    }

    isManualModalOpen.value = true;
};

const closeManualModal = () => {
    isManualModalOpen.value = false;
    editingConsumeId.value = null;
    manualForm.clearErrors();
};

const submitManualForm = () => {
    if (isEditing.value) {
        manualForm.put(route('consume.update', editingConsumeId.value), {
            preserveScroll: true,
            onSuccess: () => {
                closeManualModal();
            },
        });
    } else {
        manualForm.post(route('consume.store'), {
            preserveScroll: true,
            onSuccess: () => {
                closeManualModal();
            },
        });
    }
};

import { useImportPolling } from '@/composables/useImportPolling';
import { useExportWithToast } from '@/composables/useExportWithToast';

// ==========================================
// 5. MODAL IMPORT EXCEL
// ==========================================
const isImportModalOpen = ref(false);
const importFile = ref(null);
const importInputRef = ref(null);

const { isUploading, importErrors, startImport } = useImportPolling();
const { isExporting, download: downloadExport } = useExportWithToast();

const openImportModal = () => {
    importFile.value = null;
    importErrors.value = [];
    if (importInputRef.value) importInputRef.value.value = '';
    isImportModalOpen.value = true;
};

const closeImportModal = () => {
    if (isUploading.value) return;
    isImportModalOpen.value = false;
    importFile.value = null;
    importErrors.value = [];
    if (importInputRef.value) importInputRef.value.value = '';
};

const handleImportFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        importFile.value = file;
        importErrors.value = [];
    }
};

const submitImportForm = () => {
    if (!importFile.value || isUploading.value) return;

    startImport({
        url: route('consume.import'),
        file: importFile.value,
        title: 'Import Data Consume',
        onSuccess: () => {
            closeImportModal();
            router.reload({ preserveScroll: true });
        },
    });
};

// ==========================================
// 6. EXPORT EXCEL WITH PROGRESS TOAST
// ==========================================
const exportExcel = () => {
    const params = new URLSearchParams();
    if (filters.value.search) params.append('search', filters.value.search);
    if (filters.value.area_id) params.append('area_id', filters.value.area_id);
    if (filters.value.machine_id) params.append('machine_id', filters.value.machine_id);
    if (filters.value.date_from) params.append('date_from', filters.value.date_from);
    if (filters.value.date_to) params.append('date_to', filters.value.date_to);

    const url = `${route('consume.export')}?${params.toString()}`;
    const filename = `consume_report_${new Date().toISOString().slice(0, 10)}.xlsx`;

    downloadExport({
        url,
        filename,
        title: 'Ekspor Data Consume',
        loadingMessage: 'Menyiapkan file ekspor data konsumsi...',
        successMessage: 'File ekspor data consume berhasil diunduh.',
    });
};

// ==========================================
// 7. MODAL SINKRONISASI DATA & JADWAL OTOMATIS
// ==========================================
const isApiModalOpen = ref(false);
const isSyncing = ref(false);
const isSavingSchedule = ref(false);
const showAdvancedSettings = ref(false);

const scheduleList = ref([]);
const newScheduleTime = ref('');

const openApiModal = () => {
    scheduleList.value = (props.syncSchedules && props.syncSchedules.length > 0)
        ? props.syncSchedules.map(s => ({
            id: s.id,
            time: s.time,
            is_active: s.is_active ?? true,
        }))
        : [
            { time: '05:00', is_active: true },
            { time: '11:00', is_active: true },
            { time: '17:00', is_active: true },
        ];
    newScheduleTime.value = '';
    isApiModalOpen.value = true;
};

const closeApiModal = () => {
    if (!isSyncing.value && !isSavingSchedule.value) {
        isApiModalOpen.value = false;
    }
};

const triggerManualSync = () => {
    isSyncing.value = true;
    router.post(route('consume.sync-api'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isSyncing.value = false;
            isApiModalOpen.value = false;
        },
    });
};

const addScheduleTime = () => {
    if (!newScheduleTime.value) return;
    const timeVal = newScheduleTime.value.trim();
    if (!scheduleList.value.some(s => s.time === timeVal)) {
        scheduleList.value.push({
            time: timeVal,
            is_active: true,
        });
        scheduleList.value.sort((a, b) => a.time.localeCompare(b.time));
    }
    newScheduleTime.value = '';
};

const removeScheduleTime = (index) => {
    scheduleList.value.splice(index, 1);
};

const saveSchedules = () => {
    isSavingSchedule.value = true;
    router.post(route('consume.sync-schedules.update'), {
        schedules: scheduleList.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSavingSchedule.value = false;
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Data Consume Sparepart" />

        <template #header>
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-800">
                    Data Consume Sparepart
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Rekaman seluruh konsumsi sparepart, filter multi-kriteria, dan aksi terintegrasi.
                </p>
            </div>
        </template>

        <div class="p-6 space-y-4">
            <!-- ACTION & INFO BAR (di atas filter card) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <!-- Floating Last Sync Info Badge -->
                <div class="flex items-center gap-2">
                    <div
                        v-if="lastSyncAt"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100/90 border border-slate-200/90 text-slate-700 text-xs shadow-2xs select-none"
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

                <!-- ACTION BUTTONS -->
                <div class="flex items-center justify-end gap-2 flex-wrap">
                    <button
                        type="button"
                        @click="reloadData"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 border border-slate-200 bg-white text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition shadow-2xs cursor-pointer"
                        title="Muat ulang data"
                    >
                        <RefreshCw class="w-3.5 h-3.5 text-slate-500" />
                        Refresh
                    </button>
                    <button
                        type="button"
                        @click="openApiModal"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition shadow-sm cursor-pointer"
                        title="Integrasi API"
                    >
                        Sync API
                    </button>

                    <button
                        v-if="isAdmin"
                        type="button"
                        @click="openImportModal"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 border border-emerald-600 bg-emerald-600 text-xs font-semibold text-white rounded-lg hover:bg-emerald-700 transition shadow-2xs cursor-pointer"
                    >
                        Import Excel
                    </button>

                    <button
                        type="button"
                        @click="exportExcel"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 border border-emerald-600 bg-white text-emerald-700 text-xs font-semibold rounded-lg hover:bg-emerald-50 transition shadow-2xs cursor-pointer"
                        title="Export data konsumsi sesuai filter aktif ke file Excel"
                    >
                        Export Excel
                    </button>

                    <button
                        type="button"
                        @click="openManualModal"
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-900 text-white text-xs font-semibold rounded-lg hover:bg-slate-800 transition shadow-2xs cursor-pointer"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        Tambah Manual
                    </button>
                </div>
            </div>

            <!-- FILTER CARD -->
            <div class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border border-gray-200">
                <div class="flex flex-wrap items-end gap-2">

                    <!-- Field Search -->
                    <div class="flex flex-col gap-0.5 flex-1 min-w-[200px]">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            Pencarian
                        </label>

                        <div class="relative">
                            <Search
                                class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400"
                            />

                            <input
                                v-model="filters.search"
                                type="text"
                                placeholder="Ketik PN BAAN atau nama part..."
                                class="w-full pl-8 pr-3 py-1.5 border border-gray-200 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
                                @keydown.enter="applyFilters"
                            />
                        </div>
                    </div>

                    <!-- Field Area -->
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            Area
                        </label>

                        <select
                            v-model="filters.area_id"
                            @change="handleAreaFilterChange"
                            class="px-2.5 py-1.5 border border-gray-200 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900 min-w-28"
                        >
                            <option value="">-- Semua Area --</option>

                            <option
                                v-for="area in areas"
                                :key="area.id"
                                :value="area.id"
                            >
                                {{ area.name }} ({{ area.code }})
                            </option>
                        </select>
                    </div>

                    <!-- Field Machine -->
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            Machine
                        </label>

                        <select
                            v-model="filters.machine_id"
                            :disabled="!filters.area_id || machineOptions.length === 0"
                            class="px-2.5 py-1.5 border border-gray-200 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900 min-w-28 disabled:bg-gray-100 disabled:text-gray-400"
                        >
                            <option value="">
                                {{ filters.area_id ? '-- Semua Machine --' : 'Pilih area dulu' }}
                            </option>

                            <option
                                v-for="m in machineOptions"
                                :key="m.id"
                                :value="m.id"
                            >
                                {{ m.name }} ({{ m.code }})
                            </option>
                        </select>
                    </div>

                    <!-- Field Date From -->
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            Dari Tanggal
                        </label>

                        <input
                            v-model="filters.date_from"
                            type="date"
                            class="px-2.5 py-1.5 border border-gray-200 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900"
                        />
                    </div>

                    <!-- Field Date To -->
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            Sampai Tanggal
                        </label>

                        <input
                            v-model="filters.date_to"
                            type="date"
                            class="px-2.5 py-1.5 border border-gray-200 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900"
                        />
                    </div>

                    <!-- Field Per Page -->
                    <div class="flex flex-col gap-0.5">
                        <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            Tampilkan
                        </label>

                        <select
                            v-model.number="filters.per_page"
                            @change="applyFilters"
                            class="px-2.5 py-1.5 border border-gray-200 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900"
                        >
                            <option :value="5">5</option>
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="100">100</option>
                        </select>
                    </div>

                    <!-- Tombol Cari + Reset -->
                    <div class="flex items-end gap-1">
                        <button
                            type="button"
                            @click="applyFilters"
                            class="px-3 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-md hover:bg-gray-700 transition"
                        >
                            Cari
                        </button>

                        <button
                            type="button"
                            @click="resetFilters"
                            class="p-1.5 border border-gray-200 rounded-md text-gray-500 hover:bg-gray-50 transition"
                            title="Reset filter"
                        >
                            <RotateCcw class="w-3.5 h-3.5" />
                        </button>
                    </div>

                </div>
            </div>


            <!-- DATA TABLE -->
            <DataTable
                :columns="tableColumns"
                :data="consumes"
            >
                <template #cell-consumed_at="{ value }">
                    <span class="whitespace-nowrap font-medium text-slate-700">
                        {{ formatDate(value) }}
                    </span>
                </template>

                <template #cell-part_number\.pn_baan="{ row }">
                    <span class="font-mono font-bold text-blue-700 whitespace-nowrap">
                        {{ row.part_number?.pn_baan || '-' }}
                    </span>
                </template>

                <template #cell-part_number\.description="{ row }">
                    <span class="max-w-xs truncate block text-slate-600" :title="row.part_number?.description">
                        {{ row.part_number?.description || '-' }}
                    </span>
                </template>

                <template #cell-area\.name="{ row }">
                    <span v-if="row.area" class="font-medium text-slate-800">
                        {{ row.area.name }}
                    </span>
                    <span v-else class="text-slate-400 italic text-[11px]">-</span>
                </template>

                <template #cell-machine\.name="{ row }">
                    <span v-if="row.machine" class="font-medium text-slate-800">
                        {{ row.machine.name }}
                    </span>
                    <span v-else class="text-slate-400 italic text-[11px]">-</span>
                </template>

                <template #cell-quantity="{ value }">
                    <span class="font-bold text-slate-900">
                        {{ Math.abs(Number(value)) }}
                    </span>
                </template>

                <template #cell-amount="{ value }">
                    <span class="font-semibold text-slate-900 whitespace-nowrap">
                        {{ formatRupiah(value) }}
                    </span>
                </template>

                <template v-if="isAdmin" #actions="{ row }">
                    <button
                        type="button"
                        @click="openEditModal(row)"
                        class="text-blue-600 hover:text-blue-900 hover:underline font-semibold inline-flex items-center gap-1 text-xs"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        @click="confirmDelete(row)"
                        class="text-red-600 hover:text-red-900 hover:underline font-semibold inline-flex items-center gap-1 text-xs ml-3"
                    >
                        Hapus
                    </button>
                </template>

                <template #empty>
                    <p>Belum ada data transaksi konsumsi ditemukan.</p>
                </template>
            </DataTable>
        </div>

        <!-- Confirm Delete Modal -->
        <ConfirmModal
            :show="confirmingConsumeDeletion"
            title="Konfirmasi Hapus Data Consume"
            :message="`Apakah Anda yakin ingin menghapus data konsumsi untuk ${consumeToDelete?.part_number?.pn_baan} sejumlah ${consumeToDelete?.quantity} unit?`"
            confirm-label="Hapus Consume"
            variant="danger"
            :loading="isDeletingConsume"
            @confirm="deleteConsume"
            @cancel="closeDeleteModal"
        />

        <!-- MODAL INPUT & EDIT MANUAL -->
        <Modal :show="isManualModalOpen" @close="closeManualModal">
            <div class="p-6 sm:p-8 space-y-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            {{ isEditing ? 'Edit Data Transaksi Consume' : 'Input Manual Transaksi Consume' }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ isEditing ? 'Perbarui informasi transaksi penggunaan sparepart yang sudah tercatat.' : 'Catat pengeluaran sparepart baru ke dalam sistem.' }}
                        </p>
                    </div>
                    <button @click="closeManualModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitManualForm" class="space-y-4">
                    <!-- Date (Tanggal Pemakaian) -->
                    <div>
                        <InputLabel for="manual_consumed_at" value="Date (Tanggal Pemakaian) *" />
                        <TextInput
                            id="manual_consumed_at"
                            v-model="manualForm.consumed_at"
                            type="date"
                            class="mt-1 block w-full text-xs"
                            required
                        />
                        <InputError class="mt-1" :message="manualForm.errors.consumed_at" />
                    </div>

                    <!-- Part Number Autocomplete / Search -->
                    <div class="relative">
                        <InputLabel for="manual_part_number" value="Part Number (PN BAAN) *" />
                        <TextInput
                            id="manual_part_number"
                            v-model="manualSearchQuery"
                            type="text"
                            class="mt-1 block w-full text-xs font-mono"
                            placeholder="Cari nomor part (PN BAAN)..."
                            @focus="isPartDropdownOpen = true"
                            autocomplete="off"
                            required
                        />
                        <InputError class="mt-1" :message="manualForm.errors.part_number_id" />

                        <!-- Searchable Dropdown List -->
                        <div
                            v-if="isPartDropdownOpen && filteredPartNumbers.length > 0"
                            class="absolute left-0 right-0 z-30 mt-1 max-h-52 overflow-y-auto bg-white border border-slate-300 rounded-lg shadow-xl text-xs"
                        >
                            <div
                                v-for="part in filteredPartNumbers"
                                :key="part.id"
                                @click="selectPart(part)"
                                class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-slate-100 last:border-0"
                            >
                                <div class="font-bold font-mono text-blue-700">{{ part.pn_baan }}</div>
                                <div class="text-[11px] text-slate-500 truncate">{{ part.description || 'Tanpa Deskripsi' }}</div>
                            </div>
                        </div>

                        <!-- Read-only Desc Display -->
                        <div v-if="selectedPart" class="mt-2 p-2.5 bg-slate-50 border border-slate-200 rounded-lg">
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider block">Deskripsi Part (Desc):</span>
                            <span class="text-xs text-slate-800 font-medium">{{ selectedPart.description || '-' }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Quantity -->
                        <div>
                            <InputLabel for="manual_qty" value="Quantity (qty) *" />
                            <TextInput
                                id="manual_qty"
                                v-model="manualForm.quantity"
                                type="number"
                                step="1"
                                class="mt-1 block w-full text-xs"
                                placeholder="Contoh: -4 atau 10"
                                required
                            />
                            <p class="text-[10px] text-slate-400 mt-1">
                                * Boleh bernilai negatif.
                            </p>
                            <InputError class="mt-1" :message="manualForm.errors.quantity" />
                        </div>

                        <!-- Amount -->
                        <div>
                            <InputLabel for="manual_amount" value="Amount (Nominal Rp) *" />
                            <TextInput
                                id="manual_amount"
                                v-model="manualForm.amount"
                                type="number"
                                step="0.01"
                                class="mt-1 block w-full text-xs"
                                placeholder="Contoh: -1386000"
                                required
                            />
                            <p class="text-[10px] text-slate-400 mt-1">
                                * Boleh bernilai negatif.
                            </p>
                            <InputError class="mt-1" :message="manualForm.errors.amount" />
                        </div>
                    </div>

                    <!-- Area Dropdown (Opsional) -->
                    <div class="pt-2 border-t border-slate-100">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="manual_area" value="Area (Opsional)" />
                                <select
                                    id="manual_area"
                                    v-model="manualForm.area_id"
                                    @change="handleManualAreaChange"
                                    class="mt-1 block w-full border-slate-300 rounded-lg text-xs focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option value="">-- Tanpa Area --</option>
                                    <option v-for="a in areas" :key="a.id" :value="a.id">
                                        {{ a.name }} ({{ a.code }})
                                    </option>
                                </select>
                                <InputError class="mt-1" :message="manualForm.errors.area_id" />
                            </div>

                            <!-- Machine Dropdown (Opsional) -->
                            <div>
                                <InputLabel for="manual_machine" value="Machine / Station (Opsional)" />
                                <select
                                    id="manual_machine"
                                    v-model="manualForm.machine_id"
                                    :disabled="!manualForm.area_id || manualMachines.length === 0"
                                    class="mt-1 block w-full border-slate-300 rounded-lg text-xs focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-400"
                                >
                                    <option value="">{{ manualForm.area_id ? '-- Tanpa Machine --' : 'Pilih area terlebih dahulu' }}</option>
                                    <option v-for="m in manualMachines" :key="m.id" :value="m.id">
                                        {{ m.name }} ({{ m.code }})
                                    </option>
                                </select>
                                <InputError class="mt-1" :message="manualForm.errors.machine_id" />
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                        <SecondaryButton type="button" @click="closeManualModal">
                            Batal
                        </SecondaryButton>
                        <PrimaryButton :disabled="manualForm.processing">
                            {{ manualForm.processing ? 'Menyimpan...' : (isEditing ? 'Simpan Perubahan' : 'Simpan') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL IMPORT EXCEL -->
        <Modal :show="isImportModalOpen" @close="closeImportModal" max-width="lg">
            <div class="p-6 sm:p-7 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Import Data Consume dari Excel
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Unggah file Excel untuk mencatat transaksi pemakaian sparepart secara massal.
                        </p>
                    </div>
                    <button @click="closeImportModal" :disabled="isUploading" class="text-slate-400 hover:text-slate-600 p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-3.5 bg-emerald-50/80 rounded-xl border border-emerald-100 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-emerald-900">Format Kolom Excel:</p>
                        <p class="text-[11px] text-emerald-700 font-mono mt-0.5">Date | Part Number | Desc | qty | Amount</p>
                    </div>
                    <a
                        :href="route('consume.template')"
                        class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:text-emerald-900 underline"
                        download
                    >
                        <Download class="w-3.5 h-3.5" />
                        Download Template
                    </a>
                </div>

                <!-- Error Messages Box -->
                <div v-if="importErrors.length > 0" class="p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700 space-y-1.5 max-h-48 overflow-y-auto">
                    <div class="flex items-center gap-1.5 font-bold text-red-800">
                        <AlertCircle class="w-4 h-4 flex-shrink-0" />
                        <span>Terdapat {{ importErrors.length }} baris bermasalah:</span>
                    </div>
                    <ul class="space-y-1 pl-1 text-[11px]">
                        <li v-for="(err, idx) in importErrors" :key="idx" class="flex items-start gap-1.5">
                            <span class="font-bold text-red-900">•</span>
                            <span>{{ typeof err === 'object' && err !== null ? (err.message || JSON.stringify(err)) : err }}</span>
                        </li>
                    </ul>
                </div>

                <form @submit.prevent="submitImportForm" class="space-y-4">
                    <div>
                        <InputLabel for="import_file" value="Pilih File Excel (.xlsx, .xls) *" />
                        <input
                            ref="importInputRef"
                            id="import_file"
                            type="file"
                            accept=".xlsx, .xls"
                            @change="handleImportFileChange"
                            class="mt-1 block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer"
                            required
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <SecondaryButton type="button" @click="closeImportModal" :disabled="isUploading">
                            Batal
                        </SecondaryButton>
                        <button
                            type="submit"
                            :disabled="!importFile || isUploading"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                        >
                            <span>{{ isUploading ? 'Mengimpor...' : 'Import' }}</span>
                        </button>
                    </div>
                </form>

                <!-- Riwayat Import Terakhir -->
                <div v-if="importLogs.length > 0" class="pt-4 border-t border-slate-100 space-y-3">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Riwayat Import Terakhir
                    </h4>
                    <div class="max-h-48 overflow-y-auto space-y-2 pr-1">
                        <div
                            v-for="log in importLogs"
                            :key="log.id"
                            class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs flex items-center justify-between"
                        >
                            <div>
                                <p class="font-bold text-slate-800 font-mono text-[11px]">{{ log.file_name }}</p>
                                <p class="text-[10px] text-slate-400">{{ formatDateTime(log.created_at) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <StatusBadge :value="log.status" type="status" />
                                <span class="text-[11px] font-bold text-slate-700">{{ log.processed_rows || 0 }}/{{ log.total_rows || 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- MODAL SINKRONISASI DATA & JADWAL OTOMATIS -->
        <Modal :show="isApiModalOpen" @close="closeApiModal" max-width="lg">
            <div class="p-6 sm:p-7 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Sinkronisasi Data Konsumsi
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Atur penarikan data konsumsi dari sistem pusat secara otomatis atau manual.
                        </p>
                    </div>
                    <button @click="closeApiModal" :disabled="isSyncing || isSavingSchedule" class="text-slate-400 hover:text-slate-600 p-1 disabled:opacity-50">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- 1. Tarik Data Manual -->
                <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h4 class="text-xs font-bold text-blue-950 flex items-center gap-1.5">
                                Tarik Data Sekarang
                            </h4>
                            <p class="text-xs text-blue-800/80 mt-1 leading-relaxed">
                                Ambil dan perbarui data konsumsi sparepart terbaru secara langsung dari sistem pusat tanpa menunggu jadwal rutin.
                            </p>
                        </div>
                    </div>
                    <div class="pt-1">
                        <button
                            type="button"
                            @click="triggerManualSync"
                            :disabled="isSyncing || isSavingSchedule"
                            class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 rounded-lg shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer"
                        >
                            <RefreshCw :class="['w-3.5 h-3.5', isSyncing ? 'animate-spin' : '']" />
                            <span>{{ isSyncing ? 'Sedang Menarik Data...' : 'Tarik Data Sekarang' }}</span>
                        </button>
                    </div>
                </div>

                <!-- 2. Jadwal Penarikan Otomatis Harian -->
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Clock class="w-4 h-4 text-emerald-600" />
                            <h4 class="text-xs font-bold text-slate-800">
                                Jadwal Penarikan Otomatis (Harian)
                            </h4>
                        </div>
                        <span
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold"
                            :class="scheduleList.filter(s => s.is_active).length > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="scheduleList.filter(s => s.is_active).length > 0 ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                            {{ scheduleList.filter(s => s.is_active).length > 0 ? 'Otomatis Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <p class="text-xs text-slate-600">
                        Sistem akan otomatis mengambil data baru setiap hari pada jam-jam berikut:
                    </p>

                    <!-- Daftar Waktu (Chips / Badge List) -->
                    <div class="space-y-2">
                        <div v-if="scheduleList.length > 0" class="flex flex-wrap items-center gap-2">
                            <div
                                v-for="(item, idx) in scheduleList"
                                :key="idx"
                                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                                :class="item.is_active ? 'bg-white border-slate-200 text-slate-800 shadow-2xs' : 'bg-slate-100 border-slate-200 text-slate-400 line-through'"
                            >
                                <span class="font-mono text-xs">{{ item.time }} WIB</span>
                                <button
                                    v-if="isAdmin"
                                    type="button"
                                    @click="removeScheduleTime(idx)"
                                    class="text-slate-400 hover:text-red-600 transition p-0.5 cursor-pointer"
                                    title="Hapus jam ini"
                                >
                                    <X class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-xs text-slate-400 italic py-1">
                            Belum ada jadwal yang diatur.
                        </div>
                    </div>

                    <!-- Input Tambah Jam Baru (Khusus Admin) -->
                    <div v-if="isAdmin" class="pt-3 border-t border-slate-200/80 space-y-3">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
                            <div class="flex items-center gap-2 w-full sm:w-auto">
                                <label class="text-[11px] font-medium text-slate-600 whitespace-nowrap">
                                    Tambah Jam:
                                </label>
                                <input
                                    type="time"
                                    v-model="newScheduleTime"
                                    class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs focus:ring-1 focus:ring-slate-900 focus:border-slate-900"
                                />
                                <button
                                    type="button"
                                    @click="addScheduleTime"
                                    :disabled="!newScheduleTime"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-800 text-white rounded-lg text-xs font-medium hover:bg-slate-900 transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                                >
                                    <Plus class="w-3.5 h-3.5" />
                                    Tambah
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pt-2">
                            <p class="text-[11px] text-slate-500 flex items-center gap-1">
                                Klik <strong>Simpan Jadwal</strong> jika Anda mengubah daftar jam di atas.
                            </p>
                            <button
                                type="button"
                                @click="saveSchedules"
                                :disabled="isSavingSchedule"
                                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-2xs transition disabled:opacity-50 cursor-pointer"
                            >
                                <span>{{ isSavingSchedule ? 'Menyimpan...' : 'Simpan Jadwal' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 3. Integrasi Lanjutan (Collapsible untuk IT / Pengembang) -->
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <button
                        type="button"
                        @click="showAdvancedSettings = !showAdvancedSettings"
                        class="w-full flex items-center justify-between px-4 py-2.5 bg-slate-50 hover:bg-slate-100 text-xs font-semibold text-slate-700 transition text-left cursor-pointer"
                    >
                        <span>Integrasi Lanjutan / Webhook (Khusus Tim IT)</span>
                        <component :is="showAdvancedSettings ? ChevronUp : ChevronDown" class="w-4 h-4 text-slate-500" />
                    </button>
                    <div v-show="showAdvancedSettings" class="p-4 bg-white space-y-2.5 text-xs border-t border-slate-200">
                        <p class="text-[11px] text-slate-500">
                            Sistem eksternal juga dapat mengirimkan data pemakaian secara langsung ke sistem ini melalui endpoint:
                        </p>
                        <div class="p-2.5 bg-slate-900 text-slate-100 rounded-lg font-mono text-[11px] overflow-x-auto">
                            POST /api/v1/consumes/sync
                        </div>
                        <p class="text-[11px] text-slate-400">
                            Header autentikasi: <code>Authorization: Bearer &lt;token&gt;</code>, <code>Accept: application/json</code>.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-slate-100">
                    <SecondaryButton @click="closeApiModal" :disabled="isSyncing || isSavingSchedule">
                        Tutup
                    </SecondaryButton>
                </div>
            </div>
        </Modal>

    </AppLayout>
</template>
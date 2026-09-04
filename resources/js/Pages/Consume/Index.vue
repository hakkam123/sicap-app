<script setup>
import { ref, computed, watch } from 'vue';
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
import { Search, RotateCcw, Plus, Upload, RefreshCw } from 'lucide-vue-next';

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
    router.get(route('consume.index'), {}, { preserveState: false });
};

const reloadData = () => {
    router.reload({ preserveScroll: true });
};

// ==========================================
// 2. DATA TABLE COLUMNS & FORMATTERS
// ==========================================
const tableColumns = [
    { key: 'consumed_at', label: 'Tanggal', width: 'w-28' },
    { key: 'part_number.pn_baan', label: 'PN BAAN', width: 'w-44' },
    { key: 'part_number.description', label: 'Deskripsi' },
    { key: 'area.name', label: 'Area', width: 'w-28' },
    { key: 'machine.name', label: 'Machine', width: 'w-28' },
    { key: 'quantity', label: 'Qty', align: 'right', width: 'w-20' },
    { key: 'amount', label: 'Amount', align: 'right', width: 'w-32' },
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
// 4. MODAL INPUT MANUAL
// ==========================================
const isManualModalOpen = ref(false);
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

const estimatedAmount = computed(() => {
    if (!selectedPart.value || selectedPart.value.price_per_unit === null) return null;
    const qty = parseInt(manualForm.quantity) || 0;
    return Math.max(0, qty) * Number(selectedPart.value.price_per_unit);
});

const selectPart = (part) => {
    manualForm.part_number_id = part.id;
    manualSearchQuery.value = `${part.pn_baan} - ${part.description || ''}`;
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
    manualForm.reset();
    manualForm.quantity = 1;
    manualForm.consumed_at = new Date().toISOString().split('T')[0];
    manualSearchQuery.value = '';
    manualMachines.value = [];
    isManualModalOpen.value = true;
};

const closeManualModal = () => {
    isManualModalOpen.value = false;
};

const submitManualForm = () => {
    manualForm.post(route('consume.store'), {
        onSuccess: () => {
            closeManualModal();
        },
    });
};

// ==========================================
// 5. MODAL IMPORT EXCEL
// ==========================================
const isImportModalOpen = ref(false);
const importFile = ref(null);
const importInputRef = ref(null);

const importForm = useForm({
    file: null,
});

const openImportModal = () => {
    importForm.reset();
    importFile.value = null;
    isImportModalOpen.value = true;
};

const closeImportModal = () => {
    isImportModalOpen.value = false;
};

const handleImportFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        importFile.value = file;
        importForm.file = file;
    }
};

const submitImportForm = () => {
    if (!importForm.file) return;
    importForm.post(route('consume.import.store'), {
        onSuccess: () => {
            importForm.reset();
            importFile.value = null;
            if (importInputRef.value) importInputRef.value.value = '';
        },
    });
};

// ==========================================
// 6. MODAL SYNC API INFO
// ==========================================
const isApiModalOpen = ref(false);
const openApiModal = () => { isApiModalOpen.value = true; };
const closeApiModal = () => { isApiModalOpen.value = false; };
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
            <!-- ACTION BUTTONS (di luar card, atas kanan) -->
            <div class="flex justify-end gap-2 flex-wrap">
                <button
                    type="button"
                    @click="reloadData"
                    class="flex items-center gap-1 px-2.5 py-1.5 border border-gray-200 bg-white text-xs font-medium text-gray-700 rounded-md hover:bg-gray-50 transition"
                    title="Muat ulang data"
                >
                    <RefreshCw class="w-3.5 h-3.5 text-gray-500" />
                    Refresh
                </button>

                <button
                    type="button"
                    @click="openApiModal"
                    class="flex items-center gap-1 px-2.5 py-1.5 border border-gray-200 bg-white text-xs font-medium text-gray-700 rounded-md hover:bg-gray-50 transition"
                    title="Integrasi API"
                >
                    Sync API
                </button>

                <button
                    v-if="isAdmin"
                    type="button"
                    @click="openImportModal"
                    class="flex items-center gap-1 px-2.5 py-1.5 border border-green-600 bg-green-600 text-xs font-medium text-white rounded-md hover:bg-green-700 transition"
                >
                    <Upload class="w-3.5 h-3.5 text-white" />
                    Import Excel
                </button>



                <button
                    type="button"
                    @click="openManualModal"
                    class="flex items-center gap-1 px-2.5 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-md hover:bg-gray-700 transition"
                >
                    <Plus class="w-3.5 h-3.5" />
                    Manual
                </button>
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
                        @click="confirmDelete(row)"
                        class="text-red-600 hover:text-red-900 hover:underline font-semibold inline-flex items-center gap-1 text-xs"
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

        <!-- MODAL INPUT MANUAL -->
        <Modal :show="isManualModalOpen" @close="closeManualModal">
            <div class="p-6 sm:p-8 space-y-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Input Manual Transaksi Consume
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Catat pengeluaran sparepart baru ke dalam sistem.
                        </p>
                    </div>
                    <button @click="closeManualModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitManualForm" class="space-y-4">
                    <!-- Part Number Autocomplete / Search -->
                    <div class="relative">
                        <InputLabel for="manual_part_number" value="Part Number (PN BAAN) *" />
                        <TextInput
                            id="manual_part_number"
                            v-model="manualSearchQuery"
                            type="text"
                            class="mt-1 block w-full text-xs font-mono"
                            placeholder="Cari nomor part atau ketik nama..."
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
                                <div v-if="part.price_per_unit" class="text-[10px] text-emerald-600 font-semibold">
                                    Harga: {{ formatRupiah(part.price_per_unit) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Area Dropdown -->
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

                    <!-- Machine Dropdown -->
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

                    <!-- Quantity (Wajib Positif) -->
                    <div>
                        <InputLabel for="manual_qty" value="Quantity (Jumlah Unit) *" />
                        <TextInput
                            id="manual_qty"
                            v-model="manualForm.quantity"
                            type="number"
                            min="1"
                            step="1"
                            class="mt-1 block w-full text-xs"
                            placeholder="Contoh: 5"
                            required
                        />
                        <p class="text-[11px] text-slate-400 mt-1">
                            * Input kuantitas selalu berupa angka positif (pengeluaran stok).
                        </p>
                        <InputError class="mt-1" :message="manualForm.errors.quantity" />
                    </div>

                    <!-- Amount -->
                    <div>
                        <InputLabel for="manual_amount" value="Amount / Total Nominal (Rp)" />
                        <TextInput
                            id="manual_amount"
                            v-model="manualForm.amount"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full text-xs"
                            :placeholder="estimatedAmount !== null ? `Estimasi: ${formatRupiah(estimatedAmount)}` : 'Dihitung otomatis jika kosong'"
                        />
                        <InputError class="mt-1" :message="manualForm.errors.amount" />
                    </div>

                    <!-- Consumed At -->
                    <div>
                        <InputLabel for="manual_consumed_at" value="Tanggal Pemakaian *" />
                        <TextInput
                            id="manual_consumed_at"
                            v-model="manualForm.consumed_at"
                            type="date"
                            class="mt-1 block w-full text-xs"
                            required
                        />
                        <InputError class="mt-1" :message="manualForm.errors.consumed_at" />
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                        <SecondaryButton type="button" @click="closeManualModal">
                            Batal
                        </SecondaryButton>
                        <PrimaryButton :disabled="manualForm.processing">
                            {{ manualForm.processing ? 'Menyimpan...' : 'Simpan' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL IMPORT EXCEL -->
        <Modal :show="isImportModalOpen" @close="closeImportModal">
            <div class="p-6 sm:p-8 space-y-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Import Data Consume dari Excel
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            File Excel diproses secara asynchronous via background queue.
                        </p>
                    </div>
                    <button @click="closeImportModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitImportForm" class="space-y-4">
                    <div class="p-3.5 bg-blue-50/70 rounded-xl border border-blue-100 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-blue-900">Template Format Excel</p>
                            <p class="text-[11px] text-blue-700">Kolom: Date | Part Number | Desc | qty | Amount</p>
                        </div>
                        <a
                            :href="route('consume.template')"
                            class="inline-flex items-center gap-1 text-xs font-bold text-blue-700 hover:text-blue-900 underline"
                            download
                        >
                            Download Template
                        </a>
                    </div>

                    <div>
                        <InputLabel for="import_file" value="Pilih File Excel (.xlsx, .xls) *" />
                        <input
                            ref="importInputRef"
                            id="import_file"
                            type="file"
                            accept=".xlsx, .xls"
                            @change="handleImportFileChange"
                            class="mt-1 block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            required
                        />
                        <InputError class="mt-1" :message="importForm.errors.file" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <SecondaryButton type="button" @click="closeImportModal">
                            Batal
                        </SecondaryButton>
                        <PrimaryButton :disabled="importForm.processing || !importForm.file">
                            {{ importForm.processing ? 'Mengunggah...' : 'Upload & Jalankan Queue' }}
                        </PrimaryButton>
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

        <!-- MODAL SYNC API INFO -->
        <Modal :show="isApiModalOpen" @close="closeApiModal">
            <div class="p-6 sm:p-8 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">
                        REST API Synchronisation Endpoint
                    </h3>
                    <button @click="closeApiModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3 text-xs text-slate-600">
                    <p>
                        Sistem eksternal dapat mengirimkan rekaman consume melalui endpoint:
                    </p>
                    <div class="p-3 bg-slate-900 text-slate-100 rounded-lg font-mono text-[11px] overflow-x-auto">
                        POST /api/v1/consumes/sync
                    </div>
                    <p class="text-[11px] text-slate-500">
                        Header wajib: <code>Authorization: Bearer &lt;token&gt;</code>, <code>Accept: application/json</code>.
                    </p>
                </div>

                <div class="flex justify-end pt-3 border-t border-slate-100">
                    <SecondaryButton @click="closeApiModal">
                        Tutup
                    </SecondaryButton>
                </div>
            </div>
        </Modal>

    </AppLayout>
</template>
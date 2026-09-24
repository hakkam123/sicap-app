<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/UI/ConfirmModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
    Search,
    RotateCcw,
    Plus,
    X,
    Layers,
    Upload,
    Download,
    AlertCircle,
    MapPin,
    Tag,
    FileSpreadsheet,
    Edit3
} from 'lucide-vue-next';
import { useImportPolling } from '@/composables/useImportPolling';

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const props = defineProps({
    partNumbers: {
        type: Object,
        required: true,
    },
    areas: {
        type: Array,
        default: () => [],
    },
    allPartOptions: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', per_page: 10 }),
    },
});

// ==========================================
// 1. FILTER STATE & ACTIONS
// ==========================================
const filters = ref({
    search: props.filters.search || '',
    per_page: props.filters.per_page || 10,
});

const applyFilter = () => {
    router.get(
        route('part-numbers.index'),
        { search: filters.value.search, per_page: filters.value.per_page },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const resetFilter = () => {
    filters.value.search = '';
    filters.value.per_page = 10;
    router.get(route('part-numbers.index'), {}, { preserveState: false });
};

// ==========================================
// 2. DATA TABLE COLUMNS & FORMATTERS
// ==========================================
const tableColumns = [
    { key: 'pn_baan', label: 'PN BAAN', width: 'w-44' },
    { key: 'part_number_code', label: 'Kode Part', width: 'w-36' },
    { key: 'description', label: 'Deskripsi' },
    { key: 'addressing', label: 'Addressing (Lokasi)', width: 'w-48' },
    { key: 'areas_count', label: 'Area Mapping', align: 'center', width: 'w-28' },
    { key: 'machines_count', label: 'Machine Mapping', align: 'center', width: 'w-32' },
];

// ==========================================
// 3. CREATE / EDIT PART NUMBER MODAL
// ==========================================
const isModalOpen = ref(false);
const editingPart = ref(null);
const isEditing = computed(() => !!editingPart.value);

const form = useForm({
    pn_baan: '',
    part_number_code: '',
    description: '',
    addressing: '',
    area_ids: [],
    machine_ids: [],
});

const openCreateModal = () => {
    editingPart.value = null;
    form.reset();
    form.clearErrors();
    form.area_ids = [];
    form.machine_ids = [];
    isModalOpen.value = true;
};

const openEditModal = (part) => {
    editingPart.value = part;
    form.clearErrors();
    form.pn_baan = part.pn_baan || '';
    form.part_number_code = part.part_number_code || '';
    form.description = part.description || '';
    form.addressing = part.addressing || '';
    form.area_ids = part.areas ? part.areas.map(a => a.id) : [];
    form.machine_ids = part.machines ? part.machines.map(m => m.id) : [];
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingPart.value = null;
    form.reset();
    form.clearErrors();
};

const toggleArea = (areaId) => {
    const idx = form.area_ids.indexOf(areaId);
    if (idx > -1) {
        form.area_ids.splice(idx, 1);
    } else {
        form.area_ids.push(areaId);
    }
};

const toggleMachine = (machineId, areaId) => {
    const idx = form.machine_ids.indexOf(machineId);
    if (idx > -1) {
        form.machine_ids.splice(idx, 1);
    } else {
        form.machine_ids.push(machineId);
        if (areaId && !form.area_ids.includes(areaId)) {
            form.area_ids.push(areaId);
        }
    }
};

const selectAllMachinesForArea = (area) => {
    if (!form.area_ids.includes(area.id)) {
        form.area_ids.push(area.id);
    }
    if (area.machines) {
        area.machines.forEach(m => {
            if (!form.machine_ids.includes(m.id)) {
                form.machine_ids.push(m.id);
            }
        });
    }
};

const deselectAllMachinesForArea = (area) => {
    if (area.machines) {
        const mIds = area.machines.map(m => m.id);
        form.machine_ids = form.machine_ids.filter(id => !mIds.includes(id));
    }
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('part-numbers.update', editingPart.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('part-numbers.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

// ==========================================
// 4. KELOLA ADDRESSING MODAL (TABBED)
// ==========================================
const isAddressingModalOpen = ref(false);
const addressingTab = ref('manual'); // 'manual' | 'import'
const addressingPartSearch = ref('');

const addressingForm = useForm({
    part_number_id: '',
    part_number_code: '',
    addressing: '',
});

const selectedAddressingPart = computed(() => {
    if (!addressingForm.part_number_id) return null;
    return props.allPartOptions.find(p => p.id === addressingForm.part_number_id) || null;
});

const filteredPartOptions = computed(() => {
    const q = addressingPartSearch.value.trim().toLowerCase();
    if (!q) return props.allPartOptions.slice(0, 100);
    return props.allPartOptions.filter(p =>
        (p.pn_baan || '').toLowerCase().includes(q) ||
        (p.part_number_code || '').toLowerCase().includes(q) ||
        (p.description || '').toLowerCase().includes(q)
    ).slice(0, 100);
});

const openAddressingModal = (targetPart = null) => {
    addressingTab.value = 'manual';
    addressingForm.reset();
    addressingForm.clearErrors();
    addressingPartSearch.value = '';

    if (targetPart) {
        addressingForm.part_number_id = targetPart.id;
        addressingForm.part_number_code = targetPart.part_number_code || '';
        addressingForm.addressing = targetPart.addressing || '';
    } else if (props.allPartOptions.length > 0) {
        const first = props.allPartOptions[0];
        addressingForm.part_number_id = first.id;
        addressingForm.part_number_code = first.part_number_code || '';
        addressingForm.addressing = first.addressing || '';
    }

    isAddressingModalOpen.value = true;
};

const onAddressingPartChange = () => {
    const p = selectedAddressingPart.value;
    if (p) {
        addressingForm.part_number_code = p.part_number_code || '';
        addressingForm.addressing = p.addressing || '';
    } else {
        addressingForm.part_number_code = '';
        addressingForm.addressing = '';
    }
};

const closeAddressingModal = () => {
    if (addressingImportPolling.isUploading.value) return;
    isAddressingModalOpen.value = false;
    addressingForm.reset();
    addressingForm.clearErrors();
};

const submitAddressingManual = () => {
    if (!addressingForm.part_number_id) return;
    addressingForm.post(route('addressing.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeAddressingModal();
            router.reload({ preserveScroll: true });
        },
    });
};

// Addressing Import via Polling
const addressingImportPolling = useImportPolling();
const addressingImportFile = ref(null);
const addressingImportInputRef = ref(null);

const handleAddressingFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        addressingImportFile.value = file;
        addressingImportPolling.importErrors.value = [];
    }
};

const submitAddressingImport = () => {
    if (!addressingImportFile.value || addressingImportPolling.isUploading.value) return;

    addressingImportPolling.startImport({
        url: route('addressing.import'),
        file: addressingImportFile.value,
        title: 'Import Addressing Sparepart',
        onSuccess: () => {
            closeAddressingModal();
            router.reload({ preserveScroll: true });
        },
    });
};

// ==========================================
// 5. DELETE CONFIRMATION MODAL
// ==========================================
const confirmModal = ref({ show: false, id: null, name: '' });
const isDeleting = ref(false);

const openConfirm = (row) => {
    confirmModal.value = { show: true, id: row.id, name: row.pn_baan };
};

const closeConfirm = () => {
    confirmModal.value = { show: false, id: null, name: '' };
};

const doDelete = () => {
    isDeleting.value = true;
    router.delete(route('part-numbers.destroy', confirmModal.value.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            closeConfirm();
        },
    });
};

// ==========================================
// 6. MAIN IMPORT EXCEL PART NUMBER MODAL
// ==========================================
const isImportModalOpen = ref(false);
const importFile = ref(null);
const importInputRef = ref(null);

const { isUploading, importErrors, startImport } = useImportPolling();

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

const submitImport = () => {
    if (!importFile.value || isUploading.value) return;

    startImport({
        url: route('part-numbers.import'),
        file: importFile.value,
        title: 'Import Part Number',
        onSuccess: () => {
            closeImportModal();
            router.reload({ preserveScroll: true });
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Master Data Part Number" />

        <template #header>
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-800">
                    Master Data Part Number
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Kelola katalog nomor part (PN BAAN), kode part, addressing lokasi rak, dan relasi mapping area/mesin.
                </p>
            </div>
        </template>

        <div class="p-6 space-y-4">
            <!-- FILTER + ACTION CARD -->
            <div class="bg-white p-3 sm:p-4 rounded-xl shadow-xs border border-gray-200">
                <div class="flex flex-wrap items-end justify-between gap-3">

                    <!-- FILTER FIELDS — KIRI -->
                    <div class="flex flex-wrap items-end gap-2">

                        <!-- Field Search -->
                        <div class="flex flex-col gap-0.5">
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
                                    placeholder="Cari PN BAAN, kode part, atau lokasi..."
                                    class="w-64 pl-8 pr-3 py-1.5 border border-gray-200 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
                                    @keydown.enter="applyFilter"
                                />
                            </div>
                        </div>

                        <!-- Field Per Page -->
                        <div class="flex flex-col gap-0.5">
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                Tampilkan
                            </label>

                            <select
                                v-model.number="filters.per_page"
                                @change="applyFilter"
                                class="px-2.5 py-1.5 border border-gray-200 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900"
                            >
                                <option :value="5">5</option>
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                                <option :value="100">100</option>
                            </select>
                        </div>

                        <!-- Tombol Cari + Reset -->
                        <div class="flex items-end gap-1">
                            <button
                                type="button"
                                @click="applyFilter"
                                class="px-3 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-md hover:bg-gray-700 transition"
                            >
                                Cari
                            </button>

                            <button
                                type="button"
                                @click="resetFilter"
                                class="p-1.5 border border-gray-200 rounded-md text-gray-500 hover:bg-gray-50 transition"
                                title="Reset filter"
                            >
                                <RotateCcw class="w-3.5 h-3.5" />
                            </button>
                        </div>

                    </div>

                    <!-- ACTION BUTTONS — KANAN -->
                    <div
                        v-if="isAdmin"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <!-- Button Kelola Addressing -->
                        <button
                            type="button"
                            @click="openAddressingModal()"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700 transition shadow-xs"
                            title="Input atau update massal addressing rak sparepart"
                        >
                            <MapPin class="w-3.5 h-3.5 text-white" />
                            Kelola Addressing
                        </button>

                        <!-- Button Import Excel -->
                        <button
                            type="button"
                            @click="openImportModal"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white text-xs font-medium rounded-md hover:bg-emerald-700 transition shadow-xs"
                        >
                            <Upload class="w-3.5 h-3.5 text-white" />
                            Import Part Number
                        </button>

                        <!-- Button Tambah Part Number -->
                        <button
                            type="button"
                            @click="openCreateModal"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-md hover:bg-gray-700 transition shadow-xs"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            Tambah Part Number
                        </button>
                    </div>

                </div>
            </div>

            <!-- DATA TABLE -->
            <DataTable
                :columns="tableColumns"
                :data="partNumbers"
            >
                <!-- PN BAAN -->
                <template #cell-pn_baan="{ value }">
                    <span class="font-mono font-bold text-slate-800 whitespace-nowrap">{{ value }}</span>
                </template>

                <!-- Kode Part -->
                <template #cell-part_number_code="{ value }">
                    <span v-if="value" class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded font-mono font-semibold text-slate-700 text-xs">
                        {{ value }}
                    </span>
                    <span v-else class="text-slate-400 text-xs">-</span>
                </template>

                <!-- Deskripsi -->
                <template #cell-description="{ value }">
                    <span class="text-slate-600">{{ value || '-' }}</span>
                </template>

                <!-- Addressing -->
                <template #cell-addressing="{ row, value }">
                    <div v-if="value" class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded text-xs font-medium">
                        <MapPin class="w-3 h-3 text-emerald-600 shrink-0" />
                        <span class="truncate max-w-xs" :title="value">{{ value }}</span>
                    </div>
                    <button
                        v-else-if="isAdmin"
                        type="button"
                        @click="openAddressingModal(row)"
                        class="text-xs text-amber-600 hover:text-amber-800 hover:underline italic font-medium inline-flex items-center gap-1"
                    >
                        <span>Belum teraddress</span>
                        <Edit3 class="w-3 h-3 text-amber-500" />
                    </button>
                    <span v-else class="italic text-slate-400 text-xs">
                        Belum teraddress
                    </span>
                </template>

                <!-- Area Mapping Count -->
                <template #cell-areas_count="{ value }">
                    <span class="text-slate-700 font-medium">
                        {{ value || 0 }} area
                    </span>
                </template>

                <!-- Machine Mapping Count -->
                <template #cell-machines_count="{ value }">
                    <span class="text-slate-700 font-medium">
                        {{ value || 0 }} mesin
                    </span>
                </template>

                <!-- Row Actions -->
                <template v-if="isAdmin" #actions="{ row }">
                    <button
                        type="button"
                        @click="openAddressingModal(row)"
                        class="text-indigo-600 hover:text-indigo-900 hover:underline font-semibold inline-flex items-center gap-1 text-xs mr-2.5"
                        title="Edit Addressing & Kode Part"
                    >
                        <MapPin class="w-3 h-3" />
                        Addressing
                    </button>
                    <button
                        type="button"
                        @click="openEditModal(row)"
                        class="text-blue-600 hover:text-blue-900 hover:underline font-semibold inline-flex items-center gap-1 text-xs"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        @click="openConfirm(row)"
                        class="text-red-600 hover:text-red-900 hover:underline font-semibold inline-flex items-center gap-1 text-xs ml-2.5"
                    >
                        Hapus
                    </button>
                </template>

                <template #empty>
                    <p>Tidak ada data part number ditemukan.</p>
                </template>
            </DataTable>
        </div>

        <!-- ======================================================= -->
        <!-- MODAL KELOLA ADDRESSING (INPUT & IMPORT)                -->
        <!-- ======================================================= -->
        <Modal :show="isAddressingModalOpen" @close="closeAddressingModal" max-width="2xl">
            <div class="p-6 space-y-5">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 bg-indigo-50 text-indigo-700 rounded-lg border border-indigo-100">
                            <MapPin class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">
                                Kelola Addressing & Kode Sparepart
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Atur kode sparepart dan lokasi rak/addressing untuk katalog sparepart.
                            </p>
                        </div>
                    </div>
                    <button @click="closeAddressingModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Tabs: Manual vs Excel Import -->
                <div class="flex border-b border-slate-200 text-xs font-semibold">
                    <button
                        type="button"
                        @click="addressingTab = 'manual'"
                        :class="[
                            'px-4 py-2.5 border-b-2 flex items-center gap-2 transition',
                            addressingTab === 'manual'
                                ? 'border-indigo-600 text-indigo-700 font-bold bg-indigo-50/40'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        ]"
                    >
                        <Tag class="w-4 h-4" />
                        <span>Input / Edit Manual</span>
                    </button>

                    <button
                        type="button"
                        @click="addressingTab = 'import'"
                        :class="[
                            'px-4 py-2.5 border-b-2 flex items-center gap-2 transition',
                            addressingTab === 'import'
                                ? 'border-emerald-600 text-emerald-700 font-bold bg-emerald-50/40'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        ]"
                    >
                        <FileSpreadsheet class="w-4 h-4" />
                        <span>Import Excel Massal</span>
                    </button>
                </div>

                <!-- TAB 1: MANUAL INPUT / EDIT ADDRESSING -->
                <div v-if="addressingTab === 'manual'">
                    <form @submit.prevent="submitAddressingManual" class="space-y-4">
                        <!-- Part Number Selection -->
                        <div>
                            <InputLabel for="addressing_part_id" value="Pilih Part Number (PN BAAN) *" />
                            <select
                                id="addressing_part_id"
                                v-model="addressingForm.part_number_id"
                                @change="onAddressingPartChange"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-600 focus:ring-indigo-600 rounded-md shadow-xs text-xs font-mono font-semibold text-slate-800"
                                required
                            >
                                <option value="" disabled>-- Pilih Part Number --</option>
                                <option
                                    v-for="p in allPartOptions"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.pn_baan }} {{ p.description ? `— ${p.description}` : '' }} {{ p.part_number_code ? `[Kode: ${p.part_number_code}]` : '' }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="addressingForm.errors.part_number_id" />
                        </div>

                        <!-- Read-only Part Details (If selected) -->
                        <div v-if="selectedAddressingPart" class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs space-y-1">
                            <p class="text-slate-500">Deskripsi Part:</p>
                            <p class="font-medium text-slate-800">{{ selectedAddressingPart.description || '-' }}</p>
                        </div>

                        <!-- Kode Sparepart (part_number_code) -->
                        <div>
                            <InputLabel for="addressing_part_code" value="Kode Sparepart (Internal / Alternatif)" />
                            <TextInput
                                id="addressing_part_code"
                                v-model="addressingForm.part_number_code"
                                type="text"
                                class="mt-1 block w-full font-mono text-xs"
                                placeholder="Contoh: SP-00123"
                            />
                            <p class="text-[11px] text-slate-500 mt-1">
                                Kode internal atau kode singkat sparepart (tidak wajib unique).
                            </p>
                            <InputError class="mt-1" :message="addressingForm.errors.part_number_code" />
                        </div>

                        <!-- Addressing / Lokasi Rak -->
                        <div>
                            <InputLabel for="addressing_location" value="Lokasi Addressing / Rak *" />
                            <TextInput
                                id="addressing_location"
                                v-model="addressingForm.addressing"
                                type="text"
                                class="mt-1 block w-full text-xs"
                                placeholder="Contoh: RACK-01 A1/LEMARI 5-TRAY A/CONTAINER BOX A"
                            />
                            <p class="text-[11px] text-slate-500 mt-1">
                                Lokasi spesifik penyimpanan part (Rak, Lemari, Tray, Container Box).
                            </p>
                            <InputError class="mt-1" :message="addressingForm.errors.addressing" />
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                            <SecondaryButton type="button" @click="closeAddressingModal">
                                Batal
                            </SecondaryButton>
                            <PrimaryButton :disabled="addressingForm.processing">
                                Simpan Addressing
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- TAB 2: EXCEL IMPORT ADDRESSING -->
                <div v-else class="space-y-4">
                    <div class="p-3.5 bg-emerald-50/80 rounded-xl border border-emerald-100 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-emerald-900">Format Kolom Excel Addressing:</p>
                            <p class="text-[11px] text-emerald-700 font-mono mt-0.5">pn_baan | part_number_code | addressing</p>
                        </div>
                        <a
                            :href="route('addressing.template')"
                            class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:text-emerald-900 underline"
                            download
                        >
                            <Download class="w-3.5 h-3.5" />
                            Download Template
                        </a>
                    </div>

                    <!-- Error Messages Box -->
                    <div v-if="addressingImportPolling.importErrors.value.length > 0" class="p-3 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700 space-y-1.5 max-h-48 overflow-y-auto">
                        <div class="flex items-center gap-1.5 font-bold text-red-800">
                            <AlertCircle class="w-4 h-4 shrink-0" />
                            <span>Terdapat {{ addressingImportPolling.importErrors.value.length }} baris bermasalah:</span>
                        </div>
                        <ul class="space-y-1 pl-1 text-[11px]">
                            <li v-for="(err, idx) in addressingImportPolling.importErrors.value" :key="idx" class="flex items-start gap-1.5">
                                <span class="font-bold text-red-900">•</span>
                                <span>{{ typeof err === 'object' && err !== null ? (err.message || JSON.stringify(err)) : err }}</span>
                            </li>
                        </ul>
                    </div>

                    <form @submit.prevent="submitAddressingImport" class="space-y-4">
                        <div>
                            <InputLabel for="addressing_import_file" value="Pilih File Excel (.xlsx, .xls) *" />
                            <input
                                ref="addressingImportInputRef"
                                id="addressing_import_file"
                                type="file"
                                accept=".xlsx, .xls"
                                @change="handleAddressingFileChange"
                                class="mt-1 block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer"
                                required
                            />
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                            <SecondaryButton type="button" @click="closeAddressingModal" :disabled="addressingImportPolling.isUploading.value">
                                Batal
                            </SecondaryButton>
                            <button
                                type="submit"
                                :disabled="!addressingImportFile || addressingImportPolling.isUploading.value"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span>{{ addressingImportPolling.isUploading.value ? 'Mengimpor...' : 'Import Addressing' }}</span>
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </Modal>

        <!-- ======================================================= -->
        <!-- MODAL FORM CREATE / EDIT PART NUMBER                    -->
        <!-- ======================================================= -->
        <Modal :show="isModalOpen" @close="closeModal" max-width="2xl">
            <div class="p-6 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            {{ isEditing ? 'Edit Data Part Number' : 'Tambah Part Number Baru' }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ isEditing ? 'Perbarui data part number serta mapping area & mesin.' : 'Masukkan nomor part baru beserta penugasan area/mesin.' }}
                        </p>
                    </div>
                    <button @click="closeModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- PN BAAN -->
                    <div>
                        <InputLabel for="pn_baan" value="Nomor Part (PN BAAN) *" />
                        <TextInput
                            id="pn_baan"
                            v-model="form.pn_baan"
                            type="text"
                            class="mt-1 block w-full uppercase font-mono text-xs"
                            placeholder="Contoh: SPFAMEBITHOL-2295"
                            required
                            autofocus
                        />
                        <InputError class="mt-1" :message="form.errors.pn_baan" />
                    </div>

                    <!-- Kode Sparepart & Addressing -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="part_number_code" value="Kode Sparepart" />
                            <TextInput
                                id="part_number_code"
                                v-model="form.part_number_code"
                                type="text"
                                class="mt-1 block w-full font-mono text-xs"
                                placeholder="Contoh: SP-00123"
                            />
                            <InputError class="mt-1" :message="form.errors.part_number_code" />
                        </div>
                        <div>
                            <InputLabel for="addressing" value="Addressing (Lokasi)" />
                            <TextInput
                                id="addressing"
                                v-model="form.addressing"
                                type="text"
                                class="mt-1 block w-full text-xs"
                                placeholder="Contoh: RACK-01 A1"
                            />
                            <InputError class="mt-1" :message="form.errors.addressing" />
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <InputLabel for="description" value="Deskripsi Sparepart" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="2"
                            class="mt-1 block w-full border-gray-300 focus:border-slate-900 focus:ring-slate-900 rounded-md shadow-xs text-xs"
                            placeholder="Contoh: Bit Holder 1736234 (Rear)..."
                        ></textarea>
                        <InputError class="mt-1" :message="form.errors.description" />
                    </div>

                    <!-- SECTION ASSIGN AREA & MACHINE -->
                    <div class="pt-2 border-t border-slate-100">
                        <div class="flex items-center gap-1.5 mb-2">
                            <Layers class="w-4 h-4 text-slate-700" />
                            <span class="text-xs font-bold text-slate-800">Assign Area & Machine</span>
                        </div>
                        <p class="text-[11px] text-slate-500 mb-3">
                            Pilih area dan mesin tempat part ini digunakan.
                        </p>

                        <div class="space-y-3 max-h-56 overflow-y-auto pr-1">
                            <div
                                v-for="area in areas"
                                :key="area.id"
                                class="border border-slate-200 rounded-lg p-3 bg-slate-50/50"
                            >
                                <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            :value="area.id"
                                            :checked="form.area_ids.includes(area.id)"
                                            @change="toggleArea(area.id)"
                                            class="rounded border-slate-300 text-slate-900 focus:ring-slate-900 h-4 w-4"
                                        />
                                        <span class="text-xs font-bold text-slate-800">
                                            Area: {{ area.name }} ({{ area.code }})
                                        </span>
                                    </label>

                                    <div class="flex items-center gap-2 text-[10px]">
                                        <button
                                            type="button"
                                            @click="selectAllMachinesForArea(area)"
                                            class="text-blue-600 hover:underline font-medium"
                                        >
                                            Pilih Semua Mesin
                                        </button>
                                        <span class="text-slate-300">|</span>
                                        <button
                                            type="button"
                                            @click="deselectAllMachinesForArea(area)"
                                            class="text-slate-500 hover:underline"
                                        >
                                            Batal Pilih
                                        </button>
                                    </div>
                                </div>

                                <!-- Mesin-mesin di area ini -->
                                <div v-if="area.machines && area.machines.length > 0" class="mt-2.5 grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    <label
                                        v-for="machine in area.machines"
                                        :key="machine.id"
                                        class="flex items-center gap-1.5 p-1 rounded hover:bg-white cursor-pointer text-xs text-slate-700"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="machine.id"
                                            :checked="form.machine_ids.includes(machine.id)"
                                            @change="toggleMachine(machine.id, area.id)"
                                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-3.5 w-3.5"
                                        />
                                        <span class="truncate" :title="`${machine.name} (${machine.code})`">
                                            {{ machine.name }}
                                        </span>
                                    </label>
                                </div>
                                <div v-else class="mt-2 text-[11px] text-slate-400 italic">
                                    Belum ada mesin di area ini.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                        <SecondaryButton type="button" @click="closeModal">
                            Batal
                        </SecondaryButton>

                        <PrimaryButton :disabled="form.processing">
                            {{ isEditing ? 'Simpan Perubahan' : 'Tambah Part Number' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ======================================================= -->
        <!-- MODAL IMPORT EXCEL PART NUMBER                          -->
        <!-- ======================================================= -->
        <Modal :show="isImportModalOpen" @close="closeImportModal" max-width="lg">
            <div class="p-6 sm:p-7 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Import Master Data Part Number
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Unggah file Excel untuk menambah atau memperbarui katalog part number secara massal.
                        </p>
                    </div>
                    <button @click="closeImportModal" :disabled="isUploading" class="text-slate-400 hover:text-slate-600 p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-3.5 bg-emerald-50/80 rounded-xl border border-emerald-100 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-emerald-900">Format Kolom Excel:</p>
                        <p class="text-[11px] text-emerald-700 font-mono mt-0.5">pn_baan | description</p>
                    </div>
                    <a
                        :href="route('part-numbers.template')"
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
                        <AlertCircle class="w-4 h-4 shrink-0" />
                        <span>Terdapat {{ importErrors.length }} baris bermasalah:</span>
                    </div>
                    <ul class="space-y-1 pl-1 text-[11px]">
                        <li v-for="(err, idx) in importErrors" :key="idx" class="flex items-start gap-1.5">
                            <span class="font-bold text-red-900">•</span>
                            <span>{{ typeof err === 'object' && err !== null ? (err.message || JSON.stringify(err)) : err }}</span>
                        </li>
                    </ul>
                </div>

                <form @submit.prevent="submitImport" class="space-y-4">
                    <div>
                        <InputLabel for="part_import_file" value="Pilih File Excel (.xlsx, .xls) *" />
                        <input
                            ref="importInputRef"
                            id="part_import_file"
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
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span>{{ isUploading ? 'Mengimpor...' : 'Import' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Confirm Delete Modal -->
        <ConfirmModal
            :show="confirmModal.show"
            title="Konfirmasi Hapus Part Number"
            :message="`Yakin ingin menghapus part number '${confirmModal.name}'? Data part tidak dapat dihapus jika memiliki riwayat transaksi konsumsi.`"
            confirm-label="Hapus Part Number"
            variant="danger"
            :loading="isDeleting"
            @confirm="doDelete"
            @cancel="closeConfirm"
        />
    </AppLayout>
</template>
<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Search, RotateCcw, Download, Upload } from 'lucide-vue-next';

const page = usePage();

const isAdmin = computed(() => {
    const user = page.props.auth?.user;

    return user?.role === 'admin' || user?.is_admin === true;
});

const props = defineProps({
    partNumbers: {
        type: Object,
        required: true,
    },

    mappings: {
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
            search_part: '',
            area_id: '',
            tab: 'mapping',
            per_page: 10,
        }),
    },
});

// ==========================================
// TAB STATE
// ==========================================
const activeTab = ref(props.filters.tab || 'mapping');

const switchTab = (tab) => {
    activeTab.value = tab;

    router.get(
        route('mapping.index'),
        {
            ...props.filters,
            tab,
            per_page: filters.value.per_page,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

// ==========================================
// FILTER STATE & ACTIONS
// ==========================================
const filters = ref({
    search: props.filters.search || '',
    search_part: props.filters.search_part || '',
    area_id: props.filters.area_id || '',
    per_page: props.filters.per_page || 10,
});

const handleMappingSearch = () => {
    router.get(
        route('mapping.index'),
        {
            tab: 'mapping',
            search: filters.value.search,
            area_id: filters.value.area_id,
            per_page: filters.value.per_page,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

const handleMappingReset = () => {
    filters.value.search = '';
    filters.value.area_id = '';
    filters.value.per_page = 10;

    router.get(
        route('mapping.index'),
        {
            tab: 'mapping',
        },
        {
            preserveState: false,
        }
    );
};

const handlePartsSearch = () => {
    router.get(
        route('mapping.index'),
        {
            tab: 'parts',
            search_part: filters.value.search_part,
            per_page: filters.value.per_page,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

const handlePartsReset = () => {
    filters.value.search_part = '';
    filters.value.per_page = 10;

    router.get(
        route('mapping.index'),
        {
            tab: 'parts',
        },
        {
            preserveState: false,
        }
    );
};

// ==========================================
// TABLE COLUMNS
// ==========================================
const mappingColumns = [
    {
        key: 'pn_baan',
        label: 'PN BAAN',
        width: 'w-44',
    },
    {
        key: 'part_desc',
        label: 'Deskripsi',
    },
    {
        key: 'area_name',
        label: 'Area',
        width: 'w-36',
    },
    {
        key: 'machine_name',
        label: 'Machine',
        width: 'w-36',
    },
];

const partsColumns = [
    {
        key: 'pn_baan',
        label: 'PN BAAN',
        width: 'w-44',
    },
    {
        key: 'description',
        label: 'Deskripsi',
    },
    {
        key: 'areas_count',
        label: 'Mapped Area',
        align: 'center',
        width: 'w-36',
    },
    {
        key: 'machines_count',
        label: 'Mapped Machine',
        align: 'center',
        width: 'w-36',
    },
];

// ==========================================
// MODAL ASSIGN MAPPING STATE & LOGIC
// ==========================================
const showAssignModal = ref(false);
const selectedPart = ref(null);
const isLoadingDetail = ref(false);
const isSavingMapping = ref(false);
const selectedAreaIds = ref([]);
const selectedMachineIds = ref([]);

const openAssignModal = async (part) => {
    selectedPart.value = part;
    selectedAreaIds.value = [];
    selectedMachineIds.value = [];
    showAssignModal.value = true;
    isLoadingDetail.value = true;

    try {
        const res = await fetch(
            `/mapping/${part.id || part.part_id}/detail`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        );

        const data = await res.json();

        selectedAreaIds.value = data.area_ids || [];
        selectedMachineIds.value = data.machine_ids || [];
    } catch (e) {
        console.error('Gagal mengambil data mapping:', e);
    } finally {
        isLoadingDetail.value = false;
    }
};

const closeAssignModal = () => {
    showAssignModal.value = false;
    selectedPart.value = null;
    selectedAreaIds.value = [];
    selectedMachineIds.value = [];
};

const activeAreas = computed(() => {
    return props.areas.filter((area) =>
        selectedAreaIds.value.includes(area.id)
    );
});

const handleAreaCheckboxChange = () => {
    const validMachineIds = [];

    props.areas.forEach((area) => {
        if (
            selectedAreaIds.value.includes(area.id) &&
            Array.isArray(area.machines)
        ) {
            area.machines.forEach((machine) => {
                validMachineIds.push(machine.id);
            });
        }
    });

    selectedMachineIds.value = selectedMachineIds.value.filter((id) =>
        validMachineIds.includes(id)
    );
};

const selectAllAreas = () => {
    selectedAreaIds.value = props.areas.map((area) => area.id);

    handleAreaCheckboxChange();
};

const clearAllAreas = () => {
    selectedAreaIds.value = [];
    selectedMachineIds.value = [];
};

const toggleSelectAllMachinesInArea = (area) => {
    if (!area.machines || area.machines.length === 0) {
        return;
    }

    const areaMachineIds = area.machines.map((machine) => machine.id);

    const allSelected = areaMachineIds.every((id) =>
        selectedMachineIds.value.includes(id)
    );

    if (allSelected) {
        selectedMachineIds.value = selectedMachineIds.value.filter(
            (id) => !areaMachineIds.includes(id)
        );
    } else {
        const set = new Set(selectedMachineIds.value);

        areaMachineIds.forEach((id) => {
            set.add(id);
        });

        selectedMachineIds.value = Array.from(set);
    }
};

const isAllMachinesInAreaSelected = (area) => {
    if (!area.machines || area.machines.length === 0) {
        return false;
    }

    return area.machines.every((machine) =>
        selectedMachineIds.value.includes(machine.id)
    );
};

const saveMapping = () => {
    if (!selectedPart.value) {
        return;
    }

    if (selectedAreaIds.value.length === 0) {
        alert('Pilih minimal satu area untuk mapping.');
        return;
    }

    isSavingMapping.value = true;

    router.post(
        route('mapping.sync'),
        {
            part_number_id:
                selectedPart.value.id || selectedPart.value.part_id,
            area_ids: selectedAreaIds.value,
            machine_ids: selectedMachineIds.value,
        },
        {
            preserveScroll: true,

            onFinish: () => {
                isSavingMapping.value = false;
            },

            onSuccess: () => {
                closeAssignModal();
            },
        }
    );
};

// ==========================================
// MODAL IMPORT MAPPING EXCEL
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
    if (!importForm.file) {
        return;
    }

    importForm.post(
        route('mapping.import'),
        {
            onSuccess: () => {
                closeImportModal();
            },
        }
    );
};
</script>

<template>
    <AppLayout>
        <Head title="Mapping Part Number" />

        <template #header>
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-800">
                    Mapping Part Number
                </h2>

                <p class="text-xs text-slate-500 mt-0.5">
                    Kelola assignment part ke area dan mesin operasional.
                </p>
            </div>
        </template>

        <div class="p-6 space-y-4">

            <!-- ======================================================= -->
            <!-- TAB 1: DATA MAPPING                                     -->
            <!-- ======================================================= -->
            <div
                v-if="activeTab === 'mapping'"
                class="space-y-4"
            >

                <!-- TAB SWITCHER — ATAS KANAN -->
                <div class="flex justify-end">
                    <div class="inline-flex p-0.5 bg-slate-100 rounded-md border border-slate-200">

                        <button
                            type="button"
                            @click="switchTab('mapping')"
                            :class="[
                                'inline-flex items-center gap-1 px-2.5 py-1.5 rounded text-xs font-semibold transition-all',
                                activeTab === 'mapping'
                                    ? 'bg-gray-900 text-white shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900'
                            ]"
                        >
                            <span>Data Mapping</span>
                        </button>

                        <button
                            type="button"
                            @click="switchTab('parts')"
                            :class="[
                                'inline-flex items-center gap-1 px-2.5 py-1.5 rounded text-xs font-semibold transition-all',
                                activeTab === 'parts'
                                    ? 'bg-gray-900 text-white shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900'
                            ]"
                        >
                            <span>Daftar Part</span>
                        </button>

                    </div>
                </div>


                <!-- FILTER + ACTION CARD -->
                <div class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border border-gray-200">

                    <div class="flex flex-wrap items-end gap-2">

                        <!-- FILTER FIELDS — KIRI -->
                        <div class="flex items-end gap-2">

                            <!-- Field Search -->
                            <div class="flex flex-col gap-0.5">

                                <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                    Pencarian Part
                                </label>

                                <div class="relative">

                                    <Search
                                        class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400"
                                    />

                                    <input
                                        v-model="filters.search"
                                        type="text"
                                        placeholder="Cari PN BAAN atau deskripsi..."
                                        class="w-64 pl-8 pr-3 py-1.5 border border-gray-200 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
                                        @keydown.enter="handleMappingSearch"
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
                                    class="px-2.5 py-1.5 border border-gray-200 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900 min-w-28"
                                >
                                    <option value="">
                                        -- Semua Area --
                                    </option>

                                    <option
                                        v-for="area in areas"
                                        :key="area.id"
                                        :value="area.id"
                                    >
                                        {{ area.name }} ({{ area.code }})
                                    </option>
                                </select>
                            </div>

                            <!-- Field Per Page -->
                            <div class="flex flex-col gap-0.5">
                                <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                    Tampilkan
                                </label>

                                <select
                                    v-model.number="filters.per_page"
                                    @change="handleMappingSearch"
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
                                    @click="handleMappingSearch"
                                    class="px-3 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-md hover:bg-gray-700 transition"
                                >
                                    Cari
                                </button>

                                <button
                                    type="button"
                                    @click="handleMappingReset"
                                    class="p-1.5 border border-gray-200 rounded-md text-gray-500 hover:bg-gray-50 transition"
                                    title="Reset filter"
                                >
                                    <RotateCcw class="w-3.5 h-3.5" />
                                </button>

                            </div>

                        </div>


                        <!-- ACTION BUTTONS — KANAN -->
                        <div class="flex items-end gap-1 ml-auto">

                            <!-- Template -->
                            <a
                                :href="route('mapping.template')"
                                class="flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 bg-gray-100 text-xs font-medium text-gray-700 rounded-md hover:bg-gray-200 transition"
                                download
                                title="Download template Excel mapping"
                            >
                                <Download class="w-3.5 h-3.5 text-gray-600" />
                                Template
                            </a>


                            <!-- Import Excel -->
                            <button
                                v-if="isAdmin"
                                type="button"
                                @click="openImportModal"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-md hover:bg-green-700 transition"
                            >
                                <Upload class="w-3.5 h-3.5 text-white" />
                                Import Excel
                            </button>

                        </div>

                    </div>

                </div>


                <!-- Data Table Mapping -->
                <DataTable
                    :columns="mappingColumns"
                    :data="mappings"
                >

                    <template #cell-pn_baan="{ value }">
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-bold bg-blue-50 text-blue-800 border border-blue-200"
                        >
                            {{ value }}
                        </span>
                    </template>


                    <template #cell-part_desc="{ value }">
                        <span
                            class="text-slate-700 max-w-sm truncate block"
                            :title="value"
                        >
                            {{ value || '-' }}
                        </span>
                    </template>


                    <template #cell-area_name="{ row }">
                        <span class="font-medium text-slate-800">
                            {{ row.area_name }}
                        </span>
                    </template>


                    <template #cell-machine_name="{ row }">

                        <span
                            v-if="row.machine_name"
                            class="font-medium text-slate-800"
                        >
                            {{ row.machine_name }}
                        </span>

                        <span
                            v-else
                            class="text-slate-400 italic text-[11px]"
                        >
                            Semua Mesin di Area
                        </span>

                    </template>


                    <template #actions="{ row }">
                        <button
                            type="button"
                            @click="openAssignModal(row)"
                            class="text-blue-600 hover:text-blue-900 hover:underline font-semibold inline-flex items-center gap-1 text-xs"
                        >
                            Edit
                        </button>
                    </template>


                    <template #empty>
                        <p>
                            Belum ada data relasi mapping ditemukan.
                        </p>
                    </template>

                </DataTable>

            </div>


            <!-- ======================================================= -->
            <!-- TAB 2: DAFTAR PART NUMBER                               -->
            <!-- ======================================================= -->
            <div
                v-else-if="activeTab === 'parts'"
                class="space-y-4"
            >

                <!-- TAB SWITCHER — ATAS KANAN -->
                <div class="flex justify-end">
                    <div class="inline-flex p-0.5 bg-slate-100 rounded-md border border-slate-200">

                        <button
                            type="button"
                            @click="switchTab('mapping')"
                            :class="[
                                'inline-flex items-center gap-1 px-2.5 py-1.5 rounded text-xs font-semibold transition-all',
                                activeTab === 'mapping'
                                    ? 'bg-gray-900 text-white shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900'
                            ]"
                        >
                            <span>Data Mapping</span>
                        </button>

                        <button
                            type="button"
                            @click="switchTab('parts')"
                            :class="[
                                'inline-flex items-center gap-1 px-2.5 py-1.5 rounded text-xs font-semibold transition-all',
                                activeTab === 'parts'
                                    ? 'bg-gray-900 text-white shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900'
                            ]"
                        >
                            <span>Daftar Part</span>
                        </button>

                    </div>
                </div>


                <!-- FILTER + ACTION CARD -->
                <div class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border border-gray-200">

                    <div class="flex flex-wrap items-end gap-2">

                        <!-- FILTER FIELDS — KIRI -->
                        <div class="flex items-end gap-2">

                            <!-- Field Search -->
                            <div class="flex flex-col gap-0.5">

                                <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                    Pencarian Part Number
                                </label>

                                <div class="relative">

                                    <Search
                                        class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400"
                                    />

                                    <input
                                        v-model="filters.search_part"
                                        type="text"
                                        placeholder="Cari PN BAAN atau deskripsi..."
                                        class="w-72 pl-8 pr-3 py-1.5 border border-gray-200 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
                                        @keydown.enter="handlePartsSearch"
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
                                    @change="handlePartsSearch"
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
                                    @click="handlePartsSearch"
                                    class="px-3 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-md hover:bg-gray-700 transition"
                                >
                                    Cari
                                </button>

                                <button
                                    type="button"
                                    @click="handlePartsReset"
                                    class="p-1.5 border border-gray-200 rounded-md text-gray-500 hover:bg-gray-50 transition"
                                    title="Reset filter"
                                >
                                    <RotateCcw class="w-3.5 h-3.5" />
                                </button>

                            </div>

                        </div>


                        <!-- ACTION BUTTONS — KANAN -->
                        <div class="flex items-end gap-1 ml-auto">

                            <!-- Template -->
                            <a
                                :href="route('mapping.template')"
                                class="flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 bg-gray-100 text-xs font-medium text-gray-700 rounded-md hover:bg-gray-200 transition"
                                download
                                title="Download template Excel mapping"
                            >
                                <Download class="w-3.5 h-3.5 text-gray-600" />
                                Template
                            </a>


                            <!-- Import Excel -->
                            <button
                                v-if="isAdmin"
                                type="button"
                                @click="openImportModal"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-md hover:bg-green-700 transition"
                            >
                                <Upload class="w-3.5 h-3.5 text-white" />
                                Import Excel
                            </button>

                        </div>

                    </div>

                </div>


                <!-- Data Table Parts -->
                <DataTable
                    :columns="partsColumns"
                    :data="partNumbers"
                >

                    <template #cell-pn_baan="{ value }">
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-bold bg-blue-50 text-blue-800 border border-blue-200"
                        >
                            {{ value }}
                        </span>
                    </template>


                    <template #cell-description="{ value }">
                        <span
                            class="text-slate-700 max-w-sm truncate block"
                            :title="value"
                        >
                            {{ value || '-' }}
                        </span>
                    </template>


                    <template #cell-areas_count="{ value }">
                        <span
                            :class="[
                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border',
                                value > 0
                                    ? 'bg-blue-50 text-blue-700 border-blue-200'
                                    : 'bg-slate-50 text-slate-400 border-slate-200'
                            ]"
                        >
                            {{ value }} area
                        </span>
                    </template>


                    <template #cell-machines_count="{ value }">
                        <span
                            :class="[
                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border',
                                value > 0
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                    : 'bg-slate-50 text-slate-400 border-slate-200'
                            ]"
                        >
                            {{ value }} mesin
                        </span>
                    </template>


                    <template #actions="{ row }">
                        <button
                            type="button"
                            @click="openAssignModal(row)"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-900 hover:bg-slate-800 text-white rounded-md text-xs font-semibold shadow-xs transition-colors"
                        >
                            <span>Atur Mapping</span>
                        </button>
                    </template>


                    <template #empty>
                        <p>
                            Tidak ada part number ditemukan.
                        </p>
                    </template>

                </DataTable>

            </div>

        </div>


        <!-- ======================================================= -->
        <!-- MODAL ASSIGN MAPPING                                    -->
        <!-- ======================================================= -->
        <Modal
            :show="showAssignModal"
            @close="closeAssignModal"
        >

            <div class="p-6 sm:p-8 space-y-6">

                <!-- Header Modal -->
                <div class="flex items-start justify-between pb-3 border-b border-slate-100">

                    <div>

                        <h3 class="text-base font-bold text-slate-900">
                            Atur Mapping —
                            <span class="text-blue-600 font-mono">
                                {{ selectedPart?.pn_baan }}
                            </span>
                        </h3>

                        <p class="text-xs text-slate-500 mt-0.5">
                            {{
                                selectedPart?.description ||
                                selectedPart?.part_desc ||
                                'Tanpa Deskripsi'
                            }}
                        </p>

                    </div>


                    <button
                        type="button"
                        @click="closeAssignModal"
                        class="text-slate-400 hover:text-slate-600 p-1"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>

                </div>


                <!-- Loading Detail State -->
                <div
                    v-if="isLoadingDetail"
                    class="py-12 text-center text-slate-400 text-xs animate-pulse"
                >
                    Memuat konfigurasi relasi saat ini...
                </div>


                <div
                    v-else
                    class="space-y-6"
                >

                    <!-- SECTION A: PILIH AREA -->
                    <div class="space-y-3">

                        <div class="flex items-center justify-between">

                            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                                Pilih Area
                            </label>

                            <div class="flex items-center gap-3 text-xs">

                                <button
                                    type="button"
                                    @click="selectAllAreas"
                                    class="text-blue-600 hover:text-blue-800 font-semibold"
                                >
                                    Pilih Semua
                                </button>

                                <span class="text-slate-300">
                                    |
                                </span>

                                <button
                                    type="button"
                                    @click="clearAllAreas"
                                    class="text-slate-500 hover:text-slate-700 font-semibold"
                                >
                                    Hapus Semua
                                </button>

                            </div>

                        </div>


                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-48 overflow-y-auto pr-1">

                            <label
                                v-for="area in areas"
                                :key="area.id"
                                :class="[
                                    'flex items-center gap-3 p-3 rounded-lg border text-xs cursor-pointer transition-all',
                                    selectedAreaIds.includes(area.id)
                                        ? 'bg-blue-50/70 border-blue-400 text-blue-900 font-semibold'
                                        : 'bg-white border-slate-200 hover:bg-slate-50 text-slate-700'
                                ]"
                            >

                                <input
                                    type="checkbox"
                                    :value="area.id"
                                    v-model="selectedAreaIds"
                                    @change="handleAreaCheckboxChange"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4"
                                />

                                <div class="flex-1 flex items-center justify-between">

                                    <span>
                                        {{ area.name }}
                                    </span>

                                    <span class="font-mono text-[11px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">
                                        {{ area.code }}
                                    </span>

                                </div>

                            </label>

                        </div>

                    </div>


                    <!-- SECTION B: PILIH MACHINE -->
                    <div class="space-y-3 pt-4 border-t border-slate-100">

                        <div class="flex items-center justify-between">

                            <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                                Pilih Machine
                            </label>

                            <span class="text-xs text-slate-500">
                                {{ selectedMachineIds.length }} mesin dipilih
                            </span>

                        </div>


                        <!-- Belum pilih area -->
                        <div
                            v-if="selectedAreaIds.length === 0"
                            class="p-4 rounded-lg bg-slate-50 border border-dashed border-slate-200 text-center text-xs text-slate-400"
                        >
                            Pilih area terlebih dahulu untuk menampilkan daftar mesin.
                        </div>


                        <!-- Tampilkan Mesin per Area -->
                        <div
                            v-else
                            class="space-y-3 max-h-60 overflow-y-auto pr-1"
                        >

                            <div
                                v-for="area in activeAreas"
                                :key="area.id"
                                class="border border-slate-200 rounded-xl p-3.5 bg-slate-50/50 space-y-2.5"
                            >

                                <div class="flex items-center justify-between pb-1.5 border-b border-slate-200">

                                    <div class="flex items-center gap-2">

                                        <span class="h-2 w-2 rounded-full bg-blue-600"></span>

                                        <span class="font-bold text-xs text-slate-800">
                                            {{ area.name }}
                                        </span>

                                        <span class="text-[10px] font-mono text-slate-500">
                                            ({{ area.code }})
                                        </span>

                                    </div>


                                    <button
                                        v-if="area.machines && area.machines.length > 0"
                                        type="button"
                                        @click="toggleSelectAllMachinesInArea(area)"
                                        class="text-[11px] font-semibold text-blue-600 hover:text-blue-800"
                                    >
                                        {{
                                            isAllMachinesInAreaSelected(area)
                                                ? 'Batal Semua'
                                                : 'Pilih Semua'
                                        }}
                                    </button>

                                </div>


                                <div
                                    v-if="!area.machines || area.machines.length === 0"
                                    class="text-xs text-slate-400 italic py-1"
                                >
                                    Belum ada data mesin di area ini.
                                </div>


                                <div
                                    v-else
                                    class="grid grid-cols-1 sm:grid-cols-2 gap-2"
                                >

                                    <label
                                        v-for="m in area.machines"
                                        :key="m.id"
                                        :class="[
                                            'flex items-center gap-2 p-2 rounded-lg border text-xs cursor-pointer transition-all',
                                            selectedMachineIds.includes(m.id)
                                                ? 'bg-emerald-50/80 border-emerald-400 text-emerald-900 font-semibold'
                                                : 'bg-white border-slate-200 hover:bg-slate-50 text-slate-700'
                                        ]"
                                    >

                                        <input
                                            type="checkbox"
                                            :value="m.id"
                                            v-model="selectedMachineIds"
                                            class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 h-4 w-4"
                                        />

                                        <div class="flex-1 flex items-center justify-between">

                                            <span>
                                                {{ m.name }}
                                            </span>

                                            <span class="font-mono text-[10px] px-1 py-0.5 rounded bg-slate-100 text-slate-500">
                                                {{ m.code }}
                                            </span>

                                        </div>

                                    </label>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Footer Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">

                        <SecondaryButton
                            type="button"
                            @click="closeAssignModal"
                            :disabled="isSavingMapping"
                        >
                            Batal
                        </SecondaryButton>

                        <PrimaryButton
                            type="button"
                            @click="saveMapping"
                            :disabled="
                                isSavingMapping ||
                                selectedAreaIds.length === 0
                            "
                        >
                            {{
                                isSavingMapping
                                    ? 'Menyimpan...'
                                    : 'Simpan'
                            }}
                        </PrimaryButton>

                    </div>

                </div>

            </div>

        </Modal>


        <!-- ======================================================= -->
        <!-- MODAL IMPORT EXCEL MAPPING                              -->
        <!-- ======================================================= -->
        <Modal
            :show="isImportModalOpen"
            @close="closeImportModal"
        >

            <div class="p-6 sm:p-8 space-y-5">

                <div class="flex items-center justify-between pb-3 border-b border-slate-100">

                    <div>

                        <h3 class="text-base font-bold text-slate-900">
                            Import Mapping Part ↔ Area & Machine
                        </h3>

                        <p class="text-xs text-slate-500 mt-0.5">
                            Unggah file Excel untuk memetakan part number ke area dan mesin secara massal.
                        </p>

                    </div>


                    <button
                        type="button"
                        @click="closeImportModal"
                        class="text-slate-400 hover:text-slate-600 p-1"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>

                </div>


                <form
                    @submit.prevent="submitImportForm"
                    class="space-y-4"
                >

                    <div class="p-3 bg-blue-50 rounded-xl border border-blue-100 text-xs">

                        <p class="font-bold text-blue-900">
                            Format Kolom Excel:
                        </p>

                        <p class="text-blue-700 font-mono text-[11px] mt-0.5">
                            pn_baan | area_code | machine_code
                        </p>

                        <p class="text-slate-500 text-[11px] mt-1">
                            * Kolom
                            <code>machine_code</code>
                            bersifat opsional jika hanya ingin memetakan ke area.
                        </p>

                    </div>


                    <div>

                        <InputLabel
                            for="mapping_import_file"
                            value="Pilih File Excel (.xlsx, .xls) *"
                        />

                        <input
                            ref="importInputRef"
                            id="mapping_import_file"
                            type="file"
                            accept=".xlsx, .xls"
                            @change="handleImportFileChange"
                            class="mt-1 block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            required
                        />

                        <InputError
                            class="mt-1"
                            :message="importForm.errors.file"
                        />

                    </div>


                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">

                        <SecondaryButton
                            type="button"
                            @click="closeImportModal"
                        >
                            Batal
                        </SecondaryButton>

                        <PrimaryButton
                            :disabled="
                                importForm.processing ||
                                !importForm.file
                            "
                        >
                            {{
                                importForm.processing
                                    ? 'Mengunggah...'
                                    : 'Upload & Proses'
                            }}
                        </PrimaryButton>

                    </div>

                </form>

            </div>

        </Modal>

    </AppLayout>
</template>

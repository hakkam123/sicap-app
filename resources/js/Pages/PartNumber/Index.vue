<script setup>
import { ref, computed } from 'vue';
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
import { Search, RotateCcw, Plus, X, Layers } from 'lucide-vue-next';

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
    areas: {
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
    { key: 'pn_baan', label: 'PN BAAN', width: 'w-48' },
    { key: 'description', label: 'Deskripsi' },
    { key: 'price_per_unit', label: 'Harga/Unit', align: 'right', width: 'w-36' },
    { key: 'areas_count', label: 'Area Mapping', align: 'center', width: 'w-32' },
    { key: 'machines_count', label: 'Machine Mapping', align: 'center', width: 'w-36' },
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

// ==========================================
// 3. CREATE / EDIT MODAL FORM
// ==========================================
const isModalOpen = ref(false);
const editingPart = ref(null);
const isEditing = computed(() => !!editingPart.value);

const form = useForm({
    pn_baan: '',
    description: '',
    price_per_unit: '',
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
    form.description = part.description || '';
    form.price_per_unit = part.price_per_unit !== null && part.price_per_unit !== undefined ? part.price_per_unit : '';
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
        // Otomatis centang area jika mesinnya dipilih
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
// 4. DELETE CONFIRMATION MODAL
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
                    Kelola katalog nomor part (PN BAAN), harga satuan, dan relasi mapping.
                </p>
            </div>
        </template>

        <div class="p-6 space-y-4">
            <!-- FILTER + ACTION CARD -->
            <div class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border border-gray-200">
                <div class="flex flex-wrap items-end gap-2">

                    <!-- FILTER FIELDS — KIRI -->
                    <div class="flex items-end gap-2">

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
                                    placeholder="Cari PN BAAN atau deskripsi..."
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
                        class="flex items-end gap-1 ml-auto"
                    >
                        <button
                            type="button"
                            @click="openCreateModal"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-md hover:bg-gray-700 transition"
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
                <template #cell-pn_baan="{ value }">
                    <span class="font-mono text-xs font-bold text-blue-700">{{ value }}</span>
                </template>

                <template #cell-price_per_unit="{ value }">
                    <span class="font-semibold text-slate-900 whitespace-nowrap text-xs">
                        {{ formatRupiah(value) }}
                    </span>
                </template>

                <template #cell-areas_count="{ value }">
                    <span class="bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded font-semibold">
                        {{ value }} area
                    </span>
                </template>

                <template #cell-machines_count="{ value }">
                    <span class="bg-purple-50 text-purple-700 text-xs px-2 py-0.5 rounded font-semibold">
                        {{ value }} machine
                    </span>
                </template>

                <template #actions="{ row }">
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
                        class="text-red-600 hover:text-red-900 hover:underline font-semibold inline-flex items-center gap-1 text-xs ml-3"
                    >
                        Hapus
                    </button>
                </template>

                <template #empty>
                    <p>Tidak ada data part number ditemukan.</p>
                </template>
            </DataTable>
        </div>

        <!-- MODAL FORM CREATE / EDIT PART NUMBER -->
        <Modal :show="isModalOpen" @close="closeModal" max-width="2xl">
            <div class="p-6 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            {{ isEditing ? 'Edit Data Part Number' : 'Tambah Part Number Baru' }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ isEditing ? 'Perbarui data part number, harga, serta mapping area & mesin.' : 'Masukkan nomor part baru beserta penugasan area/mesin.' }}
                        </p>
                    </div>
                    <button @click="closeModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

                        <!-- Price Per Unit -->
                        <div>
                            <InputLabel for="price_per_unit" value="Harga Satuan (Rp)" />
                            <TextInput
                                id="price_per_unit"
                                v-model="form.price_per_unit"
                                type="number"
                                step="0.01"
                                min="0"
                                class="mt-1 block w-full text-xs"
                                placeholder="Contoh: 3100000"
                            />
                            <InputError class="mt-1" :message="form.errors.price_per_unit" />
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <InputLabel for="description" value="Deskripsi Sparepart" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="2"
                            class="mt-1 block w-full border-gray-300 focus:border-slate-900 focus:ring-slate-900 rounded-md shadow-sm text-xs"
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
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
import { Search, RotateCcw, Plus, X, Upload, Download, AlertCircle } from 'lucide-vue-next';

const page = usePage();
const isAdmin = computed(() => {
    const user = page.props.auth?.user;
    return user?.role === 'admin' || user?.is_admin === true;
});

const props = defineProps({
    machines: {
        type: Object,
        required: true,
    },
    areas: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '', area_id: '', per_page: 10 }),
    },
});

// ==========================================
// 1. FILTER STATE & ACTIONS
// ==========================================
const filters = ref({
    search: props.filters.search || '',
    area_id: props.filters.area_id || '',
    per_page: props.filters.per_page || 10,
});

const applyFilter = () => {
    router.get(
        route('machines.index'),
        {
            search: filters.value.search,
            area_id: filters.value.area_id,
            per_page: filters.value.per_page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const resetFilter = () => {
    filters.value.search = '';
    filters.value.area_id = '';
    filters.value.per_page = 10;
    router.get(route('machines.index'), {}, { preserveState: false });
};

// ==========================================
// 2. DATA TABLE COLUMNS
// ==========================================
const tableColumns = [
    { key: 'code', label: 'Kode', width: 'w-36' },
    { key: 'name', label: 'Nama Machine' },
    { key: 'area', label: 'Area', width: 'w-44' },
    { key: 'description', label: 'Deskripsi' },
];

// ==========================================
// 3. CREATE / EDIT MODAL FORM
// ==========================================
const isModalOpen = ref(false);
const editingMachine = ref(null);
const isEditing = computed(() => !!editingMachine.value);

const form = useForm({
    area_id: '',
    code: '',
    name: '',
    description: '',
});

const openCreateModal = () => {
    editingMachine.value = null;
    form.reset();
    form.clearErrors();
    if (filters.value.area_id) {
        form.area_id = filters.value.area_id;
    }
    isModalOpen.value = true;
};

const openEditModal = (machine) => {
    editingMachine.value = machine;
    form.clearErrors();
    form.area_id = machine.area_id || '';
    form.code = machine.code || '';
    form.name = machine.name || '';
    form.description = machine.description || '';
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingMachine.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('machines.update', editingMachine.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('machines.store'), {
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
    confirmModal.value = { show: true, id: row.id, name: row.name };
};

const closeConfirm = () => {
    confirmModal.value = { show: false, id: null, name: '' };
};

const doDelete = () => {
    isDeleting.value = true;
    router.delete(route('machines.destroy', confirmModal.value.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            closeConfirm();
        },
    });
};

import { useImportPolling } from '@/composables/useImportPolling';

// ==========================================
// 5. IMPORT EXCEL MODAL
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
        url: route('machines.import'),
        file: importFile.value,
        title: 'Import Machine',
        onSuccess: () => {
            closeImportModal();
            router.reload({ preserveScroll: true });
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Master Data Machine" />

        <template #header>
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-800">
                    Master Data Machine
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Kelola data mesin dan stasiun kerja per area produksi.
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
                                    placeholder="Cari nama atau kode machine..."
                                    class="w-64 pl-8 pr-3 py-1.5 border border-gray-200 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent"
                                    @keydown.enter="applyFilter"
                                />
                            </div>
                        </div>

                        <!-- Field Select Area -->
                        <div class="flex flex-col gap-0.5">
                            <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                Area
                            </label>

                            <select
                                v-model="filters.area_id"
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

                    <!-- ACTION BUTTON — KANAN -->
                    <div
                        v-if="isAdmin"
                        class="flex items-end gap-2 ml-auto"
                    >
                        <button
                            type="button"
                            @click="openImportModal"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white text-xs font-medium rounded-md hover:bg-emerald-700 transition"
                        >
                            <Upload class="w-3.5 h-3.5 text-white" />
                            Import Excel
                        </button>

                        <button
                            type="button"
                            @click="openCreateModal"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-md hover:bg-gray-700 transition"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            Tambah Machine
                        </button>
                    </div>

                </div>
            </div>

            <!-- DATA TABLE -->
            <DataTable
                :columns="tableColumns"
                :data="machines"
            >
                <template #cell-code="{ value }">
                    <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded font-bold text-slate-800">{{ value }}</span>
                </template>

                <template #cell-area="{ row }">
                    <span v-if="row.area" class="font-medium text-slate-800">
                        {{ row.area?.name }}
                    </span>
                    <span v-else class="text-slate-400 italic text-xs">Tanpa Area</span>
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
                    <p>Tidak ada data machine ditemukan.</p>
                </template>
            </DataTable>
        </div>

        <!-- MODAL FORM CREATE / EDIT MACHINE -->
        <Modal :show="isModalOpen" @close="closeModal" max-width="lg">
            <div class="p-6 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            {{ isEditing ? 'Edit Data Machine' : 'Tambah Machine Baru' }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ isEditing ? 'Perbarui informasi mesin atau stasiun kerja.' : 'Tautkan mesin baru ke area kerja produksi.' }}
                        </p>
                    </div>
                    <button @click="closeModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- Area Dropdown -->
                    <div>
                        <InputLabel for="area_id" value="Area Produksi *" />
                        <select
                            id="area_id"
                            v-model="form.area_id"
                            class="mt-1 block w-full border-gray-300 focus:border-slate-900 focus:ring-slate-900 rounded-md shadow-sm text-xs"
                            required
                        >
                            <option value="" disabled>-- Pilih Area Induk --</option>
                            <option
                                v-for="area in areas"
                                :key="area.id"
                                :value="area.id"
                            >
                                {{ area.name }} ({{ area.code }})
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.area_id" />
                    </div>

                    <!-- Code -->
                    <div>
                        <InputLabel for="code" value="Kode Mesin / Stasiun *" />
                        <TextInput
                            id="code"
                            v-model="form.code"
                            type="text"
                            class="mt-1 block w-full uppercase font-mono text-xs"
                            placeholder="Contoh: AUTOSCREW, AOI_3D, PRINTER"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.code" />
                    </div>

                    <!-- Name -->
                    <div>
                        <InputLabel for="name" value="Nama Mesin / Stasiun *" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full text-xs"
                            placeholder="Contoh: Auto Screw Robot Station"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <!-- Description -->
                    <div>
                        <InputLabel for="description" value="Deskripsi" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full border-gray-300 focus:border-slate-900 focus:ring-slate-900 rounded-md shadow-sm text-xs"
                            placeholder="Keterangan opsional mengenai mesin ini..."
                        ></textarea>
                        <InputError class="mt-1" :message="form.errors.description" />
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                        <SecondaryButton type="button" @click="closeModal">
                            Batal
                        </SecondaryButton>

                        <PrimaryButton :disabled="form.processing">
                            {{ isEditing ? 'Simpan Perubahan' : 'Tambah Machine' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL IMPORT EXCEL MACHINE -->
        <Modal :show="isImportModalOpen" @close="closeImportModal" max-width="lg">
            <div class="p-6 sm:p-7 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Import Master Data Machine
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Unggah file Excel untuk menambah atau memperbarui data mesin secara massal.
                        </p>
                    </div>
                    <button @click="closeImportModal" :disabled="isUploading" class="text-slate-400 hover:text-slate-600 p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-3.5 bg-emerald-50/80 rounded-xl border border-emerald-100 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-emerald-900">Format Kolom Excel:</p>
                        <p class="text-[11px] text-emerald-700 font-mono mt-0.5">area_code | code | name | description</p>
                    </div>
                    <a
                        :href="route('machines.template')"
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

                <form @submit.prevent="submitImport" class="space-y-4">
                    <div>
                        <InputLabel for="machine_import_file" value="Pilih File Excel (.xlsx, .xls) *" />
                        <input
                            ref="importInputRef"
                            id="machine_import_file"
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
            title="Konfirmasi Hapus Machine"
            :message="`Yakin ingin menghapus machine '${confirmModal.name}'? Data mesin tidak dapat dihapus jika memiliki transaksi konsumsi.`"
            confirm-label="Hapus Machine"
            variant="danger"
            :loading="isDeleting"
            @confirm="doDelete"
            @cancel="closeConfirm"
        />
    </AppLayout>
</template>
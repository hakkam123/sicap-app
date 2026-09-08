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
import { Search, RotateCcw, Plus, X } from 'lucide-vue-next';

const page = usePage();
const isAdmin = computed(() => {
    const user = page.props.auth?.user;
    return user?.role === 'admin' || user?.is_admin === true;
});

const props = defineProps({
    areas: {
        type: Object,
        required: true,
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
        route('areas.index'),
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
    router.get(route('areas.index'), {}, { preserveState: false });
};

// ==========================================
// 2. DATA TABLE COLUMNS
// ==========================================
const tableColumns = [
    { key: 'code', label: 'Kode', width: 'w-24' },
    { key: 'name', label: 'Nama Area' },
    { key: 'description', label: 'Deskripsi' },
    { key: 'machines_count', label: 'Jumlah Machine', align: 'center', width: 'w-36' },
];

// ==========================================
// 3. CREATE / EDIT MODAL FORM
// ==========================================
const isModalOpen = ref(false);
const editingArea = ref(null);
const isEditing = computed(() => !!editingArea.value);

const form = useForm({
    code: '',
    name: '',
    description: '',
});

const openCreateModal = () => {
    editingArea.value = null;
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
};

const openEditModal = (area) => {
    editingArea.value = area;
    form.clearErrors();
    form.code = area.code || '';
    form.name = area.name || '';
    form.description = area.description || '';
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingArea.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('areas.update', editingArea.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('areas.store'), {
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
    router.delete(route('areas.destroy', confirmModal.value.id), {
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
        <Head title="Master Data Area" />

        <template #header>
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-800">
                    Master Data Area
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Kelola data area dan lini produksi sparepart.
                </p>
            </div>
        </template>

        <div class="p-6 space-y-4">
            <!-- FILTER + ACTION CARD -->
            <div class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border border-gray-200">
                <div class="flex flex-wrap items-end gap-2">

                    <!-- SEARCH + FILTER BUTTONS — KIRI -->
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
                                    placeholder="Cari nama atau kode area..."
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

                    <!-- ACTION BUTTON — KANAN -->
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
                            Tambah Area
                        </button>
                    </div>

                </div>

            </div>

            <!-- DATA TABLE -->
            <DataTable
                :columns="tableColumns"
                :data="areas"
            >
                <template #cell-code="{ value }">
                    <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded font-bold text-slate-800">{{ value }}</span>
                </template>

                <template #cell-machines_count="{ value }">
                    <span class="bg-purple-50 text-purple-700 font-semibold px-2 py-0.5 rounded text-xs">
                        {{ value }} mesin
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
                    <p>Tidak ada data area ditemukan.</p>
                </template>
            </DataTable>
        </div>

        <!-- MODAL FORM CREATE / EDIT AREA -->
        <Modal :show="isModalOpen" @close="closeModal" max-width="lg">
            <div class="p-6 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            {{ isEditing ? 'Edit Data Area' : 'Tambah Area Baru' }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ isEditing ? 'Perbarui informasi area yang sudah terdaftar.' : 'Masukkan informasi kode dan nama area baru.' }}
                        </p>
                    </div>
                    <button @click="closeModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- Code -->
                    <div>
                        <InputLabel for="code" value="Kode Area *" />
                        <TextInput
                            id="code"
                            v-model="form.code"
                            type="text"
                            class="mt-1 block w-full uppercase font-mono text-xs"
                            placeholder="Contoh: SMT, FA, COMMON"
                            required
                            autofocus
                        />
                        <InputError class="mt-1" :message="form.errors.code" />
                    </div>

                    <!-- Name -->
                    <div>
                        <InputLabel for="name" value="Nama Area *" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full text-xs"
                            placeholder="Contoh: Surface Mount Technology"
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
                            placeholder="Keterangan opsional area..."
                        ></textarea>
                        <InputError class="mt-1" :message="form.errors.description" />
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                        <SecondaryButton type="button" @click="closeModal">
                            Batal
                        </SecondaryButton>

                        <PrimaryButton :disabled="form.processing">
                            {{ isEditing ? 'Simpan Perubahan' : 'Tambah Area' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Confirm Delete Modal -->
        <ConfirmModal
            :show="confirmModal.show"
            title="Konfirmasi Hapus Area"
            :message="`Yakin ingin menghapus area '${confirmModal.name}'? Mesin yang terkait tidak bisa dihapus jika ada data consume.`"
            confirm-label="Hapus Area"
            variant="danger"
            :loading="isDeleting"
            @confirm="doDelete"
            @cancel="closeConfirm"
        />
    </AppLayout>
</template>
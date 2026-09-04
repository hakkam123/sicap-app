<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import ConfirmModal from '@/Components/UI/ConfirmModal.vue';
import { Search, RotateCcw, Plus } from 'lucide-vue-next';

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
    { key: 'code', label: 'Kode', width: 'w-32' },
    { key: 'name', label: 'Nama Machine' },
    { key: 'area', label: 'Area', width: 'w-40' },
    { key: 'description', label: 'Deskripsi' },
];

// ==========================================
// 3. NAVIGASI
// ==========================================
const goToCreate = () => router.visit(route('machines.create'));
const goToEdit = (row) => router.visit(route('machines.edit', row.id));

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
        onFinish: () => {
            isDeleting.value = false;
            closeConfirm();
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
                            class="flex items-end gap-1 ml-auto"
                        >
                            <button
                                type="button"
                                @click="goToCreate"
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
                    <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ value }}</span>
                </template>

                <template #cell-area="{ row }">
                    <span v-if="row.area">
                        {{ row.area?.name }}
                        <span class="text-xs text-gray-400">({{ row.area?.code }})</span>
                    </span>
                    <span v-else class="text-slate-400 italic text-xs">Tanpa Area</span>
                </template>

                <template #actions="{ row }">
                    <button
                        type="button"
                        @click="goToEdit(row)"
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

        <!-- Confirm Delete Modal -->
        <ConfirmModal
            :show="confirmModal.show"
            title="Konfirmasi Hapus Machine"
            :message="`Yakin ingin menghapus machine '${confirmModal.name}'? Data mesin ini akan di-soft delete jika tidak memiliki data konsumsi terkait.`"
            confirm-label="Hapus Machine"
            variant="danger"
            :loading="isDeleting"
            @confirm="doDelete"
            @cancel="closeConfirm"
        />
    </AppLayout>
</template>
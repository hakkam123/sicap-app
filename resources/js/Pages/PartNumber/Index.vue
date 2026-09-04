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
    partNumbers: {
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
    return 'Rp ' + Number(val).toLocaleString('id-ID');
};

// ==========================================
// 3. NAVIGASI
// ==========================================
const goToCreate = () => router.visit(route('part-numbers.create'));
const goToEdit = (row) => router.visit(route('part-numbers.edit', row.id));

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
                            @click="goToCreate"
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
                    <span class="font-mono text-sm font-bold text-blue-600">{{ value }}</span>
                </template>

                <template #cell-price_per_unit="{ value }">
                    <span class="font-semibold text-slate-900 whitespace-nowrap">
                        {{ formatRupiah(value) }}
                    </span>
                </template>

                <template #cell-areas_count="{ value }">
                    <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full font-semibold">
                        {{ value }} area
                    </span>
                </template>

                <template #cell-machines_count="{ value }">
                    <span class="bg-purple-100 text-purple-700 text-xs px-2 py-0.5 rounded-full font-semibold">
                        {{ value }} machine
                    </span>
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
                    <p>Tidak ada data part number ditemukan.</p>
                </template>
            </DataTable>
        </div>

        <!-- Confirm Delete Modal -->
        <ConfirmModal
            :show="confirmModal.show"
            title="Konfirmasi Hapus Part Number"
            :message="`Yakin ingin menghapus part number '${confirmModal.name}'? Data part ini akan di-soft delete jika tidak memiliki data konsumsi terkait.`"
            confirm-label="Hapus Part Number"
            variant="danger"
            :loading="isDeleting"
            @confirm="doDelete"
            @cancel="closeConfirm"
        />
    </AppLayout>
</template>
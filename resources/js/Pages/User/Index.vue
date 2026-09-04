<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import ConfirmModal from '@/Components/UI/ConfirmModal.vue';
import { Search, RotateCcw, Plus } from 'lucide-vue-next';

const page = usePage();
const isAdmin = computed(() => {
    const user = page.props.auth?.user;
    return user?.role === 'admin' || user?.is_admin === true;
});

const props = defineProps({
    users: {
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
        route('users.index'),
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
    router.get(route('users.index'), {}, { preserveState: false });
};

// ==========================================
// 2. DATA TABLE COLUMNS & FORMATTERS
// ==========================================
const tableColumns = [
    { key: 'name', label: 'Nama' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Role', align: 'center', width: 'w-32' },
    { key: 'created_at', label: 'Terdaftar Pada', width: 'w-36' },
];

const formatDate = (isoString) => {
    if (!isoString) return '-';
    return new Date(isoString).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

// ==========================================
// 3. NAVIGASI
// ==========================================
const goToCreate = () => router.visit(route('users.create'));
const goToEdit = (row) => router.visit(route('users.edit', row.id));

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
    router.delete(route('users.destroy', confirmModal.value.id), {
        onFinish: () => {
            isDeleting.value = false;
            closeConfirm();
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="User Management" />

        <template #header>
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-800">
                    User Management
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Kelola akun pengguna, hak akses peran (admin/user), dan kredensial login.
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
                                    placeholder="Cari nama atau email..."
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
                        class="flex items-end gap-1 ml-auto shrink-0"
                    >
                        <button
                            type="button"
                            @click="goToCreate"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-md hover:bg-gray-700 transition"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            Tambah User
                        </button>
                    </div>

                </div>
            </div>

            <!-- DATA TABLE -->
            <DataTable
                :columns="tableColumns"
                :data="users"
            >
                <template #cell-name="{ row, value }">
                    <span class="font-medium text-slate-900">{{ value }}</span>
                    <span
                        v-if="row.id === page.props.auth?.user?.id"
                        class="ml-2 text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded font-bold"
                    >
                        Anda
                    </span>
                </template>

                <template #cell-role="{ row, value }">
                    <StatusBadge
                        :value="row.roles && row.roles[0]?.name ? row.roles[0].name : (value || 'user')"
                        type="role"
                    />
                </template>

                <template #cell-created_at="{ value }">
                    <span class="text-slate-500 whitespace-nowrap">{{ formatDate(value) }}</span>
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
                        v-if="row.id !== page.props.auth?.user?.id"
                        type="button"
                        @click="openConfirm(row)"
                        class="text-red-600 hover:text-red-900 hover:underline font-semibold inline-flex items-center gap-1 text-xs ml-3"
                    >
                        Hapus
                    </button>
                </template>

                <template #empty>
                    <p>Tidak ada data pengguna ditemukan.</p>
                </template>
            </DataTable>
        </div>

        <!-- Confirm Delete Modal -->
        <ConfirmModal
            :show="confirmModal.show"
            title="Konfirmasi Hapus Pengguna"
            :message="`Yakin ingin menghapus akun pengguna '${confirmModal.name}'? Data akun ini akan di-soft delete dan tidak dapat login kembali.`"
            confirm-label="Hapus Pengguna"
            variant="danger"
            :loading="isDeleting"
            @confirm="doDelete"
            @cancel="closeConfirm"
        />
    </AppLayout>
</template>

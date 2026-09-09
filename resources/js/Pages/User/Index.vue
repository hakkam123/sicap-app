<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
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
// 3. CREATE / EDIT MODAL FORM
// ==========================================
const isModalOpen = ref(false);
const editingUser = ref(null);
const isEditing = computed(() => !!editingUser.value);

const form = useForm({
    name: '',
    email: '',
    role: 'user',
    password: '',
    password_confirmation: '',
});

const openCreateModal = () => {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'user';
    isModalOpen.value = true;
};

const openEditModal = (user) => {
    editingUser.value = user;
    form.clearErrors();
    form.name = user.name || '';
    form.email = user.email || '';
    form.role = user.roles && user.roles.length > 0 ? user.roles[0].name : (user.role || 'user');
    form.password = '';
    form.password_confirmation = '';
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingUser.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('users.update', editingUser.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('users.store'), {
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
    router.delete(route('users.destroy', confirmModal.value.id), {
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
                            @click="openCreateModal"
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
                        class="ml-2 text-[10px] bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-bold"
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
                    <span class="text-slate-500 whitespace-nowrap text-xs">{{ formatDate(value) }}</span>
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

        <!-- MODAL FORM CREATE / EDIT USER -->
        <Modal :show="isModalOpen" @close="closeModal" max-width="lg">
            <div class="p-6 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            {{ isEditing ? 'Edit Data Pengguna' : 'Tambah Pengguna Baru' }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ isEditing ? 'Perbarui informasi profil dan peran pengguna.' : 'Daftarkan akun pengguna baru ke dalam sistem COPA.' }}
                        </p>
                    </div>
                    <button @click="closeModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- Name -->
                    <div>
                        <InputLabel for="name" value="Nama Lengkap *" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full text-xs"
                            placeholder="Masukkan nama lengkap..."
                            required
                            autofocus
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <!-- Email -->
                    <div>
                        <InputLabel for="email" value="Alamat Email *" />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full text-xs"
                            placeholder="contoh: operator@sicap.local"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>

                    <!-- Role -->
                    <div>
                        <InputLabel for="role" value="Peran (Role) *" />
                        <select
                            id="role"
                            v-model="form.role"
                            class="mt-1 block w-full border-gray-300 focus:border-slate-900 focus:ring-slate-900 rounded-md shadow-sm text-xs"
                            required
                        >
                            <option value="user">User (Operator / Standard User)</option>
                            <option value="admin">Administrator (Akses Penuh)</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.role" />
                    </div>

                    <!-- Password -->
                    <div>
                        <InputLabel
                            for="password"
                            :value="isEditing ? 'Kata Sandi (Opsional)' : 'Kata Sandi *'"
                        />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full text-xs"
                            :required="!isEditing"
                            :placeholder="isEditing ? 'Kosongkan jika tidak ingin mengubah kata sandi' : 'Minimal 8 karakter'"
                        />
                        <InputError class="mt-1" :message="form.errors.password" />
                    </div>

                    <!-- Password Confirmation -->
                    <div v-if="!isEditing || form.password.length > 0">
                        <InputLabel
                            for="password_confirmation"
                            :value="isEditing ? 'Konfirmasi Kata Sandi Baru *' : 'Konfirmasi Kata Sandi *'"
                        />
                        <TextInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block w-full text-xs"
                            :required="!isEditing || form.password.length > 0"
                            placeholder="Ulangi kata sandi di atas..."
                        />
                        <InputError class="mt-1" :message="form.errors.password_confirmation" />
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                        <SecondaryButton type="button" @click="closeModal">
                            Batal
                        </SecondaryButton>

                        <PrimaryButton :disabled="form.processing">
                            {{ isEditing ? 'Simpan Perubahan' : 'Simpan User' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Confirm Delete Modal -->
        <ConfirmModal
            :show="confirmModal.show"
            title="Konfirmasi Hapus Pengguna"
            :message="`Yakin ingin menghapus akun pengguna '${confirmModal.name}'? Akun yang dihapus tidak akan dapat login kembali.`"
            confirm-label="Hapus Pengguna"
            variant="danger"
            :loading="isDeleting"
            @confirm="doDelete"
            @cancel="closeConfirm"
        />
    </AppLayout>
</template>

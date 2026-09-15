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
import { Search, RotateCcw, Plus, X, Check, Eye, EyeOff, Lock, ShieldCheck } from 'lucide-vue-next';

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

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

const getRoleLabel = (user) => {
    return user.role === 'admin' ? 'Administrator' : 'User';
};

// ==========================================
// 3. CREATE / EDIT MODAL FORM & PASSWORD RULES
// ==========================================
const isModalOpen = ref(false);
const editingUser = ref(null);
const isEditing = computed(() => !!editingUser.value);

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const form = useForm({
    name: '',
    email: '',
    role: 'user',
    password: '',
    password_confirmation: '',
});

// Realtime Password Validation Rules
const passwordRules = computed(() => {
    const p = form.password || '';
    return {
        hasMinLength: p.length >= 8,
        hasUppercase: /[A-Z]/.test(p),
        hasNumber: /[0-9]/.test(p),
        hasSymbol: /[^A-Za-z0-9]/.test(p),
        isConfirmed: form.password_confirmation.length > 0 && p === form.password_confirmation,
    };
});

const passwordScore = computed(() => {
    let score = 0;
    if (passwordRules.value.hasMinLength) score++;
    if (passwordRules.value.hasUppercase) score++;
    if (passwordRules.value.hasNumber) score++;
    if (passwordRules.value.hasSymbol) score++;
    return score;
});

const passwordStrengthText = computed(() => {
    if (!form.password) return '';
    switch (passwordScore.value) {
        case 1:
            return 'Sangat Lemah';
        case 2:
            return 'Lemah';
        case 3:
            return 'Cukup Kuat';
        case 4:
            return 'Sangat Kuat (Memenuhi Syarat)';
        default:
            return 'Belum Sesuai';
    }
});

const isPasswordRequirementMet = computed(() => {
    return (
        passwordRules.value.hasMinLength &&
        passwordRules.value.hasUppercase &&
        passwordRules.value.hasNumber &&
        passwordRules.value.hasSymbol
    );
});

const isPasswordInputActive = computed(() => {
    return !isEditing.value || (form.password && form.password.length > 0);
});

const canSubmit = computed(() => {
    if (form.processing) return false;
    if (!form.name || !form.email || !form.role) return false;
    if (isPasswordInputActive.value) {
        if (!isPasswordRequirementMet.value) return false;
        if (form.password !== form.password_confirmation) return false;
    }
    return true;
});

const openCreateModal = () => {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'user';
    showPassword.value = false;
    showConfirmPassword.value = false;
    isModalOpen.value = true;
};

const openEditModal = (user) => {
    editingUser.value = user;
    form.clearErrors();
    form.name = user.name || '';
    form.email = user.email || '';
    form.role = user.role || 'user';
    form.password = '';
    form.password_confirmation = '';
    showPassword.value = false;
    showConfirmPassword.value = false;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    showPassword.value = false;
    showConfirmPassword.value = false;
};

const submitForm = () => {
    if (isPasswordInputActive.value) {
        if (!isPasswordRequirementMet.value) {
            form.setError('password', 'Kata sandi wajib minimal 8 karakter, mengandung huruf besar, angka, dan simbol khusus.');
            return;
        }
        if (form.password !== form.password_confirmation) {
            form.setError('password_confirmation', 'Konfirmasi kata sandi tidak cocok.');
            return;
        }
    }

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
                        class="ml-1.5 text-slate-400 text-xs font-normal"
                    >
                        (Anda)
                    </span>
                </template>

                <template #cell-email="{ value }">
                    <span class="text-slate-600">{{ value }}</span>
                </template>

                <template #cell-role="{ row }">
                    <span class="text-slate-700">
                        {{ getRoleLabel(row) }}
                    </span>
                </template>

                <template #cell-created_at="{ value }">
                    <span class="text-slate-500 whitespace-nowrap text-xs">{{ formatDate(value) }}</span>
                </template>

                <template v-if="isAdmin" #actions="{ row }">
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
                            placeholder="contoh: operator@copa.local"
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
                        <div class="flex items-center justify-between">
                            <InputLabel
                                for="password"
                                :value="isEditing ? 'Kata Sandi (Opsional)' : 'Kata Sandi *'"
                            />
                            <span
                                v-if="form.password && form.password.length > 0"
                                class="text-[10px] font-mono font-semibold px-2 py-0.5 rounded-full transition-all"
                                :class="passwordRules.hasMinLength ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-600 border border-rose-200'"
                            >
                                {{ form.password.length }} karakter {{ passwordRules.hasMinLength ? '✓' : '(min. 8)' }}
                            </span>
                        </div>

                        <div class="relative mt-1">
                            <TextInput
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                class="block w-full text-xs pr-9"
                                :required="!isEditing"
                                :placeholder="isEditing ? 'Kosongkan jika tidak ingin mengubah kata sandi' : 'Minimal 8 karakter, ada huruf besar, angka & simbol'"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-0.5"
                                tabindex="-1"
                                title="Lihat kata sandi"
                            >
                                <EyeOff v-if="showPassword" class="w-4 h-4" />
                                <Eye v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <InputError class="mt-1" :message="form.errors.password" />
                    </div>

                    <!-- Password Strength & Criteria Checklist Card -->
                    <div
                        v-if="isPasswordInputActive && form.password.length > 0"
                        class="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-2.5 text-xs transition-all"
                    >
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-medium text-slate-600 flex items-center gap-1.5">
                                <Lock class="w-3.5 h-3.5 text-slate-400" />
                                Kekuatan Kata Sandi:
                                <span
                                    class="font-semibold"
                                    :class="passwordScore === 4 ? 'text-emerald-700' : passwordScore >= 2 ? 'text-amber-700' : 'text-rose-600'"
                                >
                                    {{ passwordStrengthText }}
                                </span>
                            </span>
                            <span class="text-[10px] font-mono text-slate-500 font-medium">
                                {{ passwordScore }}/4 Syarat Terpenuhi
                            </span>
                        </div>

                        <!-- Progress Strength Bar -->
                        <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-300"
                                :class="passwordScore === 4 ? 'bg-emerald-500' : passwordScore >= 3 ? 'bg-blue-500' : passwordScore >= 2 ? 'bg-amber-500' : 'bg-rose-500'"
                                :style="{ width: `${(passwordScore / 4) * 100}%` }"
                            ></div>
                        </div>

                        <!-- 4 Requirements Checklist Grid -->
                        <div class="grid grid-cols-2 gap-2 text-[11px]">
                            <!-- 1. Min 8 Characters with live counter -->
                            <div
                                class="flex items-center gap-1.5 transition-colors"
                                :class="passwordRules.hasMinLength ? 'text-emerald-700 font-medium' : 'text-slate-500'"
                            >
                                <Check v-if="passwordRules.hasMinLength" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 flex items-center justify-center shrink-0">
                                    <div class="w-1 h-1 bg-slate-400 rounded-full"></div>
                                </div>
                                <span>Min. 8 Karakter <span class="font-mono text-[10px]">({{ form.password.length }}/8)</span></span>
                            </div>

                            <!-- 2. Uppercase Letter -->
                            <div
                                class="flex items-center gap-1.5 transition-colors"
                                :class="passwordRules.hasUppercase ? 'text-emerald-700 font-medium' : 'text-slate-500'"
                            >
                                <Check v-if="passwordRules.hasUppercase" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 flex items-center justify-center shrink-0">
                                    <div class="w-1 h-1 bg-slate-400 rounded-full"></div>
                                </div>
                                <span>Huruf Besar (A-Z)</span>
                            </div>

                            <!-- 3. Number -->
                            <div
                                class="flex items-center gap-1.5 transition-colors"
                                :class="passwordRules.hasNumber ? 'text-emerald-700 font-medium' : 'text-slate-500'"
                            >
                                <Check v-if="passwordRules.hasNumber" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 flex items-center justify-center shrink-0">
                                    <div class="w-1 h-1 bg-slate-400 rounded-full"></div>
                                </div>
                                <span>Angka (0-9)</span>
                            </div>

                            <!-- 4. Special Symbol -->
                            <div
                                class="flex items-center gap-1.5 transition-colors"
                                :class="passwordRules.hasSymbol ? 'text-emerald-700 font-medium' : 'text-slate-500'"
                            >
                                <Check v-if="passwordRules.hasSymbol" class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                <div v-else class="w-3.5 h-3.5 rounded-full border border-slate-300 flex items-center justify-center shrink-0">
                                    <div class="w-1 h-1 bg-slate-400 rounded-full"></div>
                                </div>
                                <span>Simbol Khusus (!@#$%)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Password Confirmation -->
                    <div v-if="!isEditing || form.password.length > 0">
                        <InputLabel
                            for="password_confirmation"
                            :value="isEditing ? 'Konfirmasi Kata Sandi Baru *' : 'Konfirmasi Kata Sandi *'"
                        />
                        <div class="relative mt-1">
                            <TextInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showConfirmPassword ? 'text' : 'password'"
                                class="block w-full text-xs pr-9"
                                :required="!isEditing || form.password.length > 0"
                                placeholder="Ulangi kata sandi di atas..."
                            />
                            <button
                                type="button"
                                @click="showConfirmPassword = !showConfirmPassword"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-0.5"
                                tabindex="-1"
                                title="Lihat konfirmasi kata sandi"
                            >
                                <EyeOff v-if="showConfirmPassword" class="w-4 h-4" />
                                <Eye v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <InputError class="mt-1" :message="form.errors.password_confirmation" />

                        <!-- Realtime Match Feedback -->
                        <div v-if="form.password_confirmation.length > 0" class="mt-1 text-[11px] flex items-center gap-1.5">
                            <span v-if="passwordRules.isConfirmed" class="text-emerald-600 flex items-center gap-1 font-medium">
                                <Check class="w-3.5 h-3.5 text-emerald-600" /> Konfirmasi kata sandi cocok
                            </span>
                            <span v-else class="text-rose-600 flex items-center gap-1 font-medium">
                                <X class="w-3.5 h-3.5 text-rose-500" /> Konfirmasi kata sandi belum cocok
                            </span>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                        <SecondaryButton type="button" @click="closeModal">
                            Batal
                        </SecondaryButton>

                        <PrimaryButton :disabled="!canSubmit || form.processing">
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

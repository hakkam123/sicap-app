<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
});

const isEdit = computed(() => !!props.user);

// Determine initial role
const initialRole = computed(() => {
    if (props.user?.roles && props.user.roles.length > 0) {
        return props.user.roles[0].name;
    }
    return props.user?.role || 'user';
});

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    password_confirmation: '',
    role: initialRole.value,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('users.update', props.user.id));
    } else {
        form.post(route('users.store'));
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="isEdit ? 'Edit User' : 'Tambah User'" />

        <template #header>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('users.index')"
                    class="text-gray-500 hover:text-gray-700 text-sm"
                >
                    User Management
                </Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? 'Edit User' : 'Tambah User Baru' }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 sm:p-8">
                    <form @submit.prevent="submit" class="space-y-6">

                        <!-- Name -->
                        <div>
                            <InputLabel for="name" value="Nama Lengkap *" />
                            <TextInput
                                id="name"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.name"
                                required
                                autofocus
                                placeholder="Masukkan nama lengkap..."
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <!-- Email -->
                        <div>
                            <InputLabel for="email" value="Alamat Email *" />
                            <TextInput
                                id="email"
                                type="email"
                                class="mt-1 block w-full"
                                v-model="form.email"
                                required
                                placeholder="contoh: operator@sicap.local"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <!-- Role -->
                        <div>
                            <InputLabel for="role" value="Peran (Role) *" />
                            <select
                                id="role"
                                v-model="form.role"
                                class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm"
                                required
                            >
                                <option value="user">User (Operator / Standard User)</option>
                                <option value="admin">Administrator</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-400">
                                Administrator memiliki akses penuh ke Master Data, Mapping, Import, dan User Management.
                            </p>
                            <InputError class="mt-2" :message="form.errors.role" />
                        </div>

                        <!-- Password -->
                        <div>
                            <InputLabel
                                for="password"
                                :value="isEdit ? 'Kata Sandi (Opsional)' : 'Kata Sandi *'"
                            />
                            <TextInput
                                id="password"
                                type="password"
                                class="mt-1 block w-full"
                                v-model="form.password"
                                :required="!isEdit"
                                :placeholder="isEdit ? 'Kosongkan jika tidak ingin mengubah kata sandi' : 'Minimal 8 karakter'"
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <!-- Password Confirmation -->
                        <div>
                            <InputLabel
                                for="password_confirmation"
                                :value="isEdit ? 'Konfirmasi Kata Sandi (Opsional)' : 'Konfirmasi Kata Sandi *'"
                            />
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                                v-model="form.password_confirmation"
                                :required="!isEdit && form.password.length > 0"
                                placeholder="Ulangi kata sandi di atas..."
                            />
                            <InputError class="mt-2" :message="form.errors.password_confirmation" />
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                            <Link :href="route('users.index')">
                                <SecondaryButton type="button">
                                    Batal
                                </SecondaryButton>
                            </Link>

                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                {{ isEdit ? 'Simpan Perubahan' : 'Simpan User' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


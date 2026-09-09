<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Check } from 'lucide-vue-next';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const profileForm = useForm({
    name: props.user.name,
    email: props.user.email,
});

const profileSaved = ref(false);

const submitProfile = () => {
    profileForm.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            profileSaved.value = true;
            setTimeout(() => { profileSaved.value = false; }, 3000);
        },
    });
};

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const passwordSaved = ref(false);

const submitPassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            passwordSaved.value = true;
            setTimeout(() => { passwordSaved.value = false; }, 3000);
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Profil Saya" />

        <template #header>
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-800">Profil Saya</h2>
                <p class="text-xs text-slate-500 mt-0.5">Kelola informasi akun dan kata sandi Anda.</p>
            </div>
        </template>

        <div class="py-6 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">

                <!-- Kiri: Informasi Profil -->
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">

                    <!-- Header card dengan accent strip -->
                    <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-700 uppercase tracking-wide">Informasi Profil</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Nama lengkap dan alamat email</p>
                        </div>
                        <span
                            class="text-[11px] font-semibold px-2 py-0.5 rounded"
                            :class="user.role === 'admin'
                                ? 'bg-violet-100 text-violet-700'
                                : 'bg-slate-100 text-slate-500'"
                        >
                            {{ user.role === 'admin' ? 'Admin' : 'User' }}
                        </span>
                    </div>

                    <form @submit.prevent="submitProfile" class="p-5 space-y-3.5">
                        <div>
                            <InputLabel for="name" value="Nama Lengkap" class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-1" />
                            <TextInput
                                id="name"
                                v-model="profileForm.name"
                                type="text"
                                class="block w-full text-xs"
                                required
                                autocomplete="name"
                            />
                            <InputError class="mt-1" :message="profileForm.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Alamat Email" class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-1" />
                            <TextInput
                                id="email"
                                v-model="profileForm.email"
                                type="email"
                                class="block w-full text-xs"
                                required
                                autocomplete="username"
                            />
                            <InputError class="mt-1" :message="profileForm.errors.email" />
                        </div>

                        <div class="flex items-center gap-3 pt-1">
                            <button
                                type="submit"
                                :disabled="profileForm.processing"
                                class="px-4 py-1.5 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 text-white text-xs font-semibold rounded-lg transition-colors"
                            >
                                {{ profileForm.processing ? 'Menyimpan...' : 'Simpan Profil' }}
                            </button>
                            <span v-if="profileSaved" class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                                <Check class="w-3.5 h-3.5" /> Tersimpan
                            </span>
                        </div>
                    </form>
                </div>

                <!-- Kanan: Kata Sandi -->
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">

                    <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50">
                        <p class="text-xs font-bold text-slate-700 uppercase tracking-wide">Kata Sandi</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Gunakan kata sandi panjang dan acak</p>
                    </div>

                    <form @submit.prevent="submitPassword" class="p-5 space-y-3.5">
                        <div>
                            <InputLabel for="current_password" value="Kata Sandi Saat Ini" class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-1" />
                            <TextInput
                                id="current_password"
                                v-model="passwordForm.current_password"
                                type="password"
                                class="block w-full text-xs"
                                required
                                autocomplete="current-password"
                            />
                            <InputError class="mt-1" :message="passwordForm.errors.current_password" />
                        </div>

                        <div>
                            <InputLabel for="password" value="Kata Sandi Baru" class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-1" />
                            <TextInput
                                id="password"
                                v-model="passwordForm.password"
                                type="password"
                                class="block w-full text-xs"
                                required
                                autocomplete="new-password"
                            />
                            <InputError class="mt-1" :message="passwordForm.errors.password" />
                        </div>

                        <div>
                            <InputLabel for="password_confirmation" value="Konfirmasi Kata Sandi Baru" class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide mb-1" />
                            <TextInput
                                id="password_confirmation"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                class="block w-full text-xs"
                                required
                                autocomplete="new-password"
                            />
                            <InputError class="mt-1" :message="passwordForm.errors.password_confirmation" />
                        </div>

                        <div class="flex items-center gap-3 pt-1">
                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 disabled:opacity-50 text-white text-xs font-semibold rounded-lg transition-colors"
                            >
                                {{ passwordForm.processing ? 'Menyimpan...' : 'Perbarui Kata Sandi' }}
                            </button>
                            <span v-if="passwordSaved" class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium">
                                <Check class="w-3.5 h-3.5" /> Diperbarui
                            </span>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
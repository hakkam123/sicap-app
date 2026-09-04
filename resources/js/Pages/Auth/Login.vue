<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};

const hasErrors = computed(() => {
    return Object.keys(form.errors).length > 0;
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Masuk ke SICAP" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 to-gray-700 p-4">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
            <!-- Logo area -->
            <div class="text-center">
                <div class="w-14 h-14 bg-gray-900 text-white font-bold text-xl flex items-center justify-center rounded-2xl mx-auto mb-4 shadow-md">
                    S
                </div>
                <h1 class="text-2xl font-bold text-gray-900 text-center">
                    SICAP
                </h1>
                <p class="text-sm text-gray-500 text-center mb-6">
                    Sistem Informasi Consume Sparepart
                </p>
            </div>

            <!-- Flash status (jika ada) -->
            <div v-if="status" class="mb-5 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-lg">
                {{ status }}
            </div>

            <!-- Error message alert -->
            <div v-if="hasErrors" class="mb-5 p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg space-y-1">
                <p class="font-semibold">Gagal Masuk:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    <li v-for="(error, key) in form.errors" :key="key">
                        {{ error }}
                    </li>
                </ul>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-4">
                <!-- Field Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="nama@astra-visteon.com"
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent transition"
                        :class="{ 'border-red-300 ring-1 ring-red-300': form.errors.email }"
                    />
                </div>

                <!-- Field Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        Password
                    </label>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="w-full px-4 py-2.5 pr-11 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent transition"
                            :class="{ 'border-red-300 ring-1 ring-red-300': form.errors.password }"
                        />
                        <button
                            type="button"
                            @click="togglePasswordVisibility"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none p-1"
                            tabindex="-1"
                        >
                            <Eye v-if="!showPassword" class="w-4 h-4" />
                            <EyeOff v-else class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Checkbox Remember Me -->
                <div class="flex items-center">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            v-model="form.remember"
                            class="w-4 h-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                        />
                        <span class="text-sm text-gray-600">Remember me</span>
                    </label>
                </div>

                <!-- Tombol MASUK -->
                <div class="pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-gray-900 text-white py-2.5 rounded-lg font-semibold text-sm hover:bg-gray-700 transition flex items-center justify-center disabled:opacity-60 disabled:cursor-not-allowed shadow-sm"
                    >
                        <svg
                            v-if="form.processing"
                            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ form.processing ? 'Memproses...' : 'MASUK' }}</span>
                    </button>
                </div>
            </form>

            <!-- Footer card -->
            <p class="text-xs text-gray-400 text-center mt-6">
                © 2026 PT Astra Visteon Indonesia
            </p>
        </div>
    </div>
</template>

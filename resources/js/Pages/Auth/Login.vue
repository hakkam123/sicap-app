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
    <div class="login-page">

        <Head title="Masuk ke COPA" />

        <!-- Background decoration -->
        <div class="background-pattern">
            <span class="dot dot-1"></span>
            <span class="dot dot-2"></span>
            <span class="dot dot-3"></span>
            <span class="dot dot-4"></span>
            <span class="dot dot-5"></span>
            <span class="dot dot-6"></span>
            <span class="dot dot-7"></span>
            <span class="dot dot-8"></span>
            <span class="dot dot-9"></span>
            <span class="dot dot-10"></span>
            <span class="dot dot-11"></span>
            <span class="dot dot-12"></span>
        </div>

        <!-- Subtle grid -->
        <div class="grid-pattern"></div>

        <!-- Login Card -->
        <div
            class="relative z-10 w-full max-w-[850px] mx-4 bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden flex flex-col md:flex-row min-h-[520px]"
        >

            <!-- ============================== -->
            <!-- KOLOM KIRI: GAMBAR FOTO        -->
            <!-- ============================== -->
            <div class="hidden md:block md:w-[45%] relative bg-slate-900">
                <!-- Foto Background Kiri -->
                <img
                    src="/leftside-login.jpg"
                    alt="Manajemen Inventaris COPA"
                    class="absolute inset-0 w-full h-full object-cover"
                />
                
                <!-- Gradient overlay agar teks di atasnya terbaca jelas -->
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/40 to-transparent"></div>
                
                <!-- Teks pemanis di atas gambar -->
                <div class="absolute bottom-0 left-0 p-8">
                    <p class="text-white/95 text-sm font-medium leading-relaxed tracking-wide">
                        Sistem manajemen dan kontrol <br> <i>Consumption Part</i> yang terintegrasi.
                    </p>
                </div>
            </div>


            <!-- ============================== -->
            <!-- KOLOM KANAN: LOGO & FORM       -->
            <!-- ============================== -->
            <div class="md:w-[55%] px-7 py-8 md:px-12 md:py-10 flex flex-col justify-center">

                <!-- Header Section: Text Kiri, Logo Kanan -->
                <div class="flex justify-between items-start mb-8 gap-4">
                    <!-- Welcome Section -->
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">
                            Selamat datang
                        </h2>
                        <p class="text-sm text-slate-500 mt-1.5">
                            Silakan masuk untuk melanjutkan ke sistem <strong>COPA</strong>.
                        </p>
                    </div>
                </div>

                <!-- Status Message -->
                <div
                    v-if="status"
                    class="mb-6 flex items-start gap-3 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700"
                >
                    <svg class="w-4 h-4 mt-0.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <p class="text-xs leading-5">{{ status }}</p>
                </div>

                <!-- Error Message -->
                <div
                    v-if="hasErrors"
                    class="mb-6 px-4 py-3 rounded-lg bg-red-50 border border-red-200"
                >
                    <div class="flex items-start gap-3">
                        <svg class="w-4 h-4 mt-0.5 text-red-500 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-red-700">Tidak dapat masuk</p>
                            <p v-for="(error, key) in form.errors" :key="key" class="text-xs text-red-600 mt-0.5">{{ error }}</p>
                        </div>
                    </div>
                </div>

                <!-- Login Form -->
                <form @submit.prevent="submit" class="space-y-5">
                    
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-700 mb-2">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="nama@astra-visteon.com"
                            class="w-full h-11 px-3.5 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-100"
                            :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-50': form.errors.email }"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-[11px] text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-700 mb-2">Password</label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="Masukkan password"
                                class="w-full h-11 px-3.5 pr-11 bg-white border border-slate-300 rounded-lg text-sm text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-100"
                                :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-50': form.errors.password }"
                            />
                            <button type="button" @click="togglePasswordVisibility" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 transition-colors" aria-label="Tampilkan password">
                                <Eye v-if="!showPassword" class="w-4 h-4" />
                                <EyeOff v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-[11px] text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input v-model="form.remember" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                            <span class="text-xs text-slate-600">Ingat saya</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full h-11 mt-2 flex items-center justify-center gap-2 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800 active:bg-slate-950 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                    >
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <span>{{ form.processing ? 'Memproses...' : 'Masuk' }}</span>
                    </button>

                </form>

                <!-- Footer -->
                <div class="mt-8 pt-5 border-t border-slate-100">
                    <p class="text-[11px] text-slate-400">
                        © 2026 PT Astra Visteon Indonesia
                    </p>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
/* Bagian style tidak ada yang diubah dari sebelumnya */
.login-page {
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    overflow: hidden;
    background:
        radial-gradient(circle at 20% 20%, rgba(148, 163, 184, 0.12), transparent 28%),
        radial-gradient(circle at 80% 80%, rgba(148, 163, 184, 0.10), transparent 30%),
        #f1f5f9;
}

.background-pattern { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.dot { position: absolute; width: 5px; height: 5px; border-radius: 9999px; background: #64748b; opacity: 0; animation: dotPulse 5s ease-in-out infinite; }

.dot-1 { left: 8%; top: 18%; animation-delay: 0s; }
.dot-2 { left: 17%; top: 72%; animation-delay: 1.2s; }
.dot-3 { left: 28%; top: 32%; animation-delay: 2.4s; }
.dot-4 { left: 38%; top: 82%; animation-delay: 0.8s; }
.dot-5 { left: 52%; top: 12%; animation-delay: 3s; }
.dot-6 { left: 63%; top: 76%; animation-delay: 1.7s; }
.dot-7 { left: 72%; top: 27%; animation-delay: 2.8s; }
.dot-8 { left: 84%; top: 62%; animation-delay: 0.5s; }
.dot-9 { left: 92%; top: 16%; animation-delay: 3.5s; }
.dot-10 { left: 12%; top: 48%; animation-delay: 2s; }
.dot-11 { left: 78%; top: 88%; animation-delay: 1s; }
.dot-12 { left: 45%; top: 92%; animation-delay: 4s; }

@keyframes dotPulse {
    0%, 100% { opacity: 0; transform: scale(0.7); }
    50% { opacity: 0.28; transform: scale(1); }
}

.grid-pattern {
    position: absolute; inset: 0; pointer-events: none; opacity: 0.35;
    background-image:
        linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
    background-size: 48px 48px;
    mask-image: linear-gradient(to bottom, transparent, black 20%, black 80%, transparent);
}

@media (prefers-reduced-motion: reduce) { .dot { animation: none; opacity: 0.15; } }
</style>
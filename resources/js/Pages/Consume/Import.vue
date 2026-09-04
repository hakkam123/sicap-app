<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const page = usePage();
const fileInput = ref(null);
const selectedFile = ref(null);
const isDragging = ref(false);

const form = useForm({
    file: null,
});

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        selectedFile.value = file;
        form.file = file;
    }
};

const handleDrop = (e) => {
    isDragging.value = false;
    const file = e.dataTransfer.files[0];
    if (file) {
        selectedFile.value = file;
        form.file = file;
    }
};

const removeFile = () => {
    selectedFile.value = null;
    form.file = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    if (!form.file) return;

    form.post(route('consume.import.store'), {
        onSuccess: () => {
            removeFile();
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Import Data Consume" />

        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Import Data Consume (Excel)
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Unggah file Excel konsumsi sparepart harian untuk diproses secara asynchronous di antrian background.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a
                        :href="route('consume.template')"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors"
                        download
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Download Template Excel
                    </a>
                    <Link
                        :href="route('import-logs.index')"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 rounded-lg text-xs font-medium transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Lihat Import Log
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8 space-y-6">

                <!-- Flash Messages -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg flex items-center justify-between"
                >
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ $page.props.flash.success }}</span>
                    </div>
                </div>

                <div
                    v-if="$page.props.flash?.error"
                    class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg flex items-center justify-between"
                >
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ $page.props.flash.error }}</span>
                    </div>
                </div>

                <!-- Card Upload -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 sm:p-8">
                    <form @submit.prevent="submit" class="space-y-6">

                        <!-- Drag and Drop Zone -->
                        <div
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop"
                            :class="[
                                'border-2 border-dashed rounded-xl p-8 text-center transition-all cursor-pointer',
                                isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-gray-400 bg-gray-50'
                            ]"
                            @click="$refs.fileInput.click()"
                        >
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".xlsx, .xls"
                                class="hidden"
                                @change="handleFileChange"
                            />

                            <div class="flex flex-col items-center justify-center space-y-3">
                                <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <span class="font-semibold text-blue-600 hover:text-blue-500">Pilih file Excel</span> atau seret ke area ini
                                </div>
                                <p class="text-xs text-gray-400">
                                    Format didukung: .xlsx, .xls (Maksimal 10 MB)
                                </p>
                            </div>
                        </div>

                        <!-- Selected File Preview -->
                        <div
                            v-if="selectedFile"
                            class="p-4 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-blue-100 text-blue-700 rounded-md">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">
                                        {{ selectedFile.name }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ (selectedFile.size / 1024).toFixed(1) }} KB
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click.stop="removeFile"
                                class="text-gray-400 hover:text-red-600 transition-colors p-1"
                                title="Batal pilih file"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <InputError :message="form.errors.file" />

                        <!-- Info Text -->
                        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg flex gap-3 text-xs text-amber-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="font-semibold">Informasi Proses:</p>
                                <p class="mt-0.5">
                                    File akan diproses di background. Cek <strong>Import Log</strong> untuk melihat status dan memverifikasi data yang berhasil masuk atau kesalahan baris.
                                </p>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                            <SecondaryButton
                                type="button"
                                @click="removeFile"
                                :disabled="!selectedFile || form.processing"
                            >
                                Batal
                            </SecondaryButton>

                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing || !selectedFile }"
                                :disabled="form.processing || !selectedFile"
                                class="inline-flex items-center gap-1.5"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                {{ form.processing ? 'Mengunggah...' : 'Upload & Proses' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </AppLayout>
</template>


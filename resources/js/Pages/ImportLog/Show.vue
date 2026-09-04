<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    log: {
        type: Object,
        required: true,
    },
});

const formatDate = (isoString) => {
    if (!isoString) return '-';
    const d = new Date(isoString);
    return d.toLocaleString('id-ID', {
        dateStyle: 'full',
        timeStyle: 'medium',
    });
};

const progressPercentage = computed(() => {
    if (!props.log.total_rows || props.log.total_rows === 0) return 0;
    return Math.min(100, Math.round((props.log.processed_rows / props.log.total_rows) * 100));
});

// Parse error messages into structured rows
const parsedErrors = computed(() => {
    if (!props.log.error_message) return [];

    let rawList = [];
    try {
        const decoded = JSON.parse(props.log.error_message);
        if (Array.isArray(decoded)) {
            rawList = decoded;
        } else if (typeof decoded === 'string') {
            rawList = [decoded];
        } else {
            rawList = [JSON.stringify(decoded)];
        }
    } catch (e) {
        rawList = props.log.error_message.split('\n').filter(line => line.trim().length > 0);
    }

    return rawList.map((err, index) => {
        const str = String(err);
        const match = str.match(/^Baris\s+(\d+):\s*(.+)$/i);
        if (match) {
            const rowNum = match[1];
            const msg = match[2];
            let field = 'Data Row';

            if (/part\s*number/i.test(msg) || /pn/i.test(msg)) {
                field = 'Part Number';
            } else if (/tanggal/i.test(msg) || /date/i.test(msg)) {
                field = 'Date';
            } else if (/quantity/i.test(msg) || /qty/i.test(msg)) {
                field = 'Quantity';
            } else if (/amount/i.test(msg)) {
                field = 'Amount';
            }

            return {
                id: index + 1,
                row: rowNum,
                field: field,
                message: msg,
            };
        }

        return {
            id: index + 1,
            row: '-',
            field: 'General',
            message: str,
        };
    });
});
</script>

<template>
    <AppLayout>
        <Head :title="`Detail Import Log - ${log.filename}`" />

        <template #header>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('import-logs.index')"
                    class="text-gray-500 hover:text-gray-700 text-sm"
                >
                    Riwayat Import Log
                </Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Detail Log
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">

                <!-- Header Info Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 sm:p-8">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-100">
                        <div>
                            <div class="flex items-center gap-3">
                                <h3 class="text-lg font-bold text-gray-900">
                                    {{ log.filename }}
                                </h3>
                                <span
                                    v-if="log.status === 'success'"
                                    class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"
                                >
                                    Success
                                </span>
                                <span
                                    v-else-if="log.status === 'processing'"
                                    class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 animate-pulse"
                                >
                                    Processing
                                </span>
                                <span
                                    v-else-if="log.status === 'pending'"
                                    class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700"
                                >
                                    Pending
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800"
                                >
                                    Failed
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1 font-mono">
                                Log ID: {{ log.id }}
                            </p>
                        </div>

                        <Link :href="route('import-logs.index')">
                            <SecondaryButton class="inline-flex items-center gap-1.5 text-xs">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Kembali ke Import Log
                            </SecondaryButton>
                        </Link>
                    </div>

                    <!-- Metadata Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-6">
                        <div>
                            <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Diunggah Oleh
                            </span>
                            <p class="text-sm font-semibold text-gray-900 mt-1">
                                {{ log.user?.name || '-' }}
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ log.user?.email || '' }}
                            </p>
                        </div>

                        <div>
                            <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Waktu Pengunggahan
                            </span>
                            <p class="text-sm font-medium text-gray-800 mt-1">
                                {{ formatDate(log.created_at) }}
                            </p>
                        </div>

                        <div>
                            <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Terakhir Diperbarui
                            </span>
                            <p class="text-sm font-medium text-gray-800 mt-1">
                                {{ formatDate(log.updated_at) }}
                            </p>
                        </div>
                    </div>

                    <!-- Progress Bar & Count -->
                    <div class="mt-8 p-4 bg-gray-50 rounded-xl border border-gray-100">
                        <div class="flex items-center justify-between text-xs font-semibold text-gray-700 mb-2">
                            <span>Progres Pemrosesan Baris</span>
                            <span class="text-sm font-bold text-gray-900">
                                {{ log.processed_rows }} / {{ log.total_rows }} baris ({{ progressPercentage }}%)
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                            <div
                                class="h-2.5 rounded-full transition-all duration-500"
                                :class="[
                                    log.status === 'success' ? 'bg-green-600' :
                                    log.status === 'failed' ? 'bg-red-600' : 'bg-blue-600'
                                ]"
                                :style="{ width: `${progressPercentage}%` }"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- Success State Card -->
                <div
                    v-if="log.status === 'success'"
                    class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-green-500 flex items-start gap-4"
                >
                    <div class="p-2 rounded-full bg-green-100 text-green-600 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-gray-900">
                            Proses Import Selesai dengan Berhasil
                        </h4>
                        <p class="text-xs text-gray-600 mt-1">
                            Semua baris konsumsi pada file ini telah berhasil divalidasi dan disimpan ke tabel data konsumsi tanpa kesalahan.
                        </p>
                    </div>
                </div>

                <!-- Error Messages Table (If Failed) -->
                <div
                    v-if="log.status === 'failed' || parsedErrors.length > 0"
                    class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-t-4 border-red-500 space-y-4"
                >
                    <div>
                        <h4 class="text-base font-bold text-red-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            Daftar Kesalahan Validasi Baris ({{ parsedErrors.length }} Kesalahan)
                        </h4>
                        <p class="text-xs text-gray-500 mt-1">
                            Karena sistem menerapkan transaksi atomik, seluruh transaksi dibatalkan (rollback) untuk menjaga konsistensi database.
                        </p>
                    </div>

                    <!-- Errors Table -->
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-20">
                                        Row
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-36">
                                        Field
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Message
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="err in parsedErrors"
                                    :key="err.id"
                                    class="hover:bg-red-50/40 transition-colors"
                                >
                                    <td class="px-4 py-3 text-xs font-mono font-bold text-gray-900">
                                        {{ err.row }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                            {{ err.field }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-red-700 font-medium">
                                        {{ err.message }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>


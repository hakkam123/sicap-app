<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    logs: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ status: '' }),
    },
});

const page = usePage();
const currentStatus = ref(props.filters.status || '');

const statusTabs = [
    { label: 'Semua Status', value: '' },
    { label: 'Pending', value: 'pending' },
    { label: 'Processing', value: 'processing' },
    { label: 'Success', value: 'success' },
    { label: 'Failed', value: 'failed' },
];

const filterByStatus = (statusVal) => {
    currentStatus.value = statusVal;
    router.get(
        route('import-logs.index'),
        { status: statusVal },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

const formatDate = (isoString) => {
    if (!isoString) return '-';
    const d = new Date(isoString);
    return d.toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Riwayat Import Log" />

        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Riwayat Import Log
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Monitoring status proses background job dan rincian data import Excel.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('consume.import')">
                        <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            Import File Baru
                        </button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Flash Success Message -->
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

                <!-- Status Filter Tabs -->
                <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                    <button
                        v-for="tab in statusTabs"
                        :key="tab.value"
                        @click="filterByStatus(tab.value)"
                        :class="[
                            'px-4 py-1.5 rounded-full text-xs font-semibold transition-all',
                            currentStatus === tab.value
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'
                        ]"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Table Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-12">
                                        No
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Nama File
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Total Rows
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Processed Rows
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Uploaded By
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Waktu Upload
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="logs.data.length === 0">
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-400 text-sm">
                                        Tidak ada riwayat import ditemukan.
                                    </td>
                                </tr>
                                <tr
                                    v-for="(log, idx) in logs.data"
                                    :key="log.id"
                                    class="hover:bg-gray-50 transition-colors"
                                >
                                    <td class="px-4 py-3 text-xs text-gray-500 font-medium">
                                        {{ (logs.current_page - 1) * logs.per_page + idx + 1 }}
                                    </td>
                                    <td class="px-4 py-3 text-xs font-medium text-gray-900 max-w-xs truncate" :title="log.filename">
                                        {{ log.filename }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-center whitespace-nowrap">
                                        <span
                                            v-if="log.status === 'success'"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
                                        >
                                            Success
                                        </span>
                                        <span
                                            v-else-if="log.status === 'processing'"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 animate-pulse"
                                        >
                                            Processing
                                        </span>
                                        <span
                                            v-else-if="log.status === 'pending'"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700"
                                        >
                                            Pending
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"
                                        >
                                            Failed
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-center font-medium text-gray-700">
                                        {{ log.total_rows }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-center font-bold" :class="log.status === 'success' ? 'text-green-700' : 'text-gray-700'">
                                        {{ log.processed_rows }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600">
                                        {{ log.user?.name || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                        {{ formatDate(log.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-right whitespace-nowrap">
                                        <Link
                                            :href="route('import-logs.show', log.id)"
                                            class="text-blue-600 hover:text-blue-900 font-semibold hover:underline inline-flex items-center gap-1"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            Detail
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <Pagination :links="logs.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

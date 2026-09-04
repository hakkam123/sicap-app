<script setup>
import { computed, useSlots } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    columns: {
        type: Array,
        required: true,
    },
    data: {
        type: Object,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['sort']);
const slots = useSlots();

const hasActionsSlot = computed(() => !!slots.actions);

const rows = computed(() => {
    return props.data?.data || [];
});

const total = computed(() => {
    return props.data?.total || 0;
});

const from = computed(() => {
    return props.data?.from || 0;
});

const to = computed(() => {
    return props.data?.to || 0;
});

const currentPage = computed(() => {
    return props.data?.current_page || 1;
});

const perPage = computed(() => {
    return props.data?.per_page || 20;
});

const links = computed(() => {
    return props.data?.links || [];
});

const getCellValue = (row, key) => {
    if (!key) return '';

    if (key.includes('.')) {
        return key
            .split('.')
            .reduce((acc, part) => acc && acc[part], row) ?? '';
    }

    return row[key] ?? '';
};

const getAlignmentClass = (align) => {
    switch (align) {
        case 'right':
            return 'text-right';

        case 'center':
            return 'text-center';

        case 'left':
        default:
            return 'text-left';
    }
};

const handleSort = (column) => {
    if (column.sortable) {
        emit('sort', column.key);
    }
};
</script>

<template>
    <div
        class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-slate-200 p-5 sm:p-6 space-y-4"
    >
        <!-- Table Responsive Container -->
        <div class="overflow-x-auto relative">
            <!-- Loading Overlay -->
            <div
                v-if="loading"
                class="absolute inset-0 bg-white/70 backdrop-blur-2xs flex items-center justify-center z-10"
            >
                <div class="flex items-center gap-2 text-blue-600 text-xs font-semibold">
                    <svg
                        class="animate-spin h-5 w-5"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>

                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>

                    <span>Memuat data...</span>
                </div>
            </div>

            <!-- Table -->
            <table class="min-w-full divide-y divide-slate-200 text-left">
                <thead class="bg-slate-50">
                    <tr>
                        <!-- Number Column -->
                        <th
                            scope="col"
                            class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider w-14 text-left"
                        >
                            No
                        </th>

                        <!-- Dynamic Columns -->
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            scope="col"
                            :class="[
                                'px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider',
                                col.width || '',
                                getAlignmentClass(col.align),
                                col.sortable
                                    ? 'cursor-pointer select-none hover:text-slate-800'
                                    : '',
                            ]"
                            @click="handleSort(col)"
                        >
                            <div
                                class="inline-flex items-center gap-1.5"
                                :class="
                                    col.align === 'right'
                                        ? 'justify-end'
                                        : col.align === 'center'
                                            ? 'justify-center'
                                            : ''
                                "
                            >
                                <span>{{ col.label }}</span>

                                <svg
                                    v-if="col.sortable"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-3 w-3 text-slate-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"
                                    />
                                </svg>
                            </div>
                        </th>

                        <!-- Actions Header -->
                        <th
                            v-if="hasActionsSlot"
                            scope="col"
                            class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider text-right w-28"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 bg-white">
                    <!-- Empty State -->
                    <tr v-if="rows.length === 0">
                        <td
                            :colspan="columns.length + (hasActionsSlot ? 2 : 1)"
                            class="px-4 py-12 text-center text-slate-400 text-xs"
                        >
                            <slot name="empty">
                                <div
                                    class="flex flex-col items-center justify-center space-y-2"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-8 w-8 text-slate-300"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.5"
                                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                                        />
                                    </svg>

                                    <p>Tidak ada data ditemukan.</p>
                                </div>
                            </slot>
                        </td>
                    </tr>

                    <!-- Data Rows -->
                    <tr
                        v-for="(row, rowIndex) in rows"
                        :key="row.id || rowIndex"
                        class="hover:bg-slate-50/80 transition-colors"
                    >
                        <!-- Row Number -->
                        <td
                            class="px-4 py-3 text-xs text-slate-500 font-medium whitespace-nowrap"
                        >
                            {{ (currentPage - 1) * perPage + rowIndex + 1 }}
                        </td>

                        <!-- Cells -->
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            :class="[
                                'px-4 py-3 text-xs text-slate-700',
                                getAlignmentClass(col.align),
                            ]"
                        >
                            <slot
                                :name="`cell-${col.key}`"
                                :row="row"
                                :value="getCellValue(row, col.key)"
                                :index="rowIndex"
                            >
                                {{ getCellValue(row, col.key) || '-' }}
                            </slot>
                        </td>

                        <!-- Actions Cell -->
                        <td
                            v-if="hasActionsSlot"
                            class="px-4 py-3 text-xs text-right font-medium whitespace-nowrap space-x-2"
                        >
                            <slot
                                name="actions"
                                :row="row"
                                :index="rowIndex"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Bottom Info + Pagination -->
        <div
            v-if="total > 0"
            class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3"
        >
            <!-- Info Data -->
            <div class="flex items-center gap-3 text-xs text-slate-500">
                <span>
                    Menampilkan
                    <span class="font-bold text-slate-800">
                        {{ from }} - {{ to }}
                    </span>
                    dari
                    <span class="font-bold text-slate-800">
                        {{ total }}
                    </span>
                    rekaman data
                </span>

                <span class="hidden sm:inline text-slate-300">|</span>

                <span>
                    Halaman
                    <span class="font-bold text-slate-800">
                        {{ currentPage }}
                    </span>
                    dari
                    <span class="font-bold text-slate-800">
                        {{ Math.ceil(total / perPage) || 1 }}
                    </span>
                </span>
            </div>

            <!-- Pagination -->
            <div
                class="inline-flex items-center gap-1 flex-wrap justify-center"
            >
                <template
                    v-for="(link, index) in links"
                    :key="index"
                >
                    <!-- Disabled Pagination -->
                    <div
                        v-if="link.url === null"
                        class="px-2.5 py-1 text-xs text-slate-400 bg-slate-50 border border-slate-200 rounded-md cursor-not-allowed select-none"
                        v-html="link.label"
                    />

                    <!-- Active / Available Pagination -->
                    <Link
                        v-else
                        :href="link.url"
                        :class="[
                            'px-2.5 py-1 text-xs rounded-md border transition-colors select-none',
                            link.active
                                ? 'bg-blue-600 text-white border-blue-600 font-semibold shadow-xs'
                                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50 hover:border-slate-300',
                        ]"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import {
    Search,
    RotateCcw,
    Layers,
    MapPin,
    Cpu,
    CheckCircle2,
    AlertCircle,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight
} from 'lucide-vue-next';

const props = defineProps({
    parts: {
        type: [Array, Object],
        default: () => [],
    },
});

// Defensive: normalize parts to always be an Array (handle Object from stale cache)
const partsList = computed(() => {
    if (Array.isArray(props.parts)) return props.parts;
    if (props.parts && typeof props.parts === 'object') return Object.values(props.parts);
    return [];
});

// ==========================================
// 1. FILTER & SEARCH STATE (DEBOUNCED)
// ==========================================
const searchInput = ref('');
const debouncedSearch = ref('');
const filterAddressingStatus = ref('all'); // 'all', 'addressed', 'unaddressed'
const selectedArea = ref('');

let debounceTimer = null;
watch(searchInput, (newVal) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        debouncedSearch.value = newVal.trim().toLowerCase();
        currentPage.value = 1; // Reset to page 1 on search
    }, 300);
});

// Area list extracted from all parts for filtering
const uniqueAreas = computed(() => {
    const areaMap = new Map();
    partsList.value.forEach(p => {
        (p.areas || []).forEach(a => {
            if (typeof a === 'string') {
                if (!areaMap.has(a)) areaMap.set(a, { id: a, name: a, code: a });
            } else if (a && a.id && !areaMap.has(a.id)) {
                areaMap.set(a.id, a);
            }
        });
    });
    return Array.from(areaMap.values()).sort((a, b) => a.name.localeCompare(b.name));
});

// ==========================================
// 2. COMPUTED FILTERED DATA (100% CLIENT-SIDE)
// ==========================================
const filteredParts = computed(() => {
    const q = debouncedSearch.value;
    const status = filterAddressingStatus.value;
    const areaFilter = selectedArea.value;

    return partsList.value.filter(part => {
        // Addressing Status Filter
        const hasAddressing = !!(part.addressing && part.addressing.trim());
        if (status === 'addressed' && !hasAddressing) return false;
        if (status === 'unaddressed' && hasAddressing) return false;

        // Area Filter
        if (areaFilter) {
            const hasArea = (part.areas || []).some(a => {
                if (typeof a === 'string') return a === areaFilter;
                return a.id === areaFilter || a.code === areaFilter;
            });
            if (!hasArea) return false;
        }

        // Search Query Filter
        if (!q) return true;

        const pnBaan = (part.pn_baan || '').toLowerCase();
        const partCode = (part.part_number_code || '').toLowerCase();
        const desc = (part.description || '').toLowerCase();
        const addr = (part.addressing || '').toLowerCase();

        const machinesStr = Array.isArray(part.machines)
            ? part.machines.map(m => typeof m === 'string' ? m : `${m.code || ''} ${m.name || ''}`).join(' ').toLowerCase()
            : (part.machines_text || '').toLowerCase();

        const areasStr = Array.isArray(part.areas)
            ? part.areas.map(a => typeof a === 'string' ? a : `${a.code || ''} ${a.name || ''}`).join(' ').toLowerCase()
            : (part.areas_text || '').toLowerCase();

        return (
            pnBaan.includes(q) ||
            partCode.includes(q) ||
            desc.includes(q) ||
            addr.includes(q) ||
            machinesStr.includes(q) ||
            areasStr.includes(q)
        );
    });
});

// Summary Counts
const totalCount = computed(() => partsList.value.length);
const addressedCount = computed(() => partsList.value.filter(p => !!(p.addressing && p.addressing.trim())).length);
const unaddressedCount = computed(() => totalCount.value - addressedCount.value);

// Helper to format machines comma-separated
const formatMachines = (machines, fallback) => {
    if (fallback && fallback !== '-') return fallback;
    if (!machines || machines.length === 0) return '-';
    return machines.map(m => typeof m === 'string' ? m : (m.name || m.code)).join(', ');
};

// Helper to format areas comma-separated
const formatAreas = (areas, fallback) => {
    if (fallback && fallback !== '-') return fallback;
    if (!areas || areas.length === 0) return '-';
    return areas.map(a => typeof a === 'string' ? a : (a.code || a.name)).join(', ');
};

// ==========================================
// 3. CLIENT-SIDE PAGINATION
// ==========================================
const perPage = ref(25);
const currentPage = ref(1);

const totalPages = computed(() => {
    if (perPage.value === -1) return 1;
    return Math.ceil(filteredParts.value.length / perPage.value) || 1;
});

const paginatedParts = computed(() => {
    if (perPage.value === -1) return filteredParts.value;
    const start = (currentPage.value - 1) * perPage.value;
    return filteredParts.value.slice(start, start + perPage.value);
});

const startItemIndex = computed(() => {
    if (filteredParts.value.length === 0) return 0;
    return (currentPage.value - 1) * perPage.value + 1;
});

const endItemIndex = computed(() => {
    if (perPage.value === -1) return filteredParts.value.length;
    return Math.min(currentPage.value * perPage.value, filteredParts.value.length);
});

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const resetAllFilters = () => {
    searchInput.value = '';
    debouncedSearch.value = '';
    filterAddressingStatus.value = 'all';
    selectedArea.value = '';
    currentPage.value = 1;
};
</script>

<template>
    <PublicLayout>
        <Head title="Katalog Sparepart & Addressing Publik" />

        <div class="space-y-6">

            <!-- HERO & STATS HEADER -->
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 rounded-2xl p-6 sm:p-8 text-white shadow-md border border-slate-700/50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div class="space-y-1.5 max-w-2xl">

                        <h2 class="text-xl sm:text-2xl font-bold tracking-tight">
                            Katalog Sparepart PT Astra Visteon Indonesia
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-300">
                            Cari nomor part, kode sparepart, lokasi addressing, serta mapping mesin dan area secara real-time.
                        </p>
                    </div>

                    <!-- Mini KPI Badges -->
                    <!-- <div class="grid grid-cols-3 gap-2 sm:gap-3 shrink-0">
                        <div class="bg-white/10 backdrop-blur-sm border border-white/10 rounded-xl p-3 text-center">
                            <p class="text-[11px] font-medium text-slate-300 uppercase tracking-wider">Total Part</p>
                            <p class="text-lg sm:text-xl font-extrabold text-white mt-0.5">{{ totalCount.toLocaleString() }}</p>
                        </div>
                        <div class="bg-emerald-500/15 backdrop-blur-sm border border-emerald-500/30 rounded-xl p-3 text-center">
                            <p class="text-[11px] font-medium text-emerald-300 uppercase tracking-wider">Teraddress</p>
                            <p class="text-lg sm:text-xl font-extrabold text-emerald-400 mt-0.5">{{ addressedCount.toLocaleString() }}</p>
                        </div>
                        <div class="bg-amber-500/15 backdrop-blur-sm border border-amber-500/30 rounded-xl p-3 text-center">
                            <p class="text-[11px] font-medium text-amber-300 uppercase tracking-wider">Belum Ada</p>
                            <p class="text-lg sm:text-xl font-extrabold text-amber-400 mt-0.5">{{ unaddressedCount.toLocaleString() }}</p>
                        </div>
                    </div> -->
                </div>
            </div>

            <!-- SEARCH & FILTER CONTROLS (100% Client-side) -->
            <div class="bg-white p-4 sm:p-5 rounded-xl shadow-xs border border-slate-200 space-y-4">
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                    
                    <!-- Search Input (Debounced 300ms) -->
                    <div class="relative flex-1">
                        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                        <input
                            v-model="searchInput"
                            type="text"
                            placeholder="Cari PN BAAN, kode part, deskripsi, lokasi rak/addressing, atau nama mesin..."
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 focus:bg-white transition"
                        />
                        <button
                            v-if="searchInput"
                            @click="searchInput = ''"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-slate-600 font-bold"
                        >
                            &times;
                        </button>
                    </div>

                    <!-- Filter by Addressing Status & Area -->
                    <div class="flex flex-wrap items-center gap-2">
                        
                        <!-- Addressing Status Pill -->
                        <div class="inline-flex rounded-lg bg-slate-100 p-1 border border-slate-200 text-xs">
                            <button
                                type="button"
                                @click="filterAddressingStatus = 'all'; currentPage = 1;"
                                :class="[
                                    'px-3 py-1.5 rounded-md font-medium transition',
                                    filterAddressingStatus === 'all'
                                        ? 'bg-white text-slate-900 shadow-xs font-bold'
                                        : 'text-slate-600 hover:text-slate-900'
                                ]"
                            >
                                Semua
                            </button>
                            <button
                                type="button"
                                @click="filterAddressingStatus = 'addressed'; currentPage = 1;"
                                :class="[
                                    'px-3 py-1.5 rounded-md font-medium transition',
                                    filterAddressingStatus === 'addressed'
                                        ? 'bg-white text-emerald-700 shadow-xs font-bold'
                                        : 'text-slate-600 hover:text-slate-900'
                                ]"
                            >
                                Teraddress ({{ addressedCount }})
                            </button>
                            <button
                                type="button"
                                @click="filterAddressingStatus = 'unaddressed'; currentPage = 1;"
                                :class="[
                                    'px-3 py-1.5 rounded-md font-medium transition',
                                    filterAddressingStatus === 'unaddressed'
                                        ? 'bg-white text-amber-700 shadow-xs font-bold'
                                        : 'text-slate-600 hover:text-slate-900'
                                ]"
                            >
                                Belum ({{ unaddressedCount }})
                            </button>
                        </div>

                        <!-- Filter Area Dropdown -->
                        <select
                            v-model="selectedArea"
                            @change="currentPage = 1"
                            class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-900"
                        >
                            <option value="">Semua Area</option>
                            <option v-for="area in uniqueAreas" :key="area.id" :value="area.id">
                                Area: {{ area.name }} ({{ area.code }})
                            </option>
                        </select>

                        <!-- Items Per Page -->
                        <select
                            v-model.number="perPage"
                            @change="currentPage = 1"
                            class="px-2.5 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-900"
                        >
                            <option :value="25">25 baris</option>
                            <option :value="50">50 baris</option>
                            <option :value="100">100 baris</option>
                            <option :value="-1">Semua</option>
                        </select>

                        <!-- Reset Button -->
                        <button
                            v-if="searchInput || filterAddressingStatus !== 'all' || selectedArea"
                            type="button"
                            @click="resetAllFilters"
                            class="p-2 border border-slate-200 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition"
                            title="Reset semua filter"
                        >
                            <RotateCcw class="w-4 h-4" />
                        </button>
                    </div>

                </div>

                <!-- Active Filter Indicator -->
                <div class="flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-100">
                    <div>
                        Menemukan <strong class="text-slate-800">{{ filteredParts.length }}</strong> sparepart
                        <span v-if="debouncedSearch"> untuk kata kunci <strong class="text-slate-900">"{{ debouncedSearch }}"</strong></span>
                    </div>
                    <div v-if="filteredParts.length > 0">
                        Halaman <strong class="text-slate-800">{{ currentPage }}</strong> dari {{ totalPages }}
                    </div>
                </div>
            </div>

            <!-- DATA TABLE -->
            <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100/80 border-b border-slate-200 text-slate-700 uppercase font-bold text-[11px] tracking-wider">
                                <th class="py-3 px-4 w-12 text-center text-slate-400">#</th>
                                <th class="py-3 px-4 min-w-[160px]">Nomor Sparepart</th>
                                <th class="py-3 px-4 min-w-[130px]">Kode Sparepart</th>
                                <th class="py-3 px-4 min-w-[200px]">Nama Sparepart</th>
                                <th class="py-3 px-4 min-w-[220px]">Addressing</th>
                                <th class="py-3 px-4 min-w-[160px]">Machine</th>
                                <th class="py-3 px-4 min-w-[100px]">Area</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <template v-if="paginatedParts.length > 0">
                                <tr
                                    v-for="(part, idx) in paginatedParts"
                                    :key="part.id"
                                    class="hover:bg-slate-50/80 transition-colors"
                                >
                                    <!-- Row Number -->
                                    <td class="py-3 px-4 text-center text-slate-400 font-mono text-[11px]">
                                        {{ (currentPage - 1) * (perPage === -1 ? 0 : perPage) + idx + 1 }}
                                    </td>

                                    <!-- Nomor Sparepart (PN BAAN) -->
                                    <td class="py-3 px-4 font-mono font-bold text-slate-900 whitespace-nowrap">
                                        {{ part.pn_baan }}
                                    </td>

                                    <!-- Kode Sparepart -->
                                    <td class="py-3 px-4 font-mono text-slate-700">
                                        <span v-if="part.part_number_code" class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded font-semibold text-slate-800">
                                            {{ part.part_number_code }}
                                        </span>
                                        <span v-else class="text-slate-400">-</span>
                                    </td>

                                    <!-- Nama Sparepart (Description) -->
                                    <td class="py-3 px-4 text-slate-700 font-medium">
                                        {{ part.description || '-' }}
                                    </td>

                                    <!-- Addressing (Lokasi) -->
                                    <td class="py-3 px-4">
                                        <div v-if="part.addressing && part.addressing.trim()" class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-md font-semibold text-xs">
                                            <MapPin class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                            <span>{{ part.addressing }}</span>
                                        </div>
                                        <span v-else class="italic text-slate-400 text-xs">
                                            Belum teraddress
                                        </span>
                                    </td>

                                    <!-- Machine -->
                                    <td class="py-3 px-4 text-slate-600">
                                        <span class="leading-relaxed">
                                            {{ formatMachines(part.machines, part.machines_text) }}
                                        </span>
                                    </td>

                                    <!-- Area -->
                                    <td class="py-3 px-4 text-slate-600">
                                        <span class="inline-flex flex-wrap gap-1">
                                            {{ formatAreas(part.areas, part.areas_text) }}
                                        </span>
                                    </td>
                                </tr>
                            </template>

                            <!-- Empty State -->
                            <tr v-else>
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <AlertCircle class="w-8 h-8 text-slate-300" />
                                        <p class="font-semibold text-slate-700">Tidak ada data sparepart yang cocok.</p>
                                        <p class="text-xs text-slate-400 max-w-md">
                                            Coba sesuaikan kata kunci pencarian atau reset filter untuk menampilkan kembali semua data.
                                        </p>
                                        <button
                                            type="button"
                                            @click="resetAllFilters"
                                            class="mt-2 px-3 py-1.5 bg-slate-900 text-white rounded-md text-xs font-semibold hover:bg-slate-700 transition"
                                        >
                                            Reset Filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- CLIENT-SIDE PAGINATION FOOTER -->
                <div
                    v-if="filteredParts.length > 0 && perPage !== -1"
                    class="p-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600"
                >
                    <div>
                        Menampilkan <strong>{{ startItemIndex }}</strong> - <strong>{{ endItemIndex }}</strong> dari total <strong>{{ filteredParts.length }}</strong> data
                    </div>

                    <div class="flex items-center gap-1">
                        <!-- First Page -->
                        <button
                            type="button"
                            :disabled="currentPage === 1"
                            @click="goToPage(1)"
                            class="p-1.5 border border-slate-200 rounded-md text-slate-600 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition"
                            title="Halaman Pertama"
                        >
                            <ChevronsLeft class="w-4 h-4" />
                        </button>

                        <!-- Prev Page -->
                        <button
                            type="button"
                            :disabled="currentPage === 1"
                            @click="goToPage(currentPage - 1)"
                            class="px-2.5 py-1.5 border border-slate-200 rounded-md text-slate-600 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition"
                            title="Halaman Sebelumnya"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>

                        <!-- Current Page Indicator -->
                        <span class="px-3 py-1.5 font-bold text-slate-800 bg-white border border-slate-200 rounded-md">
                            {{ currentPage }} / {{ totalPages }}
                        </span>

                        <!-- Next Page -->
                        <button
                            type="button"
                            :disabled="currentPage === totalPages"
                            @click="goToPage(currentPage + 1)"
                            class="px-2.5 py-1.5 border border-slate-200 rounded-md text-slate-600 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition"
                            title="Halaman Berikutnya"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>

                        <!-- Last Page -->
                        <button
                            type="button"
                            :disabled="currentPage === totalPages"
                            @click="goToPage(totalPages)"
                            class="p-1.5 border border-slate-200 rounded-md text-slate-600 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed transition"
                            title="Halaman Terakhir"
                        >
                            <ChevronsRight class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </PublicLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    partNumbers: {
        type: Array,
        required: true,
    },
    areas: {
        type: Array,
        required: true,
    },
});

const page = usePage();

// Format today's date YYYY-MM-DD
const today = new Date().toISOString().split('T')[0];

const form = useForm({
    part_number_id: '',
    area_id: '',
    machine_id: '',
    quantity: '',
    amount: '',
    consumed_at: today,
});

// Part Search State
const partSearch = ref('');
const isPartDropdownOpen = ref(false);

const filteredParts = computed(() => {
    if (!partSearch.value) {
        return props.partNumbers.slice(0, 30);
    }
    const q = partSearch.value.toLowerCase();
    return props.partNumbers
        .filter(p => p.pn_baan.toLowerCase().includes(q) || (p.description && p.description.toLowerCase().includes(q)))
        .slice(0, 30);
});

const selectedPart = computed(() => {
    return props.partNumbers.find(p => p.id === form.part_number_id) || null;
});

const selectPart = (part) => {
    form.part_number_id = part.id;
    partSearch.value = part.pn_baan;
    isPartDropdownOpen.value = false;
};

const clearPart = () => {
    form.part_number_id = '';
    partSearch.value = '';
};

// Machine Dropdown State (Loaded via AJAX when area changes)
const availableMachines = ref([]);
const isLoadingMachines = ref(false);

watch(() => form.area_id, async (newAreaId) => {
    form.machine_id = '';
    availableMachines.value = [];

    if (!newAreaId) return;

    isLoadingMachines.value = true;
    try {
        const res = await fetch(route('mapping.machines-by-area') + '?area_id=' + encodeURIComponent(newAreaId), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (res.ok) {
            availableMachines.value = await res.json();
        }
    } catch (err) {
        console.error('Failed to load machines:', err);
    } finally {
        isLoadingMachines.value = false;
    }
});

// Estimated Amount Calculation
const estimatedAmount = computed(() => {
    if (!selectedPart.value || selectedPart.value.price_per_unit === null) return null;
    const qty = parseInt(form.quantity, 10);
    if (isNaN(qty)) return null;
    return Math.abs(qty) * Number(selectedPart.value.price_per_unit);
});

const formatRupiah = (val) => {
    if (val === null || val === undefined || val === '') return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(val);
};

const resetForm = () => {
    form.reset();
    form.consumed_at = today;
    partSearch.value = '';
    availableMachines.value = [];
};

const submit = () => {
    form.post(route('consume.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Input Consume Manual" />

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Input Consume Manual
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Formulir pencatatan konsumsi sparepart harian/shift secara manual.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8 space-y-6">

                <!-- Flash Success Message -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg flex items-center justify-between"
                >
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium">{{ $page.props.flash.success }}</span>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 sm:p-8">
                    <form @submit.prevent="submit" class="space-y-6">

                        <!-- 1. Part Number Searchable Dropdown -->
                        <div class="relative">
                            <InputLabel for="part_search" value="Part Number (PN BAAN) *" />
                            
                            <div class="relative mt-1">
                                <input
                                    id="part_search"
                                    type="text"
                                    v-model="partSearch"
                                    @focus="isPartDropdownOpen = true"
                                    placeholder="Ketik untuk mencari PN BAAN atau deskripsi part..."
                                    class="w-full pl-3 pr-10 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500"
                                    autocomplete="off"
                                    required
                                />
                                <button
                                    v-if="form.part_number_id"
                                    type="button"
                                    @click="clearPart"
                                    class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600"
                                    title="Hapus pilihan part"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Dropdown Results -->
                            <div
                                v-if="isPartDropdownOpen"
                                class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-y-auto"
                            >
                                <div
                                    v-if="filteredParts.length === 0"
                                    class="p-3 text-xs text-gray-400 text-center"
                                >
                                    Tidak ada part number yang cocok.
                                </div>
                                <div
                                    v-for="part in filteredParts"
                                    :key="part.id"
                                    @click="selectPart(part)"
                                    class="p-2.5 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-none transition-colors"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold font-mono text-purple-700">
                                            {{ part.pn_baan }}
                                        </span>
                                        <span v-if="part.price_per_unit" class="text-xs text-gray-500">
                                            {{ formatRupiah(part.price_per_unit) }}
                                        </span>
                                    </div>
                                    <p v-if="part.description" class="text-xs text-gray-600 truncate mt-0.5">
                                        {{ part.description }}
                                    </p>
                                </div>
                            </div>

                            <!-- Selected Part Info Banner -->
                            <div
                                v-if="selectedPart"
                                class="mt-2 p-3 bg-purple-50 border border-purple-200 rounded-md flex items-center justify-between text-xs"
                            >
                                <div>
                                    <span class="font-bold text-purple-900">{{ selectedPart.pn_baan }}</span>
                                    <span class="text-purple-700 ml-2">{{ selectedPart.description || '-' }}</span>
                                </div>
                                <div class="font-semibold text-purple-900">
                                    Harga: {{ formatRupiah(selectedPart.price_per_unit) }}
                                </div>
                            </div>

                            <InputError class="mt-1" :message="form.errors.part_number_id" />
                        </div>

                        <!-- 2. Area & Machine (2-columns) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Area Dropdown (Optional) -->
                            <div>
                                <InputLabel for="area_id" value="Area (Opsional)" />
                                <select
                                    id="area_id"
                                    v-model="form.area_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm"
                                >
                                    <option value="">-- Tanpa Area / Global --</option>
                                    <option v-for="area in areas" :key="area.id" :value="area.id">
                                        {{ area.name }} ({{ area.code }})
                                    </option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.area_id" />
                            </div>

                            <!-- Machine Dropdown (Optional, Dependent on Area) -->
                            <div>
                                <div class="flex items-center justify-between">
                                    <InputLabel for="machine_id" value="Machine / Station (Opsional)" />
                                    <span v-if="isLoadingMachines" class="text-xs text-blue-600 animate-pulse">
                                        Memuat mesin...
                                    </span>
                                </div>
                                <select
                                    id="machine_id"
                                    v-model="form.machine_id"
                                    :disabled="!form.area_id || isLoadingMachines"
                                    class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm disabled:bg-gray-100 disabled:text-gray-400"
                                >
                                    <option value="">
                                        {{ form.area_id ? '-- Pilih Machine / Station --' : '-- Pilih area terlebih dahulu --' }}
                                    </option>
                                    <option v-for="mch in availableMachines" :key="mch.id" :value="mch.id">
                                        {{ mch.name }} ({{ mch.code }})
                                    </option>
                                </select>
                                <InputError class="mt-1" :message="form.errors.machine_id" />
                            </div>
                        </div>

                        <!-- 3. Quantity & Amount (2-columns) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Quantity -->
                            <div>
                                <InputLabel for="quantity" value="Quantity *" />
                                <TextInput
                                    id="quantity"
                                    v-model="form.quantity"
                                    type="number"
                                    step="1"
                                    class="mt-1 block w-full"
                                    placeholder="Contoh: -1 (pemakaian keluar) atau 1"
                                    required
                                />
                                <p class="mt-1 text-xs text-gray-500">
                                    Dapat diisi nilai negatif (stok keluar) atau positif.
                                </p>
                                <InputError class="mt-1" :message="form.errors.quantity" />
                            </div>

                            <!-- Amount (Opsional) -->
                            <div>
                                <InputLabel for="amount" value="Amount Nominal (Rp)" />
                                <TextInput
                                    id="amount"
                                    v-model="form.amount"
                                    type="number"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                    :placeholder="estimatedAmount !== null ? `Estimasi: ${estimatedAmount}` : 'Opsional / otomatis'"
                                />
                                <p v-if="estimatedAmount !== null && !form.amount" class="mt-1 text-xs text-emerald-600">
                                    Otomatis dihitung: {{ formatRupiah(estimatedAmount) }}
                                </p>
                                <p v-else class="mt-1 text-xs text-gray-500">
                                    Biarkan kosong untuk menghitung otomatis dari harga part.
                                </p>
                                <InputError class="mt-1" :message="form.errors.amount" />
                            </div>
                        </div>

                        <!-- 4. Consumed At Date Picker -->
                        <div>
                            <InputLabel for="consumed_at" value="Tanggal Consume *" />
                            <TextInput
                                id="consumed_at"
                                v-model="form.consumed_at"
                                type="date"
                                class="mt-1 block w-full sm:w-60"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.consumed_at" />
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                            <SecondaryButton type="button" @click="resetForm">
                                Reset Form
                            </SecondaryButton>

                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-1.5"
                            >
                                
                                {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </AppLayout>
</template>


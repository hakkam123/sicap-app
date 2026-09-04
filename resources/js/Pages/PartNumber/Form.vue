<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    partNumber: {
        type: Object,
        default: null,
    },
});

const isEditing = computed(() => !!props.partNumber);
const title = computed(() => (isEditing.value ? 'Edit Part Number' : 'Tambah Part Number Baru'));

const form = useForm({
    pn_baan: props.partNumber?.pn_baan || '',
    description: props.partNumber?.description || '',
    price_per_unit: props.partNumber?.price_per_unit !== null && props.partNumber?.price_per_unit !== undefined
        ? props.partNumber.price_per_unit
        : '',
});

const submit = () => {
    if (isEditing.value) {
        form.put(route('part-numbers.update', props.partNumber.id));
    } else {
        form.post(route('part-numbers.store'));
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="title" />

        <template #header>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('part-numbers.index')"
                    class="text-gray-500 hover:text-gray-700 text-sm"
                >
                    Master Data Part Number
                </Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ title }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- PN BAAN -->
                        <div>
                            <InputLabel for="pn_baan" value="Nomor Part (PN BAAN) *" />
                            <TextInput
                                id="pn_baan"
                                v-model="form.pn_baan"
                                type="text"
                                class="mt-1 block w-full uppercase font-mono"
                                placeholder="Contoh: SPFAMEBITHOL-2295"
                                required
                                autofocus
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                Kode unik part number dari sistem BAAN (maksimal 100 karakter).
                            </p>
                            <InputError class="mt-1" :message="form.errors.pn_baan" />
                        </div>

                        <!-- Description -->
                        <div>
                            <InputLabel for="description" value="Deskripsi Sparepart" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                placeholder="Contoh: Bit Holder 1736234 (Rear)..."
                            ></textarea>
                            <InputError class="mt-1" :message="form.errors.description" />
                        </div>

                        <!-- Price Per Unit -->
                        <div>
                            <InputLabel for="price_per_unit" value="Harga Satuan (Rp)" />
                            <TextInput
                                id="price_per_unit"
                                v-model="form.price_per_unit"
                                type="number"
                                step="0.01"
                                min="0"
                                class="mt-1 block w-full"
                                placeholder="Contoh: 3100000"
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                Harga per unit sparepart dalam Rupiah (opsional).
                            </p>
                            <InputError class="mt-1" :message="form.errors.price_per_unit" />
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                            <Link :href="route('part-numbers.index')">
                                <SecondaryButton type="button">
                                    Batal
                                </SecondaryButton>
                            </Link>

                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                {{ isEditing ? 'Simpan Perubahan' : 'Tambah Part Number' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


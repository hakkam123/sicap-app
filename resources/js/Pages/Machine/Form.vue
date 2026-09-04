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
    machine: {
        type: Object,
        default: null,
    },
    areas: {
        type: Array,
        required: true,
    },
});

const isEditing = computed(() => !!props.machine);
const title = computed(() => (isEditing.value ? 'Edit Machine' : 'Tambah Machine Baru'));

const form = useForm({
    area_id: props.machine?.area_id || '',
    code: props.machine?.code || '',
    name: props.machine?.name || '',
    description: props.machine?.description || '',
});

const submit = () => {
    if (isEditing.value) {
        form.put(route('machines.update', props.machine.id));
    } else {
        form.post(route('machines.store'));
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="title" />

        <template #header>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('machines.index')"
                    class="text-gray-500 hover:text-gray-700 text-sm"
                >
                    Master Data Machine
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
                        <!-- Area Dropdown -->
                        <div>
                            <InputLabel for="area_id" value="Pilih Area *" />
                            <select
                                id="area_id"
                                v-model="form.area_id"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                required
                            >
                                <option value="" disabled>-- Pilih Area Induk --</option>
                                <option
                                    v-for="area in areas"
                                    :key="area.id"
                                    :value="area.id"
                                >
                                    {{ area.name }} ({{ area.code }})
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                Setiap mesin wajib terikat pada satu area produksi.
                            </p>
                            <InputError class="mt-1" :message="form.errors.area_id" />
                        </div>

                        <!-- Code -->
                        <div>
                            <InputLabel for="code" value="Kode Mesin / Stasiun *" />
                            <TextInput
                                id="code"
                                v-model="form.code"
                                type="text"
                                class="mt-1 block w-full uppercase"
                                placeholder="Contoh: AUTOSCREW, AOI_3D, STENCIL"
                                required
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                Kode unik pengenal mesin (maksimal 50 karakter).
                            </p>
                            <InputError class="mt-1" :message="form.errors.code" />
                        </div>

                        <!-- Name -->
                        <div>
                            <InputLabel for="name" value="Nama Mesin / Stasiun *" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Contoh: Auto Screw Robot Station"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>

                        <!-- Description -->
                        <div>
                            <InputLabel for="description" value="Deskripsi" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                                placeholder="Keterangan opsional mengenai mesin/stasiun ini..."
                            ></textarea>
                            <InputError class="mt-1" :message="form.errors.description" />
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                            <Link :href="route('machines.index')">
                                <SecondaryButton type="button">
                                    Batal
                                </SecondaryButton>
                            </Link>

                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                {{ isEditing ? 'Simpan Perubahan' : 'Tambah Machine' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


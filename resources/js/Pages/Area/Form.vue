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
    area: {
        type: Object,
        default: null,
    },
});

const isEditing = computed(() => !!props.area);
const title = computed(() => (isEditing.value ? 'Edit Area' : 'Tambah Area Baru'));

const form = useForm({
    code: props.area?.code || '',
    name: props.area?.name || '',
    description: props.area?.description || '',
});

const submit = () => {
    if (isEditing.value) {
        form.put(route('areas.update', props.area.id));
    } else {
        form.post(route('areas.store'));
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="title" />

        <template #header>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('areas.index')"
                    class="text-gray-500 hover:text-gray-700 text-sm"
                >
                    Master Data Area
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
                        <!-- Code -->
                        <div>
                            <InputLabel for="code" value="Kode Area *" />
                            <TextInput
                                id="code"
                                v-model="form.code"
                                type="text"
                                class="mt-1 block w-full uppercase"
                                placeholder="Contoh: FA, SMT, ENG"
                                required
                                autofocus
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                Kode unik pengenal area (maksimal 50 karakter).
                            </p>
                            <InputError class="mt-1" :message="form.errors.code" />
                        </div>

                        <!-- Name -->
                        <div>
                            <InputLabel for="name" value="Nama Area *" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Contoh: Final Assembly"
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
                                placeholder="Keterangan opsional mengenai area ini..."
                            ></textarea>
                            <InputError class="mt-1" :message="form.errors.description" />
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                            <Link :href="route('areas.index')">
                                <SecondaryButton type="button">
                                    Batal
                                </SecondaryButton>
                            </Link>

                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                {{ isEditing ? 'Simpan Perubahan' : 'Tambah Area' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


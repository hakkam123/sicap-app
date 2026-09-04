<script setup>
import { computed, watch, onMounted, onUnmounted } from 'vue';
import AppButton from '@/Components/UI/AppButton.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Konfirmasi',
    },
    message: {
        type: String,
        default: '',
    },
    confirmLabel: {
        type: String,
        default: 'Ya, Lanjutkan',
    },
    cancelLabel: {
        type: String,
        default: 'Batal',
    },
    variant: {
        type: String,
        default: 'danger',
        validator: (v) => ['danger', 'warning'].includes(v),
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['confirm', 'cancel']);

const closeOnEscape = (e) => {
    if (e.key === 'Escape' && props.show && !props.loading) {
        emit('cancel');
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

watch(
    () => props.show,
    (show) => {
        if (show) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
);

const iconClasses = computed(() => {
    return props.variant === 'danger'
        ? 'bg-red-100 text-red-600'
        : 'bg-amber-100 text-amber-600';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
            >
                <!-- Backdrop -->
                <div
                    class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
                    @click="!loading && $emit('cancel')"
                ></div>

                <!-- Modal Dialog -->
                <div
                    class="relative bg-white rounded-2xl overflow-hidden shadow-2xl transform transition-all sm:w-full sm:max-w-lg p-6 space-y-4 z-10"
                >
                    <div class="sm:flex sm:items-start gap-4">
                        <!-- Icon Alert -->
                        <div
                            :class="[
                                'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10',
                                iconClasses,
                            ]"
                        >
                            <svg
                                v-if="variant === 'danger'"
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <svg
                                v-else
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>

                        <!-- Content -->
                        <div class="mt-3 text-center sm:mt-0 sm:text-left flex-1">
                            <h3 class="text-base font-bold leading-6 text-slate-900">
                                {{ title }}
                            </h3>
                            <div class="mt-2">
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    {{ message }}
                                </p>
                                <slot />
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <AppButton
                            variant="outline"
                            size="md"
                            :disabled="loading"
                            @click="$emit('cancel')"
                        >
                            {{ cancelLabel }}
                        </AppButton>
                        <AppButton
                            :variant="variant === 'danger' ? 'danger' : 'primary'"
                            size="md"
                            :loading="loading"
                            @click="$emit('confirm')"
                        >
                            {{ confirmLabel }}
                        </AppButton>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>


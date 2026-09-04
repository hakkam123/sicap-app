<script setup>
import { computed } from 'vue';
import * as LucideIcons from 'lucide-vue-next';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary', 'outline', 'danger', 'ghost'].includes(value),
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
    icon: {
        type: String,
        default: null,
    },
    iconPosition: {
        type: String,
        default: 'left',
        validator: (value) => ['left', 'right'].includes(value),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: 'button',
    },
});

defineEmits(['click']);

const IconComponent = computed(() => {
    if (!props.icon) return null;
    return LucideIcons[props.icon] || null;
});

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'secondary':
            return 'bg-green-600 text-white hover:bg-green-700 shadow-sm border border-transparent focus:ring-green-500';
        case 'outline':
            return 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 shadow-sm focus:ring-blue-500';
        case 'danger':
            return 'bg-red-600 text-white hover:bg-red-700 shadow-sm border border-transparent focus:ring-red-500';
        case 'ghost':
            return 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 border border-transparent focus:ring-gray-400';
        case 'primary':
        default:
            return 'bg-gray-900 text-white hover:bg-gray-700 shadow-sm border border-transparent focus:ring-gray-900';
    }
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'px-2.5 py-1.5 text-xs rounded-md gap-1.5';
        case 'lg':
            return 'px-4 py-2.5 text-sm rounded-lg gap-2.5';
        case 'md':
        default:
            return 'px-3.5 py-2 text-xs rounded-lg gap-2';
    }
});

const iconSizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-3.5 w-3.5';
        case 'lg':
            return 'h-4.5 w-4.5';
        case 'md':
        default:
            return 'h-4 w-4';
    }
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        @click="$emit('click', $event)"
        :class="[
            'inline-flex items-center justify-center font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed select-none',
            variantClasses,
            sizeClasses,
        ]"
    >
        <!-- Loading Spinner -->
        <svg
            v-if="loading"
            class="animate-spin"
            :class="iconSizeClasses"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>

        <!-- Left Icon -->
        <component
            v-else-if="IconComponent && iconPosition === 'left'"
            :is="IconComponent"
            :class="iconSizeClasses"
        />

        <!-- Button Content -->
        <slot />

        <!-- Right Icon -->
        <component
            v-if="!loading && IconComponent && iconPosition === 'right'"
            :is="IconComponent"
            :class="iconSizeClasses"
        />
    </button>
</template>


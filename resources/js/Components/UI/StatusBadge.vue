<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: {
        type: [String, Number],
        default: '',
    },
    type: {
        type: String,
        default: 'source',
        validator: (val) => ['source', 'status', 'role', 'custom'].includes(val),
    },
    color: {
        type: String,
        default: 'gray',
    },
});

const label = computed(() => {
    const val = String(props.value || '').toLowerCase();
    switch (val) {
        case 'import_excel':
            return 'Import Excel';
        case 'api':
            return 'API Sync';
        case 'manual':
            return 'Manual';
        case 'admin':
            return 'Administrator';
        case 'user':
            return 'User';
        case 'processing':
            return 'Processing';
        case 'success':
            return 'Success';
        case 'failed':
            return 'Failed';
        case 'pending':
            return 'Pending';
        default:
            return props.value || '-';
    }
});

const badgeClasses = computed(() => {
    const val = String(props.value || '').toLowerCase();

    if (props.type === 'custom') {
        switch (props.color) {
            case 'blue':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'green':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'red':
                return 'bg-red-100 text-red-800 border-red-200';
            case 'purple':
                return 'bg-purple-100 text-purple-800 border-purple-200';
            case 'indigo':
                return 'bg-indigo-100 text-indigo-800 border-indigo-200';
            case 'amber':
            case 'yellow':
                return 'bg-amber-100 text-amber-800 border-amber-200';
            case 'gray':
            default:
                return 'bg-gray-100 text-gray-700 border-gray-200';
        }
    }

    if (props.type === 'source') {
        switch (val) {
            case 'manual':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'import_excel':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'api':
                return 'bg-purple-100 text-purple-800 border-purple-200';
            default:
                return 'bg-gray-100 text-gray-700 border-gray-200';
        }
    }

    if (props.type === 'status') {
        switch (val) {
            case 'success':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'processing':
                return 'bg-blue-100 text-blue-800 border-blue-200 animate-pulse';
            case 'failed':
                return 'bg-red-100 text-red-800 border-red-200';
            case 'pending':
            default:
                return 'bg-gray-100 text-gray-600 border-gray-200';
        }
    }

    if (props.type === 'role') {
        switch (val) {
            case 'admin':
                return 'bg-indigo-100 text-indigo-800 border-indigo-200 font-bold';
            case 'user':
            default:
                return 'bg-gray-100 text-gray-600 border-gray-200';
        }
    }

    return 'bg-gray-100 text-gray-700 border-gray-200';
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border transition-colors',
            badgeClasses,
        ]"
    >
        {{ label }}
    </span>
</template>


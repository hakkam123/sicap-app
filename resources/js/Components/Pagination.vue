<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <div v-if="links.length > 3" class="flex flex-wrap items-center justify-center gap-1 mt-4">
        <template v-for="(link, key) in links" :key="key">
            <div
                v-if="link.url === null"
                class="px-3 py-1.5 text-sm text-gray-400 border border-gray-200 rounded-md bg-gray-50"
                v-html="link.label"
            />
            <Link
                v-else
                :href="link.url"
                class="px-3 py-1.5 text-sm rounded-md border transition-colors duration-150"
                :class="[
                    link.active
                        ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                        : 'bg-white text-gray-750 border-gray-300 hover:bg-gray-100 hover:text-gray-900 text-gray-700'
                ]"
                v-html="link.label"
            />
        </template>
    </div>
</template>


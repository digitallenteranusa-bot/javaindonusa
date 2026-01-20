<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    links: Array,
    from: Number,
    to: Number,
    total: Number,
    showInfo: {
        type: Boolean,
        default: true,
    },
})

// Safely decode HTML entities for pagination labels
const decodeLabel = (label) => {
    if (!label) return ''
    // Only decode known safe HTML entities from Laravel pagination
    return label
        .replace(/&laquo;/g, '\u00AB')  // «
        .replace(/&raquo;/g, '\u00BB')  // »
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
}
</script>

<template>
    <div class="flex items-center justify-between px-4 py-3 border-t bg-white">
        <p v-if="showInfo" class="text-sm text-gray-500">
            Menampilkan {{ from }} - {{ to }} dari {{ total }}
        </p>
        <div v-else></div>

        <div class="flex gap-1">
            <template v-for="(link, index) in links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="px-3 py-1 text-sm rounded transition-colors"
                    :class="link.active
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    "
                    preserve-scroll
                >
                    {{ decodeLabel(link.label) }}
                </Link>
                <span
                    v-else
                    class="px-3 py-1 text-sm rounded bg-gray-100 text-gray-400 cursor-not-allowed"
                >
                    {{ decodeLabel(link.label) }}
                </span>
            </template>
        </div>
    </div>
</template>

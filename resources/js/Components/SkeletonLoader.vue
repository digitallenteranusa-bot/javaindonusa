<template>
    <div class="animate-pulse">
        <!-- Text skeleton -->
        <template v-if="type === 'text'">
            <div v-for="i in rows" :key="i" class="space-y-2 mb-3">
                <div class="h-4 bg-gray-200 rounded" :class="i === rows ? 'w-3/4' : 'w-full'"></div>
            </div>
        </template>

        <!-- Card skeleton -->
        <template v-else-if="type === 'card'">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="h-3 bg-gray-200 rounded w-24 mb-3"></div>
                        <div class="h-7 bg-gray-200 rounded w-32 mb-2"></div>
                        <div class="h-3 bg-gray-200 rounded w-20"></div>
                    </div>
                    <div class="w-12 h-12 bg-gray-200 rounded-lg"></div>
                </div>
            </div>
        </template>

        <!-- Table skeleton -->
        <template v-else-if="type === 'table'">
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <!-- Header -->
                <div class="bg-gray-50 px-4 py-3 flex gap-4">
                    <div v-for="c in columns" :key="'h-' + c" class="h-3 bg-gray-200 rounded flex-1"></div>
                </div>
                <!-- Rows -->
                <div v-for="r in rows" :key="'r-' + r" class="px-4 py-4 border-t border-gray-100 flex gap-4">
                    <div v-for="c in columns" :key="'c-' + r + '-' + c" class="h-4 bg-gray-200 rounded flex-1"></div>
                </div>
            </div>
        </template>

        <!-- Circle skeleton -->
        <template v-else-if="type === 'circle'">
            <div class="w-12 h-12 bg-gray-200 rounded-full"></div>
        </template>
    </div>
</template>

<script setup>
defineProps({
    type: {
        type: String,
        default: 'text',
        validator: (v) => ['text', 'card', 'table', 'circle'].includes(v),
    },
    rows: {
        type: Number,
        default: 3,
    },
    columns: {
        type: Number,
        default: 5,
    },
})
</script>

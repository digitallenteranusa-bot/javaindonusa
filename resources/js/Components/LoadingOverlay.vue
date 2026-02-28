<template>
    <div v-if="show" class="relative">
        <div
            class="absolute inset-0 bg-white/70 flex items-center justify-center z-10 rounded-xl"
            :class="{ 'backdrop-blur-sm': blur }"
        >
            <div class="flex flex-col items-center gap-2">
                <svg class="animate-spin w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                <span v-if="label" class="text-sm text-gray-600 font-medium">{{ label }}</span>
            </div>
        </div>
        <slot />
    </div>
    <slot v-else />
</template>

<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        default: '',
    },
    blur: {
        type: Boolean,
        default: false,
    },
})
</script>

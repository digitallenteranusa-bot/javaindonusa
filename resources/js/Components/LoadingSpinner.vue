<template>
    <div class="flex items-center justify-center gap-2" :class="containerClass">
        <svg
            class="animate-spin text-blue-600"
            :class="sizeClass"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <span v-if="label" class="text-gray-600" :class="labelClass">{{ label }}</span>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    label: {
        type: String,
        default: '',
    },
})

const sizeClass = computed(() => ({
    sm: 'w-4 h-4',
    md: 'w-6 h-6',
    lg: 'w-8 h-8',
}[props.size]))

const labelClass = computed(() => ({
    sm: 'text-xs',
    md: 'text-sm',
    lg: 'text-base',
}[props.size]))

const containerClass = computed(() => ({
    sm: 'py-2',
    md: 'py-4',
    lg: 'py-8',
}[props.size]))
</script>

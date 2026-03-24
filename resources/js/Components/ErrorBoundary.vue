<script setup>
import { ref, onErrorCaptured } from 'vue'

const hasError = ref(false)
const errorMessage = ref('')

onErrorCaptured((err) => {
    hasError.value = true
    errorMessage.value = err?.message || 'Terjadi kesalahan'
    console.error('[ErrorBoundary]', err)
    // Don't propagate — Sentry captures via global handler
    return false
})

const retry = () => {
    hasError.value = false
    errorMessage.value = ''
}
</script>

<template>
    <div v-if="hasError" class="flex flex-col items-center justify-center py-12 px-4">
        <svg class="w-16 h-16 text-red-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
        </svg>
        <h3 class="text-lg font-semibold text-gray-800 mb-1">Terjadi Kesalahan</h3>
        <p class="text-sm text-gray-500 mb-4 text-center max-w-md">{{ errorMessage }}</p>
        <button
            @click="retry"
            class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
        >
            Coba Lagi
        </button>
    </div>
    <slot v-else />
</template>

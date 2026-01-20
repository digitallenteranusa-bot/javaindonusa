<script setup>
import Modal from './Modal.vue'

const props = defineProps({
    show: Boolean,
    title: {
        type: String,
        default: 'Konfirmasi',
    },
    message: {
        type: String,
        default: 'Apakah Anda yakin?',
    },
    confirmText: {
        type: String,
        default: 'Ya, Lanjutkan',
    },
    cancelText: {
        type: String,
        default: 'Batal',
    },
    type: {
        type: String,
        default: 'warning', // warning, danger, info
    },
    loading: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['confirm', 'cancel'])

const typeStyles = {
    warning: {
        icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        iconBg: 'bg-yellow-100',
        iconColor: 'text-yellow-600',
        buttonBg: 'bg-yellow-600 hover:bg-yellow-700',
    },
    danger: {
        icon: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
        iconBg: 'bg-red-100',
        iconColor: 'text-red-600',
        buttonBg: 'bg-red-600 hover:bg-red-700',
    },
    info: {
        icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        iconBg: 'bg-blue-100',
        iconColor: 'text-blue-600',
        buttonBg: 'bg-blue-600 hover:bg-blue-700',
    },
}
</script>

<template>
    <Modal :show="show" max-width="sm" @close="emit('cancel')">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div
                    class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                    :class="typeStyles[type].iconBg"
                >
                    <svg
                        class="w-6 h-6"
                        :class="typeStyles[type].iconColor"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            :d="typeStyles[type].icon"
                        />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ message }}</p>
                </div>
            </div>

            <div class="mt-6 flex gap-3 justify-end">
                <button
                    @click="emit('cancel')"
                    :disabled="loading"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50"
                >
                    {{ cancelText }}
                </button>
                <button
                    @click="emit('confirm')"
                    :disabled="loading"
                    class="px-4 py-2 text-white rounded-lg disabled:opacity-50"
                    :class="typeStyles[type].buttonBg"
                >
                    {{ loading ? 'Memproses...' : confirmText }}
                </button>
            </div>
        </div>
    </Modal>
</template>

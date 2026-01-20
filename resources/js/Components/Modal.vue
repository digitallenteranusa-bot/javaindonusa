<script setup>
import { watch } from 'vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: 'md',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
    position: {
        type: String,
        default: 'center', // center, bottom
    },
})

const emit = defineEmits(['close'])

const close = () => {
    if (props.closeable) {
        emit('close')
    }
}

const maxWidthClass = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
}

// Prevent body scroll when modal is open
watch(() => props.show, (value) => {
    if (value) {
        document.body.style.overflow = 'hidden'
    } else {
        document.body.style.overflow = ''
    }
})
</script>

<template>
    <Teleport to="body">
        <Transition leave-active-class="duration-200">
            <div
                v-show="show"
                class="fixed inset-0 z-50 overflow-y-auto"
                scroll-region
            >
                <div
                    class="min-h-screen px-4 text-center"
                    :class="position === 'bottom' ? 'flex items-end' : 'flex items-center justify-center'"
                >
                    <Transition
                        enter-active-class="ease-out duration-300"
                        enter-from-class="opacity-0"
                        enter-to-class="opacity-100"
                        leave-active-class="ease-in duration-200"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0"
                    >
                        <div
                            v-show="show"
                            class="fixed inset-0 bg-black/50 transition-opacity"
                            @click="close"
                        />
                    </Transition>

                    <Transition
                        enter-active-class="ease-out duration-300"
                        :enter-from-class="position === 'bottom' ? 'opacity-0 translate-y-full' : 'opacity-0 translate-y-4 scale-95'"
                        :enter-to-class="position === 'bottom' ? 'opacity-100 translate-y-0' : 'opacity-100 translate-y-0 scale-100'"
                        leave-active-class="ease-in duration-200"
                        :leave-from-class="position === 'bottom' ? 'opacity-100 translate-y-0' : 'opacity-100 translate-y-0 scale-100'"
                        :leave-to-class="position === 'bottom' ? 'opacity-0 translate-y-full' : 'opacity-0 translate-y-4 scale-95'"
                    >
                        <div
                            v-show="show"
                            class="relative bg-white overflow-hidden shadow-xl transform transition-all w-full"
                            :class="[
                                maxWidthClass[maxWidth],
                                position === 'bottom' ? 'rounded-t-2xl' : 'rounded-xl my-8'
                            ]"
                        >
                            <slot v-if="show" />
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

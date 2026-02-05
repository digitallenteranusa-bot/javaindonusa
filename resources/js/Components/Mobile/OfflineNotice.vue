<script setup>
import { useNative } from '@/Composables/useNative';

const { isOnline } = useNative();
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="-translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="-translate-y-full opacity-0"
    >
        <div
            v-if="!isOnline"
            class="fixed top-0 left-0 right-0 z-50 bg-red-600 text-white px-4 py-3 text-center text-sm font-medium shadow-lg"
        >
            <div class="flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824 2.167a1 1 0 111.414 1.414m-1.414-1.414L3 3"></path>
                </svg>
                <span>Tidak ada koneksi internet</span>
            </div>
        </div>
    </Transition>
</template>

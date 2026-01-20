<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const user = computed(() => page.props.auth?.user)
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Main Content -->
        <slot />

        <!-- Flash Messages -->
        <div
            v-if="$page.props.flash?.success"
            class="fixed top-4 left-4 right-4 z-50 bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg text-sm"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="fixed top-4 left-4 right-4 z-50 bg-red-500 text-white px-4 py-3 rounded-lg shadow-lg text-sm"
        >
            {{ $page.props.flash.error }}
        </div>
    </div>
</template>

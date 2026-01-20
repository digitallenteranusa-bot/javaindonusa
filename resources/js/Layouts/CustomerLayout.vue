<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    customer: Object,
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <slot />
    </div>
</template>

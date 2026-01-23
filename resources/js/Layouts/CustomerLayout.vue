<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

defineProps({
    customer: Object,
})

// Check active route
const isActive = (routeName) => {
    const currentUrl = window.location.pathname
    const routes = {
        'dashboard': '/portal',
        'invoices': '/portal/invoices',
        'payments': '/portal/payments',
    }

    if (routeName === 'dashboard') {
        return currentUrl === '/portal' || currentUrl === '/portal/' || currentUrl === '/portal/dashboard'
    }
    return currentUrl.startsWith(routes[routeName])
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 pb-16">
        <!-- Main Content -->
        <slot />

        <!-- Bottom Navigation -->
        <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2 z-40">
            <div class="flex justify-around max-w-lg mx-auto">
                <Link
                    href="/portal"
                    class="flex flex-col items-center py-1 px-3"
                    :class="isActive('dashboard') ? 'text-blue-600' : 'text-gray-500'"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="text-xs mt-1">Beranda</span>
                </Link>
                <Link
                    href="/portal/invoices"
                    class="flex flex-col items-center py-1 px-3"
                    :class="isActive('invoices') ? 'text-blue-600' : 'text-gray-500'"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-xs mt-1">Tagihan</span>
                </Link>
                <Link
                    href="/portal/payments"
                    class="flex flex-col items-center py-1 px-3"
                    :class="isActive('payments') ? 'text-blue-600' : 'text-gray-500'"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <span class="text-xs mt-1">Pembayaran</span>
                </Link>
                <Link
                    href="/portal/logout"
                    method="post"
                    as="button"
                    class="flex flex-col items-center py-1 px-3 text-gray-500"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="text-xs mt-1">Keluar</span>
                </Link>
            </div>
        </nav>

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

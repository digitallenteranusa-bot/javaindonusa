<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const showProfileMenu = ref(false)

// Check active route
const isActive = (routeName) => {
    const currentUrl = window.location.pathname
    const routes = {
        'dashboard': '/collector',
        'customers': '/collector/customers',
        'expenses': '/collector/expenses',
        'settlement': '/collector/settlement',
    }

    if (routeName === 'dashboard') {
        return currentUrl === '/collector' || currentUrl === '/collector/'
    }
    return currentUrl.startsWith(routes[routeName])
}

// Logout
const logout = () => {
    router.post('/logout')
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Main Content -->
        <slot />

        <!-- Bottom Navigation -->
        <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2 z-40">
            <div class="flex justify-around max-w-lg mx-auto">
                <Link
                    href="/collector"
                    class="flex flex-col items-center py-1 px-3"
                    :class="isActive('dashboard') ? 'text-blue-600' : 'text-gray-500'"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="text-xs mt-1">Home</span>
                </Link>
                <Link
                    href="/collector/customers"
                    class="flex flex-col items-center py-1 px-3"
                    :class="isActive('customers') ? 'text-blue-600' : 'text-gray-500'"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-xs mt-1">Pelanggan</span>
                </Link>
                <Link
                    href="/collector/expenses"
                    class="flex flex-col items-center py-1 px-3"
                    :class="isActive('expenses') ? 'text-blue-600' : 'text-gray-500'"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                    </svg>
                    <span class="text-xs mt-1">Belanja</span>
                </Link>
                <Link
                    href="/collector/settlement"
                    class="flex flex-col items-center py-1 px-3"
                    :class="isActive('settlement') ? 'text-blue-600' : 'text-gray-500'"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="text-xs mt-1">Setoran</span>
                </Link>
                <!-- Profile/Account -->
                <button
                    @click="showProfileMenu = true"
                    class="flex flex-col items-center py-1 px-3 text-gray-500"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span class="text-xs mt-1">Akun</span>
                </button>
            </div>
        </nav>

        <!-- Profile Menu Modal -->
        <div
            v-if="showProfileMenu"
            class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-end justify-center"
            @click.self="showProfileMenu = false"
        >
            <div class="bg-white rounded-t-2xl w-full max-w-lg p-6 animate-slide-up">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Akun Saya</h3>
                    <button @click="showProfileMenu = false" class="text-gray-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- User Info -->
                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl mb-4">
                    <div class="w-14 h-14 bg-blue-500 rounded-full flex items-center justify-center text-white text-xl font-bold">
                        {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ user?.name || 'User' }}</p>
                        <p class="text-sm text-gray-500">{{ user?.email || user?.phone || '-' }}</p>
                        <p class="text-xs text-blue-600">Penagih</p>
                    </div>
                </div>

                <!-- Logout Button -->
                <button
                    @click="logout"
                    class="w-full mt-4 py-3 bg-red-500 text-white rounded-lg font-semibold flex items-center justify-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Keluar
                </button>
            </div>
        </div>

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

<style scoped>
.animate-slide-up {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
</style>

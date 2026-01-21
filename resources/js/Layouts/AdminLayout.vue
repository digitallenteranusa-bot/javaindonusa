<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const user = computed(() => page.props.auth?.user)

const sidebarOpen = ref(true)
const mobileMenuOpen = ref(false)

// Menu Groups
const mainNavigation = [
    { name: 'Dashboard', href: '/admin', icon: 'home' },
    { name: 'Pelanggan', href: '/admin/customers', icon: 'users' },
    { name: 'Perangkat', href: '/admin/devices', icon: 'device' },
]

const billingNavigation = [
    { name: 'Invoice', href: '/admin/invoices', icon: 'document' },
    { name: 'Pembayaran', href: '/admin/payments', icon: 'credit-card' },
    { name: 'Pengeluaran', href: '/admin/expenses', icon: 'receipt' },
    { name: 'Setoran', href: '/admin/settlements', icon: 'cash' },
]

const masterNavigation = [
    { name: 'Paket', href: '/admin/packages', icon: 'cube' },
    { name: 'Area', href: '/admin/areas', icon: 'map' },
    { name: 'Router', href: '/admin/routers', icon: 'server' },
    { name: 'Users', href: '/admin/users', icon: 'user-group' },
]

const systemNavigation = [
    { name: 'Laporan', href: '/admin/reports', icon: 'chart' },
    { name: 'Pengaturan', href: '/admin/settings', icon: 'cog' },
    { name: 'Sistem', href: '/admin/system', icon: 'info' },
]

const isActive = (href) => {
    const currentPath = window.location.pathname
    if (href === '/admin') {
        return currentPath === '/admin'
    }
    return currentPath.startsWith(href)
}

const navLinkClass = (href) => [
    'flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors',
    isActive(href)
        ? 'bg-blue-600 text-white'
        : 'text-gray-300 hover:bg-gray-800 hover:text-white'
]

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value
}

// Icon components
const icons = {
    home: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>`
    },
    users: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>`
    },
    device: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>`
    },
    document: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>`
    },
    'credit-card': {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>`
    },
    receipt: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" /></svg>`
    },
    cash: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>`
    },
    cube: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>`
    },
    map: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" /></svg>`
    },
    server: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" /></svg>`
    },
    'user-group': {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>`
    },
    chart: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>`
    },
    cog: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>`
    },
    info: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
    }
}

const getIcon = (name) => ({
    template: icons[name]?.template || icons.home.template
})
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex flex-col bg-gray-900 transition-all duration-300',
                sidebarOpen ? 'w-64' : 'w-20'
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center justify-between px-4 bg-gray-800">
                <Link href="/admin" class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center overflow-hidden">
                        <img
                            v-if="page.props.isp?.logo"
                            :src="page.props.isp.logo"
                            :alt="page.props.isp?.name || 'Logo'"
                            class="w-full h-full object-contain p-1"
                        >
                        <span v-else class="text-white font-bold text-lg">
                            {{ page.props.isp?.initials || 'ISP' }}
                        </span>
                    </div>
                    <span v-if="sidebarOpen" class="text-white font-semibold text-lg truncate max-w-[160px]">
                        {{ page.props.isp?.name || 'ISP Billing' }}
                    </span>
                </Link>
                <button
                    @click="toggleSidebar"
                    class="text-gray-400 hover:text-white lg:block hidden"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            :d="sidebarOpen ? 'M11 19l-7-7 7-7m8 14l-7-7 7-7' : 'M13 5l7 7-7 7M5 5l7 7-7 7'" />
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-4 px-3">
                <!-- Main -->
                <div class="mb-4">
                    <p v-if="sidebarOpen" class="px-3 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Menu Utama</p>
                    <ul class="space-y-1">
                        <li v-for="item in mainNavigation" :key="item.name">
                            <Link :href="item.href" :class="navLinkClass(item.href)">
                                <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- Billing -->
                <div class="mb-4">
                    <p v-if="sidebarOpen" class="px-3 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Billing</p>
                    <ul class="space-y-1">
                        <li v-for="item in billingNavigation" :key="item.name">
                            <Link :href="item.href" :class="navLinkClass(item.href)">
                                <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- Master Data -->
                <div class="mb-4">
                    <p v-if="sidebarOpen" class="px-3 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Master Data</p>
                    <ul class="space-y-1">
                        <li v-for="item in masterNavigation" :key="item.name">
                            <Link :href="item.href" :class="navLinkClass(item.href)">
                                <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- System -->
                <div class="mb-4">
                    <p v-if="sidebarOpen" class="px-3 mb-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sistem</p>
                    <ul class="space-y-1">
                        <li v-for="item in systemNavigation" :key="item.name">
                            <Link :href="item.href" :class="navLinkClass(item.href)">
                                <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- User Menu -->
            <div class="border-t border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gray-600 rounded-full flex items-center justify-center">
                        <span class="text-white font-medium">{{ user?.name?.charAt(0) || 'A' }}</span>
                    </div>
                    <div v-if="sidebarOpen" class="flex-1 min-w-0">
                        <p class="text-white text-sm font-medium truncate">{{ user?.name || 'Admin' }}</p>
                        <p class="text-gray-400 text-xs truncate">{{ user?.email }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div :class="['transition-all duration-300', sidebarOpen ? 'lg:ml-64' : 'lg:ml-20']">
            <!-- Top Header -->
            <header class="sticky top-0 z-40 bg-white border-b border-gray-200">
                <div class="flex items-center justify-between h-16 px-4 lg:px-8">
                    <!-- Mobile Menu Button -->
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="lg:hidden p-2 rounded-md text-gray-500 hover:text-gray-900"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Page Title (slot for child pages) -->
                    <div class="flex-1">
                        <slot name="header" />
                    </div>

                    <!-- Right Actions -->
                    <div class="flex items-center gap-4">
                        <!-- Notifications -->
                        <button class="p-2 text-gray-500 hover:text-gray-900 relative">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                        </button>

                        <!-- Logout -->
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="p-2 text-gray-500 hover:text-gray-900"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-4 lg:p-8">
                <slot />
            </main>
        </div>

        <!-- Mobile Sidebar Overlay -->
        <div
            v-if="mobileMenuOpen"
            class="fixed inset-0 z-40 lg:hidden"
            @click="mobileMenuOpen = false"
        >
            <div class="fixed inset-0 bg-gray-600 bg-opacity-75"></div>
        </div>

        <!-- Flash Messages -->
        <div
            v-if="$page.props.flash?.success"
            class="fixed bottom-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="fixed bottom-4 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg"
        >
            {{ $page.props.flash.error }}
        </div>
    </div>
</template>

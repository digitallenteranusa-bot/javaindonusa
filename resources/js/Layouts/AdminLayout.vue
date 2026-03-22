<script setup>
import { ref, computed, onMounted, onUnmounted, watch, h } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useTheme } from '@/Composables/useTheme'

const { isDark, toggleTheme } = useTheme()

const page = usePage()
const user = computed(() => page.props.auth?.user)
const permissions = computed(() => page.props.auth?.user?.permissions || [])
const isAdmin = computed(() => user.value?.role === 'admin')

const hasPermission = (permission) => {
    if (isAdmin.value) return true
    return permissions.value.includes(permission)
}

const filterByPermission = (items) => {
    return items.filter(item => !item.permission || hasPermission(item.permission))
}

// Flash message handling with auto-dismiss
const showSuccess = ref(false)
const showError = ref(false)
const showWarning = ref(false)
const showInfo = ref(false)

const successMessage = computed(() => page.props.flash?.success)
const errorMessage = computed(() => page.props.flash?.error)
const warningMessage = computed(() => page.props.flash?.warning)
const infoMessage = computed(() => page.props.flash?.info)

// Watch for flash messages and auto-dismiss
watch(successMessage, (val) => {
    if (val) {
        showSuccess.value = true
        setTimeout(() => { showSuccess.value = false }, 5000)
    }
}, { immediate: true })

watch(errorMessage, (val) => {
    if (val) {
        showError.value = true
        setTimeout(() => { showError.value = false }, 8000)
    }
}, { immediate: true })

watch(warningMessage, (val) => {
    if (val) {
        showWarning.value = true
        setTimeout(() => { showWarning.value = false }, 8000)
    }
}, { immediate: true })

watch(infoMessage, (val) => {
    if (val) {
        showInfo.value = true
        setTimeout(() => { showInfo.value = false }, 5000)
    }
}, { immediate: true })

const sidebarOpen = ref(true)
const mobileMenuOpen = ref(false)

// Collapsible menu sections - load from localStorage
const expandedSections = ref({
    billing: localStorage.getItem('sidebar_billing') !== 'false',
    collector: localStorage.getItem('sidebar_collector') !== 'false',
    finance: localStorage.getItem('sidebar_finance') !== 'false',
    network: localStorage.getItem('sidebar_network') !== 'false',
    master: localStorage.getItem('sidebar_master') !== 'false',
    system: localStorage.getItem('sidebar_system') !== 'false',
})

const toggleSection = (section) => {
    expandedSections.value[section] = !expandedSections.value[section]
    localStorage.setItem(`sidebar_${section}`, expandedSections.value[section])
}

// Close mobile menu on route change
const closeMobileMenu = () => {
    mobileMenuOpen.value = false
}

// Menu Groups - each item has optional 'permission' for visibility control
const mainNavigation = [
    { name: 'Dashboard', href: '/admin', icon: 'home', permission: 'dashboard.view' },
    { name: 'Pelanggan', href: '/admin/customers', icon: 'users', permission: 'customers.view' },
    { name: 'Mapping', href: '/admin/mapping', icon: 'globe', permission: 'mapping.view' },
    { name: 'Broadcast', href: '/admin/broadcasts/create', icon: 'megaphone', permission: 'customers.view' },
]

const billingNavigation = [
    { name: 'Invoice', href: '/admin/invoices', icon: 'document', permission: 'invoices.view' },
    { name: 'Belum Bayar', href: '/admin/billing/unpaid', icon: 'clipboard', permission: 'invoices.view' },
    { name: 'Isolir', href: '/admin/isolation', icon: 'shield', permission: 'invoices.view' },
    { name: 'Pembayaran', href: '/admin/payments', icon: 'credit-card', permission: 'payments.view' },
]

const collectorNavigation = [
    { name: 'Pengeluaran Penagih', href: '/admin/expenses', icon: 'receipt', permission: 'expenses.view' },
    { name: 'Setoran', href: '/admin/settlements', icon: 'cash', permission: 'settlements.view' },
    { name: 'Performa Penagih', href: '/admin/finance/collector-performance', icon: 'user-group', permission: 'collectors.view' },
]

const financeNavigation = [
    { name: 'Dashboard', href: '/admin/finance', icon: 'banknotes', permission: 'finance.view' },
    { name: 'Pengeluaran Operasional', href: '/admin/finance/expenses', icon: 'receipt', permission: 'finance.manage' },
    { name: 'Analisa Pendapatan', href: '/admin/analytics/revenue', icon: 'chart', permission: 'reports.view' },
    { name: 'Laporan', href: '/admin/reports', icon: 'chart', permission: 'reports.view' },
]

const networkNavigation = [
    { name: 'Perangkat', href: '/admin/devices', icon: 'device', permission: 'devices.view' },
    { name: 'Kesehatan Jaringan', href: '/admin/analytics/network', icon: 'signal', permission: 'devices.view' },
    { name: 'Kesehatan VPS', href: '/admin/analytics/vps', icon: 'server', permission: 'system.view' },
    { name: 'VPN Server', href: '/admin/vpn-server', icon: 'vpn', permission: 'routers.view' },
]

const masterNavigation = [
    { name: 'Paket', href: '/admin/packages', icon: 'cube', permission: 'packages.view' },
    { name: 'Area', href: '/admin/areas', icon: 'map', permission: 'areas.view' },
    { name: 'Router', href: '/admin/routers', icon: 'server', permission: 'routers.view' },
    { name: 'Merk Router', href: '/admin/router-brands', icon: 'wifi', permission: 'routers.view' },
    { name: 'Radius Server', href: '/admin/radius-servers', icon: 'shield', permission: 'radius.view' },
    { name: 'ODP', href: '/admin/odps', icon: 'odp', permission: 'odps.view' },
    { name: 'OLT', href: '/admin/olts', icon: 'olt', permission: 'olts.view' },
]

const systemNavigation = [
    { name: 'Users', href: '/admin/users', icon: 'user-group', permission: 'users.view' },
    { name: 'Audit Log', href: '/admin/audit-logs', icon: 'clipboard', permission: 'audit.view' },
    { name: 'Roles', href: '/admin/roles', icon: 'shield', permission: 'roles.view' },
    { name: 'Pengaturan', href: '/admin/settings', icon: 'cog', permission: 'settings.view' },
    { name: 'Sistem', href: '/admin/system', icon: 'info', permission: 'system.view' },
]

const isActive = (href) => {
    const currentPath = window.location.pathname
    if (href === '/admin' || href === '/admin/finance') {
        return currentPath === href
    }
    return currentPath.startsWith(href)
}

const navLinkClass = (href) => [
    'flex items-center gap-3 px-3 py-2 rounded-lg transition-colors text-sm',
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
    },
    globe: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
    },
    odp: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>`
    },
    olt: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>`
    },
    clipboard: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>`
    },
    shield: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>`
    },
    wifi: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" /></svg>`
    },
    megaphone: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>`
    },
    banknotes: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 7a2 2 0 012-2h16a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V7zm10 1a4 4 0 100 8 4 4 0 000-8zm-6 1a1 1 0 100 2 1 1 0 000-2zm12 4a1 1 0 100 2 1 1 0 000-2z" /></svg>`
    },
    close: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>`
    },
    signal: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.348 14.651a3.75 3.75 0 010-5.303m5.304 0a3.75 3.75 0 010 5.303m-7.425 2.122a6.75 6.75 0 010-9.546m9.546 0a6.75 6.75 0 010 9.546M5.106 18.894c-3.808-3.808-3.808-9.98 0-13.789m13.788 0c3.808 3.808 3.808 9.981 0 13.79M12 12h.008v.007H12V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>`
    },
    vpn: {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>`
    },
    'chevron-down': {
        template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>`
    }
}

const getIcon = (name) => ({
    inheritAttrs: true,
    setup(_, { attrs }) {
        const svg = icons[name]?.template || icons.home.template
        return () => h('svg', {
            ...attrs,
            innerHTML: svg.replace(/<svg[^>]*>/, '').replace(/<\/svg>/, ''),
            fill: 'none',
            stroke: 'currentColor',
            viewBox: '0 0 24 24',
        })
    }
})
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 dark:text-gray-200">
        <!-- Mobile Sidebar Overlay -->
        <Transition
            enter-active-class="transition-opacity ease-linear duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-linear duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mobileMenuOpen"
                class="fixed inset-0 z-50 lg:hidden"
            >
                <div class="fixed inset-0 bg-gray-900/80" @click="mobileMenuOpen = false"></div>
            </div>
        </Transition>

        <!-- Mobile Sidebar -->
        <Transition
            enter-active-class="transition ease-in-out duration-300 transform"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition ease-in-out duration-300 transform"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full"
        >
            <aside
                v-if="mobileMenuOpen"
                class="fixed inset-y-0 left-0 z-50 w-72 flex flex-col bg-gray-900 lg:hidden"
            >
                <!-- Logo with Close Button -->
                <div class="flex h-16 items-center justify-between px-4 bg-gray-800">
                    <Link href="/admin" class="flex items-center gap-3" @click="closeMobileMenu">
                        <img
                            src="/img/logo-dark.png"
                            alt="Logo"
                            class="h-10 w-auto object-contain"
                        >
                        <span class="text-white font-semibold text-lg truncate max-w-[160px]">
                            {{ page.props.isp?.name || 'ISP Billing' }}
                        </span>
                    </Link>
                    <button
                        @click="mobileMenuOpen = false"
                        class="p-2 text-gray-400 hover:text-white rounded-lg hover:bg-gray-700"
                    >
                        <component :is="getIcon('close')" class="w-6 h-6" />
                    </button>
                </div>

                <!-- Mobile Navigation -->
                <nav class="flex-1 overflow-y-auto py-4 px-3">
                    <!-- Main -->
                    <div class="mb-4">
                        <div class="px-3 mb-2 flex items-center gap-2 text-sm font-bold text-blue-400 uppercase tracking-wider">
                            <component :is="getIcon('home')" class="w-4 h-4" />
                            <span>Menu Utama</span>
                        </div>
                        <ul class="space-y-1">
                            <li v-for="item in filterByPermission(mainNavigation)" :key="item.name">
                                <Link :href="item.href" :class="navLinkClass(item.href)" @click="closeMobileMenu">
                                    <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                    <span class="truncate">{{ item.name }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Billing & Tagihan -->
                    <div v-if="filterByPermission(billingNavigation).length" class="mb-4">
                        <button
                            @click="toggleSection('billing')"
                            class="w-full flex items-center justify-between px-3 mb-2 text-sm font-bold text-blue-400 uppercase tracking-wider hover:text-blue-300 transition-colors"
                        >
                            <span class="flex items-center gap-2"><component :is="getIcon('document')" class="w-4 h-4 flex-shrink-0" />Billing & Tagihan</span>
                            <component
                                :is="getIcon('chevron-down')"
                                class="w-4 h-4 transition-transform duration-200"
                                :class="{ 'rotate-180': !expandedSections.billing }"
                            />
                        </button>
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-96"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="opacity-100 max-h-96"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <ul v-show="expandedSections.billing" class="space-y-1 overflow-hidden">
                                <li v-for="item in filterByPermission(billingNavigation)" :key="item.name">
                                    <Link :href="item.href" :class="navLinkClass(item.href)" @click="closeMobileMenu">
                                        <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                        <span class="truncate">{{ item.name }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </Transition>
                    </div>

                    <!-- Penagih -->
                    <div v-if="filterByPermission(collectorNavigation).length" class="mb-4">
                        <button
                            @click="toggleSection('collector')"
                            class="w-full flex items-center justify-between px-3 mb-2 text-sm font-bold text-orange-400 uppercase tracking-wider hover:text-orange-300 transition-colors"
                        >
                            <span class="flex items-center gap-2"><component :is="getIcon('user-group')" class="w-4 h-4 flex-shrink-0" />Penagih</span>
                            <component
                                :is="getIcon('chevron-down')"
                                class="w-4 h-4 transition-transform duration-200"
                                :class="{ 'rotate-180': !expandedSections.collector }"
                            />
                        </button>
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-96"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="opacity-100 max-h-96"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <ul v-show="expandedSections.collector" class="space-y-1 overflow-hidden">
                                <li v-for="item in filterByPermission(collectorNavigation)" :key="item.name">
                                    <Link :href="item.href" :class="navLinkClass(item.href)" @click="closeMobileMenu">
                                        <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                        <span class="truncate">{{ item.name }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </Transition>
                    </div>

                    <!-- Keuangan -->
                    <div v-if="filterByPermission(financeNavigation).length" class="mb-4">
                        <button
                            @click="toggleSection('finance')"
                            class="w-full flex items-center justify-between px-3 mb-2 text-sm font-bold text-green-400 uppercase tracking-wider hover:text-green-300 transition-colors"
                        >
                            <span class="flex items-center gap-2"><component :is="getIcon('banknotes')" class="w-4 h-4 flex-shrink-0" />Keuangan</span>
                            <component
                                :is="getIcon('chevron-down')"
                                class="w-4 h-4 transition-transform duration-200"
                                :class="{ 'rotate-180': !expandedSections.finance }"
                            />
                        </button>
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-96"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="opacity-100 max-h-96"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <ul v-show="expandedSections.finance" class="space-y-1 overflow-hidden">
                                <li v-for="item in filterByPermission(financeNavigation)" :key="item.name">
                                    <Link :href="item.href" :class="navLinkClass(item.href)" @click="closeMobileMenu">
                                        <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                        <span class="truncate">{{ item.name }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </Transition>
                    </div>

                    <!-- Jaringan -->
                    <div v-if="filterByPermission(networkNavigation).length" class="mb-4">
                        <button
                            @click="toggleSection('network')"
                            class="w-full flex items-center justify-between px-3 mb-2 text-sm font-bold text-cyan-400 uppercase tracking-wider hover:text-cyan-300 transition-colors"
                        >
                            <span class="flex items-center gap-2"><component :is="getIcon('signal')" class="w-4 h-4 flex-shrink-0" />Jaringan</span>
                            <component
                                :is="getIcon('chevron-down')"
                                class="w-4 h-4 transition-transform duration-200"
                                :class="{ 'rotate-180': !expandedSections.network }"
                            />
                        </button>
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-96"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="opacity-100 max-h-96"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <ul v-show="expandedSections.network" class="space-y-1 overflow-hidden">
                                <li v-for="item in filterByPermission(networkNavigation)" :key="item.name">
                                    <Link :href="item.href" :class="navLinkClass(item.href)" @click="closeMobileMenu">
                                        <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                        <span class="truncate">{{ item.name }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </Transition>
                    </div>

                    <!-- Master Data -->
                    <div v-if="filterByPermission(masterNavigation).length" class="mb-4">
                        <button
                            @click="toggleSection('master')"
                            class="w-full flex items-center justify-between px-3 mb-2 text-sm font-bold text-blue-400 uppercase tracking-wider hover:text-blue-300 transition-colors"
                        >
                            <span class="flex items-center gap-2"><component :is="getIcon('cube')" class="w-4 h-4 flex-shrink-0" />Master Data</span>
                            <component
                                :is="getIcon('chevron-down')"
                                class="w-4 h-4 transition-transform duration-200"
                                :class="{ 'rotate-180': !expandedSections.master }"
                            />
                        </button>
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-96"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="opacity-100 max-h-96"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <ul v-show="expandedSections.master" class="space-y-1 overflow-hidden">
                                <li v-for="item in filterByPermission(masterNavigation)" :key="item.name">
                                    <Link :href="item.href" :class="navLinkClass(item.href)" @click="closeMobileMenu">
                                        <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                        <span class="truncate">{{ item.name }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </Transition>
                    </div>

                    <!-- System -->
                    <div v-if="filterByPermission(systemNavigation).length" class="mb-4">
                        <button
                            @click="toggleSection('system')"
                            class="w-full flex items-center justify-between px-3 mb-2 text-sm font-bold text-blue-400 uppercase tracking-wider hover:text-blue-300 transition-colors"
                        >
                            <span class="flex items-center gap-2"><component :is="getIcon('cog')" class="w-4 h-4 flex-shrink-0" />Sistem</span>
                            <component
                                :is="getIcon('chevron-down')"
                                class="w-4 h-4 transition-transform duration-200"
                                :class="{ 'rotate-180': !expandedSections.system }"
                            />
                        </button>
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-96"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="opacity-100 max-h-96"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <ul v-show="expandedSections.system" class="space-y-1 overflow-hidden">
                                <li v-for="item in filterByPermission(systemNavigation)" :key="item.name">
                                    <Link :href="item.href" :class="navLinkClass(item.href)" @click="closeMobileMenu">
                                        <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                        <span class="truncate">{{ item.name }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </Transition>
                    </div>
                </nav>

                <!-- Mobile User Menu -->
                <div class="border-t border-gray-700 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gray-600 rounded-full flex items-center justify-center">
                            <span class="text-white font-medium">{{ user?.name?.charAt(0) || 'A' }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-white text-sm font-medium truncate">{{ user?.name || 'Admin' }}</p>
                            <p class="text-gray-400 text-xs truncate">{{ user?.email }}</p>
                        </div>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="mt-3 w-full flex items-center justify-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Logout
                    </Link>
                </div>
            </aside>
        </Transition>

        <!-- Desktop Sidebar (hidden on mobile) -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-40 flex-col bg-gray-900 transition-all duration-300 hidden lg:flex',
                sidebarOpen ? 'w-64' : 'w-20'
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center justify-between px-4 bg-gray-800">
                <Link href="/admin" class="flex items-center gap-3">
                    <img
                        src="/img/logo-dark.png"
                        alt="Logo"
                        class="h-10 w-auto object-contain"
                    >
                    <span v-if="sidebarOpen" class="text-white font-semibold text-lg truncate max-w-[160px]">
                        {{ page.props.isp?.name || 'ISP Billing' }}
                    </span>
                </Link>
                <button
                    @click="toggleSidebar"
                    class="text-gray-400 hover:text-white"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            :d="sidebarOpen ? 'M11 19l-7-7 7-7m8 14l-7-7 7-7' : 'M13 5l7 7-7 7M5 5l7 7-7 7'" />
                    </svg>
                </button>
            </div>

            <!-- Desktop Navigation -->
            <nav class="flex-1 overflow-y-auto py-4 px-3">
                <!-- Main -->
                <div class="mb-4">
                    <div v-if="sidebarOpen" class="px-3 mb-2 flex items-center gap-2 text-sm font-bold text-blue-400 uppercase tracking-wider">
                        <component :is="getIcon('home')" class="w-4 h-4" />
                        <span>Menu Utama</span>
                    </div>
                    <ul class="space-y-1">
                        <li v-for="item in filterByPermission(mainNavigation)" :key="item.name">
                            <Link :href="item.href" :class="navLinkClass(item.href)">
                                <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>

                <!-- Billing & Tagihan -->
                <div v-if="filterByPermission(billingNavigation).length" class="mb-4">
                    <button
                        v-if="sidebarOpen"
                        @click="toggleSection('billing')"
                        class="w-full flex items-center justify-between px-3 mb-2 text-sm font-bold text-blue-400 uppercase tracking-wider hover:text-blue-300 transition-colors"
                    >
                        <span class="flex items-center gap-2"><component :is="getIcon('document')" class="w-4 h-4 flex-shrink-0" />Billing & Tagihan</span>
                        <component
                            :is="getIcon('chevron-down')"
                            class="w-4 h-4 transition-transform duration-200"
                            :class="{ 'rotate-180': !expandedSections.billing }"
                        />
                    </button>
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-96"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 max-h-96"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <ul v-show="expandedSections.billing || !sidebarOpen" class="space-y-1 overflow-hidden">
                            <li v-for="item in filterByPermission(billingNavigation)" :key="item.name">
                                <Link :href="item.href" :class="navLinkClass(item.href)">
                                    <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                    <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                                </Link>
                            </li>
                        </ul>
                    </Transition>
                </div>

                <!-- Penagih -->
                <div v-if="filterByPermission(collectorNavigation).length" class="mb-4">
                    <button
                        v-if="sidebarOpen"
                        @click="toggleSection('collector')"
                        class="w-full flex items-center justify-between px-3 mb-2 text-sm font-bold text-orange-400 uppercase tracking-wider hover:text-orange-300 transition-colors"
                    >
                        <span class="flex items-center gap-2"><component :is="getIcon('user-group')" class="w-4 h-4 flex-shrink-0" />Penagih</span>
                        <component
                            :is="getIcon('chevron-down')"
                            class="w-4 h-4 transition-transform duration-200"
                            :class="{ 'rotate-180': !expandedSections.collector }"
                        />
                    </button>
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-96"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 max-h-96"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <ul v-show="expandedSections.collector || !sidebarOpen" class="space-y-1 overflow-hidden">
                            <li v-for="item in filterByPermission(collectorNavigation)" :key="item.name">
                                <Link :href="item.href" :class="navLinkClass(item.href)">
                                    <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                    <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                                </Link>
                            </li>
                        </ul>
                    </Transition>
                </div>

                <!-- Keuangan -->
                <div v-if="filterByPermission(financeNavigation).length" class="mb-4">
                    <button
                        v-if="sidebarOpen"
                        @click="toggleSection('finance')"
                        class="w-full flex items-center justify-between px-3 mb-2 text-sm font-bold text-green-400 uppercase tracking-wider hover:text-green-300 transition-colors"
                    >
                        <span class="flex items-center gap-2"><component :is="getIcon('banknotes')" class="w-4 h-4 flex-shrink-0" />Keuangan</span>
                        <component
                            :is="getIcon('chevron-down')"
                            class="w-4 h-4 transition-transform duration-200"
                            :class="{ 'rotate-180': !expandedSections.finance }"
                        />
                    </button>
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-96"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 max-h-96"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <ul v-show="expandedSections.finance || !sidebarOpen" class="space-y-1 overflow-hidden">
                            <li v-for="item in filterByPermission(financeNavigation)" :key="item.name">
                                <Link :href="item.href" :class="navLinkClass(item.href)">
                                    <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                    <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                                </Link>
                            </li>
                        </ul>
                    </Transition>
                </div>

                <!-- Jaringan -->
                <div v-if="filterByPermission(networkNavigation).length" class="mb-4">
                    <button
                        v-if="sidebarOpen"
                        @click="toggleSection('network')"
                        class="w-full flex items-center justify-between px-3 mb-2 text-sm font-bold text-cyan-400 uppercase tracking-wider hover:text-cyan-300 transition-colors"
                    >
                        <span class="flex items-center gap-2"><component :is="getIcon('signal')" class="w-4 h-4 flex-shrink-0" />Jaringan</span>
                        <component
                            :is="getIcon('chevron-down')"
                            class="w-4 h-4 transition-transform duration-200"
                            :class="{ 'rotate-180': !expandedSections.network }"
                        />
                    </button>
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-96"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 max-h-96"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <ul v-show="expandedSections.network || !sidebarOpen" class="space-y-1 overflow-hidden">
                            <li v-for="item in filterByPermission(networkNavigation)" :key="item.name">
                                <Link :href="item.href" :class="navLinkClass(item.href)">
                                    <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                    <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                                </Link>
                            </li>
                        </ul>
                    </Transition>
                </div>

                <!-- Master Data -->
                <div v-if="filterByPermission(masterNavigation).length" class="mb-4">
                    <button
                        v-if="sidebarOpen"
                        @click="toggleSection('master')"
                        class="w-full flex items-center justify-between px-3 mb-2 text-sm font-bold text-blue-400 uppercase tracking-wider hover:text-blue-300 transition-colors"
                    >
                        <span class="flex items-center gap-2"><component :is="getIcon('cube')" class="w-4 h-4 flex-shrink-0" />Master Data</span>
                        <component
                            :is="getIcon('chevron-down')"
                            class="w-4 h-4 transition-transform duration-200"
                            :class="{ 'rotate-180': !expandedSections.master }"
                        />
                    </button>
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-96"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 max-h-96"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <ul v-show="expandedSections.master || !sidebarOpen" class="space-y-1 overflow-hidden">
                            <li v-for="item in filterByPermission(masterNavigation)" :key="item.name">
                                <Link :href="item.href" :class="navLinkClass(item.href)">
                                    <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                    <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                                </Link>
                            </li>
                        </ul>
                    </Transition>
                </div>

                <!-- System -->
                <div v-if="filterByPermission(systemNavigation).length" class="mb-4">
                    <button
                        v-if="sidebarOpen"
                        @click="toggleSection('system')"
                        class="w-full flex items-center justify-between px-3 mb-2 text-sm font-bold text-blue-400 uppercase tracking-wider hover:text-blue-300 transition-colors"
                    >
                        <span class="flex items-center gap-2"><component :is="getIcon('cog')" class="w-4 h-4 flex-shrink-0" />Sistem</span>
                        <component
                            :is="getIcon('chevron-down')"
                            class="w-4 h-4 transition-transform duration-200"
                            :class="{ 'rotate-180': !expandedSections.system }"
                        />
                    </button>
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-96"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 max-h-96"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <ul v-show="expandedSections.system || !sidebarOpen" class="space-y-1 overflow-hidden">
                            <li v-for="item in filterByPermission(systemNavigation)" :key="item.name">
                                <Link :href="item.href" :class="navLinkClass(item.href)">
                                    <component :is="getIcon(item.icon)" class="w-5 h-5 flex-shrink-0" />
                                    <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                                </Link>
                            </li>
                        </ul>
                    </Transition>
                </div>
            </nav>

            <!-- Desktop User Menu -->
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
        <div :class="['transition-all duration-300 lg:ml-64', sidebarOpen ? 'lg:ml-64' : 'lg:ml-20']">
            <!-- Top Header -->
            <header class="sticky top-0 z-30 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between h-16 px-4 lg:px-8">
                    <!-- Mobile Menu Button -->
                    <button
                        @click="mobileMenuOpen = true"
                        class="lg:hidden p-2 rounded-md text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700"
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
                    <div class="flex items-center gap-2 lg:gap-4">
                        <!-- Theme Toggle -->
                        <button
                            @click="toggleTheme"
                            class="p-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors"
                            :title="isDark ? 'Mode Terang' : 'Mode Gelap'"
                        >
                            <!-- Sun icon (shown in dark mode) -->
                            <svg v-if="isDark" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <!-- Moon icon (shown in light mode) -->
                            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                        </button>

                        <!-- Notifications -->
                        <button class="p-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white relative">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                        </button>

                        <!-- Logout (Desktop only) -->
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="hidden lg:block p-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
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

        <!-- Flash Messages -->
        <div class="fixed bottom-4 right-4 left-4 lg:left-auto z-50 space-y-2 max-w-md">
            <!-- Success -->
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div
                    v-if="showSuccess && successMessage"
                    class="bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg flex items-start gap-3"
                >
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="flex-1">
                        <p class="font-semibold">Berhasil!</p>
                        <p class="text-sm opacity-90">{{ successMessage }}</p>
                    </div>
                    <button @click="showSuccess = false" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </Transition>

            <!-- Error -->
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div
                    v-if="showError && errorMessage"
                    class="bg-red-500 text-white px-6 py-4 rounded-lg shadow-lg flex items-start gap-3"
                >
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="flex-1">
                        <p class="font-semibold">Error!</p>
                        <p class="text-sm opacity-90">{{ errorMessage }}</p>
                    </div>
                    <button @click="showError = false" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </Transition>

            <!-- Warning -->
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div
                    v-if="showWarning && warningMessage"
                    class="bg-yellow-500 text-white px-6 py-4 rounded-lg shadow-lg flex items-start gap-3"
                >
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="flex-1">
                        <p class="font-semibold">Peringatan!</p>
                        <p class="text-sm opacity-90">{{ warningMessage }}</p>
                    </div>
                    <button @click="showWarning = false" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </Transition>

            <!-- Info -->
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div
                    v-if="showInfo && infoMessage"
                    class="bg-blue-500 text-white px-6 py-4 rounded-lg shadow-lg flex items-start gap-3"
                >
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="flex-1">
                        <p class="font-semibold">Info</p>
                        <p class="text-sm opacity-90">{{ infoMessage }}</p>
                    </div>
                    <button @click="showInfo = false" class="text-white/80 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </Transition>
        </div>
    </div>
</template>

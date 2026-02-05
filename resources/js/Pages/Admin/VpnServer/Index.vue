<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    clients: Object,
    filters: Object,
    openVpnStatus: Object,
    wireGuardStatus: Object,
    settings: Object,
    stats: Object,
})

const search = ref(props.filters.search || '')
const protocol = ref(props.filters.protocol || '')
const status = ref(props.filters.status || '')

// Apply filters
const applyFilters = () => {
    router.get('/admin/vpn-server', {
        search: search.value || undefined,
        protocol: protocol.value || undefined,
        status: status.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Toggle client
const toggleClient = (client) => {
    router.post(`/admin/vpn-server/clients/${client.id}/toggle`)
}

// Delete client
const deleteClient = (client) => {
    if (confirm(`Yakin ingin menghapus client ${client.name}?`)) {
        router.delete(`/admin/vpn-server/clients/${client.id}`)
    }
}

// Refresh status
const refreshStatus = () => {
    router.post('/admin/vpn-server/refresh-status')
}

// Service control
const startOpenVpn = () => router.post('/admin/vpn-server/openvpn/start')
const stopOpenVpn = () => router.post('/admin/vpn-server/openvpn/stop')
const startWireGuard = () => router.post('/admin/vpn-server/wireguard/start')
const stopWireGuard = () => router.post('/admin/vpn-server/wireguard/stop')

// Format date
const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('id-ID')
}

// Status badge color - use computed status from model
const getStatusColor = (client) => {
    const colors = {
        connected: 'bg-green-100 text-green-700',
        disconnected: 'bg-gray-100 text-gray-600',
        disabled: 'bg-red-100 text-red-700',
    }
    return colors[client.status] || 'bg-gray-100 text-gray-600'
}

const getStatusText = (client) => {
    const texts = {
        connected: 'Connected',
        disconnected: 'Disconnected',
        disabled: 'Disabled',
    }
    return texts[client.status] || 'Unknown'
}

// Protocol badge
const getProtocolBadge = (protocol) => {
    return protocol === 'wireguard'
        ? 'bg-purple-100 text-purple-700'
        : 'bg-blue-100 text-blue-700'
}

// Check if setup is complete
const openVpnReady = computed(() => props.openVpnStatus?.all_ready)
const wireGuardReady = computed(() => props.wireGuardStatus?.all_ready)
</script>

<template>
    <Head title="VPN Server" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900">VPN Server</h1>
                <div class="flex gap-2">
                    <Link
                        href="/admin/vpn-server/settings"
                        class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center gap-1"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="hidden sm:inline">Settings</span>
                    </Link>
                    <Link
                        href="/admin/vpn-server/clients/create"
                        class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-1"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span class="hidden sm:inline">Tambah</span> Client
                    </Link>
                </div>
            </div>
        </template>

        <!-- Status Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-xl shadow-sm p-3">
                <div class="flex items-center gap-2">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 truncate">Total</p>
                        <p class="text-xl font-bold">{{ stats.total_clients }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-3">
                <div class="flex items-center gap-2">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 truncate">OpenVPN</p>
                        <p class="text-xl font-bold">{{ stats.openvpn_clients }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-3">
                <div class="flex items-center gap-2">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 truncate">WireGuard</p>
                        <p class="text-xl font-bold">{{ stats.wireguard_clients }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-3">
                <div class="flex items-center gap-2">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 truncate">Enabled</p>
                        <p class="text-xl font-bold">{{ stats.enabled_clients }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service Status -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <!-- OpenVPN Status -->
            <div class="bg-white rounded-xl shadow-sm p-3">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span
                            :class="[
                                'w-2.5 h-2.5 rounded-full',
                                openVpnStatus.service_running ? 'bg-green-500' : 'bg-red-500'
                            ]"
                        ></span>
                        <h3 class="font-semibold text-sm">OpenVPN</h3>
                    </div>
                    <button
                        v-if="!openVpnStatus.service_running"
                        @click="startOpenVpn"
                        class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200"
                        :disabled="!openVpnReady"
                        :class="{ 'opacity-50 cursor-not-allowed': !openVpnReady }"
                    >
                        Start
                    </button>
                    <button
                        v-else
                        @click="stopOpenVpn"
                        class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200"
                    >
                        Stop
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-2 text-xs">
                    <div class="text-center">
                        <span :class="openVpnStatus.openvpn_installed ? 'text-green-600' : 'text-red-600'">
                            {{ openVpnStatus.openvpn_installed ? '✓' : '✗' }}
                        </span>
                        <p class="text-gray-500 mt-0.5">Install</p>
                    </div>
                    <div class="text-center">
                        <span :class="openVpnStatus.ca_cert_exists ? 'text-green-600' : 'text-red-600'">
                            {{ openVpnStatus.ca_cert_exists ? '✓' : '✗' }}
                        </span>
                        <p class="text-gray-500 mt-0.5">CA</p>
                    </div>
                    <div class="text-center">
                        <span :class="openVpnStatus.server_cert_exists ? 'text-green-600' : 'text-red-600'">
                            {{ openVpnStatus.server_cert_exists ? '✓' : '✗' }}
                        </span>
                        <p class="text-gray-500 mt-0.5">Cert</p>
                    </div>
                </div>
            </div>

            <!-- WireGuard Status -->
            <div class="bg-white rounded-xl shadow-sm p-3">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span
                            :class="[
                                'w-2.5 h-2.5 rounded-full',
                                wireGuardStatus.interface_up ? 'bg-green-500' : 'bg-red-500'
                            ]"
                        ></span>
                        <h3 class="font-semibold text-sm">WireGuard</h3>
                    </div>
                    <button
                        v-if="!wireGuardStatus.interface_up"
                        @click="startWireGuard"
                        class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200"
                        :disabled="!wireGuardReady"
                        :class="{ 'opacity-50 cursor-not-allowed': !wireGuardReady }"
                    >
                        Start
                    </button>
                    <button
                        v-else
                        @click="stopWireGuard"
                        class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200"
                    >
                        Stop
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-2 text-xs">
                    <div class="text-center">
                        <span :class="wireGuardStatus.wireguard_installed ? 'text-green-600' : 'text-red-600'">
                            {{ wireGuardStatus.wireguard_installed ? '✓' : '✗' }}
                        </span>
                        <p class="text-gray-500 mt-0.5">Install</p>
                    </div>
                    <div class="text-center">
                        <span :class="wireGuardStatus.server_keys_exist ? 'text-green-600' : 'text-red-600'">
                            {{ wireGuardStatus.server_keys_exist ? '✓' : '✗' }}
                        </span>
                        <p class="text-gray-500 mt-0.5">Keys</p>
                    </div>
                    <div class="text-center">
                        <span :class="wireGuardStatus.interface_up ? 'text-green-600' : 'text-gray-400'">
                            {{ wireGuardStatus.interface_up ? '✓' : '✗' }}
                        </span>
                        <p class="text-gray-500 mt-0.5">wg0</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
            <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-2">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari..."
                    class="col-span-2 sm:w-48 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    @keyup.enter="applyFilters"
                >
                <select
                    v-model="protocol"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    @change="applyFilters"
                >
                    <option value="">Protocol</option>
                    <option value="openvpn">OpenVPN</option>
                    <option value="wireguard">WireGuard</option>
                </select>
                <select
                    v-model="status"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    @change="applyFilters"
                >
                    <option value="">Status</option>
                    <option value="enabled">Enabled</option>
                    <option value="disabled">Disabled</option>
                </select>
                <button
                    @click="refreshStatus"
                    class="col-span-2 sm:col-span-1 px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center justify-center gap-1"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh
                </button>
            </div>
        </div>

        <!-- Clients List - Mobile Cards -->
        <div class="lg:hidden space-y-3">
            <div v-for="client in clients.data" :key="client.id" class="bg-white rounded-xl shadow-sm p-4">
                <!-- Header -->
                <div class="flex items-start justify-between mb-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-semibold text-gray-900 truncate">{{ client.name }}</h3>
                            <span :class="['px-2 py-0.5 text-xs font-medium rounded', getProtocolBadge(client.protocol)]">
                                {{ client.protocol === 'wireguard' ? 'WG' : 'OVPN' }}
                            </span>
                            <span :class="['px-2 py-0.5 text-xs rounded', getStatusColor(client)]">
                                {{ getStatusText(client) }}
                            </span>
                        </div>
                        <p v-if="client.description" class="text-xs text-gray-500 mt-1 truncate">{{ client.description }}</p>
                    </div>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                    <div>
                        <span class="text-gray-500">VPN IP:</span>
                        <span class="font-mono ml-1">{{ client.client_vpn_ip }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Router:</span>
                        <span class="ml-1">{{ client.router?.name || '-' }}</span>
                    </div>
                    <div v-if="client.bytes_received || client.bytes_sent" class="col-span-2">
                        <span class="text-green-600 text-xs">RX: {{ client.bytes_received_formatted }}</span>
                        <span class="text-blue-600 text-xs ml-2">TX: {{ client.bytes_sent_formatted }}</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-1 pt-2 border-t border-gray-100">
                    <Link
                        :href="`/admin/vpn-server/clients/${client.id}`"
                        class="flex-1 py-2 text-center text-sm text-blue-600 hover:bg-blue-50 rounded"
                    >
                        Detail
                    </Link>
                    <a
                        :href="`/admin/vpn-server/clients/${client.id}/download-script`"
                        class="flex-1 py-2 text-center text-sm text-green-600 hover:bg-green-50 rounded"
                    >
                        Script
                    </a>
                    <button
                        @click="toggleClient(client)"
                        :class="['flex-1 py-2 text-center text-sm rounded', client.is_enabled ? 'text-red-600 hover:bg-red-50' : 'text-green-600 hover:bg-green-50']"
                    >
                        {{ client.is_enabled ? 'Disable' : 'Enable' }}
                    </button>
                    <Link
                        :href="`/admin/vpn-server/clients/${client.id}/edit`"
                        class="flex-1 py-2 text-center text-sm text-gray-600 hover:bg-gray-100 rounded"
                    >
                        Edit
                    </Link>
                    <button
                        @click="deleteClient(client)"
                        class="p-2 text-red-600 hover:bg-red-50 rounded"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Empty State Mobile -->
            <div v-if="!clients.data?.length" class="bg-white rounded-xl shadow-sm p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <p class="mt-4 text-gray-500">Belum ada VPN client</p>
                <Link
                    href="/admin/vpn-server/clients/create"
                    class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                    Tambah Client
                </Link>
            </div>
        </div>

        <!-- Clients Table - Desktop -->
        <div class="hidden lg:block bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Client</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Protocol</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">VPN IP</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Router</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">Traffic</th>
                        <th class="px-4 py-3 text-center text-sm font-medium text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="client in clients.data" :key="client.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div>
                                <p class="font-medium text-gray-900">{{ client.name }}</p>
                                <p class="text-xs text-gray-500">{{ client.description || '-' }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="['px-2 py-1 text-xs font-medium rounded', getProtocolBadge(client.protocol)]"
                            >
                                {{ client.protocol === 'wireguard' ? 'WireGuard' : 'OpenVPN' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-mono text-sm">{{ client.client_vpn_ip }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="client.router" class="text-sm">{{ client.router.name }}</span>
                            <span v-else class="text-sm text-gray-400">-</span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-1 text-xs rounded', getStatusColor(client)]">
                                {{ getStatusText(client) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <div v-if="client.bytes_received || client.bytes_sent">
                                <p class="text-green-600">RX: {{ client.bytes_received_formatted }}</p>
                                <p class="text-blue-600">TX: {{ client.bytes_sent_formatted }}</p>
                            </div>
                            <span v-else class="text-gray-400">-</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-center gap-1">
                                <Link
                                    :href="`/admin/vpn-server/clients/${client.id}`"
                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded"
                                    title="Detail"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </Link>
                                <a
                                    :href="`/admin/vpn-server/clients/${client.id}/download-script`"
                                    class="p-2 text-green-600 hover:bg-green-50 rounded"
                                    title="Download Script"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </a>
                                <button
                                    @click="toggleClient(client)"
                                    :class="[
                                        'p-2 rounded',
                                        client.is_enabled ? 'text-red-600 hover:bg-red-50' : 'text-green-600 hover:bg-green-50'
                                    ]"
                                    :title="client.is_enabled ? 'Disable' : 'Enable'"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path v-if="client.is_enabled" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                                <Link
                                    :href="`/admin/vpn-server/clients/${client.id}/edit`"
                                    class="p-2 text-gray-600 hover:bg-gray-100 rounded"
                                    title="Edit"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button
                                    @click="deleteClient(client)"
                                    class="p-2 text-red-600 hover:bg-red-50 rounded"
                                    title="Hapus"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Empty State Desktop -->
            <div v-if="!clients.data?.length" class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <p class="mt-4 text-gray-500">Belum ada VPN client</p>
                <Link
                    href="/admin/vpn-server/clients/create"
                    class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                    Tambah Client Pertama
                </Link>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="clients.data?.length && clients.last_page > 1" class="mt-6 flex justify-center">
            <div class="flex gap-2">
                <Link
                    v-for="link in clients.links"
                    :key="link.label"
                    :href="link.url"
                    :class="[
                        'px-3 py-1 text-sm rounded',
                        link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100',
                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AdminLayout>
</template>

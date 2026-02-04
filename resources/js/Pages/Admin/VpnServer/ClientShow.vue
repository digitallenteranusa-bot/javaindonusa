<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    client: Object,
})

const showConfig = ref(false)
const showScript = ref(false)

// Calculate server IP based on client VPN IP (server is always .1 in same subnet)
const serverIp = computed(() => {
    if (!props.client.client_vpn_ip) return '-'
    const parts = props.client.client_vpn_ip.split('.')
    if (parts.length !== 4) return '-'
    return `${parts[0]}.${parts[1]}.${parts[2]}.1`
})

// Copy to clipboard
const copyToClipboard = (text, message = 'Copied!') => {
    navigator.clipboard.writeText(text)
    alert(message)
}

// Regenerate
const regenerate = () => {
    if (confirm('Regenerate akan membuat sertifikat/key baru. Client perlu dikonfigurasi ulang. Lanjutkan?')) {
        router.post(`/admin/vpn-server/clients/${props.client.id}/regenerate`)
    }
}

// Toggle client
const toggleClient = () => {
    router.post(`/admin/vpn-server/clients/${props.client.id}/toggle`)
}

// Delete client
const deleteClient = () => {
    if (confirm(`Yakin ingin menghapus client ${props.client.name}?`)) {
        router.delete(`/admin/vpn-server/clients/${props.client.id}`)
    }
}

// Format date
const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('id-ID')
}

// Status helpers
const getStatusColor = () => {
    if (!props.client.is_enabled) return 'bg-red-100 text-red-700'
    if (props.client.connected_at && (!props.client.disconnected_at || new Date(props.client.connected_at) > new Date(props.client.disconnected_at))) {
        return 'bg-green-100 text-green-700'
    }
    return 'bg-gray-100 text-gray-600'
}

const getStatusText = () => {
    if (!props.client.is_enabled) return 'Disabled'
    if (props.client.connected_at && (!props.client.disconnected_at || new Date(props.client.connected_at) > new Date(props.client.disconnected_at))) {
        return 'Connected'
    }
    return 'Disconnected'
}
</script>

<template>
    <Head :title="`VPN Client - ${client.name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/vpn-server" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ client.name }}</h1>
                        <p class="text-sm text-gray-500">{{ client.description || 'VPN Client' }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="`/admin/vpn-server/clients/${client.id}/edit`"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                    >
                        Edit
                    </Link>
                    <button
                        @click="toggleClient"
                        :class="[
                            'px-4 py-2 rounded-lg',
                            client.is_enabled ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200'
                        ]"
                    >
                        {{ client.is_enabled ? 'Disable' : 'Enable' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Info -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Status Card -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold">Status</h2>
                        <span :class="['px-3 py-1 rounded-full text-sm font-medium', getStatusColor()]">
                            {{ getStatusText() }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Protocol</p>
                            <p class="font-medium">{{ client.protocol === 'wireguard' ? 'WireGuard' : 'OpenVPN' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">VPN IP</p>
                            <p class="font-medium font-mono">{{ client.client_vpn_ip }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Remote IP</p>
                            <p class="font-medium font-mono">{{ client.remote_ip || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Router</p>
                            <p class="font-medium">{{ client.router?.name || '-' }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t">
                        <div>
                            <p class="text-sm text-gray-500">Connected At</p>
                            <p class="font-medium">{{ formatDate(client.connected_at) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Traffic (RX/TX)</p>
                            <p class="font-medium">
                                {{ client.bytes_received_formatted || '0 B' }} / {{ client.bytes_sent_formatted || '0 B' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Technical Details -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Detail Teknis</h2>

                    <div class="space-y-4">
                        <div v-if="client.protocol === 'openvpn'">
                            <label class="block text-sm font-medium text-gray-500 mb-1">Common Name (CN)</label>
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    :value="client.common_name"
                                    readonly
                                    class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg font-mono text-sm"
                                >
                                <button
                                    @click="copyToClipboard(client.common_name)"
                                    class="px-3 py-2 bg-gray-100 rounded-lg hover:bg-gray-200"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div v-if="client.protocol === 'wireguard'">
                            <label class="block text-sm font-medium text-gray-500 mb-1">Public Key</label>
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    :value="client.public_key"
                                    readonly
                                    class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg font-mono text-sm"
                                >
                                <button
                                    @click="copyToClipboard(client.public_key)"
                                    class="px-3 py-2 bg-gray-100 rounded-lg hover:bg-gray-200"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div v-if="client.mikrotik_lan_subnet">
                            <label class="block text-sm font-medium text-gray-500 mb-1">Mikrotik LAN Subnet</label>
                            <p class="font-mono text-sm">{{ client.mikrotik_lan_subnet }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-1">Last Generated</label>
                            <p class="text-sm">{{ formatDate(client.last_generated_at) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Config/Script Viewer -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold">Mikrotik Script</h2>
                        <div class="flex gap-2">
                            <button
                                @click="showScript = !showScript"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm"
                            >
                                {{ showScript ? 'Hide' : 'Show' }}
                            </button>
                            <a
                                :href="`/admin/vpn-server/clients/${client.id}/download-script`"
                                class="px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 text-sm"
                            >
                                Download .rsc
                            </a>
                        </div>
                    </div>

                    <div v-if="showScript" class="mt-4">
                        <pre class="p-4 bg-gray-900 text-green-400 rounded-lg overflow-x-auto text-xs">{{ client.generated_script }}</pre>
                        <button
                            @click="copyToClipboard(client.generated_script, 'Script copied!')"
                            class="mt-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm"
                        >
                            Copy Script
                        </button>
                    </div>
                </div>

                <!-- OpenVPN Config (only for OpenVPN) -->
                <div v-if="client.protocol === 'openvpn'" class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold">OpenVPN Config (.ovpn)</h2>
                        <div class="flex gap-2">
                            <button
                                @click="showConfig = !showConfig"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm"
                            >
                                {{ showConfig ? 'Hide' : 'Show' }}
                            </button>
                            <a
                                :href="`/admin/vpn-server/clients/${client.id}/download-config`"
                                class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-sm"
                            >
                                Download .ovpn
                            </a>
                        </div>
                    </div>

                    <div v-if="showConfig" class="mt-4">
                        <pre class="p-4 bg-gray-900 text-green-400 rounded-lg overflow-x-auto text-xs max-h-96">{{ client.generated_config }}</pre>
                    </div>
                </div>

                <!-- WireGuard Private Key (only for WireGuard) -->
                <div v-if="client.protocol === 'wireguard'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Private Key (untuk Mikrotik)</h2>
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg mb-4">
                        <p class="text-sm text-yellow-700">
                            Private key ini diperlukan saat konfigurasi WireGuard di Mikrotik.
                            Simpan dengan aman dan jangan dibagikan.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <input
                            type="password"
                            :value="client.private_key"
                            readonly
                            class="flex-1 px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg font-mono text-sm"
                        >
                        <button
                            @click="copyToClipboard(client.private_key, 'Private key copied!')"
                            class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200"
                        >
                            Copy
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Aksi</h2>
                    <div class="space-y-3">
                        <a
                            :href="`/admin/vpn-server/clients/${client.id}/download-script`"
                            class="flex items-center gap-3 p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download Mikrotik Script
                        </a>

                        <a
                            v-if="client.protocol === 'openvpn'"
                            :href="`/admin/vpn-server/clients/${client.id}/download-p12`"
                            class="flex items-center gap-3 p-3 bg-orange-50 text-orange-700 rounded-lg hover:bg-orange-100 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Download .p12 (MikroTik v6)
                        </a>

                        <a
                            v-if="client.protocol === 'openvpn'"
                            :href="`/admin/vpn-server/clients/${client.id}/download-certificates`"
                            class="flex items-center gap-3 p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                            Download Certificates (ZIP)
                        </a>

                        <a
                            v-if="client.protocol === 'openvpn'"
                            :href="`/admin/vpn-server/clients/${client.id}/download-config`"
                            class="flex items-center gap-3 p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download .ovpn (All-in-One)
                        </a>

                        <button
                            @click="regenerate"
                            class="w-full flex items-center gap-3 p-3 bg-yellow-50 text-yellow-700 rounded-lg hover:bg-yellow-100 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Regenerate Config
                        </button>

                        <button
                            @click="deleteClient"
                            class="w-full flex items-center gap-3 p-3 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Hapus Client
                        </button>
                    </div>
                </div>

                <!-- Setup Instructions -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Cara Setup</h2>
                    <div class="space-y-3 text-sm">
                        <div v-if="client.protocol === 'openvpn'">
                            <p class="font-medium text-gray-700 mb-2">OpenVPN Setup (MikroTik v6):</p>
                            <ol class="list-decimal list-inside space-y-2 text-gray-600">
                                <li>Download file <strong>.p12</strong> (1 file saja)</li>
                                <li>Upload file .p12 ke Mikrotik via WinBox</li>
                                <li>Import: <code class="bg-gray-100 px-1 rounded">/certificate import file-name=nama.p12</code></li>
                                <li>Cek: <code class="bg-gray-100 px-1 rounded">/certificate print</code></li>
                                <li>Buat OVPN client interface</li>
                                <li>Test koneksi ke <code class="bg-gray-100 px-1 rounded">{{ serverIp }}</code></li>
                            </ol>
                            <p class="text-xs text-gray-500 mt-2">
                                File .p12 berisi semua certificate dalam 1 file untuk kemudahan import.
                            </p>
                        </div>
                        <div v-else>
                            <p class="font-medium text-gray-700 mb-2">WireGuard Setup:</p>
                            <ol class="list-decimal list-inside space-y-2 text-gray-600">
                                <li>Pastikan RouterOS v7+</li>
                                <li>Copy script ke terminal Mikrotik</li>
                                <li>Atau setup manual dengan private key di atas</li>
                                <li>Test koneksi ke <code class="bg-gray-100 px-1 rounded">{{ serverIp }}</code></li>
                            </ol>
                        </div>
                    </div>

                    <!-- Server Info -->
                    <div class="mt-4 pt-4 border-t">
                        <p class="text-xs text-gray-500">
                            Server VPN IP: <code class="bg-gray-100 px-1 rounded">{{ serverIp }}</code><br>
                            Client VPN IP: <code class="bg-gray-100 px-1 rounded">{{ client.client_vpn_ip }}</code>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

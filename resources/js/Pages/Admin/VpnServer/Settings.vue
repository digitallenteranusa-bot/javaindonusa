<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    settings: Object,
    openVpnStatus: Object,
    wireGuardStatus: Object,
})

const form = useForm({
    public_endpoint: props.settings.public_endpoint || '',
    server_address: props.settings.server_address || '10.200.1.0/24',
    port: props.settings.port || 1194,
    protocol: props.settings.protocol || 'udp',
    wg_port: props.settings.wg_port || 51820,
})

const loading = ref({
    initPki: false,
    generateCa: false,
    generateServer: false,
    generateDh: false,
    generateTa: false,
    generateWgKeys: false,
})

// Save settings
const saveSettings = () => {
    form.post('/admin/vpn-server/settings')
}

// OpenVPN Setup
const initPki = () => {
    loading.value.initPki = true
    router.post('/admin/vpn-server/openvpn/init-pki', {}, {
        onFinish: () => loading.value.initPki = false
    })
}

const generateCa = () => {
    loading.value.generateCa = true
    router.post('/admin/vpn-server/openvpn/generate-ca', {}, {
        onFinish: () => loading.value.generateCa = false
    })
}

const generateServer = () => {
    loading.value.generateServer = true
    router.post('/admin/vpn-server/openvpn/generate-server', {}, {
        onFinish: () => loading.value.generateServer = false
    })
}

const generateDh = () => {
    loading.value.generateDh = true
    router.post('/admin/vpn-server/openvpn/generate-dh', {}, {
        onFinish: () => loading.value.generateDh = false
    })
}

const generateTa = () => {
    loading.value.generateTa = true
    router.post('/admin/vpn-server/openvpn/generate-ta', {}, {
        onFinish: () => loading.value.generateTa = false
    })
}

// WireGuard Setup
const generateWgKeys = () => {
    loading.value.generateWgKeys = true
    router.post('/admin/vpn-server/wireguard/generate-keys', {}, {
        onFinish: () => loading.value.generateWgKeys = false
    })
}
</script>

<template>
    <Head title="VPN Server Settings" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/vpn-server" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">VPN Server Settings</h1>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- General Settings -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Pengaturan Umum</h2>
                <form @submit.prevent="saveSettings" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Public Endpoint (IP/Domain VPS)
                        </label>
                        <input
                            v-model="form.public_endpoint"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="vpn.example.com atau 123.45.67.89"
                        >
                        <p class="text-xs text-gray-500 mt-1">IP public atau domain VPS tempat VPN server berjalan</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            VPN Network (CIDR)
                        </label>
                        <input
                            v-model="form.server_address"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="10.200.1.0/24"
                        >
                        <p class="text-xs text-gray-500 mt-1">Network untuk VPN tunnel. Server akan menggunakan IP .1</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                OpenVPN Port
                            </label>
                            <input
                                v-model="form.port"
                                type="number"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Protocol
                            </label>
                            <select
                                v-model="form.protocol"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="udp">UDP (Recommended)</option>
                                <option value="tcp">TCP</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            WireGuard Port
                        </label>
                        <input
                            v-model="form.wg_port"
                            type="number"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                    </button>
                </form>
            </div>

            <!-- OpenVPN Setup -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">OpenVPN Setup</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Jalankan langkah-langkah berikut secara berurutan untuk setup OpenVPN server.
                </p>

                <div class="space-y-4">
                    <!-- Step 1: PKI -->
                    <div class="flex items-center justify-between p-4 border rounded-lg">
                        <div class="flex items-center gap-3">
                            <div
                                :class="[
                                    'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
                                    openVpnStatus.pki_initialized ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                                ]"
                            >
                                1
                            </div>
                            <div>
                                <p class="font-medium">Initialize PKI</p>
                                <p class="text-xs text-gray-500">Setup public key infrastructure</p>
                            </div>
                        </div>
                        <button
                            @click="initPki"
                            :disabled="loading.initPki || openVpnStatus.pki_initialized"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm',
                                openVpnStatus.pki_initialized
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-blue-100 text-blue-700 hover:bg-blue-200'
                            ]"
                        >
                            {{ openVpnStatus.pki_initialized ? 'Done' : (loading.initPki ? 'Loading...' : 'Run') }}
                        </button>
                    </div>

                    <!-- Step 2: CA -->
                    <div class="flex items-center justify-between p-4 border rounded-lg">
                        <div class="flex items-center gap-3">
                            <div
                                :class="[
                                    'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
                                    openVpnStatus.ca_cert_exists ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                                ]"
                            >
                                2
                            </div>
                            <div>
                                <p class="font-medium">Generate CA Certificate</p>
                                <p class="text-xs text-gray-500">Create Certificate Authority</p>
                            </div>
                        </div>
                        <button
                            @click="generateCa"
                            :disabled="loading.generateCa || !openVpnStatus.pki_initialized || openVpnStatus.ca_cert_exists"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm',
                                openVpnStatus.ca_cert_exists
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-blue-100 text-blue-700 hover:bg-blue-200',
                                { 'opacity-50 cursor-not-allowed': !openVpnStatus.pki_initialized }
                            ]"
                        >
                            {{ openVpnStatus.ca_cert_exists ? 'Done' : (loading.generateCa ? 'Loading...' : 'Run') }}
                        </button>
                    </div>

                    <!-- Step 3: Server Cert -->
                    <div class="flex items-center justify-between p-4 border rounded-lg">
                        <div class="flex items-center gap-3">
                            <div
                                :class="[
                                    'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
                                    openVpnStatus.server_cert_exists ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                                ]"
                            >
                                3
                            </div>
                            <div>
                                <p class="font-medium">Generate Server Certificate</p>
                                <p class="text-xs text-gray-500">Create server cert & key</p>
                            </div>
                        </div>
                        <button
                            @click="generateServer"
                            :disabled="loading.generateServer || !openVpnStatus.ca_cert_exists || openVpnStatus.server_cert_exists"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm',
                                openVpnStatus.server_cert_exists
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-blue-100 text-blue-700 hover:bg-blue-200',
                                { 'opacity-50 cursor-not-allowed': !openVpnStatus.ca_cert_exists }
                            ]"
                        >
                            {{ openVpnStatus.server_cert_exists ? 'Done' : (loading.generateServer ? 'Loading...' : 'Run') }}
                        </button>
                    </div>

                    <!-- Step 4: DH -->
                    <div class="flex items-center justify-between p-4 border rounded-lg">
                        <div class="flex items-center gap-3">
                            <div
                                :class="[
                                    'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
                                    openVpnStatus.dh_exists ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                                ]"
                            >
                                4
                            </div>
                            <div>
                                <p class="font-medium">Generate DH Parameters</p>
                                <p class="text-xs text-gray-500">Diffie-Hellman key exchange (slow)</p>
                            </div>
                        </div>
                        <button
                            @click="generateDh"
                            :disabled="loading.generateDh || openVpnStatus.dh_exists"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm',
                                openVpnStatus.dh_exists
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-blue-100 text-blue-700 hover:bg-blue-200'
                            ]"
                        >
                            {{ openVpnStatus.dh_exists ? 'Done' : (loading.generateDh ? 'Loading...' : 'Run') }}
                        </button>
                    </div>

                    <!-- Step 5: TA Key -->
                    <div class="flex items-center justify-between p-4 border rounded-lg">
                        <div class="flex items-center gap-3">
                            <div
                                :class="[
                                    'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
                                    openVpnStatus.ta_key_exists ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                                ]"
                            >
                                5
                            </div>
                            <div>
                                <p class="font-medium">Generate TLS Auth Key</p>
                                <p class="text-xs text-gray-500">Extra security layer</p>
                            </div>
                        </div>
                        <button
                            @click="generateTa"
                            :disabled="loading.generateTa || openVpnStatus.ta_key_exists"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm',
                                openVpnStatus.ta_key_exists
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-blue-100 text-blue-700 hover:bg-blue-200'
                            ]"
                        >
                            {{ openVpnStatus.ta_key_exists ? 'Done' : (loading.generateTa ? 'Loading...' : 'Run') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- WireGuard Setup -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">WireGuard Setup</h2>
                <p class="text-sm text-gray-600 mb-4">
                    WireGuard lebih cepat dan simple, tapi hanya untuk Mikrotik v7+.
                </p>

                <div class="space-y-4">
                    <!-- Server Keys -->
                    <div class="flex items-center justify-between p-4 border rounded-lg">
                        <div class="flex items-center gap-3">
                            <div
                                :class="[
                                    'w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
                                    wireGuardStatus.server_keys_exist ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                                ]"
                            >
                                1
                            </div>
                            <div>
                                <p class="font-medium">Generate Server Keys</p>
                                <p class="text-xs text-gray-500">Create WireGuard key pair</p>
                            </div>
                        </div>
                        <button
                            @click="generateWgKeys"
                            :disabled="loading.generateWgKeys || wireGuardStatus.server_keys_exist"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm',
                                wireGuardStatus.server_keys_exist
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-purple-100 text-purple-700 hover:bg-purple-200'
                            ]"
                        >
                            {{ wireGuardStatus.server_keys_exist ? 'Done' : (loading.generateWgKeys ? 'Loading...' : 'Run') }}
                        </button>
                    </div>

                    <!-- Server Public Key Display -->
                    <div v-if="settings.wg_public_key" class="p-4 bg-gray-50 rounded-lg">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Server Public Key</label>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                :value="settings.wg_public_key"
                                readonly
                                class="flex-1 px-4 py-2 bg-white border border-gray-300 rounded-lg font-mono text-sm"
                            >
                            <button
                                @click="navigator.clipboard.writeText(settings.wg_public_key)"
                                class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300"
                                title="Copy"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info & Tips -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Informasi</h2>

                <div class="space-y-4 text-sm">
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <h3 class="font-medium text-blue-700 mb-2">OpenVPN vs WireGuard</h3>
                        <ul class="space-y-1 text-blue-600">
                            <li><strong>OpenVPN:</strong> Compatible dengan Mikrotik v6 & v7</li>
                            <li><strong>WireGuard:</strong> Hanya Mikrotik v7+, tapi lebih cepat</li>
                        </ul>
                    </div>

                    <div class="p-4 bg-yellow-50 rounded-lg">
                        <h3 class="font-medium text-yellow-700 mb-2">Firewall</h3>
                        <p class="text-yellow-600">Pastikan port berikut terbuka di firewall VPS:</p>
                        <ul class="mt-2 space-y-1 text-yellow-600 font-mono">
                            <li>OpenVPN: {{ form.port }}/{{ form.protocol }}</li>
                            <li>WireGuard: {{ form.wg_port }}/udp</li>
                        </ul>
                    </div>

                    <div class="p-4 bg-green-50 rounded-lg">
                        <h3 class="font-medium text-green-700 mb-2">Sudoers Config</h3>
                        <p class="text-green-600 text-xs mb-2">
                            Tambahkan ke /etc/sudoers.d/vpn-billing:
                        </p>
                        <pre class="text-xs bg-white p-2 rounded overflow-x-auto">www-data ALL=(ALL) NOPASSWD: /usr/sbin/openvpn
www-data ALL=(ALL) NOPASSWD: /bin/systemctl * openvpn*
www-data ALL=(ALL) NOPASSWD: /etc/openvpn/easy-rsa/easyrsa
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg*
www-data ALL=(ALL) NOPASSWD: /usr/bin/tee /etc/openvpn/*
www-data ALL=(ALL) NOPASSWD: /usr/bin/tee /etc/wireguard/*</pre>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

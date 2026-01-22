<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    router: Object,
    configs: Object,
    protocols: Object,
    protocolDescriptions: Object,
    isRouterV7: Boolean,
})

const selectedProtocol = ref('l2tp')
const showScript = ref(false)

const currentConfig = computed(() => props.configs[selectedProtocol.value] || {})

const form = useForm({
    enabled: currentConfig.value.enabled || false,
    settings: { ...currentConfig.value.settings },
})

const updateForm = () => {
    const config = props.configs[selectedProtocol.value] || {}
    form.enabled = config.enabled || false
    form.settings = { ...config.settings }
}

const generateScript = () => {
    router.post(`/admin/routers/${props.router.id}/vpn/${selectedProtocol.value}/generate`, {
        enabled: form.enabled,
        settings: form.settings,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showScript.value = true
        },
    })
}

const copyScript = () => {
    const script = props.configs[selectedProtocol.value]?.generated_script
    if (script) {
        navigator.clipboard.writeText(script)
        alert('Script disalin ke clipboard')
    }
}

const downloadScript = () => {
    window.location.href = `/admin/routers/${props.router.id}/vpn/${selectedProtocol.value}/download`
}

// Update form when protocol changes
const selectProtocol = (protocol) => {
    selectedProtocol.value = protocol
    updateForm()
    showScript.value = false
}
</script>

<template>
    <Head :title="`VPN Config - ${router.name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="`/admin/routers/${router.id}`" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">VPN Configuration</h1>
                    <p class="text-sm text-gray-500">{{ router.name }} ({{ router.ip_address }})</p>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Protocol Selector -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <h3 class="font-semibold mb-4">Protocol</h3>
                    <div class="space-y-2">
                        <button
                            v-for="(label, protocol) in protocols"
                            :key="protocol"
                            @click="selectProtocol(protocol)"
                            :disabled="protocol === 'wireguard' && !isRouterV7"
                            :class="[
                                'w-full text-left px-4 py-3 rounded-lg transition-colors',
                                selectedProtocol === protocol
                                    ? 'bg-blue-50 text-blue-700 border border-blue-200'
                                    : 'hover:bg-gray-50 border border-transparent',
                                protocol === 'wireguard' && !isRouterV7 ? 'opacity-50 cursor-not-allowed' : ''
                            ]"
                        >
                            <p class="font-medium">{{ label }}</p>
                            <p class="text-xs text-gray-500">
                                {{ configs[protocol]?.generated_script ? 'Script tersedia' : 'Belum dikonfigurasi' }}
                            </p>
                        </button>
                    </div>

                    <div v-if="!isRouterV7" class="mt-4 p-3 bg-yellow-50 rounded-lg">
                        <p class="text-xs text-yellow-800">
                            WireGuard memerlukan RouterOS v7+. Router ini menggunakan {{ router.version || 'versi tidak diketahui' }}.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Configuration Form -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Protocol Info -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-2">{{ protocols[selectedProtocol] }}</h2>
                    <p class="text-gray-600 text-sm">{{ protocolDescriptions[selectedProtocol] }}</p>
                </div>

                <!-- Settings -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="font-semibold mb-4">Pengaturan</h3>

                    <!-- L2TP Settings -->
                    <div v-if="selectedProtocol === 'l2tp'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Local Address</label>
                                <input
                                    v-model="form.settings.local_address"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    placeholder="10.255.255.1"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pool Name</label>
                                <input
                                    v-model="form.settings.remote_address_pool"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    placeholder="vpn-pool"
                                >
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pool Start</label>
                                <input
                                    v-model="form.settings.pool_start"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    placeholder="10.255.255.10"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pool End</label>
                                <input
                                    v-model="form.settings.pool_end"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    placeholder="10.255.255.250"
                                >
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">DNS Server</label>
                                <input
                                    v-model="form.settings.dns_server"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    placeholder="8.8.8.8"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">IPSec Secret</label>
                                <input
                                    v-model="form.settings.ipsec_secret"
                                    type="password"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    placeholder="Enter secret"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- PPTP Settings -->
                    <div v-else-if="selectedProtocol === 'pptp'" class="space-y-4">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                            <p class="text-yellow-800 text-sm">
                                PPTP dianggap tidak aman. Gunakan L2TP/IPSec atau WireGuard jika memungkinkan.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Local Address</label>
                                <input
                                    v-model="form.settings.local_address"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pool Name</label>
                                <input
                                    v-model="form.settings.remote_address_pool"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                >
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pool Start</label>
                                <input v-model="form.settings.pool_start" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pool End</label>
                                <input v-model="form.settings.pool_end" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">DNS Server</label>
                                <input v-model="form.settings.dns_server" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                    </div>

                    <!-- SSTP Settings -->
                    <div v-else-if="selectedProtocol === 'sstp'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Local Address</label>
                                <input v-model="form.settings.local_address" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Certificate Name</label>
                                <input v-model="form.settings.certificate" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="ssl-cert">
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pool Start</label>
                                <input v-model="form.settings.pool_start" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pool End</label>
                                <input v-model="form.settings.pool_end" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">DNS Server</label>
                                <input v-model="form.settings.dns_server" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                    </div>

                    <!-- WireGuard Settings -->
                    <div v-else-if="selectedProtocol === 'wireguard'" class="space-y-4">
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Listen Port</label>
                                <input v-model="form.settings.listen_port" type="number" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">MTU</label>
                                <input v-model="form.settings.mtu" type="number" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Interface Address</label>
                                <input v-model="form.settings.interface_address" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="10.252.252.1/24">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-4">
                        <button
                            @click="generateScript"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                        >
                            Generate Script
                        </button>
                    </div>
                </div>

                <!-- Generated Script -->
                <div v-if="currentConfig.generated_script" class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold">Generated Script</h3>
                        <div class="flex gap-2">
                            <button
                                @click="copyScript"
                                class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                Copy
                            </button>
                            <button
                                @click="downloadScript"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Download .rsc
                            </button>
                        </div>
                    </div>
                    <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm font-mono">{{ currentConfig.generated_script }}</pre>
                    <p class="text-xs text-gray-500 mt-2">
                        Last generated: {{ currentConfig.last_generated_at ? new Date(currentConfig.last_generated_at).toLocaleString('id-ID') : '-' }}
                    </p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

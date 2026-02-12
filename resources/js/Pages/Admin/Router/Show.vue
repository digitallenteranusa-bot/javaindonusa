<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    router: Object,
    routerInfo: Object,
    connectionError: String,
})

// State
const activeTab = ref('interfaces')
const autoRefresh = ref(true)
const loading = ref(false)
const error = ref(props.connectionError || null)

// Data
const resources = ref(props.routerInfo || null)
const interfaces = ref([])
const queues = ref([])
const activeConnections = ref([])

// For rate calculation
const prevInterfaces = ref(null)
const prevTimestamp = ref(null)
const interfaceRates = ref({})

let pollInterval = null

// Fetch helpers
const fetchJson = async (url) => {
    const res = await fetch(url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    return res.json()
}

const fetchResources = async () => {
    const res = await fetchJson(`/admin/routers/${props.router.id}/api/resources`)
    if (res.success) {
        resources.value = res.data
        error.value = null
    } else {
        error.value = res.error
    }
}

const fetchInterfaces = async () => {
    const res = await fetchJson(`/admin/routers/${props.router.id}/api/interfaces`)
    if (res.success) {
        // Calculate rates from delta
        if (prevInterfaces.value && prevTimestamp.value) {
            const dt = res.timestamp - prevTimestamp.value
            if (dt > 0) {
                const rates = {}
                for (const iface of res.data) {
                    const name = iface.name
                    const prev = prevInterfaces.value.find(p => p.name === name)
                    if (prev) {
                        const txDelta = parseInt(iface['tx-byte'] || 0) - parseInt(prev['tx-byte'] || 0)
                        const rxDelta = parseInt(iface['rx-byte'] || 0) - parseInt(prev['rx-byte'] || 0)
                        rates[name] = {
                            tx: Math.max(0, txDelta / dt),
                            rx: Math.max(0, rxDelta / dt),
                        }
                    }
                }
                interfaceRates.value = rates
            }
        }
        prevInterfaces.value = res.data
        prevTimestamp.value = res.timestamp
        interfaces.value = res.data
        error.value = null
    } else {
        error.value = res.error
    }
}

const fetchQueues = async () => {
    const res = await fetchJson(`/admin/routers/${props.router.id}/api/queues`)
    if (res.success) {
        queues.value = res.data
        error.value = null
    } else {
        error.value = res.error
    }
}

const fetchActiveConnections = async () => {
    const res = await fetchJson(`/admin/routers/${props.router.id}/api/active-connections`)
    if (res.success) {
        activeConnections.value = res.data
        error.value = null
    } else {
        error.value = res.error
    }
}

const refreshAll = async () => {
    loading.value = true
    try {
        await fetchResources()

        if (activeTab.value === 'interfaces') {
            await fetchInterfaces()
        } else if (activeTab.value === 'queues') {
            await fetchQueues()
        } else if (activeTab.value === 'pppoe') {
            await fetchActiveConnections()
        }
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

const startPolling = () => {
    stopPolling()
    pollInterval = setInterval(() => {
        if (autoRefresh.value) {
            refreshAll()
        }
    }, 5000)
}

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval)
        pollInterval = null
    }
}

const toggleAutoRefresh = () => {
    autoRefresh.value = !autoRefresh.value
    if (autoRefresh.value) {
        startPolling()
    } else {
        stopPolling()
    }
}

// Format helpers
const formatBytes = (bytes) => {
    if (!bytes || bytes === 0) return '0 B'
    bytes = parseInt(bytes)
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    const i = Math.floor(Math.log(bytes) / Math.log(1024))
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i]
}

const formatRate = (bytesPerSec) => {
    if (!bytesPerSec || bytesPerSec === 0) return '0 bps'
    const bps = bytesPerSec * 8
    if (bps >= 1000000000) return (bps / 1000000000).toFixed(1) + ' Gbps'
    if (bps >= 1000000) return (bps / 1000000).toFixed(1) + ' Mbps'
    if (bps >= 1000) return (bps / 1000).toFixed(1) + ' Kbps'
    return bps.toFixed(0) + ' bps'
}

const memoryPercent = computed(() => {
    if (!resources.value) return 0
    const total = parseInt(resources.value.total_memory || 0)
    const free = parseInt(resources.value.free_memory || 0)
    if (total === 0) return 0
    return Math.round(((total - free) / total) * 100)
})

const cpuPercent = computed(() => {
    return parseInt(resources.value?.cpu_load || 0)
})

const cpuColor = computed(() => {
    const v = cpuPercent.value
    if (v >= 80) return 'bg-red-500'
    if (v >= 50) return 'bg-yellow-500'
    return 'bg-blue-500'
})

const memColor = computed(() => {
    const v = memoryPercent.value
    if (v >= 80) return 'bg-red-500'
    if (v >= 60) return 'bg-yellow-500'
    return 'bg-green-500'
})

onMounted(() => {
    // Initial fetch for active tab
    refreshAll()
    startPolling()
})

onUnmounted(() => {
    stopPolling()
})
</script>

<template>
    <Head :title="`Monitor - ${router.name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        href="/admin/routers"
                        class="p-2 rounded-lg hover:bg-gray-100 text-gray-500"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ router.name }}</h1>
                        <p class="text-sm text-gray-500">{{ router.ip_address }}:{{ router.api_port }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        @click="toggleAutoRefresh"
                        :class="[
                            'px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2',
                            autoRefresh
                                ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                        ]"
                    >
                        <svg class="w-4 h-4" :class="{ 'animate-spin': autoRefresh && loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Otomatis (5d)
                    </button>
                    <button
                        @click="refreshAll"
                        :disabled="loading"
                        class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium disabled:opacity-50"
                    >
                        Segarkan
                    </button>
                </div>
            </div>
        </template>

        <!-- Error Banner -->
        <div v-if="error" class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <div>
                <p class="font-medium text-red-800">Gagal terhubung ke router</p>
                <p class="text-sm text-red-600">{{ error }}</p>
            </div>
        </div>

        <!-- Router Info Card -->
        <div v-if="resources" class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Identity</p>
                    <p class="font-semibold">{{ resources.identity }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Version</p>
                    <p class="font-semibold">{{ resources.version }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Model</p>
                    <p class="font-semibold">{{ resources.model }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Arsitektur</p>
                    <p class="font-semibold">{{ resources.architecture }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Uptime</p>
                    <p class="font-semibold">{{ resources.uptime }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Pelanggan</p>
                    <p class="font-semibold">{{ router.customers_count || 0 }}</p>
                </div>
            </div>

            <!-- CPU & Memory Bars -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">Beban CPU</span>
                        <span class="font-medium">{{ cpuPercent }}%</span>
                    </div>
                    <div class="h-3 bg-gray-200 rounded-full overflow-hidden">
                        <div
                            :class="['h-full transition-all duration-500', cpuColor]"
                            :style="{ width: cpuPercent + '%' }"
                        ></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">Memori</span>
                        <span class="font-medium">{{ memoryPercent }}% ({{ formatBytes((resources.total_memory || 0) - (resources.free_memory || 0)) }} / {{ formatBytes(resources.total_memory || 0) }})</span>
                    </div>
                    <div class="h-3 bg-gray-200 rounded-full overflow-hidden">
                        <div
                            :class="['h-full transition-all duration-500', memColor]"
                            :style="{ width: memoryPercent + '%' }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="border-b flex">
                <button
                    @click="activeTab = 'interfaces'; refreshAll()"
                    :class="[
                        'px-6 py-3 text-sm font-medium border-b-2 -mb-px transition-colors',
                        activeTab === 'interfaces'
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'
                    ]"
                >
                    Interfaces
                    <span v-if="interfaces.length" class="ml-1 text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full">{{ interfaces.length }}</span>
                </button>
                <button
                    @click="activeTab = 'queues'; refreshAll()"
                    :class="[
                        'px-6 py-3 text-sm font-medium border-b-2 -mb-px transition-colors',
                        activeTab === 'queues'
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'
                    ]"
                >
                    Antrian
                    <span v-if="queues.length" class="ml-1 text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full">{{ queues.length }}</span>
                </button>
                <button
                    @click="activeTab = 'pppoe'; refreshAll()"
                    :class="[
                        'px-6 py-3 text-sm font-medium border-b-2 -mb-px transition-colors',
                        activeTab === 'pppoe'
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'
                    ]"
                >
                    PPPoE Aktif
                    <span v-if="activeConnections.length" class="ml-1 text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">{{ activeConnections.length }}</span>
                </button>
            </div>

            <!-- Interfaces Tab -->
            <div v-if="activeTab === 'interfaces'" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Nama</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Tipe</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">TX Rate</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">RX Rate</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">TX Total</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">RX Total</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">MAC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="iface in interfaces" :key="iface.name" class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium">
                                {{ iface.name }}
                                <p v-if="iface.comment" class="text-xs text-gray-400">{{ iface.comment }}</p>
                            </td>
                            <td class="px-4 py-2 text-gray-500">{{ iface.type }}</td>
                            <td class="px-4 py-2 text-center">
                                <span
                                    :class="[
                                        'inline-block w-2.5 h-2.5 rounded-full',
                                        iface.running === 'true' ? 'bg-green-500' : (iface.disabled === 'true' ? 'bg-gray-400' : 'bg-red-500')
                                    ]"
                                    :title="iface.running === 'true' ? 'Berjalan' : (iface.disabled === 'true' ? 'Nonaktif' : 'Mati')"
                                ></span>
                            </td>
                            <td class="px-4 py-2 text-right font-mono text-xs">
                                <span class="text-blue-600">{{ formatRate(interfaceRates[iface.name]?.tx || 0) }}</span>
                            </td>
                            <td class="px-4 py-2 text-right font-mono text-xs">
                                <span class="text-green-600">{{ formatRate(interfaceRates[iface.name]?.rx || 0) }}</span>
                            </td>
                            <td class="px-4 py-2 text-right font-mono text-xs text-gray-500">{{ formatBytes(iface['tx-byte']) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-xs text-gray-500">{{ formatBytes(iface['rx-byte']) }}</td>
                            <td class="px-4 py-2 font-mono text-xs text-gray-400">{{ iface['mac-address'] || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!interfaces.length && !loading" class="p-8 text-center text-gray-400">
                    Tidak ada data interface
                </div>
            </div>

            <!-- Queues Tab -->
            <div v-if="activeTab === 'queues'" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">#</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Nama</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Target</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Batas Maks (Up/Down)</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Burst Limit</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Burst Threshold</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-500">Nonaktif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(q, idx) in queues" :key="q['.id']" class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-400">{{ idx + 1 }}</td>
                            <td class="px-4 py-2 font-medium">
                                {{ q.name }}
                                <p v-if="q.comment" class="text-xs text-gray-400">{{ q.comment }}</p>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs">{{ q.target }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ q['max-limit'] || '-' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ q['burst-limit'] || '-' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ q['burst-threshold'] || '-' }}</td>
                            <td class="px-4 py-2 text-center">
                                <span
                                    :class="[
                                        'px-2 py-0.5 text-xs rounded-full',
                                        q.disabled === 'true' ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'
                                    ]"
                                >
                                    {{ q.disabled === 'true' ? 'Ya' : 'Tidak' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!queues.length && !loading" class="p-8 text-center text-gray-400">
                    Tidak ada simple queue
                </div>
            </div>

            <!-- PPPoE Active Tab -->
            <div v-if="activeTab === 'pppoe'" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">#</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Username</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Layanan</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Caller ID</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Alamat</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Uptime</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(conn, idx) in activeConnections" :key="conn['.id']" class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-400">{{ idx + 1 }}</td>
                            <td class="px-4 py-2 font-medium">{{ conn.name }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ conn.service }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ conn['caller-id'] || '-' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ conn.address || '-' }}</td>
                            <td class="px-4 py-2">{{ conn.uptime || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!activeConnections.length && !loading" class="p-8 text-center text-gray-400">
                    Tidak ada koneksi PPPoE aktif
                </div>
            </div>

            <!-- Loading Overlay -->
            <div v-if="loading && !resources" class="p-12 text-center text-gray-400">
                <svg class="animate-spin h-8 w-8 mx-auto text-blue-500 mb-3" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <p>Menghubungkan ke router...</p>
            </div>
        </div>
    </AdminLayout>
</template>

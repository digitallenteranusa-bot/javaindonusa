<script setup>
import { ref, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    overview: Object,
    systemInfo: Object,
    cpuUsage: Object,
    memoryUsage: Object,
    diskUsage: Array,
    networkStats: Array,
    activeConnections: Object,
    serviceStatuses: Array,
    topProcesses: Array,
    alerts: Array,
    lastUpdated: String,
})

// Reactive data for auto-refresh
const overview = ref(props.overview)
const systemInfo = ref(props.systemInfo)
const cpuUsage = ref(props.cpuUsage)
const memoryUsage = ref(props.memoryUsage)
const diskUsage = ref(props.diskUsage)
const networkStats = ref(props.networkStats)
const activeConnections = ref(props.activeConnections)
const serviceStatuses = ref(props.serviceStatuses)
const topProcesses = ref(props.topProcesses)
const alerts = ref(props.alerts)
const lastUpdated = ref(props.lastUpdated)

// Auto-refresh
const autoRefresh = ref(false)
const refreshing = ref(false)
let refreshInterval = null

const toggleAutoRefresh = () => {
    autoRefresh.value = !autoRefresh.value
    if (autoRefresh.value) {
        startAutoRefresh()
    } else {
        stopAutoRefresh()
    }
}

const startAutoRefresh = () => {
    stopAutoRefresh()
    refreshInterval = setInterval(fetchData, 30000)
}

const stopAutoRefresh = () => {
    if (refreshInterval) {
        clearInterval(refreshInterval)
        refreshInterval = null
    }
}

const fetchData = async () => {
    if (refreshing.value) return
    refreshing.value = true
    try {
        const response = await fetch('/admin/analytics/vps/refresh')
        const data = await response.json()
        overview.value = data.overview
        systemInfo.value = data.systemInfo
        cpuUsage.value = data.cpuUsage
        memoryUsage.value = data.memoryUsage
        diskUsage.value = data.diskUsage
        networkStats.value = data.networkStats
        activeConnections.value = data.activeConnections
        serviceStatuses.value = data.serviceStatuses
        topProcesses.value = data.topProcesses
        alerts.value = data.alerts
        lastUpdated.value = data.lastUpdated
    } catch (e) {
        console.error('Failed to refresh VPS data:', e)
    } finally {
        refreshing.value = false
    }
}

onUnmounted(() => {
    stopAutoRefresh()
})

// Helpers
const formatBytes = (bytes) => {
    if (!bytes || bytes === 0) return '0 B'
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    const i = Math.floor(Math.log(bytes) / Math.log(1024))
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i]
}

const getGaugeColor = (value) => {
    if (value > 80) return 'bg-red-500'
    if (value > 60) return 'bg-yellow-500'
    return 'bg-green-500'
}

const getGaugeTextColor = (value) => {
    if (value > 80) return 'text-red-600'
    if (value > 60) return 'text-yellow-600'
    return 'text-green-600'
}

const getServiceIcon = (name) => {
    const icons = {
        'nginx': 'N',
        'mysql': 'M',
        'redis-server': 'R',
        'supervisor': 'S',
        'queue-worker': 'Q',
    }
    if (name.startsWith('php')) return 'P'
    return icons[name] || name.charAt(0).toUpperCase()
}
</script>

<template>
    <Head title="Kesehatan VPS" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Kesehatan VPS</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ systemInfo?.hostname || 'Server' }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500">
                        Update: {{ lastUpdated }}
                    </span>
                    <button
                        @click="toggleAutoRefresh"
                        :class="[
                            'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                            autoRefresh
                                ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                        ]"
                    >
                        <span class="relative flex h-2 w-2">
                            <span v-if="autoRefresh" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span :class="['relative inline-flex rounded-full h-2 w-2', autoRefresh ? 'bg-green-500' : 'bg-gray-400']"></span>
                        </span>
                        Auto-refresh {{ autoRefresh ? 'ON' : 'OFF' }}
                    </button>
                    <button
                        @click="fetchData"
                        :disabled="refreshing"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors"
                    >
                        <svg :class="['w-4 h-4', refreshing && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
        </template>

        <!-- Section 1: Overview Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">CPU Usage</p>
                <p class="text-2xl font-bold mt-1" :class="getGaugeTextColor(overview?.cpu_percent || 0)">{{ overview?.cpu_percent || 0 }}%</p>
                <p class="text-xs text-gray-400 mt-1">{{ cpuUsage?.cores || 0 }} core(s)</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">RAM Usage</p>
                <p class="text-2xl font-bold mt-1" :class="getGaugeTextColor(overview?.ram_percent || 0)">{{ overview?.ram_percent || 0 }}%</p>
                <p class="text-xs text-gray-400 mt-1">{{ formatBytes(memoryUsage?.used || 0) }} / {{ formatBytes(memoryUsage?.total || 0) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Disk Usage</p>
                <p class="text-2xl font-bold mt-1" :class="getGaugeTextColor(overview?.disk_percent || 0)">{{ overview?.disk_percent || 0 }}%</p>
                <p class="text-xs text-gray-400 mt-1">partisi tertinggi</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Load Average</p>
                <p class="text-2xl font-bold mt-1 text-gray-800">{{ overview?.load_average || 'N/A' }}</p>
                <p class="text-xs text-gray-400 mt-1">1, 5, 15 menit</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Uptime</p>
                <p class="text-lg font-bold mt-1 text-blue-600">{{ overview?.uptime || 'N/A' }}</p>
                <p class="text-xs text-gray-400 mt-1">waktu aktif</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Total Alert</p>
                <p class="text-2xl font-bold mt-1" :class="(overview?.alert_count || 0) > 0 ? 'text-red-600' : 'text-green-600'">{{ overview?.alert_count || 0 }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ (overview?.alert_count || 0) === 0 ? 'semua normal' : 'perlu ditangani' }}</p>
            </div>
        </div>

        <!-- Section 2: System Info -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Informasi Sistem</h2>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Hostname</p>
                        <p class="text-sm font-medium text-gray-900 mt-1">{{ systemInfo?.hostname || 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">OS</p>
                        <p class="text-sm font-medium text-gray-900 mt-1">{{ systemInfo?.os || 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Kernel</p>
                        <p class="text-sm font-medium text-gray-900 mt-1 font-mono text-xs">{{ systemInfo?.kernel || 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">PHP Version</p>
                        <p class="text-sm font-medium text-gray-900 mt-1">{{ systemInfo?.php_version || 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Laravel Version</p>
                        <p class="text-sm font-medium text-gray-900 mt-1">{{ systemInfo?.laravel_version || 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Resource Gauges -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- CPU Gauge -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">CPU</h2>
                </div>
                <div class="p-6">
                    <div class="text-center mb-4">
                        <span class="text-4xl font-bold" :class="getGaugeTextColor(cpuUsage?.usage_percent || 0)">
                            {{ cpuUsage?.usage_percent || 0 }}%
                        </span>
                        <p class="text-sm text-gray-500 mt-1">{{ cpuUsage?.cores || 0 }} CPU Core(s)</p>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4">
                        <div
                            :class="['h-4 rounded-full transition-all duration-500', getGaugeColor(cpuUsage?.usage_percent || 0)]"
                            :style="{ width: (cpuUsage?.usage_percent || 0) + '%' }"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- RAM Gauge -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">RAM</h2>
                </div>
                <div class="p-6">
                    <div class="text-center mb-4">
                        <span class="text-4xl font-bold" :class="getGaugeTextColor(memoryUsage?.used_percent || 0)">
                            {{ memoryUsage?.used_percent || 0 }}%
                        </span>
                        <p class="text-sm text-gray-500 mt-1">{{ formatBytes(memoryUsage?.used || 0) }} / {{ formatBytes(memoryUsage?.total || 0) }}</p>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4 mb-3">
                        <div
                            :class="['h-4 rounded-full transition-all duration-500', getGaugeColor(memoryUsage?.used_percent || 0)]"
                            :style="{ width: (memoryUsage?.used_percent || 0) + '%' }"
                        ></div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-500">
                        <div>Cached: <span class="font-medium text-gray-700">{{ formatBytes(memoryUsage?.cached || 0) }}</span></div>
                        <div>Free: <span class="font-medium text-gray-700">{{ formatBytes(memoryUsage?.free || 0) }}</span></div>
                        <div>Swap: <span class="font-medium text-gray-700">{{ formatBytes(memoryUsage?.swap_used || 0) }} / {{ formatBytes(memoryUsage?.swap_total || 0) }}</span></div>
                        <div>Swap Usage: <span class="font-medium" :class="(memoryUsage?.swap_percent || 0) > 50 ? 'text-yellow-600' : 'text-gray-700'">{{ memoryUsage?.swap_percent || 0 }}%</span></div>
                    </div>
                </div>
            </div>

            <!-- Disk Gauge -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Disk</h2>
                </div>
                <div class="p-6">
                    <div v-if="diskUsage && diskUsage.length > 0" class="space-y-4">
                        <div v-for="(disk, idx) in diskUsage" :key="idx">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700 font-mono">{{ disk.mount }}</span>
                                <span class="text-sm font-bold" :class="getGaugeTextColor(disk.use_percent)">{{ disk.use_percent }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div
                                    :class="['h-3 rounded-full transition-all duration-500', getGaugeColor(disk.use_percent)]"
                                    :style="{ width: disk.use_percent + '%' }"
                                ></div>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">{{ formatBytes(disk.used) }} / {{ formatBytes(disk.size) }} (tersedia: {{ formatBytes(disk.available) }})</p>
                        </div>
                    </div>
                    <div v-else class="text-center text-gray-400 py-8">
                        <p>Data disk tidak tersedia.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Network Stats -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Network Statistics</h2>
                <div v-if="activeConnections" class="flex items-center gap-4 text-xs text-gray-500">
                    <span>TCP Total: <strong class="text-gray-700">{{ activeConnections.tcp_total || 0 }}</strong></span>
                    <span>Established: <strong class="text-green-600">{{ activeConnections.established || 0 }}</strong></span>
                    <span>Time Wait: <strong class="text-yellow-600">{{ activeConnections.time_wait || 0 }}</strong></span>
                    <span>Close Wait: <strong class="text-red-600">{{ activeConnections.close_wait || 0 }}</strong></span>
                </div>
            </div>
            <div v-if="networkStats && networkStats.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Interface</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">RX Bytes</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">TX Bytes</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">RX Packets</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">TX Packets</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">RX Errors</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">TX Errors</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="iface in networkStats" :key="iface.name" class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-medium text-gray-900 font-mono">{{ iface.name }}</td>
                            <td class="px-6 py-3 text-right text-gray-600">{{ formatBytes(iface.rx_bytes) }}</td>
                            <td class="px-6 py-3 text-right text-gray-600">{{ formatBytes(iface.tx_bytes) }}</td>
                            <td class="px-6 py-3 text-right text-gray-500">{{ iface.rx_packets?.toLocaleString() }}</td>
                            <td class="px-6 py-3 text-right text-gray-500">{{ iface.tx_packets?.toLocaleString() }}</td>
                            <td class="px-6 py-3 text-right" :class="iface.rx_errors > 0 ? 'text-red-600 font-medium' : 'text-gray-400'">{{ iface.rx_errors }}</td>
                            <td class="px-6 py-3 text-right" :class="iface.tx_errors > 0 ? 'text-red-600 font-medium' : 'text-gray-400'">{{ iface.tx_errors }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="p-12 text-center text-gray-500">
                <p>Data network tidak tersedia.</p>
            </div>
        </div>

        <!-- Section 5: Service Status -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Status Service</h2>
            </div>
            <div class="p-6">
                <div v-if="serviceStatuses && serviceStatuses.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div
                        v-for="svc in serviceStatuses"
                        :key="svc.name"
                        :class="[
                            'flex items-center gap-3 p-4 rounded-lg border-2 transition-colors',
                            svc.is_active
                                ? 'border-green-200 bg-green-50'
                                : svc.status === 'unknown'
                                    ? 'border-gray-200 bg-gray-50'
                                    : 'border-red-200 bg-red-50'
                        ]"
                    >
                        <div :class="[
                            'flex items-center justify-center w-10 h-10 rounded-lg text-white font-bold text-lg',
                            svc.is_active ? 'bg-green-500' : svc.status === 'unknown' ? 'bg-gray-400' : 'bg-red-500'
                        ]">
                            {{ getServiceIcon(svc.name) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ svc.name }}</p>
                            <span :class="[
                                'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mt-0.5',
                                svc.is_active
                                    ? 'bg-green-100 text-green-700'
                                    : svc.status === 'unknown'
                                        ? 'bg-gray-100 text-gray-600'
                                        : 'bg-red-100 text-red-700'
                            ]">
                                {{ svc.is_active ? 'Active' : svc.status === 'unknown' ? 'Unknown' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center text-gray-400 py-8">
                    <p>Data service tidak tersedia.</p>
                </div>
            </div>
        </div>

        <!-- Section 6: Top Processes -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Top Processes (by CPU)</h2>
            </div>
            <div v-if="topProcesses && topProcesses.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">CPU %</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">MEM %</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Command</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="proc in topProcesses" :key="proc.pid" class="hover:bg-gray-50">
                            <td class="px-6 py-3 text-gray-500 font-mono text-xs">{{ proc.pid }}</td>
                            <td class="px-6 py-3 text-gray-700">{{ proc.user }}</td>
                            <td class="px-6 py-3 text-right">
                                <span :class="['font-medium', proc.cpu > 50 ? 'text-red-600' : proc.cpu > 20 ? 'text-yellow-600' : 'text-gray-700']">
                                    {{ proc.cpu }}%
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <span :class="['font-medium', proc.mem > 50 ? 'text-red-600' : proc.mem > 20 ? 'text-yellow-600' : 'text-gray-700']">
                                    {{ proc.mem }}%
                                </span>
                            </td>
                            <td class="px-6 py-3 text-gray-500 font-mono text-xs truncate max-w-md">{{ proc.command }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="p-12 text-center text-gray-500">
                <p>Data proses tidak tersedia.</p>
            </div>
        </div>

        <!-- Section 7: Alerts -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">VPS Alerts</h2>
                <span v-if="alerts && alerts.length > 0" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                    {{ alerts.length }} alert{{ alerts.length > 1 ? 's' : '' }}
                </span>
            </div>
            <div v-if="alerts && alerts.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Judul</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pesan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(alert, idx) in alerts" :key="idx" class="hover:bg-gray-50">
                            <td class="px-6 py-3">
                                <span :class="[
                                    'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                    alert.type === 'danger' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'
                                ]">
                                    {{ alert.type === 'danger' ? 'Kritis' : 'Peringatan' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-gray-500">{{ alert.category }}</td>
                            <td class="px-6 py-3 font-medium text-gray-900">{{ alert.title }}</td>
                            <td class="px-6 py-3 text-gray-500 text-xs">{{ alert.message }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="p-12 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 mb-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <p class="text-gray-500">Tidak ada alert. Semua sistem berjalan normal.</p>
            </div>
        </div>
    </AdminLayout>
</template>

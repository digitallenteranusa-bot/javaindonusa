<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Bar, Doughnut } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js'

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
)

const props = defineProps({
    overview: Object,
    routerHealth: Array,
    deviceHealth: Object,
    signalDistribution: Array,
    alerts: Array,
    routerCpuSnapshot: Array,
    connectionStats: Array,
    lastUpdated: String,
})

// Reactive data for auto-refresh
const overview = ref(props.overview)
const routerHealth = ref(props.routerHealth)
const deviceHealth = ref(props.deviceHealth)
const signalDistribution = ref(props.signalDistribution)
const alerts = ref(props.alerts)
const routerCpuSnapshot = ref(props.routerCpuSnapshot)
const connectionStats = ref(props.connectionStats)
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
        const response = await fetch('/admin/analytics/network/refresh')
        const data = await response.json()
        overview.value = data.overview
        routerHealth.value = data.routerHealth
        deviceHealth.value = data.deviceHealth
        signalDistribution.value = data.signalDistribution
        alerts.value = data.alerts
        routerCpuSnapshot.value = data.routerCpuSnapshot
        connectionStats.value = data.connectionStats
        lastUpdated.value = data.lastUpdated
    } catch (e) {
        console.error('Failed to refresh network data:', e)
    } finally {
        refreshing.value = false
    }
}

onUnmounted(() => {
    stopAutoRefresh()
})

// CPU bar chart
const cpuChartData = computed(() => ({
    labels: (routerCpuSnapshot.value || []).map(r => r.name),
    datasets: [{
        label: 'CPU Load (%)',
        data: (routerCpuSnapshot.value || []).map(r => r.cpu_load),
        backgroundColor: (routerCpuSnapshot.value || []).map(r =>
            r.cpu_load > 80 ? '#EF4444' : r.cpu_load > 50 ? '#F59E0B' : '#10B981'
        ),
        borderRadius: 4,
    }],
}))

const cpuChartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { min: 0, max: 100, ticks: { callback: v => v + '%' } },
    },
}

// Memory bar chart
const memoryChartData = computed(() => ({
    labels: (routerCpuSnapshot.value || []).map(r => r.name),
    datasets: [{
        label: 'Memory Usage (%)',
        data: (routerCpuSnapshot.value || []).map(r => r.memory_usage),
        backgroundColor: (routerCpuSnapshot.value || []).map(r =>
            r.memory_usage > 80 ? '#EF4444' : r.memory_usage > 50 ? '#F59E0B' : '#3B82F6'
        ),
        borderRadius: 4,
    }],
}))

const memoryChartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { min: 0, max: 100, ticks: { callback: v => v + '%' } },
    },
}

// Device online/offline doughnut
const deviceDoughnutData = computed(() => ({
    labels: ['Online', 'Offline'],
    datasets: [{
        data: [deviceHealth.value?.online || 0, deviceHealth.value?.offline || 0],
        backgroundColor: ['#10B981', '#EF4444'],
        borderWidth: 0,
    }],
}))

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
    },
}

// Signal distribution doughnut
const signalDoughnutData = computed(() => ({
    labels: (signalDistribution.value || []).map(s => s.label),
    datasets: [{
        data: (signalDistribution.value || []).map(s => s.value),
        backgroundColor: (signalDistribution.value || []).map(s => s.color),
        borderWidth: 0,
    }],
}))

// Manufacturer bar chart
const manufacturerChartData = computed(() => {
    const data = deviceHealth.value?.by_manufacturer || []
    return {
        labels: data.map(d => d.manufacturer),
        datasets: [
            {
                label: 'Online',
                data: data.map(d => d.online),
                backgroundColor: '#10B981',
                borderRadius: 4,
            },
            {
                label: 'Offline',
                data: data.map(d => d.offline),
                backgroundColor: '#EF4444',
                borderRadius: 4,
            },
        ],
    }
})

const manufacturerChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
    scales: {
        x: { stacked: false },
        y: { beginAtZero: true },
    },
}

// Connection stats bar chart
const connectionChartData = computed(() => {
    const data = connectionStats.value || []
    return {
        labels: data.map(d => d.type),
        datasets: [
            {
                label: 'Aktif',
                data: data.map(d => d.active),
                backgroundColor: '#3B82F6',
                borderRadius: 4,
            },
            {
                label: 'Isolir',
                data: data.map(d => d.isolated),
                backgroundColor: '#EF4444',
                borderRadius: 4,
            },
        ],
    }
})

const connectionChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
    scales: { y: { beginAtZero: true } },
}

// Helpers
const getStatusColor = (value) => {
    if (value > 80) return 'bg-red-500'
    if (value > 50) return 'bg-yellow-500'
    return 'bg-green-500'
}

const getStatusTextColor = (value) => {
    if (value > 80) return 'text-red-600'
    if (value > 50) return 'text-yellow-600'
    return 'text-green-600'
}
</script>

<template>
    <Head title="Kesehatan Jaringan" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Kesehatan Jaringan</h1>
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
                <p class="text-gray-500 text-xs uppercase tracking-wide">Router Online</p>
                <p class="text-2xl font-bold mt-1 text-green-600">{{ overview?.router_online || 0 }}</p>
                <p class="text-xs text-gray-400 mt-1">dari {{ overview?.router_total || 0 }} router</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Device Online</p>
                <p class="text-2xl font-bold mt-1 text-blue-600">{{ overview?.device_online || 0 }}</p>
                <p class="text-xs text-gray-400 mt-1">dari {{ overview?.device_total || 0 }} device</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Sinyal Lemah</p>
                <p class="text-2xl font-bold mt-1 text-red-600">{{ overview?.weak_signal || 0 }}</p>
                <p class="text-xs text-gray-400 mt-1">perlu perhatian</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Pelanggan Aktif</p>
                <p class="text-2xl font-bold mt-1 text-green-600">{{ overview?.customer_active || 0 }}</p>
                <p class="text-xs text-gray-400 mt-1">koneksi aktif</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Pelanggan Isolir</p>
                <p class="text-2xl font-bold mt-1 text-yellow-600">{{ overview?.customer_isolated || 0 }}</p>
                <p class="text-xs text-gray-400 mt-1">akses dibatasi</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Total Alert</p>
                <p class="text-2xl font-bold mt-1" :class="(overview?.alert_count || 0) > 0 ? 'text-red-600' : 'text-green-600'">{{ overview?.alert_count || 0 }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ (overview?.alert_count || 0) === 0 ? 'semua normal' : 'perlu ditangani' }}</p>
            </div>
        </div>

        <!-- Section 2: Router Health Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Status Router</h2>
            </div>
            <div v-if="routerHealth && routerHealth.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CPU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Memori</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uptime</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Versi</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pelanggan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="r in routerHealth"
                            :key="r.id"
                            class="hover:bg-gray-50 cursor-pointer"
                            @click="router.visit(`/admin/routers/${r.id}`)"
                        >
                            <td class="px-6 py-3 font-medium text-gray-900">{{ r.name }}</td>
                            <td class="px-6 py-3 text-gray-500 font-mono text-xs">{{ r.ip_address }}</td>
                            <td class="px-6 py-3 text-center">
                                <span class="inline-flex items-center gap-1.5">
                                    <span :class="['w-2.5 h-2.5 rounded-full', r.is_online ? 'bg-green-500' : 'bg-red-500']"></span>
                                    <span :class="r.is_online ? 'text-green-700' : 'text-red-700'" class="text-xs font-medium">
                                        {{ r.is_online ? 'Online' : 'Offline' }}
                                    </span>
                                </span>
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-20 bg-gray-200 rounded-full h-2">
                                        <div
                                            :class="['h-2 rounded-full', getStatusColor(r.cpu_load)]"
                                            :style="{ width: r.cpu_load + '%' }"
                                        ></div>
                                    </div>
                                    <span :class="['text-xs font-medium', getStatusTextColor(r.cpu_load)]">{{ r.cpu_load }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-20 bg-gray-200 rounded-full h-2">
                                        <div
                                            :class="['h-2 rounded-full', getStatusColor(r.memory_usage)]"
                                            :style="{ width: r.memory_usage + '%' }"
                                        ></div>
                                    </div>
                                    <span :class="['text-xs font-medium', getStatusTextColor(r.memory_usage)]">{{ r.memory_usage }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-3 text-gray-500 text-xs">{{ r.uptime || '-' }}</td>
                            <td class="px-6 py-3 text-gray-500 text-xs">{{ r.version || '-' }}</td>
                            <td class="px-6 py-3 text-right font-medium">{{ r.customer_count }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="p-12 text-center text-gray-500">
                <p>Tidak ada data router.</p>
            </div>
        </div>

        <!-- Section 3: Router Resource Charts -->
        <div v-if="routerCpuSnapshot && routerCpuSnapshot.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">CPU Load per Router</h2>
                </div>
                <div class="p-6">
                    <div :style="{ height: Math.max(200, routerCpuSnapshot.length * 40) + 'px' }">
                        <Bar :data="cpuChartData" :options="cpuChartOptions" />
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Memory Usage per Router</h2>
                </div>
                <div class="p-6">
                    <div :style="{ height: Math.max(200, routerCpuSnapshot.length * 40) + 'px' }">
                        <Bar :data="memoryChartData" :options="memoryChartOptions" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Device Health Doughnuts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Status Device</h2>
                </div>
                <div class="p-6">
                    <div v-if="(deviceHealth?.total || 0) > 0" class="h-72">
                        <Doughnut :data="deviceDoughnutData" :options="doughnutOptions" />
                    </div>
                    <div v-else class="h-72 flex items-center justify-center text-gray-400">
                        <p>Belum ada data device.</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Distribusi Kualitas Sinyal</h2>
                </div>
                <div class="p-6">
                    <div v-if="signalDistribution && signalDistribution.some(s => s.value > 0)" class="h-72">
                        <Doughnut :data="signalDoughnutData" :options="doughnutOptions" />
                    </div>
                    <div v-else class="h-72 flex items-center justify-center text-gray-400">
                        <p>Belum ada data sinyal.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 5: Device per Manufacturer -->
        <div v-if="deviceHealth?.by_manufacturer && deviceHealth.by_manufacturer.length > 0" class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Device per Manufacturer</h2>
            </div>
            <div class="p-6">
                <div class="h-80">
                    <Bar :data="manufacturerChartData" :options="manufacturerChartOptions" />
                </div>
            </div>
        </div>

        <!-- Section 6: Connection Type Distribution -->
        <div v-if="connectionStats && connectionStats.length > 0" class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Distribusi Tipe Koneksi</h2>
            </div>
            <div class="p-6">
                <div class="h-72">
                    <Bar :data="connectionChartData" :options="connectionChartOptions" />
                </div>
            </div>
        </div>

        <!-- Section 7: Alerts Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Network Alerts</h2>
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
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
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
                            <td class="px-6 py-3 text-right">
                                <Link
                                    v-if="alert.link && alert.link !== '#'"
                                    :href="alert.link"
                                    class="text-blue-600 hover:text-blue-800 text-xs font-medium"
                                >
                                    Lihat
                                </Link>
                            </td>
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

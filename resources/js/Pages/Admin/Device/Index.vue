<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    devices: Object,
    stats: Object,
    manufacturers: Array,
    filters: Object,
})

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const manufacturerFilter = ref(props.filters.manufacturer || '')
const signalFilter = ref(props.filters.signal || '')

// Apply filters
const applyFilters = () => {
    router.get('/admin/devices', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        manufacturer: manufacturerFilter.value || undefined,
        signal: signalFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Debounced search
let searchTimeout
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 500)
})

watch([statusFilter, manufacturerFilter, signalFilter], applyFilters)

// Format date
const formatDateTime = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

// Actions
const syncDevices = () => {
    if (confirm('Sync semua device dari GenieACS?')) {
        router.post('/admin/devices/sync')
    }
}

const rebootDevice = (device) => {
    if (confirm(`Reboot device ${device.serial_number}?`)) {
        router.post(`/admin/devices/${device.id}/reboot`)
    }
}

const refreshDevice = (device) => {
    router.post(`/admin/devices/${device.id}/refresh`)
}
</script>

<template>
    <Head title="Device Management" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Device Management</h1>
                <div class="flex gap-2">
                    <button
                        @click="syncDevices"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Sync dari GenieACS
                    </button>
                </div>
            </div>
        </template>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-sm">Total Device</p>
                <p class="text-2xl font-bold">{{ stats.total }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-sm">Online</p>
                <p class="text-2xl font-bold text-green-600">{{ stats.online }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-sm">Offline</p>
                <p class="text-2xl font-bold text-red-600">{{ stats.offline }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-sm">Sinyal Lemah</p>
                <p class="text-2xl font-bold text-yellow-600">{{ stats.weak_signal }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="md:col-span-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari serial number, IP, atau pelanggan..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                <select
                    v-model="statusFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Status</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                </select>
                <select
                    v-model="manufacturerFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Manufacturer</option>
                    <option v-for="mfr in manufacturers" :key="mfr" :value="mfr">{{ mfr }}</option>
                </select>
                <select
                    v-model="signalFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Sinyal</option>
                    <option value="weak">Sinyal Lemah</option>
                </select>
            </div>
        </div>

        <!-- Devices Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Device</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Pelanggan</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">RX Power</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Last Inform</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="device in devices.data" :key="device.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div>
                                    <p class="font-medium text-gray-900">{{ device.serial_number || device.device_id }}</p>
                                    <p class="text-sm text-gray-500">{{ device.manufacturer }} {{ device.model }}</p>
                                    <p class="text-xs text-gray-400">{{ device.wan_ip }}</p>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="device.customer">
                                    <Link
                                        :href="`/admin/customers/${device.customer.id}`"
                                        class="font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        {{ device.customer.name }}
                                    </Link>
                                    <p class="text-sm text-gray-500">{{ device.customer.customer_id }}</p>
                                </div>
                                <span v-else class="text-gray-400 text-sm">Tidak terhubung</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="px-2 py-1 rounded-full text-xs font-medium"
                                    :class="device.is_online ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'"
                                >
                                    {{ device.is_online ? 'Online' : 'Offline' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="device.rx_power !== null">
                                    <span
                                        class="font-mono"
                                        :class="{
                                            'text-green-600': device.rx_power >= -28 && device.rx_power <= -8,
                                            'text-red-600': device.rx_power < -28,
                                            'text-yellow-600': device.rx_power > -8
                                        }"
                                    >
                                        {{ device.rx_power }} dBm
                                    </span>
                                </div>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ formatDateTime(device.last_inform) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <Link
                                        :href="`/admin/devices/${device.id}`"
                                        class="p-1 text-gray-500 hover:text-blue-600"
                                        title="Detail"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>
                                    <button
                                        @click="refreshDevice(device)"
                                        class="p-1 text-gray-500 hover:text-green-600"
                                        title="Refresh"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="rebootDevice(device)"
                                        class="p-1 text-gray-500 hover:text-red-600"
                                        title="Reboot"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="!devices.data.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada device</h3>
                <p class="mt-1 text-sm text-gray-500">Sync device dari GenieACS untuk memulai</p>
            </div>

            <!-- Pagination -->
            <div v-if="devices.data.length" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Menampilkan {{ devices.from }} - {{ devices.to }} dari {{ devices.total }} device
                </p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in devices.links"
                        :key="link.label"
                        :href="link.url"
                        v-html="link.label"
                        class="px-3 py-1 text-sm rounded"
                        :class="{
                            'bg-blue-600 text-white': link.active,
                            'text-gray-500 hover:bg-gray-100': !link.active && link.url,
                            'text-gray-300 cursor-not-allowed': !link.url,
                        }"
                    />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

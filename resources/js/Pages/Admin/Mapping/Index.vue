<script setup>
import { ref, onMounted, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'

const props = defineProps({
    areas: Array,
    centerPoint: Object,
    filters: Object,
})

const map = ref(null)
const mapContainer = ref(null)
const markers = ref({ customers: [], odps: [] })
const markerLayers = ref({ customers: null, odps: null })

const showCustomers = ref(true)
const showOdps = ref(true)
const selectedArea = ref(props.filters.area_id || '')
const selectedStatus = ref(props.filters.status || '')
const loading = ref(false)
const showSidebar = ref(false) // Hidden by default on mobile

const customerStatuses = {
    active: { label: 'Aktif', color: '#22c55e' },
    isolated: { label: 'Isolir', color: '#ef4444' },
    suspended: { label: 'Suspend', color: '#f59e0b' },
    terminated: { label: 'Berhenti', color: '#6b7280' },
}

let L = null

onMounted(async () => {
    // Dynamically import Leaflet
    L = await import('leaflet')
    await import('leaflet/dist/leaflet.css')

    // Import marker cluster if available
    try {
        await import('leaflet.markercluster')
        await import('leaflet.markercluster/dist/MarkerCluster.css')
        await import('leaflet.markercluster/dist/MarkerCluster.Default.css')
    } catch (e) {
        console.log('MarkerCluster not available, using regular markers')
    }

    initMap()
    loadData()
})

const initMap = () => {
    map.value = L.map(mapContainer.value).setView(
        [props.centerPoint.lat, props.centerPoint.lng],
        13
    )

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map.value)

    // Initialize marker cluster groups if available
    if (L.markerClusterGroup) {
        markerLayers.value.customers = L.markerClusterGroup({
            maxClusterRadius: 50,
            spiderfyOnMaxZoom: true,
        })
        markerLayers.value.odps = L.markerClusterGroup({
            maxClusterRadius: 30,
        })
    } else {
        markerLayers.value.customers = L.layerGroup()
        markerLayers.value.odps = L.layerGroup()
    }

    map.value.addLayer(markerLayers.value.customers)
    map.value.addLayer(markerLayers.value.odps)
}

const loadData = async () => {
    loading.value = true
    await Promise.all([
        loadCustomers(),
        loadOdps(),
    ])
    loading.value = false
}

const loadCustomers = async () => {
    try {
        const params = {}
        if (selectedArea.value) params.area_id = selectedArea.value
        if (selectedStatus.value) params.status = selectedStatus.value

        const response = await axios.get('/admin/mapping/customers', { params })
        markers.value.customers = response.data.customers
        renderCustomerMarkers()
    } catch (error) {
        console.error('Failed to load customers:', error)
    }
}

const loadOdps = async () => {
    try {
        const params = {}
        if (selectedArea.value) params.area_id = selectedArea.value

        const response = await axios.get('/admin/mapping/odps', { params })
        markers.value.odps = response.data.odps
        renderOdpMarkers()
    } catch (error) {
        console.error('Failed to load ODPs:', error)
    }
}

const renderCustomerMarkers = () => {
    markerLayers.value.customers.clearLayers()

    if (!showCustomers.value) return

    markers.value.customers.forEach(customer => {
        const statusColor = customerStatuses[customer.status]?.color || '#6b7280'

        const icon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="background-color: ${statusColor}; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
            iconSize: [16, 16],
            iconAnchor: [8, 8],
        })

        const marker = L.marker([customer.lat, customer.lng], { icon })
            .bindPopup(`
                <div class="text-sm">
                    <p class="font-semibold">${customer.name}</p>
                    <p class="text-gray-500">${customer.customer_id}</p>
                    <p>${customer.address || '-'}</p>
                    <p>Paket: ${customer.package || '-'}</p>
                    <p>Status: <span style="color: ${statusColor}">${customerStatuses[customer.status]?.label || customer.status}</span></p>
                    ${customer.odp ? `<p>ODP: ${customer.odp.code}</p>` : ''}
                    <a href="/admin/customers/${customer.id}" class="text-blue-600 hover:underline">Lihat Detail</a>
                </div>
            `)

        markerLayers.value.customers.addLayer(marker)
    })
}

const renderOdpMarkers = () => {
    markerLayers.value.odps.clearLayers()

    if (!showOdps.value) return

    markers.value.odps.forEach(odp => {
        const usagePercent = odp.usage_percentage
        let color = '#22c55e' // green
        if (usagePercent >= 90) color = '#ef4444' // red
        else if (usagePercent >= 70) color = '#f59e0b' // yellow

        const icon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="background-color: ${color}; width: 20px; height: 20px; border-radius: 4px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-size: 10px; font-weight: bold;">${odp.available_ports}</div>`,
            iconSize: [24, 24],
            iconAnchor: [12, 12],
        })

        const marker = L.marker([odp.lat, odp.lng], { icon })
            .bindPopup(`
                <div class="text-sm">
                    <p class="font-semibold">${odp.name}</p>
                    <p class="font-mono text-gray-500">${odp.code}</p>
                    <p>Tipe: ${odp.pole_type_label}</p>
                    <p>Kapasitas: ${odp.used_ports}/${odp.capacity} (${odp.available_ports} tersedia)</p>
                    <p>Area: ${odp.area || '-'}</p>
                    <a href="/admin/odps/${odp.id}/edit" class="text-blue-600 hover:underline">Edit ODP</a>
                </div>
            `)

        markerLayers.value.odps.addLayer(marker)
    })
}

watch(showCustomers, () => renderCustomerMarkers())
watch(showOdps, () => renderOdpMarkers())
watch([selectedArea, selectedStatus], () => loadData())

const zoomToAll = () => {
    const allMarkers = [
        ...markers.value.customers.map(c => [c.lat, c.lng]),
        ...markers.value.odps.map(o => [o.lat, o.lng]),
    ]

    if (allMarkers.length > 0) {
        const bounds = L.latLngBounds(allMarkers)
        map.value.fitBounds(bounds, { padding: [50, 50] })
    }
}
</script>

<template>
    <Head title="Mapping" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Mapping Pelanggan & ODP</h1>
                <div class="flex gap-2">
                    <button
                        @click="zoomToAll"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                        Zoom All
                    </button>
                    <button
                        @click="loadData"
                        :disabled="loading"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 disabled:opacity-50"
                    >
                        <svg class="w-5 h-5" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
        </template>

        <div class="relative">
            <!-- Mobile Toggle Button -->
            <button
                @click="showSidebar = !showSidebar"
                class="lg:hidden fixed bottom-20 right-4 z-[1000] bg-blue-600 text-white p-3 rounded-full shadow-lg"
            >
                <svg v-if="!showSidebar" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
                <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Mobile Sidebar Overlay -->
            <div
                v-if="showSidebar"
                class="lg:hidden fixed inset-0 bg-black bg-opacity-50 z-[999]"
                @click="showSidebar = false"
            ></div>

            <div class="flex gap-6">
                <!-- Sidebar Filters -->
                <div
                    :class="[
                        'w-72 lg:w-64 flex-shrink-0 space-y-4',
                        'fixed lg:relative inset-y-0 left-0 z-[1000] lg:z-auto',
                        'bg-gray-100 lg:bg-transparent p-4 lg:p-0',
                        'transform transition-transform duration-300 ease-in-out',
                        'overflow-y-auto',
                        showSidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
                    ]"
                >
                    <!-- Close button for mobile -->
                    <div class="lg:hidden flex justify-end mb-2">
                        <button @click="showSidebar = false" class="p-2 text-gray-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Layer Toggle -->
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="font-semibold mb-3">Layer</h3>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2">
                                <input v-model="showCustomers" type="checkbox" class="rounded text-blue-600">
                                <span class="flex items-center gap-2">
                                    <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                                    Pelanggan ({{ markers.customers.length }})
                                </span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="showOdps" type="checkbox" class="rounded text-blue-600">
                                <span class="flex items-center gap-2">
                                    <span class="w-3 h-3 bg-blue-500 rounded"></span>
                                    ODP ({{ markers.odps.length }})
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="font-semibold mb-3">Filter</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm text-gray-600">Area</label>
                                <select
                                    v-model="selectedArea"
                                    class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                >
                                    <option value="">Semua Area</option>
                                    <option v-for="area in areas" :key="area.id" :value="area.id">
                                        {{ area.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm text-gray-600">Status Pelanggan</label>
                                <select
                                    v-model="selectedStatus"
                                    class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                >
                                    <option value="">Semua Status</option>
                                    <option v-for="(info, status) in customerStatuses" :key="status" :value="status">
                                        {{ info.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="font-semibold mb-3">Legenda</h3>
                        <div class="space-y-2 text-sm">
                            <p class="font-medium text-gray-600">Pelanggan:</p>
                            <div v-for="(info, status) in customerStatuses" :key="status" class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: info.color }"></span>
                                <span>{{ info.label }}</span>
                            </div>
                            <p class="font-medium text-gray-600 mt-3">ODP (Port Tersedia):</p>
                            <div class="flex items-center gap-2">
                                <span class="w-4 h-4 bg-green-500 rounded"></span>
                                <span>Banyak (&lt;70%)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-4 h-4 bg-yellow-500 rounded"></span>
                                <span>Sedang (70-90%)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-4 h-4 bg-red-500 rounded"></span>
                                <span>Penuh (&gt;90%)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map Container -->
                <div class="flex-1 w-full">
                    <div
                        ref="mapContainer"
                        class="w-full h-[calc(100vh-180px)] lg:h-[calc(100vh-200px)] rounded-xl shadow-sm bg-gray-100"
                    ></div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style>
.leaflet-popup-content-wrapper {
    border-radius: 8px;
}
.leaflet-popup-content {
    margin: 12px;
}
</style>

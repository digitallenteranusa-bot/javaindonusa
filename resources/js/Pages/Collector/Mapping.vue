<script setup>
import { ref, onMounted, watch } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import CollectorLayout from '@/Layouts/CollectorLayout.vue'
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
const showFilters = ref(false)
const mapStyle = ref('satellite')
const locating = ref(false)
const locationError = ref('')

let baseLayers = {}

const customerStatuses = {
    active: { label: 'Aktif', color: '#22c55e' },
    isolated: { label: 'Isolir', color: '#ef4444' },
    suspended: { label: 'Suspend', color: '#f59e0b' },
    terminated: { label: 'Berhenti', color: '#6b7280' },
}

let L = null

onMounted(async () => {
    L = await import('leaflet')
    await import('leaflet/dist/leaflet.css')

    try {
        await import('leaflet.markercluster')
        await import('leaflet.markercluster/dist/MarkerCluster.css')
        await import('leaflet.markercluster/dist/MarkerCluster.Default.css')
    } catch (e) {
        console.log('MarkerCluster not available')
    }

    initMap()
    loadData()
})

const initMap = () => {
    map.value = L.map(mapContainer.value).setView(
        [props.centerPoint.lat, props.centerPoint.lng],
        17
    )

    baseLayers.street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19
    })

    baseLayers.satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '© Esri',
        maxZoom: 19
    })

    baseLayers.labels = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png', {
        attribution: '',
        maxZoom: 19,
        pane: 'shadowPane'
    })

    baseLayers.satellite.addTo(map.value)
    baseLayers.labels.addTo(map.value)

    if (L.markerClusterGroup) {
        markerLayers.value.customers = L.markerClusterGroup({ maxClusterRadius: 50 })
        markerLayers.value.odps = L.markerClusterGroup({ maxClusterRadius: 30 })
    } else {
        markerLayers.value.customers = L.layerGroup()
        markerLayers.value.odps = L.layerGroup()
    }

    map.value.addLayer(markerLayers.value.customers)
    map.value.addLayer(markerLayers.value.odps)
}

const switchMapStyle = (style) => {
    mapStyle.value = style
    Object.values(baseLayers).forEach(layer => {
        if (map.value.hasLayer(layer)) {
            map.value.removeLayer(layer)
        }
    })

    if (style === 'street') {
        baseLayers.street.addTo(map.value)
    } else {
        baseLayers.satellite.addTo(map.value)
        baseLayers.labels.addTo(map.value)
    }
}

const locateMe = () => {
    if (!navigator.geolocation) {
        locationError.value = 'Browser tidak mendukung geolocation'
        return
    }

    locating.value = true
    locationError.value = ''

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const { latitude, longitude } = position.coords
            map.value.setView([latitude, longitude], 18)

            const myLocationIcon = L.divIcon({
                className: 'my-location-marker',
                html: `<div style="background-color: #3b82f6; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 0 2px #3b82f6, 0 2px 8px rgba(0,0,0,0.3);"></div>`,
                iconSize: [22, 22],
                iconAnchor: [11, 11],
            })

            L.marker([latitude, longitude], { icon: myLocationIcon })
                .addTo(map.value)
                .bindPopup('Lokasi Anda')
                .openPopup()

            locating.value = false
        },
        (error) => {
            locating.value = false
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    locationError.value = 'Akses lokasi ditolak'
                    break
                case error.POSITION_UNAVAILABLE:
                    locationError.value = 'Lokasi tidak tersedia'
                    break
                default:
                    locationError.value = 'Gagal mendapatkan lokasi'
            }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    )
}

const loadData = async () => {
    loading.value = true
    await Promise.all([loadCustomers(), loadOdps()])
    loading.value = false
}

const loadCustomers = async () => {
    try {
        const params = {}
        if (selectedArea.value) params.area_id = selectedArea.value
        if (selectedStatus.value) params.status = selectedStatus.value

        const response = await axios.get('/collector/mapping/customers', { params })
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

        const response = await axios.get('/collector/mapping/odps', { params })
        markers.value.odps = response.data.odps
        renderOdpMarkers()
    } catch (error) {
        console.error('Failed to load ODPs:', error)
    }
}

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value || 0)
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
                    <p>Hutang: <span class="${customer.total_debt > 0 ? 'text-red-600 font-semibold' : 'text-green-600'}">${formatCurrency(customer.total_debt)}</span></p>
                    <p>Status: <span style="color: ${statusColor}">${customerStatuses[customer.status]?.label || customer.status}</span></p>
                    ${customer.odp ? `<p>ODP: ${customer.odp.code}</p>` : ''}
                    <a href="/collector/customers/${customer.id}" class="text-blue-600 hover:underline">Lihat Detail</a>
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
        let color = '#22c55e'
        if (usagePercent >= 90) color = '#ef4444'
        else if (usagePercent >= 70) color = '#f59e0b'

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
    <Head title="Peta Pelanggan" />

    <CollectorLayout>
        <div class="min-h-screen bg-gray-100 pb-20">
            <!-- Header -->
            <div class="bg-white shadow-sm sticky top-0 z-50">
                <div class="px-4 py-3">
                    <div class="flex items-center justify-between">
                        <h1 class="text-lg font-bold text-gray-900">Peta Pelanggan</h1>
                        <div class="flex items-center gap-2">
                            <!-- Filter Toggle -->
                            <button
                                @click="showFilters = !showFilters"
                                class="p-2 rounded-lg"
                                :class="showFilters ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600'"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                </svg>
                            </button>
                            <!-- Refresh -->
                            <button
                                @click="loadData"
                                :disabled="loading"
                                class="p-2 bg-blue-600 text-white rounded-lg disabled:opacity-50"
                            >
                                <svg class="w-5 h-5" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Filter Panel -->
                    <div v-if="showFilters" class="mt-3 pt-3 border-t border-gray-200 space-y-3">
                        <!-- Map Style -->
                        <div class="flex gap-2">
                            <button
                                @click="switchMapStyle('satellite')"
                                :class="[
                                    'flex-1 py-2 text-sm rounded-lg',
                                    mapStyle === 'satellite' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'
                                ]"
                            >
                                Satelit
                            </button>
                            <button
                                @click="switchMapStyle('street')"
                                :class="[
                                    'flex-1 py-2 text-sm rounded-lg',
                                    mapStyle === 'street' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'
                                ]"
                            >
                                Peta
                            </button>
                        </div>

                        <!-- Layer Toggle -->
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2">
                                <input v-model="showCustomers" type="checkbox" class="rounded text-blue-600">
                                <span class="text-sm">Pelanggan ({{ markers.customers.length }})</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="showOdps" type="checkbox" class="rounded text-blue-600">
                                <span class="text-sm">ODP ({{ markers.odps.length }})</span>
                            </label>
                        </div>

                        <!-- Area & Status -->
                        <div class="flex gap-2">
                            <select
                                v-model="selectedArea"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                            >
                                <option value="">Semua Area</option>
                                <option v-for="area in areas" :key="area.id" :value="area.id">
                                    {{ area.name }}
                                </option>
                            </select>
                            <select
                                v-model="selectedStatus"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                            >
                                <option value="">Semua Status</option>
                                <option v-for="(info, status) in customerStatuses" :key="status" :value="status">
                                    {{ info.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location Error -->
            <div v-if="locationError" class="mx-4 mt-2 p-2 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-center justify-between">
                <span>{{ locationError }}</span>
                <button @click="locationError = ''" class="text-red-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Map Container -->
            <div class="px-4 py-2">
                <div
                    ref="mapContainer"
                    class="w-full h-[calc(100vh-200px)] rounded-xl shadow-sm bg-gray-200"
                ></div>
            </div>

            <!-- Floating Buttons -->
            <div class="fixed bottom-24 right-4 z-[1000] flex flex-col gap-2">
                <!-- Locate Me -->
                <button
                    @click="locateMe"
                    :disabled="locating"
                    class="p-3 bg-green-600 text-white rounded-full shadow-lg disabled:opacity-50"
                    title="Lokasi Saya"
                >
                    <svg class="w-5 h-5" :class="{ 'animate-pulse': locating }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </button>
                <!-- Zoom All -->
                <button
                    @click="zoomToAll"
                    class="p-3 bg-white text-gray-700 rounded-full shadow-lg border border-gray-200"
                    title="Zoom Semua"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                    </svg>
                </button>
            </div>
        </div>
    </CollectorLayout>
</template>

<style>
.leaflet-popup-content-wrapper {
    border-radius: 8px;
}
.leaflet-popup-content {
    margin: 12px;
}
</style>

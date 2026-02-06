<script setup>
import { ref, computed, nextTick } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    odp: Object,
    areas: Array,
    poleTypes: Object,
})

const isEdit = computed(() => !!props.odp)

// Map related
const showMapPicker = ref(false)
const mapContainer = ref(null)
const locating = ref(false)
const locationError = ref('')
let map = null
let marker = null
let L = null

const form = useForm({
    name: props.odp?.name || '',
    code: props.odp?.code || '',
    latitude: props.odp?.latitude || '',
    longitude: props.odp?.longitude || '',
    pole_type: props.odp?.pole_type || 'sendiri',
    capacity: props.odp?.capacity || 8,
    area_id: props.odp?.area_id || '',
    is_active: props.odp?.is_active ?? true,
    notes: props.odp?.notes || '',
})

const submit = () => {
    if (isEdit.value) {
        form.put(`/admin/odps/${props.odp.id}`)
    } else {
        form.post('/admin/odps')
    }
}

// Get current location
const getLocation = () => {
    if (!navigator.geolocation) {
        locationError.value = 'Browser tidak mendukung geolocation'
        return
    }

    locating.value = true
    locationError.value = ''

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const { latitude, longitude } = position.coords

            if (showMapPicker.value && map) {
                map.setView([latitude, longitude], 18)
                addMarker(latitude, longitude)
            } else {
                form.latitude = latitude.toFixed(8)
                form.longitude = longitude.toFixed(8)
            }

            locating.value = false
        },
        (error) => {
            locating.value = false
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    locationError.value = 'Akses lokasi ditolak. Izinkan akses lokasi di pengaturan browser atau masukkan koordinat manual.'
                    break
                case error.POSITION_UNAVAILABLE:
                    locationError.value = 'Informasi lokasi tidak tersedia. Masukkan koordinat manual.'
                    break
                case error.TIMEOUT:
                    locationError.value = 'Waktu permintaan lokasi habis. Coba lagi atau masukkan koordinat manual.'
                    break
                default:
                    locationError.value = 'Gagal mendapatkan lokasi. Masukkan koordinat manual.'
            }
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    )
}

// Map picker functions
const openMapPicker = async () => {
    showMapPicker.value = true
    await nextTick()

    if (!L) {
        L = await import('leaflet')
        await import('leaflet/dist/leaflet.css')
    }

    // Default center (Kantor Kecamatan Pule or ODP location)
    const defaultLat = form.latitude || -8.1228
    const defaultLng = form.longitude || 111.5617

    map = L.map(mapContainer.value).setView([defaultLat, defaultLng], 17)

    // Satellite layer
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles © Esri',
        maxZoom: 19
    }).addTo(map)

    // Labels overlay
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        pane: 'shadowPane'
    }).addTo(map)

    // Add marker if coordinates exist
    if (form.latitude && form.longitude) {
        addMarker(form.latitude, form.longitude)
    }

    // Click to place marker
    map.on('click', (e) => {
        addMarker(e.latlng.lat, e.latlng.lng)
    })
}

const addMarker = (lat, lng) => {
    if (marker) {
        map.removeLayer(marker)
    }

    // ODP marker style (green box)
    const icon = L.divIcon({
        className: 'odp-marker',
        html: `<div style="background-color: #10b981; width: 20px; height: 20px; border-radius: 4px; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"></div>`,
        iconSize: [26, 26],
        iconAnchor: [13, 13],
    })

    marker = L.marker([lat, lng], { icon, draggable: true }).addTo(map)

    marker.on('dragend', () => {
        const pos = marker.getLatLng()
        form.latitude = pos.lat.toFixed(8)
        form.longitude = pos.lng.toFixed(8)
    })

    form.latitude = parseFloat(lat).toFixed(8)
    form.longitude = parseFloat(lng).toFixed(8)
}

const closeMapPicker = () => {
    if (map) {
        map.remove()
        map = null
        marker = null
    }
    showMapPicker.value = false
}

const confirmLocation = () => {
    closeMapPicker()
}
</script>

<template>
    <Head :title="isEdit ? 'Edit ODP' : 'Tambah ODP'" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/odps" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ isEdit ? 'Edit ODP' : 'Tambah ODP' }}
                </h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Informasi ODP</h2>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama ODP *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="ODP Jl. Sudirman 01"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.name }"
                            >
                            <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kode *</label>
                            <input
                                v-model="form.code"
                                type="text"
                                placeholder="ODP-001"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 uppercase"
                                :class="{ 'border-red-500': form.errors.code }"
                            >
                            <p v-if="form.errors.code" class="text-red-500 text-sm mt-1">{{ form.errors.code }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Tiang *</label>
                            <select
                                v-model="form.pole_type"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.pole_type }"
                            >
                                <option v-for="(label, value) in poleTypes" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                            <p v-if="form.errors.pole_type" class="text-red-500 text-sm mt-1">{{ form.errors.pole_type }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kapasitas Port *</label>
                            <select
                                v-model="form.capacity"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option :value="4">4 Port</option>
                                <option :value="8">8 Port</option>
                                <option :value="16">16 Port</option>
                                <option :value="24">24 Port</option>
                                <option :value="32">32 Port</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Area</label>
                        <select
                            v-model="form.area_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Pilih Area</option>
                            <option v-for="area in areas" :key="area.id" :value="area.id">
                                {{ area.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Koordinat</label>

                        <!-- Location Error -->
                        <div v-if="locationError" class="mb-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-700 text-sm flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2">
                                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>{{ locationError }}</span>
                            </div>
                            <button type="button" @click="locationError = ''" class="text-yellow-500 hover:text-yellow-700 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex gap-2">
                            <input
                                v-model="form.latitude"
                                type="text"
                                placeholder="Latitude (contoh: -8.1228)"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                            <input
                                v-model="form.longitude"
                                type="text"
                                placeholder="Longitude (contoh: 111.5617)"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                            <!-- Get Current Location -->
                            <button
                                type="button"
                                @click="getLocation"
                                :disabled="locating"
                                class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center gap-1"
                                title="Ambil Lokasi Saat Ini"
                            >
                                <svg class="w-5 h-5" :class="{ 'animate-pulse': locating }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                            <!-- Open Map Picker -->
                            <button
                                type="button"
                                @click="openMapPicker"
                                class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-1"
                                title="Pilih di Peta"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Klik tombol hijau untuk GPS atau tombol biru untuk pilih di peta.
                            Bisa juga isi koordinat manual dari Google Maps.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            placeholder="Catatan tambahan..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                    </div>

                    <div>
                        <label class="flex items-center gap-3">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            >
                            <span class="text-sm font-medium text-gray-700">ODP Aktif</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-4">
                <Link
                    href="/admin/odps"
                    class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                >
                    Batal
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Perbarui' : 'Simpan') }}
                </button>
            </div>
        </form>

        <!-- Map Picker Modal -->
        <Teleport to="body">
            <div v-if="showMapPicker" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-black bg-opacity-50" @click="closeMapPicker"></div>

                    <!-- Modal -->
                    <div class="relative bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                        <!-- Header -->
                        <div class="flex items-center justify-between p-4 border-b">
                            <h3 class="text-lg font-semibold">Pilih Lokasi ODP di Peta</h3>
                            <button @click="closeMapPicker" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Map -->
                        <div ref="mapContainer" class="w-full h-[60vh]"></div>

                        <!-- Footer -->
                        <div class="p-4 border-t bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-600">
                                    <span v-if="form.latitude && form.longitude">
                                        Koordinat: {{ form.latitude }}, {{ form.longitude }}
                                    </span>
                                    <span v-else class="text-gray-400">Klik pada peta untuk memilih lokasi ODP</span>
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        @click="getLocation"
                                        :disabled="locating"
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center gap-2"
                                    >
                                        <svg class="w-5 h-5" :class="{ 'animate-pulse': locating }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        {{ locating ? 'Mencari...' : 'Lokasi Saya' }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="confirmLocation"
                                        :disabled="!form.latitude || !form.longitude"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                                    >
                                        Konfirmasi Lokasi
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

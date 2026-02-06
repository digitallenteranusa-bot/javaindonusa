<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import CollectorLayout from '@/Layouts/CollectorLayout.vue'

const props = defineProps({
    customer: Object,
    packages: Array,
    areas: Array,
    routers: Array,
    odps: Array,
})

const isEditing = computed(() => !!props.customer?.id)

const form = useForm({
    name: props.customer?.name || '',
    phone: props.customer?.phone || '',
    address: props.customer?.address || '',
    kelurahan: props.customer?.kelurahan || '',
    package_id: props.customer?.package_id || '',
    area_id: props.customer?.area_id || '',
    router_id: props.customer?.router_id || '',
    odp_id: props.customer?.odp_id || '',
    pppoe_username: props.customer?.pppoe_username || '',
    ip_address: props.customer?.ip_address || '',
    onu_serial: props.customer?.onu_serial || '',
    connection_type: props.customer?.connection_type || 'pppoe',
    billing_date: props.customer?.billing_date || 1,
    total_debt: props.customer?.total_debt || 0,
    rapel_months: props.customer?.rapel_months || '',
    notes: props.customer?.notes || '',
    latitude: props.customer?.latitude || '',
    longitude: props.customer?.longitude || '',
})

// Filter ODPs by selected area
const filteredOdps = computed(() => {
    if (!form.area_id) return props.odps || []
    return (props.odps || []).filter(odp => odp.area_id === form.area_id)
})

// Reset ODP when area changes
watch(() => form.area_id, () => {
    if (!isEditing.value) {
        form.odp_id = ''
    }
})

const submit = () => {
    if (isEditing.value) {
        form.put(`/collector/customers/${props.customer.id}`)
    } else {
        form.post('/collector/customers')
    }
}

// Map related
const showMapPicker = ref(false)
const mapContainer = ref(null)
const locating = ref(false)
const locationError = ref('')
const locationSuccess = ref(false)
let map = null
let marker = null
let L = null

// Get current location with better error handling
const getLocation = () => {
    if (!navigator.geolocation) {
        locationError.value = 'Browser/aplikasi tidak mendukung geolocation. Silakan input koordinat manual.'
        return
    }

    locating.value = true
    locationError.value = ''
    locationSuccess.value = false

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const { latitude, longitude } = position.coords

            if (showMapPicker.value && map) {
                map.setView([latitude, longitude], 18)
                addMarker(latitude, longitude)
            } else {
                form.latitude = latitude.toFixed(6)
                form.longitude = longitude.toFixed(6)
            }

            locating.value = false
            locationSuccess.value = true

            setTimeout(() => {
                locationSuccess.value = false
            }, 3000)
        },
        (error) => {
            locating.value = false

            switch (error.code) {
                case error.PERMISSION_DENIED:
                    locationError.value = 'Akses lokasi ditolak. Izinkan akses lokasi di pengaturan browser/aplikasi atau pilih di peta.'
                    break
                case error.POSITION_UNAVAILABLE:
                    locationError.value = 'Informasi lokasi tidak tersedia. Pilih lokasi di peta atau input manual.'
                    break
                case error.TIMEOUT:
                    locationError.value = 'Waktu permintaan lokasi habis. Coba lagi atau pilih di peta.'
                    break
                default:
                    locationError.value = 'Gagal mendapatkan lokasi. Pilih lokasi di peta atau input manual.'
            }
        },
        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    )
}

// Map picker functions
const openMapPicker = async () => {
    showMapPicker.value = true
    await nextTick()

    // Small delay to ensure DOM is ready
    await new Promise(resolve => setTimeout(resolve, 100))

    if (!L) {
        L = await import('leaflet')
        await import('leaflet/dist/leaflet.css')
    }

    // Default center (customer location or Kantor Kecamatan Pule)
    const defaultLat = form.latitude || -8.1228
    const defaultLng = form.longitude || 111.5617

    map = L.map(mapContainer.value, {
        zoomControl: true,
        attributionControl: false
    }).setView([defaultLat, defaultLng], 17)

    // Satellite layer
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19
    }).addTo(map)

    // Labels overlay
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        pane: 'shadowPane'
    }).addTo(map)

    // Force map to recalculate size
    setTimeout(() => {
        map.invalidateSize()
    }, 200)

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

    // Customer marker style (blue pin)
    const icon = L.divIcon({
        className: 'customer-marker',
        html: `<div style="background-color: #3b82f6; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"></div>`,
        iconSize: [26, 26],
        iconAnchor: [13, 13],
    })

    marker = L.marker([lat, lng], { icon, draggable: true }).addTo(map)

    marker.on('dragend', () => {
        const pos = marker.getLatLng()
        form.latitude = pos.lat.toFixed(6)
        form.longitude = pos.lng.toFixed(6)
    })

    form.latitude = parseFloat(lat).toFixed(6)
    form.longitude = parseFloat(lng).toFixed(6)
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

// Dismiss error/success messages
const dismissMessage = () => {
    locationError.value = ''
    locationSuccess.value = false
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Pelanggan' : 'Tambah Pelanggan'" />

    <CollectorLayout>
        <div class="pb-24">
            <!-- Header -->
            <div class="bg-blue-600 text-white px-4 py-6">
                <div class="flex items-center gap-3 mb-2">
                    <Link :href="isEditing ? `/collector/customers/${customer.id}` : '/collector/customers'" class="p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h1 class="text-xl font-bold">{{ isEditing ? 'Edit Pelanggan' : 'Tambah Pelanggan Baru' }}</h1>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="p-4 space-y-4">
                <!-- Data Pelanggan -->
                <div class="bg-white rounded-xl p-4 shadow-sm">
                    <h2 class="font-semibold text-gray-800 mb-4">Data Pelanggan</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="Nama pelanggan"
                                required
                            >
                            <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon *</label>
                            <input
                                v-model="form.phone"
                                type="tel"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="08xxxxxxxxxx"
                                required
                            >
                            <p v-if="form.errors.phone" class="text-red-500 text-sm mt-1">{{ form.errors.phone }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Alamat *</label>
                            <textarea
                                v-model="form.address"
                                rows="3"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="Alamat lengkap"
                                required
                            ></textarea>
                            <p v-if="form.errors.address" class="text-red-500 text-sm mt-1">{{ form.errors.address }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kelurahan/Desa</label>
                            <input
                                v-model="form.kelurahan"
                                type="text"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="Nama kelurahan/desa"
                            >
                        </div>
                    </div>
                </div>

                <!-- Paket & Area -->
                <div class="bg-white rounded-xl p-4 shadow-sm">
                    <h2 class="font-semibold text-gray-800 mb-4">Paket & Area</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Paket *</label>
                            <select
                                v-model="form.package_id"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                required
                            >
                                <option value="">Pilih Paket</option>
                                <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">
                                    {{ pkg.name }} - Rp {{ Number(pkg.price).toLocaleString('id-ID') }}
                                </option>
                            </select>
                            <p v-if="form.errors.package_id" class="text-red-500 text-sm mt-1">{{ form.errors.package_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Area *</label>
                            <select
                                v-model="form.area_id"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                required
                            >
                                <option value="">Pilih Area</option>
                                <option v-for="area in areas" :key="area.id" :value="area.id">
                                    {{ area.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.area_id" class="text-red-500 text-sm mt-1">{{ form.errors.area_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Router</label>
                            <select
                                v-model="form.router_id"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Pilih Router</option>
                                <option v-for="router in routers" :key="router.id" :value="router.id">
                                    {{ router.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ODP</label>
                            <select
                                v-model="form.odp_id"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Pilih ODP</option>
                                <option v-for="odp in filteredOdps" :key="odp.id" :value="odp.id">
                                    {{ odp.name }} ({{ odp.code }})
                                </option>
                            </select>
                            <p v-if="!form.area_id" class="text-gray-500 text-xs mt-1">Pilih area terlebih dahulu</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Tagih *</label>
                            <select
                                v-model="form.billing_date"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                required
                            >
                                <option v-for="d in 28" :key="d" :value="d">Tanggal {{ d }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hutang Awal (Rp)</label>
                            <input
                                v-model="form.total_debt"
                                type="number"
                                min="0"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="0"
                            >
                            <p class="text-gray-500 text-xs mt-1">Kosongkan jika tidak ada hutang</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Rapel (Bulan)</label>
                            <select
                                v-model="form.rapel_months"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Tidak Rapel</option>
                                <option v-for="m in 12" :key="m" :value="m">{{ m }} Bulan</option>
                            </select>
                            <p class="text-gray-500 text-xs mt-1">Toleransi pembayaran rapel (jika berlaku)</p>
                        </div>
                    </div>
                </div>

                <!-- Koneksi -->
                <div class="bg-white rounded-xl p-4 shadow-sm">
                    <h2 class="font-semibold text-gray-800 mb-4">Data Koneksi</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Koneksi *</label>
                            <select
                                v-model="form.connection_type"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                required
                            >
                                <option value="pppoe">PPPoE</option>
                                <option value="static">Static IP</option>
                            </select>
                        </div>

                        <div v-if="form.connection_type === 'pppoe'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username PPPoE</label>
                            <input
                                v-model="form.pppoe_username"
                                type="text"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="username@domain"
                            >
                            <p class="text-gray-500 text-xs mt-1">Password otomatis: client001</p>
                        </div>

                        <div v-if="form.connection_type === 'static'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">IP Address</label>
                            <input
                                v-model="form.ip_address"
                                type="text"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="192.168.x.x"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Merk Router</label>
                            <input
                                v-model="form.onu_serial"
                                type="text"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="Contoh: TP-Link, Totolink, ZTE F609"
                            >
                        </div>
                    </div>
                </div>

                <!-- Lokasi -->
                <div class="bg-white rounded-xl p-4 shadow-sm">
                    <h2 class="font-semibold text-gray-800 mb-4">Lokasi</h2>

                    <div class="space-y-4">
                        <!-- Success Message -->
                        <div
                            v-if="locationSuccess"
                            class="p-3 bg-green-50 border border-green-200 rounded-lg flex items-center justify-between"
                        >
                            <div class="flex items-center gap-2 text-green-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-sm">Lokasi berhasil didapatkan!</span>
                            </div>
                            <button type="button" @click="dismissMessage" class="text-green-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Error Message -->
                        <div
                            v-if="locationError"
                            class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-start gap-2 text-yellow-800">
                                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <span class="text-sm">{{ locationError }}</span>
                                </div>
                                <button type="button" @click="dismissMessage" class="text-yellow-600 flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Coordinate inputs with buttons -->
                        <div class="flex gap-2">
                            <input
                                v-model="form.latitude"
                                type="text"
                                placeholder="Latitude (-8.xxxx)"
                                class="flex-1 px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                            >
                            <input
                                v-model="form.longitude"
                                type="text"
                                placeholder="Longitude (111.xxxx)"
                                class="flex-1 px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                            >
                        </div>

                        <!-- Location Buttons -->
                        <div class="flex gap-2">
                            <!-- GPS Button -->
                            <button
                                type="button"
                                @click="getLocation"
                                :disabled="locating"
                                class="flex-1 py-3 bg-green-600 text-white rounded-lg font-medium flex items-center justify-center gap-2 disabled:opacity-50"
                            >
                                <svg class="w-5 h-5" :class="{ 'animate-pulse': locating }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                {{ locating ? 'Mencari...' : 'GPS' }}
                            </button>

                            <!-- Map Picker Button -->
                            <button
                                type="button"
                                @click="openMapPicker"
                                class="flex-1 py-3 bg-blue-600 text-white rounded-lg font-medium flex items-center justify-center gap-2"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                                Pilih di Peta
                            </button>
                        </div>

                        <p class="text-xs text-gray-500 text-center">
                            Klik GPS untuk lokasi otomatis atau Pilih di Peta untuk memilih manual.
                        </p>
                    </div>
                </div>

                <!-- Catatan -->
                <div class="bg-white rounded-xl p-4 shadow-sm">
                    <h2 class="font-semibold text-gray-800 mb-4">Catatan</h2>

                    <textarea
                        v-model="form.notes"
                        rows="3"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="Catatan tambahan (opsional)"
                    ></textarea>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-4 bg-blue-600 text-white rounded-xl font-semibold disabled:opacity-50"
                >
                    <span v-if="form.processing">Menyimpan...</span>
                    <span v-else>{{ isEditing ? 'Simpan Perubahan' : 'Tambah Pelanggan' }}</span>
                </button>
            </form>
        </div>

        <!-- Map Picker Modal -->
        <Teleport to="body">
            <div v-if="showMapPicker" class="fixed inset-0 z-[9999] bg-white">
                <!-- Header -->
                <div class="absolute top-0 left-0 right-0 z-10 flex items-center justify-between p-4 border-b bg-white">
                    <h3 class="text-lg font-semibold">Pilih Lokasi Pelanggan</h3>
                    <button type="button" @click="closeMapPicker" class="p-2 text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Map Container - Full height minus header and footer -->
                <div ref="mapContainer" class="absolute top-[60px] bottom-[140px] left-0 right-0"></div>

                <!-- Footer -->
                <div class="absolute bottom-0 left-0 right-0 p-4 border-t bg-white">
                    <div class="flex flex-col gap-3">
                        <div class="text-sm text-gray-600 text-center">
                            <span v-if="form.latitude && form.longitude">
                                📍 {{ form.latitude }}, {{ form.longitude }}
                            </span>
                            <span v-else class="text-gray-400">Ketuk peta untuk memilih lokasi</span>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="getLocation"
                                :disabled="locating"
                                class="flex-1 py-3 bg-green-600 text-white rounded-lg font-medium flex items-center justify-center gap-2 disabled:opacity-50"
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
                                class="flex-1 py-3 bg-blue-600 text-white rounded-lg font-medium disabled:opacity-50"
                            >
                                Konfirmasi Lokasi
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </CollectorLayout>
</template>

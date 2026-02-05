<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    customer: Object,
    packages: Array,
    areas: Array,
    routers: Array,
    collectors: Array,
})

const isEdit = computed(() => !!props.customer)

// Map related
const showMapPicker = ref(false)
const mapContainer = ref(null)
const locating = ref(false)
const locationError = ref('')
let map = null
let marker = null
let L = null

const form = useForm({
    name: props.customer?.name || '',
    address: props.customer?.address || '',
    rt_rw: props.customer?.rt_rw || '',
    kelurahan: props.customer?.kelurahan || '',
    kecamatan: props.customer?.kecamatan || '',
    phone: props.customer?.phone || '',
    phone_alt: props.customer?.phone_alt || '',
    email: props.customer?.email || '',
    nik: props.customer?.nik || '',
    package_id: props.customer?.package_id || '',
    area_id: props.customer?.area_id || '',
    router_id: props.customer?.router_id || '',
    collector_id: props.customer?.collector_id || '',
    connection_type: props.customer?.connection_type || 'pppoe',
    pppoe_username: props.customer?.pppoe_username || '',
    pppoe_password: props.customer?.pppoe_password || '',
    ip_address: props.customer?.ip_address || '',
    mac_address: props.customer?.mac_address || '',
    onu_serial: props.customer?.onu_serial || '',
    status: props.customer?.status || 'active',
    billing_type: props.customer?.billing_type || 'postpaid',
    billing_date: props.customer?.billing_date || 1,
    is_rapel: props.customer?.is_rapel || false,
    rapel_months: props.customer?.rapel_months || 3,
    notes: props.customer?.notes || '',
    latitude: props.customer?.latitude || '',
    longitude: props.customer?.longitude || '',
})

// Auto-select router when area changes
const onAreaChange = () => {
    const area = props.areas.find(a => a.id === form.area_id)
    if (area?.router_id) {
        form.router_id = area.router_id
    }
}

// Map picker functions
const openMapPicker = async () => {
    showMapPicker.value = true
    await nextTick()

    if (!L) {
        L = await import('leaflet')
        await import('leaflet/dist/leaflet.css')
    }

    // Default center (Kantor Kecamatan Pule or customer location)
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

    const icon = L.divIcon({
        className: 'customer-marker',
        html: `<div style="background-color: #ef4444; width: 24px; height: 24px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"></div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 30],
    })

    marker = L.marker([lat, lng], { icon, draggable: true }).addTo(map)

    marker.on('dragend', () => {
        const pos = marker.getLatLng()
        form.latitude = pos.lat.toFixed(7)
        form.longitude = pos.lng.toFixed(7)
    })

    form.latitude = lat.toFixed(7)
    form.longitude = lng.toFixed(7)
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

const getCurrentLocation = () => {
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
                form.latitude = latitude.toFixed(7)
                form.longitude = longitude.toFixed(7)
            }

            locating.value = false
        },
        (error) => {
            locating.value = false
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    locationError.value = 'Akses lokasi ditolak. Izinkan akses lokasi di pengaturan browser.'
                    break
                case error.POSITION_UNAVAILABLE:
                    locationError.value = 'Informasi lokasi tidak tersedia.'
                    break
                case error.TIMEOUT:
                    locationError.value = 'Waktu permintaan lokasi habis.'
                    break
                default:
                    locationError.value = 'Gagal mendapatkan lokasi.'
            }
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    )
}

// Submit form
const submit = () => {
    const options = {
        onError: (errors) => {
            console.error('Form errors:', errors)
        },
        onSuccess: () => {
            console.log('Form submitted successfully')
        },
    }

    if (isEdit.value) {
        form.put(`/admin/customers/${props.customer.id}`, options)
    } else {
        form.post('/admin/customers', options)
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Pelanggan' : 'Tambah Pelanggan'" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/customers" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ isEdit ? 'Edit Pelanggan' : 'Tambah Pelanggan' }}
                </h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Form Errors -->
            <div v-if="Object.keys(form.errors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h3 class="text-red-800 font-medium mb-2">Terdapat kesalahan:</h3>
                <ul class="list-disc list-inside text-red-600 text-sm">
                    <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                </ul>
            </div>

            <!-- Data Pribadi -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Data Pribadi</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.name }"
                        >
                        <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon *</label>
                        <input
                            v-model="form.phone"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.phone }"
                            placeholder="08xxxxxxxxxx"
                        >
                        <p v-if="form.errors.phone" class="text-red-500 text-sm mt-1">{{ form.errors.phone }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon Alternatif</label>
                        <input
                            v-model="form.phone_alt"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">NIK</label>
                        <input
                            v-model="form.nik"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            maxlength="16"
                        >
                    </div>
                </div>
            </div>

            <!-- Alamat -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Alamat</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Lengkap *</label>
                        <textarea
                            v-model="form.address"
                            rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.address }"
                        ></textarea>
                        <p v-if="form.errors.address" class="text-red-500 text-sm mt-1">{{ form.errors.address }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">RT/RW</label>
                        <input
                            v-model="form.rt_rw"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="001/002"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kelurahan</label>
                        <input
                            v-model="form.kelurahan"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kecamatan</label>
                        <input
                            v-model="form.kecamatan"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Koordinat (Lat, Long)</label>

                        <!-- Location Error -->
                        <div v-if="locationError" class="mb-2 p-2 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm flex items-center justify-between">
                            <span>{{ locationError }}</span>
                            <button type="button" @click="locationError = ''" class="text-red-400 hover:text-red-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex gap-2">
                            <input
                                v-model="form.latitude"
                                type="text"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="Latitude"
                            >
                            <input
                                v-model="form.longitude"
                                type="text"
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="Longitude"
                            >
                            <!-- Get Current Location -->
                            <button
                                type="button"
                                @click="getCurrentLocation"
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
                        <p class="text-xs text-gray-500 mt-1">Klik tombol hijau untuk lokasi saat ini, atau tombol biru untuk pilih di peta</p>
                    </div>
                </div>
            </div>

            <!-- Layanan -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Layanan</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Paket *</label>
                        <select
                            v-model="form.package_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.package_id }"
                        >
                            <option value="">Pilih Paket</option>
                            <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">
                                {{ pkg.name }} - Rp {{ new Intl.NumberFormat('id-ID').format(pkg.price) }}
                            </option>
                        </select>
                        <p v-if="form.errors.package_id" class="text-red-500 text-sm mt-1">{{ form.errors.package_id }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Area *</label>
                        <select
                            v-model="form.area_id"
                            @change="onAreaChange"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.area_id }"
                        >
                            <option value="">Pilih Area</option>
                            <option v-for="area in areas" :key="area.id" :value="area.id">
                                {{ area.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.area_id" class="text-red-500 text-sm mt-1">{{ form.errors.area_id }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Router *</label>
                        <select
                            v-model="form.router_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.router_id }"
                        >
                            <option value="">Pilih Router</option>
                            <option v-for="router in routers" :key="router.id" :value="router.id">
                                {{ router.name }} ({{ router.ip_address }})
                            </option>
                        </select>
                        <p v-if="form.errors.router_id" class="text-red-500 text-sm mt-1">{{ form.errors.router_id }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Penagih</label>
                        <select
                            v-model="form.collector_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Pilih Penagih</option>
                            <option v-for="collector in collectors" :key="collector.id" :value="collector.id">
                                {{ collector.name }}
                            </option>
                        </select>
                    </div>

                    <div v-if="isEdit">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select
                            v-model="form.status"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="active">Aktif</option>
                            <option value="isolated">Isolir</option>
                            <option value="suspended">Suspend</option>
                            <option value="terminated">Terminated</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Koneksi -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Data Koneksi</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Tipe Koneksi -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Koneksi *</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    v-model="form.connection_type"
                                    value="pppoe"
                                    class="w-4 h-4 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="text-sm text-gray-700">PPPoE</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    v-model="form.connection_type"
                                    value="static"
                                    class="w-4 h-4 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="text-sm text-gray-700">Static IP</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    v-model="form.connection_type"
                                    value="hotspot"
                                    class="w-4 h-4 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="text-sm text-gray-700">Hotspot</span>
                            </label>
                        </div>
                    </div>

                    <!-- PPPoE Fields (show when pppoe or hotspot) -->
                    <template v-if="form.connection_type === 'pppoe' || form.connection_type === 'hotspot'">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ form.connection_type === 'hotspot' ? 'Hotspot Username' : 'PPPoE Username' }}
                            </label>
                            <input
                                v-model="form.pppoe_username"
                                type="text"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                :placeholder="form.connection_type === 'hotspot' ? 'Username hotspot' : 'Username PPPoE'"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ form.connection_type === 'hotspot' ? 'Hotspot Password' : 'PPPoE Password' }}
                            </label>
                            <input
                                v-model="form.pppoe_password"
                                type="text"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                :placeholder="form.connection_type === 'hotspot' ? 'Password hotspot' : 'Password PPPoE'"
                            >
                        </div>
                    </template>

                    <!-- Static IP Field (show when static) -->
                    <div v-if="form.connection_type === 'static'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">IP Address Pelanggan *</label>
                        <input
                            v-model="form.ip_address"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.ip_address }"
                            placeholder="192.168.x.x"
                        >
                        <p v-if="form.errors.ip_address" class="text-red-500 text-sm mt-1">{{ form.errors.ip_address }}</p>
                    </div>

                    <!-- MAC Address (show for static) -->
                    <div v-if="form.connection_type === 'static'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">MAC Address</label>
                        <input
                            v-model="form.mac_address"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="XX:XX:XX:XX:XX:XX"
                        >
                    </div>

                    <!-- Merk Router (always visible) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Merk Router</label>
                        <input
                            v-model="form.onu_serial"
                            type="text"
                            placeholder="Contoh: TP-Link, Totolink, ZTE F609"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                    </div>
                </div>
            </div>

            <!-- Billing -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Pengaturan Billing</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Billing</label>
                        <select
                            v-model="form.billing_type"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="postpaid">Postpaid (Bayar Setelah)</option>
                            <option value="prepaid">Prepaid (Bayar Di Muka)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Billing</label>
                        <select
                            v-model="form.billing_date"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="d in 28" :key="d" :value="d">Tanggal {{ d }}</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="flex items-center gap-3">
                            <input
                                v-model="form.is_rapel"
                                type="checkbox"
                                class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            >
                            <span class="text-sm font-medium text-gray-700">Pelanggan Rapel (Bayar Beberapa Bulan Sekaligus)</span>
                        </label>
                    </div>

                    <div v-if="form.is_rapel">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Batas Bulan Rapel</label>
                        <select
                            v-model="form.rapel_months"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="m in 12" :key="m" :value="m">{{ m }} Bulan</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Catatan -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Catatan</h2>

                <textarea
                    v-model="form.notes"
                    rows="3"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    placeholder="Catatan tambahan..."
                ></textarea>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4">
                <Link
                    href="/admin/customers"
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
                            <h3 class="text-lg font-semibold">Pilih Lokasi di Peta</h3>
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
                                    <span v-else class="text-gray-400">Klik pada peta untuk memilih lokasi</span>
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        @click="getCurrentLocation"
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

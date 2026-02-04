<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import CollectorLayout from '@/Layouts/CollectorLayout.vue'

const props = defineProps({
    customer: Object,
    packages: Array,
    areas: Array,
    routers: Array,
})

const isEditing = computed(() => !!props.customer?.id)

const form = useForm({
    name: props.customer?.name || '',
    phone: props.customer?.phone || '',
    address: props.customer?.address || '',
    package_id: props.customer?.package_id || '',
    area_id: props.customer?.area_id || '',
    router_id: props.customer?.router_id || '',
    pppoe_username: props.customer?.pppoe_username || '',
    pppoe_password: props.customer?.pppoe_password || '',
    ip_address: props.customer?.ip_address || '',
    onu_serial: props.customer?.onu_serial || '',
    connection_type: props.customer?.connection_type || 'pppoe',
    billing_date: props.customer?.billing_date || 1,
    notes: props.customer?.notes || '',
    latitude: props.customer?.latitude || '',
    longitude: props.customer?.longitude || '',
})

const submit = () => {
    if (isEditing.value) {
        form.put(`/collector/customers/${props.customer.id}`)
    } else {
        form.post('/collector/customers')
    }
}

// Get current location
const getCurrentLocation = () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                form.latitude = position.coords.latitude
                form.longitude = position.coords.longitude
            },
            (error) => {
                alert('Gagal mendapatkan lokasi: ' + error.message)
            }
        )
    } else {
        alert('Browser tidak mendukung geolocation')
    }
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Tagih *</label>
                            <select
                                v-model="form.billing_date"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                required
                            >
                                <option v-for="d in 28" :key="d" :value="d">Tanggal {{ d }}</option>
                            </select>
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
                        </div>

                        <div v-if="form.connection_type === 'pppoe'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password PPPoE</label>
                            <input
                                v-model="form.pppoe_password"
                                type="text"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="Password"
                            >
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
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                                <input
                                    v-model="form.latitude"
                                    type="text"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="-6.xxxxx"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                                <input
                                    v-model="form.longitude"
                                    type="text"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="106.xxxxx"
                                >
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="getCurrentLocation"
                            class="w-full py-3 border border-blue-500 text-blue-600 rounded-lg font-medium flex items-center justify-center gap-2"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Ambil Lokasi Saat Ini
                        </button>
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
    </CollectorLayout>
</template>

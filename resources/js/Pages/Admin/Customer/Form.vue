<script setup>
import { ref, computed } from 'vue'
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

// Submit form
const submit = () => {
    if (isEdit.value) {
        form.put(`/admin/customers/${props.customer.id}`)
    } else {
        form.post('/admin/customers')
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

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Koordinat (Lat, Long)</label>
                        <div class="flex gap-2">
                            <input
                                v-model="form.latitude"
                                type="text"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="Latitude"
                            >
                            <input
                                v-model="form.longitude"
                                type="text"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="Longitude"
                            >
                        </div>
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">PPPoE Username</label>
                        <input
                            v-model="form.pppoe_username"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">PPPoE Password</label>
                        <input
                            v-model="form.pppoe_password"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">IP Address</label>
                        <input
                            v-model="form.ip_address"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="192.168.x.x"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">MAC Address</label>
                        <input
                            v-model="form.mac_address"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="XX:XX:XX:XX:XX:XX"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ONU Serial</label>
                        <input
                            v-model="form.onu_serial"
                            type="text"
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
    </AdminLayout>
</template>

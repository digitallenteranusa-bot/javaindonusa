<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    odp: Object,
    areas: Array,
    poleTypes: Object,
})

const isEdit = computed(() => !!props.odp)

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

const getLocation = () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                form.latitude = position.coords.latitude.toFixed(8)
                form.longitude = position.coords.longitude.toFixed(8)
            },
            (error) => {
                alert('Tidak dapat mengakses lokasi: ' + error.message)
            }
        )
    } else {
        alert('Browser tidak mendukung geolocation')
    }
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
                        <div class="grid grid-cols-5 gap-2">
                            <input
                                v-model="form.latitude"
                                type="text"
                                placeholder="Latitude"
                                class="col-span-2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                            <input
                                v-model="form.longitude"
                                type="text"
                                placeholder="Longitude"
                                class="col-span-2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                            <button
                                type="button"
                                @click="getLocation"
                                class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center justify-center"
                                title="Ambil lokasi saat ini"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Klik tombol lokasi untuk mengambil koordinat dari GPS</p>
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
    </AdminLayout>
</template>

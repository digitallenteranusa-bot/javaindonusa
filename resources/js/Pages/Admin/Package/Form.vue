<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    package: Object,
})

const isEdit = computed(() => !!props.package)

const form = useForm({
    name: props.package?.name || '',
    code: props.package?.code || '',
    description: props.package?.description || '',
    speed_download: props.package?.speed_download || 10240,
    speed_upload: props.package?.speed_upload || 5120,
    price: props.package?.price || '',
    setup_fee: props.package?.setup_fee || 0,
    is_active: props.package?.is_active ?? true,
    mikrotik_profile: props.package?.mikrotik_profile || '',
    burst_limit: props.package?.burst_limit || '',
    burst_threshold: props.package?.burst_threshold || '',
    burst_time: props.package?.burst_time || '',
    priority: props.package?.priority || 8,
    address_list: props.package?.address_list || '',
    sort_order: props.package?.sort_order || 0,
})

// Convert Mbps to Kbps for input
const downloadMbps = computed({
    get: () => form.speed_download / 1024,
    set: (val) => form.speed_download = val * 1024
})

const uploadMbps = computed({
    get: () => form.speed_upload / 1024,
    set: (val) => form.speed_upload = val * 1024
})

// Auto generate code from name
const generateCode = () => {
    if (!isEdit.value && form.name) {
        form.code = form.name
            .toUpperCase()
            .replace(/[^A-Z0-9]/g, '')
            .substring(0, 10)
    }
}

// Submit form
const submit = () => {
    if (isEdit.value) {
        form.put(`/admin/packages/${props.package.id}`)
    } else {
        form.post('/admin/packages')
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Paket' : 'Tambah Paket'" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/packages" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ isEdit ? 'Edit Paket' : 'Tambah Paket' }}
                </h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6">
            <!-- Basic Info -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Informasi Paket</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Paket *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            @blur="generateCode"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.name }"
                            placeholder="Contoh: Paket 10 Mbps"
                        >
                        <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kode Paket *</label>
                        <input
                            v-model="form.code"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 uppercase"
                            :class="{ 'border-red-500': form.errors.code }"
                            placeholder="Contoh: PKT10M"
                        >
                        <p v-if="form.errors.code" class="text-red-500 text-sm mt-1">{{ form.errors.code }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Deskripsi singkat paket..."
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga /bulan *</label>
                            <input
                                v-model="form.price"
                                type="number"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.price }"
                                placeholder="150000"
                            >
                            <p v-if="form.errors.price" class="text-red-500 text-sm mt-1">{{ form.errors.price }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Biaya Pasang</label>
                            <input
                                v-model="form.setup_fee"
                                type="number"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="0"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-3">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            >
                            <span class="text-sm font-medium text-gray-700">Paket Aktif</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Speed Settings -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Pengaturan Kecepatan</h2>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Download (Mbps) *</label>
                        <input
                            v-model="downloadMbps"
                            type="number"
                            step="0.5"
                            min="0.5"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                        <p class="text-xs text-gray-500 mt-1">{{ form.speed_download }} Kbps</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Upload (Mbps) *</label>
                        <input
                            v-model="uploadMbps"
                            type="number"
                            step="0.5"
                            min="0.5"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                        <p class="text-xs text-gray-500 mt-1">{{ form.speed_upload }} Kbps</p>
                    </div>
                </div>
            </div>

            <!-- Mikrotik Settings -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Pengaturan Mikrotik</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Profile Name</label>
                        <input
                            v-model="form.mikrotik_profile"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Auto: pkg-{code}"
                        >
                        <p class="text-xs text-gray-500 mt-1">Kosongkan untuk generate otomatis dari kode</p>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Burst Limit</label>
                            <input
                                v-model="form.burst_limit"
                                type="text"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="0/0"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Burst Threshold</label>
                            <input
                                v-model="form.burst_threshold"
                                type="text"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="0/0"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Burst Time</label>
                            <input
                                v-model="form.burst_time"
                                type="text"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="0s/0s"
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Priority (1-8)</label>
                            <select
                                v-model="form.priority"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="p in 8" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address List</label>
                            <input
                                v-model="form.address_list"
                                type="text"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="Contoh: PAKET-10M"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Urutan Tampil</label>
                        <input
                            v-model="form.sort_order"
                            type="number"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="0"
                        >
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4">
                <Link
                    href="/admin/packages"
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

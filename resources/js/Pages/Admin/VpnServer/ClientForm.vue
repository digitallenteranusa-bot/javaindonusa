<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    client: Object,
    routers: Array,
})

const isEditing = computed(() => !!props.client)

const form = useForm({
    name: props.client?.name || '',
    description: props.client?.description || '',
    protocol: props.client?.protocol || 'openvpn',
    router_id: props.client?.router_id || '',
    mikrotik_lan_subnet: props.client?.mikrotik_lan_subnet || '',
})

const submit = () => {
    if (isEditing.value) {
        form.put(`/admin/vpn-server/clients/${props.client.id}`)
    } else {
        form.post('/admin/vpn-server/clients')
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit VPN Client' : 'Tambah VPN Client'" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/vpn-server" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ isEditing ? 'Edit VPN Client' : 'Tambah VPN Client' }}
                </h1>
            </div>
        </template>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="bg-white rounded-xl shadow-sm p-6 space-y-6">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nama Client <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="Mikrotik-Cabang-A"
                        :disabled="isEditing"
                    >
                    <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
                    <p class="text-xs text-gray-500 mt-1">Nama unik untuk identifikasi client</p>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Deskripsi
                    </label>
                    <input
                        v-model="form.description"
                        type="text"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="Router untuk cabang A di Jl. Contoh No. 123"
                    >
                    <p v-if="form.errors.description" class="text-red-500 text-sm mt-1">{{ form.errors.description }}</p>
                </div>

                <!-- Protocol -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Protocol <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <label
                            :class="[
                                'p-4 border rounded-lg cursor-pointer transition-colors',
                                form.protocol === 'openvpn' ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-gray-400',
                                isEditing ? 'opacity-50 cursor-not-allowed' : ''
                            ]"
                        >
                            <input
                                type="radio"
                                v-model="form.protocol"
                                value="openvpn"
                                class="sr-only"
                                :disabled="isEditing"
                            >
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-blue-100 rounded-lg">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium">OpenVPN</p>
                                    <p class="text-xs text-gray-500">Mikrotik v6 & v7</p>
                                </div>
                            </div>
                        </label>

                        <label
                            :class="[
                                'p-4 border rounded-lg cursor-pointer transition-colors',
                                form.protocol === 'wireguard' ? 'border-purple-500 bg-purple-50' : 'border-gray-300 hover:border-gray-400',
                                isEditing ? 'opacity-50 cursor-not-allowed' : ''
                            ]"
                        >
                            <input
                                type="radio"
                                v-model="form.protocol"
                                value="wireguard"
                                class="sr-only"
                                :disabled="isEditing"
                            >
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-purple-100 rounded-lg">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium">WireGuard</p>
                                    <p class="text-xs text-gray-500">Mikrotik v7+ only</p>
                                </div>
                            </div>
                        </label>
                    </div>
                    <p v-if="form.errors.protocol" class="text-red-500 text-sm mt-1">{{ form.errors.protocol }}</p>
                </div>

                <!-- Router -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Link ke Router (Opsional)
                    </label>
                    <select
                        v-model="form.router_id"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">-- Tidak ada --</option>
                        <option v-for="router in routers" :key="router.id" :value="router.id">
                            {{ router.name }} ({{ router.ip_address }})
                        </option>
                    </select>
                    <p v-if="form.errors.router_id" class="text-red-500 text-sm mt-1">{{ form.errors.router_id }}</p>
                    <p class="text-xs text-gray-500 mt-1">Hubungkan dengan router yang ada di sistem</p>
                </div>

                <!-- Mikrotik LAN Subnet -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Mikrotik LAN Subnet (Opsional)
                    </label>
                    <input
                        v-model="form.mikrotik_lan_subnet"
                        type="text"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="192.168.1.0/24"
                    >
                    <p v-if="form.errors.mikrotik_lan_subnet" class="text-red-500 text-sm mt-1">{{ form.errors.mikrotik_lan_subnet }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        Subnet LAN di belakang Mikrotik. Jika diisi, server akan route traffic ke subnet ini via VPN.
                    </p>
                </div>

                <!-- Protocol Info Box -->
                <div v-if="form.protocol === 'wireguard'" class="p-4 bg-purple-50 rounded-lg">
                    <h3 class="font-medium text-purple-700 mb-2">WireGuard</h3>
                    <ul class="space-y-1 text-sm text-purple-600">
                        <li>Lebih cepat dan efisien daripada OpenVPN</li>
                        <li>Reconnect instant saat koneksi terputus</li>
                        <li>Hanya untuk RouterOS v7 atau lebih baru</li>
                    </ul>
                </div>

                <div v-else class="p-4 bg-blue-50 rounded-lg">
                    <h3 class="font-medium text-blue-700 mb-2">OpenVPN</h3>
                    <ul class="space-y-1 text-sm text-blue-600">
                        <li>Compatible dengan semua versi RouterOS</li>
                        <li>Menggunakan certificate-based authentication</li>
                        <li>Sertifikat akan di-generate otomatis</li>
                    </ul>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-4 pt-4 border-t">
                    <Link
                        href="/admin/vpn-server"
                        class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                    >
                        Batal
                    </Link>
                    <button
                        type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Menyimpan...' : (isEditing ? 'Update' : 'Simpan') }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

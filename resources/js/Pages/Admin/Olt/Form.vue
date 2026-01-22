<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    olt: Object,
    types: Object,
    statuses: Object,
    ponPortOptions: Object,
})

const isEdit = computed(() => !!props.olt)

const form = useForm({
    name: props.olt?.name || '',
    ip_address: props.olt?.ip_address || '',
    type: props.olt?.type || 'HIOSO',
    pon_ports: props.olt?.pon_ports || '8',
    username: props.olt?.username || '',
    password: '',
    telnet_port: props.olt?.telnet_port || 23,
    ssh_port: props.olt?.ssh_port || 22,
    snmp_community: props.olt?.snmp_community || '',
    status: props.olt?.status || 'active',
    notes: props.olt?.notes || '',
    firmware_version: props.olt?.firmware_version || '',
})

const submit = () => {
    if (isEdit.value) {
        form.put(`/admin/olts/${props.olt.id}`)
    } else {
        form.post('/admin/olts')
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit OLT' : 'Tambah OLT'" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/olts" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ isEdit ? 'Edit OLT' : 'Tambah OLT' }}
                </h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Informasi OLT</h2>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama OLT *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="OLT Pusat"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.name }"
                            >
                            <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">IP Address *</label>
                            <input
                                v-model="form.ip_address"
                                type="text"
                                placeholder="192.168.1.1"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.ip_address }"
                            >
                            <p v-if="form.errors.ip_address" class="text-red-500 text-sm mt-1">{{ form.errors.ip_address }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipe OLT *</label>
                            <select
                                v-model="form.type"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="(label, value) in types" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah PON Port *</label>
                            <select
                                v-model="form.pon_ports"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="(label, value) in ponPortOptions" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input
                                v-model="form.username"
                                type="text"
                                placeholder="admin"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input
                                v-model="form.password"
                                type="password"
                                :placeholder="isEdit ? '(tidak diubah jika kosong)' : 'Password'"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telnet Port</label>
                            <input
                                v-model="form.telnet_port"
                                type="number"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SSH Port</label>
                            <input
                                v-model="form.ssh_port"
                                type="number"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select
                                v-model="form.status"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="(label, value) in statuses" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">SNMP Community</label>
                            <input
                                v-model="form.snmp_community"
                                type="text"
                                placeholder="public"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Firmware Version</label>
                            <input
                                v-model="form.firmware_version"
                                type="text"
                                placeholder="V1.0.0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                        </div>
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
                </div>
            </div>

            <div class="flex items-center justify-end gap-4">
                <Link
                    href="/admin/olts"
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

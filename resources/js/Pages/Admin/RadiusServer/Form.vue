<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    radiusServer: Object,
    statuses: Object,
})

const isEdit = computed(() => !!props.radiusServer)

const form = useForm({
    name: props.radiusServer?.name || '',
    ip_address: props.radiusServer?.ip_address || '',
    auth_port: props.radiusServer?.auth_port || 1812,
    acct_port: props.radiusServer?.acct_port || 1813,
    secret: '',
    status: props.radiusServer?.status || 'active',
    notes: props.radiusServer?.notes || '',
})

const submit = () => {
    if (isEdit.value) {
        form.put(`/admin/radius-servers/${props.radiusServer.id}`)
    } else {
        form.post('/admin/radius-servers')
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Radius Server' : 'Tambah Radius Server'" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/radius-servers" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ isEdit ? 'Edit Radius Server' : 'Tambah Radius Server' }}
                </h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Informasi Server</h2>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Server *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="RADIUS Primary"
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
                                placeholder="192.168.1.100"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.ip_address }"
                            >
                            <p v-if="form.errors.ip_address" class="text-red-500 text-sm mt-1">{{ form.errors.ip_address }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Auth Port</label>
                            <input
                                v-model="form.auth_port"
                                type="number"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Acct Port</label>
                            <input
                                v-model="form.acct_port"
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

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            RADIUS Secret {{ isEdit ? '' : '*' }}
                        </label>
                        <input
                            v-model="form.secret"
                            type="password"
                            :placeholder="isEdit ? '(tidak diubah jika kosong)' : 'Shared secret'"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.secret }"
                        >
                        <p v-if="form.errors.secret" class="text-red-500 text-sm mt-1">{{ form.errors.secret }}</p>
                        <p class="text-xs text-gray-500 mt-1">Secret yang sama harus dikonfigurasi di router Mikrotik</p>
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
                    href="/admin/radius-servers"
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

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    olts: Object,
    filters: Object,
    types: Object,
    statuses: Object,
    ponPortOptions: Object,
})

const search = ref(props.filters.search || '')
const typeFilter = ref(props.filters.type || '')
const statusFilter = ref(props.filters.status || '')

const applyFilters = () => {
    router.get('/admin/olts', {
        search: search.value || undefined,
        type: typeFilter.value || undefined,
        status: statusFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const deleteOlt = (olt) => {
    if (confirm(`Yakin ingin menghapus OLT ${olt.name}?`)) {
        router.delete(`/admin/olts/${olt.id}`)
    }
}

const checkConnection = (olt) => {
    router.post(`/admin/olts/${olt.id}/check-connection`)
}

const getStatusColor = (status) => {
    return {
        'active': 'bg-green-100 text-green-700',
        'inactive': 'bg-gray-100 text-gray-500',
        'maintenance': 'bg-yellow-100 text-yellow-700',
    }[status] || 'bg-gray-100 text-gray-500'
}
</script>

<template>
    <Head title="OLT" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">OLT (Optical Line Terminal)</h1>
                <Link
                    href="/admin/olts/create"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah OLT
                </Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-wrap gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari OLT..."
                    class="w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    @keyup.enter="applyFilters"
                >
                <select
                    v-model="typeFilter"
                    @change="applyFilters"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Tipe</option>
                    <option v-for="(label, value) in types" :key="value" :value="value">
                        {{ label }}
                    </option>
                </select>
                <select
                    v-model="statusFilter"
                    @change="applyFilters"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Status</option>
                    <option v-for="(label, value) in statuses" :key="value" :value="value">
                        {{ label }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PON Ports</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Check</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="olt in olts.data" :key="olt.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div>
                                <p class="font-medium">{{ olt.name }}</p>
                                <p v-if="olt.firmware_version" class="text-xs text-gray-500">
                                    FW: {{ olt.firmware_version }}
                                </p>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-sm">{{ olt.ip_address }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">
                                {{ olt.type }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">{{ olt.pon_ports }} Port</td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-1 text-xs rounded-full', getStatusColor(olt.status)]">
                                {{ statuses[olt.status] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            {{ olt.last_checked_at ? new Date(olt.last_checked_at).toLocaleString('id-ID') : '-' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <Link
                                    :href="`/admin/olts/${olt.id}`"
                                    class="p-1 text-gray-500 hover:text-blue-600"
                                    title="Detail"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </Link>
                                <button
                                    @click="checkConnection(olt)"
                                    class="p-1 text-gray-500 hover:text-green-600"
                                    title="Test Koneksi"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                    </svg>
                                </button>
                                <Link
                                    :href="`/admin/olts/${olt.id}/edit`"
                                    class="p-1 text-gray-500 hover:text-yellow-600"
                                    title="Edit"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button
                                    @click="deleteOlt(olt)"
                                    class="p-1 text-gray-500 hover:text-red-600"
                                    title="Hapus"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!olts.data?.length" class="text-center py-12 text-gray-500">
                Tidak ada OLT ditemukan
            </div>

            <!-- Pagination -->
            <div v-if="olts.data?.length" class="px-4 py-3 border-t flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Menampilkan {{ olts.from }} - {{ olts.to }} dari {{ olts.total }}
                </p>
                <div class="flex gap-2">
                    <Link
                        v-for="link in olts.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-1 text-sm rounded',
                            link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700',
                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

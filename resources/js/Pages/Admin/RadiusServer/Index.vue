<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    radiusServers: Object,
    filters: Object,
    statuses: Object,
})

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')

const applyFilters = () => {
    router.get('/admin/radius-servers', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const deleteServer = (server) => {
    if (confirm(`Yakin ingin menghapus Radius Server ${server.name}?`)) {
        router.delete(`/admin/radius-servers/${server.id}`)
    }
}

const testConnection = (server) => {
    router.post(`/admin/radius-servers/${server.id}/test-connection`)
}

const getStatusColor = (status) => {
    return {
        'active': 'bg-green-100 text-green-700',
        'inactive': 'bg-gray-100 text-gray-500',
        'testing': 'bg-yellow-100 text-yellow-700',
    }[status] || 'bg-gray-100 text-gray-500'
}
</script>

<template>
    <Head title="Radius Server" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Radius Server</h1>
                    <p class="text-sm text-gray-500 mt-1">Placeholder - Fitur akan dikembangkan lebih lanjut</p>
                </div>
                <Link
                    href="/admin/radius-servers/create"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Server
                </Link>
            </div>
        </template>

        <!-- Info Banner -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <div class="flex gap-3">
                <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <p class="text-sm text-blue-800">
                        Fitur Radius Server saat ini hanya berfungsi sebagai placeholder untuk penyimpanan konfigurasi.
                        Integrasi penuh dengan FreeRADIUS/mikrotik akan dikembangkan pada update berikutnya.
                    </p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-wrap gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari server..."
                    class="w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    @keyup.enter="applyFilters"
                >
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Auth Port</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acct Port</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Router</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="server in radiusServers.data" :key="server.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ server.name }}</td>
                        <td class="px-4 py-3 font-mono text-sm">{{ server.ip_address }}</td>
                        <td class="px-4 py-3 text-sm">{{ server.auth_port }}</td>
                        <td class="px-4 py-3 text-sm">{{ server.acct_port }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">
                                {{ server.routers_count }} router
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-1 text-xs rounded-full', getStatusColor(server.status)]">
                                {{ statuses[server.status] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    @click="testConnection(server)"
                                    class="p-1 text-gray-500 hover:text-green-600"
                                    title="Test Koneksi"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                    </svg>
                                </button>
                                <Link
                                    :href="`/admin/radius-servers/${server.id}/edit`"
                                    class="p-1 text-gray-500 hover:text-yellow-600"
                                    title="Edit"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button
                                    @click="deleteServer(server)"
                                    class="p-1 text-gray-500 hover:text-red-600"
                                    :disabled="server.routers_count > 0"
                                    :class="{ 'opacity-50 cursor-not-allowed': server.routers_count > 0 }"
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

            <div v-if="!radiusServers.data?.length" class="text-center py-12 text-gray-500">
                Tidak ada Radius Server ditemukan
            </div>

            <!-- Pagination -->
            <div v-if="radiusServers.data?.length" class="px-4 py-3 border-t flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Menampilkan {{ radiusServers.from }} - {{ radiusServers.to }} dari {{ radiusServers.total }}
                </p>
                <div class="flex gap-2">
                    <Link
                        v-for="link in radiusServers.links"
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

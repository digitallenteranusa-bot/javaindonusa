<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    radiusServer: Object,
    statuses: Object,
})

const getStatusColor = (status) => {
    return {
        'active': 'bg-green-100 text-green-700',
        'inactive': 'bg-gray-100 text-gray-500',
        'testing': 'bg-yellow-100 text-yellow-700',
    }[status] || 'bg-gray-100 text-gray-500'
}

const testing = ref(false)
const testConnection = () => {
    testing.value = true
    router.post(`/admin/radius-servers/${props.radiusServer.id}/test-connection`, {}, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            testing.value = false
        },
    })
}

const deleteServer = () => {
    if (confirm(`Yakin ingin menghapus Radius Server ${props.radiusServer.name}?`)) {
        router.delete(`/admin/radius-servers/${props.radiusServer.id}`)
    }
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}
</script>

<template>
    <Head :title="`Radius Server - ${radiusServer.name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        href="/admin/radius-servers"
                        class="p-2 rounded-lg hover:bg-gray-100 text-gray-500"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ radiusServer.name }}</h1>
                        <p class="text-sm text-gray-500">Detail Radius Server</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="testConnection"
                        :disabled="testing"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2 disabled:opacity-50"
                    >
                        <svg class="w-5 h-5" :class="{ 'animate-spin': testing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                        {{ testing ? 'Testing...' : 'Test Koneksi' }}
                    </button>
                    <Link
                        :href="`/admin/radius-servers/${radiusServer.id}/edit`"
                        class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                    </Link>
                    <button
                        @click="deleteServer"
                        :disabled="radiusServer.routers?.length > 0"
                        :class="[
                            'px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-2',
                            radiusServer.routers?.length > 0 ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Hapus
                    </button>
                </div>
            </div>
        </template>

        <!-- Server Info -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Informasi Server</h2>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm text-gray-500">Nama</dt>
                        <dd class="font-medium">{{ radiusServer.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Status</dt>
                        <dd>
                            <span :class="['px-2 py-1 text-xs rounded-full', getStatusColor(radiusServer.status)]">
                                {{ statuses[radiusServer.status] }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">IP Address</dt>
                        <dd class="font-mono">{{ radiusServer.ip_address }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Auth Port</dt>
                        <dd class="font-mono">{{ radiusServer.auth_port }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Accounting Port</dt>
                        <dd class="font-mono">{{ radiusServer.acct_port }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Jumlah Router</dt>
                        <dd>
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">
                                {{ radiusServer.routers?.length || 0 }} router
                            </span>
                        </dd>
                    </div>
                    <div class="col-span-2" v-if="radiusServer.notes">
                        <dt class="text-sm text-gray-500">Catatan</dt>
                        <dd class="text-gray-700 whitespace-pre-line">{{ radiusServer.notes }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Dibuat</dt>
                        <dd class="text-sm text-gray-600">{{ formatDate(radiusServer.created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Diperbarui</dt>
                        <dd class="text-sm text-gray-600">{{ formatDate(radiusServer.updated_at) }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Mikrotik Config Guide -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Konfigurasi Mikrotik</h2>

                <div v-if="radiusServer.routers?.length" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-700 font-medium">{{ radiusServer.routers.length }} router sudah terhubung</p>
                    <p class="text-xs text-green-600 mt-1">Pastikan konfigurasi RADIUS di setiap Mikrotik sudah benar</p>
                </div>

                <p class="text-sm text-gray-500 mb-3">Perintah di terminal Mikrotik:</p>
                <div class="bg-gray-900 text-green-400 rounded-lg p-4 text-xs font-mono space-y-2 overflow-x-auto">
                    <p class="text-gray-500"># Tambah RADIUS server</p>
                    <p>/radius add service=ppp \</p>
                    <p class="pl-4">address=&lt;IP_PUBLIK_VPS&gt; \</p>
                    <p class="pl-4">secret=&lt;SECRET&gt; \</p>
                    <p class="pl-4">authentication-port={{ radiusServer.auth_port }} \</p>
                    <p class="pl-4">accounting-port={{ radiusServer.acct_port }} \</p>
                    <p class="pl-4">timeout=3000</p>
                    <p class="mt-3 text-gray-500"># Enable RADIUS di PPP</p>
                    <p>/ppp aaa set use-radius=yes \</p>
                    <p class="pl-4">accounting=yes \</p>
                    <p class="pl-4">interim-update=5m</p>
                </div>
                <div class="mt-3 text-xs text-gray-400 space-y-1">
                    <p>&lt;IP_PUBLIK_VPS&gt; = IP publik server billing yang bisa dijangkau dari Mikrotik</p>
                    <p>&lt;SECRET&gt; = RADIUS shared secret yang dikonfigurasi di server ini</p>
                </div>
            </div>
        </div>

        <!-- Router List -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h2 class="text-lg font-semibold">Router yang Terhubung</h2>
            </div>

            <table v-if="radiusServer.routers?.length" class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Identity</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="r in radiusServer.routers" :key="r.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ r.name }}</td>
                        <td class="px-4 py-3 font-mono text-sm">{{ r.ip_address }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ r.identity || '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span
                                :class="[
                                    'inline-block w-2.5 h-2.5 rounded-full',
                                    r.is_active ? 'bg-green-500' : 'bg-gray-400'
                                ]"
                                :title="r.is_active ? 'Aktif' : 'Nonaktif'"
                            ></span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="`/admin/routers/${r.id}`"
                                class="text-blue-600 hover:text-blue-800 text-sm"
                            >
                                Lihat
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-else class="text-center py-12 text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                </svg>
                <p>Belum ada router yang terhubung ke server ini</p>
                <p class="text-sm mt-1">Assign router ke RADIUS server ini dari menu <Link href="/admin/routers" class="text-blue-600 hover:underline">Router</Link></p>
            </div>
        </div>
    </AdminLayout>
</template>

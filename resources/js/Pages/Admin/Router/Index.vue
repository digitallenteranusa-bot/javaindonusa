<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    routers: Object,
    filters: Object,
})

const search = ref(props.filters.search || '')

// Apply search
const applySearch = () => {
    router.get('/admin/routers', {
        search: search.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Test connection
const testConnection = (rt) => {
    router.post(`/admin/routers/${rt.id}/test-connection`)
}

// Sync info
const syncInfo = (rt) => {
    router.post(`/admin/routers/${rt.id}/sync-info`)
}

// Toggle active
const toggleActive = (rt) => {
    router.post(`/admin/routers/${rt.id}/toggle-active`)
}

// Delete router
const deleteRouter = (rt) => {
    if (confirm(`Yakin ingin menghapus router ${rt.name}?`)) {
        router.delete(`/admin/routers/${rt.id}`)
    }
}

// Format date
const formatDate = (date) => {
    if (!date) return 'Belum pernah'
    return new Date(date).toLocaleString('id-ID')
}

// Check if online
const isOnline = (rt) => {
    return rt.is_active
}
</script>

<template>
    <Head title="Router" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Router Mikrotik</h1>
                <Link
                    href="/admin/routers/create"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Router
                </Link>
            </div>
        </template>

        <!-- Search -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <input
                v-model="search"
                type="text"
                placeholder="Cari router..."
                class="w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                @keyup.enter="applySearch"
            >
        </div>

        <!-- Router Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="rt in routers.data"
                :key="rt.id"
                class="bg-white rounded-xl shadow-sm overflow-hidden"
            >
                <!-- Header -->
                <div class="p-4 bg-gray-50 border-b flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            :class="[
                                'w-3 h-3 rounded-full',
                                isOnline(rt) ? 'bg-green-500' : 'bg-red-500'
                            ]"
                        ></div>
                        <div>
                            <h3 class="font-semibold">{{ rt.name }}</h3>
                            <p class="text-xs text-gray-500">{{ rt.identity || 'Unknown' }}</p>
                        </div>
                    </div>
                    <span
                        :class="[
                            'px-2 py-1 text-xs rounded-full',
                            rt.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                        ]"
                    >
                        {{ rt.is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>

                <!-- Body -->
                <div class="p-4 space-y-3">
                    <div class="flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                        <span class="font-mono">{{ rt.ip_address }}:{{ rt.api_port }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <p class="text-gray-500">Version</p>
                            <p class="font-medium">{{ rt.version || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Model</p>
                            <p class="font-medium">{{ rt.model || '-' }}</p>
                        </div>
                    </div>

                    <div v-if="rt.cpu_load !== null || rt.memory_usage !== null" class="space-y-2">
                        <div>
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>CPU</span>
                                <span>{{ rt.cpu_load || 0 }}%</span>
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-blue-500"
                                    :style="{ width: (rt.cpu_load || 0) + '%' }"
                                ></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Memory</span>
                                <span>{{ rt.memory_usage || 0 }}%</span>
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-green-500"
                                    :style="{ width: (rt.memory_usage || 0) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>{{ rt.customers_count || 0 }} pelanggan</span>
                    </div>

                    <p class="text-xs text-gray-500">
                        Terakhir terhubung: {{ formatDate(rt.last_connected_at) }}
                    </p>
                </div>

                <!-- Footer -->
                <div class="px-4 py-3 bg-gray-50 border-t flex gap-2">
                    <button
                        @click="testConnection(rt)"
                        class="flex-1 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-xs font-medium"
                    >
                        Test Koneksi
                    </button>
                    <button
                        @click="syncInfo(rt)"
                        class="flex-1 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 text-xs font-medium"
                    >
                        Sync Info
                    </button>
                    <Link
                        :href="`/admin/routers/${rt.id}/edit`"
                        class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </Link>
                    <button
                        @click="deleteRouter(rt)"
                        class="px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200"
                        :disabled="rt.customers_count > 0"
                        :class="{ 'opacity-50 cursor-not-allowed': rt.customers_count > 0 }"
                        :title="rt.customers_count > 0 ? 'Router masih memiliki pelanggan' : 'Hapus router'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!routers.data?.length" class="bg-white rounded-xl shadow-sm p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
            </svg>
            <p class="mt-4 text-gray-500">Belum ada router</p>
            <Link
                href="/admin/routers/create"
                class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
                Tambah Router Pertama
            </Link>
        </div>

        <!-- Pagination -->
        <div v-if="routers.data?.length && routers.last_page > 1" class="mt-6 flex justify-center">
            <div class="flex gap-2">
                <Link
                    v-for="link in routers.links"
                    :key="link.label"
                    :href="link.url"
                    :class="[
                        'px-3 py-1 text-sm rounded',
                        link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100',
                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import debounce from 'lodash/debounce'

const props = defineProps({
    brands: Array,
    stats: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')

// Debounced search
const doSearch = debounce(() => {
    router.get('/admin/router-brands', {
        search: search.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}, 300)

watch(search, doSearch)

// Get status color
const getStatusColor = (status) => {
    const colors = {
        active: 'text-green-600',
        isolated: 'text-red-600',
        suspended: 'text-yellow-600',
        terminated: 'text-gray-600',
    }
    return colors[status] || 'text-gray-600'
}

// Calculate percentage
const getPercentage = (count, total) => {
    if (!total) return 0
    return ((count / total) * 100).toFixed(1)
}
</script>

<template>
    <Head title="Daftar Merk Router Pelanggan" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Daftar Merk Router Pelanggan</h1>
        </template>

        <div class="space-y-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <p class="text-sm text-gray-500">Total Merk</p>
                    <p class="text-2xl font-bold text-gray-900">{{ stats.unique_brands }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <p class="text-sm text-gray-500">Total Terpasang</p>
                    <p class="text-2xl font-bold text-blue-600">{{ stats.total_routers }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <p class="text-sm text-gray-500">Aktif</p>
                    <p class="text-2xl font-bold text-green-600">{{ stats.total_active }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <p class="text-sm text-gray-500">Isolir</p>
                    <p class="text-2xl font-bold text-red-600">{{ stats.total_isolated }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <p class="text-sm text-gray-500">Belum Diisi</p>
                    <p class="text-2xl font-bold text-gray-400">{{ stats.no_router_count }}</p>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari merk router..."
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                    </div>
                </div>
            </div>

            <!-- Router Brands Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    No
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Merk Router
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aktif
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Isolir
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Suspend
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Berhenti
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Persentase
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(brand, index) in brands" :key="brand.brand" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ index + 1 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">{{ brand.brand || 'Tidak Diketahui' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ brand.total }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-green-600 font-medium">{{ brand.active_count }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-red-600 font-medium">{{ brand.isolated_count }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-yellow-600 font-medium">{{ brand.suspended_count }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="text-gray-600 font-medium">{{ brand.terminated_count }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="w-20 bg-gray-200 rounded-full h-2">
                                            <div
                                                class="bg-blue-600 h-2 rounded-full"
                                                :style="{ width: getPercentage(brand.total, stats.total_routers) + '%' }"
                                            ></div>
                                        </div>
                                        <span class="text-sm text-gray-600">{{ getPercentage(brand.total, stats.total_routers) }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <Link
                                        :href="`/admin/router-brands/${encodeURIComponent(brand.brand)}`"
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                    >
                                        Lihat Detail
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="brands.length === 0">
                                <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                                        </svg>
                                        <p>Belum ada data merk router</p>
                                        <p class="text-sm mt-1">Isi kolom "Merk Router" pada data pelanggan</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Info -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <h4 class="font-medium text-blue-900">Info</h4>
                        <p class="text-sm text-blue-700 mt-1">
                            Data ini diambil dari kolom "Merk Router" pada data pelanggan.
                            Untuk menambah atau mengubah merk router, edit data pelanggan yang bersangkutan.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
    brand: String,
    customers: Object,
})

// Get status badge
const getStatusBadge = (status) => {
    const badges = {
        active: { class: 'bg-green-100 text-green-800', label: 'Aktif' },
        isolated: { class: 'bg-red-100 text-red-800', label: 'Isolir' },
        suspended: { class: 'bg-yellow-100 text-yellow-800', label: 'Suspend' },
        terminated: { class: 'bg-gray-100 text-gray-800', label: 'Berhenti' },
    }
    return badges[status] || { class: 'bg-gray-100 text-gray-800', label: status }
}

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}
</script>

<template>
    <Head :title="`Router: ${brand}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link href="/admin/router-brands" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-semibold text-gray-900">Pelanggan dengan Router: {{ brand }}</h1>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Stats -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">{{ brand }}</h2>
                        <p class="text-gray-500">{{ customers.total }} pelanggan menggunakan router ini</p>
                    </div>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Pelanggan
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Paket
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Area
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Hutang
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ customer.name }}</p>
                                        <p class="text-sm text-gray-500">{{ customer.customer_id }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-gray-900">{{ customer.package?.name || '-' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-gray-900">{{ customer.area?.name || '-' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                            getStatusBadge(customer.status).class
                                        ]"
                                    >
                                        {{ getStatusBadge(customer.status).label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <span :class="customer.total_debt > 0 ? 'text-red-600 font-medium' : 'text-gray-500'">
                                        {{ formatCurrency(customer.total_debt) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <Link
                                        :href="`/admin/customers/${customer.id}`"
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                    >
                                        Detail
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="customers.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    Tidak ada pelanggan dengan router ini
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="customers.data.length > 0" class="px-6 py-4 border-t">
                    <Pagination :links="customers.links" />
                </div>
            </div>

            <!-- Back Button -->
            <div>
                <Link
                    href="/admin/router-brands"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Kembali ke Daftar
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    plans: Object,
    filters: Object,
    stats: Object,
})

const formatCurrency = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v || 0)
const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' }) : '-'

const statusClass = (s) => ({
    active: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
    defaulted: 'bg-red-100 text-red-700',
}[s] || 'bg-gray-100 text-gray-700')

const statusLabel = (s) => ({ active: 'Berjalan', completed: 'Lunas', cancelled: 'Dibatalkan', defaulted: 'Gagal Bayar' }[s] || s)

const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')

const applyFilters = () => {
    router.get('/admin/payment-plans', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true })
}
</script>

<template>
    <Head title="Cicilan" />
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Payment Plans (Cicilan)</h1>
                <Link href="/admin/payment-plans/create" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                    + Buat Cicilan
                </Link>
            </div>
        </template>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Cicilan Aktif</p>
                <p class="text-2xl font-bold text-blue-600">{{ stats.active }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Selesai</p>
                <p class="text-2xl font-bold text-green-600">{{ stats.completed }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Sisa Cicilan Aktif</p>
                <p class="text-2xl font-bold">{{ formatCurrency(stats.total_active_amount) }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-wrap gap-4">
                <input v-model="search" @keyup.enter="applyFilters" placeholder="Cari pelanggan..." class="px-4 py-2 border rounded-lg text-sm w-64" />
                <select v-model="statusFilter" @change="applyFilters" class="px-4 py-2 border rounded-lg text-sm">
                    <option value="">Semua Status</option>
                    <option value="active">Berjalan</option>
                    <option value="completed">Lunas</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Pelanggan</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">Total Hutang</th>
                        <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">Cicilan</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">Per Bulan</th>
                        <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">Progress</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Periode</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Status</th>
                        <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="plan in plans.data" :key="plan.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">
                            <p class="font-medium">{{ plan.customer?.name }}</p>
                            <p class="text-xs text-gray-500">{{ plan.customer?.customer_id }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-semibold">{{ formatCurrency(plan.total_debt_amount) }}</td>
                        <td class="px-4 py-3 text-sm text-center">{{ plan.installment_count }}x</td>
                        <td class="px-4 py-3 text-sm text-right">{{ formatCurrency(plan.installment_amount) }}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" :style="{ width: `${Math.min(100, (plan.paid_amount / plan.total_debt_amount * 100))}%` }"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">{{ formatCurrency(plan.paid_amount) }} / {{ formatCurrency(plan.total_debt_amount) }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            {{ formatDate(plan.start_date) }} - {{ formatDate(plan.end_date) }}
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-1 text-xs rounded-full', statusClass(plan.status)]">{{ statusLabel(plan.status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <Link :href="`/admin/payment-plans/${plan.id}`" class="text-blue-600 hover:underline text-sm">Detail</Link>
                        </td>
                    </tr>
                    <tr v-if="!plans.data?.length">
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">Belum ada cicilan</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

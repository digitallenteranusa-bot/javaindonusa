<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    filters: Object,
    years: Array,
    months: Array,
    revenue: Object,
    monthlyTrend: Array,
    paymentMethods: Array,
    customerStatus: Object,
    debtAging: Array,
    topDebtors: Array,
})

const selectedYear = ref(props.filters?.year || new Date().getFullYear())
const selectedMonth = ref(props.filters?.month || new Date().getMonth() + 1)

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

// Format number
const formatNumber = (value) => {
    return new Intl.NumberFormat('id-ID').format(value || 0)
}

// Apply filter
const applyFilter = () => {
    router.get('/admin/reports', {
        year: selectedYear.value,
        month: selectedMonth.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Calculate collection rate
const collectionRate = computed(() => {
    if (!props.revenue?.billable || props.revenue.billable === 0) return 0
    return ((props.revenue?.collected || 0) / props.revenue.billable * 100).toFixed(1)
})
</script>

<template>
    <Head title="Laporan" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Laporan</h1>
        </template>

        <div class="space-y-6">
            <!-- Filter -->
            <div class="bg-white rounded-xl shadow-sm p-4">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">Tahun:</label>
                        <select
                            v-model="selectedYear"
                            @change="applyFilter"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">Bulan:</label>
                        <select
                            v-model="selectedMonth"
                            @change="applyFilter"
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                        >
                            <option v-for="month in months" :key="month.value" :value="month.value">
                                {{ month.label }}
                            </option>
                        </select>
                    </div>
                    <div class="flex gap-2 ml-auto">
                        <Link
                            href="/admin/reports/collectors"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700"
                        >
                            Laporan Penagih
                        </Link>
                        <Link
                            href="/admin/reports/areas"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700"
                        >
                            Laporan Area
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Revenue Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total Tagihan</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1">
                                {{ formatCurrency(revenue?.billable) }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Sudah Terbayar</p>
                            <p class="text-2xl font-bold text-green-600 mt-1">
                                {{ formatCurrency(revenue?.collected) }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Belum Terbayar</p>
                            <p class="text-2xl font-bold text-red-600 mt-1">
                                {{ formatCurrency(revenue?.outstanding) }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Tingkat Penagihan</p>
                            <p class="text-2xl font-bold text-blue-600 mt-1">
                                {{ collectionRate }}%
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Status & Payment Methods -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Customer Status -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Status Pelanggan</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                <span class="text-gray-700">Aktif</span>
                            </div>
                            <span class="font-semibold">{{ formatNumber(customerStatus?.active) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                <span class="text-gray-700">Terisolir</span>
                            </div>
                            <span class="font-semibold">{{ formatNumber(customerStatus?.isolated) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                                <span class="text-gray-700">Suspended</span>
                            </div>
                            <span class="font-semibold">{{ formatNumber(customerStatus?.suspended) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 bg-gray-400 rounded-full"></div>
                                <span class="text-gray-700">Tidak Aktif</span>
                            </div>
                            <span class="font-semibold">{{ formatNumber(customerStatus?.inactive) }}</span>
                        </div>
                        <hr class="my-2">
                        <div class="flex items-center justify-between font-semibold">
                            <span class="text-gray-900">Total</span>
                            <span>{{ formatNumber(customerStatus?.total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Metode Pembayaran (Bulan Ini)</h3>
                    <div class="space-y-4">
                        <div v-for="method in paymentMethods" :key="method.method" class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-3 h-3 rounded-full"
                                    :class="method.method === 'cash' ? 'bg-green-500' : 'bg-blue-500'"
                                ></div>
                                <span class="text-gray-700 capitalize">{{ method.method === 'cash' ? 'Tunai' : 'Transfer' }}</span>
                            </div>
                            <div class="text-right">
                                <span class="font-semibold">{{ formatCurrency(method.total) }}</span>
                                <span class="text-gray-500 text-sm ml-2">({{ method.count }}x)</span>
                            </div>
                        </div>
                        <div v-if="!paymentMethods?.length" class="text-center text-gray-500 py-4">
                            Belum ada pembayaran bulan ini
                        </div>
                    </div>
                </div>
            </div>

            <!-- Debt Aging -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Aging Hutang</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Umur Hutang</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-gray-500">Jumlah Pelanggan</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-gray-500">Total Hutang</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="aging in debtAging" :key="aging.label" class="border-b border-gray-100">
                                <td class="py-3 px-4">
                                    <span
                                        class="px-2 py-1 rounded-full text-xs font-medium"
                                        :class="{
                                            'bg-green-100 text-green-700': aging.months === 1,
                                            'bg-yellow-100 text-yellow-700': aging.months === 2,
                                            'bg-orange-100 text-orange-700': aging.months === 3,
                                            'bg-red-100 text-red-700': aging.months > 3,
                                        }"
                                    >
                                        {{ aging.label }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">{{ formatNumber(aging.count) }}</td>
                                <td class="py-3 px-4 text-right font-semibold">{{ formatCurrency(aging.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Debtors -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Top 10 Pelanggan dengan Hutang Terbesar</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">#</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Pelanggan</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Area</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-gray-500">Total Hutang</th>
                                <th class="text-center py-3 px-4 text-sm font-medium text-gray-500">Bulan</th>
                                <th class="text-center py-3 px-4 text-sm font-medium text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(debtor, index) in topDebtors" :key="debtor.id" class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4 text-gray-500">{{ index + 1 }}</td>
                                <td class="py-3 px-4">
                                    <Link :href="`/admin/customers/${debtor.id}`" class="text-blue-600 hover:underline">
                                        {{ debtor.name }}
                                    </Link>
                                    <p class="text-xs text-gray-500">{{ debtor.customer_id }}</p>
                                </td>
                                <td class="py-3 px-4 text-gray-600">{{ debtor.area?.name || '-' }}</td>
                                <td class="py-3 px-4 text-right font-semibold text-red-600">
                                    {{ formatCurrency(debtor.total_debt) }}
                                </td>
                                <td class="py-3 px-4 text-center">{{ debtor.unpaid_months || 0 }}</td>
                                <td class="py-3 px-4 text-center">
                                    <span
                                        class="px-2 py-1 rounded-full text-xs font-medium"
                                        :class="{
                                            'bg-green-100 text-green-700': debtor.status === 'active',
                                            'bg-red-100 text-red-700': debtor.status === 'isolated',
                                            'bg-yellow-100 text-yellow-700': debtor.status === 'suspended',
                                        }"
                                    >
                                        {{ debtor.status === 'active' ? 'Aktif' : debtor.status === 'isolated' ? 'Isolir' : 'Suspend' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!topDebtors?.length" class="text-center text-gray-500 py-8">
                        Tidak ada pelanggan dengan hutang
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

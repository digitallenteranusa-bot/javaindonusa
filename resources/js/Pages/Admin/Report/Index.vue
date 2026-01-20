<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    filters: Object,
    years: Array,
    months: Array,
    revenue: Object,
    monthlyTrend: Array,
    paymentMethods: Object,
    customerStatus: Object,
    debtAging: Object,
    topDebtors: Array,
})

const selectedYear = ref(props.filters.year)
const selectedMonth = ref(props.filters.month)

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value || 0)
}

// Format short currency (for charts)
const formatShortCurrency = (value) => {
    if (value >= 1000000000) {
        return (value / 1000000000).toFixed(1) + 'M'
    }
    if (value >= 1000000) {
        return (value / 1000000).toFixed(1) + 'Jt'
    }
    if (value >= 1000) {
        return (value / 1000).toFixed(0) + 'Rb'
    }
    return value
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

watch([selectedYear, selectedMonth], applyFilter)

// Get month name
const getMonthName = (month) => {
    return props.months.find(m => m.value === month)?.label || ''
}

// Chart data for monthly trend
const maxTrendValue = computed(() => {
    return Math.max(...props.monthlyTrend.map(m => m.billed), 1)
})

// Debt aging colors
const agingColors = {
    current: 'bg-green-500',
    '1_30': 'bg-yellow-500',
    '31_60': 'bg-orange-500',
    '61_90': 'bg-red-400',
    'over_90': 'bg-red-600',
}

const agingLabels = {
    current: 'Belum Jatuh Tempo',
    '1_30': '1-30 Hari',
    '31_60': '31-60 Hari',
    '61_90': '61-90 Hari',
    'over_90': '> 90 Hari',
}
</script>

<template>
    <Head title="Laporan" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Laporan & Analisis</h1>
                <div class="flex items-center gap-3">
                    <select
                        v-model="selectedMonth"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                    >
                        <option v-for="month in months" :key="month.value" :value="month.value">
                            {{ month.label }}
                        </option>
                    </select>
                    <select
                        v-model="selectedYear"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                    >
                        <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                    </select>
                </div>
            </div>
        </template>

        <!-- Revenue Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Tagihan</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatCurrency(revenue.total_billed) }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ revenue.invoice_count }} invoice</p>
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
                        <p class="text-sm text-gray-500">Terbayar</p>
                        <p class="text-2xl font-bold text-green-600 mt-1">{{ formatCurrency(revenue.total_paid) }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ revenue.paid_count }} lunas</p>
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
                        <p class="text-sm text-gray-500">Outstanding</p>
                        <p class="text-2xl font-bold text-red-600 mt-1">{{ formatCurrency(revenue.total_outstanding) }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ revenue.overdue_count }} jatuh tempo</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-blue-100">Collection Rate</p>
                        <p class="text-3xl font-bold mt-1">{{ revenue.collection_rate }}%</p>
                        <p class="text-xs text-blue-200 mt-1">{{ getMonthName(selectedMonth) }} {{ selectedYear }}</p>
                    </div>
                    <div class="w-16 h-16 relative">
                        <svg class="w-16 h-16 transform -rotate-90">
                            <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="6" fill="none" class="text-blue-400 opacity-30" />
                            <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="6" fill="none"
                                class="text-white"
                                :stroke-dasharray="`${revenue.collection_rate * 1.76} 176`"
                            />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Monthly Revenue Trend -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Trend Pendapatan Bulanan {{ selectedYear }}</h3>

                <div class="h-64 flex items-end gap-2">
                    <div
                        v-for="month in monthlyTrend"
                        :key="month.month"
                        class="flex-1 flex flex-col items-center"
                    >
                        <div class="w-full flex flex-col gap-1 mb-2" style="height: 200px;">
                            <!-- Billed bar -->
                            <div
                                class="w-full bg-blue-200 rounded-t transition-all duration-300"
                                :style="{ height: `${(month.billed / maxTrendValue) * 100}%` }"
                                :title="`Tagihan: ${formatCurrency(month.billed)}`"
                            ></div>
                            <!-- Paid bar (overlay) -->
                            <div
                                class="w-full bg-green-500 rounded-t transition-all duration-300 -mt-1"
                                :style="{ height: `${(month.paid / maxTrendValue) * 100}%`, marginTop: `-${(month.billed / maxTrendValue) * 100}%` }"
                                :title="`Terbayar: ${formatCurrency(month.paid)}`"
                            ></div>
                        </div>
                        <span class="text-xs text-gray-500">{{ month.month_name }}</span>
                        <span class="text-xs font-medium" :class="month.collection_rate >= 80 ? 'text-green-600' : month.collection_rate >= 50 ? 'text-yellow-600' : 'text-red-600'">
                            {{ month.collection_rate }}%
                        </span>
                    </div>
                </div>

                <div class="flex items-center justify-center gap-6 mt-4 text-sm">
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 bg-blue-200 rounded"></div>
                        <span class="text-gray-600">Tagihan</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 bg-green-500 rounded"></div>
                        <span class="text-gray-600">Terbayar</span>
                    </div>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Metode Pembayaran</h3>
                <p class="text-xs text-gray-400 mb-4">Bulan ini</p>

                <div class="space-y-4">
                    <!-- Cash -->
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">Cash/Tunai</span>
                            <span class="font-medium">{{ paymentMethods.cash.count }} transaksi</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3">
                            <div
                                class="bg-green-500 h-3 rounded-full"
                                :style="{ width: `${paymentMethods.total.amount > 0 ? (paymentMethods.cash.amount / paymentMethods.total.amount) * 100 : 0}%` }"
                            ></div>
                        </div>
                        <p class="text-right text-sm font-semibold text-green-600 mt-1">
                            {{ formatCurrency(paymentMethods.cash.amount) }}
                        </p>
                    </div>

                    <!-- Transfer -->
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">Transfer</span>
                            <span class="font-medium">{{ paymentMethods.transfer.count }} transaksi</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3">
                            <div
                                class="bg-blue-500 h-3 rounded-full"
                                :style="{ width: `${paymentMethods.total.amount > 0 ? (paymentMethods.transfer.amount / paymentMethods.total.amount) * 100 : 0}%` }"
                            ></div>
                        </div>
                        <p class="text-right text-sm font-semibold text-blue-600 mt-1">
                            {{ formatCurrency(paymentMethods.transfer.amount) }}
                        </p>
                    </div>

                    <hr class="my-4">

                    <div class="flex justify-between items-center">
                        <span class="font-medium">Total</span>
                        <span class="text-xl font-bold">{{ formatCurrency(paymentMethods.total.amount) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Customer Status -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Status Pelanggan</h3>

                <div class="flex items-center justify-center mb-4">
                    <div class="relative w-32 h-32">
                        <svg class="w-32 h-32 transform -rotate-90">
                            <circle cx="64" cy="64" r="56" stroke="#e5e7eb" stroke-width="12" fill="none" />
                            <circle cx="64" cy="64" r="56" stroke="#22c55e" stroke-width="12" fill="none"
                                :stroke-dasharray="`${customerStatus.active_percentage * 3.52} 352`"
                            />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-bold">{{ customerStatus.total }}</span>
                            <span class="text-xs text-gray-500">Total</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-green-500"></div>
                            <span class="text-sm text-gray-600">Aktif</span>
                        </div>
                        <span class="font-medium">{{ customerStatus.active }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <span class="text-sm text-gray-600">Isolir</span>
                        </div>
                        <span class="font-medium">{{ customerStatus.isolated }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                            <span class="text-sm text-gray-600">Suspend</span>
                        </div>
                        <span class="font-medium">{{ customerStatus.suspended }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-gray-400"></div>
                            <span class="text-sm text-gray-600">Non-Aktif</span>
                        </div>
                        <span class="font-medium">{{ customerStatus.inactive }}</span>
                    </div>
                </div>
            </div>

            <!-- Debt Aging -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Umur Piutang</h3>

                <div class="space-y-3">
                    <div v-for="(data, key) in debtAging" :key="key">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">{{ agingLabels[key] }}</span>
                            <span class="font-medium">{{ data.count }} pelanggan</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div
                                :class="[agingColors[key], 'h-2 rounded-full']"
                                :style="{ width: `${Math.min((data.amount / (Object.values(debtAging).reduce((a, b) => a + b.amount, 0) || 1)) * 100, 100)}%` }"
                            ></div>
                        </div>
                        <p class="text-right text-xs text-gray-500 mt-0.5">
                            {{ formatCurrency(data.amount) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Top Debtors -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Top 10 Hutang Terbesar</h3>
                    <Link href="/admin/customers?sort=debt" class="text-blue-600 text-sm hover:underline">
                        Lihat Semua
                    </Link>
                </div>

                <div class="space-y-3 max-h-80 overflow-y-auto">
                    <div
                        v-for="(debtor, index) in topDebtors"
                        :key="debtor.id"
                        class="flex items-center gap-3"
                    >
                        <span class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-xs font-medium text-gray-600">
                            {{ index + 1 }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <Link
                                :href="`/admin/customers/${debtor.id}`"
                                class="font-medium text-sm hover:text-blue-600 truncate block"
                            >
                                {{ debtor.name }}
                            </Link>
                            <p class="text-xs text-gray-500 truncate">{{ debtor.area }} • {{ debtor.package }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-red-600 text-sm">{{ formatCurrency(debtor.total_debt) }}</p>
                            <span
                                v-if="debtor.status === 'isolated'"
                                class="text-xs px-1.5 py-0.5 bg-red-100 text-red-600 rounded"
                            >
                                Isolir
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="!topDebtors.length" class="text-center py-8 text-gray-500 text-sm">
                    Tidak ada data hutang
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Link
                href="/admin/reports/collectors"
                class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow flex items-center gap-4"
            >
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold">Performa Penagih</h4>
                    <p class="text-sm text-gray-500">Lihat collection rate per penagih</p>
                </div>
            </Link>

            <Link
                href="/admin/reports/areas"
                class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow flex items-center gap-4"
            >
                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold">Performa Area</h4>
                    <p class="text-sm text-gray-500">Lihat collection rate per area</p>
                </div>
            </Link>

            <button
                @click="router.get('/admin/reports/export', { year: selectedYear, month: selectedMonth })"
                class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow flex items-center gap-4 text-left w-full"
            >
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h4 class="font-semibold">Export Laporan</h4>
                    <p class="text-sm text-gray-500">Download laporan ke Excel</p>
                </div>
            </button>
        </div>
    </AdminLayout>
</template>

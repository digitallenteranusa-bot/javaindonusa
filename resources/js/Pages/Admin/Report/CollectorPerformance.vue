<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    filters: Object,
    years: Array,
    months: Array,
    collectors: Array,
})

const selectedYear = ref(props.filters.year)
const selectedMonth = ref(props.filters.month)

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

// Apply filter
const applyFilter = () => {
    router.get('/admin/reports/collectors', {
        year: selectedYear.value,
        month: selectedMonth.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

watch([selectedYear, selectedMonth], applyFilter)

// Get rate color
const getRateColor = (rate) => {
    if (rate >= 80) return 'text-green-600'
    if (rate >= 60) return 'text-yellow-600'
    return 'text-red-600'
}

const getRateBgColor = (rate) => {
    if (rate >= 80) return 'bg-green-500'
    if (rate >= 60) return 'bg-yellow-500'
    return 'bg-red-500'
}

// Get month name
const getMonthName = (month) => {
    return props.months.find(m => m.value === month)?.label || ''
}

// Export to Excel
const exportToExcel = () => {
    window.location.href = `/admin/reports/collectors/export?year=${selectedYear.value}&month=${selectedMonth.value}`
}
</script>

<template>
    <Head title="Performa Penagih" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/reports" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Performa Penagih</h1>
                        <p class="text-gray-500 text-sm">{{ getMonthName(selectedMonth) }} {{ selectedYear }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <select
                        v-model="selectedMonth"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm"
                    >
                        <option v-for="month in months" :key="month.value" :value="month.value">
                            {{ month.label }}
                        </option>
                    </select>
                    <select
                        v-model="selectedYear"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm"
                    >
                        <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                    </select>
                    <button
                        @click="exportToExcel"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export Excel
                    </button>
                </div>
            </div>
        </template>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Total Penagih</p>
                <p class="text-2xl font-bold mt-1">{{ collectors.length }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Total Tagihan</p>
                <p class="text-2xl font-bold mt-1 text-blue-600">
                    {{ formatCurrency(collectors.reduce((a, b) => a + b.total_billable, 0)) }}
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Total Tertagih</p>
                <p class="text-2xl font-bold mt-1 text-green-600">
                    {{ formatCurrency(collectors.reduce((a, b) => a + b.total_collected, 0)) }}
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Rata-rata Collection Rate</p>
                <p class="text-2xl font-bold mt-1">
                    {{ collectors.length > 0 ? (collectors.reduce((a, b) => a + b.collection_rate, 0) / collectors.length).toFixed(1) : 0 }}%
                </p>
            </div>
        </div>

        <!-- Collector Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penagih</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pelanggan</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Target Tagihan</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tertagih</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cash</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Transfer</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Transaksi</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Collection Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="(collector, index) in collectors" :key="collector.id" class="hover:bg-gray-50">
                            <td class="px-4 py-4">
                                <span
                                    class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold"
                                    :class="index < 3 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ index + 1 }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-medium">{{ collector.name }}</p>
                            </td>
                            <td class="px-4 py-4 text-right">
                                {{ collector.customers_count }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                {{ formatCurrency(collector.total_billable) }}
                            </td>
                            <td class="px-4 py-4 text-right font-semibold text-green-600">
                                {{ formatCurrency(collector.total_collected) }}
                            </td>
                            <td class="px-4 py-4 text-right text-sm">
                                {{ formatCurrency(collector.cash_collected) }}
                            </td>
                            <td class="px-4 py-4 text-right text-sm">
                                {{ formatCurrency(collector.transfer_collected) }}
                            </td>
                            <td class="px-4 py-4 text-center">
                                {{ collector.transactions }}
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-100 rounded-full h-2">
                                        <div
                                            :class="[getRateBgColor(collector.collection_rate), 'h-2 rounded-full']"
                                            :style="{ width: `${collector.collection_rate}%` }"
                                        ></div>
                                    </div>
                                    <span :class="['font-bold text-sm w-14 text-right', getRateColor(collector.collection_rate)]">
                                        {{ collector.collection_rate }}%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="!collectors.length" class="text-center py-12">
                <p class="text-gray-500">Tidak ada data penagih</p>
            </div>
        </div>
    </AdminLayout>
</template>

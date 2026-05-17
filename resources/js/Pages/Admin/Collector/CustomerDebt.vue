<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    filters: Object,
    collectors: Array,
    debtByCollector: Array,
    summary: Object,
    years: Array,
    months: Array,
})

const monthFilter = ref(props.filters.period_month)
const yearFilter = ref(props.filters.period_year)
const collectorFilter = ref(props.filters.collector_id || '')

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

const applyFilters = () => {
    router.get('/admin/collector/customer-debt', {
        period_month: monthFilter.value || undefined,
        period_year: yearFilter.value || undefined,
        collector_id: collectorFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

watch([monthFilter, yearFilter, collectorFilter], applyFilters)

const exportExcel = () => {
    const params = new URLSearchParams()
    if (monthFilter.value) params.append('period_month', monthFilter.value)
    if (yearFilter.value) params.append('period_year', yearFilter.value)
    if (collectorFilter.value) params.append('collector_id', collectorFilter.value)

    window.location.href = `/admin/collector/customer-debt/export?${params.toString()}`
}

const expandedCollectors = ref({})

const toggleCollector = (name) => {
    expandedCollectors.value[name] = !expandedCollectors.value[name]
}

const getMonthName = (month) => {
    return props.months.find(m => m.value === month)?.label || ''
}
</script>

<template>
    <Head title="Piutang Pelanggan per Penagih" />
    <AdminLayout>
        <div class="p-4 lg:p-6 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Piutang Pelanggan</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Semua hutang pelanggan s/d {{ getMonthName(monthFilter) }} {{ yearFilter }} per penagih
                    </p>
                </div>
                <button
                    @click="exportExcel"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export Excel
                </button>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bulan</label>
                        <select v-model="monthFilter" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tahun</label>
                        <select v-model="yearFilter" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Penagih</label>
                        <select v-model="collectorFilter" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                            <option value="">Semua Penagih</option>
                            <option v-for="c in collectors" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Piutang</p>
                    <p class="text-xl font-bold text-red-600">{{ formatCurrency(summary.total_debt) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pelanggan Menunggak</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ summary.total_customers }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Penagih</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ summary.total_collectors }}</p>
                </div>
            </div>

            <!-- Debt by Collector -->
            <div class="space-y-4">
                <div
                    v-for="group in debtByCollector"
                    :key="group.collector_name"
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
                >
                    <!-- Collector Header (clickable) -->
                    <button
                        @click="toggleCollector(group.collector_name)"
                        class="w-full px-4 py-3 flex items-center justify-between bg-purple-50 dark:bg-purple-900/20 hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors"
                    >
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-purple-600 transition-transform" :class="{ 'rotate-90': expandedCollectors[group.collector_name] }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ group.collector_name }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">({{ group.customer_count }} pelanggan)</span>
                        </div>
                        <span class="font-bold text-red-600">{{ formatCurrency(group.total_debt) }}</span>
                    </button>

                    <!-- Customer Table -->
                    <div v-show="expandedCollectors[group.collector_name]" class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">ID</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Nama</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Area</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Paket</th>
                                    <th class="px-4 py-2 text-center font-medium text-gray-600 dark:text-gray-300">Bulan Nunggak</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Sejak</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Total Hutang</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr
                                    v-for="cust in group.customers"
                                    :key="cust.customer_id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                >
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300 font-mono text-xs">{{ cust.customer_id }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-white font-medium">{{ cust.name }}</td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ cust.area }}</td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ cust.package }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                            :class="cust.unpaid_months >= 3 ? 'bg-red-100 text-red-700' : cust.unpaid_months >= 2 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'"
                                        >
                                            {{ cust.unpaid_months }} bln
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500 dark:text-gray-400 text-xs">{{ cust.oldest_period }}</td>
                                    <td class="px-4 py-2 text-right font-semibold text-red-600">{{ formatCurrency(cust.total_debt) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="debtByCollector.length === 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="mt-2 text-gray-500 dark:text-gray-400">Tidak ada piutang pelanggan untuk periode ini</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

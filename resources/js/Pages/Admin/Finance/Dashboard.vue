<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Line, Bar } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js'

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler
)

const props = defineProps({
    stats: Object,
    trend: Array,
    breakdown: Array,
    filters: Object,
})

const selectedMonth = ref(props.filters.month)
const selectedYear = ref(props.filters.year)

const months = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
]

const currentYear = new Date().getFullYear()
const years = Array.from({ length: 5 }, (_, i) => currentYear - i)

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

const formatShortCurrency = (value) => {
    if (Math.abs(value) >= 1000000) {
        return (value / 1000000).toFixed(1) + ' Jt'
    }
    if (Math.abs(value) >= 1000) {
        return (value / 1000).toFixed(0) + ' Rb'
    }
    return value.toLocaleString('id-ID')
}

const applyFilter = () => {
    router.get('/admin/finance', {
        month: selectedMonth.value,
        year: selectedYear.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Summary table rows
const summaryRows = computed(() => [
    {
        label: 'Pendapatan (Pembayaran Verified)',
        amount: props.stats?.revenue || 0,
        type: 'income',
    },
    {
        label: '(-) Pengeluaran Collector',
        amount: props.stats?.collector_expense || 0,
        type: 'expense',
    },
    {
        label: '(-) Pengeluaran Operasional',
        amount: props.stats?.operational_expense || 0,
        type: 'expense',
    },
    {
        label: '(-) Gaji Pegawai',
        amount: props.stats?.salary || 0,
        type: 'expense',
    },
])

// Chart: Trend 12 bulan
const trendChartData = computed(() => ({
    labels: (props.trend || []).map(item => item.month),
    datasets: [
        {
            label: 'Pendapatan',
            data: (props.trend || []).map(item => item.revenue),
            borderColor: '#10B981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 6,
        },
        {
            label: 'Pengeluaran',
            data: (props.trend || []).map(item => item.expense),
            borderColor: '#EF4444',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 6,
        },
        {
            label: 'Laba Bersih',
            data: (props.trend || []).map(item => item.profit),
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: false,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 6,
            borderDash: [5, 5],
        },
    ],
}))

const trendChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
        tooltip: {
            callbacks: {
                label: (ctx) => ctx.dataset.label + ': ' + new Intl.NumberFormat('id-ID', {
                    style: 'currency', currency: 'IDR', minimumFractionDigits: 0,
                }).format(ctx.raw),
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (value) => formatShortCurrency(value),
            },
        },
    },
}

// Chart: Breakdown pengeluaran
const breakdownChartData = computed(() => {
    const items = (props.breakdown || []).filter(b => b.total > 0)
    return {
        labels: items.map(b => b.label),
        datasets: [{
            label: 'Jumlah',
            data: items.map(b => b.total),
            backgroundColor: [
                '#F59E0B', '#EF4444', '#8B5CF6', '#3B82F6',
                '#10B981', '#EC4899', '#6366F1', '#14B8A6',
            ],
            borderRadius: 6,
        }],
    }
})

const breakdownChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => new Intl.NumberFormat('id-ID', {
                    style: 'currency', currency: 'IDR', minimumFractionDigits: 0,
                }).format(ctx.raw),
            },
        },
    },
    scales: {
        x: {
            beginAtZero: true,
            ticks: {
                callback: (value) => formatShortCurrency(value),
            },
        },
    },
}
</script>

<template>
    <Head title="Keuangan - Dashboard" />

    <AdminLayout>
        <template #header>
            <h1 class="text-2xl font-bold text-gray-900">Dashboard Keuangan</h1>
        </template>

        <!-- Filter Bulan & Tahun -->
        <div class="mb-6 flex items-center gap-3">
            <select
                v-model="selectedMonth"
                @change="applyFilter"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
            >
                <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
            <select
                v-model="selectedYear"
                @change="applyFilter"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
            >
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
        </div>

        <!-- 4 Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Pendapatan Kotor -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Pendapatan Kotor</p>
                        <p class="text-2xl font-bold mt-2 text-green-600">{{ formatCurrency(stats?.revenue) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Belanja -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Belanja</p>
                        <p class="text-2xl font-bold mt-2 text-red-600">{{ formatCurrency(stats?.total_expense) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-red-500 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Gaji -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Gaji</p>
                        <p class="text-2xl font-bold mt-2 text-purple-600">{{ formatCurrency(stats?.salary) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-500 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Laba Bersih -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Laba Bersih</p>
                        <p :class="['text-2xl font-bold mt-2', (stats?.net_profit || 0) >= 0 ? 'text-blue-600' : 'text-red-600']">
                            {{ formatCurrency(stats?.net_profit) }}
                        </p>
                    </div>
                    <div :class="['w-12 h-12 rounded-lg flex items-center justify-center', (stats?.net_profit || 0) >= 0 ? 'bg-blue-500' : 'bg-red-500']">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Ringkasan + Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Tabel Ringkasan -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Ringkasan Keuangan</h2>
                </div>
                <table class="w-full">
                    <tbody class="divide-y">
                        <tr v-for="row in summaryRows" :key="row.label">
                            <td class="px-6 py-3 text-sm text-gray-700">{{ row.label }}</td>
                            <td :class="['px-6 py-3 text-sm font-medium text-right', row.type === 'income' ? 'text-green-600' : 'text-red-600']">
                                {{ formatCurrency(row.amount) }}
                            </td>
                        </tr>
                        <tr class="bg-gray-50 font-bold">
                            <td class="px-6 py-3 text-sm text-gray-900">= Laba Bersih</td>
                            <td :class="['px-6 py-3 text-sm text-right', (stats?.net_profit || 0) >= 0 ? 'text-blue-600' : 'text-red-600']">
                                {{ formatCurrency(stats?.net_profit) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Breakdown Pengeluaran -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Breakdown Pengeluaran</h2>
                </div>
                <div class="p-6">
                    <div v-if="breakdown && breakdown.some(b => b.total > 0)" class="h-64">
                        <Bar :data="breakdownChartData" :options="breakdownChartOptions" />
                    </div>
                    <div v-else class="h-64 flex items-center justify-center text-gray-400">
                        Belum ada data pengeluaran
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart Trend 12 Bulan -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Trend Keuangan {{ selectedYear }}</h2>
            </div>
            <div class="p-6">
                <div v-if="trend && trend.length > 0" class="h-80">
                    <Line :data="trendChartData" :options="trendChartOptions" />
                </div>
                <div v-else class="h-80 flex items-center justify-center text-gray-400">
                    Belum ada data
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
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
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
)

const props = defineProps({
    filters: Object,
    years: Array,
    months: Array,
    summary: Object,
    yearComparison: Array,
    monthlyTrend: Array,
    growthRate: Array,
    collectionRateTrend: Array,
    revenueByPackage: Array,
    revenueByArea: Array,
    paymentMethods: Array,
    customerGrowth: Array,
    customerMetrics: Array,
    topCustomers: Array,
    debtAging: Array,
})

const selectedYear = ref(props.filters.year)
const selectedMonth = ref(props.filters.month)

const currentYear = new Date().getFullYear()
const yearOptions = computed(() => {
    const yrs = props.years && props.years.length > 0 ? [...props.years] : [currentYear]
    if (!yrs.includes(currentYear)) yrs.unshift(currentYear)
    if (!yrs.includes(currentYear - 1)) yrs.push(currentYear - 1)
    return [...new Set(yrs)].sort((a, b) => b - a)
})

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

const formatShortCurrency = (value) => {
    if (Math.abs(value) >= 1000000000) return (value / 1000000000).toFixed(1) + ' M'
    if (Math.abs(value) >= 1000000) return (value / 1000000).toFixed(1) + ' Jt'
    if (Math.abs(value) >= 1000) return (value / 1000).toFixed(0) + ' Rb'
    return (value || 0).toLocaleString('id-ID')
}

const applyFilter = () => {
    router.get('/admin/analytics/revenue', {
        year: selectedYear.value,
        month: selectedMonth.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

const chartColors = [
    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
    '#EC4899', '#14B8A6', '#6366F1', '#F97316', '#06B6D4',
]

// --- Chart Data ---

// Section 2: YoY Comparison
const yoyChartData = computed(() => ({
    labels: (props.yearComparison || []).map(i => i.month_name),
    datasets: [
        {
            label: String(selectedYear.value),
            data: (props.yearComparison || []).map(i => i.current_year),
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
            borderRadius: 4,
        },
        {
            label: String(selectedYear.value - 1),
            data: (props.yearComparison || []).map(i => i.prev_year),
            backgroundColor: 'rgba(156, 163, 175, 0.6)',
            borderRadius: 4,
        },
    ],
}))

const yoyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
        tooltip: {
            callbacks: {
                label: (ctx) => ctx.dataset.label + ': ' + formatCurrency(ctx.raw),
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { callback: (v) => formatShortCurrency(v) },
        },
    },
}

// Section 3: Monthly Trend (billed vs collected vs outstanding)
const monthlyTrendChartData = computed(() => ({
    labels: (props.monthlyTrend || []).map(i => i.month_name),
    datasets: [
        {
            label: 'Tagihan',
            data: (props.monthlyTrend || []).map(i => i.billed),
            backgroundColor: 'rgba(59, 130, 246, 0.7)',
            borderRadius: 4,
        },
        {
            label: 'Terbayar',
            data: (props.monthlyTrend || []).map(i => i.paid),
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderRadius: 4,
        },
        {
            label: 'Outstanding',
            data: (props.monthlyTrend || []).map(i => Math.max(0, i.billed - i.paid)),
            backgroundColor: 'rgba(239, 68, 68, 0.5)',
            borderRadius: 4,
        },
    ],
}))

const monthlyTrendChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
        tooltip: {
            callbacks: {
                label: (ctx) => ctx.dataset.label + ': ' + formatCurrency(ctx.raw),
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { callback: (v) => formatShortCurrency(v) },
        },
    },
}

// Section 4a: Growth Rate
const growthRateChartData = computed(() => ({
    labels: (props.growthRate || []).map(i => i.month_name),
    datasets: [{
        label: 'Growth Rate (%)',
        data: (props.growthRate || []).map(i => i.growth_rate),
        borderColor: '#3B82F6',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointHoverRadius: 6,
    }],
}))

const growthRateChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => 'Growth: ' + ctx.raw + '%',
            },
        },
    },
    scales: {
        y: {
            ticks: { callback: (v) => v + '%' },
        },
    },
}

// Section 4b: Collection Rate
const collectionRateChartData = computed(() => ({
    labels: (props.collectionRateTrend || []).map(i => i.month_name),
    datasets: [{
        label: 'Collection Rate (%)',
        data: (props.collectionRateTrend || []).map(i => i.collection_rate),
        borderColor: '#10B981',
        backgroundColor: 'rgba(16, 185, 129, 0.1)',
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointHoverRadius: 6,
    }],
}))

const collectionRateChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => 'Rate: ' + ctx.raw + '%',
            },
        },
    },
    scales: {
        y: {
            min: 0,
            max: 100,
            ticks: { callback: (v) => v + '%' },
        },
    },
}

// Section 5a: Revenue by Package (Doughnut)
const packageChartData = computed(() => ({
    labels: (props.revenueByPackage || []).map(i => i.name),
    datasets: [{
        data: (props.revenueByPackage || []).map(i => i.total_paid),
        backgroundColor: chartColors.slice(0, (props.revenueByPackage || []).length),
        borderWidth: 2,
        borderColor: '#fff',
    }],
}))

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
        tooltip: {
            callbacks: {
                label: (ctx) => ctx.label + ': ' + formatCurrency(ctx.raw),
            },
        },
    },
}

// Section 5b: Payment Methods (Doughnut)
const paymentMethodChartData = computed(() => ({
    labels: (props.paymentMethods || []).map(i => i.method || 'Lainnya'),
    datasets: [{
        data: (props.paymentMethods || []).map(i => i.total),
        backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6'],
        borderWidth: 2,
        borderColor: '#fff',
    }],
}))

// Section 6: Revenue by Area (Horizontal Bar)
const areaChartData = computed(() => ({
    labels: (props.revenueByArea || []).map(i => i.name),
    datasets: [{
        label: 'Terbayar',
        data: (props.revenueByArea || []).map(i => i.total_paid),
        backgroundColor: 'rgba(59, 130, 246, 0.7)',
        borderRadius: 4,
    }],
}))

const areaChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => formatCurrency(ctx.raw),
            },
        },
    },
    scales: {
        x: {
            beginAtZero: true,
            ticks: { callback: (v) => formatShortCurrency(v) },
        },
    },
}

// Section 7: Customer Growth
const customerGrowthChartData = computed(() => ({
    labels: (props.customerGrowth || []).map(i => i.month_name),
    datasets: [
        {
            label: 'Pelanggan Baru',
            data: (props.customerGrowth || []).map(i => i.new),
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderRadius: 4,
        },
        {
            label: 'Churn',
            data: (props.customerGrowth || []).map(i => i.churn),
            backgroundColor: 'rgba(239, 68, 68, 0.7)',
            borderRadius: 4,
        },
    ],
}))

const customerGrowthChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
    },
    scales: {
        y: { beginAtZero: true },
    },
}

// Section 8: ARPU
const arpuChartData = computed(() => ({
    labels: (props.customerMetrics || []).map(i => i.month_name),
    datasets: [
        {
            label: 'ARPU',
            data: (props.customerMetrics || []).map(i => i.arpu),
            borderColor: '#8B5CF6',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            yAxisID: 'y',
        },
        {
            label: 'Pelanggan Aktif',
            data: (props.customerMetrics || []).map(i => i.active_customers),
            borderColor: '#F59E0B',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            fill: false,
            tension: 0.4,
            pointRadius: 4,
            borderDash: [5, 5],
            yAxisID: 'y1',
        },
    ],
}))

const arpuChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    if (ctx.dataset.yAxisID === 'y') return 'ARPU: ' + formatCurrency(ctx.raw)
                    return 'Pelanggan: ' + ctx.raw
                },
            },
        },
    },
    scales: {
        y: {
            type: 'linear',
            position: 'left',
            beginAtZero: true,
            ticks: { callback: (v) => formatShortCurrency(v) },
            title: { display: true, text: 'ARPU (Rp)' },
        },
        y1: {
            type: 'linear',
            position: 'right',
            beginAtZero: true,
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Jumlah Pelanggan' },
        },
    },
}
</script>

<template>
    <Head title="Analisa Pendapatan" />

    <AdminLayout>
        <template #header>
            <h1 class="text-2xl font-bold text-gray-900">Analisa Pendapatan</h1>
        </template>

        <!-- Section 0: Filter Bar -->
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <select
                v-model="selectedYear"
                @change="applyFilter"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
            >
                <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
            </select>
            <select
                v-model="selectedMonth"
                @change="applyFilter"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
            >
                <option :value="null">Semua Bulan</option>
                <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
        </div>

        <!-- Section 1: Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Total Pendapatan</p>
                <p class="text-xl font-bold mt-1 text-green-600">{{ formatCurrency(summary?.total_revenue) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Growth vs {{ selectedYear - 1 }}</p>
                <p :class="['text-xl font-bold mt-1', (summary?.growth_percent || 0) >= 0 ? 'text-green-600' : 'text-red-600']">
                    {{ (summary?.growth_percent || 0) >= 0 ? '+' : '' }}{{ summary?.growth_percent || 0 }}%
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Rata-rata Bulanan</p>
                <p class="text-xl font-bold mt-1 text-blue-600">{{ formatCurrency(summary?.monthly_average) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Bulan Terbaik</p>
                <p class="text-xl font-bold mt-1 text-purple-600">{{ summary?.best_month || '-' }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ formatCurrency(summary?.best_month_amount) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <p class="text-gray-500 text-xs uppercase tracking-wide">Collection Rate</p>
                <p :class="['text-xl font-bold mt-1', (summary?.collection_rate || 0) >= 80 ? 'text-green-600' : 'text-yellow-600']">
                    {{ summary?.collection_rate || 0 }}%
                </p>
            </div>
        </div>

        <!-- Section 2: YoY Comparison -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Perbandingan Year-over-Year</h2>
                <p class="text-sm text-gray-500">{{ selectedYear }} vs {{ selectedYear - 1 }}</p>
            </div>
            <div class="p-6">
                <div class="h-80">
                    <Bar :data="yoyChartData" :options="yoyChartOptions" />
                </div>
            </div>
        </div>

        <!-- Section 3: Monthly Trend -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Tren Pendapatan Bulanan {{ selectedYear }}</h2>
            </div>
            <div class="p-6">
                <div class="h-80">
                    <Bar :data="monthlyTrendChartData" :options="monthlyTrendChartOptions" />
                </div>
            </div>
        </div>

        <!-- Section 4: Growth Rate + Collection Rate -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Growth Rate (MoM)</h2>
                </div>
                <div class="p-6">
                    <div class="h-64">
                        <Line :data="growthRateChartData" :options="growthRateChartOptions" />
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Collection Rate</h2>
                </div>
                <div class="p-6">
                    <div class="h-64">
                        <Line :data="collectionRateChartData" :options="collectionRateChartOptions" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 5: Revenue per Package + Payment Methods -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Revenue per Paket</h2>
                </div>
                <div class="p-6">
                    <div v-if="revenueByPackage && revenueByPackage.length > 0" class="h-72">
                        <Doughnut :data="packageChartData" :options="doughnutOptions" />
                    </div>
                    <div v-else class="h-72 flex items-center justify-center text-gray-400">
                        Belum ada data
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Metode Pembayaran</h2>
                </div>
                <div class="p-6">
                    <div v-if="paymentMethods && paymentMethods.length > 0" class="h-72">
                        <Doughnut :data="paymentMethodChartData" :options="doughnutOptions" />
                    </div>
                    <div v-else class="h-72 flex items-center justify-center text-gray-400">
                        Belum ada data
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 6: Revenue per Area -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Revenue per Area</h2>
            </div>
            <div class="p-6">
                <div v-if="revenueByArea && revenueByArea.length > 0" :style="{ height: Math.max(300, (revenueByArea || []).length * 40) + 'px' }">
                    <Bar :data="areaChartData" :options="areaChartOptions" />
                </div>
                <div v-else class="h-64 flex items-center justify-center text-gray-400">
                    Belum ada data
                </div>
            </div>
        </div>

        <!-- Section 7: Customer Growth -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Pertumbuhan Pelanggan {{ selectedYear }}</h2>
            </div>
            <div class="p-6">
                <div class="h-72">
                    <Bar :data="customerGrowthChartData" :options="customerGrowthChartOptions" />
                </div>
            </div>
        </div>

        <!-- Section 8: Customer Metrics (ARPU) -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">ARPU & Pelanggan Aktif</h2>
            </div>
            <div class="p-6">
                <div class="h-80">
                    <Line :data="arpuChartData" :options="arpuChartOptions" />
                </div>
            </div>
        </div>

        <!-- Section 9: Top 10 Pelanggan (Debtors) -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Top 10 Pelanggan Berhutang</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">#</th>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">ID</th>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">Nama</th>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">Area</th>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">Paket</th>
                            <th class="text-right px-6 py-3 font-medium text-gray-500">Total Hutang</th>
                            <th class="text-center px-6 py-3 font-medium text-gray-500">Bulan Tunggakan</th>
                            <th class="text-center px-6 py-3 font-medium text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(c, idx) in topCustomers" :key="c.id" class="hover:bg-gray-50">
                            <td class="px-6 py-3 text-gray-500">{{ idx + 1 }}</td>
                            <td class="px-6 py-3 font-mono text-xs">{{ c.customer_id }}</td>
                            <td class="px-6 py-3 font-medium text-gray-900">{{ c.name }}</td>
                            <td class="px-6 py-3 text-gray-600">{{ c.area?.name || '-' }}</td>
                            <td class="px-6 py-3 text-gray-600">{{ c.package || '-' }}</td>
                            <td class="px-6 py-3 text-right font-medium text-red-600">{{ formatCurrency(c.total_debt) }}</td>
                            <td class="px-6 py-3 text-center">{{ c.unpaid_months }}</td>
                            <td class="px-6 py-3 text-center">
                                <span :class="[
                                    'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                    c.status === 'active' ? 'bg-green-100 text-green-700' :
                                    c.status === 'isolated' ? 'bg-red-100 text-red-700' :
                                    'bg-gray-100 text-gray-700'
                                ]">
                                    {{ c.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!topCustomers || topCustomers.length === 0">
                            <td colspan="8" class="px-6 py-8 text-center text-gray-400">Tidak ada data</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 10: Debt Aging -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Analisa Hutang (Aging)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left px-6 py-3 font-medium text-gray-500">Kategori</th>
                            <th class="text-right px-6 py-3 font-medium text-gray-500">Jumlah Pelanggan</th>
                            <th class="text-right px-6 py-3 font-medium text-gray-500">Total Hutang</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="item in debtAging" :key="item.label" class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-medium text-gray-900">{{ item.label }}</td>
                            <td class="px-6 py-3 text-right text-gray-600">{{ item.count }}</td>
                            <td class="px-6 py-3 text-right font-medium text-red-600">{{ formatCurrency(item.total) }}</td>
                        </tr>
                        <tr v-if="!debtAging || debtAging.length === 0">
                            <td colspan="3" class="px-6 py-8 text-center text-gray-400">Tidak ada data</td>
                        </tr>
                        <tr v-else class="bg-gray-50 font-bold">
                            <td class="px-6 py-3 text-gray-900">Total</td>
                            <td class="px-6 py-3 text-right text-gray-900">{{ (debtAging || []).reduce((s, i) => s + i.count, 0) }}</td>
                            <td class="px-6 py-3 text-right text-red-600">{{ formatCurrency((debtAging || []).reduce((s, i) => s + i.total, 0)) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

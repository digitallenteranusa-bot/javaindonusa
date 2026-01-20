<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    stats: Object,
    period: String,
    periods: Object,
})

const selectedPeriod = ref(props.period)

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

// Change period
const changePeriod = () => {
    router.get('/admin', { period: selectedPeriod.value }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Overview cards
const overviewCards = computed(() => [
    {
        label: 'Total Pelanggan',
        value: formatNumber(props.stats?.overview?.total_customers),
        subtext: `${props.stats?.overview?.active_customers || 0} aktif`,
        icon: 'users',
        color: 'bg-blue-500',
        href: '/admin/customers',
    },
    {
        label: 'Pendapatan Bulan Ini',
        value: formatCurrency(props.stats?.overview?.revenue_this_month),
        subtext: `${props.stats?.overview?.revenue_growth > 0 ? '+' : ''}${props.stats?.overview?.revenue_growth || 0}% dari bulan lalu`,
        icon: 'cash',
        color: 'bg-green-500',
        href: '/admin/payments',
    },
    {
        label: 'Total Hutang',
        value: formatCurrency(props.stats?.overview?.total_debt),
        subtext: `Collection rate: ${props.stats?.overview?.collection_rate || 0}%`,
        icon: 'document',
        color: 'bg-red-500',
        href: '/admin/invoices?status=overdue',
    },
    {
        label: 'Pelanggan Isolir',
        value: formatNumber(props.stats?.overview?.isolated_customers),
        subtext: 'Perlu perhatian',
        icon: 'warning',
        color: 'bg-yellow-500',
        href: '/admin/customers?status=isolated',
    },
])
</script>

<template>
    <Head title="Dashboard Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        </template>

        <!-- Period Filter -->
        <div class="mb-6 flex items-center justify-between">
            <p class="text-gray-600">Selamat datang kembali!</p>
            <select
                v-model="selectedPeriod"
                @change="changePeriod"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
            >
                <option v-for="(label, value) in periods" :key="value" :value="value">
                    {{ label }}
                </option>
            </select>
        </div>

        <!-- Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <Link
                v-for="card in overviewCards"
                :key="card.label"
                :href="card.href"
                class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">{{ card.label }}</p>
                        <p class="text-2xl font-bold mt-2">{{ card.value }}</p>
                        <p class="text-gray-400 text-xs mt-1">{{ card.subtext }}</p>
                    </div>
                    <div :class="[card.color, 'w-12 h-12 rounded-lg flex items-center justify-center']">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path v-if="card.icon === 'users'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            <path v-else-if="card.icon === 'cash'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            <path v-else-if="card.icon === 'document'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            <path v-else-if="card.icon === 'warning'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                </div>
            </Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Revenue Stats -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Ringkasan Pendapatan</h2>

                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="text-center p-4 bg-gray-50 rounded-lg">
                        <p class="text-gray-500 text-sm">Total</p>
                        <p class="text-xl font-bold text-gray-900">
                            {{ formatCurrency(stats?.revenue?.total) }}
                        </p>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-lg">
                        <p class="text-gray-500 text-sm">Cash</p>
                        <p class="text-xl font-bold text-green-600">
                            {{ formatCurrency(stats?.revenue?.cash) }}
                        </p>
                    </div>
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <p class="text-gray-500 text-sm">Transfer</p>
                        <p class="text-xl font-bold text-blue-600">
                            {{ formatCurrency(stats?.revenue?.transfer) }}
                        </p>
                    </div>
                </div>

                <!-- Revenue Trend Chart Placeholder -->
                <div class="h-64 bg-gray-100 rounded-lg flex items-center justify-center">
                    <p class="text-gray-500">Grafik Pendapatan</p>
                </div>
            </div>

            <!-- Alerts -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Notifikasi</h2>

                <div class="space-y-3">
                    <div
                        v-for="alert in stats?.alerts"
                        :key="alert.title"
                        :class="[
                            'p-4 rounded-lg border-l-4',
                            alert.type === 'danger' ? 'bg-red-50 border-red-500' :
                            alert.type === 'warning' ? 'bg-yellow-50 border-yellow-500' :
                            'bg-blue-50 border-blue-500'
                        ]"
                    >
                        <p class="font-medium text-sm">{{ alert.title }}</p>
                        <p class="text-gray-600 text-xs mt-1">{{ alert.message }}</p>
                        <Link
                            v-if="alert.action_url"
                            :href="alert.action_url"
                            class="text-blue-600 text-xs mt-2 inline-block hover:underline"
                        >
                            Lihat Detail →
                        </Link>
                    </div>

                    <div v-if="!stats?.alerts?.length" class="text-center py-8 text-gray-500">
                        Tidak ada notifikasi
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
            <!-- Customer Stats -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Status Pelanggan</h2>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <span class="text-gray-600">Aktif</span>
                        </div>
                        <span class="font-semibold">{{ formatNumber(stats?.customers?.by_status?.active) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                            <span class="text-gray-600">Isolir</span>
                        </div>
                        <span class="font-semibold">{{ formatNumber(stats?.customers?.by_status?.isolated) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                            <span class="text-gray-600">Terminated</span>
                        </div>
                        <span class="font-semibold">{{ formatNumber(stats?.customers?.by_status?.terminated) }}</span>
                    </div>
                </div>

                <hr class="my-4">

                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Dengan Hutang</span>
                    <span class="font-semibold text-red-600">{{ formatNumber(stats?.customers?.with_debt) }}</span>
                </div>
            </div>

            <!-- Collector Performance -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold">Performa Penagih</h2>
                    <span class="text-sm text-gray-500">{{ stats?.collectors?.total_collectors }} penagih</span>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="collector in stats?.collectors?.collectors?.slice(0, 5)"
                        :key="collector.id"
                        class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                    >
                        <div>
                            <p class="font-medium text-sm">{{ collector.name }}</p>
                            <p class="text-xs text-gray-500">{{ collector.assigned_customers }} pelanggan</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-green-600 text-sm">
                                {{ formatCurrency(collector.collected) }}
                            </p>
                            <p class="text-xs text-gray-500">{{ collector.transactions }} transaksi</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t flex justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Pending Expenses</p>
                        <p class="font-semibold text-yellow-600">{{ stats?.collectors?.pending_expenses }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Pending Settlements</p>
                        <p class="font-semibold text-yellow-600">{{ stats?.collectors?.pending_settlements }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="mt-6 bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Aktivitas Terbaru</h2>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-gray-500 text-sm border-b">
                            <th class="pb-3 font-medium">Waktu</th>
                            <th class="pb-3 font-medium">Aktivitas</th>
                            <th class="pb-3 font-medium">Pelanggan</th>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 font-medium">Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="activity in stats?.recent_activities?.slice(0, 10)"
                            :key="activity.id"
                            class="text-sm"
                        >
                            <td class="py-3 text-gray-500">{{ activity.created_at }}</td>
                            <td class="py-3">
                                <p class="font-medium">{{ activity.title }}</p>
                                <p class="text-gray-500 text-xs">{{ activity.description }}</p>
                            </td>
                            <td class="py-3">
                                <Link
                                    v-if="activity.customer"
                                    :href="`/admin/customers/${activity.customer_id}`"
                                    class="text-blue-600 hover:underline"
                                >
                                    {{ activity.customer }}
                                </Link>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="py-3">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded-full',
                                        activity.status === 'success' ? 'bg-green-100 text-green-600' :
                                        activity.status === 'failed' ? 'bg-red-100 text-red-600' :
                                        'bg-yellow-100 text-yellow-600'
                                    ]"
                                >
                                    {{ activity.status }}
                                </span>
                            </td>
                            <td class="py-3 text-gray-500">{{ activity.performed_by || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

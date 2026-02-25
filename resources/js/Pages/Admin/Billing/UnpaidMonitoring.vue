<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    invoices: Object,
    filters: Object,
    stats: Object,
    areas: Array,
    collectors: Array,
    periodMonth: Number,
    periodYear: Number,
    years: Array,
    months: Array,
})

const search = ref(props.filters.search || '')
const areaFilter = ref(props.filters.area_id || '')
const collectorFilter = ref(props.filters.collector_id || '')
const statusFilter = ref(props.filters.status || '')
const monthFilter = ref(props.periodMonth)
const yearFilter = ref(props.periodYear)

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

const applyFilters = () => {
    router.get('/admin/billing/unpaid', {
        search: search.value || undefined,
        area_id: areaFilter.value || undefined,
        collector_id: collectorFilter.value || undefined,
        status: statusFilter.value || undefined,
        period_month: monthFilter.value || undefined,
        period_year: yearFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

watch([areaFilter, collectorFilter, statusFilter, monthFilter, yearFilter], applyFilters)

const statusClass = (status) => {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-700',
        partial: 'bg-blue-100 text-blue-700',
        overdue: 'bg-red-100 text-red-700',
    }
    return classes[status] || 'bg-gray-100 text-gray-700'
}

const statusLabel = (status) => {
    const labels = {
        pending: 'Belum Bayar',
        partial: 'Sebagian',
        overdue: 'Jatuh Tempo',
    }
    return labels[status] || status
}

const exportExcel = () => {
    const params = new URLSearchParams()
    if (monthFilter.value) params.append('period_month', monthFilter.value)
    if (yearFilter.value) params.append('period_year', yearFilter.value)
    if (areaFilter.value) params.append('area_id', areaFilter.value)
    if (collectorFilter.value) params.append('collector_id', collectorFilter.value)
    if (statusFilter.value) params.append('status', statusFilter.value)
    if (search.value) params.append('search', search.value)

    window.location.href = `/admin/billing/unpaid/export?${params.toString()}`
}

const decodeLabel = (label) => {
    if (!label) return ''
    return label
        .replace(/&laquo;/g, '\u00AB')
        .replace(/&raquo;/g, '\u00BB')
        .replace(/&amp;/g, '&')
}
</script>

<template>
    <Head title="Belum Bayar" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Monitoring Belum Bayar</h1>
                <button
                    @click="exportExcel"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export Excel
                </button>
            </div>
        </template>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Belum Bayar</p>
                <p class="text-2xl font-bold text-yellow-600">{{ stats?.unpaid_count || 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Jatuh Tempo</p>
                <p class="text-2xl font-bold text-red-600">{{ stats?.overdue_count || 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Total Tagihan</p>
                <p class="text-lg font-bold">{{ formatCurrency(stats?.total_billed) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Total Terbayar</p>
                <p class="text-lg font-bold text-green-600">{{ formatCurrency(stats?.total_paid) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Total Sisa</p>
                <p class="text-lg font-bold text-red-600">{{ formatCurrency(stats?.total_remaining) }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div class="md:col-span-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari nama atau ID pelanggan..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        @keyup.enter="applyFilters"
                    >
                </div>
                <select
                    v-model="areaFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Area</option>
                    <option v-for="area in areas" :key="area.id" :value="area.id">{{ area.name }}</option>
                </select>
                <select
                    v-model="collectorFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Penagih</option>
                    <option v-for="collector in collectors" :key="collector.id" :value="collector.id">{{ collector.name }}</option>
                </select>
                <select
                    v-model="statusFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Status</option>
                    <option value="pending">Belum Bayar</option>
                    <option value="partial">Sebagian</option>
                    <option value="overdue">Jatuh Tempo</option>
                </select>
                <div class="flex gap-2">
                    <select
                        v-model="monthFilter"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    >
                        <option v-for="month in months" :key="month.value" :value="month.value">
                            {{ month.label }}
                        </option>
                    </select>
                    <select
                        v-model="yearFilter"
                        class="w-24 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    >
                        <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paket</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penagih</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tagihan</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Terbayar</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sisa</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Nunggak</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="(invoice, index) in invoices.data" :key="invoice.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ invoices.from + index }}
                            </td>
                            <td class="px-4 py-3 text-sm font-mono">
                                {{ invoice.customer?.customer_id }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-sm">{{ invoice.customer?.name }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ invoice.customer?.area?.name || '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ invoice.customer?.package?.name || '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ invoice.customer?.collector?.name || '-' }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-medium">
                                {{ formatCurrency(invoice.total_amount) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-green-600">
                                {{ formatCurrency(invoice.paid_amount) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-medium text-red-600">
                                {{ formatCurrency(invoice.remaining_amount) }}
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['px-2 py-1 text-xs rounded-full', statusClass(invoice.status)]">
                                    {{ statusLabel(invoice.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs font-bold rounded-full',
                                        invoice.overdue_months >= 2 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'
                                    ]"
                                >
                                    {{ invoice.overdue_months }} bln
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link
                                        :href="`/admin/invoices/${invoice.id}`"
                                        class="p-1 text-gray-500 hover:text-blue-600"
                                        title="Lihat Invoice"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </Link>
                                    <Link
                                        :href="`/admin/customers/${invoice.customer?.id}`"
                                        class="p-1 text-gray-500 hover:text-green-600"
                                        title="Lihat Pelanggan"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="!invoices.data?.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="mt-4 text-gray-500">Semua pelanggan sudah lunas untuk periode ini</p>
            </div>

            <!-- Pagination -->
            <div v-if="invoices.data?.length" class="px-4 py-3 border-t flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Menampilkan {{ invoices.from }} - {{ invoices.to }} dari {{ invoices.total }} pelanggan
                </p>
                <div class="flex gap-2">
                    <template v-for="(link, index) in invoices.links" :key="index">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="[
                                'px-3 py-1 text-sm rounded',
                                link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                            ]"
                        >
                            {{ decodeLabel(link.label) }}
                        </Link>
                        <span
                            v-else
                            class="px-3 py-1 text-sm rounded bg-gray-100 text-gray-400 cursor-not-allowed"
                        >
                            {{ decodeLabel(link.label) }}
                        </span>
                    </template>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

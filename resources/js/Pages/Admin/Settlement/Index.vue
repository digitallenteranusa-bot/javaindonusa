<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    settlements: Object,
    filters: Object,
    collectors: Array,
    pendingCount: Number,
    pendingAmount: Number,
})

const statusFilter = ref(props.filters.status || '')
const collectorFilter = ref(props.filters.collector_id || '')
const startDate = ref(props.filters.start_date || '')
const endDate = ref(props.filters.end_date || '')

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

// Format date
const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

// Apply filters
const applyFilters = () => {
    router.get('/admin/settlements', {
        status: statusFilter.value || undefined,
        collector_id: collectorFilter.value || undefined,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Watch filter changes
watch([statusFilter, collectorFilter, startDate, endDate], applyFilters)

// Status badge
const statusClass = (status) => {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-700',
        verified: 'bg-green-100 text-green-700',
        settled: 'bg-green-100 text-green-700',
        rejected: 'bg-red-100 text-red-700',
        discrepancy: 'bg-orange-100 text-orange-700',
    }
    return classes[status] || 'bg-gray-100 text-gray-700'
}

const statusLabel = (status) => {
    const labels = {
        pending: 'Menunggu',
        verified: 'Terverifikasi',
        settled: 'Selesai',
        rejected: 'Ditolak',
        discrepancy: 'Selisih',
    }
    return labels[status] || status
}

// Difference display
const differenceClass = (diff) => {
    if (!diff || diff === 0) return 'text-gray-500'
    return diff > 0 ? 'text-green-600' : 'text-red-600'
}

const formatDifference = (diff) => {
    if (!diff || diff === 0) return '-'
    const prefix = diff > 0 ? '+' : ''
    return prefix + formatCurrency(diff)
}
</script>

<template>
    <Head title="Setoran" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Setoran</h1>
                <div class="flex gap-2">
                    <Link
                        v-if="pendingCount > 0"
                        href="/admin/settlements/pending"
                        class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 flex items-center gap-2"
                    >
                        <span class="bg-yellow-600 text-xs px-2 py-0.5 rounded-full">{{ pendingCount }}</span>
                        Perlu Verifikasi
                    </Link>
                </div>
            </div>
        </template>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Total Setoran</p>
                <p class="text-2xl font-bold">{{ settlements.total }}</p>
            </div>
            <div class="bg-yellow-50 rounded-xl shadow-sm p-4">
                <p class="text-yellow-600 text-xs">Menunggu Verifikasi</p>
                <p class="text-2xl font-bold text-yellow-600">{{ pendingCount }}</p>
                <p class="text-sm text-yellow-500">{{ formatCurrency(pendingAmount) }}</p>
            </div>
            <div class="bg-green-50 rounded-xl shadow-sm p-4">
                <p class="text-green-600 text-xs">Terverifikasi</p>
                <p class="text-2xl font-bold text-green-600">
                    {{ settlements.data?.filter(s => s.status === 'verified' || s.status === 'settled').length || 0 }}
                </p>
            </div>
            <div class="bg-orange-50 rounded-xl shadow-sm p-4">
                <p class="text-orange-600 text-xs">Ada Selisih</p>
                <p class="text-2xl font-bold text-orange-600">
                    {{ settlements.data?.filter(s => s.status === 'discrepancy').length || 0 }}
                </p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <select
                    v-model="statusFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Status</option>
                    <option value="pending">Menunggu</option>
                    <option value="verified">Terverifikasi</option>
                    <option value="settled">Selesai</option>
                    <option value="discrepancy">Selisih</option>
                </select>
                <select
                    v-model="collectorFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Penagih</option>
                    <option v-for="collector in collectors" :key="collector.id" :value="collector.id">
                        {{ collector.name }}
                    </option>
                </select>
                <input
                    v-model="startDate"
                    type="date"
                    class="px-4 py-2 border border-gray-300 rounded-lg"
                    placeholder="Dari tanggal"
                >
                <input
                    v-model="endDate"
                    type="date"
                    class="px-4 py-2 border border-gray-300 rounded-lg"
                    placeholder="Sampai tanggal"
                >
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penagih</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tagihan</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pengeluaran</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Setoran</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aktual</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Selisih</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="settlement in settlements.data" :key="settlement.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">
                                {{ formatDate(settlement.settlement_date) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-sm">{{ settlement.collector?.name }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-green-600">
                                {{ formatCurrency(settlement.total_collection) }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-red-600">
                                {{ formatCurrency(settlement.total_expense || settlement.approved_expense) }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium">
                                {{ formatCurrency(settlement.expected_amount) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span v-if="settlement.actual_amount" class="font-medium">
                                    {{ formatCurrency(settlement.actual_amount) }}
                                </span>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span :class="['font-medium text-sm', differenceClass(settlement.difference)]">
                                    {{ formatDifference(settlement.difference) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['px-2 py-1 text-xs rounded-full', statusClass(settlement.status)]">
                                    {{ statusLabel(settlement.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/admin/settlements/${settlement.id}`"
                                    class="p-1 text-gray-500 hover:text-blue-600"
                                    title="Lihat Detail"
                                >
                                    <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="!settlements.data?.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <p class="mt-4 text-gray-500">Tidak ada setoran ditemukan</p>
            </div>

            <!-- Pagination -->
            <div v-if="settlements.data?.length" class="px-4 py-3 border-t flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Menampilkan {{ settlements.from }} - {{ settlements.to }} dari {{ settlements.total }} setoran
                </p>
                <div class="flex gap-2">
                    <Link
                        v-for="link in settlements.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-1 text-sm rounded',
                            link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

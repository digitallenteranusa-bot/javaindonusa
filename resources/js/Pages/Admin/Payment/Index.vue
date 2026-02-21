<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    payments: Object,
    filters: Object,
    stats: Object,
    collectors: Array,
})

const search = ref(props.filters.search || '')
const methodFilter = ref(props.filters.payment_method || '')
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

// Format datetime
const formatDateTime = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

// Apply filters
const applyFilters = () => {
    router.get('/admin/payments', {
        search: search.value || undefined,
        payment_method: methodFilter.value || undefined,
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
watch([methodFilter, statusFilter, collectorFilter, startDate, endDate], applyFilters)

// Quick date filters
const setDateFilter = (type) => {
    const today = new Date()

    switch (type) {
        case 'today':
            startDate.value = today.toISOString().split('T')[0]
            endDate.value = today.toISOString().split('T')[0]
            break
        case 'week':
            const weekAgo = new Date(today)
            weekAgo.setDate(weekAgo.getDate() - 7)
            startDate.value = weekAgo.toISOString().split('T')[0]
            endDate.value = today.toISOString().split('T')[0]
            break
        case 'month':
            startDate.value = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0]
            endDate.value = today.toISOString().split('T')[0]
            break
        case 'clear':
            startDate.value = ''
            endDate.value = ''
            break
    }
}

// Cancel payment modal
const showCancelModal = ref(false)
const cancelPayment = ref(null)
const cancelReason = ref('')

const openCancelModal = (payment) => {
    cancelPayment.value = payment
    cancelReason.value = ''
    showCancelModal.value = true
}

const submitCancel = () => {
    router.post(`/admin/payments/${cancelPayment.value.id}/cancel`, {
        reason: cancelReason.value,
    }, {
        onSuccess: () => {
            showCancelModal.value = false
            cancelPayment.value = null
        },
    })
}

// Check if payment can be cancelled (within 24 hours)
const canCancel = (payment) => {
    if (payment.status === 'cancelled') return false
    const createdAt = new Date(payment.created_at)
    const now = new Date()
    const hoursDiff = (now - createdAt) / (1000 * 60 * 60)
    return hoursDiff <= 24
}

const showExportMenu = ref(false)
const exportMenuRef = ref(null)

// Click outside to close export menu
const handleClickOutside = (event) => {
    if (exportMenuRef.value && !exportMenuRef.value.contains(event.target)) {
        showExportMenu.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
})

// Export functions
const exportExcel = () => {
    const params = new URLSearchParams()
    if (startDate.value) params.append('start_date', startDate.value)
    if (endDate.value) params.append('end_date', endDate.value)
    if (methodFilter.value) params.append('payment_method', methodFilter.value)
    if (collectorFilter.value) params.append('collector_id', collectorFilter.value)

    window.location.href = `/admin/payments-export?${params.toString()}`
    showExportMenu.value = false
}

const downloadReceipt = (payment) => {
    window.open(`/admin/payments/${payment.id}/pdf`, '_blank')
}

const previewReceipt = (payment) => {
    window.open(`/admin/payments/${payment.id}/pdf/preview`, '_blank')
}
</script>

<template>
    <Head title="Pembayaran" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Pembayaran</h1>
                <div class="flex gap-2">
                    <!-- Daily Summary Link -->
                    <Link
                        href="/admin/payments/daily-summary"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Rekap Harian
                    </Link>
                    <!-- Export Dropdown -->
                    <div ref="exportMenuRef" class="relative">
                        <button
                            @click.stop="showExportMenu = !showExportMenu"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center gap-2"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Export
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div
                            v-if="showExportMenu"
                            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10 py-1"
                        >
                            <button
                                @click="exportExcel"
                                class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                            >
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Export Excel
                            </button>
                        </div>
                    </div>
                    <Link
                        href="/admin/payments/create"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Input Pembayaran
                    </Link>
                </div>
            </div>
        </template>

        <!-- Summary Cards -->
        <div class="bg-gradient-to-r from-green-600 to-green-800 rounded-xl shadow-sm p-4 sm:p-6 mb-6 text-white">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 sm:gap-6">
                <div class="col-span-2 md:col-span-1">
                    <p class="text-green-200 text-xs sm:text-sm">Total Pembayaran</p>
                    <p class="text-lg sm:text-xl lg:text-2xl font-bold truncate">{{ formatCurrency(stats?.total_amount) }}</p>
                    <p class="text-green-200 text-xs sm:text-sm mt-1">{{ stats?.total_count || 0 }} transaksi</p>
                </div>
                <div>
                    <p class="text-green-200 text-xs sm:text-sm">Cash</p>
                    <p class="text-lg sm:text-xl lg:text-2xl font-bold truncate">{{ formatCurrency(stats?.cash) }}</p>
                </div>
                <div>
                    <p class="text-green-200 text-xs sm:text-sm">Transfer</p>
                    <p class="text-lg sm:text-xl lg:text-2xl font-bold truncate">{{ formatCurrency(stats?.transfer) }}</p>
                </div>
                <div>
                    <p class="text-green-200 text-xs sm:text-sm">Rata-rata</p>
                    <p class="text-lg sm:text-xl lg:text-2xl font-bold truncate">{{ formatCurrency(stats?.total_count ? stats?.total_amount / stats?.total_count : 0) }}</p>
                </div>
                <div v-if="stats?.cancelled_count > 0">
                    <p class="text-red-200 text-xs sm:text-sm">Dibatalkan</p>
                    <p class="text-lg sm:text-xl lg:text-2xl font-bold text-red-200">{{ stats?.cancelled_count }}</p>
                    <p class="text-red-200 text-xs mt-1">transaksi</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Search -->
                <div class="md:col-span-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari no. pembayaran atau pelanggan..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        @keyup.enter="applyFilters"
                    >
                </div>
                <!-- Status Filter -->
                <div class="md:col-span-2">
                    <select
                        v-model="statusFilter"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Semua Status</option>
                        <option value="success">Sukses</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>
                </div>
                <!-- Method Filter -->
                <div class="md:col-span-2">
                    <select
                        v-model="methodFilter"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Semua Metode</option>
                        <option value="cash">Cash</option>
                        <option value="transfer">Transfer</option>
                    </select>
                </div>
                <!-- Collector Filter -->
                <div class="md:col-span-2">
                    <select
                        v-model="collectorFilter"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Semua Penagih</option>
                        <option v-for="collector in collectors" :key="collector.id" :value="collector.id">
                            {{ collector.name }}
                        </option>
                    </select>
                </div>
                <!-- Date Filters -->
                <div class="md:col-span-3 flex gap-2">
                    <input
                        v-model="startDate"
                        type="date"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                        placeholder="Dari"
                    >
                    <input
                        v-model="endDate"
                        type="date"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                        placeholder="Sampai"
                    >
                </div>
            </div>
            <!-- Quick Date Filters -->
            <div class="flex gap-2 mt-3">
                <button
                    @click="setDateFilter('today')"
                    :class="[
                        'px-3 py-1 text-xs rounded-full transition-colors',
                        startDate === new Date().toISOString().split('T')[0] && endDate === new Date().toISOString().split('T')[0]
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    ]"
                >
                    Hari Ini
                </button>
                <button
                    @click="setDateFilter('week')"
                    class="px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded-full hover:bg-gray-200"
                >
                    7 Hari Terakhir
                </button>
                <button
                    @click="setDateFilter('month')"
                    class="px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded-full hover:bg-gray-200"
                >
                    Bulan Ini
                </button>
                <button
                    v-if="startDate || endDate || methodFilter || statusFilter || collectorFilter"
                    @click="setDateFilter('clear'); methodFilter = ''; statusFilter = ''; collectorFilter = ''"
                    class="px-3 py-1 text-xs bg-red-100 text-red-700 rounded-full hover:bg-red-200"
                >
                    Reset Semua Filter
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Pembayaran</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pelanggan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Metode</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penagih</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="payment in payments.data" :key="payment.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/admin/payments/${payment.id}`"
                                    class="text-blue-600 hover:underline font-mono text-sm"
                                >
                                    {{ payment.payment_number }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ formatDateTime(payment.created_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div>
                                    <Link
                                        :href="`/admin/customers/${payment.customer?.id}`"
                                        class="font-medium text-sm hover:text-blue-600"
                                    >
                                        {{ payment.customer?.name }}
                                    </Link>
                                    <p class="text-xs text-gray-500">{{ payment.customer?.customer_id }}</p>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded-full capitalize',
                                        payment.payment_method === 'cash'
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-blue-100 text-blue-700'
                                    ]"
                                >
                                    {{ payment.payment_method }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-semibold text-green-600">{{ formatCurrency(payment.amount) }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span v-if="payment.collector" class="text-gray-700">
                                    {{ payment.collector.name }}
                                </span>
                                <span v-else-if="payment.received_by" class="text-blue-600">
                                    {{ payment.received_by?.name }} (Admin)
                                </span>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded-full',
                                        payment.status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'
                                    ]"
                                >
                                    {{ payment.status === 'cancelled' ? 'Dibatalkan' : 'Sukses' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link
                                        :href="`/admin/payments/${payment.id}`"
                                        class="p-1 text-gray-500 hover:text-blue-600"
                                        title="Lihat"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>
                                    <button
                                        @click="downloadReceipt(payment)"
                                        class="p-1 text-gray-500 hover:text-red-600"
                                        title="Download Kwitansi"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="canCancel(payment)"
                                        @click="openCancelModal(payment)"
                                        class="p-1 text-gray-500 hover:text-red-600"
                                        title="Batalkan (hanya dalam 24 jam)"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="!payments.data?.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <p class="mt-4 text-gray-500">Tidak ada pembayaran ditemukan</p>
            </div>

            <!-- Pagination -->
            <div v-if="payments.data?.length" class="px-4 py-3 border-t flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Menampilkan {{ payments.from }} - {{ payments.to }} dari {{ payments.total }} pembayaran
                </p>
                <div class="flex gap-2">
                    <Link
                        v-for="link in payments.links"
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

        <!-- Cancel Modal -->
        <div
            v-if="showCancelModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showCancelModal = false"
        >
            <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2 text-red-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Batalkan Pembayaran
                </h3>

                <div class="space-y-4">
                    <!-- Payment Info -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-mono text-sm font-medium">{{ cancelPayment?.payment_number }}</p>
                                <p class="text-gray-500 text-sm">{{ cancelPayment?.customer?.name }}</p>
                            </div>
                            <p class="font-semibold text-green-600">{{ formatCurrency(cancelPayment?.amount) }}</p>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">
                            <p>Metode: <span class="capitalize">{{ cancelPayment?.payment_method }}</span></p>
                            <p>Waktu: {{ formatDateTime(cancelPayment?.created_at) }}</p>
                        </div>
                    </div>

                    <!-- Warning -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                        <p class="font-medium">Perhatian:</p>
                        <ul class="list-disc list-inside mt-1 text-xs">
                            <li>Pembatalan hanya dapat dilakukan dalam 24 jam</li>
                            <li>Hutang pelanggan akan dikembalikan</li>
                            <li>Tindakan ini tidak dapat dibatalkan</li>
                        </ul>
                    </div>

                    <!-- Reason -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Alasan Pembatalan <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="cancelReason"
                            rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            placeholder="Masukkan alasan pembatalan..."
                        ></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3">
                        <button
                            @click="showCancelModal = false"
                            class="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                        >
                            Batal
                        </button>
                        <button
                            @click="submitCancel"
                            :disabled="!cancelReason"
                            class="flex-1 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Batalkan Pembayaran
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

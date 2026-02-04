<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'

const props = defineProps({
    invoices: Object,
    filters: Object,
    stats: Object,
    years: Array,
    months: Array,
})

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const yearFilter = ref(props.filters.period_year || '')
const monthFilter = ref(props.filters.period_month || '')

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
    router.get('/admin/invoices', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        period_year: yearFilter.value || undefined,
        period_month: monthFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Watch filter changes
watch([statusFilter, yearFilter, monthFilter], applyFilters)

// Status badge
const statusClass = (status) => {
    const classes = {
        paid: 'bg-green-100 text-green-700',
        pending: 'bg-yellow-100 text-yellow-700',
        partial: 'bg-blue-100 text-blue-700',
        overdue: 'bg-red-100 text-red-700',
        cancelled: 'bg-gray-100 text-gray-500',
    }
    return classes[status] || 'bg-gray-100 text-gray-700'
}

const statusLabel = (status) => {
    const labels = {
        paid: 'Lunas',
        pending: 'Belum Bayar',
        partial: 'Sebagian',
        overdue: 'Jatuh Tempo',
        cancelled: 'Dibatalkan',
    }
    return labels[status] || status
}

// Generate invoices
const generateInvoices = () => {
    if (confirm('Generate invoice untuk semua pelanggan bulan ini?')) {
        router.post('/admin/invoices/generate', {
            month: new Date().getMonth() + 1,
            year: new Date().getFullYear(),
        })
    }
}

// Update overdue status
const updateOverdue = () => {
    router.post('/admin/invoices/update-overdue')
}

// ========== Generate Invoice for Selected Customers ==========
const showSelectCustomerModal = ref(false)
const selectPeriodMonth = ref(new Date().getMonth() + 1)
const selectPeriodYear = ref(new Date().getFullYear())
const customerSearch = ref('')
const availableCustomers = ref([])
const selectedCustomerIds = ref([])
const loadingCustomers = ref(false)
const generatingInvoices = ref(false)
const showAllCustomers = ref(false)

// Month names in Indonesian
const monthNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
]

const openSelectCustomerModal = () => {
    showSelectCustomerModal.value = true
    selectPeriodMonth.value = new Date().getMonth() + 1
    selectPeriodYear.value = new Date().getFullYear()
    selectedCustomerIds.value = []
    fetchAvailableCustomers()
}

const fetchAvailableCustomers = async () => {
    loadingCustomers.value = true
    try {
        const response = await axios.get('/admin/invoices/customers-without-invoice', {
            params: {
                month: selectPeriodMonth.value,
                year: selectPeriodYear.value,
                search: customerSearch.value,
                show_all: showAllCustomers.value ? 1 : 0,
            }
        })
        availableCustomers.value = response.data.customers
    } catch (error) {
        console.error('Failed to fetch customers:', error)
        availableCustomers.value = []
    } finally {
        loadingCustomers.value = false
    }
}

// Watch for period changes
watch([selectPeriodMonth, selectPeriodYear, showAllCustomers], () => {
    selectedCustomerIds.value = []
    fetchAvailableCustomers()
})

// Debounce search
let searchTimeout = null
const searchCustomers = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        fetchAvailableCustomers()
    }, 300)
}

const toggleCustomerSelection = (customerId) => {
    const index = selectedCustomerIds.value.indexOf(customerId)
    if (index > -1) {
        selectedCustomerIds.value.splice(index, 1)
    } else {
        selectedCustomerIds.value.push(customerId)
    }
}

const toggleSelectAllCustomers = () => {
    const selectableCustomers = availableCustomers.value.filter(c => !c.has_invoice)
    if (selectedCustomerIds.value.length === selectableCustomers.length && selectableCustomers.length > 0) {
        selectedCustomerIds.value = []
    } else {
        selectedCustomerIds.value = selectableCustomers.map(c => c.id)
    }
}

const generateForSelected = () => {
    if (selectedCustomerIds.value.length === 0) {
        alert('Pilih minimal 1 pelanggan')
        return
    }

    if (!confirm(`Generate invoice untuk ${selectedCustomerIds.value.length} pelanggan periode ${monthNames[selectPeriodMonth.value - 1]} ${selectPeriodYear.value}?`)) {
        return
    }

    generatingInvoices.value = true
    router.post('/admin/invoices/generate-selected', {
        customer_ids: selectedCustomerIds.value,
        month: selectPeriodMonth.value,
        year: selectPeriodYear.value,
    }, {
        onSuccess: () => {
            showSelectCustomerModal.value = false
            selectedCustomerIds.value = []
        },
        onFinish: () => {
            generatingInvoices.value = false
        }
    })
}

// Mark as paid
const markAsPaid = (invoice) => {
    if (confirm(`Tandai invoice ${invoice.invoice_number} sebagai lunas?`)) {
        router.post(`/admin/invoices/${invoice.id}/mark-paid`)
    }
}

// Cancel invoice
const showCancelModal = ref(false)
const cancelInvoice = ref(null)
const cancelReason = ref('')
const showExportMenu = ref(false)
const selectedInvoices = ref([])

const openCancelModal = (invoice) => {
    cancelInvoice.value = invoice
    cancelReason.value = ''
    showCancelModal.value = true
}

const submitCancel = () => {
    router.post(`/admin/invoices/${cancelInvoice.value.id}/cancel`, {
        reason: cancelReason.value,
    }, {
        onSuccess: () => {
            showCancelModal.value = false
            cancelInvoice.value = null
        },
    })
}

// Export functions
const exportExcel = () => {
    const params = new URLSearchParams()
    if (yearFilter.value) params.append('period_year', yearFilter.value)
    if (monthFilter.value) params.append('period_month', monthFilter.value)
    if (statusFilter.value) params.append('status', statusFilter.value)

    window.location.href = `/admin/invoices-export?${params.toString()}`
    showExportMenu.value = false
}

const downloadPdf = (invoice) => {
    window.open(`/admin/invoices/${invoice.id}/pdf`, '_blank')
}

const previewPdf = (invoice) => {
    window.open(`/admin/invoices/${invoice.id}/pdf/preview`, '_blank')
}

// Safely decode HTML entities for pagination labels
const decodeLabel = (label) => {
    if (!label) return ''
    return label
        .replace(/&laquo;/g, '\u00AB')
        .replace(/&raquo;/g, '\u00BB')
        .replace(/&amp;/g, '&')
}

const bulkExportPdf = () => {
    if (selectedInvoices.value.length === 0) {
        alert('Pilih minimal 1 invoice untuk export bulk PDF')
        return
    }

    const form = document.createElement('form')
    form.method = 'POST'
    form.action = '/admin/invoices/bulk-pdf'
    form.target = '_blank'

    // Add CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    if (csrfToken) {
        const csrfInput = document.createElement('input')
        csrfInput.type = 'hidden'
        csrfInput.name = '_token'
        csrfInput.value = csrfToken
        form.appendChild(csrfInput)
    }

    // Add selected invoice IDs
    selectedInvoices.value.forEach(id => {
        const input = document.createElement('input')
        input.type = 'hidden'
        input.name = 'invoice_ids[]'
        input.value = id
        form.appendChild(input)
    })

    document.body.appendChild(form)
    form.submit()
    document.body.removeChild(form)
    showExportMenu.value = false
}

const toggleSelectInvoice = (invoiceId) => {
    const index = selectedInvoices.value.indexOf(invoiceId)
    if (index > -1) {
        selectedInvoices.value.splice(index, 1)
    } else {
        selectedInvoices.value.push(invoiceId)
    }
}

const toggleSelectAll = () => {
    if (selectedInvoices.value.length === props.invoices.data.length) {
        selectedInvoices.value = []
    } else {
        selectedInvoices.value = props.invoices.data.map(i => i.id)
    }
}
</script>

<template>
    <Head title="Invoice" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Invoice</h1>
                <div class="flex gap-2">
                    <!-- Export Dropdown -->
                    <div class="relative">
                        <button
                            @click="showExportMenu = !showExportMenu"
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
                            <button
                                @click="bulkExportPdf"
                                class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                            >
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                Bulk Export PDF ({{ selectedInvoices.length }})
                            </button>
                        </div>
                    </div>
                    <button
                        @click="updateOverdue"
                        class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 text-sm"
                    >
                        Update Status Overdue
                    </button>
                    <button
                        @click="generateInvoices"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Generate Semua
                    </button>
                    <button
                        @click="openSelectCustomerModal"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        Generate Pilihan
                    </button>
                </div>
            </div>
        </template>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Total Invoice</p>
                <p class="text-2xl font-bold">{{ stats?.total || 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Belum Bayar</p>
                <p class="text-2xl font-bold text-yellow-600">{{ stats?.pending || 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Sebagian</p>
                <p class="text-2xl font-bold text-blue-600">{{ stats?.partial || 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Lunas</p>
                <p class="text-2xl font-bold text-green-600">{{ stats?.paid || 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Jatuh Tempo</p>
                <p class="text-2xl font-bold text-red-600">{{ stats?.overdue || 0 }}</p>
            </div>
        </div>

        <!-- Summary -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl shadow-sm p-6 mb-6 text-white">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <p class="text-blue-200 text-sm">Total Tagihan Outstanding</p>
                    <p class="text-3xl font-bold">{{ formatCurrency(stats?.total_billed) }}</p>
                </div>
                <div>
                    <p class="text-blue-200 text-sm">Sisa Belum Terbayar</p>
                    <p class="text-3xl font-bold">{{ formatCurrency(stats?.total_outstanding) }}</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="md:col-span-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari no. invoice atau nama pelanggan..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        @keyup.enter="applyFilters"
                    >
                </div>
                <select
                    v-model="statusFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Status</option>
                    <option value="pending">Belum Bayar</option>
                    <option value="partial">Sebagian</option>
                    <option value="paid">Lunas</option>
                    <option value="overdue">Jatuh Tempo</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>
                <select
                    v-model="yearFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Tahun</option>
                    <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                </select>
                <select
                    v-model="monthFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Bulan</option>
                    <option v-for="month in months" :key="month.value" :value="month.value">
                        {{ month.label }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left">
                                <input
                                    type="checkbox"
                                    @change="toggleSelectAll"
                                    :checked="selectedInvoices.length === invoices.data?.length && invoices.data?.length > 0"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Invoice</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pelanggan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jatuh Tempo</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Dibayar</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sisa</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    :checked="selectedInvoices.includes(invoice.id)"
                                    @change="toggleSelectInvoice(invoice.id)"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/admin/invoices/${invoice.id}`"
                                    class="text-blue-600 hover:underline font-mono text-sm"
                                >
                                    {{ invoice.invoice_number }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">
                                <div>
                                    <p class="font-medium text-sm">{{ invoice.customer?.name }}</p>
                                    <p class="text-xs text-gray-500">{{ invoice.customer?.customer_id }}</p>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ invoice.period_month }}/{{ invoice.period_year }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ formatDate(invoice.due_date) }}
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
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link
                                        :href="`/admin/invoices/${invoice.id}`"
                                        class="p-1 text-gray-500 hover:text-blue-600"
                                        title="Lihat"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>
                                    <button
                                        @click="downloadPdf(invoice)"
                                        class="p-1 text-gray-500 hover:text-red-600"
                                        title="Download PDF"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="invoice.status !== 'paid' && invoice.status !== 'cancelled'"
                                        @click="markAsPaid(invoice)"
                                        class="p-1 text-gray-500 hover:text-green-600"
                                        title="Tandai Lunas"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="invoice.status === 'pending'"
                                        @click="openCancelModal(invoice)"
                                        class="p-1 text-gray-500 hover:text-red-600"
                                        title="Batalkan"
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
            <div v-if="!invoices.data?.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="mt-4 text-gray-500">Tidak ada invoice ditemukan</p>
            </div>

            <!-- Pagination -->
            <div v-if="invoices.data?.length" class="px-4 py-3 border-t flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Menampilkan {{ invoices.from }} - {{ invoices.to }} dari {{ invoices.total }} invoice
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

        <!-- Cancel Modal -->
        <div
            v-if="showCancelModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showCancelModal = false"
        >
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Batalkan Invoice</h3>

                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="font-mono text-sm">{{ cancelInvoice?.invoice_number }}</p>
                        <p class="text-gray-500 text-sm">{{ cancelInvoice?.customer?.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Pembatalan *</label>
                        <textarea
                            v-model="cancelReason"
                            rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Masukkan alasan pembatalan..."
                        ></textarea>
                    </div>

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
                            class="flex-1 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50"
                        >
                            Batalkan Invoice
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Select Customer Modal -->
        <div
            v-if="showSelectCustomerModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showSelectCustomerModal = false"
        >
            <div class="bg-white rounded-xl w-full max-w-3xl max-h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="p-4 border-b">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold">Generate Invoice Pelanggan Pilihan</h3>
                        <button @click="showSelectCustomerModal = false" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Period Selection -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
                            <select
                                v-model="selectPeriodMonth"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="(name, idx) in monthNames" :key="idx" :value="idx + 1">
                                    {{ name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
                            <select
                                v-model="selectPeriodYear"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Search -->
                    <input
                        v-model="customerSearch"
                        @input="searchCustomers"
                        type="text"
                        placeholder="Cari nama, ID, atau telepon pelanggan..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    >

                    <!-- Show All Toggle -->
                    <label class="flex items-center gap-2 mt-3 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="showAllCustomers"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        >
                        <span class="text-sm text-gray-600">Tampilkan semua pelanggan (termasuk yang sudah ada invoice)</span>
                    </label>
                </div>

                <!-- Customer List -->
                <div class="flex-1 overflow-y-auto p-4">
                    <div v-if="loadingCustomers" class="flex items-center justify-center py-8">
                        <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span class="ml-2 text-gray-600">Memuat pelanggan...</span>
                    </div>

                    <div v-else-if="availableCustomers.length === 0" class="text-center py-8 text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p>Semua pelanggan sudah memiliki invoice untuk periode ini</p>
                    </div>

                    <div v-else>
                        <!-- Select All -->
                        <div class="flex items-center justify-between mb-3 pb-3 border-b">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    :checked="selectedCustomerIds.length === availableCustomers.filter(c => !c.has_invoice).length && availableCustomers.filter(c => !c.has_invoice).length > 0"
                                    @change="toggleSelectAllCustomers"
                                    :disabled="availableCustomers.filter(c => !c.has_invoice).length === 0"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                                >
                                <span class="font-medium">Pilih Semua</span>
                            </label>
                            <span class="text-sm text-gray-500">
                                {{ availableCustomers.filter(c => !c.has_invoice).length }} pelanggan belum ada invoice
                                <span v-if="showAllCustomers && availableCustomers.filter(c => c.has_invoice).length > 0" class="text-green-600">
                                    · {{ availableCustomers.filter(c => c.has_invoice).length }} sudah ada
                                </span>
                            </span>
                        </div>

                        <!-- Customer Items -->
                        <div class="space-y-2">
                            <label
                                v-for="customer in availableCustomers"
                                :key="customer.id"
                                class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer"
                                :class="{
                                    'border-blue-500 bg-blue-50': selectedCustomerIds.includes(customer.id),
                                    'border-green-300 bg-green-50': customer.has_invoice && !selectedCustomerIds.includes(customer.id),
                                    'hover:bg-gray-50': !customer.has_invoice && !selectedCustomerIds.includes(customer.id)
                                }"
                            >
                                <input
                                    type="checkbox"
                                    :checked="selectedCustomerIds.includes(customer.id)"
                                    @change="toggleCustomerSelection(customer.id)"
                                    :disabled="customer.has_invoice"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                                >
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="font-medium text-gray-900">{{ customer.name }}</p>
                                        <span v-if="customer.has_invoice" class="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded-full">
                                            Sudah ada invoice
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        {{ customer.customer_id }} · {{ customer.phone }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ customer.package?.name || '-' }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ formatCurrency(customer.package?.price) }}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-4 border-t bg-gray-50">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">
                            {{ selectedCustomerIds.length }} pelanggan dipilih
                        </span>
                        <div class="flex gap-3">
                            <button
                                @click="showSelectCustomerModal = false"
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100"
                            >
                                Batal
                            </button>
                            <button
                                @click="generateForSelected"
                                :disabled="selectedCustomerIds.length === 0 || generatingInvoices"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center gap-2"
                            >
                                <svg v-if="generatingInvoices" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span v-if="generatingInvoices">Memproses...</span>
                                <span v-else>Generate {{ selectedCustomerIds.length }} Invoice</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    invoice: Object,
})

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
        month: 'long',
        day: 'numeric',
    })
}

// Format datetime
const formatDateTime = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('id-ID')
}

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

// Mark as paid
const markAsPaid = () => {
    if (confirm('Tandai invoice ini sebagai lunas?')) {
        router.post(`/admin/invoices/${props.invoice.id}/mark-paid`)
    }
}

// Cancel invoice
const showCancelModal = ref(false)
const cancelReason = ref('')

const submitCancel = () => {
    router.post(`/admin/invoices/${props.invoice.id}/cancel`, {
        reason: cancelReason.value,
    }, {
        onSuccess: () => {
            showCancelModal.value = false
        },
    })
}

// Print invoice
const printInvoice = () => {
    window.print()
}
</script>

<template>
    <Head :title="`Invoice ${invoice.invoice_number}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/invoices" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ invoice.invoice_number }}</h1>
                        <p class="text-gray-500">Periode {{ invoice.period_month }}/{{ invoice.period_year }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span :class="['px-3 py-1 text-sm rounded-full', statusClass(invoice.status)]">
                        {{ statusLabel(invoice.status) }}
                    </span>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Invoice Details -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h2 class="text-lg font-semibold">Detail Invoice</h2>
                        </div>
                        <button
                            @click="printInvoice"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center gap-2 text-sm"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print
                        </button>
                    </div>

                    <div class="border rounded-lg overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Deskripsi</th>
                                    <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ invoice.package_name }}</p>
                                        <p class="text-sm text-gray-500">
                                            Periode {{ invoice.period_month }}/{{ invoice.period_year }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{ formatCurrency(invoice.package_price) }}
                                    </td>
                                </tr>
                                <tr v-if="invoice.additional_charges > 0">
                                    <td class="px-4 py-3 text-gray-600">Biaya Tambahan</td>
                                    <td class="px-4 py-3 text-right">
                                        {{ formatCurrency(invoice.additional_charges) }}
                                    </td>
                                </tr>
                                <tr v-if="invoice.discount > 0">
                                    <td class="px-4 py-3 text-gray-600">Diskon</td>
                                    <td class="px-4 py-3 text-right text-green-600">
                                        -{{ formatCurrency(invoice.discount) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td class="px-4 py-3 font-semibold">Total</td>
                                    <td class="px-4 py-3 text-right font-bold text-lg">
                                        {{ formatCurrency(invoice.total_amount) }}
                                    </td>
                                </tr>
                                <tr v-if="invoice.paid_amount > 0">
                                    <td class="px-4 py-3 text-green-600">Sudah Dibayar</td>
                                    <td class="px-4 py-3 text-right text-green-600 font-semibold">
                                        {{ formatCurrency(invoice.paid_amount) }}
                                    </td>
                                </tr>
                                <tr v-if="invoice.remaining_amount > 0">
                                    <td class="px-4 py-3 text-red-600 font-semibold">Sisa</td>
                                    <td class="px-4 py-3 text-right text-red-600 font-bold">
                                        {{ formatCurrency(invoice.remaining_amount) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Riwayat Pembayaran</h2>

                    <div v-if="invoice.payments?.length" class="space-y-3">
                        <div
                            v-for="payment in invoice.payments"
                            :key="payment.id"
                            class="flex items-center justify-between p-4 bg-gray-50 rounded-lg"
                        >
                            <div>
                                <p class="font-mono text-sm">{{ payment.payment_number }}</p>
                                <p class="text-xs text-gray-500">{{ formatDateTime(payment.created_at) }}</p>
                                <p class="text-xs text-gray-500 capitalize">{{ payment.payment_method }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-green-600">{{ formatCurrency(payment.amount) }}</p>
                                <Link
                                    :href="`/admin/payments/${payment.id}`"
                                    class="text-xs text-blue-600 hover:underline"
                                >
                                    Lihat Detail
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-8 text-gray-500">
                        Belum ada pembayaran
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Customer Info -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Pelanggan</h2>

                    <div class="space-y-3">
                        <div>
                            <p class="text-gray-500 text-sm">Nama</p>
                            <Link
                                :href="`/admin/customers/${invoice.customer?.id}`"
                                class="font-medium text-blue-600 hover:underline"
                            >
                                {{ invoice.customer?.name }}
                            </Link>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">ID Pelanggan</p>
                            <p class="font-mono">{{ invoice.customer?.customer_id }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">Telepon</p>
                            <p>{{ invoice.customer?.phone }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">Email</p>
                            <p>{{ invoice.customer?.email || '-' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Invoice Info -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Informasi</h2>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Dibuat</span>
                            <span>{{ formatDate(invoice.created_at) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Jatuh Tempo</span>
                            <span>{{ formatDate(invoice.due_date) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Periode</span>
                            <span>{{ formatDate(invoice.period_start) }} - {{ formatDate(invoice.period_end) }}</span>
                        </div>
                        <div v-if="invoice.paid_at" class="flex justify-between">
                            <span class="text-gray-500">Lunas Pada</span>
                            <span class="text-green-600">{{ formatDate(invoice.paid_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Aksi</h2>

                    <div class="space-y-2">
                        <Link
                            :href="`/admin/payments/create?customer_id=${invoice.customer?.id}`"
                            class="w-full py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Input Pembayaran
                        </Link>

                        <button
                            v-if="invoice.status !== 'paid' && invoice.status !== 'cancelled'"
                            @click="markAsPaid"
                            class="w-full py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 text-sm"
                        >
                            Tandai Lunas
                        </button>

                        <button
                            v-if="invoice.status === 'pending'"
                            @click="showCancelModal = true"
                            class="w-full py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-sm"
                        >
                            Batalkan Invoice
                        </button>

                        <a
                            :href="`https://wa.me/62${invoice.customer?.phone?.replace(/^0/, '')}?text=Yth.%20${encodeURIComponent(invoice.customer?.name)}%0A%0AInvoice%20${encodeURIComponent(invoice.invoice_number)}%0APeriode:%20${invoice.period_month}/${invoice.period_year}%0ATotal:%20${formatCurrency(invoice.remaining_amount)}%0A%0AMohon%20segera%20melakukan%20pembayaran.%20Terima%20kasih.`"
                            target="_blank"
                            class="w-full py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 text-sm flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                            </svg>
                            Kirim via WhatsApp
                        </a>
                    </div>
                </div>

                <!-- Notes -->
                <div v-if="invoice.notes" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Catatan</h2>
                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ invoice.notes }}</p>
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Pembatalan *</label>
                        <textarea
                            v-model="cancelReason"
                            rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
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
                            Batalkan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

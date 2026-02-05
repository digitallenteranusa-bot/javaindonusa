<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    payment: Object,
})

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
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

// Cancel payment
const showCancelModal = ref(false)
const cancelReason = ref('')

const submitCancel = () => {
    router.post(`/admin/payments/${props.payment.id}/cancel`, {
        reason: cancelReason.value,
    }, {
        onSuccess: () => {
            showCancelModal.value = false
        },
    })
}

// Print receipt
const printReceipt = () => {
    window.print()
}
</script>

<template>
    <Head :title="`Pembayaran ${payment.payment_number}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/payments" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ payment.payment_number }}</h1>
                        <p class="text-gray-500">{{ formatDateTime(payment.created_at) }}</p>
                    </div>
                </div>
                <span
                    :class="[
                        'px-3 py-1 text-sm rounded-full',
                        payment.status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'
                    ]"
                >
                    {{ payment.status === 'cancelled' ? 'Dibatalkan' : 'Sukses' }}
                </span>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Payment Receipt -->
                <div class="bg-white rounded-xl shadow-sm p-6" id="receipt">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Bukti Pembayaran</h2>
                            <p class="text-gray-500">Java Indonusa Internet</p>
                        </div>
                        <button
                            @click="printReceipt"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center gap-2 text-sm print:hidden"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print
                        </button>
                    </div>

                    <div class="border-t border-b border-dashed py-4 my-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-500">No. Pembayaran</p>
                                <p class="font-mono font-semibold">{{ payment.payment_number }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Tanggal</p>
                                <p class="font-medium">{{ formatDateTime(payment.created_at) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Info -->
                    <div class="mb-6">
                        <p class="text-gray-500 text-sm mb-2">Pelanggan</p>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="font-semibold">{{ payment.customer?.name }}</p>
                            <p class="text-sm text-gray-600">{{ payment.customer?.customer_id }}</p>
                            <p class="text-sm text-gray-600">{{ payment.customer?.address }}</p>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Metode Pembayaran</span>
                            <span class="font-medium capitalize">{{ payment.payment_method }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Dialokasikan ke Invoice</span>
                            <span class="font-medium">{{ formatCurrency(payment.allocated_to_invoice) }}</span>
                        </div>
                        <div v-if="payment.allocated_to_debt > 0" class="flex justify-between">
                            <span class="text-gray-600">Dialokasikan ke Hutang</span>
                            <span class="font-medium">{{ formatCurrency(payment.allocated_to_debt) }}</span>
                        </div>
                    </div>

                    <!-- Total -->
                    <div class="border-t border-dashed mt-4 pt-4">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-medium">Total Pembayaran</span>
                            <span class="text-2xl font-bold text-green-600">{{ formatCurrency(payment.amount) }}</span>
                        </div>
                    </div>

                    <!-- Status -->
                    <div v-if="payment.status === 'cancelled'" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-red-700 font-medium">Pembayaran Dibatalkan</p>
                        <p class="text-sm text-red-600 mt-1">{{ payment.notes }}</p>
                    </div>

                    <!-- Signature Area (for print) -->
                    <div class="mt-8 grid grid-cols-2 gap-8 print:block hidden">
                        <div class="text-center">
                            <p class="text-sm text-gray-500 mb-16">Pelanggan</p>
                            <p class="border-t pt-2">{{ payment.customer?.name }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-sm text-gray-500 mb-16">Petugas</p>
                            <p class="border-t pt-2">{{ payment.received_by?.name || payment.collector?.name || '-' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Invoice Allocation -->
                <div v-if="payment.invoices?.length" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Alokasi ke Invoice</h2>

                    <div class="space-y-3">
                        <div
                            v-for="invoice in payment.invoices"
                            :key="invoice.id"
                            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                        >
                            <div>
                                <Link
                                    :href="`/admin/invoices/${invoice.id}`"
                                    class="font-mono text-blue-600 hover:underline text-sm"
                                >
                                    {{ invoice.invoice_number }}
                                </Link>
                                <p class="text-xs text-gray-500">
                                    Periode {{ invoice.period_month }}/{{ invoice.period_year }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-green-600">{{ formatCurrency(invoice.pivot?.amount) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Payment Info -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Informasi</h2>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Metode</span>
                            <span
                                :class="[
                                    'px-2 py-1 text-xs rounded-full capitalize',
                                    payment.payment_method === 'cash' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'
                                ]"
                            >
                                {{ payment.payment_method }}
                            </span>
                        </div>
                        <div v-if="payment.payment_channel" class="flex justify-between">
                            <span class="text-gray-500">Channel</span>
                            <span class="capitalize">{{ payment.payment_channel }}</span>
                        </div>
                        <div v-if="payment.transfer_proof" class="flex justify-between">
                            <span class="text-gray-500">Bukti Transfer</span>
                            <a :href="`/storage/${payment.transfer_proof}`" target="_blank" class="text-blue-600 hover:underline">
                                Lihat
                            </a>
                        </div>
                        <div v-if="payment.collector" class="flex justify-between">
                            <span class="text-gray-500">Penagih</span>
                            <span>{{ payment.collector.name }}</span>
                        </div>
                        <div v-if="payment.received_by" class="flex justify-between">
                            <span class="text-gray-500">Diterima Oleh</span>
                            <span>{{ payment.received_by.name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div v-if="payment.status !== 'cancelled'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Aksi</h2>

                    <div class="space-y-2">
                        <button
                            @click="showCancelModal = true"
                            class="w-full py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-sm"
                        >
                            Batalkan Pembayaran
                        </button>
                    </div>

                    <p class="text-xs text-gray-500 mt-3">
                        * Pembayaran hanya dapat dibatalkan dalam 24 jam
                    </p>
                </div>

                <!-- Notes -->
                <div v-if="payment.notes" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Catatan</h2>
                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ payment.notes }}</p>
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
                <h3 class="text-lg font-semibold mb-4">Batalkan Pembayaran</h3>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4 text-sm text-yellow-800">
                    <p class="font-medium">Perhatian!</p>
                    <p>Pembatalan pembayaran akan mengembalikan hutang pelanggan. Aksi ini tidak dapat dibatalkan.</p>
                </div>

                <div class="space-y-4">
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
                            Batalkan Pembayaran
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #receipt, #receipt * {
        visibility: visible;
    }
    #receipt {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
}
</style>

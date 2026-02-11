<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import CustomerLayout from '@/Layouts/CustomerLayout.vue'

const props = defineProps({
    customer: Object,
    billing: Object,
    invoices: Array,
    payments: Array,
    isp_info: Object,
    transfer_proof_wa_url: String,
})

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value)
}

// Format date
const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}

// Status badge
const getStatusBadge = (status) => {
    const badges = {
        paid: { class: 'bg-green-100 text-green-600', text: 'Lunas' },
        pending: { class: 'bg-yellow-100 text-yellow-600', text: 'Belum Bayar' },
        partial: { class: 'bg-blue-100 text-blue-600', text: 'Sebagian' },
        overdue: { class: 'bg-red-100 text-red-600', text: 'Jatuh Tempo' },
    }
    return badges[status] || { class: 'bg-gray-100 text-gray-600', text: status }
}

// Open WhatsApp for transfer proof
const openTransferProof = () => {
    window.open(props.transfer_proof_wa_url, '_blank')
}

// Copy bank account
const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text)
    alert('Nomor rekening disalin!')
}
</script>

<template>
    <Head title="Portal Pelanggan" />

    <CustomerLayout :customer="customer">
        <div class="bg-gray-100 pb-4">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white">
            <div class="px-4 py-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-xl font-bold">Halo, {{ customer?.name ?? '-' }}</h1>
                        <p class="text-blue-100 text-sm mt-1">ID: {{ customer?.customer_id ?? '-' }}</p>
                    </div>
                </div>

                <!-- Package Info -->
                <div v-if="customer?.package" class="mt-4 bg-white/10 rounded-lg p-3">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-blue-100 text-xs">Paket Aktif</p>
                            <p class="font-semibold">{{ customer.package.name }}</p>
                            <p class="text-sm text-blue-100">{{ customer.package.speed }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-blue-100 text-xs">Per Bulan</p>
                            <p class="font-bold text-lg">{{ formatCurrency(customer.package.price) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Isolir -->
        <div v-if="billing?.isolation_status" class="px-4 -mt-2">
            <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-red-800">Layanan Terisolir</h3>
                        <p class="text-sm text-red-600 mt-1">{{ billing.isolation_reason }}</p>
                        <p class="text-sm text-red-600 mt-2">
                            Silakan lakukan pembayaran untuk mengaktifkan kembali layanan Anda.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Billing Summary -->
        <div class="px-4 mt-4">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h2 class="font-semibold text-gray-800 mb-3">Ringkasan Tagihan</h2>

                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Hutang</span>
                        <span class="font-bold text-lg" :class="(billing?.total_debt ?? 0) > 0 ? 'text-red-600' : 'text-green-600'">
                            {{ formatCurrency(billing?.total_debt ?? 0) }}
                        </span>
                    </div>

                    <div v-if="(customer?.credit_balance ?? 0) > 0" class="flex justify-between items-center">
                        <span class="text-gray-600">Saldo Kredit</span>
                        <span class="font-bold text-lg text-green-600">
                            {{ formatCurrency(customer?.credit_balance ?? 0) }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Bulan Belum Bayar</span>
                        <span class="font-semibold">{{ billing?.unpaid_months ?? 0 }} bulan</span>
                    </div>

                    <hr>

                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Tagihan Bulan Ini</span>
                        <div class="text-right">
                            <span class="font-semibold">{{ formatCurrency(billing?.current_month?.amount ?? 0) }}</span>
                            <span
                                class="ml-2 px-2 py-0.5 rounded-full text-xs"
                                :class="getStatusBadge(billing?.current_month?.status ?? 'pending').class"
                            >
                                {{ getStatusBadge(billing?.current_month?.status ?? 'pending').text }}
                            </span>
                        </div>
                    </div>

                    <div v-if="billing?.current_month?.due_date" class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">Jatuh Tempo</span>
                        <span class="text-gray-700">{{ formatDate(billing.current_month.due_date) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Info -->
        <div class="px-4 mt-4">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h2 class="font-semibold text-gray-800 mb-3">Informasi Pembayaran</h2>

                <!-- Bank Accounts -->
                <div v-if="isp_info?.bank_accounts?.length" class="space-y-3">
                    <div
                        v-for="(bank, index) in isp_info.bank_accounts"
                        :key="index"
                        class="bg-gray-50 rounded-lg p-3"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-gray-800">{{ bank.bank }}</p>
                                <p class="text-lg font-mono mt-1">{{ bank.account }}</p>
                                <p class="text-sm text-gray-600">a.n {{ bank.name }}</p>
                            </div>
                            <button
                                @click="copyToClipboard(bank.account)"
                                class="px-3 py-1 bg-blue-100 text-blue-600 rounded-lg text-sm"
                            >
                                Salin
                            </button>
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Transfer Proof -->
                <div class="mt-4 p-3 bg-green-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-2">
                        Sudah transfer? Kirim bukti transfer via WhatsApp:
                    </p>
                    <button
                        @click="openTransferProof"
                        class="w-full flex items-center justify-center gap-2 py-2 bg-green-500 text-white rounded-lg font-medium"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        </svg>
                        Kirim Bukti Transfer
                    </button>
                </div>

                <!-- Contact Info -->
                <div v-if="isp_info" class="mt-4 text-center text-sm text-gray-600">
                    <p v-if="isp_info.phone">Layanan Pelanggan: {{ isp_info.phone }}</p>
                    <p v-if="isp_info.operational_hours" class="mt-1">{{ isp_info.operational_hours }}</p>
                </div>
            </div>
        </div>

        <!-- Invoice History -->
        <div class="px-4 mt-4">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold text-gray-800">Histori Tagihan</h2>
                    <Link :href="route('customer.invoices')" class="text-blue-600 text-sm">
                        Lihat Semua
                    </Link>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="invoice in invoices.slice(0, 6)"
                        :key="invoice.invoice_number"
                        class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0"
                    >
                        <div>
                            <p class="font-medium text-gray-800">{{ invoice.period }}</p>
                            <p class="text-sm text-gray-500">{{ invoice.invoice_number }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold">{{ formatCurrency(invoice.amount) }}</p>
                            <span
                                class="px-2 py-0.5 rounded-full text-xs"
                                :class="getStatusBadge(invoice.status).class"
                            >
                                {{ getStatusBadge(invoice.status).text }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="!invoices.length" class="text-center py-4 text-gray-500">
                    Belum ada tagihan
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="px-4 mt-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold text-gray-800">Histori Pembayaran</h2>
                    <Link :href="route('customer.payments')" class="text-blue-600 text-sm">
                        Lihat Semua
                    </Link>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="payment in payments.slice(0, 5)"
                        :key="payment.payment_number"
                        class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0"
                    >
                        <div>
                            <p class="font-medium text-gray-800">{{ formatCurrency(payment.amount) }}</p>
                            <p class="text-sm text-gray-500">{{ payment.date }}</p>
                        </div>
                        <div class="text-right">
                            <span class="px-2 py-1 bg-green-100 text-green-600 rounded text-xs capitalize">
                                {{ payment.method }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="!payments.length" class="text-center py-4 text-gray-500">
                    Belum ada pembayaran
                </div>
            </div>
        </div>

        </div>
    </CustomerLayout>
</template>

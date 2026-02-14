<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import CustomerLayout from '@/Layouts/CustomerLayout.vue'

const props = defineProps({
    customer: Object,
    unpaidInvoices: Array,
    activeTransaction: Object,
})

// State
const channels = ref([])
const loadingChannels = ref(false)
const selectedInvoices = ref([])
const selectedMethod = ref(null)
const creating = ref(false)
const transaction = ref(props.activeTransaction || null)
const polling = ref(null)
const error = ref(null)
const paymentSuccess = ref(false)

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value)
}

// Computed
const totalAmount = computed(() => {
    return props.unpaidInvoices
        .filter(inv => selectedInvoices.value.includes(inv.id))
        .reduce((sum, inv) => sum + inv.remaining_amount, 0)
})

const canPay = computed(() => {
    return selectedInvoices.value.length > 0 && selectedMethod.value && !creating.value
})

const groupedChannels = computed(() => {
    const groups = {}
    channels.value.forEach(ch => {
        const group = ch.group || 'Lainnya'
        if (!groups[group]) groups[group] = []
        groups[group].push(ch)
    })
    return groups
})

const countdownText = computed(() => {
    if (!transaction.value?.expired_at) return ''
    const now = new Date()
    const expired = new Date(transaction.value.expired_at)
    const diff = expired - now
    if (diff <= 0) return 'Kadaluarsa'
    const hours = Math.floor(diff / 3600000)
    const minutes = Math.floor((diff % 3600000) / 60000)
    const seconds = Math.floor((diff % 60000) / 1000)
    return `${hours}j ${minutes}m ${seconds}d`
})

// Select all invoices by default
onMounted(() => {
    if (props.unpaidInvoices.length > 0 && !transaction.value) {
        selectedInvoices.value = props.unpaidInvoices.map(inv => inv.id)
    }
    loadChannels()
    if (transaction.value && transaction.value.status === 'UNPAID') {
        startPolling()
    }
})

onUnmounted(() => {
    stopPolling()
})

// Load payment channels
const loadChannels = async () => {
    loadingChannels.value = true
    try {
        const response = await fetch('/portal/tripay/channels')
        const data = await response.json()
        if (data.success) {
            channels.value = data.data
        }
    } catch (e) {
        console.error('Failed to load channels:', e)
    } finally {
        loadingChannels.value = false
    }
}

// Toggle invoice selection
const toggleInvoice = (invoiceId) => {
    const index = selectedInvoices.value.indexOf(invoiceId)
    if (index > -1) {
        selectedInvoices.value.splice(index, 1)
    } else {
        selectedInvoices.value.push(invoiceId)
    }
}

// Select all / deselect all
const toggleAll = () => {
    if (selectedInvoices.value.length === props.unpaidInvoices.length) {
        selectedInvoices.value = []
    } else {
        selectedInvoices.value = props.unpaidInvoices.map(inv => inv.id)
    }
}

// Create transaction
const createTransaction = async () => {
    if (!canPay.value) return

    creating.value = true
    error.value = null

    try {
        const response = await fetch('/portal/tripay/pay', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                method: selectedMethod.value,
                invoice_ids: selectedInvoices.value,
            }),
        })

        const data = await response.json()

        if (response.status === 409 && data.transaction) {
            // Existing unpaid transaction
            transaction.value = data.transaction
            startPolling()
            return
        }

        if (!response.ok) {
            error.value = data.message || 'Gagal membuat transaksi'
            return
        }

        if (data.success) {
            transaction.value = data.transaction
            startPolling()
        } else {
            error.value = data.message || 'Gagal membuat transaksi'
        }
    } catch (e) {
        error.value = 'Terjadi kesalahan. Silakan coba lagi.'
        console.error('Create transaction error:', e)
    } finally {
        creating.value = false
    }
}

// Poll transaction status
const startPolling = () => {
    if (polling.value) return
    polling.value = setInterval(async () => {
        if (!transaction.value?.id) return
        try {
            const response = await fetch(`/portal/tripay/status/${transaction.value.id}`)
            const data = await response.json()
            if (data.status === 'PAID') {
                transaction.value.status = 'PAID'
                paymentSuccess.value = true
                stopPolling()
            } else if (data.status === 'EXPIRED' || data.status === 'FAILED') {
                transaction.value.status = data.status
                stopPolling()
            }
        } catch (e) {
            console.error('Polling error:', e)
        }
    }, 5000)
}

const stopPolling = () => {
    if (polling.value) {
        clearInterval(polling.value)
        polling.value = null
    }
}

// Reset (new transaction)
const resetTransaction = () => {
    transaction.value = null
    selectedMethod.value = null
    error.value = null
    paymentSuccess.value = false
    selectedInvoices.value = props.unpaidInvoices.map(inv => inv.id)
}

// Go back to dashboard
const goToDashboard = () => {
    router.visit('/portal')
}

// Group label mapping
const groupLabel = (group) => {
    const labels = {
        'Virtual Account': 'Virtual Account (VA)',
        'E-Wallet': 'E-Wallet',
        'Convenience Store': 'Minimarket',
        'Direct Debit': 'Direct Debit',
    }
    return labels[group] || group
}

// Countdown updater
const countdownInterval = ref(null)
const countdownDisplay = ref('')

const updateCountdown = () => {
    if (!transaction.value?.expired_at) {
        countdownDisplay.value = ''
        return
    }
    const now = new Date()
    const expired = new Date(transaction.value.expired_at)
    const diff = expired - now
    if (diff <= 0) {
        countdownDisplay.value = 'Kadaluarsa'
        if (transaction.value.status === 'UNPAID') {
            transaction.value.status = 'EXPIRED'
        }
        stopPolling()
        if (countdownInterval.value) {
            clearInterval(countdownInterval.value)
        }
        return
    }
    const hours = Math.floor(diff / 3600000)
    const minutes = Math.floor((diff % 3600000) / 60000)
    const seconds = Math.floor((diff % 60000) / 1000)
    countdownDisplay.value = `${hours}j ${minutes}m ${seconds}d`
}

onMounted(() => {
    countdownInterval.value = setInterval(updateCountdown, 1000)
    updateCountdown()
})

onUnmounted(() => {
    if (countdownInterval.value) clearInterval(countdownInterval.value)
})
</script>

<template>
    <Head title="Bayar Online" />

    <CustomerLayout :customer="customer">
        <div class="bg-gray-100 pb-4">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-600 to-green-800 text-white">
                <div class="px-4 py-6">
                    <div class="flex items-center gap-3">
                        <Link href="/portal" class="text-white/80 hover:text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </Link>
                        <div>
                            <h1 class="text-xl font-bold">Bayar Online</h1>
                            <p class="text-green-100 text-sm mt-1">Pembayaran via QRIS, VA, atau E-Wallet</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Success -->
            <div v-if="paymentSuccess" class="px-4 mt-4">
                <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Pembayaran Berhasil!</h2>
                    <p class="text-gray-600 mb-2">Pembayaran Anda telah diterima dan diproses.</p>
                    <p class="text-lg font-semibold text-green-600 mb-6">{{ formatCurrency(transaction?.amount || 0) }}</p>
                    <button
                        @click="goToDashboard"
                        class="w-full py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700"
                    >
                        Kembali ke Beranda
                    </button>
                </div>
            </div>

            <!-- Active Transaction (waiting for payment) -->
            <div v-else-if="transaction && transaction.status === 'UNPAID'" class="px-4 mt-4 space-y-4">
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="text-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Menunggu Pembayaran</h2>
                        <p class="text-sm text-gray-500 mt-1">Ref: {{ transaction.reference }}</p>
                    </div>

                    <!-- Amount -->
                    <div class="bg-gray-50 rounded-lg p-4 text-center mb-4">
                        <p class="text-sm text-gray-600">Total Bayar</p>
                        <p class="text-2xl font-bold text-gray-800">{{ formatCurrency(transaction.total_amount) }}</p>
                        <p v-if="transaction.fee_customer > 0" class="text-xs text-gray-500 mt-1">
                            Termasuk biaya layanan {{ formatCurrency(transaction.fee_customer) }}
                        </p>
                    </div>

                    <!-- QR Code (for QRIS) -->
                    <div v-if="transaction.qr_url" class="text-center mb-4">
                        <p class="text-sm text-gray-600 mb-2">Scan QR Code untuk membayar:</p>
                        <div class="inline-block p-3 bg-white border-2 border-gray-200 rounded-xl">
                            <img :src="transaction.qr_url" alt="QR Code" class="w-64 h-64 mx-auto">
                        </div>
                    </div>

                    <!-- Checkout URL (for VA / other) -->
                    <div v-if="transaction.checkout_url && !transaction.qr_url" class="text-center mb-4">
                        <p class="text-sm text-gray-600 mb-3">Klik tombol di bawah untuk melakukan pembayaran:</p>
                        <a
                            :href="transaction.checkout_url"
                            target="_blank"
                            class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700"
                        >
                            Lanjutkan Pembayaran
                        </a>
                    </div>

                    <!-- Pay URL (for e-wallet deeplink) -->
                    <div v-if="transaction.pay_url" class="text-center mb-4">
                        <a
                            :href="transaction.pay_url"
                            class="inline-block px-6 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700"
                        >
                            Buka Aplikasi E-Wallet
                        </a>
                    </div>

                    <!-- Countdown -->
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Batas waktu pembayaran:</p>
                        <p class="text-lg font-semibold text-red-600">{{ countdownDisplay }}</p>
                    </div>

                    <!-- Polling indicator -->
                    <div class="mt-4 flex items-center justify-center gap-2 text-sm text-gray-500">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span>Menunggu konfirmasi pembayaran...</span>
                    </div>
                </div>

                <!-- Cancel / New Transaction -->
                <div class="text-center">
                    <button
                        @click="resetTransaction"
                        class="text-sm text-gray-500 hover:text-gray-700 underline"
                    >
                        Buat transaksi baru (transaksi ini akan kadaluarsa otomatis)
                    </button>
                </div>
            </div>

            <!-- Expired / Failed Transaction -->
            <div v-else-if="transaction && (transaction.status === 'EXPIRED' || transaction.status === 'FAILED')" class="px-4 mt-4">
                <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800 mb-2">
                        {{ transaction.status === 'EXPIRED' ? 'Transaksi Kadaluarsa' : 'Transaksi Gagal' }}
                    </h2>
                    <p class="text-gray-600 mb-4">Silakan buat transaksi baru untuk melakukan pembayaran.</p>
                    <button
                        @click="resetTransaction"
                        class="w-full py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700"
                    >
                        Buat Transaksi Baru
                    </button>
                </div>
            </div>

            <!-- Invoice Selection & Payment Method -->
            <div v-else class="space-y-4">
                <!-- Error -->
                <div v-if="error" class="px-4 mt-4">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                        {{ error }}
                    </div>
                </div>

                <!-- No unpaid invoices -->
                <div v-if="unpaidInvoices.length === 0" class="px-4 mt-4">
                    <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h2 class="text-lg font-semibold text-gray-800">Tidak Ada Tagihan</h2>
                        <p class="text-gray-600 mt-2">Semua tagihan Anda sudah lunas.</p>
                        <Link href="/portal" class="inline-block mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Kembali
                        </Link>
                    </div>
                </div>

                <template v-else>
                    <!-- Select Invoices -->
                    <div class="px-4 mt-4">
                        <div class="bg-white rounded-xl shadow-sm p-4">
                            <div class="flex justify-between items-center mb-3">
                                <h2 class="font-semibold text-gray-800">Pilih Tagihan</h2>
                                <button @click="toggleAll" class="text-sm text-blue-600 hover:underline">
                                    {{ selectedInvoices.length === unpaidInvoices.length ? 'Batal Semua' : 'Pilih Semua' }}
                                </button>
                            </div>

                            <div class="space-y-2">
                                <label
                                    v-for="invoice in unpaidInvoices"
                                    :key="invoice.id"
                                    class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                    :class="selectedInvoices.includes(invoice.id) ? 'border-green-300 bg-green-50' : 'border-gray-200 hover:bg-gray-50'"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="selectedInvoices.includes(invoice.id)"
                                        @change="toggleInvoice(invoice.id)"
                                        class="w-4 h-4 text-green-600 rounded"
                                    >
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-800">{{ invoice.period_label }}</p>
                                        <p class="text-xs text-gray-500">{{ invoice.invoice_number }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-800">{{ formatCurrency(invoice.remaining_amount) }}</p>
                                        <span
                                            class="text-xs px-2 py-0.5 rounded-full"
                                            :class="{
                                                'bg-yellow-100 text-yellow-600': invoice.status === 'pending',
                                                'bg-blue-100 text-blue-600': invoice.status === 'partial',
                                                'bg-red-100 text-red-600': invoice.status === 'overdue',
                                            }"
                                        >
                                            {{ invoice.status === 'pending' ? 'Belum Bayar' : invoice.status === 'partial' ? 'Sebagian' : 'Jatuh Tempo' }}
                                        </span>
                                    </div>
                                </label>
                            </div>

                            <!-- Total -->
                            <div v-if="selectedInvoices.length > 0" class="mt-4 pt-4 border-t flex justify-between items-center">
                                <span class="text-gray-600">Total Bayar ({{ selectedInvoices.length }} tagihan)</span>
                                <span class="text-xl font-bold text-green-600">{{ formatCurrency(totalAmount) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Select Payment Method -->
                    <div v-if="selectedInvoices.length > 0" class="px-4">
                        <div class="bg-white rounded-xl shadow-sm p-4">
                            <h2 class="font-semibold text-gray-800 mb-3">Pilih Metode Pembayaran</h2>

                            <div v-if="loadingChannels" class="py-8 text-center text-gray-500">
                                <div class="w-8 h-8 border-2 border-gray-300 border-t-blue-600 rounded-full animate-spin mx-auto mb-2"></div>
                                Memuat metode pembayaran...
                            </div>

                            <div v-else-if="channels.length === 0" class="py-4 text-center text-gray-500 text-sm">
                                Tidak ada metode pembayaran tersedia. Hubungi admin.
                            </div>

                            <div v-else class="space-y-4">
                                <div v-for="(groupChannels, group) in groupedChannels" :key="group">
                                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">{{ groupLabel(group) }}</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button
                                            v-for="channel in groupChannels"
                                            :key="channel.code"
                                            @click="selectedMethod = channel.code"
                                            class="flex items-center gap-2 p-3 rounded-lg border transition-colors text-left"
                                            :class="selectedMethod === channel.code ? 'border-green-500 bg-green-50 ring-1 ring-green-500' : 'border-gray-200 hover:bg-gray-50'"
                                        >
                                            <img v-if="channel.icon_url" :src="channel.icon_url" :alt="channel.name" class="w-8 h-8 object-contain flex-shrink-0">
                                            <div v-else class="w-8 h-8 bg-gray-200 rounded flex items-center justify-center flex-shrink-0">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                                </svg>
                                            </div>
                                            <span class="text-sm font-medium text-gray-700 truncate">{{ channel.name }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pay Button -->
                    <div v-if="selectedInvoices.length > 0 && selectedMethod" class="px-4 mb-4">
                        <button
                            @click="createTransaction"
                            :disabled="!canPay"
                            class="w-full py-4 bg-green-600 text-white rounded-xl font-bold text-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                        >
                            <template v-if="creating">
                                <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                Memproses...
                            </template>
                            <template v-else>
                                Bayar {{ formatCurrency(totalAmount) }}
                            </template>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </CustomerLayout>
</template>

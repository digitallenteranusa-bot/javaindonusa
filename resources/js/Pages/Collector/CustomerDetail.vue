<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import CollectorLayout from '@/Layouts/CollectorLayout.vue'

const props = defineProps({
    customer: Object,
    visitHistory: Array,
})

const page = usePage()

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
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    })
}

// Format datetime
const formatDateTime = (date) => {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

// Get period name
const getPeriodName = (month, year) => {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des']
    return `${months[month - 1]} ${year}`
}

// Get invoice status badge
const getInvoiceStatusBadge = (status) => {
    const badges = {
        'paid': 'bg-green-100 text-green-600',
        'pending': 'bg-yellow-100 text-yellow-600',
        'partial': 'bg-blue-100 text-blue-600',
        'overdue': 'bg-red-100 text-red-600',
    }
    return badges[status] || 'bg-gray-100 text-gray-600'
}

const getInvoiceStatusText = (status) => {
    const texts = {
        'paid': 'Lunas',
        'pending': 'Belum Bayar',
        'partial': 'Sebagian',
        'overdue': 'Jatuh Tempo',
    }
    return texts[status] || status
}

// Payment modal
const showPaymentModal = ref(false)
const paymentType = ref('cash')
const paymentAmount = ref('')
const paymentNotes = ref('')
const transferProof = ref(null)

const openPaymentModal = () => {
    paymentAmount.value = props.customer.total_debt
    paymentNotes.value = ''
    transferProof.value = null
    showPaymentModal.value = true
}

const processPayment = () => {
    const formData = new FormData()
    formData.append('amount', paymentAmount.value)
    formData.append('notes', paymentNotes.value)

    if (paymentType.value === 'transfer' && transferProof.value) {
        formData.append('transfer_proof', transferProof.value)
    }

    const url = paymentType.value === 'cash'
        ? route('collector.payment.cash', props.customer.id)
        : route('collector.payment.transfer', props.customer.id)

    router.post(url, formData, {
        onSuccess: () => {
            showPaymentModal.value = false
        },
    })
}

const handleFileUpload = (event) => {
    transferProof.value = event.target.files[0]
}

// WhatsApp
const openWhatsApp = () => {
    let phone = props.customer.phone?.replace(/[^0-9]/g, '') || ''
    if (phone.startsWith('0')) {
        phone = '62' + phone.substring(1)
    }
    const message = `Yth. Bapak/Ibu ${props.customer.name},\n\nKami mengingatkan bahwa tagihan internet Anda sebesar ${formatCurrency(props.customer.total_debt)} belum terbayar.\n\nMohon segera melakukan pembayaran untuk menghindari pemutusan layanan.\n\nTerima kasih.`
    const url = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`
    window.open(url, '_blank')
}

// Call customer
const callCustomer = () => {
    window.location.href = `tel:${props.customer.phone}`
}

// Navigate to map
const openMaps = () => {
    if (props.customer.latitude && props.customer.longitude) {
        window.open(`https://maps.google.com/?q=${props.customer.latitude},${props.customer.longitude}`, '_blank')
    } else if (props.customer.address) {
        window.open(`https://maps.google.com/?q=${encodeURIComponent(props.customer.address)}`, '_blank')
    }
}

// Active tab
const activeTab = ref('invoices')
</script>

<template>
    <Head :title="`Detail - ${customer.name}`" />

    <CollectorLayout>
        <div class="min-h-screen bg-gray-100 pb-20">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white px-4 py-6">
                <div class="flex items-center gap-3">
                    <Link :href="route('collector.customers')" class="p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div class="flex-1">
                        <h1 class="text-xl font-bold">{{ customer.name }}</h1>
                        <p class="text-blue-100 text-sm">{{ customer.customer_id }}</p>
                    </div>
                    <span
                        v-if="customer.status === 'isolated'"
                        class="px-3 py-1 bg-red-500 text-white text-xs rounded-full font-semibold"
                    >
                        ISOLIR
                    </span>
                    <span
                        v-else-if="customer.status === 'active'"
                        class="px-3 py-1 bg-green-500 text-white text-xs rounded-full font-semibold"
                    >
                        AKTIF
                    </span>
                </div>
            </div>

            <!-- Customer Info Card -->
            <div class="px-4 -mt-4">
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Paket</p>
                            <p class="font-semibold text-gray-800">{{ customer.package?.name || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Total Hutang</p>
                            <p class="font-bold" :class="customer.total_debt > 0 ? 'text-red-600' : 'text-green-600'">
                                {{ formatCurrency(customer.total_debt) }}
                            </p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-gray-500">Alamat</p>
                            <p class="text-sm text-gray-800">{{ customer.address || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Telepon</p>
                            <p class="text-sm text-gray-800">{{ customer.phone || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Area</p>
                            <p class="text-sm text-gray-800">{{ customer.area?.name || '-' }}</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-2 mt-4 pt-4 border-t">
                        <button
                            @click="callCustomer"
                            class="flex-1 flex items-center justify-center gap-1 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            Telepon
                        </button>
                        <button
                            @click="openWhatsApp"
                            class="flex-1 flex items-center justify-center gap-1 py-2 bg-green-500 text-white rounded-lg text-sm"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                            </svg>
                            WA
                        </button>
                        <button
                            @click="openMaps"
                            class="flex-1 flex items-center justify-center gap-1 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Maps
                        </button>
                        <button
                            v-if="customer.total_debt > 0"
                            @click="openPaymentModal"
                            class="flex-1 flex items-center justify-center gap-1 py-2 bg-blue-500 text-white rounded-lg text-sm"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                            Bayar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="px-4 mt-4">
                <div class="flex bg-white rounded-lg shadow-sm p-1">
                    <button
                        @click="activeTab = 'invoices'"
                        :class="[
                            'flex-1 py-2 text-sm font-medium rounded-lg transition-colors',
                            activeTab === 'invoices' ? 'bg-blue-500 text-white' : 'text-gray-600'
                        ]"
                    >
                        Tagihan
                    </button>
                    <button
                        @click="activeTab = 'payments'"
                        :class="[
                            'flex-1 py-2 text-sm font-medium rounded-lg transition-colors',
                            activeTab === 'payments' ? 'bg-blue-500 text-white' : 'text-gray-600'
                        ]"
                    >
                        Pembayaran
                    </button>
                </div>
            </div>

            <!-- Invoices Tab -->
            <div v-if="activeTab === 'invoices'" class="px-4 mt-4 space-y-3">
                <h3 class="font-semibold text-gray-800">Histori Tagihan (12 Bulan Terakhir)</h3>

                <div v-if="customer.invoices?.length > 0" class="space-y-2">
                    <div
                        v-for="invoice in customer.invoices"
                        :key="invoice.id"
                        class="bg-white rounded-lg shadow-sm p-4"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-gray-800">
                                    {{ getPeriodName(invoice.period_month, invoice.period_year) }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    Jatuh Tempo: {{ formatDate(invoice.due_date) }}
                                </p>
                            </div>
                            <span
                                :class="['px-2 py-1 text-xs rounded-full font-medium', getInvoiceStatusBadge(invoice.status)]"
                            >
                                {{ getInvoiceStatusText(invoice.status) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center mt-2 pt-2 border-t">
                            <div>
                                <p class="text-xs text-gray-500">Total Tagihan</p>
                                <p class="font-semibold text-gray-800">{{ formatCurrency(invoice.total_amount) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500">Sisa</p>
                                <p class="font-semibold" :class="invoice.remaining_amount > 0 ? 'text-red-600' : 'text-green-600'">
                                    {{ formatCurrency(invoice.remaining_amount) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-8 bg-white rounded-lg">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="mt-2 text-gray-500">Belum ada tagihan</p>
                </div>
            </div>

            <!-- Payments Tab -->
            <div v-if="activeTab === 'payments'" class="px-4 mt-4 space-y-3">
                <h3 class="font-semibold text-gray-800">Histori Pembayaran</h3>

                <div v-if="customer.payments?.length > 0" class="space-y-2">
                    <div
                        v-for="payment in customer.payments"
                        :key="payment.id"
                        class="bg-white rounded-lg shadow-sm p-4"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-green-600">{{ formatCurrency(payment.amount) }}</p>
                                <p class="text-sm text-gray-500">{{ formatDateTime(payment.created_at) }}</p>
                            </div>
                            <span
                                :class="[
                                    'px-2 py-1 text-xs rounded-full font-medium',
                                    payment.payment_method === 'cash' ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600'
                                ]"
                            >
                                {{ payment.payment_method === 'cash' ? 'Tunai' : 'Transfer' }}
                            </span>
                        </div>
                        <div v-if="payment.notes" class="mt-2 pt-2 border-t">
                            <p class="text-xs text-gray-500">Catatan</p>
                            <p class="text-sm text-gray-700">{{ payment.notes }}</p>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-8 bg-white rounded-lg">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                    </svg>
                    <p class="mt-2 text-gray-500">Belum ada pembayaran</p>
                </div>
            </div>

            <!-- Payment Modal -->
            <div
                v-if="showPaymentModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex items-end justify-center z-50"
                @click.self="showPaymentModal = false"
            >
                <div class="bg-white rounded-t-2xl w-full max-w-lg p-6 animate-slide-up">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Proses Pembayaran</h3>
                        <button @click="showPaymentModal = false" class="text-gray-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <!-- Customer Info -->
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="font-semibold">{{ customer.name }}</p>
                            <p class="text-sm text-gray-600">{{ customer.customer_id }}</p>
                            <p class="text-red-600 font-semibold mt-1">
                                Hutang: {{ formatCurrency(customer.total_debt) }}
                            </p>
                        </div>

                        <!-- Payment Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Metode Pembayaran</label>
                            <div class="flex gap-3">
                                <button
                                    @click="paymentType = 'cash'"
                                    :class="[
                                        'flex-1 py-2 rounded-lg border-2 text-sm font-medium transition-colors',
                                        paymentType === 'cash' ? 'border-blue-500 bg-blue-50 text-blue-600' : 'border-gray-200'
                                    ]"
                                >
                                    Tunai
                                </button>
                                <button
                                    @click="paymentType = 'transfer'"
                                    :class="[
                                        'flex-1 py-2 rounded-lg border-2 text-sm font-medium transition-colors',
                                        paymentType === 'transfer' ? 'border-blue-500 bg-blue-50 text-blue-600' : 'border-gray-200'
                                    ]"
                                >
                                    Transfer
                                </button>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah Bayar</label>
                            <input
                                v-model="paymentAmount"
                                type="number"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500"
                            >
                        </div>

                        <!-- Transfer Proof -->
                        <div v-if="paymentType === 'transfer'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Bukti Transfer</label>
                            <input
                                type="file"
                                accept="image/*"
                                @change="handleFileUpload"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300"
                            >
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
                            <textarea
                                v-model="paymentNotes"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300"
                                rows="2"
                            ></textarea>
                        </div>

                        <!-- Submit -->
                        <button
                            @click="processPayment"
                            class="w-full py-3 bg-blue-600 text-white rounded-lg font-semibold"
                        >
                            Proses Pembayaran
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </CollectorLayout>
</template>

<style scoped>
.animate-slide-up {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
</style>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import CollectorLayout from '@/Layouts/CollectorLayout.vue'

const props = defineProps({
    customers: Object,
    filters: Object,
    stats: Object,
})

const page = usePage()

// Watch for WhatsApp URL in flash
watch(() => page.props.flash?.whatsapp_url, (url) => {
    if (url) {
        window.open(url, '_blank')
    }
}, { immediate: true })

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const paymentFilter = ref(props.filters.payment_status || '')

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

// Apply filters
const applyFilter = () => {
    router.get(route('collector.customers'), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        payment_status: paymentFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Watch filter changes
watch([statusFilter, paymentFilter], applyFilter)

// Payment modal
const showPaymentModal = ref(false)
const selectedCustomer = ref(null)
const paymentType = ref('cash')
const paymentAmount = ref('')
const paymentNotes = ref('')
const transferProof = ref(null)

const openPaymentModal = (customer) => {
    selectedCustomer.value = customer
    paymentAmount.value = customer.total_debt
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
        ? route('collector.payment.cash', selectedCustomer.value.id)
        : route('collector.payment.transfer', selectedCustomer.value.id)

    router.post(url, formData, {
        onSuccess: () => {
            showPaymentModal.value = false
            selectedCustomer.value = null
        },
    })
}

const handleFileUpload = (event) => {
    transferProof.value = event.target.files[0]
}

// WhatsApp - generate URL directly on client
const openWhatsApp = (customer) => {
    let phone = customer.phone?.replace(/[^0-9]/g, '') || ''

    // Convert 08 to 628
    if (phone.startsWith('0')) {
        phone = '62' + phone.substring(1)
    }

    const message = `Yth. Bapak/Ibu ${customer.name},\n\nKami mengingatkan bahwa tagihan internet Anda sebesar ${formatCurrency(customer.total_debt)} belum terbayar.\n\nMohon segera melakukan pembayaran untuk menghindari pemutusan layanan.\n\nTerima kasih.`

    const url = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`
    window.open(url, '_blank')
}

// Call customer
const callCustomer = (customer) => {
    window.location.href = `tel:${customer.phone}`
}

// Navigate to map
const openMaps = (customer) => {
    if (customer.latitude && customer.longitude) {
        window.open(`https://maps.google.com/?q=${customer.latitude},${customer.longitude}`, '_blank')
    } else if (customer.address) {
        window.open(`https://maps.google.com/?q=${encodeURIComponent(customer.address)}`, '_blank')
    }
}
</script>

<template>
    <Head title="Daftar Pelanggan" />

    <CollectorLayout>
        <div class="min-h-screen bg-gray-100 pb-20">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white px-4 py-6">
                <div class="flex items-center gap-3">
                    <Link :href="route('collector.dashboard')" class="p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-xl font-bold">Daftar Pelanggan</h1>
                        <p class="text-blue-100 text-sm">{{ stats?.total || 0 }} pelanggan</p>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="px-4 -mt-4">
                <div class="grid grid-cols-4 gap-2">
                    <div class="bg-white rounded-lg shadow-sm p-3 text-center">
                        <p class="text-lg font-bold text-blue-600">{{ stats?.total || 0 }}</p>
                        <p class="text-xs text-gray-500">Total</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-3 text-center">
                        <p class="text-lg font-bold text-green-600">{{ stats?.paid || 0 }}</p>
                        <p class="text-xs text-gray-500">Lunas</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-3 text-center">
                        <p class="text-lg font-bold text-yellow-600">{{ stats?.unpaid || 0 }}</p>
                        <p class="text-xs text-gray-500">Belum</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-3 text-center">
                        <p class="text-lg font-bold text-red-600">{{ stats?.isolated || 0 }}</p>
                        <p class="text-xs text-gray-500">Isolir</p>
                    </div>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="px-4 mt-4 space-y-2">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari nama, ID, alamat..."
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500"
                    @keyup.enter="applyFilter"
                >
                <div class="flex gap-2">
                    <select
                        v-model="statusFilter"
                        class="flex-1 px-3 py-2 rounded-lg border border-gray-300 text-sm"
                    >
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="isolated">Isolir</option>
                        <option value="suspended">Suspend</option>
                    </select>
                    <select
                        v-model="paymentFilter"
                        class="flex-1 px-3 py-2 rounded-lg border border-gray-300 text-sm"
                    >
                        <option value="">Semua Tagihan</option>
                        <option value="paid">Lunas</option>
                        <option value="unpaid">Belum Bayar</option>
                        <option value="overdue">Jatuh Tempo</option>
                    </select>
                </div>
            </div>

            <!-- Customer List -->
            <div class="px-4 mt-4 space-y-3">
                <div
                    v-for="customer in customers.data"
                    :key="customer.id"
                    class="bg-white rounded-xl shadow-sm overflow-hidden"
                >
                    <Link :href="route('collector.customer.detail', customer.id)" class="block p-4">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-gray-800">{{ customer.name }}</h3>
                                    <span
                                        v-if="customer.status === 'isolated'"
                                        class="px-2 py-0.5 bg-red-100 text-red-600 text-xs rounded-full"
                                    >
                                        Isolir
                                    </span>
                                    <span
                                        v-else-if="customer.status === 'active'"
                                        class="px-2 py-0.5 bg-green-100 text-green-600 text-xs rounded-full"
                                    >
                                        Aktif
                                    </span>
                                </div>
                                <p class="text-gray-500 text-xs mt-1">{{ customer.customer_id }}</p>
                                <p class="text-gray-600 text-sm mt-1">{{ customer.address }}</p>
                                <div class="flex items-center gap-4 mt-2">
                                    <p class="text-sm text-gray-500">
                                        <span class="font-medium">{{ customer.package?.name }}</span>
                                    </p>
                                    <p class="text-sm text-gray-500">{{ customer.phone }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p
                                    class="font-bold"
                                    :class="customer.total_debt > 0 ? 'text-red-600' : 'text-green-600'"
                                >
                                    {{ formatCurrency(customer.total_debt) }}
                                </p>
                                <p v-if="customer.unpaid_months > 0" class="text-xs text-gray-500">
                                    {{ customer.unpaid_months }} bulan
                                </p>
                            </div>
                        </div>
                    </Link>

                    <!-- Action Buttons -->
                    <div class="px-4 pb-4">
                        <div class="flex gap-2">
                            <button
                                @click="callCustomer(customer)"
                                class="flex-1 flex items-center justify-center gap-1 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                Telepon
                            </button>
                            <button
                                @click="openWhatsApp(customer)"
                                class="flex-1 flex items-center justify-center gap-1 py-2 bg-green-500 text-white rounded-lg text-sm"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                </svg>
                                WA
                            </button>
                            <button
                                @click="openMaps(customer)"
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
                                @click="openPaymentModal(customer)"
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

                <!-- Empty State -->
                <div v-if="!customers.data?.length" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <p class="mt-4 text-gray-500">Tidak ada pelanggan ditemukan</p>
                </div>

                <!-- Pagination -->
                <div v-if="customers.data?.length" class="flex justify-center gap-2 py-4">
                    <Link
                        v-for="link in customers.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-2 text-sm rounded-lg',
                            link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700',
                            !link.url ? 'opacity-50' : ''
                        ]"
                        v-html="link.label"
                    />
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

                    <div v-if="selectedCustomer" class="space-y-4">
                        <!-- Customer Info -->
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="font-semibold">{{ selectedCustomer.name }}</p>
                            <p class="text-sm text-gray-600">{{ selectedCustomer.customer_id }}</p>
                            <p class="text-red-600 font-semibold mt-1">
                                Hutang: {{ formatCurrency(selectedCustomer.total_debt) }}
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

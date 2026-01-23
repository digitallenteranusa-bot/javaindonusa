<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import CollectorLayout from '@/Layouts/CollectorLayout.vue'

const props = defineProps({
    stats: Object,
    overdueCustomers: Object,
    dailySummary: Object,
    filters: Object,
})

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const showPaymentModal = ref(false)
const selectedCustomer = ref(null)
const paymentType = ref('cash')
const paymentAmount = ref('')
const paymentNotes = ref('')
const transferProof = ref(null)

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value)
}

// Statistik cards
const statCards = computed(() => [
    {
        label: 'Total Pelanggan',
        value: props.stats?.customers?.total ?? 0,
        color: 'bg-blue-500',
        icon: 'users',
    },
    {
        label: 'Sudah Bayar',
        value: props.stats?.customers?.paid_this_month ?? 0,
        color: 'bg-green-500',
        icon: 'check-circle',
    },
    {
        label: 'Belum Bayar',
        value: props.stats?.customers?.unpaid_this_month ?? 0,
        color: 'bg-yellow-500',
        icon: 'clock',
    },
    {
        label: 'Terisolir',
        value: props.stats?.customers?.isolated ?? 0,
        color: 'bg-red-500',
        icon: 'x-circle',
    },
])

// Revenue cards
const revenueCards = computed(() => [
    {
        label: 'Total Tagihan',
        value: formatCurrency(props.stats?.revenue?.total_billable ?? 0),
        color: 'text-blue-600',
    },
    {
        label: 'Sudah Ditagih',
        value: formatCurrency(props.stats?.revenue?.collected ?? 0),
        color: 'text-green-600',
    },
    {
        label: 'Total Hutang',
        value: formatCurrency(props.stats?.revenue?.total_debt ?? 0),
        color: 'text-red-600',
    },
])

// Filter customers
const applyFilter = () => {
    router.get(route('collector.dashboard'), {
        search: search.value,
        status: statusFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Open payment modal
const openPaymentModal = (customer) => {
    selectedCustomer.value = customer
    paymentAmount.value = customer.total_debt
    paymentNotes.value = ''
    transferProof.value = null
    showPaymentModal.value = true
}

// Process payment
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

// Open WhatsApp
const openWhatsApp = (customer) => {
    router.post(route('collector.whatsapp', customer.id), {}, {
        onSuccess: (response) => {
            if (response.props.whatsapp_url) {
                window.open(response.props.whatsapp_url, '_blank')
            }
        },
    })
}

// Handle file upload
const handleFileUpload = (event) => {
    transferProof.value = event.target.files[0]
}
</script>

<template>
    <Head title="Dashboard Penagih" />

    <CollectorLayout>
        <div class="min-h-screen bg-gray-100 pb-20">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white px-4 py-6">
                <h1 class="text-xl font-bold">Dashboard Penagih</h1>
                <p class="text-blue-100 text-sm mt-1">{{ new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
            </div>

            <!-- Statistik Pelanggan -->
            <div class="px-4 -mt-4">
                <div class="grid grid-cols-2 gap-3">
                    <div
                        v-for="stat in statCards"
                        :key="stat.label"
                        class="bg-white rounded-xl shadow-sm p-4"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-xs">{{ stat.label }}</p>
                                <p class="text-2xl font-bold mt-1">{{ stat.value }}</p>
                            </div>
                            <div :class="[stat.color, 'w-10 h-10 rounded-full flex items-center justify-center']">
                                <span class="text-white text-lg">{{ stat.value }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistik Pendapatan -->
            <div class="px-4 mt-4">
                <h2 class="text-lg font-semibold mb-3">Ringkasan Tagihan</h2>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="space-y-3">
                        <div v-for="rev in revenueCards" :key="rev.label" class="flex justify-between items-center">
                            <span class="text-gray-600 text-sm">{{ rev.label }}</span>
                            <span :class="[rev.color, 'font-semibold']">{{ rev.value }}</span>
                        </div>
                        <hr class="my-2">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 text-sm">Tingkat Penagihan</span>
                            <span class="font-semibold text-blue-600">{{ stats.revenue.collection_rate }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Setoran Hari Ini -->
            <div class="px-4 mt-4">
                <h2 class="text-lg font-semibold mb-3">Setoran Hari Ini</h2>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div>
                            <p class="text-gray-500 text-xs">Tagihan Masuk</p>
                            <p class="text-green-600 font-bold text-sm">
                                {{ formatCurrency(dailySummary.settlement.cash_collection) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">Belanja</p>
                            <p class="text-red-600 font-bold text-sm">
                                {{ formatCurrency(dailySummary.settlement.approved_expense) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">Harus Setor</p>
                            <p class="text-blue-600 font-bold text-sm">
                                {{ formatCurrency(dailySummary.settlement.must_settle) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="px-4 mt-4">
                <div class="flex gap-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari nama/alamat..."
                        class="flex-1 px-4 py-2 rounded-lg border border-gray-300 text-sm focus:ring-2 focus:ring-blue-500"
                        @keyup.enter="applyFilter"
                    >
                    <select
                        v-model="statusFilter"
                        class="px-3 py-2 rounded-lg border border-gray-300 text-sm"
                        @change="applyFilter"
                    >
                        <option value="">Semua</option>
                        <option value="active">Aktif</option>
                        <option value="isolated">Isolir</option>
                    </select>
                </div>
            </div>

            <!-- Daftar Pelanggan Menunggak -->
            <div class="px-4 mt-4">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-lg font-semibold">Pelanggan Menunggak</h2>
                    <Link :href="route('collector.customers')" class="text-blue-600 text-sm">
                        Lihat Semua
                    </Link>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="customer in overdueCustomers.data"
                        :key="customer.id"
                        class="bg-white rounded-xl shadow-sm p-4"
                    >
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
                                </div>
                                <p class="text-gray-500 text-xs mt-1">{{ customer.customer_id }}</p>
                                <p class="text-gray-600 text-sm mt-1">{{ customer.address }}</p>
                                <p class="text-red-600 font-semibold mt-2">
                                    Hutang: {{ formatCurrency(customer.total_debt) }}
                                </p>
                                <p class="text-gray-500 text-xs">
                                    {{ customer.invoices?.length || 0 }} bulan belum bayar
                                </p>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-2 mt-4">
                            <button
                                @click="openWhatsApp(customer)"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-medium hover:bg-green-600"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                </svg>
                                WhatsApp
                            </button>
                            <button
                                @click="openPaymentModal(customer)"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600"
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
                <div v-if="!overdueCustomers.data?.length" class="text-center py-12">
                    <p class="text-gray-500">Tidak ada pelanggan menunggak</p>
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
                                placeholder="Masukkan nominal"
                            >
                        </div>

                        <!-- Transfer Proof (if transfer) -->
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">Catatan (opsional)</label>
                            <textarea
                                v-model="paymentNotes"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500"
                                rows="2"
                                placeholder="Catatan pembayaran..."
                            ></textarea>
                        </div>

                        <!-- Submit Button -->
                        <button
                            @click="processPayment"
                            class="w-full py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700"
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
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}
</style>

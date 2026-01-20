<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    settlement: Object,
    payments: Array,
    expenses: Array,
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
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    })
}

const formatDateTime = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('id-ID')
}

const formatTime = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    })
}

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
        pending: 'Menunggu Verifikasi',
        verified: 'Terverifikasi',
        settled: 'Selesai',
        rejected: 'Ditolak',
        discrepancy: 'Ada Selisih',
    }
    return labels[status] || status
}

// Verify modal
const showVerifyModal = ref(false)
const actualAmount = ref(props.settlement.expected_amount)
const verifyNotes = ref('')

const verifySettlement = () => {
    router.post(`/admin/settlements/${props.settlement.id}/verify`, {
        actual_amount: actualAmount.value,
        notes: verifyNotes.value,
    }, {
        onSuccess: () => {
            showVerifyModal.value = false
        },
    })
}

// Reject modal
const showRejectModal = ref(false)
const rejectReason = ref('')

const rejectSettlement = () => {
    if (!rejectReason.value) return
    router.post(`/admin/settlements/${props.settlement.id}/reject`, {
        reason: rejectReason.value,
    }, {
        onSuccess: () => {
            showRejectModal.value = false
        },
    })
}

// Calculate difference preview
const differencePreview = () => {
    const diff = actualAmount.value - props.settlement.expected_amount
    if (diff === 0) return { text: 'Sesuai', class: 'text-green-600' }
    if (diff > 0) return { text: `Lebih ${formatCurrency(diff)}`, class: 'text-green-600' }
    return { text: `Kurang ${formatCurrency(Math.abs(diff))}`, class: 'text-red-600' }
}
</script>

<template>
    <Head :title="`Setoran - ${settlement.settlement_number || formatDate(settlement.settlement_date)}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/settlements" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Detail Setoran</h1>
                        <p class="text-gray-500">{{ settlement.collector?.name }} - {{ formatDate(settlement.settlement_date) }}</p>
                    </div>
                </div>
                <span :class="['px-3 py-1 text-sm rounded-full', statusClass(settlement.status)]">
                    {{ statusLabel(settlement.status) }}
                </span>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Summary Card -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Ringkasan Setoran</h2>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-green-50 rounded-lg p-4 text-center">
                            <p class="text-sm text-green-600">Total Tagihan</p>
                            <p class="text-xl font-bold text-green-700">{{ formatCurrency(settlement.total_collection) }}</p>
                        </div>
                        <div class="bg-red-50 rounded-lg p-4 text-center">
                            <p class="text-sm text-red-600">Pengeluaran</p>
                            <p class="text-xl font-bold text-red-700">{{ formatCurrency(settlement.total_expense || settlement.approved_expense) }}</p>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-4 text-center">
                            <p class="text-sm text-blue-600">Setoran</p>
                            <p class="text-xl font-bold text-blue-700">{{ formatCurrency(settlement.expected_amount) }}</p>
                        </div>
                        <div :class="[
                            'rounded-lg p-4 text-center',
                            settlement.status === 'discrepancy' ? 'bg-orange-50' : 'bg-gray-50'
                        ]">
                            <p class="text-sm text-gray-600">Aktual</p>
                            <p class="text-xl font-bold" :class="settlement.status === 'discrepancy' ? 'text-orange-700' : 'text-gray-700'">
                                {{ settlement.actual_amount ? formatCurrency(settlement.actual_amount) : '-' }}
                            </p>
                        </div>
                    </div>

                    <!-- Calculation breakdown -->
                    <div class="border-t pt-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Total Tagihan Cash</span>
                            <span class="text-green-600">+ {{ formatCurrency(settlement.cash_collection) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Total Tagihan Transfer</span>
                            <span class="text-blue-600">{{ formatCurrency(settlement.transfer_collection) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Pengeluaran Disetujui</span>
                            <span class="text-red-600">- {{ formatCurrency(settlement.approved_expense) }}</span>
                        </div>
                        <div class="flex justify-between font-semibold border-t pt-2">
                            <span>Yang Harus Disetor</span>
                            <span>{{ formatCurrency(settlement.expected_amount) }}</span>
                        </div>
                        <div v-if="settlement.difference !== null && settlement.difference !== 0" class="flex justify-between">
                            <span class="text-gray-500">Selisih</span>
                            <span :class="settlement.difference > 0 ? 'text-green-600' : 'text-red-600'">
                                {{ settlement.difference > 0 ? '+' : '' }}{{ formatCurrency(settlement.difference) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Payments List -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Pembayaran ({{ payments.length }})</h2>

                    <div v-if="payments.length" class="space-y-3">
                        <div
                            v-for="payment in payments"
                            :key="payment.id"
                            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                        >
                            <div>
                                <p class="font-medium text-sm">{{ payment.customer?.name }}</p>
                                <p class="text-xs text-gray-500">{{ payment.customer?.customer_id }} - {{ formatTime(payment.created_at) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-green-600">{{ formatCurrency(payment.amount) }}</p>
                                <p class="text-xs text-gray-500 capitalize">{{ payment.payment_method }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-gray-500 text-center py-4">Tidak ada pembayaran</p>
                </div>

                <!-- Expenses List -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Pengeluaran ({{ expenses.length }})</h2>

                    <div v-if="expenses.length" class="space-y-3">
                        <div
                            v-for="expense in expenses"
                            :key="expense.id"
                            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                        >
                            <div>
                                <p class="font-medium text-sm capitalize">{{ expense.category }}</p>
                                <p class="text-xs text-gray-500">{{ expense.description }}</p>
                            </div>
                            <p class="font-semibold text-red-600">{{ formatCurrency(expense.amount) }}</p>
                        </div>
                    </div>
                    <p v-else class="text-gray-500 text-center py-4">Tidak ada pengeluaran</p>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Actions -->
                <div v-if="settlement.status === 'pending'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Verifikasi</h2>

                    <div class="space-y-2">
                        <button
                            @click="showVerifyModal = true"
                            class="w-full py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Verifikasi Setoran
                        </button>
                        <button
                            @click="showRejectModal = true"
                            class="w-full py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200"
                        >
                            Tolak
                        </button>
                    </div>
                </div>

                <!-- Info -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Informasi</h2>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Penagih</span>
                            <span class="font-medium">{{ settlement.collector?.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Tanggal</span>
                            <span>{{ formatDate(settlement.settlement_date) }}</span>
                        </div>
                        <div v-if="settlement.verified_at" class="flex justify-between">
                            <span class="text-gray-500">Diverifikasi</span>
                            <span>{{ formatDateTime(settlement.verified_at) }}</span>
                        </div>
                        <div v-if="settlement.received_by" class="flex justify-between">
                            <span class="text-gray-500">Diterima oleh</span>
                            <span>{{ settlement.received_by?.name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div v-if="settlement.notes" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Catatan</h2>
                    <p class="text-sm text-gray-600">{{ settlement.notes }}</p>
                </div>
            </div>
        </div>

        <!-- Verify Modal -->
        <div
            v-if="showVerifyModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showVerifyModal = false"
        >
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Verifikasi Setoran</h3>

                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-500">Yang harus disetor</span>
                            <span class="font-semibold">{{ formatCurrency(settlement.expected_amount) }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Aktual Diterima *</label>
                        <input
                            v-model.number="actualAmount"
                            type="number"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Masukkan jumlah aktual"
                        >
                        <p v-if="actualAmount !== settlement.expected_amount" :class="['text-sm mt-1', differencePreview().class]">
                            {{ differencePreview().text }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan (opsional)</label>
                        <textarea
                            v-model="verifyNotes"
                            rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Catatan verifikasi..."
                        ></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button
                            @click="showVerifyModal = false"
                            class="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                        >
                            Batal
                        </button>
                        <button
                            @click="verifySettlement"
                            class="flex-1 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                        >
                            Verifikasi
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div
            v-if="showRejectModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showRejectModal = false"
        >
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Tolak Setoran</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Penolakan *</label>
                        <textarea
                            v-model="rejectReason"
                            rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Masukkan alasan penolakan..."
                        ></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button
                            @click="showRejectModal = false"
                            class="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                        >
                            Batal
                        </button>
                        <button
                            @click="rejectSettlement"
                            :disabled="!rejectReason"
                            class="flex-1 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50"
                        >
                            Tolak
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

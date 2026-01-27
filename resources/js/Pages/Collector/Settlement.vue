<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import CollectorLayout from '@/Layouts/CollectorLayout.vue'

const props = defineProps({
    settlements: Object,
    dailySummary: Object,
    pendingSettlement: Object,
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
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}

// Format datetime
const formatDateTime = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    })
}

// Get today's date in YYYY-MM-DD format
const getTodayDate = () => {
    const today = new Date()
    return today.toISOString().split('T')[0]
}

// Settlement modal
const showSettlementModal = ref(false)
const form = useForm({
    period_start: getTodayDate(),
    period_end: getTodayDate(),
    actual_amount: props.pendingSettlement?.must_settle || 0,
    notes: '',
})

const openSettlementModal = () => {
    form.period_start = getTodayDate()
    form.period_end = getTodayDate()
    form.actual_amount = props.pendingSettlement?.must_settle || 0
    form.notes = ''
    showSettlementModal.value = true
}

const submitSettlement = () => {
    form.post(route('collector.settlement.store'), {
        onSuccess: () => {
            showSettlementModal.value = false
            form.reset()
        },
    })
}

// Status badge
const getStatusBadge = (status) => {
    const badges = {
        pending: { class: 'bg-yellow-100 text-yellow-600', text: 'Menunggu' },
        verified: { class: 'bg-green-100 text-green-600', text: 'Diverifikasi' },
        rejected: { class: 'bg-red-100 text-red-600', text: 'Ditolak' },
    }
    return badges[status] || { class: 'bg-gray-100 text-gray-600', text: status }
}

// Check if can settle
const canSettle = computed(() => {
    return (props.pendingSettlement?.must_settle || 0) > 0
})
</script>

<template>
    <Head title="Setoran" />

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
                        <h1 class="text-xl font-bold">Setoran</h1>
                        <p class="text-blue-100 text-sm">Setor tagihan ke kantor</p>
                    </div>
                </div>
            </div>

            <!-- Pending Settlement Card -->
            <div class="px-4 -mt-4">
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <h3 class="text-sm font-medium text-gray-500 mb-3">Ringkasan Hari Ini</h3>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Tagihan Cash Masuk</span>
                            <span class="font-semibold text-green-600">
                                {{ formatCurrency(pendingSettlement?.cash_collection || 0) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Belanja Disetujui</span>
                            <span class="font-semibold text-red-600">
                                -{{ formatCurrency(pendingSettlement?.approved_expense || 0) }}
                            </span>
                        </div>
                        <hr>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-800">Harus Disetor</span>
                            <span class="font-bold text-xl text-blue-600">
                                {{ formatCurrency(pendingSettlement?.must_settle || 0) }}
                            </span>
                        </div>
                    </div>

                    <!-- Settle Button -->
                    <button
                        v-if="canSettle"
                        @click="openSettlementModal"
                        class="w-full mt-4 py-3 bg-blue-600 text-white rounded-lg font-semibold flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                        </svg>
                        Setor Sekarang
                    </button>

                    <div v-else class="mt-4 p-3 bg-green-50 rounded-lg text-center">
                        <svg class="w-8 h-8 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm text-green-600 mt-2">Tidak ada yang harus disetor</p>
                    </div>
                </div>
            </div>

            <!-- Settlement History -->
            <div class="px-4 mt-4">
                <h3 class="text-sm font-medium text-gray-500 mb-3">Riwayat Setoran</h3>

                <div class="space-y-3">
                    <div
                        v-for="settlement in settlements.data"
                        :key="settlement.id"
                        class="bg-white rounded-xl shadow-sm p-4"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-gray-800">
                                    {{ formatCurrency(settlement.amount) }}
                                </p>
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ formatDate(settlement.settlement_date) }}
                                </p>
                                <p v-if="settlement.notes" class="text-sm text-gray-600 mt-1">
                                    {{ settlement.notes }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="getStatusBadge(settlement.status).class"
                                >
                                    {{ getStatusBadge(settlement.status).text }}
                                </span>
                                <p v-if="settlement.verified_by" class="text-xs text-gray-400 mt-2">
                                    {{ settlement.verified_by.name }}
                                </p>
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <span class="text-gray-500">Tagihan:</span>
                                <span class="text-green-600 ml-1">{{ formatCurrency(settlement.cash_collected) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Belanja:</span>
                                <span class="text-red-600 ml-1">{{ formatCurrency(settlement.expense_used) }}</span>
                            </div>
                        </div>

                        <!-- Rejection reason -->
                        <div v-if="settlement.status === 'rejected' && settlement.rejection_reason" class="mt-2 p-2 bg-red-50 rounded text-sm text-red-600">
                            Alasan: {{ settlement.rejection_reason }}
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!settlements.data?.length" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="mt-4 text-gray-500">Belum ada riwayat setoran</p>
                </div>

                <!-- Pagination -->
                <div v-if="settlements.data?.length" class="flex justify-center gap-2 py-4">
                    <Link
                        v-for="link in settlements.links"
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

            <!-- Settlement Modal -->
            <div
                v-if="showSettlementModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex items-end justify-center z-50"
                @click.self="showSettlementModal = false"
            >
                <div class="bg-white rounded-t-2xl w-full max-w-lg p-6 animate-slide-up">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Setor Tagihan</h3>
                        <button @click="showSettlementModal = false" class="text-gray-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitSettlement" class="space-y-4">
                        <!-- Summary -->
                        <div class="bg-blue-50 rounded-lg p-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-blue-700">Tagihan Cash</span>
                                <span class="font-medium">{{ formatCurrency(pendingSettlement?.cash_collection) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-blue-700">Belanja</span>
                                <span class="font-medium">-{{ formatCurrency(pendingSettlement?.approved_expense) }}</span>
                            </div>
                            <hr class="border-blue-200">
                            <div class="flex justify-between">
                                <span class="font-medium text-blue-800">Total Setor</span>
                                <span class="font-bold text-lg text-blue-800">{{ formatCurrency(pendingSettlement?.must_settle) }}</span>
                            </div>
                        </div>

                        <!-- Amount (readonly) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah Setoran</label>
                            <input
                                v-model="form.actual_amount"
                                type="number"
                                readonly
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-gray-50 text-xl font-semibold text-center"
                            >
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Catatan (Opsional)</label>
                            <textarea
                                v-model="form.notes"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500"
                                rows="2"
                                placeholder="Catatan untuk admin..."
                            ></textarea>
                        </div>

                        <!-- Info -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                            <p class="font-medium">Perhatian:</p>
                            <p>Setoran akan diverifikasi oleh admin. Pastikan jumlah uang yang disetor sudah sesuai.</p>
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3 bg-blue-600 text-white rounded-lg font-semibold disabled:opacity-50"
                        >
                            {{ form.processing ? 'Memproses...' : 'Konfirmasi Setoran' }}
                        </button>
                    </form>
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

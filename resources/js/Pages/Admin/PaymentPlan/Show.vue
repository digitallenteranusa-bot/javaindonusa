<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    plan: Object,
})

const formatCurrency = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v || 0)
const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) : '-'

const statusClass = (s) => ({
    pending: 'bg-yellow-100 text-yellow-700',
    paid: 'bg-green-100 text-green-700',
    partial: 'bg-blue-100 text-blue-700',
    overdue: 'bg-red-100 text-red-700',
}[s] || 'bg-gray-100 text-gray-700')

const statusLabel = (s) => ({ pending: 'Belum Bayar', paid: 'Lunas', partial: 'Sebagian', overdue: 'Terlambat' }[s] || s)

const showCancelModal = ref(false)
const cancelReason = ref('')

const submitCancel = () => {
    router.post(`/admin/payment-plans/${props.plan.id}/cancel`, { reason: cancelReason.value }, {
        onSuccess: () => { showCancelModal.value = false },
    })
}

const progress = Math.min(100, Math.round((props.plan.paid_amount / props.plan.total_debt_amount) * 100))
</script>

<template>
    <Head title="Detail Cicilan" />
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/payment-plans" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Cicilan {{ plan.customer?.name }}</h1>
                    <p class="text-gray-500">{{ plan.installment_count }}x cicilan</p>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Progress -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Progress Pembayaran</h2>
                    <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                        <div class="bg-blue-600 h-4 rounded-full transition-all" :style="{ width: `${progress}%` }"></div>
                    </div>
                    <div class="flex justify-between text-sm text-gray-500">
                        <span>{{ formatCurrency(plan.paid_amount) }} dibayar</span>
                        <span>{{ progress }}%</span>
                        <span>{{ formatCurrency(plan.remaining_amount) }} sisa</span>
                    </div>
                </div>

                <!-- Installments -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Jadwal Cicilan</h2>
                    <div class="space-y-3">
                        <div v-for="inst in plan.installments" :key="inst.id" class="flex items-center justify-between p-4 border rounded-lg" :class="inst.status === 'paid' ? 'bg-green-50 border-green-200' : inst.status === 'overdue' ? 'bg-red-50 border-red-200' : 'bg-white'">
                            <div>
                                <p class="font-medium">Cicilan ke-{{ inst.installment_number }}</p>
                                <p class="text-sm text-gray-500">Jatuh tempo: {{ formatDate(inst.due_date) }}</p>
                                <p v-if="inst.paid_at" class="text-xs text-green-600">Dibayar: {{ formatDate(inst.paid_at) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">{{ formatCurrency(inst.amount) }}</p>
                                <span :class="['px-2 py-1 text-xs rounded-full', statusClass(inst.status)]">{{ statusLabel(inst.status) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Info Cicilan</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Total Hutang</span><span class="font-semibold">{{ formatCurrency(plan.total_debt_amount) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Jumlah Cicilan</span><span>{{ plan.installment_count }}x</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Per Bulan</span><span class="font-semibold text-blue-600">{{ formatCurrency(plan.installment_amount) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Mulai</span><span>{{ formatDate(plan.start_date) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Selesai</span><span>{{ formatDate(plan.end_date) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Dibuat oleh</span><span>{{ plan.created_by?.name || '-' }}</span></div>
                    </div>
                </div>

                <div v-if="plan.status === 'active'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Aksi</h2>
                    <button @click="showCancelModal = true" class="w-full py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-sm">
                        Batalkan Cicilan
                    </button>
                </div>

                <div v-if="plan.notes" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Catatan</h2>
                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ plan.notes }}</p>
                </div>
            </div>
        </div>

        <!-- Cancel Modal -->
        <div v-if="showCancelModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showCancelModal = false">
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Batalkan Cicilan</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan *</label>
                        <textarea v-model="cancelReason" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showCancelModal = false" class="flex-1 py-2 border text-gray-700 rounded-lg hover:bg-gray-50">Batal</button>
                        <button @click="submitCancel" :disabled="!cancelReason" class="flex-1 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50">Batalkan</button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

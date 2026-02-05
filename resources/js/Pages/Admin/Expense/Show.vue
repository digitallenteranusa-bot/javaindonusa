<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    expense: Object,
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

// Status badge
const statusClass = (status) => {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-700',
        approved: 'bg-green-100 text-green-700',
        rejected: 'bg-red-100 text-red-700',
    }
    return classes[status] || 'bg-gray-100 text-gray-700'
}

const statusLabel = (status) => {
    const labels = {
        pending: 'Menunggu Persetujuan',
        approved: 'Disetujui',
        rejected: 'Ditolak',
    }
    return labels[status] || status
}

// Category label
const categoryLabel = (category) => {
    const labels = {
        transport: 'Transportasi',
        meal: 'Makan',
        fuel: 'BBM',
        parking: 'Parkir',
        toll: 'Tol',
        maintenance: 'Perawatan',
        other: 'Lainnya',
    }
    return labels[category] || category
}

// Approve
const showApproveModal = ref(false)
const approvalNotes = ref('')

const approveExpense = () => {
    router.post(`/admin/expenses/${props.expense.id}/approve`, {
        notes: approvalNotes.value,
    }, {
        onSuccess: () => {
            showApproveModal.value = false
        },
    })
}

// Reject
const showRejectModal = ref(false)
const rejectionReason = ref('')

const rejectExpense = () => {
    if (!rejectionReason.value) return
    router.post(`/admin/expenses/${props.expense.id}/reject`, {
        reason: rejectionReason.value,
    }, {
        onSuccess: () => {
            showRejectModal.value = false
        },
    })
}

// Image preview modal
const showImagePreview = ref(false)

const openImagePreview = () => {
    showImagePreview.value = true
}

const closeImagePreview = () => {
    showImagePreview.value = false
}
</script>

<template>
    <Head :title="`Pengeluaran - ${expense.expense_number || expense.id}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/expenses" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Detail Pengeluaran</h1>
                        <p class="text-gray-500">{{ formatDate(expense.expense_date) }}</p>
                    </div>
                </div>
                <span :class="['px-3 py-1 text-sm rounded-full', statusClass(expense.status)]">
                    {{ statusLabel(expense.status) }}
                </span>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Expense Detail -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Detail Pengeluaran</h2>

                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-3 border-b">
                            <span class="text-gray-500">Penagih</span>
                            <span class="font-medium">{{ expense.user?.name }}</span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b">
                            <span class="text-gray-500">Tanggal</span>
                            <span>{{ formatDate(expense.expense_date) }}</span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b">
                            <span class="text-gray-500">Kategori</span>
                            <span class="capitalize">{{ categoryLabel(expense.category) }}</span>
                        </div>
                        <div class="py-3 border-b">
                            <span class="text-gray-500 block mb-2">Deskripsi</span>
                            <p class="text-gray-900">{{ expense.description }}</p>
                        </div>
                        <div class="flex justify-between items-center py-3">
                            <span class="text-gray-500 text-lg">Jumlah</span>
                            <span class="text-2xl font-bold text-gray-900">{{ formatCurrency(expense.amount) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Receipt Photo -->
                <div v-if="expense.receipt_photo" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Bukti/Struk</h2>
                    <img
                        :src="`/storage/${expense.receipt_photo}`"
                        alt="Receipt"
                        class="max-w-full rounded-lg border cursor-pointer hover:opacity-90 transition"
                        @click="openImagePreview"
                    >
                    <p class="text-xs text-gray-400 mt-2 text-center">Klik gambar untuk memperbesar</p>
                </div>

                <!-- Rejection Reason (if rejected) -->
                <div v-if="expense.status === 'rejected' && expense.rejection_reason" class="bg-red-50 rounded-xl p-6">
                    <h2 class="text-lg font-semibold text-red-800 mb-2">Alasan Penolakan</h2>
                    <p class="text-red-700">{{ expense.rejection_reason }}</p>
                    <p class="text-sm text-red-600 mt-2">
                        Ditolak oleh {{ expense.verified_by?.name }} pada {{ formatDateTime(expense.verified_at) }}
                    </p>
                </div>

                <!-- Approval Notes (if approved) -->
                <div v-if="expense.status === 'approved' && expense.notes" class="bg-green-50 rounded-xl p-6">
                    <h2 class="text-lg font-semibold text-green-800 mb-2">Catatan Persetujuan</h2>
                    <p class="text-green-700">{{ expense.notes }}</p>
                    <p class="text-sm text-green-600 mt-2">
                        Disetujui oleh {{ expense.verified_by?.name }} pada {{ formatDateTime(expense.verified_at) }}
                    </p>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Actions -->
                <div v-if="expense.status === 'pending'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Aksi</h2>

                    <div class="space-y-2">
                        <button
                            @click="showApproveModal = true"
                            class="w-full py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Setujui
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
                            <span class="text-gray-500">ID</span>
                            <span class="font-mono">{{ expense.id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Dibuat</span>
                            <span>{{ formatDateTime(expense.created_at) }}</span>
                        </div>
                        <div v-if="expense.verified_at" class="flex justify-between">
                            <span class="text-gray-500">Diverifikasi</span>
                            <span>{{ formatDateTime(expense.verified_at) }}</span>
                        </div>
                        <div v-if="expense.verified_by" class="flex justify-between">
                            <span class="text-gray-500">Oleh</span>
                            <span>{{ expense.verified_by?.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approve Modal -->
        <div
            v-if="showApproveModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showApproveModal = false"
        >
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Setujui Pengeluaran</h3>

                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm text-gray-500">Jumlah</p>
                        <p class="text-xl font-bold">{{ formatCurrency(expense.amount) }}</p>
                        <p class="text-sm text-gray-500 mt-1">{{ categoryLabel(expense.category) }} - {{ expense.description }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan (opsional)</label>
                        <textarea
                            v-model="approvalNotes"
                            rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Catatan persetujuan..."
                        ></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button
                            @click="showApproveModal = false"
                            class="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                        >
                            Batal
                        </button>
                        <button
                            @click="approveExpense"
                            class="flex-1 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                        >
                            Setujui
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
                <h3 class="text-lg font-semibold mb-4">Tolak Pengeluaran</h3>

                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm text-gray-500">Jumlah</p>
                        <p class="text-xl font-bold">{{ formatCurrency(expense.amount) }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Penolakan *</label>
                        <textarea
                            v-model="rejectionReason"
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
                            @click="rejectExpense"
                            :disabled="!rejectionReason"
                            class="flex-1 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50"
                        >
                            Tolak
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Image Preview Modal -->
        <div
            v-if="showImagePreview"
            class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50"
            @click="closeImagePreview"
        >
            <!-- Close Button -->
            <button
                @click="closeImagePreview"
                class="absolute top-4 right-4 w-10 h-10 bg-white/20 rounded-full flex items-center justify-center text-white z-10"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Back Button -->
            <button
                @click="closeImagePreview"
                class="absolute top-4 left-4 w-10 h-10 bg-white/20 rounded-full flex items-center justify-center text-white z-10"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </button>

            <!-- Image -->
            <img
                :src="`/storage/${expense.receipt_photo}`"
                alt="Bukti Pengeluaran"
                class="max-w-full max-h-full object-contain p-4"
                @click.stop
            >
        </div>
    </AdminLayout>
</template>

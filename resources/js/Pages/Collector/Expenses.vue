<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import CollectorLayout from '@/Layouts/CollectorLayout.vue'

const props = defineProps({
    expenses: Object,
    dailySummary: Object,
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
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    })
}

// New expense modal
const showExpenseModal = ref(false)
const form = useForm({
    amount: '',
    description: '',
    receipt_photo: null,
})

const openExpenseModal = () => {
    form.reset()
    showExpenseModal.value = true
}

const handleReceiptUpload = (event) => {
    form.receipt_photo = event.target.files[0]
}

const submitExpense = () => {
    const formData = new FormData()
    formData.append('amount', form.amount)
    formData.append('description', form.description)
    if (form.receipt_photo) {
        formData.append('receipt_photo', form.receipt_photo)
    }

    router.post(route('collector.expenses.store'), formData, {
        onSuccess: () => {
            showExpenseModal.value = false
            form.reset()
        },
    })
}

// Status badge
const getStatusBadge = (status) => {
    const badges = {
        pending: { class: 'bg-yellow-100 text-yellow-600', text: 'Menunggu' },
        approved: { class: 'bg-green-100 text-green-600', text: 'Disetujui' },
        rejected: { class: 'bg-red-100 text-red-600', text: 'Ditolak' },
    }
    return badges[status] || { class: 'bg-gray-100 text-gray-600', text: status }
}

// Image preview modal
const showImagePreview = ref(false)
const previewImageUrl = ref('')

const openImagePreview = (imagePath) => {
    previewImageUrl.value = `/storage/${imagePath}`
    showImagePreview.value = true
}

const closeImagePreview = () => {
    showImagePreview.value = false
    previewImageUrl.value = ''
}

</script>

<template>
    <Head title="Belanja" />

    <CollectorLayout>
        <div class="min-h-screen bg-gray-100 pb-20">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white px-4 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Link :href="route('collector.dashboard')" class="p-1">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </Link>
                        <div>
                            <h1 class="text-xl font-bold">Belanja</h1>
                            <p class="text-blue-100 text-sm">Catat pengeluaran harian</p>
                        </div>
                    </div>
                    <button
                        @click="openExpenseModal"
                        class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Daily Summary -->
            <div class="px-4 -mt-4">
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <h3 class="text-sm font-medium text-gray-500 mb-3">Ringkasan Hari Ini</h3>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p class="text-xs text-gray-500">Diajukan</p>
                            <p class="text-lg font-bold text-yellow-600">
                                {{ formatCurrency(dailySummary?.pending || 0) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Disetujui</p>
                            <p class="text-lg font-bold text-green-600">
                                {{ formatCurrency(dailySummary?.approved || 0) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Ditolak</p>
                            <p class="text-lg font-bold text-red-600">
                                {{ formatCurrency(dailySummary?.rejected || 0) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expense List -->
            <div class="px-4 mt-4">
                <h3 class="text-sm font-medium text-gray-500 mb-3">Riwayat Belanja</h3>

                <div class="space-y-3">
                    <div
                        v-for="expense in expenses.data"
                        :key="expense.id"
                        class="bg-white rounded-xl shadow-sm p-4"
                    >
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold text-gray-800">{{ expense.description }}</p>
                                        <p class="text-xs text-gray-400 mt-1">{{ formatDate(expense.created_at) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-gray-800">{{ formatCurrency(expense.amount) }}</p>
                                        <span
                                            class="px-2 py-0.5 text-xs rounded-full mt-1 inline-block"
                                            :class="getStatusBadge(expense.status).class"
                                        >
                                            {{ getStatusBadge(expense.status).text }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Rejection reason -->
                                <div v-if="expense.status === 'rejected' && expense.rejection_reason" class="mt-2 p-2 bg-red-50 rounded text-sm text-red-600">
                                    Alasan: {{ expense.rejection_reason }}
                                </div>

                                <!-- Receipt photo -->
                                <div v-if="expense.receipt_photo" class="mt-2">
                                    <button
                                        @click="openImagePreview(expense.receipt_photo)"
                                        class="text-blue-600 text-sm hover:underline flex items-center gap-1"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Lihat Bukti
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!expenses.data?.length" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                    </svg>
                    <p class="mt-4 text-gray-500">Belum ada belanja tercatat</p>
                    <button
                        @click="openExpenseModal"
                        class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm"
                    >
                        Tambah Belanja
                    </button>
                </div>

                <!-- Pagination -->
                <div v-if="expenses.data?.length" class="flex justify-center gap-2 py-4">
                    <Link
                        v-for="link in expenses.links"
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

            <!-- New Expense Modal -->
            <div
                v-if="showExpenseModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex items-end justify-center z-50"
                @click.self="showExpenseModal = false"
            >
                <div class="bg-white rounded-t-2xl w-full max-w-lg p-6 animate-slide-up">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Tambah Belanja</h3>
                        <button @click="showExpenseModal = false" class="text-gray-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitExpense" class="space-y-4">
                        <!-- Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah</label>
                            <input
                                v-model="form.amount"
                                type="number"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500"
                                placeholder="0"
                            >
                            <p v-if="form.errors.amount" class="text-red-500 text-sm mt-1">{{ form.errors.amount }}</p>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Keterangan</label>
                            <textarea
                                v-model="form.description"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500"
                                rows="2"
                                placeholder="Deskripsi belanja..."
                            ></textarea>
                            <p v-if="form.errors.description" class="text-red-500 text-sm mt-1">{{ form.errors.description }}</p>
                        </div>

                        <!-- Receipt Photo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Foto Bukti (Opsional)</label>
                            <input
                                type="file"
                                accept="image/*"
                                capture="environment"
                                @change="handleReceiptUpload"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300"
                            >
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            :disabled="form.processing || !form.amount || !form.description"
                            class="w-full py-3 bg-blue-600 text-white rounded-lg font-semibold disabled:opacity-50"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Belanja' }}
                        </button>
                    </form>
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
                    :src="previewImageUrl"
                    alt="Bukti"
                    class="max-w-full max-h-full object-contain p-4"
                    @click.stop
                >
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

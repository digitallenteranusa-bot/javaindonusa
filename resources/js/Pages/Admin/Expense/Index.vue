<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    expenses: Object,
    filters: Object,
    collectors: Array,
    categories: Object,
    pendingCount: Number,
    pendingAmount: Number,
})

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const userFilter = ref(props.filters.user_id || '')
const categoryFilter = ref(props.filters.category || '')
const startDate = ref(props.filters.start_date || '')
const endDate = ref(props.filters.end_date || '')

const selectedExpenses = ref([])

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
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

// Apply filters
const applyFilters = () => {
    router.get('/admin/expenses', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        user_id: userFilter.value || undefined,
        category: categoryFilter.value || undefined,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Watch filter changes
watch([statusFilter, userFilter, categoryFilter, startDate, endDate], applyFilters)

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
        pending: 'Menunggu',
        approved: 'Disetujui',
        rejected: 'Ditolak',
    }
    return labels[status] || status
}

// Category badge
const categoryClass = (category) => {
    const classes = {
        transport: 'bg-blue-100 text-blue-700',
        meal: 'bg-orange-100 text-orange-700',
        fuel: 'bg-purple-100 text-purple-700',
        parking: 'bg-cyan-100 text-cyan-700',
        toll: 'bg-indigo-100 text-indigo-700',
        maintenance: 'bg-pink-100 text-pink-700',
        other: 'bg-gray-100 text-gray-700',
    }
    return classes[category] || 'bg-gray-100 text-gray-700'
}

// Approve expense
const approveExpense = (expense) => {
    if (confirm(`Setujui pengeluaran ${formatCurrency(expense.amount)}?`)) {
        router.post(`/admin/expenses/${expense.id}/approve`)
    }
}

// Reject expense
const rejectExpense = (expense) => {
    const reason = prompt('Alasan penolakan:')
    if (reason) {
        router.post(`/admin/expenses/${expense.id}/reject`, { reason })
    }
}

// Bulk approve
const bulkApprove = () => {
    if (selectedExpenses.value.length === 0) {
        alert('Pilih pengeluaran yang akan disetujui')
        return
    }
    if (confirm(`Setujui ${selectedExpenses.value.length} pengeluaran?`)) {
        router.post('/admin/expenses/bulk-approve', {
            expense_ids: selectedExpenses.value,
        }, {
            onSuccess: () => {
                selectedExpenses.value = []
            },
        })
    }
}

// Toggle select all
const toggleSelectAll = () => {
    const pendingExpenses = props.expenses.data.filter(e => e.status === 'pending')
    if (selectedExpenses.value.length === pendingExpenses.length) {
        selectedExpenses.value = []
    } else {
        selectedExpenses.value = pendingExpenses.map(e => e.id)
    }
}
</script>

<template>
    <Head title="Pengeluaran" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Pengeluaran</h1>
                <div class="flex gap-2">
                    <Link
                        v-if="pendingCount > 0"
                        href="/admin/expenses/pending"
                        class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 flex items-center gap-2"
                    >
                        <span class="bg-yellow-600 text-xs px-2 py-0.5 rounded-full">{{ pendingCount }}</span>
                        Perlu Persetujuan
                    </Link>
                </div>
            </div>
        </template>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Total Pengeluaran</p>
                <p class="text-2xl font-bold">{{ expenses.total }}</p>
            </div>
            <div class="bg-yellow-50 rounded-xl shadow-sm p-4">
                <p class="text-yellow-600 text-xs">Menunggu Persetujuan</p>
                <p class="text-2xl font-bold text-yellow-600">{{ pendingCount }}</p>
                <p class="text-sm text-yellow-500">{{ formatCurrency(pendingAmount) }}</p>
            </div>
            <div class="bg-green-50 rounded-xl shadow-sm p-4">
                <p class="text-green-600 text-xs">Disetujui</p>
                <p class="text-2xl font-bold text-green-600">
                    {{ expenses.data?.filter(e => e.status === 'approved').length || 0 }}
                </p>
            </div>
            <div class="bg-red-50 rounded-xl shadow-sm p-4">
                <p class="text-red-600 text-xs">Ditolak</p>
                <p class="text-2xl font-bold text-red-600">
                    {{ expenses.data?.filter(e => e.status === 'rejected').length || 0 }}
                </p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div class="md:col-span-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari deskripsi..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        @keyup.enter="applyFilters"
                    >
                </div>
                <select
                    v-model="statusFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Status</option>
                    <option value="pending">Menunggu</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                </select>
                <select
                    v-model="userFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Penagih</option>
                    <option v-for="collector in collectors" :key="collector.id" :value="collector.id">
                        {{ collector.name }}
                    </option>
                </select>
                <select
                    v-model="categoryFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Kategori</option>
                    <option v-for="(label, key) in categories" :key="key" :value="key">
                        {{ label }}
                    </option>
                </select>
                <div class="flex gap-2">
                    <input
                        v-model="startDate"
                        type="date"
                        class="flex-1 px-2 py-2 border border-gray-300 rounded-lg text-sm"
                    >
                    <input
                        v-model="endDate"
                        type="date"
                        class="flex-1 px-2 py-2 border border-gray-300 rounded-lg text-sm"
                    >
                </div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div v-if="selectedExpenses.length > 0" class="bg-blue-50 rounded-xl p-4 mb-4 flex items-center justify-between">
            <span class="text-blue-700">{{ selectedExpenses.length }} pengeluaran dipilih</span>
            <button
                @click="bulkApprove"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
            >
                Setujui Semua
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left">
                                <input
                                    type="checkbox"
                                    @change="toggleSelectAll"
                                    :checked="selectedExpenses.length === expenses.data?.filter(e => e.status === 'pending').length && selectedExpenses.length > 0"
                                    class="w-4 h-4 text-blue-600 rounded"
                                >
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penagih</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="expense in expenses.data" :key="expense.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <input
                                    v-if="expense.status === 'pending'"
                                    type="checkbox"
                                    :value="expense.id"
                                    v-model="selectedExpenses"
                                    class="w-4 h-4 text-blue-600 rounded"
                                >
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ formatDate(expense.expense_date) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-sm">{{ expense.user?.name }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['px-2 py-1 text-xs rounded-full', categoryClass(expense.category)]">
                                    {{ categories[expense.category] || expense.category }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">
                                {{ expense.description }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-semibold">{{ formatCurrency(expense.amount) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['px-2 py-1 text-xs rounded-full', statusClass(expense.status)]">
                                    {{ statusLabel(expense.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link
                                        :href="`/admin/expenses/${expense.id}`"
                                        class="p-1 text-gray-500 hover:text-blue-600"
                                        title="Lihat"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>
                                    <button
                                        v-if="expense.status === 'pending'"
                                        @click="approveExpense(expense)"
                                        class="p-1 text-gray-500 hover:text-green-600"
                                        title="Setujui"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="expense.status === 'pending'"
                                        @click="rejectExpense(expense)"
                                        class="p-1 text-gray-500 hover:text-red-600"
                                        title="Tolak"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="!expenses.data?.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                </svg>
                <p class="mt-4 text-gray-500">Tidak ada pengeluaran ditemukan</p>
            </div>

            <!-- Pagination -->
            <div v-if="expenses.data?.length" class="px-4 py-3 border-t flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Menampilkan {{ expenses.from }} - {{ expenses.to }} dari {{ expenses.total }} pengeluaran
                </p>
                <div class="flex gap-2">
                    <Link
                        v-for="link in expenses.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-1 text-sm rounded',
                            link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

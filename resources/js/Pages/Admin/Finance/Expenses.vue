<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    expenses: Object,
    filters: Object,
    categories: Object,
    totalAmount: Number,
    totalCount: Number,
})

const search = ref(props.filters.search || '')
const monthFilter = ref(props.filters.month || String(new Date().getMonth() + 1))
const yearFilter = ref(props.filters.year || String(new Date().getFullYear()))
const categoryFilter = ref(props.filters.category || '')

const currentYear = new Date().getFullYear()
const years = Array.from({ length: 5 }, (_, i) => currentYear - i)
const months = [
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
]

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const applyFilters = () => {
    router.get('/admin/finance/expenses', {
        search: search.value || undefined,
        month: monthFilter.value || undefined,
        year: yearFilter.value || undefined,
        category: categoryFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

watch([monthFilter, yearFilter, categoryFilter], applyFilters)

let searchTimeout = null
const onSearch = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 300)
}

const categoryBadgeClass = (category) => {
    const classes = {
        salary: 'bg-purple-100 text-purple-700',
        rent: 'bg-blue-100 text-blue-700',
        electricity: 'bg-yellow-100 text-yellow-700',
        internet: 'bg-cyan-100 text-cyan-700',
        equipment: 'bg-orange-100 text-orange-700',
        maintenance: 'bg-green-100 text-green-700',
        other: 'bg-gray-100 text-gray-700',
    }
    return classes[category] || 'bg-gray-100 text-gray-700'
}

const deleteExpense = (expense) => {
    if (confirm(`Hapus pengeluaran "${expense.description}" (${formatCurrency(expense.amount)})?`)) {
        router.delete(`/admin/finance/expenses/${expense.id}`)
    }
}
</script>

<template>
    <Head title="Keuangan - Pengeluaran Operasional" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Pengeluaran Operasional</h1>
                <Link
                    href="/admin/finance/expenses/create"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 text-sm"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Pengeluaran
                </Link>
            </div>
        </template>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Total Pengeluaran</p>
                <p class="text-2xl font-bold text-red-600">{{ formatCurrency(totalAmount) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Jumlah Transaksi</p>
                <p class="text-2xl font-bold">{{ totalCount }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari deskripsi..."
                    class="border rounded-lg px-3 py-2 text-sm"
                    @input="onSearch"
                />
                <select v-model="monthFilter" class="border rounded-lg px-3 py-2 text-sm">
                    <option value="">Semua Bulan</option>
                    <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
                <select v-model="yearFilter" class="border rounded-lg px-3 py-2 text-sm">
                    <option v-for="y in years" :key="y" :value="String(y)">{{ y }}</option>
                </select>
                <select v-model="categoryFilter" class="border rounded-lg px-3 py-2 text-sm">
                    <option value="">Semua Kategori</option>
                    <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kategori</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Nominal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dibuat Oleh</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="expense in expenses.data" :key="expense.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap">
                                {{ formatDate(expense.expense_date) }}
                            </td>
                            <td class="px-6 py-4">
                                <span :class="['px-2 py-1 text-xs rounded-full font-medium', categoryBadgeClass(expense.category)]">
                                    {{ categories[expense.category] || expense.category }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <div>{{ expense.description }}</div>
                                <div v-if="expense.notes" class="text-xs text-gray-400 mt-1">{{ expense.notes }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-right text-red-600 whitespace-nowrap">
                                {{ formatCurrency(expense.amount) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ expense.created_by?.name || '-' }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <Link
                                        :href="`/admin/finance/expenses/${expense.id}/edit`"
                                        class="text-blue-600 hover:text-blue-800 text-sm"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        @click="deleteExpense(expense)"
                                        class="text-red-600 hover:text-red-800 text-sm"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!expenses.data || expenses.data.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                Belum ada data pengeluaran operasional
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="expenses.links && expenses.last_page > 1" class="px-6 py-4 border-t bg-gray-50">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-600">
                        Menampilkan {{ expenses.from }}-{{ expenses.to }} dari {{ expenses.total }}
                    </p>
                    <div class="flex gap-1">
                        <template v-for="link in expenses.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'px-3 py-1 text-sm rounded',
                                    link.active
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-white border text-gray-600 hover:bg-gray-50'
                                ]"
                                v-html="link.label"
                                preserve-state
                                preserve-scroll
                            />
                            <span
                                v-else
                                class="px-3 py-1 text-sm text-gray-400"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

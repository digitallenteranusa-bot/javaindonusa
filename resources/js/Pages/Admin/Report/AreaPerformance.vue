<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    filters: Object,
    years: Array,
    months: Array,
    areas: Array,
})

const selectedYear = ref(props.filters.year)
const selectedMonth = ref(props.filters.month)

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

// Apply filter
const applyFilter = () => {
    router.get('/admin/reports/areas', {
        year: selectedYear.value,
        month: selectedMonth.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

watch([selectedYear, selectedMonth], applyFilter)

// Get rate color
const getRateColor = (rate) => {
    if (rate >= 80) return 'text-green-600'
    if (rate >= 60) return 'text-yellow-600'
    return 'text-red-600'
}

const getRateBgColor = (rate) => {
    if (rate >= 80) return 'bg-green-500'
    if (rate >= 60) return 'bg-yellow-500'
    return 'bg-red-500'
}

// Get month name
const getMonthName = (month) => {
    return props.months.find(m => m.value === month)?.label || ''
}
</script>

<template>
    <Head title="Performa Area" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/reports" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Performa Area</h1>
                        <p class="text-gray-500 text-sm">{{ getMonthName(selectedMonth) }} {{ selectedYear }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <select
                        v-model="selectedMonth"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm"
                    >
                        <option v-for="month in months" :key="month.value" :value="month.value">
                            {{ month.label }}
                        </option>
                    </select>
                    <select
                        v-model="selectedYear"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm"
                    >
                        <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                    </select>
                </div>
            </div>
        </template>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Total Area</p>
                <p class="text-2xl font-bold mt-1">{{ areas.length }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Total Tagihan</p>
                <p class="text-2xl font-bold mt-1 text-blue-600">
                    {{ formatCurrency(areas.reduce((a, b) => a + b.total_billed, 0)) }}
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Total Terbayar</p>
                <p class="text-2xl font-bold mt-1 text-green-600">
                    {{ formatCurrency(areas.reduce((a, b) => a + b.total_paid, 0)) }}
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Total Outstanding</p>
                <p class="text-2xl font-bold mt-1 text-red-600">
                    {{ formatCurrency(areas.reduce((a, b) => a + b.outstanding, 0)) }}
                </p>
            </div>
        </div>

        <!-- Area Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div
                v-for="(area, index) in areas"
                :key="area.id"
                class="bg-white rounded-xl shadow-sm p-6"
            >
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="index < 3"
                                class="w-6 h-6 rounded-full bg-yellow-100 text-yellow-700 flex items-center justify-center text-xs font-bold"
                            >
                                {{ index + 1 }}
                            </span>
                            <h3 class="font-semibold text-lg">{{ area.name }}</h3>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">{{ area.customers_count }} pelanggan</p>
                    </div>
                    <span
                        :class="['text-2xl font-bold', getRateColor(area.collection_rate)]"
                    >
                        {{ area.collection_rate }}%
                    </span>
                </div>

                <!-- Progress Bar -->
                <div class="w-full bg-gray-100 rounded-full h-3 mb-4">
                    <div
                        :class="[getRateBgColor(area.collection_rate), 'h-3 rounded-full transition-all duration-300']"
                        :style="{ width: `${area.collection_rate}%` }"
                    ></div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Tagihan</p>
                        <p class="font-semibold">{{ formatCurrency(area.total_billed) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Terbayar</p>
                        <p class="font-semibold text-green-600">{{ formatCurrency(area.total_paid) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Outstanding</p>
                        <p class="font-semibold text-red-600">{{ formatCurrency(area.outstanding) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Status</p>
                        <p>
                            <span class="text-green-600">{{ area.paid_count }} lunas</span>
                            <span class="text-gray-400 mx-1">•</span>
                            <span class="text-red-600">{{ area.overdue_count }} overdue</span>
                        </p>
                    </div>
                </div>

                <!-- Action -->
                <Link
                    :href="`/admin/customers?area_id=${area.id}`"
                    class="mt-4 block text-center py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200"
                >
                    Lihat Pelanggan
                </Link>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!areas.length" class="bg-white rounded-xl shadow-sm p-12 text-center">
            <p class="text-gray-500">Tidak ada data area</p>
        </div>
    </AdminLayout>
</template>

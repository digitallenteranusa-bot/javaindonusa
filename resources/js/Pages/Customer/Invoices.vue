<script setup>
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
    customer: Object,
    invoices: Object,
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
        year: 'numeric',
    })
}

// Status badge
const getStatusBadge = (status) => {
    const badges = {
        paid: { class: 'bg-green-100 text-green-600', text: 'Lunas' },
        pending: { class: 'bg-yellow-100 text-yellow-600', text: 'Belum Bayar' },
        partial: { class: 'bg-blue-100 text-blue-600', text: 'Sebagian' },
        overdue: { class: 'bg-red-100 text-red-600', text: 'Jatuh Tempo' },
        cancelled: { class: 'bg-gray-100 text-gray-500', text: 'Dibatalkan' },
    }
    return badges[status] || { class: 'bg-gray-100 text-gray-600', text: status }
}
</script>

<template>
    <Head title="Histori Tagihan" />

    <div class="min-h-screen bg-gray-100">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white px-4 py-6">
            <div class="flex items-center gap-3">
                <Link :href="route('customer.dashboard')" class="p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-xl font-bold">Histori Tagihan</h1>
                    <p class="text-blue-100 text-sm">{{ customer.name }}</p>
                </div>
            </div>
        </div>

        <!-- Invoice List -->
        <div class="px-4 py-4">
            <div class="space-y-3">
                <div
                    v-for="invoice in invoices.data"
                    :key="invoice.id"
                    class="bg-white rounded-xl shadow-sm p-4"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-gray-800">
                                Periode {{ invoice.period_month }}/{{ invoice.period_year }}
                            </p>
                            <p class="text-sm text-gray-500 font-mono mt-1">{{ invoice.invoice_number }}</p>
                            <p class="text-xs text-gray-400 mt-1">
                                Jatuh tempo: {{ formatDate(invoice.due_date) }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-gray-800">{{ formatCurrency(invoice.total_amount) }}</p>
                            <span
                                class="px-2 py-1 text-xs rounded-full mt-1 inline-block"
                                :class="getStatusBadge(invoice.status).class"
                            >
                                {{ getStatusBadge(invoice.status).text }}
                            </span>
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <div v-if="invoice.paid_amount > 0" class="mt-3 pt-3 border-t border-gray-100 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Dibayar</span>
                            <span class="text-green-600">{{ formatCurrency(invoice.paid_amount) }}</span>
                        </div>
                        <div v-if="invoice.remaining_amount > 0" class="flex justify-between mt-1">
                            <span class="text-gray-500">Sisa</span>
                            <span class="text-red-600 font-medium">{{ formatCurrency(invoice.remaining_amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!invoices.data?.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="mt-4 text-gray-500">Belum ada tagihan</p>
            </div>

            <!-- Pagination -->
            <div v-if="invoices.data?.length" class="flex justify-center gap-2 py-4">
                <Link
                    v-for="link in invoices.links"
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
    </div>
</template>

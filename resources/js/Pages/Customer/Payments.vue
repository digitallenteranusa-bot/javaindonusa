<script setup>
import { Head, Link } from '@inertiajs/vue3'
import CustomerLayout from '@/Layouts/CustomerLayout.vue'

const props = defineProps({
    customer: Object,
    payments: Object,
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
const formatDateTime = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

// Payment method badge
const getMethodBadge = (method) => {
    const badges = {
        cash: { class: 'bg-green-100 text-green-600', text: 'Tunai' },
        transfer: { class: 'bg-blue-100 text-blue-600', text: 'Transfer' },
    }
    return badges[method] || { class: 'bg-gray-100 text-gray-600', text: method }
}
</script>

<template>
    <Head title="Histori Pembayaran" />

    <CustomerLayout :customer="customer">
        <div class="bg-gray-100 pb-4">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white px-4 py-6">
            <div class="flex items-center gap-3">
                <Link :href="route('customer.dashboard')" class="p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-xl font-bold">Histori Pembayaran</h1>
                    <p class="text-blue-100 text-sm">{{ customer.name }}</p>
                </div>
            </div>
        </div>

        <!-- Payment List -->
        <div class="px-4 py-4">
            <div class="space-y-3">
                <div
                    v-for="payment in payments.data"
                    :key="payment.id"
                    class="bg-white rounded-xl shadow-sm p-4"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-bold text-green-600 text-lg">
                                {{ formatCurrency(payment.amount) }}
                            </p>
                            <p class="text-sm text-gray-500 font-mono mt-1">{{ payment.payment_number }}</p>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ formatDateTime(payment.created_at) }}
                            </p>
                        </div>
                        <div class="text-right">
                            <span
                                class="px-2 py-1 text-xs rounded-full"
                                :class="getMethodBadge(payment.payment_method).class"
                            >
                                {{ getMethodBadge(payment.payment_method).text }}
                            </span>
                            <p v-if="payment.collector" class="text-xs text-gray-400 mt-2">
                                via {{ payment.collector.name }}
                            </p>
                        </div>
                    </div>

                    <!-- Allocated Invoices -->
                    <div v-if="payment.invoices?.length" class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-2">Dialokasikan ke:</p>
                        <div class="space-y-1">
                            <div
                                v-for="invoice in payment.invoices"
                                :key="invoice.id"
                                class="flex justify-between text-sm"
                            >
                                <span class="text-gray-600">
                                    {{ invoice.period_month }}/{{ invoice.period_year }}
                                </span>
                                <span class="text-gray-800">{{ formatCurrency(invoice.pivot?.amount) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="payment.notes" class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-sm text-gray-600">{{ payment.notes }}</p>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!payments.data?.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <p class="mt-4 text-gray-500">Belum ada pembayaran</p>
            </div>

            <!-- Pagination -->
            <div v-if="payments.data?.length" class="flex justify-center gap-2 py-4">
                <Link
                    v-for="link in payments.links"
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
    </CustomerLayout>
</template>

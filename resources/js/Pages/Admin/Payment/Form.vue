<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    customers: Array,
})

// Get customer_id from URL params if present
const urlParams = new URLSearchParams(window.location.search)
const preselectedCustomerId = urlParams.get('customer_id')

const form = useForm({
    customer_id: preselectedCustomerId || '',
    amount: '',
    payment_method: 'cash',
    transfer_proof: '',
    notes: '',
})

const searchCustomer = ref('')
const showCustomerDropdown = ref(false)
const selectedCustomer = ref(null)

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

// Filter customers by search
const filteredCustomers = computed(() => {
    if (!searchCustomer.value) return props.customers?.slice(0, 10) || []

    const search = searchCustomer.value.toLowerCase()
    return props.customers?.filter(c =>
        c.name.toLowerCase().includes(search) ||
        c.customer_id.toLowerCase().includes(search) ||
        c.phone?.includes(search)
    ).slice(0, 10) || []
})

// Select customer
const selectCustomer = (customer) => {
    selectedCustomer.value = customer
    form.customer_id = customer.id
    searchCustomer.value = customer.name
    showCustomerDropdown.value = false

    // Set amount to customer's total debt
    if (customer.total_debt > 0) {
        form.amount = customer.total_debt
    }
}

// Initialize with preselected customer
if (preselectedCustomerId) {
    const customer = props.customers?.find(c => c.id == preselectedCustomerId)
    if (customer) {
        selectCustomer(customer)
    }
}

// Quick amount buttons
const setAmount = (amount) => {
    form.amount = amount
}

// Submit
const submit = () => {
    form.post('/admin/payments')
}
</script>

<template>
    <Head title="Input Pembayaran" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/payments" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">Input Pembayaran</h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6">
            <!-- Customer Selection -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Pilih Pelanggan</h2>

                <div class="relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cari Pelanggan *</label>
                    <input
                        v-model="searchCustomer"
                        type="text"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        :class="{ 'border-red-500': form.errors.customer_id }"
                        placeholder="Ketik nama, ID, atau telepon..."
                        @focus="showCustomerDropdown = true"
                        @blur="setTimeout(() => showCustomerDropdown = false, 200)"
                    >
                    <p v-if="form.errors.customer_id" class="text-red-500 text-sm mt-1">{{ form.errors.customer_id }}</p>

                    <!-- Dropdown -->
                    <div
                        v-if="showCustomerDropdown && filteredCustomers.length"
                        class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                    >
                        <button
                            v-for="customer in filteredCustomers"
                            :key="customer.id"
                            type="button"
                            class="w-full px-4 py-3 text-left hover:bg-gray-50 border-b last:border-b-0"
                            @click="selectCustomer(customer)"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium">{{ customer.name }}</p>
                                    <p class="text-xs text-gray-500">{{ customer.customer_id }} • {{ customer.phone }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-red-600 font-semibold text-sm">{{ formatCurrency(customer.total_debt) }}</p>
                                    <p class="text-xs text-gray-500">{{ customer.package?.name }}</p>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Selected Customer Info -->
                <div v-if="selectedCustomer" class="mt-4 p-4 bg-blue-50 rounded-lg">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-blue-900">{{ selectedCustomer.name }}</p>
                            <p class="text-sm text-blue-700">{{ selectedCustomer.customer_id }}</p>
                            <p class="text-sm text-blue-700">{{ selectedCustomer.phone }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-blue-700">Total Hutang</p>
                            <p class="text-xl font-bold text-red-600">{{ formatCurrency(selectedCustomer.total_debt) }}</p>
                            <div v-if="selectedCustomer.credit_balance > 0" class="mt-1">
                                <p class="text-sm text-blue-700">Saldo Kredit</p>
                                <p class="text-lg font-bold text-green-600">{{ formatCurrency(selectedCustomer.credit_balance) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Detail Pembayaran</h2>

                <div class="space-y-4">
                    <!-- Payment Method -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Metode Pembayaran</label>
                        <div class="flex gap-3">
                            <button
                                type="button"
                                @click="form.payment_method = 'cash'"
                                :class="[
                                    'flex-1 py-3 px-4 rounded-lg border-2 text-center font-medium transition-colors',
                                    form.payment_method === 'cash'
                                        ? 'border-green-500 bg-green-50 text-green-700'
                                        : 'border-gray-200 hover:border-gray-300'
                                ]"
                            >
                                <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Cash / Tunai
                            </button>
                            <button
                                type="button"
                                @click="form.payment_method = 'transfer'"
                                :class="[
                                    'flex-1 py-3 px-4 rounded-lg border-2 text-center font-medium transition-colors',
                                    form.payment_method === 'transfer'
                                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                                        : 'border-gray-200 hover:border-gray-300'
                                ]"
                            >
                                <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                                Transfer
                            </button>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Pembayaran *</label>
                        <input
                            v-model="form.amount"
                            type="number"
                            min="1000"
                            step="1000"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-xl font-semibold"
                            :class="{ 'border-red-500': form.errors.amount }"
                            placeholder="0"
                        >
                        <p v-if="form.errors.amount" class="text-red-500 text-sm mt-1">{{ form.errors.amount }}</p>

                        <!-- Quick amount buttons -->
                        <div v-if="selectedCustomer" class="flex gap-2 mt-2">
                            <button
                                type="button"
                                @click="setAmount(selectedCustomer.total_debt)"
                                class="px-3 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200"
                            >
                                Lunas ({{ formatCurrency(selectedCustomer.total_debt) }})
                            </button>
                            <button
                                type="button"
                                @click="setAmount(selectedCustomer.package?.price || 0)"
                                class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
                            >
                                1 Bulan ({{ formatCurrency(selectedCustomer.package?.price) }})
                            </button>
                        </div>
                    </div>

                    <!-- Transfer Proof -->
                    <div v-if="form.payment_method === 'transfer'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bukti Transfer</label>
                        <input
                            v-model="form.transfer_proof"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="URL bukti transfer atau nomor referensi"
                        >
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Catatan pembayaran (opsional)"
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div v-if="selectedCustomer && form.amount" class="bg-green-50 border border-green-200 rounded-xl p-6">
                <h3 class="font-semibold text-green-800 mb-3">Ringkasan Pembayaran</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-green-700">Pelanggan</span>
                        <span class="font-medium text-green-900">{{ selectedCustomer.name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-green-700">Metode</span>
                        <span class="font-medium text-green-900 capitalize">{{ form.payment_method }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-green-700">Hutang Sebelum</span>
                        <span class="font-medium text-red-600">{{ formatCurrency(selectedCustomer.total_debt) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-green-700">Pembayaran</span>
                        <span class="font-medium text-green-900">{{ formatCurrency(form.amount) }}</span>
                    </div>
                    <hr class="border-green-300">
                    <div class="flex justify-between text-base">
                        <span class="text-green-700 font-medium">Hutang Setelah</span>
                        <span class="font-bold text-green-900">
                            {{ formatCurrency(Math.max(0, selectedCustomer.total_debt - form.amount)) }}
                        </span>
                    </div>
                    <div v-if="form.amount > selectedCustomer.total_debt" class="flex justify-between text-base mt-1">
                        <span class="text-green-700 font-medium">Kredit Baru</span>
                        <span class="font-bold text-green-600">
                            + {{ formatCurrency(form.amount - selectedCustomer.total_debt) }}
                        </span>
                    </div>
                    <div v-if="form.amount > selectedCustomer.total_debt" class="mt-2 p-2 bg-green-100 rounded text-xs text-green-800">
                        Kelebihan bayar akan disimpan sebagai saldo kredit dan otomatis digunakan untuk tagihan berikutnya.
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4">
                <Link
                    href="/admin/payments"
                    class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                >
                    Batal
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing || !form.customer_id || !form.amount"
                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 font-semibold"
                >
                    {{ form.processing ? 'Memproses...' : 'Proses Pembayaran' }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>

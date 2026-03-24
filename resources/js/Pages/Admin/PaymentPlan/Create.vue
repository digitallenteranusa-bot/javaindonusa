<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    customer: Object,
})

const form = useForm({
    customer_id: props.customer?.id || '',
    installment_count: 6,
    total_amount: props.customer?.total_debt || '',
    notes: '',
})

const formatCurrency = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v || 0)

const customerSearch = ref('')
const customerResults = ref([])
const selectedCustomer = ref(props.customer || null)

const searchCustomers = async () => {
    if (customerSearch.value.length < 2) return
    try {
        const response = await fetch(`/admin/customers-search?search=${encodeURIComponent(customerSearch.value)}&limit=10`)
        const data = await response.json()
        customerResults.value = data.customers || data
    } catch (e) {
        customerResults.value = []
    }
}

const selectCustomer = (customer) => {
    selectedCustomer.value = customer
    form.customer_id = customer.id
    form.total_amount = customer.total_debt || 0
    customerResults.value = []
    customerSearch.value = ''
}

const installmentAmount = computed(() => {
    if (!form.total_amount || !form.installment_count) return 0
    return Math.ceil(form.total_amount / form.installment_count)
})

const submit = () => {
    form.post('/admin/payment-plans')
}
</script>

<template>
    <Head title="Buat Cicilan" />
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/payment-plans" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">Buat Cicilan</h1>
            </div>
        </template>

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Customer -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pelanggan *</label>
                        <div v-if="selectedCustomer" class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <div>
                                <p class="font-medium">{{ selectedCustomer.name }}</p>
                                <p class="text-sm text-gray-500">{{ selectedCustomer.customer_id }} — Hutang: {{ formatCurrency(selectedCustomer.total_debt) }}</p>
                            </div>
                            <button type="button" @click="selectedCustomer = null; form.customer_id = ''" class="text-red-500 text-sm">Ganti</button>
                        </div>
                        <div v-else class="relative">
                            <input v-model="customerSearch" @input="searchCustomers" placeholder="Ketik nama/ID pelanggan..." class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                            <div v-if="customerResults.length" class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                <button v-for="c in customerResults" :key="c.id" type="button" @click="selectCustomer(c)" class="w-full px-4 py-2 text-left hover:bg-gray-50 text-sm">
                                    <span class="font-medium">{{ c.name }}</span> <span class="text-gray-500">({{ c.customer_id }}) — {{ formatCurrency(c.total_debt) }}</span>
                                </button>
                            </div>
                        </div>
                        <p v-if="form.errors.customer_id" class="text-red-500 text-sm mt-1">{{ form.errors.customer_id }}</p>
                    </div>

                    <!-- Total Amount -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Hutang untuk Dicicil (Rp)</label>
                        <input v-model.number="form.total_amount" type="number" min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                        <p class="text-xs text-gray-500 mt-1">Kosongkan untuk menggunakan total hutang pelanggan saat ini.</p>
                    </div>

                    <!-- Installment Count -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Cicilan *</label>
                        <select v-model.number="form.installment_count" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option v-for="n in [2,3,4,5,6,8,10,12,18,24]" :key="n" :value="n">{{ n }}x bulan</option>
                        </select>
                        <p v-if="form.errors.installment_count" class="text-red-500 text-sm mt-1">{{ form.errors.installment_count }}</p>
                    </div>

                    <!-- Preview -->
                    <div v-if="form.total_amount && form.installment_count" class="bg-gray-50 rounded-lg p-4">
                        <h3 class="font-medium text-gray-700 mb-2">Ringkasan Cicilan</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span>Total Hutang:</span>
                                <span class="font-semibold">{{ formatCurrency(form.total_amount) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Jumlah Cicilan:</span>
                                <span class="font-semibold">{{ form.installment_count }}x</span>
                            </div>
                            <div class="flex justify-between border-t pt-2">
                                <span>Per Bulan:</span>
                                <span class="font-bold text-lg text-blue-600">{{ formatCurrency(installmentAmount) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea v-model="form.notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                    </div>

                    <div class="flex gap-3">
                        <Link href="/admin/payment-plans" class="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-center">Batal</Link>
                        <button type="submit" :disabled="form.processing" class="flex-1 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            {{ form.processing ? 'Menyimpan...' : 'Buat Cicilan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

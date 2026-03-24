<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    customer: Object,
})

const form = useForm({
    customer_id: props.customer?.id || '',
    type: 'credit',
    amount: '',
    reason: '',
    invoice_id: '',
    payment_id: '',
    notes: '',
})

const customerSearch = ref('')
const customerResults = ref([])
const selectedCustomer = ref(props.customer || null)
const searching = ref(false)

const searchCustomers = async () => {
    if (customerSearch.value.length < 2) return
    searching.value = true
    try {
        const response = await fetch(`/admin/customers-search?search=${encodeURIComponent(customerSearch.value)}&limit=10`)
        const data = await response.json()
        customerResults.value = data.customers || data
    } catch (e) {
        customerResults.value = []
    }
    searching.value = false
}

const selectCustomer = (customer) => {
    selectedCustomer.value = customer
    form.customer_id = customer.id
    customerResults.value = []
    customerSearch.value = ''
}

const submit = () => {
    form.post('/admin/credit-notes')
}

const typeOptions = [
    { value: 'refund', label: 'Refund — Pengembalian dana' },
    { value: 'credit', label: 'Credit Note — Tambah saldo kredit pelanggan' },
    { value: 'adjustment', label: 'Penyesuaian — Kurangi hutang langsung' },
]
</script>

<template>
    <Head title="Buat Credit Note" />
    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/credit-notes" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">Buat Credit Note</h1>
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
                                <p class="text-sm text-gray-500">{{ selectedCustomer.customer_id }}</p>
                            </div>
                            <button type="button" @click="selectedCustomer = null; form.customer_id = ''" class="text-red-500 text-sm">Ganti</button>
                        </div>
                        <div v-else class="relative">
                            <input v-model="customerSearch" @input="searchCustomers" placeholder="Ketik nama/ID pelanggan..." class="w-full px-4 py-2 border border-gray-300 rounded-lg" />
                            <div v-if="customerResults.length" class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                <button v-for="c in customerResults" :key="c.id" type="button" @click="selectCustomer(c)" class="w-full px-4 py-2 text-left hover:bg-gray-50 text-sm">
                                    <span class="font-medium">{{ c.name }}</span> <span class="text-gray-500">({{ c.customer_id }})</span>
                                </button>
                            </div>
                        </div>
                        <p v-if="form.errors.customer_id" class="text-red-500 text-sm mt-1">{{ form.errors.customer_id }}</p>
                    </div>

                    <!-- Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe *</label>
                        <select v-model="form.type" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah (Rp) *</label>
                        <input v-model.number="form.amount" type="number" min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="0" />
                        <p v-if="form.errors.amount" class="text-red-500 text-sm mt-1">{{ form.errors.amount }}</p>
                    </div>

                    <!-- Reason -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan *</label>
                        <textarea v-model="form.reason" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Jelaskan alasan credit note..."></textarea>
                        <p v-if="form.errors.reason" class="text-red-500 text-sm mt-1">{{ form.errors.reason }}</p>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Tambahan</label>
                        <textarea v-model="form.notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                    </div>

                    <div class="flex gap-3">
                        <Link href="/admin/credit-notes" class="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-center">Batal</Link>
                        <button type="submit" :disabled="form.processing" class="flex-1 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            {{ form.processing ? 'Menyimpan...' : 'Buat Credit Note' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

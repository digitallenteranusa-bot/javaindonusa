<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    customer: Object,
    debtSummary: Object,
})

const showAdjustModal = ref(false)
const adjustAmount = ref(0)
const adjustReason = ref('')

const showHistoricalModal = ref(false)
const historicalMonth = ref(new Date().getMonth()) // previous month default
const historicalYear = ref(new Date().getFullYear())
const historicalAmount = ref(props.customer.package?.price || 0)
const historicalDescription = ref('')

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
        month: 'long',
        day: 'numeric',
    })
}

// Status badge
const statusClass = (status) => {
    const classes = {
        active: 'bg-green-100 text-green-700',
        isolated: 'bg-red-100 text-red-700',
        suspended: 'bg-yellow-100 text-yellow-700',
        terminated: 'bg-gray-100 text-gray-700',
        paid: 'bg-green-100 text-green-700',
        pending: 'bg-yellow-100 text-yellow-700',
        partial: 'bg-blue-100 text-blue-700',
        overdue: 'bg-red-100 text-red-700',
    }
    return classes[status] || 'bg-gray-100 text-gray-700'
}

// Adjust debt
const submitAdjust = () => {
    router.post(`/admin/customers/${props.customer.id}/adjust-debt`, {
        amount: adjustAmount.value,
        reason: adjustReason.value,
    }, {
        onSuccess: () => {
            showAdjustModal.value = false
            adjustAmount.value = 0
            adjustReason.value = ''
        },
    })
}

// Submit historical invoice
const submitHistoricalInvoice = () => {
    router.post(`/admin/customers/${props.customer.id}/add-historical-invoice`, {
        month: historicalMonth.value,
        year: historicalYear.value,
        amount: historicalAmount.value,
        description: historicalDescription.value,
    }, {
        onSuccess: () => {
            showHistoricalModal.value = false
            historicalDescription.value = ''
        },
    })
}

// Recalculate debt
const recalculateDebt = () => {
    if (confirm('Yakin ingin merekalkukasi hutang dari invoice?')) {
        router.post(`/admin/customers/${props.customer.id}/recalculate-debt`)
    }
}
</script>

<template>
    <Head :title="`Pelanggan - ${customer.name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/customers" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ customer.name }}</h1>
                    <p class="text-gray-500">{{ customer.customer_id }}</p>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Info -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Customer Info Card -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-start justify-between mb-4">
                        <h2 class="text-lg font-semibold">Informasi Pelanggan</h2>
                        <div class="flex items-center gap-2">
                            <span :class="['px-3 py-1 text-sm rounded-full', statusClass(customer.status)]">
                                {{ customer.status }}
                            </span>
                            <Link
                                :href="`/admin/customers/${customer.id}/edit`"
                                class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                            >
                                Edit
                            </Link>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500">Nama</p>
                            <p class="font-medium">{{ customer.name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">No. Telepon</p>
                            <p class="font-medium">{{ customer.phone }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-gray-500">Alamat</p>
                            <p class="font-medium">{{ customer.full_address || customer.address }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Paket</p>
                            <p class="font-medium">{{ customer.package?.name || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Harga</p>
                            <p class="font-medium">{{ formatCurrency(customer.package?.price) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Area</p>
                            <p class="font-medium">{{ customer.area?.name || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Router</p>
                            <p class="font-medium">{{ customer.router?.name || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Penagih</p>
                            <p class="font-medium">{{ customer.collector?.name || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Tanggal Bergabung</p>
                            <p class="font-medium">{{ formatDate(customer.join_date) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Connection Info -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Data Koneksi</h2>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500">Tipe Koneksi</p>
                            <p class="font-medium">
                                <span :class="[
                                    'px-2 py-0.5 text-xs rounded-full',
                                    customer.connection_type === 'pppoe' ? 'bg-blue-100 text-blue-700' :
                                    customer.connection_type === 'static' ? 'bg-purple-100 text-purple-700' :
                                    'bg-orange-100 text-orange-700'
                                ]">
                                    {{ customer.connection_type === 'pppoe' ? 'PPPoE' :
                                       customer.connection_type === 'static' ? 'Static IP' : 'Hotspot' }}
                                </span>
                            </p>
                        </div>
                        <div v-if="customer.connection_type === 'pppoe' || customer.connection_type === 'hotspot'">
                            <p class="text-gray-500">{{ customer.connection_type === 'hotspot' ? 'Hotspot Username' : 'PPPoE Username' }}</p>
                            <p class="font-medium font-mono">{{ customer.pppoe_username || '-' }}</p>
                        </div>
                        <div v-if="customer.connection_type === 'static'">
                            <p class="text-gray-500">IP Address</p>
                            <p class="font-medium font-mono">{{ customer.ip_address || '-' }}</p>
                        </div>
                        <div v-if="customer.connection_type === 'static'">
                            <p class="text-gray-500">MAC Address</p>
                            <p class="font-medium font-mono">{{ customer.mac_address || '-' }}</p>
                        </div>
                        <div v-if="customer.connection_type === 'pppoe'">
                            <p class="text-gray-500">ODP</p>
                            <p v-if="customer.odp" class="font-medium">
                                {{ customer.odp.name }} ({{ customer.odp.code }})
                            </p>
                            <p v-else class="text-gray-400">Belum ditentukan</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Merk Router</p>
                            <p class="font-medium">{{ customer.onu_serial || '-' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Invoice History -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Riwayat Invoice</h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="pb-2 font-medium">No. Invoice</th>
                                    <th class="pb-2 font-medium">Periode</th>
                                    <th class="pb-2 font-medium">Total</th>
                                    <th class="pb-2 font-medium">Dibayar</th>
                                    <th class="pb-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="invoice in customer.invoices" :key="invoice.id">
                                    <td class="py-2 font-mono">{{ invoice.invoice_number }}</td>
                                    <td class="py-2">{{ invoice.period_month }}/{{ invoice.period_year }}</td>
                                    <td class="py-2">{{ formatCurrency(invoice.total_amount) }}</td>
                                    <td class="py-2">{{ formatCurrency(invoice.paid_amount) }}</td>
                                    <td class="py-2">
                                        <span :class="['px-2 py-0.5 text-xs rounded-full', statusClass(invoice.status)]">
                                            {{ invoice.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="!customer.invoices?.length" class="text-center py-8 text-gray-500">
                        Belum ada invoice
                    </div>
                </div>

                <!-- Payment History -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Riwayat Pembayaran</h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="pb-2 font-medium">No. Pembayaran</th>
                                    <th class="pb-2 font-medium">Tanggal</th>
                                    <th class="pb-2 font-medium">Metode</th>
                                    <th class="pb-2 font-medium">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="payment in customer.payments" :key="payment.id">
                                    <td class="py-2 font-mono">{{ payment.payment_number }}</td>
                                    <td class="py-2">{{ formatDate(payment.created_at) }}</td>
                                    <td class="py-2 capitalize">{{ payment.payment_method }}</td>
                                    <td class="py-2 text-green-600 font-semibold">{{ formatCurrency(payment.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="!customer.payments?.length" class="text-center py-8 text-gray-500">
                        Belum ada pembayaran
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Debt Summary -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Ringkasan Hutang</h2>

                    <div class="text-center mb-4">
                        <p class="text-gray-500 text-sm">Total Hutang</p>
                        <p class="text-3xl font-bold text-red-600">
                            {{ formatCurrency(customer.total_debt) }}
                        </p>
                        <div v-if="customer.credit_balance > 0" class="mt-2">
                            <p class="text-gray-500 text-sm">Saldo Kredit</p>
                            <p class="text-xl font-bold text-green-600">
                                {{ formatCurrency(customer.credit_balance) }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Invoice Belum Lunas</span>
                            <span class="font-medium">{{ debtSummary?.unpaid_invoices_count || 0 }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Tertua</span>
                            <span class="font-medium">{{ debtSummary?.oldest_unpaid || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Terbaru</span>
                            <span class="font-medium">{{ debtSummary?.newest_unpaid || '-' }}</span>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="space-y-2">
                        <button
                            @click="showHistoricalModal = true"
                            class="w-full py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 text-sm"
                        >
                            Tambah Invoice Lama
                        </button>
                        <button
                            @click="showAdjustModal = true"
                            class="w-full py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 text-sm"
                        >
                            Adjust Hutang
                        </button>
                        <button
                            @click="recalculateDebt"
                            class="w-full py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm"
                        >
                            Rekalkukasi Hutang
                        </button>
                    </div>
                </div>

                <!-- Debt History -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Riwayat Hutang</h2>

                    <div class="space-y-3 max-h-80 overflow-y-auto">
                        <div
                            v-for="history in customer.debt_histories"
                            :key="history.id"
                            class="border-l-2 pl-3 py-1"
                            :class="history.type.includes('payment') || history.type.includes('subtract') ? 'border-green-500' : 'border-red-500'"
                        >
                            <p class="text-sm font-medium">{{ history.description }}</p>
                            <p class="text-xs text-gray-500">
                                {{ formatCurrency(history.amount) }} •
                                {{ formatDate(history.created_at) }}
                            </p>
                        </div>
                    </div>

                    <div v-if="!customer.debt_histories?.length" class="text-center py-4 text-gray-500 text-sm">
                        Belum ada riwayat
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Aksi Cepat</h2>

                    <div class="space-y-2">
                        <a
                            :href="`https://wa.me/62${customer.phone?.replace(/^0/, '')}`"
                            target="_blank"
                            class="w-full py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 text-sm flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                            </svg>
                            WhatsApp
                        </a>
                        <Link
                            :href="`/admin/payments/create?customer_id=${customer.id}`"
                            class="w-full py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 text-sm flex items-center justify-center"
                        >
                            Input Pembayaran
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Adjust Modal -->
        <div
            v-if="showAdjustModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showAdjustModal = false"
        >
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Adjust Hutang</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Jumlah (positif = tambah, negatif = kurang)
                        </label>
                        <input
                            v-model="adjustAmount"
                            type="number"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan</label>
                        <textarea
                            v-model="adjustReason"
                            rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button
                            @click="showAdjustModal = false"
                            class="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                        >
                            Batal
                        </button>
                        <button
                            @click="submitAdjust"
                            class="flex-1 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                        >
                            Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Historical Invoice Modal -->
        <div
            v-if="showHistoricalModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showHistoricalModal = false"
        >
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Tambah Invoice Lama</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Buat invoice untuk periode lama yang belum tercatat. Invoice akan otomatis berstatus overdue dan menambah hutang.
                </p>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
                            <select
                                v-model="historicalMonth"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option :value="1">Januari</option>
                                <option :value="2">Februari</option>
                                <option :value="3">Maret</option>
                                <option :value="4">April</option>
                                <option :value="5">Mei</option>
                                <option :value="6">Juni</option>
                                <option :value="7">Juli</option>
                                <option :value="8">Agustus</option>
                                <option :value="9">September</option>
                                <option :value="10">Oktober</option>
                                <option :value="11">November</option>
                                <option :value="12">Desember</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
                            <select
                                v-model="historicalYear"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="y in 7" :key="y" :value="new Date().getFullYear() - y + 1">
                                    {{ new Date().getFullYear() - y + 1 }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah (Rp)</label>
                        <input
                            v-model="historicalAmount"
                            type="number"
                            min="1000"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        >
                        <p class="text-xs text-gray-400 mt-1">Default: harga paket saat ini</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan (opsional)</label>
                        <textarea
                            v-model="historicalDescription"
                            rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Misal: Hutang bulan lalu belum tercatat"
                        ></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button
                            @click="showHistoricalModal = false"
                            class="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                        >
                            Batal
                        </button>
                        <button
                            @click="submitHistoricalInvoice"
                            class="flex-1 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600"
                        >
                            Buat Invoice
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

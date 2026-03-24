<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    creditNotes: Object,
    filters: Object,
    stats: Object,
})

const formatCurrency = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v || 0)
const formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' }) : '-'

const statusClass = (s) => ({
    pending: 'bg-yellow-100 text-yellow-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
}[s] || 'bg-gray-100 text-gray-700')

const statusLabel = (s) => ({ pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak' }[s] || s)
const typeLabel = (t) => ({ refund: 'Refund', credit: 'Credit Note', adjustment: 'Penyesuaian' }[t] || t)

const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')

const applyFilters = () => {
    router.get('/admin/credit-notes', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true })
}

const approve = (cn) => {
    if (confirm(`Setujui credit note ${cn.credit_note_number} sebesar ${formatCurrency(cn.amount)}?`)) {
        router.post(`/admin/credit-notes/${cn.id}/approve`)
    }
}

const showRejectModal = ref(false)
const rejectTarget = ref(null)
const rejectReason = ref('')

const openReject = (cn) => {
    rejectTarget.value = cn
    rejectReason.value = ''
    showRejectModal.value = true
}

const submitReject = () => {
    router.post(`/admin/credit-notes/${rejectTarget.value.id}/reject`, { reason: rejectReason.value }, {
        onSuccess: () => { showRejectModal.value = false },
    })
}
</script>

<template>
    <Head title="Credit Notes" />
    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Credit Notes & Refund</h1>
                <Link href="/admin/credit-notes/create" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                    + Buat Credit Note
                </Link>
            </div>
        </template>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Menunggu Persetujuan</p>
                <p class="text-2xl font-bold text-yellow-600">{{ stats.pending }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Disetujui</p>
                <p class="text-2xl font-bold text-green-600">{{ stats.approved }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-500">Total Disetujui</p>
                <p class="text-2xl font-bold">{{ formatCurrency(stats.total_approved_amount) }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-wrap gap-4">
                <input v-model="search" @keyup.enter="applyFilters" placeholder="Cari..." class="px-4 py-2 border rounded-lg text-sm w-64" />
                <select v-model="statusFilter" @change="applyFilters" class="px-4 py-2 border rounded-lg text-sm">
                    <option value="">Semua Status</option>
                    <option value="pending">Menunggu</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">No.</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Pelanggan</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Tipe</th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">Jumlah</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Alasan</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">Tanggal</th>
                        <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="cn in creditNotes.data" :key="cn.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-mono">{{ cn.credit_note_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ cn.customer?.name }}</td>
                        <td class="px-4 py-3 text-sm">{{ typeLabel(cn.type) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-semibold">{{ formatCurrency(cn.amount) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">{{ cn.reason }}</td>
                        <td class="px-4 py-3">
                            <span :class="['px-2 py-1 text-xs rounded-full', statusClass(cn.status)]">{{ statusLabel(cn.status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(cn.created_at) }}</td>
                        <td class="px-4 py-3 text-center">
                            <div v-if="cn.status === 'pending'" class="flex justify-center gap-2">
                                <button @click="approve(cn)" class="px-3 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200">Setujui</button>
                                <button @click="openReject(cn)" class="px-3 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">Tolak</button>
                            </div>
                            <span v-else class="text-xs text-gray-400">-</span>
                        </td>
                    </tr>
                    <tr v-if="!creditNotes.data?.length">
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">Belum ada credit note</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showRejectModal = false">
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Tolak Credit Note</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Penolakan *</label>
                        <textarea v-model="rejectReason" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showRejectModal = false" class="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Batal</button>
                        <button @click="submitReject" :disabled="!rejectReason" class="flex-1 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50">Tolak</button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

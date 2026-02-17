<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import debounce from 'lodash/debounce'

const props = defineProps({
    customers: Object,
    filters: Object,
    areas: Array,
    packages: Array,
    collectors: Array,
})

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const areaFilter = ref(props.filters.area_id || '')
const packageFilter = ref(props.filters.package_id || '')
const collectorFilter = ref(props.filters.collector_id || '')
const perPage = ref(props.filters.per_page || 15)

// Import modal
const showImportModal = ref(false)
const importForm = useForm({
    file: null,
})
const fileInput = ref(null)

const openImportModal = () => {
    showImportModal.value = true
    importForm.reset()
}

const closeImportModal = () => {
    showImportModal.value = false
    importForm.reset()
}

const handleFileChange = (e) => {
    importForm.file = e.target.files[0]
}

const submitImport = () => {
    importForm.post('/admin/customers-import', {
        forceFormData: true,
        onSuccess: () => {
            closeImportModal()
        },
    })
}

const downloadTemplate = () => {
    window.location.href = '/admin/customers-import-template'
}

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

// Apply filters
const applyFilters = debounce(() => {
    router.get('/admin/customers', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        area_id: areaFilter.value || undefined,
        package_id: packageFilter.value || undefined,
        collector_id: collectorFilter.value || undefined,
        per_page: perPage.value != 15 ? perPage.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}, 300)

// Watch for filter changes
watch([search, statusFilter, areaFilter, packageFilter, collectorFilter, perPage], applyFilters)

// Status badge classes
const statusClass = (status) => {
    const classes = {
        active: 'bg-green-100 text-green-700',
        isolated: 'bg-red-100 text-red-700',
        suspended: 'bg-yellow-100 text-yellow-700',
        terminated: 'bg-gray-100 text-gray-700',
    }
    return classes[status] || 'bg-gray-100 text-gray-700'
}

// Status label
const statusLabel = (status) => {
    const labels = {
        active: 'Aktif',
        isolated: 'Isolir',
        suspended: 'Suspend',
        terminated: 'Terminated',
    }
    return labels[status] || status
}

// Delete customer
const deleteCustomer = (customer) => {
    if (confirm(`Yakin ingin menghapus pelanggan ${customer.name}?`)) {
        router.delete(`/admin/customers/${customer.id}`)
    }
}
</script>

<template>
    <Head title="Pelanggan" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Pelanggan</h1>
                <div class="flex items-center gap-2">
                    <button
                        @click="openImportModal"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Import Excel
                    </button>
                    <Link
                        href="/admin/customers/create"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah Pelanggan
                    </Link>
                </div>
            </div>
        </template>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari nama, ID, telepon..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                <select
                    v-model="statusFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Status</option>
                    <option value="active">Aktif</option>
                    <option value="isolated">Isolir</option>
                    <option value="suspended">Suspend</option>
                    <option value="terminated">Terminated</option>
                </select>
                <select
                    v-model="areaFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Area</option>
                    <option v-for="area in areas" :key="area.id" :value="area.id">
                        {{ area.name }}
                    </option>
                </select>
                <select
                    v-model="packageFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Paket</option>
                    <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">
                        {{ pkg.name }}
                    </option>
                </select>
                <select
                    v-model="collectorFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Penagih</option>
                    <option v-for="col in collectors" :key="col.id" :value="col.id">
                        {{ col.name }}
                    </option>
                </select>
                <select
                    v-model="perPage"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option :value="10">10 per halaman</option>
                    <option :value="15">15 per halaman</option>
                    <option :value="25">25 per halaman</option>
                    <option :value="50">50 per halaman</option>
                    <option :value="100">100 per halaman</option>
                    <option value="all">Semua</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pelanggan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paket</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hutang</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/admin/customers/${customer.id}`"
                                    class="text-blue-600 hover:underline font-medium"
                                >
                                    {{ customer.customer_id }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">
                                <div>
                                    <p class="font-medium text-gray-900">{{ customer.name }}</p>
                                    <p class="text-sm text-gray-500">{{ customer.phone }}</p>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-sm">{{ customer.package?.name || '-' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-sm">{{ customer.area?.name || '-' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="customer.total_debt > 0 ? 'text-red-600 font-semibold' : 'text-gray-500'">
                                    {{ formatCurrency(customer.total_debt) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['px-2 py-1 text-xs rounded-full', statusClass(customer.status)]">
                                    {{ statusLabel(customer.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="`/admin/customers/${customer.id}`"
                                        class="p-1 text-gray-500 hover:text-blue-600"
                                        title="Lihat"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>
                                    <Link
                                        :href="`/admin/customers/${customer.id}/edit`"
                                        class="p-1 text-gray-500 hover:text-yellow-600"
                                        title="Edit"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </Link>
                                    <button
                                        @click="deleteCustomer(customer)"
                                        class="p-1 text-gray-500 hover:text-red-600"
                                        title="Hapus"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="!customers.data?.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <p class="mt-4 text-gray-500">Tidak ada pelanggan ditemukan</p>
            </div>

            <!-- Pagination -->
            <div v-if="customers.data?.length" class="px-4 py-3 border-t flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Menampilkan {{ customers.from }} - {{ customers.to }} dari {{ customers.total }} pelanggan
                </p>
                <div class="flex gap-2">
                    <Link
                        v-for="link in customers.links"
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

        <!-- Import Modal -->
        <Teleport to="body">
            <div v-if="showImportModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-black opacity-50" @click="closeImportModal"></div>

                    <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Import Pelanggan dari Excel</h3>
                            <button @click="closeImportModal" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <!-- Download Template -->
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-start gap-3">
                                    <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <p class="text-sm text-blue-800">Download template Excel terlebih dahulu untuk memastikan format data yang benar.</p>
                                        <button
                                            @click="downloadTemplate"
                                            class="mt-2 text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            Download Template
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- File Upload -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">File Excel</label>
                                <div
                                    class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors"
                                    :class="{ 'border-blue-500 bg-blue-50': importForm.file }"
                                >
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        accept=".xlsx,.xls,.csv"
                                        @change="handleFileChange"
                                        class="hidden"
                                    >
                                    <div v-if="!importForm.file">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-600">
                                            <button type="button" @click="fileInput.click()" class="text-blue-600 hover:text-blue-800 font-medium">
                                                Pilih file
                                            </button>
                                            atau drag & drop
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500">XLSX, XLS, CSV (Max 10MB)</p>
                                    </div>
                                    <div v-else class="flex items-center justify-center gap-3">
                                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <div class="text-left">
                                            <p class="text-sm font-medium text-gray-900">{{ importForm.file.name }}</p>
                                            <p class="text-xs text-gray-500">{{ (importForm.file.size / 1024).toFixed(1) }} KB</p>
                                        </div>
                                        <button type="button" @click="fileInput.click()" class="text-blue-600 hover:text-blue-800 text-sm">
                                            Ganti
                                        </button>
                                    </div>
                                </div>
                                <p v-if="importForm.errors.file" class="mt-1 text-sm text-red-600">{{ importForm.errors.file }}</p>
                            </div>

                            <!-- Notes -->
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-800">
                                <p class="font-medium mb-1">Catatan:</p>
                                <ul class="list-disc list-inside space-y-1 text-xs">
                                    <li>Nama <strong>Paket</strong>, <strong>Area</strong>, <strong>Router</strong>, dan <strong>Penagih</strong> harus sesuai dengan data yang ada di sistem</li>
                                    <li>Kolom <strong>id_pelanggan</strong> boleh dikosongkan (akan di-generate otomatis)</li>
                                    <li>Format tanggal: <strong>YYYY-MM-DD</strong> (contoh: 2024-01-15)</li>
                                    <li>Pelanggan dengan ID yang sudah ada akan di-skip</li>
                                </ul>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6">
                            <button
                                type="button"
                                @click="closeImportModal"
                                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                            >
                                Batal
                            </button>
                            <button
                                @click="submitImport"
                                :disabled="!importForm.file || importForm.processing"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center gap-2"
                            >
                                <svg v-if="importForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ importForm.processing ? 'Mengimport...' : 'Import' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

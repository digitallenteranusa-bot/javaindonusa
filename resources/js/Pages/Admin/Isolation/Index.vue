<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import debounce from 'lodash/debounce'

const props = defineProps({
    customers: Object,
    filters: Object,
    areas: Array,
    packages: Array,
    activeCustomers: Array,
})

const search = ref(props.filters.search || '')
const areaFilter = ref(props.filters.area_id || '')
const packageFilter = ref(props.filters.package_id || '')
const perPage = ref(props.filters.per_page === 'all' ? 'all' : Number(props.filters.per_page) || 15)

// Reopen confirmation
const showReopenModal = ref(false)
const selectedCustomer = ref(null)
const reopenLoading = ref(false)

const openReopenModal = (customer) => {
    selectedCustomer.value = customer
    showReopenModal.value = true
}

const closeReopenModal = () => {
    showReopenModal.value = false
    selectedCustomer.value = null
}

const confirmReopen = () => {
    if (!selectedCustomer.value) return
    reopenLoading.value = true
    router.post(`/admin/isolation/${selectedCustomer.value.id}/reopen`, {}, {
        onFinish: () => {
            reopenLoading.value = false
            closeReopenModal()
        },
    })
}

// Isolate manual
const showIsolateModal = ref(false)
const isolateSearch = ref('')
const showIsolateDropdown = ref(false)
const isolateCustomer = ref(null)
const isolateReason = ref('')
const isolateLoading = ref(false)

const filteredActiveCustomers = computed(() => {
    if (!isolateSearch.value) return props.activeCustomers?.slice(0, 10) || []
    const s = isolateSearch.value.toLowerCase()
    return props.activeCustomers?.filter(c =>
        c.name.toLowerCase().includes(s) ||
        c.customer_id.toLowerCase().includes(s) ||
        c.phone?.includes(s)
    ).slice(0, 10) || []
})

const selectIsolateCustomer = (customer) => {
    isolateCustomer.value = customer
    isolateSearch.value = `${customer.customer_id} - ${customer.name}`
    showIsolateDropdown.value = false
}

const openIsolateModal = () => {
    isolateSearch.value = ''
    isolateCustomer.value = null
    isolateReason.value = ''
    showIsolateModal.value = true
}

const closeIsolateModal = () => {
    showIsolateModal.value = false
    isolateCustomer.value = null
    isolateSearch.value = ''
    isolateReason.value = ''
}

const confirmIsolate = () => {
    if (!isolateCustomer.value || !isolateReason.value) return
    isolateLoading.value = true
    router.post(`/admin/isolation/${isolateCustomer.value.id}/isolate`, {
        reason: isolateReason.value,
    }, {
        onFinish: () => {
            isolateLoading.value = false
            closeIsolateModal()
        },
    })
}

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

// Apply filters
const applyFilters = debounce(() => {
    router.get('/admin/isolation', {
        search: search.value || undefined,
        area_id: areaFilter.value || undefined,
        package_id: packageFilter.value || undefined,
        per_page: perPage.value != 15 ? perPage.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}, 300)

// Watch for filter changes
watch([search, areaFilter, packageFilter, perPage], applyFilters)
</script>

<template>
    <Head title="Pelanggan Isolir" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Pelanggan Isolir</h1>
                    <p class="text-sm text-gray-500 mt-1">Total: {{ customers.total }} pelanggan terisolir</p>
                </div>
                <button
                    @click="openIsolateModal"
                    class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700"
                >
                    Isolir Manual
                </button>
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
            </div>
            <div class="flex items-center justify-between mt-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500">Per halaman:</span>
                    <select
                        v-model="perPage"
                        class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                    >
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                        <option value="all">Semua</option>
                    </select>
                </div>
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Hutang</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alasan Isolir</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tgl Isolir</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/admin/customers/${customer.id}`"
                                    class="text-blue-600 hover:text-blue-800 font-medium text-sm"
                                >
                                    {{ customer.customer_id }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ customer.name }}</div>
                                <div class="text-xs text-gray-500">{{ customer.phone || '-' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ customer.package?.name || '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ customer.area?.name || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-sm font-semibold text-red-600">
                                    {{ formatCurrency(customer.total_debt) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">
                                {{ customer.isolation_reason || '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                {{ formatDate(customer.isolation_date) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="openReopenModal(customer)"
                                        class="px-3 py-1.5 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700 whitespace-nowrap"
                                        title="Buka Isolir"
                                    >
                                        Buka Isolir
                                    </button>
                                    <Link
                                        :href="`/admin/customers/${customer.id}`"
                                        class="px-3 py-1.5 bg-blue-50 text-blue-600 text-xs rounded-lg hover:bg-blue-100 whitespace-nowrap"
                                    >
                                        Detail
                                    </Link>
                                    <a
                                        v-if="customer.phone"
                                        :href="`https://wa.me/62${customer.phone?.replace(/^0/, '')}`"
                                        target="_blank"
                                        class="px-3 py-1.5 bg-emerald-50 text-emerald-600 text-xs rounded-lg hover:bg-emerald-100 whitespace-nowrap"
                                        title="WhatsApp"
                                    >
                                        WA
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="!customers.data?.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Tidak ada pelanggan isolir</h3>
                <p class="mt-1 text-sm text-gray-500">Semua pelanggan dalam status aktif.</p>
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
                            'px-3 py-1 text-sm rounded-lg',
                            link.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100',
                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                        v-html="link.label"
                        :preserve-state="true"
                    />
                </div>
            </div>
        </div>

        <!-- Reopen Confirmation Modal -->
        <div v-if="showReopenModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="fixed inset-0 bg-black bg-opacity-50" @click="closeReopenModal"></div>
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md relative z-10">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Konfirmasi Buka Isolir</h3>
                <p class="text-sm text-gray-600 mb-1">
                    Apakah Anda yakin ingin membuka isolir untuk pelanggan:
                </p>
                <div class="bg-gray-50 rounded-lg p-3 mb-4">
                    <p class="font-semibold text-gray-900">{{ selectedCustomer?.name }}</p>
                    <p class="text-sm text-gray-500">{{ selectedCustomer?.customer_id }}</p>
                    <p class="text-sm text-red-600 font-medium mt-1">
                        Hutang: {{ formatCurrency(selectedCustomer?.total_debt) }}
                    </p>
                </div>
                <div class="flex justify-end gap-3">
                    <button
                        @click="closeReopenModal"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        :disabled="reopenLoading"
                    >
                        Batal
                    </button>
                    <button
                        @click="confirmReopen"
                        class="px-4 py-2 text-sm text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50"
                        :disabled="reopenLoading"
                    >
                        {{ reopenLoading ? 'Memproses...' : 'Ya, Buka Isolir' }}
                    </button>
                </div>
            </div>
        </div>
        <!-- Isolate Manual Modal -->
        <div v-if="showIsolateModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="fixed inset-0 bg-black bg-opacity-50" @click="closeIsolateModal"></div>
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md relative z-10">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Isolir Manual</h3>

                <!-- Customer Search -->
                <div class="mb-4 relative">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Pelanggan *</label>
                    <input
                        v-model="isolateSearch"
                        type="text"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                        placeholder="Ketik nama, ID, atau telepon..."
                        @focus="showIsolateDropdown = true"
                        @blur="setTimeout(() => showIsolateDropdown = false, 200)"
                    >
                    <div
                        v-if="showIsolateDropdown && filteredActiveCustomers.length"
                        class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto"
                    >
                        <button
                            v-for="c in filteredActiveCustomers"
                            :key="c.id"
                            type="button"
                            class="w-full text-left px-4 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-0"
                            @click="selectIsolateCustomer(c)"
                        >
                            <div class="text-sm font-medium text-gray-900">{{ c.customer_id }} - {{ c.name }}</div>
                            <div class="text-xs text-gray-500">{{ c.package?.name || '-' }} | {{ c.phone || '-' }}</div>
                        </button>
                    </div>
                    <div
                        v-if="showIsolateDropdown && isolateSearch && !filteredActiveCustomers.length"
                        class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg p-3 text-sm text-gray-500 text-center"
                    >
                        Tidak ditemukan pelanggan aktif
                    </div>
                </div>

                <!-- Selected Customer Info -->
                <div v-if="isolateCustomer" class="bg-gray-50 rounded-lg p-3 mb-4">
                    <p class="font-semibold text-gray-900">{{ isolateCustomer.name }}</p>
                    <p class="text-sm text-gray-500">{{ isolateCustomer.customer_id }} | {{ isolateCustomer.package?.name || '-' }}</p>
                </div>

                <!-- Reason -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Isolir *</label>
                    <input
                        v-model="isolateReason"
                        type="text"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                        placeholder="Contoh: Tunggakan 3 bulan, permintaan pelanggan..."
                        maxlength="255"
                    >
                </div>

                <div class="flex justify-end gap-3">
                    <button
                        @click="closeIsolateModal"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        :disabled="isolateLoading"
                    >
                        Batal
                    </button>
                    <button
                        @click="confirmIsolate"
                        class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
                        :disabled="isolateLoading || !isolateCustomer || !isolateReason"
                    >
                        {{ isolateLoading ? 'Memproses...' : 'Ya, Isolir' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

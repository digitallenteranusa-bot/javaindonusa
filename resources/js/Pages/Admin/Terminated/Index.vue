<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import SkeletonLoader from '@/Components/SkeletonLoader.vue'
import debounce from 'lodash/debounce'

const props = defineProps({
    customers: Object,
    filters: Object,
    areas: Array,
    packages: Array,
})

const search = ref(props.filters.search || '')
const areaFilter = ref(props.filters.area_id || '')
const packageFilter = ref(props.filters.package_id || '')
const perPage = ref(props.filters.per_page === 'all' ? 'all' : Number(props.filters.per_page) || 15)

// Reactivate
const showReactivateModal = ref(false)
const selectedCustomer = ref(null)
const reactivateLoading = ref(false)

const openReactivateModal = (customer) => {
    selectedCustomer.value = customer
    showReactivateModal.value = true
}

const closeReactivateModal = () => {
    showReactivateModal.value = false
    selectedCustomer.value = null
}

const confirmReactivate = () => {
    if (!selectedCustomer.value) return
    reactivateLoading.value = true
    router.post(`/admin/terminated/${selectedCustomer.value.id}/reactivate`, {}, {
        onFinish: () => {
            reactivateLoading.value = false
            closeReactivateModal()
        },
    })
}

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}

const applyFilters = debounce(() => {
    router.get('/admin/terminated', {
        search: search.value || undefined,
        area_id: areaFilter.value || undefined,
        package_id: packageFilter.value || undefined,
        per_page: perPage.value != 15 ? perPage.value : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}, 300)

watch([search, areaFilter, packageFilter, perPage], applyFilters)
</script>

<template>
    <Head title="Pelanggan Terminated" />

    <AdminLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Pelanggan Terminated</h1>
                <p class="text-sm text-gray-500 mt-1">Total: {{ customers.total }} pelanggan berhenti</p>
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
            <div class="flex items-center mt-4">
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
        <SkeletonLoader v-if="!customers?.data" type="table" :rows="5" :columns="5" />
        <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pelanggan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paket</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sisa Hutang</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tgl Terminated</th>
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
                                <span :class="['text-sm font-semibold', customer.total_debt > 0 ? 'text-red-600' : 'text-gray-500']">
                                    {{ formatCurrency(customer.total_debt) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                {{ formatDate(customer.updated_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="openReactivateModal(customer)"
                                        class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700 whitespace-nowrap"
                                    >
                                        Aktifkan Ulang
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Tidak ada pelanggan terminated</h3>
                <p class="mt-1 text-sm text-gray-500">Belum ada pelanggan yang diterminasi.</p>
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

        <!-- Reactivate Confirmation Modal -->
        <div v-if="showReactivateModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="fixed inset-0 bg-black bg-opacity-50" @click="closeReactivateModal"></div>
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md relative z-10">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Aktifkan Ulang Pelanggan</h3>
                <p class="text-sm text-gray-600 mb-1">
                    Apakah Anda yakin ingin mengaktifkan ulang pelanggan:
                </p>
                <div class="bg-gray-50 rounded-lg p-3 mb-4">
                    <p class="font-semibold text-gray-900">{{ selectedCustomer?.name }}</p>
                    <p class="text-sm text-gray-500">{{ selectedCustomer?.customer_id }}</p>
                    <p v-if="selectedCustomer?.total_debt > 0" class="text-sm text-red-600 font-medium mt-1">
                        Sisa hutang: {{ formatCurrency(selectedCustomer?.total_debt) }}
                    </p>
                </div>
                <div class="flex justify-end gap-3">
                    <button
                        @click="closeReactivateModal"
                        class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        :disabled="reactivateLoading"
                    >
                        Batal
                    </button>
                    <button
                        @click="confirmReactivate"
                        class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        :disabled="reactivateLoading"
                    >
                        {{ reactivateLoading ? 'Memproses...' : 'Ya, Aktifkan' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

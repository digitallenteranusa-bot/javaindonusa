<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    packages: Object,
    filters: Object,
})

const search = ref(props.filters.search || '')

// Format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value || 0)
}

// Format speed
const formatSpeed = (kbps) => {
    if (kbps >= 1024) {
        return (kbps / 1024) + ' Mbps'
    }
    return kbps + ' Kbps'
}

// Apply search
const applySearch = () => {
    router.get('/admin/packages', {
        search: search.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Toggle active
const toggleActive = (pkg) => {
    router.post(`/admin/packages/${pkg.id}/toggle-active`)
}

// Delete package
const deletePackage = (pkg) => {
    if (confirm(`Yakin ingin menghapus paket ${pkg.name}?`)) {
        router.delete(`/admin/packages/${pkg.id}`)
    }
}
</script>

<template>
    <Head title="Paket" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Paket Internet</h1>
                <Link
                    href="/admin/packages/create"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Paket
                </Link>
            </div>
        </template>

        <!-- Search -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <input
                v-model="search"
                type="text"
                placeholder="Cari paket..."
                class="w-full md:w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                @keyup.enter="applySearch"
            >
        </div>

        <!-- Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="pkg in packages.data"
                :key="pkg.id"
                class="bg-white rounded-xl shadow-sm p-6 relative"
            >
                <!-- Status Badge -->
                <span
                    :class="[
                        'absolute top-4 right-4 px-2 py-1 text-xs rounded-full',
                        pkg.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                    ]"
                >
                    {{ pkg.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>

                <div class="mb-4">
                    <h3 class="text-lg font-bold text-gray-900">{{ pkg.name }}</h3>
                    <p class="text-sm text-gray-500">{{ pkg.code }}</p>
                </div>

                <div class="text-3xl font-bold text-blue-600 mb-4">
                    {{ formatCurrency(pkg.price) }}
                    <span class="text-sm font-normal text-gray-500">/bulan</span>
                </div>

                <div class="space-y-2 text-sm text-gray-600 mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Download: {{ formatSpeed(pkg.speed_download) }}
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Upload: {{ formatSpeed(pkg.speed_upload) }}
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ pkg.customers_count || 0 }} pelanggan
                    </div>
                </div>

                <p v-if="pkg.description" class="text-sm text-gray-500 mb-4">
                    {{ pkg.description }}
                </p>

                <div class="flex gap-2 pt-4 border-t">
                    <Link
                        :href="`/admin/packages/${pkg.id}/edit`"
                        class="flex-1 py-2 text-center bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm"
                    >
                        Edit
                    </Link>
                    <button
                        @click="toggleActive(pkg)"
                        :class="[
                            'flex-1 py-2 rounded-lg text-sm',
                            pkg.is_active
                                ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200'
                                : 'bg-green-100 text-green-700 hover:bg-green-200'
                        ]"
                    >
                        {{ pkg.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                    <button
                        @click="deletePackage(pkg)"
                        class="px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-sm"
                        :disabled="pkg.customers_count > 0"
                        :class="{ 'opacity-50 cursor-not-allowed': pkg.customers_count > 0 }"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!packages.data?.length" class="bg-white rounded-xl shadow-sm p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <p class="mt-4 text-gray-500">Belum ada paket</p>
            <Link
                href="/admin/packages/create"
                class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
                Tambah Paket Pertama
            </Link>
        </div>

        <!-- Pagination -->
        <div v-if="packages.data?.length && packages.last_page > 1" class="mt-6 flex justify-center">
            <div class="flex gap-2">
                <Link
                    v-for="link in packages.links"
                    :key="link.label"
                    :href="link.url"
                    :class="[
                        'px-3 py-1 text-sm rounded',
                        link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100',
                        !link.url ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AdminLayout>
</template>

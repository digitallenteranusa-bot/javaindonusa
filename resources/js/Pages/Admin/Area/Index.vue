<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    areas: Object,
    filters: Object,
})

const search = ref(props.filters.search || '')

// Apply filters
const applyFilters = () => {
    router.get('/admin/areas', {
        search: search.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Toggle active
const toggleActive = (area) => {
    router.post(`/admin/areas/${area.id}/toggle-active`)
}

// Delete area
const deleteArea = (area) => {
    if (confirm(`Yakin ingin menghapus area ${area.name}?`)) {
        router.delete(`/admin/areas/${area.id}`)
    }
}
</script>

<template>
    <Head title="Area" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Area / Wilayah</h1>
                <Link
                    href="/admin/areas/create"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Area
                </Link>
            </div>
        </template>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <input
                v-model="search"
                type="text"
                placeholder="Cari area..."
                class="w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                @keyup.enter="applyFilters"
            >
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kode</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Area</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penagih</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pelanggan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="area in areas.data" :key="area.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-sm">{{ area.code }}</td>
                        <td class="px-4 py-3">
                            <div>
                                <p class="font-medium">{{ area.name }}</p>
                                <p v-if="area.parent" class="text-xs text-gray-500">
                                    Parent: {{ area.parent.name }}
                                </p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm">{{ area.collector?.name || '-' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">
                                {{ area.customers_count }} pelanggan
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'px-2 py-1 text-xs rounded-full',
                                    area.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                                ]"
                            >
                                {{ area.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <Link
                                    :href="`/admin/areas/${area.id}/edit`"
                                    class="p-1 text-gray-500 hover:text-yellow-600"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button
                                    @click="toggleActive(area)"
                                    class="p-1 text-gray-500 hover:text-blue-600"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                </button>
                                <button
                                    @click="deleteArea(area)"
                                    class="p-1 text-gray-500 hover:text-red-600"
                                    :disabled="area.customers_count > 0"
                                    :class="{ 'opacity-50 cursor-not-allowed': area.customers_count > 0 }"
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

            <div v-if="!areas.data?.length" class="text-center py-12 text-gray-500">
                Tidak ada area ditemukan
            </div>

            <!-- Pagination -->
            <div v-if="areas.data?.length" class="px-4 py-3 border-t flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Menampilkan {{ areas.from }} - {{ areas.to }} dari {{ areas.total }}
                </p>
                <div class="flex gap-2">
                    <Link
                        v-for="link in areas.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-1 text-sm rounded',
                            link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700',
                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

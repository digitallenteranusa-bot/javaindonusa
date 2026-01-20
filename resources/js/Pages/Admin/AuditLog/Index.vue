<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    logs: Object,
    filters: Object,
    stats: Object,
    modules: Object,
    actions: Object,
    admins: Array,
})

const search = ref(props.filters.search || '')
const moduleFilter = ref(props.filters.module || '')
const actionFilter = ref(props.filters.action || '')
const adminFilter = ref(props.filters.admin_id || '')
const startDate = ref(props.filters.start_date || '')
const endDate = ref(props.filters.end_date || '')

// Apply filters
const applyFilters = () => {
    router.get('/admin/audit-logs', {
        search: search.value || undefined,
        module: moduleFilter.value || undefined,
        action: actionFilter.value || undefined,
        admin_id: adminFilter.value || undefined,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Watch filter changes
watch([moduleFilter, actionFilter, adminFilter], applyFilters)

// Apply date filter
const applyDateFilter = () => {
    applyFilters()
}

// Reset filters
const resetFilters = () => {
    search.value = ''
    moduleFilter.value = ''
    actionFilter.value = ''
    adminFilter.value = ''
    startDate.value = ''
    endDate.value = ''
    router.get('/admin/audit-logs')
}

// Export
const exportLogs = () => {
    const params = new URLSearchParams({
        ...(search.value && { search: search.value }),
        ...(moduleFilter.value && { module: moduleFilter.value }),
        ...(actionFilter.value && { action: actionFilter.value }),
        ...(adminFilter.value && { admin_id: adminFilter.value }),
        ...(startDate.value && { start_date: startDate.value }),
        ...(endDate.value && { end_date: endDate.value }),
    })
    window.location.href = `/admin/audit-logs/export?${params.toString()}`
}

// Badge class by action color
const actionBadgeClass = (color) => {
    const classes = {
        green: 'bg-green-100 text-green-700',
        blue: 'bg-blue-100 text-blue-700',
        red: 'bg-red-100 text-red-700',
        yellow: 'bg-yellow-100 text-yellow-700',
        orange: 'bg-orange-100 text-orange-700',
        purple: 'bg-purple-100 text-purple-700',
        gray: 'bg-gray-100 text-gray-700',
    }
    return classes[color] || classes.gray
}
</script>

<template>
    <Head title="Audit Log" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Audit Log</h1>
                <button
                    @click="exportLogs"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export Excel
                </button>
            </div>
        </template>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Total Log</p>
                <p class="text-2xl font-bold">{{ stats?.total || 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Login</p>
                <p class="text-2xl font-bold text-green-600">{{ stats?.by_action?.login || 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Create</p>
                <p class="text-2xl font-bold text-blue-600">{{ stats?.by_action?.create || 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-gray-500 text-xs">Update</p>
                <p class="text-2xl font-bold text-yellow-600">{{ stats?.by_action?.update || 0 }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div class="md:col-span-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari deskripsi..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        @keyup.enter="applyFilters"
                    >
                </div>
                <select
                    v-model="moduleFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Module</option>
                    <option v-for="(label, key) in modules" :key="key" :value="key">{{ label }}</option>
                </select>
                <select
                    v-model="actionFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Aksi</option>
                    <option v-for="(label, key) in actions" :key="key" :value="key">{{ label }}</option>
                </select>
                <select
                    v-model="adminFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Semua Admin</option>
                    <option v-for="admin in admins" :key="admin.id" :value="admin.id">{{ admin.name }}</option>
                </select>
                <button
                    @click="resetFilters"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                >
                    Reset
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mt-4">
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Tanggal Mulai</label>
                    <input
                        v-model="startDate"
                        type="date"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        @change="applyDateFilter"
                    >
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Tanggal Akhir</label>
                    <input
                        v-model="endDate"
                        type="date"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        @change="applyDateFilter"
                    >
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admin</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Module</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-900">{{ log.created_at }}</p>
                                <p class="text-xs text-gray-500">{{ log.created_at_human }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                        <span class="text-xs text-gray-600 font-medium">
                                            {{ log.admin?.name?.charAt(0).toUpperCase() || 'S' }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ log.admin?.name || 'System' }}</p>
                                        <p class="text-xs text-gray-500 capitalize">{{ log.admin?.role || '-' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">
                                    {{ log.module_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['px-2 py-1 text-xs rounded-full', actionBadgeClass(log.action_color)]">
                                    {{ log.action_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-gray-700 max-w-xs truncate" :title="log.description">
                                    {{ log.description }}
                                </p>
                                <p v-if="log.auditable_type" class="text-xs text-gray-500">
                                    {{ log.auditable_type }} #{{ log.auditable_id }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ log.ip_address || '-' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/admin/audit-logs/${log.id}`"
                                    class="p-1 text-gray-500 hover:text-blue-600"
                                    title="Lihat Detail"
                                >
                                    <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="!logs.data?.length" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="mt-4 text-gray-500">Tidak ada log ditemukan</p>
            </div>

            <!-- Pagination -->
            <div v-if="logs.data?.length" class="px-4 py-3 border-t flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Menampilkan {{ logs.from }} - {{ logs.to }} dari {{ logs.total }} log
                </p>
                <div class="flex gap-2">
                    <Link
                        v-for="link in logs.links"
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
    </AdminLayout>
</template>

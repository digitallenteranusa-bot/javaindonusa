<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    log: Object,
    relatedRecord: Object,
})

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

// Format JSON for display
const formatJson = (data) => {
    if (!data) return '-'
    try {
        return JSON.stringify(data, null, 2)
    } catch {
        return String(data)
    }
}

// Get changed fields between old and new values
const getChangedFields = () => {
    if (!props.log.old_values || !props.log.new_values) return []

    const changes = []
    const oldValues = props.log.old_values
    const newValues = props.log.new_values

    // Check for changed fields
    for (const key of Object.keys(newValues)) {
        if (oldValues[key] !== newValues[key]) {
            changes.push({
                field: key,
                old: oldValues[key],
                new: newValues[key],
            })
        }
    }

    return changes
}
</script>

<template>
    <Head title="Detail Audit Log" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/audit-logs" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">Detail Audit Log</h1>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Info -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Info -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Informasi Log</h2>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">ID</label>
                            <p class="font-medium text-gray-900">#{{ log.id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Waktu</label>
                            <p class="font-medium text-gray-900">{{ log.created_at }}</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Module</label>
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">
                                {{ log.module_label }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Aksi</label>
                            <span :class="['px-2 py-1 text-xs rounded-full', actionBadgeClass(log.action_color)]">
                                {{ log.action_label }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Deskripsi</label>
                            <p class="font-medium text-gray-900">{{ log.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Changed Data -->
                <div v-if="log.old_values || log.new_values" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Perubahan Data</h2>

                    <!-- Show changes in table format if both old and new exist -->
                    <div v-if="log.old_values && log.new_values && getChangedFields().length" class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Field</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nilai Lama</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nilai Baru</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="change in getChangedFields()" :key="change.field" class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ change.field }}</td>
                                    <td class="px-4 py-2 text-red-600">
                                        <span class="bg-red-50 px-2 py-1 rounded">{{ change.old ?? '-' }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-green-600">
                                        <span class="bg-green-50 px-2 py-1 rounded">{{ change.new ?? '-' }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Show raw JSON if only one exists or no specific changes -->
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-if="log.old_values">
                            <label class="block text-xs text-gray-500 mb-2">Data Sebelumnya</label>
                            <pre class="bg-red-50 text-red-800 p-4 rounded-lg text-xs overflow-x-auto max-h-96">{{ formatJson(log.old_values) }}</pre>
                        </div>
                        <div v-if="log.new_values">
                            <label class="block text-xs text-gray-500 mb-2">Data Sesudahnya</label>
                            <pre class="bg-green-50 text-green-800 p-4 rounded-lg text-xs overflow-x-auto max-h-96">{{ formatJson(log.new_values) }}</pre>
                        </div>
                    </div>

                    <div v-if="!log.old_values && !log.new_values" class="text-center py-8 text-gray-500">
                        Tidak ada perubahan data yang dicatat
                    </div>
                </div>

                <!-- Metadata -->
                <div v-if="log.metadata" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Metadata Tambahan</h2>
                    <pre class="bg-gray-50 text-gray-800 p-4 rounded-lg text-xs overflow-x-auto">{{ formatJson(log.metadata) }}</pre>
                </div>

                <!-- Related Record -->
                <div v-if="relatedRecord" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Record Terkait</h2>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-500 mb-2">
                            {{ log.auditable_type }} #{{ log.auditable_id }}
                        </p>
                        <pre class="text-xs overflow-x-auto">{{ formatJson(relatedRecord) }}</pre>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Admin Info -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Admin</h2>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-blue-600 font-bold text-lg">
                                {{ log.admin?.name?.charAt(0).toUpperCase() || 'S' }}
                            </span>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">{{ log.admin?.name || 'System' }}</p>
                            <p class="text-sm text-gray-500">{{ log.admin?.email || '-' }}</p>
                        </div>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Role</span>
                            <span class="font-medium capitalize">{{ log.admin?.role || '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Request Info -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Informasi Request</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">IP Address</label>
                            <p class="font-mono text-sm bg-gray-50 px-3 py-2 rounded">{{ log.ip_address || '-' }}</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">User Agent</label>
                            <p class="text-xs text-gray-600 bg-gray-50 px-3 py-2 rounded break-words">{{ log.user_agent || '-' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Model Info -->
                <div v-if="log.auditable_type" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Model Terkait</h2>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Tipe Model</span>
                            <span class="font-medium">{{ log.auditable_type }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">ID</span>
                            <span class="font-medium">#{{ log.auditable_id }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

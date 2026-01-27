<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    roles: Object,
    permissions: Array,
    permissionsGrouped: Object,
    rolePermissions: Object,
    permissionGroups: Object,
})

const activeRole = ref('penagih')
const editedPermissions = ref({})
const saving = ref(false)

// Initialize edited permissions from props
const initEditedPermissions = (role) => {
    editedPermissions.value = {}
    const currentPerms = props.rolePermissions[role] || []
    currentPerms.forEach(perm => {
        editedPermissions.value[perm] = true
    })
}

// Initialize on mount
initEditedPermissions(activeRole.value)

// Switch role tab
const switchRole = (role) => {
    activeRole.value = role
    initEditedPermissions(role)
}

// Toggle permission
const togglePermission = (permName) => {
    if (activeRole.value === 'admin') return // Admin can't be modified
    editedPermissions.value[permName] = !editedPermissions.value[permName]
}

// Check if permission is enabled
const hasPermission = (permName) => {
    if (activeRole.value === 'admin') return true
    return editedPermissions.value[permName] === true
}

// Check if group has any permission enabled
const groupHasAnyPermission = (groupPerms) => {
    return groupPerms.some(p => hasPermission(p.name))
}

// Toggle all permissions in a group
const toggleGroup = (groupPerms) => {
    if (activeRole.value === 'admin') return

    const allEnabled = groupPerms.every(p => hasPermission(p.name))
    groupPerms.forEach(p => {
        editedPermissions.value[p.name] = !allEnabled
    })
}

// Check if all group permissions are enabled
const groupAllEnabled = (groupPerms) => {
    return groupPerms.every(p => hasPermission(p.name))
}

// Save permissions
const savePermissions = () => {
    if (activeRole.value === 'admin') return

    saving.value = true
    const permissions = Object.keys(editedPermissions.value).filter(k => editedPermissions.value[k])

    router.put(`/admin/roles/${activeRole.value}`, {
        permissions: permissions,
    }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            saving.value = false
        },
    })
}

// Reset to default
const resetToDefault = () => {
    if (activeRole.value === 'admin') return

    if (confirm(`Reset permissions untuk ${props.roles[activeRole.value]} ke default?`)) {
        router.post(`/admin/roles/${activeRole.value}/reset`, {}, {
            preserveState: true,
            preserveScroll: true,
        })
    }
}

// Count permissions
const countPermissions = (role) => {
    if (role === 'admin') return props.permissions?.length || 0
    return props.rolePermissions[role]?.length || 0
}

// Get group label
const getGroupLabel = (group) => {
    return props.permissionGroups[group] || group
}
</script>

<template>
    <Head title="Pengaturan Role & Permission" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Pengaturan Role & Permission</h1>
        </template>

        <div class="space-y-6">
            <!-- Role Tabs -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px overflow-x-auto">
                        <button
                            v-for="(label, role) in roles"
                            :key="role"
                            @click="switchRole(role)"
                            :class="[
                                'px-6 py-4 text-sm font-medium border-b-2 whitespace-nowrap transition-colors',
                                activeRole === role
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                        >
                            {{ label }}
                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full"
                                :class="activeRole === role ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ countPermissions(role) }}
                            </span>
                        </button>
                    </nav>
                </div>

                <!-- Role Info -->
                <div class="p-4 bg-gray-50 border-b">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ roles[activeRole] }}</h3>
                            <p class="text-sm text-gray-500 mt-1">
                                <span v-if="activeRole === 'admin'">
                                    Administrator memiliki akses penuh ke semua fitur.
                                </span>
                                <span v-else>
                                    Kelola permission untuk role ini dengan mencentang/menghapus centang pada permission di bawah.
                                </span>
                            </p>
                        </div>
                        <div v-if="activeRole !== 'admin'" class="flex gap-2">
                            <button
                                @click="resetToDefault"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                            >
                                Reset Default
                            </button>
                            <button
                                @click="savePermissions"
                                :disabled="saving"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                <span v-if="saving">Menyimpan...</span>
                                <span v-else>Simpan Perubahan</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Permissions Grid -->
                <div class="p-6">
                    <div v-if="activeRole === 'admin'" class="text-center py-8">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Full Access</h3>
                        <p class="text-gray-500 mt-2">Administrator memiliki akses ke semua fitur sistem.</p>
                    </div>

                    <div v-else class="space-y-6">
                        <div
                            v-for="(groupPerms, group) in permissionsGrouped"
                            :key="group"
                            class="border border-gray-200 rounded-lg overflow-hidden"
                        >
                            <!-- Group Header -->
                            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b">
                                <div class="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        :checked="groupAllEnabled(groupPerms)"
                                        @change="toggleGroup(groupPerms)"
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    >
                                    <span class="font-medium text-gray-900">{{ getGroupLabel(group) }}</span>
                                    <span class="text-sm text-gray-500">
                                        ({{ groupPerms.filter(p => hasPermission(p.name)).length }}/{{ groupPerms.length }})
                                    </span>
                                </div>
                            </div>

                            <!-- Group Permissions -->
                            <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <label
                                    v-for="perm in groupPerms"
                                    :key="perm.id"
                                    class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                    :class="hasPermission(perm.name) ? 'border-blue-200 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="hasPermission(perm.name)"
                                        @change="togglePermission(perm.name)"
                                        class="mt-0.5 w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    >
                                    <div>
                                        <p class="font-medium text-gray-900 text-sm">{{ perm.name }}</p>
                                        <p v-if="perm.description" class="text-xs text-gray-500 mt-0.5">{{ perm.description }}</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Card -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <h4 class="font-medium text-blue-900">Tentang Permission</h4>
                        <ul class="mt-2 text-sm text-blue-700 space-y-1">
                            <li><strong>view</strong> - Melihat data</li>
                            <li><strong>create</strong> - Membuat data baru</li>
                            <li><strong>update</strong> - Mengubah data</li>
                            <li><strong>delete</strong> - Menghapus data</li>
                            <li><strong>manage</strong> - Akses penuh (CRUD)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

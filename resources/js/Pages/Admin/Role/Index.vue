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

const selectedRole = ref('penagih')
const saving = ref(false)

const currentPermissions = ref({ ...props.rolePermissions })

const togglePermission = (role, permissionName) => {
    if (role === 'admin') return // Admin can't be modified

    const perms = currentPermissions.value[role] || []
    const index = perms.indexOf(permissionName)

    if (index > -1) {
        currentPermissions.value[role] = perms.filter(p => p !== permissionName)
    } else {
        currentPermissions.value[role] = [...perms, permissionName]
    }
}

const hasPermission = (role, permissionName) => {
    if (role === 'admin') return true
    return (currentPermissions.value[role] || []).includes(permissionName)
}

const savePermissions = (role) => {
    if (role === 'admin') return

    saving.value = true
    router.put(`/admin/roles/${role}`, {
        permissions: currentPermissions.value[role] || [],
    }, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false
        },
    })
}

const resetToDefault = (role) => {
    if (confirm(`Reset permissions untuk ${props.roles[role]} ke default?`)) {
        router.post(`/admin/roles/${role}/reset`)
    }
}

const selectAllInGroup = (role, group) => {
    if (role === 'admin') return

    const groupPermissions = props.permissionsGrouped[group]?.map(p => p.name) || []
    const currentPerms = new Set(currentPermissions.value[role] || [])

    // Check if all are selected
    const allSelected = groupPermissions.every(p => currentPerms.has(p))

    if (allSelected) {
        // Deselect all
        groupPermissions.forEach(p => currentPerms.delete(p))
    } else {
        // Select all
        groupPermissions.forEach(p => currentPerms.add(p))
    }

    currentPermissions.value[role] = Array.from(currentPerms)
}

const isGroupAllSelected = (role, group) => {
    if (role === 'admin') return true
    const groupPermissions = props.permissionsGrouped[group]?.map(p => p.name) || []
    const currentPerms = currentPermissions.value[role] || []
    return groupPermissions.every(p => currentPerms.includes(p))
}
</script>

<template>
    <Head title="Role & Permissions" />

    <AdminLayout>
        <template #header>
            <h1 class="text-2xl font-bold text-gray-900">Role & Permissions</h1>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Role Selector -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <h3 class="font-semibold mb-4">Pilih Role</h3>
                    <div class="space-y-2">
                        <button
                            v-for="(label, role) in roles"
                            :key="role"
                            @click="selectedRole = role"
                            :class="[
                                'w-full text-left px-4 py-3 rounded-lg transition-colors',
                                selectedRole === role
                                    ? 'bg-blue-50 text-blue-700 border border-blue-200'
                                    : 'hover:bg-gray-50 border border-transparent'
                            ]"
                        >
                            <p class="font-medium">{{ label }}</p>
                            <p class="text-xs text-gray-500">
                                {{ role === 'admin' ? 'Semua akses' : `${(rolePermissions[role] || []).length} permissions` }}
                            </p>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Permissions Grid -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-lg font-semibold">{{ roles[selectedRole] }}</h2>
                            <p class="text-sm text-gray-500">
                                {{ selectedRole === 'admin' ? 'Administrator memiliki semua akses' : 'Pilih permissions yang diizinkan' }}
                            </p>
                        </div>
                        <div v-if="selectedRole !== 'admin'" class="flex gap-2">
                            <button
                                @click="resetToDefault(selectedRole)"
                                class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
                            >
                                Reset Default
                            </button>
                            <button
                                @click="savePermissions(selectedRole)"
                                :disabled="saving"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ saving ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </div>

                    <div v-if="selectedRole === 'admin'" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-blue-800">
                            Administrator memiliki akses penuh ke semua fitur sistem. Permissions tidak dapat diubah.
                        </p>
                    </div>

                    <div v-else class="space-y-6">
                        <div
                            v-for="(groupPerms, group) in permissionsGrouped"
                            :key="group"
                            class="border border-gray-200 rounded-lg overflow-hidden"
                        >
                            <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">
                                <h4 class="font-medium">{{ permissionGroups[group] || group }}</h4>
                                <button
                                    @click="selectAllInGroup(selectedRole, group)"
                                    class="text-sm text-blue-600 hover:text-blue-800"
                                >
                                    {{ isGroupAllSelected(selectedRole, group) ? 'Hapus Semua' : 'Pilih Semua' }}
                                </button>
                            </div>
                            <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                                <label
                                    v-for="permission in groupPerms"
                                    :key="permission.id"
                                    class="flex items-start gap-3 p-2 rounded hover:bg-gray-50 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="hasPermission(selectedRole, permission.name)"
                                        @change="togglePermission(selectedRole, permission.name)"
                                        class="mt-1 rounded text-blue-600"
                                    >
                                    <div>
                                        <p class="font-medium text-sm">{{ permission.name }}</p>
                                        <p v-if="permission.description" class="text-xs text-gray-500">
                                            {{ permission.description }}
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

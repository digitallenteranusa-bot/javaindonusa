<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    user: Object,
    stats: Object,
})

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
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    })
}

const formatDateTime = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('id-ID')
}

// Role badge color
const roleClass = (role) => {
    const classes = {
        admin: 'bg-purple-100 text-purple-700',
        penagih: 'bg-blue-100 text-blue-700',
        teknisi: 'bg-orange-100 text-orange-700',
        cs: 'bg-green-100 text-green-700',
    }
    return classes[role] || 'bg-gray-100 text-gray-700'
}

const roleLabel = (role) => {
    const labels = {
        admin: 'Administrator',
        penagih: 'Penagih/Collector',
        teknisi: 'Teknisi',
        cs: 'Customer Service',
    }
    return labels[role] || role
}

// Toggle active
const toggleActive = () => {
    if (confirm(`${props.user.is_active ? 'Nonaktifkan' : 'Aktifkan'} user ${props.user.name}?`)) {
        router.post(`/admin/users/${props.user.id}/toggle-active`)
    }
}

// Reset password modal
const showResetModal = ref(false)
const newPassword = ref('')

const resetPassword = () => {
    if (!newPassword.value) return
    router.post(`/admin/users/${props.user.id}/reset-password`, {
        password: newPassword.value,
    }, {
        onSuccess: () => {
            showResetModal.value = false
            newPassword.value = ''
        },
    })
}

// Delete user
const deleteUser = () => {
    if (confirm(`Hapus user ${props.user.name}? Aksi ini tidak dapat dibatalkan.`)) {
        router.delete(`/admin/users/${props.user.id}`)
    }
}
</script>

<template>
    <Head :title="`User: ${user.name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/users" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ user.name }}</h1>
                        <p class="text-gray-500">{{ user.email }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        :class="[
                            'px-3 py-1 text-sm rounded-full',
                            user.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                        ]"
                    >
                        {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                    <span :class="['px-3 py-1 text-sm rounded-full', roleClass(user.role)]">
                        {{ roleLabel(user.role) }}
                    </span>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- User Info -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-start gap-6">
                        <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-3xl text-gray-600 font-medium">{{ user.name?.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="flex-1">
                            <h2 class="text-xl font-semibold">{{ user.name }}</h2>
                            <p class="text-gray-500">{{ user.email }}</p>

                            <div class="grid grid-cols-2 gap-4 mt-4">
                                <div>
                                    <p class="text-gray-500 text-sm">Telepon</p>
                                    <p class="font-medium">{{ user.phone || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-sm">Area</p>
                                    <p class="font-medium">{{ user.area?.name || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-sm">Role</p>
                                    <p class="font-medium">{{ roleLabel(user.role) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-sm">Login Terakhir</p>
                                    <p class="font-medium">{{ formatDateTime(user.last_login_at) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Collector Stats (if collector) -->
                <div v-if="user.role === 'penagih' && stats" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Statistik Penagih (Bulan Ini)</h2>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-blue-600">{{ stats.assigned_customers || 0 }}</p>
                            <p class="text-sm text-blue-700">Pelanggan</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 text-center">
                            <p class="text-2xl font-bold text-green-600">{{ formatCurrency(stats.collections_this_month) }}</p>
                            <p class="text-sm text-green-700">Tagihan Masuk</p>
                        </div>
                        <div class="bg-yellow-50 rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-yellow-600">{{ stats.collection_count_this_month || 0 }}</p>
                            <p class="text-sm text-yellow-700">Transaksi</p>
                        </div>
                        <div class="bg-red-50 rounded-lg p-4 text-center">
                            <p class="text-2xl font-bold text-red-600">{{ formatCurrency(stats.expenses_this_month) }}</p>
                            <p class="text-sm text-red-700">Pengeluaran</p>
                        </div>
                    </div>
                </div>

                <!-- Account Info -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Informasi Akun</h2>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between py-2 border-b">
                            <span class="text-gray-500">ID User</span>
                            <span class="font-mono">{{ user.id }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b">
                            <span class="text-gray-500">Dibuat</span>
                            <span>{{ formatDate(user.created_at) }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b">
                            <span class="text-gray-500">Diperbarui</span>
                            <span>{{ formatDate(user.updated_at) }}</span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-gray-500">Login Terakhir</span>
                            <span>{{ formatDateTime(user.last_login_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Aksi</h2>

                    <div class="space-y-2">
                        <Link
                            :href="`/admin/users/${user.id}/edit`"
                            class="w-full py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit User
                        </Link>

                        <button
                            @click="showResetModal = true"
                            class="w-full py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 text-sm"
                        >
                            Reset Password
                        </button>

                        <button
                            @click="toggleActive"
                            :class="[
                                'w-full py-2 rounded-lg text-sm',
                                user.is_active
                                    ? 'bg-red-100 text-red-700 hover:bg-red-200'
                                    : 'bg-green-100 text-green-700 hover:bg-green-200'
                            ]"
                        >
                            {{ user.is_active ? 'Nonaktifkan User' : 'Aktifkan User' }}
                        </button>

                        <button
                            @click="deleteUser"
                            class="w-full py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 text-sm"
                        >
                            Hapus User
                        </button>
                    </div>
                </div>

                <!-- Quick Links (for collector) -->
                <div v-if="user.role === 'penagih'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-4">Lihat Data</h2>

                    <div class="space-y-2">
                        <Link
                            :href="`/admin/customers?collector_id=${user.id}`"
                            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100"
                        >
                            <span class="text-sm">Pelanggan Ditugaskan</span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                        <Link
                            :href="`/admin/payments?collector_id=${user.id}`"
                            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100"
                        >
                            <span class="text-sm">Pembayaran</span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                        <Link
                            :href="`/admin/expenses?collector_id=${user.id}`"
                            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100"
                        >
                            <span class="text-sm">Pengeluaran</span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                        <Link
                            :href="`/admin/settlements?collector_id=${user.id}`"
                            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100"
                        >
                            <span class="text-sm">Setoran</span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reset Password Modal -->
        <div
            v-if="showResetModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showResetModal = false"
        >
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Reset Password</h3>

                <div class="space-y-4">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-800">
                        <p>Password baru akan langsung aktif. Pastikan untuk memberitahu user.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru *</label>
                        <input
                            v-model="newPassword"
                            type="password"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Minimal 8 karakter"
                        >
                    </div>

                    <div class="flex gap-3">
                        <button
                            @click="showResetModal = false"
                            class="flex-1 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                        >
                            Batal
                        </button>
                        <button
                            @click="resetPassword"
                            :disabled="!newPassword || newPassword.length < 8"
                            class="flex-1 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        >
                            Reset Password
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

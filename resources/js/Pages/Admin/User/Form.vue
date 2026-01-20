<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    user: Object,
    areas: Array,
    roles: Object,
})

const isEdit = computed(() => !!props.user)

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    phone: props.user?.phone || '',
    password: '',
    role: props.user?.role || 'penagih',
    area_id: props.user?.area_id || '',
    is_active: props.user?.is_active ?? true,
})

const showPassword = ref(false)

const submit = () => {
    if (isEdit.value) {
        form.put(`/admin/users/${props.user.id}`)
    } else {
        form.post('/admin/users')
    }
}

// Show area field only for collector role
const showAreaField = computed(() => form.role === 'penagih')
</script>

<template>
    <Head :title="isEdit ? 'Edit User' : 'Tambah User'" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/users" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ isEdit ? 'Edit User' : 'Tambah User' }}
                </h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6">
            <!-- Basic Info -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Informasi User</h2>

                <div class="space-y-4">
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.name }"
                            placeholder="Masukkan nama lengkap"
                        >
                        <p v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.email }"
                            placeholder="contoh@email.com"
                        >
                        <p v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</p>
                    </div>

                    <!-- Phone -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon</label>
                        <input
                            v-model="form.phone"
                            type="text"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.phone }"
                            placeholder="08xxxxxxxxxx"
                        >
                        <p v-if="form.errors.phone" class="text-red-500 text-sm mt-1">{{ form.errors.phone }}</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Password {{ isEdit ? '(Kosongkan jika tidak diubah)' : '*' }}
                        </label>
                        <div class="relative">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 pr-10"
                                :class="{ 'border-red-500': form.errors.password }"
                                placeholder="Masukkan password"
                            >
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500"
                            >
                                <svg v-if="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-red-500 text-sm mt-1">{{ form.errors.password }}</p>
                        <p class="text-gray-500 text-xs mt-1">Minimal 8 karakter</p>
                    </div>
                </div>
            </div>

            <!-- Role & Area -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Role & Penugasan</h2>

                <div class="space-y-4">
                    <!-- Role -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                v-for="(label, key) in roles"
                                :key="key"
                                type="button"
                                @click="form.role = key"
                                :class="[
                                    'py-3 px-4 rounded-lg border-2 text-center font-medium transition-colors',
                                    form.role === key
                                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                                        : 'border-gray-200 hover:border-gray-300'
                                ]"
                            >
                                <span class="block text-sm">{{ label }}</span>
                            </button>
                        </div>
                        <p v-if="form.errors.role" class="text-red-500 text-sm mt-1">{{ form.errors.role }}</p>
                    </div>

                    <!-- Area (for Collector) -->
                    <div v-if="showAreaField">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Area Penugasan</label>
                        <select
                            v-model="form.area_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.area_id }"
                        >
                            <option value="">-- Pilih Area --</option>
                            <option v-for="area in areas" :key="area.id" :value="area.id">
                                {{ area.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.area_id" class="text-red-500 text-sm mt-1">{{ form.errors.area_id }}</p>
                        <p class="text-gray-500 text-xs mt-1">Penagih akan ditugaskan ke area ini</p>
                    </div>

                    <!-- Active Status -->
                    <div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                            >
                            <span class="text-sm font-medium text-gray-700">User Aktif</span>
                        </label>
                        <p class="text-gray-500 text-xs mt-1 ml-8">User yang tidak aktif tidak dapat login</p>
                    </div>
                </div>
            </div>

            <!-- Role Description -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <h3 class="font-medium text-blue-800 mb-2">Deskripsi Role</h3>
                <div class="text-sm text-blue-700 space-y-1">
                    <p v-if="form.role === 'admin'">
                        <strong>Administrator:</strong> Akses penuh ke semua fitur sistem
                    </p>
                    <p v-else-if="form.role === 'penagih'">
                        <strong>Penagih/Collector:</strong> Menagih pembayaran, input pengeluaran, setoran harian
                    </p>
                    <p v-else-if="form.role === 'technician'">
                        <strong>Teknisi:</strong> Manajemen router, troubleshooting pelanggan
                    </p>
                    <p v-else-if="form.role === 'finance'">
                        <strong>Finance:</strong> Akses laporan keuangan dan pembayaran
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4">
                <Link
                    href="/admin/users"
                    class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                >
                    Batal
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Tambah User') }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>

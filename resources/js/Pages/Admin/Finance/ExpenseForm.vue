<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    categories: Object,
    expense: Object,
})

const isEdit = computed(() => !!props.expense)

const form = useForm({
    category: props.expense?.category || '',
    description: props.expense?.description || '',
    amount: props.expense?.amount || '',
    expense_date: props.expense?.expense_date ? props.expense.expense_date.substring(0, 10) : new Date().toISOString().substring(0, 10),
    receipt_photo: null,
    notes: props.expense?.notes || '',
})

const previewUrl = ref(props.expense?.receipt_url || null)

const onFileChange = (e) => {
    const file = e.target.files[0]
    if (file) {
        form.receipt_photo = file
        previewUrl.value = URL.createObjectURL(file)
    }
}

const removePhoto = () => {
    form.receipt_photo = null
    previewUrl.value = null
}

const submit = () => {
    if (isEdit.value) {
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(`/admin/finance/expenses/${props.expense.id}`, {
            forceFormData: true,
        })
    } else {
        form.post('/admin/finance/expenses', {
            forceFormData: true,
        })
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Pengeluaran' : 'Tambah Pengeluaran'" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/admin/finance/expenses" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ isEdit ? 'Edit Pengeluaran Operasional' : 'Tambah Pengeluaran Operasional' }}
                </h1>
            </div>
        </template>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Detail Pengeluaran</h2>

                <div class="space-y-4">
                    <!-- Kategori -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori *</label>
                        <select
                            v-model="form.category"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.category }"
                        >
                            <option value="">Pilih Kategori</option>
                            <option v-for="(label, key) in categories" :key="key" :value="key">
                                {{ label }}
                            </option>
                        </select>
                        <p v-if="form.errors.category" class="text-red-500 text-sm mt-1">{{ form.errors.category }}</p>
                    </div>

                    <!-- Deskripsi -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi *</label>
                        <input
                            v-model="form.description"
                            type="text"
                            placeholder="Contoh: Gaji bulan Februari 2026"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.description }"
                        >
                        <p v-if="form.errors.description" class="text-red-500 text-sm mt-1">{{ form.errors.description }}</p>
                    </div>

                    <!-- Nominal & Tanggal -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nominal (Rp) *</label>
                            <input
                                v-model="form.amount"
                                type="number"
                                min="0"
                                step="1000"
                                placeholder="0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.amount }"
                            >
                            <p v-if="form.errors.amount" class="text-red-500 text-sm mt-1">{{ form.errors.amount }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal *</label>
                            <input
                                v-model="form.expense_date"
                                type="date"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.expense_date }"
                            >
                            <p v-if="form.errors.expense_date" class="text-red-500 text-sm mt-1">{{ form.errors.expense_date }}</p>
                            <p class="text-xs text-gray-400 mt-1">Periode bulan/tahun otomatis dari tanggal</p>
                        </div>
                    </div>

                    <!-- Upload Bukti -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bukti / Nota (Opsional)</label>
                        <div v-if="previewUrl" class="mb-3">
                            <div class="relative inline-block">
                                <img :src="previewUrl" class="max-h-40 rounded-lg border" alt="Preview bukti" />
                                <button
                                    type="button"
                                    @click="removePhoto"
                                    class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>
                        <input
                            type="file"
                            accept="image/*"
                            @change="onFileChange"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        >
                        <p v-if="form.errors.receipt_photo" class="text-red-500 text-sm mt-1">{{ form.errors.receipt_photo }}</p>
                    </div>

                    <!-- Catatan -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            placeholder="Catatan tambahan (opsional)"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4">
                <Link
                    href="/admin/finance/expenses"
                    class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                >
                    Batal
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Perbarui' : 'Simpan') }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>

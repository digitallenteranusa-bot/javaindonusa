<script setup>
import { ref, computed, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    areas: Array,
    totalActiveCustomers: Number,
    maintenanceTemplate: String,
})

const form = useForm({
    tanggal_mulai: '',
    tanggal_selesai: '',
    keterangan: '',
    target: 'all',
    area_id: '',
})

const showConfirm = ref(false)

// Selected area info
const selectedArea = computed(() => {
    if (form.target !== 'area' || !form.area_id) return null
    return props.areas?.find(a => a.id == form.area_id)
})

// Number of recipients
const recipientCount = computed(() => {
    if (form.target === 'area' && selectedArea.value) {
        return selectedArea.value.active_customers
    }
    return props.totalActiveCustomers
})

// Format datetime for display
const formatDateTime = (value) => {
    if (!value) return '-'
    const date = new Date(value)
    return date.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

// Live message preview
const messagePreview = computed(() => {
    const template = props.maintenanceTemplate
    const startTime = form.tanggal_mulai ? formatDateTime(form.tanggal_mulai) : '{tanggal_mulai}'
    const endTime = form.tanggal_selesai ? formatDateTime(form.tanggal_selesai) : '{tanggal_selesai}'
    const description = form.keterangan || '{keterangan}'

    if (template) {
        return template
            .replace(/{nama}/g, 'Nama Pelanggan')
            .replace(/{tanggal_mulai}/g, startTime)
            .replace(/{tanggal_selesai}/g, endTime)
            .replace(/{keterangan}/g, description)
            .replace(/{telepon}/g, '08xx')
            .replace(/{whatsapp}/g, '08xx')
    }

    // Default template preview
    return `🔧 *PEMBERITAHUAN MAINTENANCE*\n\nYth. Bapak/Ibu *Nama Pelanggan*,\n\nKami informasikan bahwa akan dilakukan maintenance/perbaikan pada jaringan kami.\n\n📅 *Waktu Mulai:* ${startTime}\n📅 *Estimasi Selesai:* ${endTime}\n\n📝 *Keterangan:*\n${description}\n\nSelama proses ini, layanan internet Anda mungkin mengalami gangguan sementara. Kami mohon maaf atas ketidaknyamanannya.`
})

const submit = () => {
    form.post('/admin/broadcasts/maintenance', {
        onSuccess: () => {
            showConfirm.value = false
            form.reset()
        },
        onError: () => {
            showConfirm.value = false
        },
    })
}
</script>

<template>
    <Head title="Broadcast Maintenance" />
    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Broadcast Notifikasi Maintenance</h1>
        </template>

        <div class="max-w-5xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Form Section -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                        </svg>
                        Detail Maintenance
                    </h2>

                    <form @submit.prevent="showConfirm = true" class="space-y-4">
                        <!-- Tanggal Mulai -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Tanggal & Waktu Mulai <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.tanggal_mulai"
                                type="datetime-local"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                            <p v-if="form.errors.tanggal_mulai" class="text-red-500 text-sm mt-1">{{ form.errors.tanggal_mulai }}</p>
                        </div>

                        <!-- Estimasi Selesai -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Estimasi Selesai <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.tanggal_selesai"
                                type="datetime-local"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                            <p v-if="form.errors.tanggal_selesai" class="text-red-500 text-sm mt-1">{{ form.errors.tanggal_selesai }}</p>
                        </div>

                        <!-- Keterangan -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Keterangan / Deskripsi Gangguan <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                v-model="form.keterangan"
                                rows="3"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Contoh: Perbaikan kabel fiber optik di area Pule"
                                required
                            ></textarea>
                            <p v-if="form.errors.keterangan" class="text-red-500 text-sm mt-1">{{ form.errors.keterangan }}</p>
                        </div>

                        <!-- Target Penerima -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Target Penerima <span class="text-red-500">*</span>
                            </label>
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                    :class="form.target === 'all' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'"
                                >
                                    <input v-model="form.target" type="radio" value="all" class="text-blue-600 focus:ring-blue-500" />
                                    <div>
                                        <span class="font-medium text-gray-900">Semua Pelanggan Aktif</span>
                                        <span class="text-sm text-gray-500 ml-1">({{ totalActiveCustomers }} pelanggan)</span>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                    :class="form.target === 'area' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'"
                                >
                                    <input v-model="form.target" type="radio" value="area" class="text-blue-600 focus:ring-blue-500" />
                                    <span class="font-medium text-gray-900">Per Area</span>
                                </label>
                            </div>
                        </div>

                        <!-- Area Dropdown -->
                        <div v-if="form.target === 'area'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Pilih Area <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.area_id"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                required
                            >
                                <option value="">-- Pilih Area --</option>
                                <option v-for="area in areas" :key="area.id" :value="area.id">
                                    {{ area.name }} ({{ area.active_customers }} pelanggan)
                                </option>
                            </select>
                            <p v-if="form.errors.area_id" class="text-red-500 text-sm mt-1">{{ form.errors.area_id }}</p>
                        </div>

                        <!-- Recipient Info Box -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-sm text-blue-800">
                                    Notifikasi akan dikirim ke <strong>{{ recipientCount }}</strong> pelanggan via WhatsApp
                                </span>
                            </div>
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="form.processing || recipientCount === 0"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            Kirim Notifikasi
                        </button>
                    </form>
                </div>

                <!-- Preview Section -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Preview Pesan WhatsApp
                    </h2>

                    <!-- WhatsApp-like preview -->
                    <div class="bg-[#e5ddd5] rounded-lg p-4 min-h-[300px]">
                        <div class="bg-white rounded-lg p-3 shadow-sm max-w-sm">
                            <p class="text-sm text-gray-800 whitespace-pre-line leading-relaxed">{{ messagePreview }}</p>
                            <p class="text-xs text-gray-400 text-right mt-2">{{ new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}</p>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 mt-3">
                        * Preview menggunakan "Nama Pelanggan" sebagai contoh. Saat dikirim, nama akan diganti dengan nama pelanggan masing-masing.
                    </p>

                    <!-- Template info -->
                    <div v-if="maintenanceTemplate" class="mt-4 bg-green-50 border border-green-200 rounded-lg p-3">
                        <p class="text-sm text-green-800 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Menggunakan template custom dari Pengaturan
                        </p>
                    </div>
                    <div v-else class="mt-4 bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <p class="text-sm text-gray-600">
                            Menggunakan template default. Ubah template di menu Pengaturan > Notifikasi.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showConfirm = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Konfirmasi Kirim Notifikasi</h3>
                    <p class="text-gray-600 mb-4">
                        Anda akan mengirim notifikasi maintenance ke <strong>{{ recipientCount }}</strong> pelanggan
                        <span v-if="form.target === 'area' && selectedArea">di area <strong>{{ selectedArea.name }}</strong></span>.
                    </p>
                    <p class="text-sm text-gray-500 mb-6">
                        Pastikan informasi sudah benar. Notifikasi akan dikirim via WhatsApp secara bertahap melalui queue.
                    </p>
                    <div class="flex gap-3">
                        <button
                            @click="showConfirm = false"
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                            :disabled="form.processing"
                        >
                            Batal
                        </button>
                        <button
                            @click="submit"
                            class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors flex items-center justify-center gap-2 disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            {{ form.processing ? 'Mengirim...' : 'Ya, Kirim' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>

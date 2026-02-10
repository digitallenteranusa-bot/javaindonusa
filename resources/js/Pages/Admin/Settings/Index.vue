<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    settings: Object,
    ispInfo: Object,
    whatsappConfig: Object,
    whatsappDrivers: Object,
    logoUrl: String,
})

const activeTab = ref('billing')
const testPhone = ref('')
const testingWa = ref(false)

// Billing settings form
const billingForm = useForm({
    billing_due_date: props.settings.billing_due_date || 20,
    billing_grace_days: props.settings.billing_grace_days || 7,
    isolation_threshold_months: props.settings.isolation_threshold_months || 3,
    rapel_tolerance_months: props.settings.rapel_tolerance_months || 3,
    recent_payment_days: props.settings.recent_payment_days || 30,
})

// ISP info form
const ispForm = useForm({
    company_name: props.ispInfo?.company_name || '',
    tagline: props.ispInfo?.tagline || '',
    address: props.ispInfo?.address || '',
    phone_primary: props.ispInfo?.phone_primary || '',
    phone_secondary: props.ispInfo?.phone_secondary || '',
    whatsapp_number: props.ispInfo?.whatsapp_number || '',
    email: props.ispInfo?.email || '',
    website: props.ispInfo?.website || '',
    bank_accounts: props.ispInfo?.bank_accounts?.length ? props.ispInfo.bank_accounts : [{ bank: '', account: '', name: '' }],
})

// Format operational hours for display
const formatOperationalHours = (hours) => {
    if (!hours || typeof hours !== 'object') return '-'

    const days = {
        monday: 'Senin',
        tuesday: 'Selasa',
        wednesday: 'Rabu',
        thursday: 'Kamis',
        friday: 'Jumat',
        saturday: 'Sabtu',
        sunday: 'Minggu'
    }

    const result = []
    for (const [day, schedule] of Object.entries(hours)) {
        if (schedule.closed) {
            result.push(`${days[day]}: Tutup`)
        } else {
            result.push(`${days[day]}: ${schedule.open} - ${schedule.close}`)
        }
    }
    return result.join(', ')
}

// Default templates
const defaultReminderTemplate = `📢 PENGINGAT TAGIHAN

Yth. Bapak/Ibu {nama},

Kami menginformasikan bahwa tagihan internet Anda sebesar {nominal} akan jatuh tempo pada {jatuh_tempo}.

Segera lakukan pembayaran untuk menghindari pemutusan layanan otomatis oleh sistem.

Pesan ini dikirim otomatis. Abaikan jika sudah membayar.

Terima kasih.`

const defaultOverdueTemplate = `⚠️ TAGIHAN JATUH TEMPO

Yth. Bapak/Ibu {nama},

Tagihan internet Anda sebesar {nominal} telah melewati jatuh tempo.

Mohon segera lakukan pembayaran untuk menghindari isolir/pemutusan layanan.

ID Pelanggan: {customer_id}

Hubungi kami jika ada kendala:
📞 {telepon}
💬 WA: {whatsapp}`

const defaultPaymentConfirmationTemplate = `✅ KONFIRMASI PEMBAYARAN

Yth. Bapak/Ibu {nama},

Pembayaran Anda telah kami terima:

Detail:
No. Pembayaran: {no_pembayaran}
Jumlah: {nominal}
Metode: {metode}
Tanggal: {tanggal}

{status_lunas}

Terima kasih.`

const defaultIsolationTemplate = `🔴 PEMBERITAHUAN ISOLIR

Yth. Bapak/Ibu {nama},

Dengan berat hati kami informasikan bahwa layanan internet Anda telah DIISOLIR karena tunggakan pembayaran.

Total Tunggakan: {nominal}

Untuk mengaktifkan kembali layanan, silakan:
1. Lakukan pembayaran
2. Kirim bukti transfer via WhatsApp
3. Layanan akan aktif dalam 1x24 jam

Hubungi kami:
📞 {telepon}
💬 WA: {whatsapp}`

// Notification form (removed SMS)
const notificationForm = useForm({
    whatsapp_enabled: props.settings.whatsapp_enabled === '1',
    reminder_days_before: props.settings.reminder_days_before || 3,
    reminder_template: props.settings.reminder_template || defaultReminderTemplate,
    overdue_template: props.settings.overdue_template || defaultOverdueTemplate,
    isolation_template: props.settings.isolation_template || defaultIsolationTemplate,
    payment_confirmation_template: props.settings.payment_confirmation_template || defaultPaymentConfirmationTemplate,
})

// WhatsApp config form
const whatsappForm = useForm({
    driver: props.whatsappConfig?.driver || 'fonnte',
    api_key: props.whatsappConfig?.api_key || '',
    sender: props.whatsappConfig?.sender || '',
})

// Mikrotik form
const mikrotikForm = useForm({
    mikrotik_auto_isolate: props.settings.mikrotik_auto_isolate === '1',
    mikrotik_auto_reopen: props.settings.mikrotik_auto_reopen === '1',
    isolation_profile: props.settings.isolation_profile || 'isolated',
    isolation_address_list: props.settings.isolation_address_list || 'ISOLIR',
})

// GenieACS form
const genieacsForm = useForm({
    genieacs_enabled: props.settings.genieacs_enabled === '1',
    genieacs_nbi_url: props.settings.genieacs_nbi_url || 'http://localhost:7557',
    genieacs_ui_url: props.settings.genieacs_ui_url || 'http://localhost:3000',
    genieacs_fs_url: props.settings.genieacs_fs_url || 'http://localhost:7567',
    genieacs_username: props.settings.genieacs_username || '',
    genieacs_password: '',
    genieacs_sync_interval: props.settings.genieacs_sync_interval || 15,
})

// Logo upload form
const logoForm = useForm({
    logo: null,
})

// Submit handlers
const saveBilling = () => {
    billingForm.post('/admin/settings/general')
}

const saveIspInfo = () => {
    ispForm.post('/admin/settings/isp-info', {
        preserveScroll: true,
        onError: (errors) => {
            console.log('Validation errors:', errors)
        },
        onSuccess: () => {
            console.log('ISP Info saved successfully')
        }
    })
}

const saveNotification = () => {
    notificationForm.post('/admin/settings/notification')
}

const saveWhatsApp = () => {
    whatsappForm.post('/admin/settings/whatsapp')
}

const testWhatsApp = () => {
    if (!testPhone.value) {
        alert('Masukkan nomor telepon untuk test')
        return
    }
    testingWa.value = true
    router.post('/admin/settings/whatsapp/test', {
        phone: testPhone.value,
    }, {
        onFinish: () => {
            testingWa.value = false
        },
    })
}

const saveMikrotik = () => {
    mikrotikForm.post('/admin/settings/mikrotik')
}

const saveGenieacs = () => {
    genieacsForm.post('/admin/settings/genieacs')
}

// Logo handlers
const handleLogoChange = (event) => {
    logoForm.logo = event.target.files[0]
}

const uploadLogo = () => {
    logoForm.post('/admin/settings/upload-logo', {
        forceFormData: true,
        onSuccess: () => {
            logoForm.reset()
        },
    })
}

const deleteLogo = () => {
    if (confirm('Yakin ingin menghapus logo?')) {
        router.delete('/admin/settings/delete-logo')
    }
}

// Bank accounts
const addBankAccount = () => {
    ispForm.bank_accounts.push({ bank: '', account: '', name: '' })
}

const removeBankAccount = (index) => {
    ispForm.bank_accounts.splice(index, 1)
}

const tabs = [
    { id: 'billing', label: 'Billing', icon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z' },
    { id: 'isp', label: 'Info ISP', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
    { id: 'branding', label: 'Branding', icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { id: 'notification', label: 'Notifikasi', icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' },
    { id: 'whatsapp', label: 'WhatsApp', icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
    { id: 'mikrotik', label: 'Mikrotik', icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01' },
    { id: 'genieacs', label: 'GenieACS', icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
]
</script>

<template>
    <Head title="Pengaturan" />

    <AdminLayout>
        <template #header>
            <h1 class="text-2xl font-bold text-gray-900">Pengaturan</h1>
        </template>

        <div class="flex gap-6">
            <!-- Sidebar Tabs -->
            <div class="w-64 flex-shrink-0">
                <div class="bg-white rounded-xl shadow-sm p-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="[
                            'w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left transition-colors',
                            activeTab === tab.id
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-gray-600 hover:bg-gray-50'
                        ]"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon" />
                        </svg>
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1">
                <!-- Billing Settings -->
                <div v-if="activeTab === 'billing'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-6">Pengaturan Billing</h2>

                    <form @submit.prevent="saveBilling" class="space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Jatuh Tempo</label>
                                <input
                                    v-model="billingForm.billing_due_date"
                                    type="number"
                                    min="1"
                                    max="28"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                                <p class="text-xs text-gray-500 mt-1">Tanggal jatuh tempo tagihan setiap bulan (1-28)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Grace Period (Hari)</label>
                                <input
                                    v-model="billingForm.billing_grace_days"
                                    type="number"
                                    min="0"
                                    max="30"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                                <p class="text-xs text-gray-500 mt-1">Toleransi keterlambatan sebelum overdue</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ambang Isolasi (Bulan)</label>
                                <input
                                    v-model="billingForm.isolation_threshold_months"
                                    type="number"
                                    min="1"
                                    max="12"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                                <p class="text-xs text-gray-500 mt-1">Jumlah bulan overdue sebelum isolasi (pelanggan rapel dikecualikan)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Toleransi Rapel (Bulan)</label>
                                <input
                                    v-model="billingForm.rapel_tolerance_months"
                                    type="number"
                                    min="1"
                                    max="12"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                                <p class="text-xs text-gray-500 mt-1">Toleransi khusus untuk pelanggan dengan tipe pembayaran rapel</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pembayaran Terakhir (Hari)</label>
                                <input
                                    v-model="billingForm.recent_payment_days"
                                    type="number"
                                    min="1"
                                    max="90"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                                <p class="text-xs text-gray-500 mt-1">Jika ada pembayaran dalam X hari, tidak diisolasi</p>
                            </div>
                        </div>

                        <!-- Info Box -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
                            <p class="font-medium mb-2">Alur Isolasi Otomatis:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Tagihan dikirim tanggal 1 setiap bulan</li>
                                <li>Jatuh tempo sesuai pengaturan (default tanggal 20)</li>
                                <li>Grace period: toleransi keterlambatan setelah jatuh tempo</li>
                                <li>Isolasi otomatis jika tunggakan melebihi ambang (default 3 bulan)</li>
                            </ul>
                            <p class="font-medium mt-3 mb-2">Pengecualian Isolasi:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li><strong>Pelanggan Rapel:</strong> Tidak diisolasi selama masih dalam batas toleransi rapel</li>
                                <li><strong>Pembayaran Baru:</strong> Jika ada pembayaran dalam X hari terakhir</li>
                            </ul>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="billingForm.processing"
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ billingForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ISP Info -->
                <div v-if="activeTab === 'isp'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-6">Informasi ISP</h2>

                    <form @submit.prevent="saveIspInfo" class="space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Perusahaan *</label>
                                <input v-model="ispForm.company_name" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <p v-if="ispForm.errors.company_name" class="text-red-500 text-xs mt-1">{{ ispForm.errors.company_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tagline</label>
                                <input v-model="ispForm.tagline" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Telepon Utama *</label>
                                <input v-model="ispForm.phone_primary" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="08123456789">
                                <p v-if="ispForm.errors.phone_primary" class="text-red-500 text-xs mt-1">{{ ispForm.errors.phone_primary }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Telepon Sekunder</label>
                                <input v-model="ispForm.phone_secondary" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="08123456789">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor WhatsApp</label>
                                <input v-model="ispForm.whatsapp_number" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="08123456789">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input v-model="ispForm.email" type="email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                                <input v-model="ispForm.website" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="https://example.com">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jam Operasional</label>
                                <div class="px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm text-gray-600">
                                    {{ formatOperationalHours(ispInfo?.operational_hours) }}
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Dikelola melalui database</p>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                                <textarea v-model="ispForm.address" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                        </div>

                        <!-- Bank Accounts -->
                        <div>
                            <div class="flex justify-between items-center mb-3">
                                <label class="block text-sm font-medium text-gray-700">Rekening Bank</label>
                                <button type="button" @click="addBankAccount" class="text-blue-600 text-sm hover:underline">+ Tambah Rekening</button>
                            </div>
                            <div class="space-y-3">
                                <div v-for="(account, index) in ispForm.bank_accounts" :key="index" class="flex gap-3 items-start p-4 bg-gray-50 rounded-lg">
                                    <div class="flex-1 grid grid-cols-3 gap-3">
                                        <input v-model="account.bank" type="text" placeholder="Nama Bank" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                        <input v-model="account.account" type="text" placeholder="No. Rekening" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                        <input v-model="account.name" type="text" placeholder="Atas Nama" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                    </div>
                                    <button v-if="ispForm.bank_accounts.length > 1" type="button" @click="removeBankAccount(index)" class="text-red-500 hover:text-red-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Error Display -->
                        <div v-if="Object.keys(ispForm.errors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <p class="text-red-800 font-medium mb-2">Terdapat kesalahan:</p>
                            <ul class="list-disc list-inside text-sm text-red-600">
                                <li v-for="(error, field) in ispForm.errors" :key="field">{{ error }}</li>
                            </ul>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="ispForm.processing" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {{ ispForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Branding / Logo -->
                <div v-if="activeTab === 'branding'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-6">Branding & Logo</h2>

                    <div class="space-y-6">
                        <!-- Current Logo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Logo Perusahaan</label>
                            <div class="flex items-start gap-6">
                                <div class="w-40 h-40 bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden">
                                    <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="max-w-full max-h-full object-contain">
                                    <div v-else class="text-gray-400 text-center">
                                        <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <p class="text-sm mt-2">Belum ada logo</p>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <input type="file" @change="handleLogoChange" accept="image/png,image/jpeg,image/svg+xml" class="block text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    <p class="text-xs text-gray-500">Format: PNG, JPG, SVG. Maksimal 2MB.</p>
                                    <div class="flex gap-2">
                                        <button @click="uploadLogo" :disabled="!logoForm.logo || logoForm.processing" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm">
                                            {{ logoForm.processing ? 'Mengupload...' : 'Upload Logo' }}
                                        </button>
                                        <button v-if="logoUrl" @click="deleteLogo" class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 text-sm">
                                            Hapus Logo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
                            <p>Logo akan ditampilkan pada:</p>
                            <ul class="list-disc list-inside mt-1">
                                <li>Header aplikasi</li>
                                <li>Invoice PDF</li>
                                <li>Kwitansi pembayaran</li>
                                <li>Portal pelanggan</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Notification Settings -->
                <div v-if="activeTab === 'notification'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-6">Pengaturan Notifikasi</h2>

                    <form @submit.prevent="saveNotification" class="space-y-6">
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2">
                                <input v-model="notificationForm.whatsapp_enabled" type="checkbox" class="w-4 h-4 text-blue-600 rounded">
                                <span>WhatsApp Aktif</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kirim Pengingat H- (Hari)</label>
                            <input v-model="notificationForm.reminder_days_before" type="number" min="1" max="14" class="w-32 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Template Pengingat</label>
                            <textarea v-model="notificationForm.reminder_template" rows="10" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm"></textarea>
                            <p class="text-xs text-gray-500 mt-1">Variabel: <code class="bg-gray-100 px-1 rounded">{nama}</code> <code class="bg-gray-100 px-1 rounded">{nominal}</code> <code class="bg-gray-100 px-1 rounded">{jatuh_tempo}</code> <code class="bg-gray-100 px-1 rounded">{customer_id}</code> <code class="bg-gray-100 px-1 rounded">{paket}</code> <code class="bg-gray-100 px-1 rounded">{hari}</code></p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Template Overdue</label>
                            <textarea v-model="notificationForm.overdue_template" rows="10" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm"></textarea>
                            <p class="text-xs text-gray-500 mt-1">Variabel: <code class="bg-gray-100 px-1 rounded">{nama}</code> <code class="bg-gray-100 px-1 rounded">{nominal}</code> <code class="bg-gray-100 px-1 rounded">{customer_id}</code> <code class="bg-gray-100 px-1 rounded">{paket}</code> <code class="bg-gray-100 px-1 rounded">{telepon}</code> <code class="bg-gray-100 px-1 rounded">{whatsapp}</code></p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Template Isolasi</label>
                            <textarea v-model="notificationForm.isolation_template" rows="12" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm"></textarea>
                            <p class="text-xs text-gray-500 mt-1">Variabel: <code class="bg-gray-100 px-1 rounded">{nama}</code> <code class="bg-gray-100 px-1 rounded">{nominal}</code> <code class="bg-gray-100 px-1 rounded">{customer_id}</code> <code class="bg-gray-100 px-1 rounded">{paket}</code> <code class="bg-gray-100 px-1 rounded">{telepon}</code> <code class="bg-gray-100 px-1 rounded">{whatsapp}</code> <code class="bg-gray-100 px-1 rounded">{portal_url}</code></p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Template Konfirmasi Pembayaran</label>
                            <textarea v-model="notificationForm.payment_confirmation_template" rows="10" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm"></textarea>
                            <p class="text-xs text-gray-500 mt-1">Variabel: <code class="bg-gray-100 px-1 rounded">{nama}</code> <code class="bg-gray-100 px-1 rounded">{nominal}</code> <code class="bg-gray-100 px-1 rounded">{no_pembayaran}</code> <code class="bg-gray-100 px-1 rounded">{metode}</code> <code class="bg-gray-100 px-1 rounded">{tanggal}</code> <code class="bg-gray-100 px-1 rounded">{sisa_tagihan}</code> <code class="bg-gray-100 px-1 rounded">{customer_id}</code> <code class="bg-gray-100 px-1 rounded">{paket}</code> <code class="bg-gray-100 px-1 rounded">{status_lunas}</code></p>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="notificationForm.processing" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {{ notificationForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- WhatsApp Config -->
                <div v-if="activeTab === 'whatsapp'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-6">Konfigurasi WhatsApp Gateway</h2>

                    <form @submit.prevent="saveWhatsApp" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Provider / Driver</label>
                            <select v-model="whatsappForm.driver" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option v-for="(info, driver) in whatsappDrivers" :key="driver" :value="driver">
                                    {{ info.name }} - {{ info.description }}
                                </option>
                            </select>
                        </div>

                        <div v-if="whatsappForm.driver !== 'manual'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">API Key / Token</label>
                            <input v-model="whatsappForm.api_key" type="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Masukkan API key dari provider">
                            <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengubah</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Pengirim</label>
                            <input v-model="whatsappForm.sender" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="628123456789">
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="whatsappForm.processing" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {{ whatsappForm.processing ? 'Menyimpan...' : 'Simpan Konfigurasi' }}
                            </button>
                        </div>
                    </form>

                    <!-- Test WhatsApp -->
                    <div class="mt-8 pt-6 border-t">
                        <h3 class="font-semibold mb-4">Test Kirim Pesan</h3>
                        <div class="flex gap-3">
                            <input v-model="testPhone" type="text" placeholder="Nomor tujuan (08123456789)" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <button @click="testWhatsApp" :disabled="testingWa" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">
                                {{ testingWa ? 'Mengirim...' : 'Kirim Test' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mikrotik Settings -->
                <div v-if="activeTab === 'mikrotik'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-6">Pengaturan Mikrotik</h2>

                    <form @submit.prevent="saveMikrotik" class="space-y-6">
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2">
                                <input v-model="mikrotikForm.mikrotik_auto_isolate" type="checkbox" class="w-4 h-4 text-blue-600 rounded">
                                <span>Auto Isolasi</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="mikrotikForm.mikrotik_auto_reopen" type="checkbox" class="w-4 h-4 text-blue-600 rounded">
                                <span>Auto Buka Isolasi</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Profile Isolasi</label>
                                <input v-model="mikrotikForm.isolation_profile" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="isolated">
                                <p class="text-xs text-gray-500 mt-1">Nama PPPoE profile untuk pelanggan terisolasi</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address List Isolasi</label>
                                <input v-model="mikrotikForm.isolation_address_list" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="ISOLIR">
                                <p class="text-xs text-gray-500 mt-1">Nama address list untuk IP terisolasi</p>
                            </div>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-800">
                            <p class="font-medium">Perhatian:</p>
                            <p>Pastikan koneksi ke Mikrotik sudah dikonfigurasi di halaman Router.</p>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="mikrotikForm.processing" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {{ mikrotikForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- GenieACS Settings -->
                <div v-if="activeTab === 'genieacs'" class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <h2 class="text-lg font-semibold">Pengaturan GenieACS (TR-069)</h2>
                    </div>

                    <form @submit.prevent="saveGenieacs" class="space-y-6">
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2">
                                <input v-model="genieacsForm.genieacs_enabled" type="checkbox" class="w-4 h-4 text-blue-600 rounded">
                                <span>GenieACS Aktif</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">NBI URL</label>
                                <input v-model="genieacsForm.genieacs_nbi_url" type="url" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="http://localhost:7557">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">UI URL</label>
                                <input v-model="genieacsForm.genieacs_ui_url" type="url" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="http://localhost:3000">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">FS URL</label>
                                <input v-model="genieacsForm.genieacs_fs_url" type="url" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="http://localhost:7567">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Interval Sinkronisasi (menit)</label>
                                <input v-model="genieacsForm.genieacs_sync_interval" type="number" min="5" max="60" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                <input v-model="genieacsForm.genieacs_username" type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input v-model="genieacsForm.genieacs_password" type="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Kosongkan jika tidak ingin mengubah">
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="genieacsForm.processing" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {{ genieacsForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

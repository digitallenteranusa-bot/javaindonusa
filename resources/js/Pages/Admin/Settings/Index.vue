<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    settings: Object,
    ispInfo: Object,
})

const activeTab = ref('billing')

// Billing settings form
const billingForm = useForm({
    billing_due_days: props.settings.billing_due_days || 20,
    billing_grace_days: props.settings.billing_grace_days || 7,
    isolation_threshold_months: props.settings.isolation_threshold_months || 2,
    rapel_tolerance_months: props.settings.rapel_tolerance_months || 3,
    recent_payment_days: props.settings.recent_payment_days || 30,
})

// ISP info form
const ispForm = useForm({
    company_name: props.ispInfo?.company_name || '',
    tagline: props.ispInfo?.tagline || '',
    address: props.ispInfo?.address || '',
    phone: props.ispInfo?.phone || '',
    email: props.ispInfo?.email || '',
    website: props.ispInfo?.website || '',
    operational_hours: props.ispInfo?.operational_hours || '',
    bank_accounts: props.ispInfo?.bank_accounts || [{ bank: '', account: '', name: '' }],
})

// Notification form
const notificationForm = useForm({
    whatsapp_enabled: props.settings.whatsapp_enabled === '1',
    sms_enabled: props.settings.sms_enabled === '1',
    reminder_days_before: props.settings.reminder_days_before || 3,
    reminder_template: props.settings.reminder_template || '',
    overdue_template: props.settings.overdue_template || '',
    isolation_template: props.settings.isolation_template || '',
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

// Submit handlers
const saveBilling = () => {
    billingForm.post('/admin/settings/general')
}

const saveIspInfo = () => {
    ispForm.post('/admin/settings/isp-info')
}

const saveNotification = () => {
    notificationForm.post('/admin/settings/notification')
}

const saveMikrotik = () => {
    mikrotikForm.post('/admin/settings/mikrotik')
}

const saveGenieacs = () => {
    genieacsForm.post('/admin/settings/genieacs')
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
    { id: 'notification', label: 'Notifikasi', icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9' },
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jatuh Tempo (Hari)</label>
                                <input
                                    v-model="billingForm.billing_due_days"
                                    type="number"
                                    min="1"
                                    max="30"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                                <p class="text-xs text-gray-500 mt-1">Tanggal jatuh tempo setiap bulan</p>
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
                                <p class="text-xs text-gray-500 mt-1">Jumlah bulan overdue sebelum isolasi</p>
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
                                <p class="text-xs text-gray-500 mt-1">Toleransi tambahan untuk pelanggan rapel</p>
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
                                <input
                                    v-model="ispForm.company_name"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tagline</label>
                                <input
                                    v-model="ispForm.tagline"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Telepon *</label>
                                <input
                                    v-model="ispForm.phone"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input
                                    v-model="ispForm.email"
                                    type="email"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                                <input
                                    v-model="ispForm.website"
                                    type="url"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jam Operasional</label>
                                <input
                                    v-model="ispForm.operational_hours"
                                    type="text"
                                    placeholder="08:00 - 17:00"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                                <textarea
                                    v-model="ispForm.address"
                                    rows="2"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                ></textarea>
                            </div>
                        </div>

                        <!-- Bank Accounts -->
                        <div>
                            <div class="flex justify-between items-center mb-3">
                                <label class="block text-sm font-medium text-gray-700">Rekening Bank</label>
                                <button
                                    type="button"
                                    @click="addBankAccount"
                                    class="text-blue-600 text-sm hover:underline"
                                >
                                    + Tambah Rekening
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="(account, index) in ispForm.bank_accounts"
                                    :key="index"
                                    class="flex gap-3 items-start p-4 bg-gray-50 rounded-lg"
                                >
                                    <div class="flex-1 grid grid-cols-3 gap-3">
                                        <input
                                            v-model="account.bank"
                                            type="text"
                                            placeholder="Nama Bank"
                                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                        >
                                        <input
                                            v-model="account.account"
                                            type="text"
                                            placeholder="No. Rekening"
                                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                        >
                                        <input
                                            v-model="account.name"
                                            type="text"
                                            placeholder="Atas Nama"
                                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                        >
                                    </div>
                                    <button
                                        v-if="ispForm.bank_accounts.length > 1"
                                        type="button"
                                        @click="removeBankAccount(index)"
                                        class="text-red-500 hover:text-red-700"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="ispForm.processing"
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ ispForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Notification Settings -->
                <div v-if="activeTab === 'notification'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-6">Pengaturan Notifikasi</h2>

                    <form @submit.prevent="saveNotification" class="space-y-6">
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="notificationForm.whatsapp_enabled"
                                    type="checkbox"
                                    class="w-4 h-4 text-blue-600 rounded"
                                >
                                <span>WhatsApp Aktif</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="notificationForm.sms_enabled"
                                    type="checkbox"
                                    class="w-4 h-4 text-blue-600 rounded"
                                >
                                <span>SMS Aktif</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kirim Pengingat H- (Hari)</label>
                            <input
                                v-model="notificationForm.reminder_days_before"
                                type="number"
                                min="1"
                                max="14"
                                class="w-32 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Template Pengingat</label>
                            <textarea
                                v-model="notificationForm.reminder_template"
                                rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="Gunakan {nama}, {nominal}, {jatuh_tempo} sebagai variabel"
                            ></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Template Overdue</label>
                            <textarea
                                v-model="notificationForm.overdue_template"
                                rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            ></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Template Isolasi</label>
                            <textarea
                                v-model="notificationForm.isolation_template"
                                rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            ></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="notificationForm.processing"
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ notificationForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Mikrotik Settings -->
                <div v-if="activeTab === 'mikrotik'" class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold mb-6">Pengaturan Mikrotik</h2>

                    <form @submit.prevent="saveMikrotik" class="space-y-6">
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="mikrotikForm.mikrotik_auto_isolate"
                                    type="checkbox"
                                    class="w-4 h-4 text-blue-600 rounded"
                                >
                                <span>Auto Isolasi</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="mikrotikForm.mikrotik_auto_reopen"
                                    type="checkbox"
                                    class="w-4 h-4 text-blue-600 rounded"
                                >
                                <span>Auto Buka Isolasi</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Profile Isolasi</label>
                                <input
                                    v-model="mikrotikForm.isolation_profile"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="isolated"
                                >
                                <p class="text-xs text-gray-500 mt-1">Nama PPPoE profile untuk pelanggan terisolasi</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address List Isolasi</label>
                                <input
                                    v-model="mikrotikForm.isolation_address_list"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="ISOLIR"
                                >
                                <p class="text-xs text-gray-500 mt-1">Nama address list untuk IP terisolasi</p>
                            </div>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-800">
                            <p class="font-medium">Perhatian:</p>
                            <p>Pastikan koneksi ke Mikrotik sudah dikonfigurasi di file .env dengan benar.</p>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="mikrotikForm.processing"
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ mikrotikForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- GenieACS Settings -->
                <div v-if="activeTab === 'genieacs'" class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <img src="/img/acs.png" alt="GenieACS" class="w-10 h-10">
                        <h2 class="text-lg font-semibold">Pengaturan GenieACS (TR-069)</h2>
                    </div>

                    <form @submit.prevent="saveGenieacs" class="space-y-6">
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="genieacsForm.genieacs_enabled"
                                    type="checkbox"
                                    class="w-4 h-4 text-blue-600 rounded"
                                >
                                <span>GenieACS Aktif</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">NBI URL</label>
                                <input
                                    v-model="genieacsForm.genieacs_nbi_url"
                                    type="url"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="http://localhost:7557"
                                >
                                <p class="text-xs text-gray-500 mt-1">URL NBI API GenieACS</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">UI URL</label>
                                <input
                                    v-model="genieacsForm.genieacs_ui_url"
                                    type="url"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="http://localhost:3000"
                                >
                                <p class="text-xs text-gray-500 mt-1">URL Web UI GenieACS</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">FS URL</label>
                                <input
                                    v-model="genieacsForm.genieacs_fs_url"
                                    type="url"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="http://localhost:7567"
                                >
                                <p class="text-xs text-gray-500 mt-1">URL File Server GenieACS</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Interval Sinkronisasi (menit)</label>
                                <input
                                    v-model="genieacsForm.genieacs_sync_interval"
                                    type="number"
                                    min="5"
                                    max="60"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                <input
                                    v-model="genieacsForm.genieacs_username"
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <input
                                    v-model="genieacsForm.genieacs_password"
                                    type="password"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="Kosongkan jika tidak ingin mengubah"
                                >
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
                            <p class="font-medium">Informasi:</p>
                            <p>GenieACS digunakan untuk manajemen perangkat CPE (ONT/Router) via protokol TR-069.</p>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="genieacsForm.processing"
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ genieacsForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

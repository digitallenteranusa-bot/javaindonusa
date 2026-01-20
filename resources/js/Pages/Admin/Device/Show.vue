<script setup>
import { ref, onMounted, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    device: Object,
    genieData: Object,
    customers: Array,
})

// UI States
const loading = ref(false)
const refreshing = ref(false)
const rebooting = ref(false)
const resetting = ref(false)
const installingFirmware = ref(false)
const loadingFirmwareFiles = ref(false)
const uploadingFirmware = ref(false)
const loadingTasks = ref(false)

// Firmware
const firmwareFiles = ref([])
const selectedFirmwareFile = ref('')
const showFirmwareModal = ref(false)
const showUploadModal = ref(false)
const firmwareFile = ref(null)
const firmwareVersion = ref('')
const firmwareOui = ref('')
const firmwareProductClass = ref('')

// WiFi
const showWifiModal = ref(false)
const wifiSsid = ref(props.device.wifi_ssid || '')
const wifiPassword = ref('')
const wifiEnabled = ref(props.device.wifi_enabled !== false)
const savingWifi = ref(false)

// Link Customer
const showLinkModal = ref(false)
const selectedCustomerId = ref('')
const linking = ref(false)

// Factory Reset
const showResetModal = ref(false)
const resetConfirm = ref('')

// Tasks
const tasks = ref([])

// Computed
const signalStatus = computed(() => {
    const rx = props.device.rx_power
    if (rx === null || rx === undefined) return 'unknown'
    if (rx < -28) return 'weak'
    if (rx > -8) return 'too_strong'
    return 'good'
})

const signalClass = computed(() => {
    switch (signalStatus.value) {
        case 'good': return 'text-green-600'
        case 'weak': return 'text-red-600'
        case 'too_strong': return 'text-yellow-600'
        default: return 'text-gray-400'
    }
})

// Format
const formatDateTime = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const formatUptime = (seconds) => {
    if (!seconds) return '-'
    const days = Math.floor(seconds / 86400)
    const hours = Math.floor((seconds % 86400) / 3600)
    const minutes = Math.floor((seconds % 3600) / 60)

    if (days > 0) return `${days}d ${hours}h ${minutes}m`
    if (hours > 0) return `${hours}h ${minutes}m`
    return `${minutes}m`
}

// Load firmware files from GenieACS
const loadFirmwareFiles = async () => {
    loadingFirmwareFiles.value = true
    try {
        const response = await fetch('/admin/devices/firmware-files')
        const data = await response.json()
        firmwareFiles.value = data.files || []
    } catch (error) {
        console.error('Failed to load firmware files:', error)
    } finally {
        loadingFirmwareFiles.value = false
    }
}

// Load pending tasks
const loadTasks = async () => {
    loadingTasks.value = true
    try {
        const response = await fetch(`/admin/devices/${props.device.id}/tasks`)
        const data = await response.json()
        tasks.value = data.tasks || []
    } catch (error) {
        console.error('Failed to load tasks:', error)
    } finally {
        loadingTasks.value = false
    }
}

// Actions
const refreshDevice = () => {
    refreshing.value = true
    router.post(`/admin/devices/${props.device.id}/refresh`, {}, {
        onFinish: () => {
            refreshing.value = false
            loadTasks()
        }
    })
}

const rebootDevice = () => {
    if (!confirm('Reboot device ini?')) return

    rebooting.value = true
    router.post(`/admin/devices/${props.device.id}/reboot`, {}, {
        onFinish: () => {
            rebooting.value = false
            loadTasks()
        }
    })
}

const factoryReset = () => {
    if (resetConfirm.value !== 'RESET') return

    resetting.value = true
    router.post(`/admin/devices/${props.device.id}/factory-reset`, {
        confirm: 'RESET'
    }, {
        onFinish: () => {
            resetting.value = false
            showResetModal.value = false
            resetConfirm.value = ''
            loadTasks()
        }
    })
}

// Install firmware
const openFirmwareModal = () => {
    loadFirmwareFiles()
    showFirmwareModal.value = true
}

const installFirmware = () => {
    if (!selectedFirmwareFile.value) {
        alert('Pilih file firmware terlebih dahulu')
        return
    }

    if (!confirm('Install firmware ke device ini? Device akan restart setelah update selesai.')) {
        return
    }

    installingFirmware.value = true
    router.post(`/admin/devices/${props.device.id}/install-update`, {
        file_id: selectedFirmwareFile.value
    }, {
        onFinish: () => {
            installingFirmware.value = false
            showFirmwareModal.value = false
            selectedFirmwareFile.value = ''
            loadTasks()
        }
    })
}

// Upload firmware
const handleFirmwareFileSelect = (event) => {
    const file = event.target.files[0]
    if (file) {
        firmwareFile.value = file
    }
}

const uploadFirmware = () => {
    if (!firmwareFile.value) {
        alert('Pilih file firmware terlebih dahulu')
        return
    }

    uploadingFirmware.value = true

    const formData = new FormData()
    formData.append('file', firmwareFile.value)
    formData.append('version', firmwareVersion.value)
    formData.append('oui', firmwareOui.value)
    formData.append('product_class', firmwareProductClass.value)

    router.post('/admin/devices/upload-firmware', formData, {
        forceFormData: true,
        onFinish: () => {
            uploadingFirmware.value = false
            showUploadModal.value = false
            firmwareFile.value = null
            firmwareVersion.value = ''
            firmwareOui.value = ''
            firmwareProductClass.value = ''
            loadFirmwareFiles()
        }
    })
}

// WiFi Settings
const saveWifiSettings = () => {
    savingWifi.value = true
    router.post(`/admin/devices/${props.device.id}/wifi`, {
        ssid: wifiSsid.value || undefined,
        password: wifiPassword.value || undefined,
        enabled: wifiEnabled.value,
    }, {
        onFinish: () => {
            savingWifi.value = false
            showWifiModal.value = false
            wifiPassword.value = ''
        }
    })
}

// Link Customer
const linkCustomer = () => {
    if (!selectedCustomerId.value) return

    linking.value = true
    router.post(`/admin/devices/${props.device.id}/link`, {
        customer_id: selectedCustomerId.value
    }, {
        onFinish: () => {
            linking.value = false
            showLinkModal.value = false
            selectedCustomerId.value = ''
        }
    })
}

const unlinkCustomer = () => {
    if (!confirm('Lepas device dari pelanggan ini?')) return

    router.post(`/admin/devices/${props.device.id}/unlink`)
}

onMounted(() => {
    loadTasks()
})
</script>

<template>
    <Head :title="`Device - ${device.serial_number || device.device_id}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link href="/admin/devices" class="p-2 hover:bg-gray-100 rounded-lg">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ device.serial_number || device.device_id }}</h1>
                        <p class="text-sm text-gray-500">{{ device.manufacturer }} {{ device.model }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="px-3 py-1 rounded-full text-sm font-medium"
                        :class="device.is_online ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'"
                    >
                        {{ device.is_online ? 'Online' : 'Offline' }}
                    </span>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Device Info & Customer -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Device Information -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Informasi Device
                    </h3>

                    <div class="space-y-3">
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Serial Number</span>
                            <span class="font-medium font-mono">{{ device.serial_number || '-' }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Manufacturer</span>
                            <span class="font-medium">{{ device.manufacturer || '-' }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Model</span>
                            <span class="font-medium">{{ device.model || '-' }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Firmware Version</span>
                            <span class="font-medium text-blue-600">{{ device.firmware_version || '-' }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Hardware Version</span>
                            <span class="font-medium">{{ device.hardware_version || '-' }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">WAN IP</span>
                            <span class="font-medium font-mono">{{ device.wan_ip || '-' }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">WAN MAC</span>
                            <span class="font-medium font-mono text-sm">{{ device.wan_mac || '-' }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Last Inform</span>
                            <span class="font-medium">{{ formatDateTime(device.last_inform) }}</span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-gray-600">Uptime</span>
                            <span class="font-medium">{{ formatUptime(device.uptime) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Customer & Signal -->
                <div class="space-y-6">
                    <!-- Customer Info -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Pelanggan
                        </h3>

                        <div v-if="device.customer" class="space-y-3">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-lg font-semibold text-blue-600">
                                        {{ device.customer.name.charAt(0).toUpperCase() }}
                                    </span>
                                </div>
                                <div class="flex-1">
                                    <Link
                                        :href="`/admin/customers/${device.customer.id}`"
                                        class="font-medium text-blue-600 hover:underline"
                                    >
                                        {{ device.customer.name }}
                                    </Link>
                                    <p class="text-sm text-gray-500">{{ device.customer.customer_id }}</p>
                                    <p v-if="device.customer.package" class="text-sm text-gray-500">
                                        {{ device.customer.package.name }}
                                    </p>
                                </div>
                            </div>
                            <button
                                @click="unlinkCustomer"
                                class="w-full px-4 py-2 text-red-600 border border-red-300 rounded-lg hover:bg-red-50"
                            >
                                Lepas dari Pelanggan
                            </button>
                        </div>
                        <div v-else class="text-center py-6">
                            <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <p class="mt-2 text-gray-500">Belum terhubung dengan pelanggan</p>
                            <button
                                @click="showLinkModal = true"
                                class="mt-3 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                            >
                                Hubungkan Pelanggan
                            </button>
                        </div>
                    </div>

                    <!-- Signal Info -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                            </svg>
                            Signal Optik
                        </h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-50 rounded-lg text-center">
                                <p class="text-sm text-gray-500 mb-1">RX Power</p>
                                <p class="text-2xl font-bold font-mono" :class="signalClass">
                                    {{ device.rx_power !== null ? device.rx_power + ' dBm' : '-' }}
                                </p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-lg text-center">
                                <p class="text-sm text-gray-500 mb-1">TX Power</p>
                                <p class="text-2xl font-bold font-mono text-blue-600">
                                    {{ device.tx_power !== null ? device.tx_power + ' dBm' : '-' }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 text-xs text-gray-500">
                            Range normal: -28 dBm s/d -8 dBm
                        </div>
                    </div>
                </div>
            </div>

            <!-- Install Update & WiFi -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Install Update Section -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Install Update Firmware
                    </h3>

                    <div class="space-y-4">
                        <div class="p-4 bg-blue-50 rounded-lg">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p class="font-medium text-blue-900">Firmware Saat Ini</p>
                                    <p class="text-sm text-blue-700 mt-1">
                                        {{ device.firmware_version || 'Tidak diketahui' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button
                                @click="openFirmwareModal"
                                class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 flex items-center justify-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                Install Firmware
                            </button>
                            <button
                                @click="showUploadModal = true"
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                Upload
                            </button>
                        </div>

                        <div class="text-sm text-gray-500">
                            <p>Upload file firmware ke GenieACS, lalu install ke device.</p>
                            <p class="mt-1 text-yellow-600">Device akan restart setelah update selesai.</p>
                        </div>
                    </div>
                </div>

                <!-- WiFi Settings -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                        Pengaturan WiFi
                    </h3>

                    <div class="space-y-3">
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">SSID</span>
                            <span class="font-medium">{{ device.wifi_ssid || '-' }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Status</span>
                            <span
                                class="px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="device.wifi_enabled !== false ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'"
                            >
                                {{ device.wifi_enabled !== false ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>

                        <button
                            @click="showWifiModal = true"
                            class="w-full mt-2 px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Ubah Pengaturan WiFi
                        </button>
                    </div>
                </div>
            </div>

            <!-- Device Actions -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Aksi Device
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Refresh -->
                    <button
                        @click="refreshDevice"
                        :disabled="refreshing"
                        class="p-4 bg-green-50 hover:bg-green-100 rounded-lg flex items-center gap-3 disabled:opacity-50"
                    >
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg v-if="refreshing" class="animate-spin w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <div class="text-left">
                            <p class="font-medium text-gray-900">Refresh Parameter</p>
                            <p class="text-sm text-gray-500">Ambil data terbaru dari device</p>
                        </div>
                    </button>

                    <!-- Reboot -->
                    <button
                        @click="rebootDevice"
                        :disabled="rebooting"
                        class="p-4 bg-yellow-50 hover:bg-yellow-100 rounded-lg flex items-center gap-3 disabled:opacity-50"
                    >
                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg v-if="rebooting" class="animate-spin w-5 h-5 text-yellow-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <div class="text-left">
                            <p class="font-medium text-gray-900">Reboot Device</p>
                            <p class="text-sm text-gray-500">Restart device</p>
                        </div>
                    </button>

                    <!-- Factory Reset -->
                    <button
                        @click="showResetModal = true"
                        class="p-4 bg-red-50 hover:bg-red-100 rounded-lg flex items-center gap-3"
                    >
                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="text-left">
                            <p class="font-medium text-gray-900">Factory Reset</p>
                            <p class="text-sm text-gray-500">Reset ke pengaturan pabrik</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Pending Tasks -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        Pending Tasks
                    </h3>
                    <button @click="loadTasks" class="text-sm text-blue-600 hover:underline">
                        Refresh
                    </button>
                </div>

                <div v-if="loadingTasks" class="text-center py-6 text-gray-500">
                    Memuat...
                </div>
                <div v-else-if="tasks.length === 0" class="text-center py-6 text-gray-500">
                    Tidak ada pending task
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="task in tasks"
                        :key="task._id"
                        class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                    >
                        <div>
                            <p class="font-medium">{{ task.name }}</p>
                            <p class="text-sm text-gray-500">{{ task.status || 'pending' }}</p>
                        </div>
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full">
                            Pending
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Firmware Install Modal -->
        <div v-if="showFirmwareModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="showFirmwareModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Install Firmware</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Pilih File Firmware
                            </label>
                            <select
                                v-model="selectedFirmwareFile"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
                            >
                                <option value="">-- Pilih Firmware --</option>
                                <option
                                    v-for="file in firmwareFiles"
                                    :key="file._id"
                                    :value="file._id"
                                >
                                    {{ file._id }} {{ file.metadata?.version ? `(v${file.metadata.version})` : '' }}
                                </option>
                            </select>
                            <p v-if="loadingFirmwareFiles" class="text-sm text-gray-500 mt-1">
                                Memuat daftar firmware...
                            </p>
                            <p v-else-if="firmwareFiles.length === 0" class="text-sm text-yellow-600 mt-1">
                                Belum ada file firmware. Upload terlebih dahulu.
                            </p>
                        </div>

                        <div class="p-3 bg-yellow-50 rounded-lg text-sm text-yellow-700">
                            Device akan restart setelah firmware berhasil diinstall.
                        </div>
                    </div>

                    <div class="flex gap-3 justify-end mt-6">
                        <button
                            @click="showFirmwareModal = false"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        >
                            Batal
                        </button>
                        <button
                            @click="installFirmware"
                            :disabled="!selectedFirmwareFile || installingFirmware"
                            class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <svg v-if="installingFirmware" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ installingFirmware ? 'Menginstall...' : 'Install' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Firmware Modal -->
        <div v-if="showUploadModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="showUploadModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Upload Firmware ke GenieACS</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                File Firmware
                            </label>
                            <input
                                type="file"
                                @change="handleFirmwareFileSelect"
                                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Version (opsional)
                            </label>
                            <input
                                v-model="firmwareVersion"
                                type="text"
                                placeholder="e.g. 1.0.5"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                OUI (opsional)
                            </label>
                            <input
                                v-model="firmwareOui"
                                type="text"
                                placeholder="e.g. 00259E"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Product Class (opsional)
                            </label>
                            <input
                                v-model="firmwareProductClass"
                                type="text"
                                placeholder="e.g. HG8245H"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
                            />
                        </div>
                    </div>

                    <div class="flex gap-3 justify-end mt-6">
                        <button
                            @click="showUploadModal = false"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        >
                            Batal
                        </button>
                        <button
                            @click="uploadFirmware"
                            :disabled="!firmwareFile || uploadingFirmware"
                            class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <svg v-if="uploadingFirmware" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ uploadingFirmware ? 'Mengupload...' : 'Upload' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- WiFi Settings Modal -->
        <div v-if="showWifiModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="showWifiModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Ubah Pengaturan WiFi</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                SSID
                            </label>
                            <input
                                v-model="wifiSsid"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Password Baru (kosongkan jika tidak diubah)
                            </label>
                            <input
                                v-model="wifiPassword"
                                type="password"
                                placeholder="Min. 8 karakter"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500"
                            />
                        </div>

                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input v-model="wifiEnabled" type="checkbox" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-cyan-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
                            </label>
                            <span class="text-sm font-medium text-gray-700">WiFi Aktif</span>
                        </div>
                    </div>

                    <div class="flex gap-3 justify-end mt-6">
                        <button
                            @click="showWifiModal = false"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        >
                            Batal
                        </button>
                        <button
                            @click="saveWifiSettings"
                            :disabled="savingWifi"
                            class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <svg v-if="savingWifi" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ savingWifi ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Factory Reset Modal -->
        <div v-if="showResetModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="showResetModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-red-600 mb-4">Factory Reset</h3>

                    <div class="space-y-4">
                        <div class="p-4 bg-red-50 rounded-lg">
                            <p class="text-sm text-red-700">
                                <strong>Peringatan:</strong> Factory reset akan menghapus semua pengaturan device dan mengembalikan ke pengaturan pabrik. Tindakan ini tidak dapat dibatalkan.
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Ketik <strong>RESET</strong> untuk konfirmasi
                            </label>
                            <input
                                v-model="resetConfirm"
                                type="text"
                                placeholder="RESET"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            />
                        </div>
                    </div>

                    <div class="flex gap-3 justify-end mt-6">
                        <button
                            @click="showResetModal = false; resetConfirm = ''"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        >
                            Batal
                        </button>
                        <button
                            @click="factoryReset"
                            :disabled="resetConfirm !== 'RESET' || resetting"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <svg v-if="resetting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ resetting ? 'Mereset...' : 'Factory Reset' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Link Customer Modal -->
        <div v-if="showLinkModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="showLinkModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Hubungkan dengan Pelanggan</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Pilih Pelanggan
                            </label>
                            <select
                                v-model="selectedCustomerId"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">-- Pilih Pelanggan --</option>
                                <option
                                    v-for="customer in customers"
                                    :key="customer.id"
                                    :value="customer.id"
                                >
                                    {{ customer.name }} ({{ customer.customer_id }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-3 justify-end mt-6">
                        <button
                            @click="showLinkModal = false"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        >
                            Batal
                        </button>
                        <button
                            @click="linkCustomer"
                            :disabled="!selectedCustomerId || linking"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <svg v-if="linking" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ linking ? 'Menghubungkan...' : 'Hubungkan' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

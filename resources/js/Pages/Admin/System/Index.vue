<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    appVersion: String,
    stats: Object,
    systemInfo: Object,
    updateInfo: Object,
})

const checkingUpdate = ref(false)
const clearingCache = ref(false)
const creatingBackup = ref(false)
const loadingBackups = ref(false)
const backups = ref([])
const showBackupModal = ref(false)
const selectedBackup = ref(null)
const restoringBackup = ref(false)
const uploadingBackup = ref(false)

// Database backup
const creatingDbBackup = ref(false)
const loadingDbBackups = ref(false)
const dbBackups = ref([])
const showDbRestoreModal = ref(false)
const selectedDbBackup = ref(null)
const restoringDbBackup = ref(false)
const uploadingDbBackup = ref(false)
const dbBackupFile = ref(null)
const dbBackupFileInput = ref(null)

// Git pull update
const pullingUpdate = ref(false)
const updateLog = ref('')

// File upload
const backupFile = ref(null)
const backupFileInput = ref(null)

// Live server time
const serverTime = ref(new Date(props.systemInfo.server_time))
let timeInterval = null

const updateTime = () => {
    serverTime.value = new Date(serverTime.value.getTime() + 1000)
}

onMounted(() => {
    timeInterval = setInterval(updateTime, 1000)
    loadBackups()
    loadDbBackups()
})

onUnmounted(() => {
    if (timeInterval) {
        clearInterval(timeInterval)
    }
})

const formatTime = (date) => {
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

    const day = days[date.getDay()]
    const d = date.getDate()
    const month = months[date.getMonth()]
    const year = date.getFullYear()
    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')
    const seconds = String(date.getSeconds()).padStart(2, '0')

    return `${day}, ${d} ${month} ${year} - ${hours}:${minutes}:${seconds}`
}

const checkUpdate = () => {
    checkingUpdate.value = true
    router.post('/admin/system/check-update', {}, {
        onFinish: () => {
            checkingUpdate.value = false
        }
    })
}

const clearCache = () => {
    clearingCache.value = true
    router.post('/admin/system/clear-cache', {}, {
        onFinish: () => {
            clearingCache.value = false
        }
    })
}

const createBackup = () => {
    creatingBackup.value = true
    router.post('/admin/system/backup', {}, {
        onFinish: () => {
            creatingBackup.value = false
            loadBackups()
        }
    })
}

const loadBackups = async () => {
    loadingBackups.value = true
    try {
        const response = await fetch('/admin/system/backups')
        const data = await response.json()
        backups.value = data.backups || []
    } catch (error) {
        console.error('Failed to load backups:', error)
    } finally {
        loadingBackups.value = false
    }
}

const page = usePage()

const gitPullUpdate = () => {
    if (!confirm('Apakah Anda yakin ingin menjalankan update? Proses ini akan menjalankan git pull, composer install, npm build, dan migrate.')) {
        return
    }

    pullingUpdate.value = true
    updateLog.value = ''

    router.post('/admin/system/git-pull-update', {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (page.props.flash?.updateLog) {
                updateLog.value = page.props.flash.updateLog
            }
        },
        onFinish: () => {
            pullingUpdate.value = false
        }
    })
}

const confirmRestore = (backup) => {
    selectedBackup.value = backup
    showBackupModal.value = true
}

const restoreBackup = () => {
    if (!selectedBackup.value) return

    restoringBackup.value = true
    router.post('/admin/system/restore-backup', {
        backup_file: selectedBackup.value.name
    }, {
        onFinish: () => {
            restoringBackup.value = false
            showBackupModal.value = false
            selectedBackup.value = null
        }
    })
}

const deleteBackup = (backup) => {
    if (!confirm(`Hapus backup ${backup.name}?`)) return

    router.delete('/admin/system/delete-backup', {
        data: { backup_file: backup.name },
        onSuccess: () => {
            loadBackups()
        }
    })
}

const handleBackupFileSelect = (event) => {
    const file = event.target.files[0]
    if (file) {
        if (file.type !== 'application/zip' && !file.name.endsWith('.zip')) {
            alert('File harus berformat ZIP')
            return
        }
        backupFile.value = file
    }
}

const uploadBackup = () => {
    if (!backupFile.value) {
        alert('Pilih file backup terlebih dahulu')
        return
    }

    uploadingBackup.value = true

    const formData = new FormData()
    formData.append('backup_file', backupFile.value)

    router.post('/admin/system/upload-backup', formData, {
        forceFormData: true,
        onFinish: () => {
            uploadingBackup.value = false
            backupFile.value = null
            if (backupFileInput.value) {
                backupFileInput.value.value = ''
            }
            loadBackups()
        }
    })
}

// Database backup methods
const createDbBackup = () => {
    creatingDbBackup.value = true
    router.post('/admin/system/db-backup', {}, {
        onFinish: () => {
            creatingDbBackup.value = false
            loadDbBackups()
        }
    })
}

const loadDbBackups = async () => {
    loadingDbBackups.value = true
    try {
        const response = await fetch('/admin/system/db-backups')
        const data = await response.json()
        dbBackups.value = data.backups || []
    } catch (error) {
        console.error('Failed to load database backups:', error)
    } finally {
        loadingDbBackups.value = false
    }
}

const handleDbBackupFileSelect = (event) => {
    const file = event.target.files[0]
    if (file) {
        const name = file.name.toLowerCase()
        if (!name.endsWith('.sql') && !name.endsWith('.sql.gz') && !name.endsWith('.gz')) {
            alert('File harus berformat .sql atau .sql.gz')
            return
        }
        dbBackupFile.value = file
    }
}

const uploadDbBackup = () => {
    if (!dbBackupFile.value) {
        alert('Pilih file backup database terlebih dahulu')
        return
    }

    uploadingDbBackup.value = true

    const formData = new FormData()
    formData.append('backup_file', dbBackupFile.value)

    router.post('/admin/system/upload-db-backup', formData, {
        forceFormData: true,
        onFinish: () => {
            uploadingDbBackup.value = false
            dbBackupFile.value = null
            if (dbBackupFileInput.value) {
                dbBackupFileInput.value.value = ''
            }
            loadDbBackups()
        }
    })
}

const confirmDbRestore = (backup) => {
    selectedDbBackup.value = backup
    showDbRestoreModal.value = true
}

const restoreDbBackup = () => {
    if (!selectedDbBackup.value) return

    restoringDbBackup.value = true
    router.post('/admin/system/restore-db-backup', {
        backup_file: selectedDbBackup.value.name
    }, {
        onFinish: () => {
            restoringDbBackup.value = false
            showDbRestoreModal.value = false
            selectedDbBackup.value = null
        }
    })
}

const deleteDbBackup = (backup) => {
    if (!confirm(`Hapus backup database ${backup.name}?`)) return

    router.delete('/admin/system/delete-db-backup', {
        data: { backup_file: backup.name },
        onSuccess: () => {
            loadDbBackups()
        }
    })
}

const formatNumber = (num) => {
    return new Intl.NumberFormat('id-ID').format(num)
}
</script>

<template>
    <Head title="Sistem" />

    <AdminLayout>
        <template #header>
            <h1 class="text-2xl font-bold text-gray-900">Informasi Sistem</h1>
        </template>

        <div class="space-y-6">
            <!-- App Info Card -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-start gap-6">
                    <div class="w-20 h-20 bg-blue-100 rounded-2xl flex items-center justify-center">
                        <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold text-gray-900">ISP Billing System</h2>
                        <p class="text-gray-500 mt-1">Java Indonusa - Sistem Billing Internet Service Provider</p>
                        <div class="flex items-center gap-4 mt-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                v{{ appVersion }}
                            </span>
                            <span v-if="updateInfo.update_available" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                Update tersedia: v{{ updateInfo.latest_version }}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button
                            @click="checkUpdate"
                            :disabled="checkingUpdate"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <svg v-if="checkingUpdate" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Cek Update
                        </button>
                    </div>
                </div>
            </div>

            <!-- Server Time -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-xl shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm mb-1">Waktu Server</p>
                        <p class="text-3xl font-bold font-mono">{{ formatTime(serverTime) }}</p>
                        <p class="text-blue-200 text-sm mt-2">
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Timezone: {{ systemInfo.timezone }}
                            </span>
                            <span v-if="systemInfo.uptime && systemInfo.uptime !== 'N/A'" class="ml-4 inline-flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Uptime: {{ systemInfo.uptime }}
                            </span>
                        </p>
                    </div>
                    <div class="hidden sm:block">
                        <svg class="w-20 h-20 text-blue-400 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <img src="/img/pelanggan.png" alt="Pelanggan" class="w-6 h-6">
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.total_customers) }}</p>
                            <p class="text-xs text-gray-500">Total Pelanggan</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.active_customers) }}</p>
                            <p class="text-xs text-gray-500">Aktif</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                            <img src="/img/tukang_tagih.png" alt="Users" class="w-6 h-6">
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.total_users) }}</p>
                            <p class="text-xs text-gray-500">Users</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <img src="/img/hutang.png" alt="Invoice" class="w-6 h-6">
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.total_invoices) }}</p>
                            <p class="text-xs text-gray-500">Invoice</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                            <img src="/img/money.png" alt="Pembayaran" class="w-6 h-6">
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.total_payments) }}</p>
                            <p class="text-xs text-gray-500">Pembayaran</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ stats.database_size }}</p>
                            <p class="text-xs text-gray-500">Database</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Update & Backup Section -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Update & Backup
                </h3>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Git Pull Update -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3">Update Aplikasi</h4>
                        <p class="text-sm text-gray-500 mb-4">Update langsung dari repository via git pull + build</p>

                        <div class="space-y-3">
                            <!-- Status: ada update / sudah terbaru -->
                            <div v-if="updateInfo.update_available" class="flex items-center gap-2 p-3 bg-green-50 rounded-lg">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-green-800">Update tersedia: v{{ updateInfo.latest_version }}</p>
                                    <p class="text-xs text-green-600">Versi saat ini: v{{ appVersion }}</p>
                                </div>
                            </div>
                            <div v-else class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm text-gray-600">Aplikasi sudah versi terbaru (v{{ appVersion }})</p>
                            </div>

                            <button
                                @click="gitPullUpdate"
                                :disabled="pullingUpdate"
                                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                <svg v-if="pullingUpdate" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                {{ pullingUpdate ? 'Sedang mengupdate...' : 'Update Sekarang' }}
                            </button>
                        </div>

                        <!-- Update Log Output -->
                        <div v-if="updateLog" class="mt-4">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Log Update:</h5>
                            <pre class="p-3 bg-gray-900 text-green-400 text-xs rounded-lg overflow-x-auto max-h-64 overflow-y-auto whitespace-pre-wrap">{{ updateLog }}</pre>
                        </div>

                        <div class="mt-4 p-3 bg-yellow-50 rounded-lg">
                            <p class="text-xs text-yellow-700">
                                <strong>Proses:</strong> git pull &rarr; composer install &rarr; npm build &rarr; migrate &rarr; clear cache. Aplikasi akan masuk maintenance mode selama proses.
                            </p>
                        </div>
                    </div>

                    <!-- Backup -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3">Backup Aplikasi</h4>
                        <p class="text-sm text-gray-500 mb-4">Buat backup sebelum melakukan perubahan besar</p>

                        <button
                            @click="createBackup"
                            :disabled="creatingBackup"
                            class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            <svg v-if="creatingBackup" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                            {{ creatingBackup ? 'Membuat Backup...' : 'Buat Backup Sekarang' }}
                        </button>

                        <!-- Upload Backup -->
                        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Upload Backup</h5>
                            <div class="flex gap-2">
                                <input
                                    ref="backupFileInput"
                                    type="file"
                                    accept=".zip"
                                    @change="handleBackupFileSelect"
                                    class="flex-1 text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                />
                                <button
                                    @click="uploadBackup"
                                    :disabled="!backupFile || uploadingBackup"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {{ uploadingBackup ? '...' : 'Upload' }}
                                </button>
                            </div>
                            <p v-if="backupFile" class="text-xs text-green-600 mt-1">{{ backupFile.name }}</p>
                        </div>

                        <!-- Backup List -->
                        <div class="mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <h5 class="text-sm font-medium text-gray-700">Daftar Backup</h5>
                                <button @click="loadBackups" class="text-xs text-blue-600 hover:underline">Refresh</button>
                            </div>

                            <div v-if="loadingBackups" class="text-center py-4 text-gray-500 text-sm">
                                Memuat...
                            </div>
                            <div v-else-if="backups.length === 0" class="text-center py-4 text-gray-500 text-sm">
                                Belum ada backup
                            </div>
                            <div v-else class="space-y-2 max-h-48 overflow-y-auto">
                                <div
                                    v-for="backup in backups"
                                    :key="backup.name"
                                    class="flex items-center justify-between p-2 bg-gray-50 rounded-lg text-sm"
                                >
                                    <div>
                                        <p class="font-medium text-gray-800">{{ backup.name }}</p>
                                        <p class="text-xs text-gray-500">{{ backup.size }} - {{ backup.created_at }}</p>
                                    </div>
                                    <div class="flex gap-1">
                                        <a
                                            :href="`/admin/system/backups/download/${backup.name}`"
                                            class="p-1.5 text-green-600 hover:bg-green-100 rounded"
                                            title="Download"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </a>
                                        <button
                                            @click="confirmRestore(backup)"
                                            class="p-1.5 text-blue-600 hover:bg-blue-100 rounded"
                                            title="Restore"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="deleteBackup(backup)"
                                            class="p-1.5 text-red-600 hover:bg-red-100 rounded"
                                            title="Hapus"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Backup Section -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                    </svg>
                    Backup Database
                </h3>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Create & Upload DB Backup -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3">Backup Database MySQL</h4>
                        <p class="text-sm text-gray-500 mb-4">Export seluruh database (tabel, data, trigger, routine) ke file SQL</p>

                        <button
                            @click="createDbBackup"
                            :disabled="creatingDbBackup"
                            class="w-full px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            <svg v-if="creatingDbBackup" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                            </svg>
                            {{ creatingDbBackup ? 'Membuat Backup Database...' : 'Backup Database Sekarang' }}
                        </button>

                        <!-- Upload DB Backup -->
                        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Upload Backup Database</h5>
                            <div class="flex gap-2">
                                <input
                                    ref="dbBackupFileInput"
                                    type="file"
                                    accept=".sql,.gz"
                                    @change="handleDbBackupFileSelect"
                                    class="flex-1 text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100"
                                />
                                <button
                                    @click="uploadDbBackup"
                                    :disabled="!dbBackupFile || uploadingDbBackup"
                                    class="px-3 py-1 bg-orange-600 text-white text-sm rounded hover:bg-orange-700 disabled:opacity-50"
                                >
                                    {{ uploadingDbBackup ? '...' : 'Upload' }}
                                </button>
                            </div>
                            <p v-if="dbBackupFile" class="text-xs text-green-600 mt-1">{{ dbBackupFile.name }}</p>
                            <p class="text-xs text-gray-400 mt-1">Format: .sql atau .sql.gz</p>
                        </div>

                        <div class="mt-4 p-3 bg-yellow-50 rounded-lg">
                            <p class="text-xs text-yellow-700">
                                <strong>Catatan:</strong> Backup database berisi seluruh data (pelanggan, invoice, pembayaran, dll). Restore akan menimpa database yang ada saat ini.
                            </p>
                        </div>
                    </div>

                    <!-- DB Backup List -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-medium text-gray-900">Daftar Backup Database</h4>
                            <button @click="loadDbBackups" class="text-xs text-orange-600 hover:underline">Refresh</button>
                        </div>

                        <div v-if="loadingDbBackups" class="text-center py-8 text-gray-500 text-sm">
                            Memuat...
                        </div>
                        <div v-else-if="dbBackups.length === 0" class="text-center py-8 text-gray-500 text-sm">
                            Belum ada backup database
                        </div>
                        <div v-else class="space-y-2 max-h-64 overflow-y-auto">
                            <div
                                v-for="backup in dbBackups"
                                :key="backup.name"
                                class="flex items-center justify-between p-3 bg-gray-50 rounded-lg text-sm"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-gray-800 truncate">{{ backup.name }}</p>
                                    <p class="text-xs text-gray-500">{{ backup.size }} - {{ backup.created_at }}</p>
                                </div>
                                <div class="flex gap-1 ml-2 shrink-0">
                                    <a
                                        :href="`/admin/system/db-backups/download/${backup.name}`"
                                        class="p-1.5 text-green-600 hover:bg-green-100 rounded"
                                        title="Download"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </a>
                                    <button
                                        @click="confirmDbRestore(backup)"
                                        class="p-1.5 text-orange-600 hover:bg-orange-100 rounded"
                                        title="Restore Database"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="deleteDbBackup(backup)"
                                        class="p-1.5 text-red-600 hover:bg-red-100 rounded"
                                        title="Hapus"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- System Info -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4">Informasi Server</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">PHP Version</span>
                            <span class="font-medium text-green-600">{{ systemInfo.php_version }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Laravel Version</span>
                            <span class="font-medium text-red-600">{{ systemInfo.laravel_version }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Server OS</span>
                            <span class="font-medium">{{ systemInfo.server_os }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Web Server</span>
                            <span class="font-medium text-sm text-gray-700">{{ systemInfo.server_software }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Timezone</span>
                            <span class="font-medium">{{ systemInfo.timezone }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Max Upload</span>
                            <span class="font-medium">{{ systemInfo.max_upload }}</span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-gray-600">Memory Limit</span>
                            <span class="font-medium">{{ systemInfo.memory_limit }}</span>
                        </div>
                    </div>
                </div>

                <!-- Maintenance -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4">Maintenance</h3>
                    <div class="space-y-4">
                        <!-- Clear Cache -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-900">Bersihkan Cache</p>
                                <p class="text-sm text-gray-500">Hapus cache aplikasi, config, view, dan route</p>
                            </div>
                            <button
                                @click="clearCache"
                                :disabled="clearingCache"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 flex items-center gap-2"
                            >
                                <svg v-if="clearingCache" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Clear Cache
                            </button>
                        </div>

                        <!-- Update Info -->
                        <div class="p-4 bg-blue-50 rounded-lg">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p class="font-medium text-blue-900">Info Update</p>
                                    <p class="text-sm text-blue-700 mt-1">
                                        Versi saat ini: <strong>v{{ appVersion }}</strong>
                                    </p>
                                    <p v-if="updateInfo.last_check" class="text-sm text-blue-600 mt-1">
                                        Terakhir dicek: {{ updateInfo.last_check }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Links -->
                        <div class="grid grid-cols-2 gap-3">
                            <a href="/admin/settings" class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                <img src="/img/pengaturan.png" alt="Pengaturan" class="w-6 h-6">
                                <span class="text-sm font-medium text-gray-700">Pengaturan</span>
                            </a>
                            <a href="/admin/reports" class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                <span class="text-sm font-medium text-gray-700">Laporan</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tech Stack -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Tech Stack</h3>
                <div class="flex flex-wrap gap-3">
                    <span class="px-4 py-2 bg-red-100 text-red-800 rounded-lg text-sm font-medium">Laravel 11</span>
                    <span class="px-4 py-2 bg-green-100 text-green-800 rounded-lg text-sm font-medium">Vue 3</span>
                    <span class="px-4 py-2 bg-purple-100 text-purple-800 rounded-lg text-sm font-medium">Inertia.js</span>
                    <span class="px-4 py-2 bg-blue-100 text-blue-800 rounded-lg text-sm font-medium">Tailwind CSS</span>
                    <span class="px-4 py-2 bg-orange-100 text-orange-800 rounded-lg text-sm font-medium">MySQL 8</span>
                    <span class="px-4 py-2 bg-rose-100 text-rose-800 rounded-lg text-sm font-medium">Redis</span>
                    <span class="px-4 py-2 bg-cyan-100 text-cyan-800 rounded-lg text-sm font-medium">Mikrotik API</span>
                    <span class="px-4 py-2 bg-indigo-100 text-indigo-800 rounded-lg text-sm font-medium">GenieACS TR-069</span>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center text-sm text-gray-500 py-4">
                <p>&copy; {{ new Date().getFullYear() }} Java Indonusa. ISP Billing System.</p>
            </div>
        </div>

        <!-- Restore Modal -->
        <div v-if="showBackupModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="showBackupModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Konfirmasi Restore</h3>
                    <p class="text-gray-600 mb-4">
                        Apakah Anda yakin ingin restore dari backup <strong>{{ selectedBackup?.name }}</strong>?
                    </p>
                    <p class="text-sm text-yellow-600 mb-6">
                        Proses ini akan mengganti file aplikasi dengan versi backup. Aplikasi akan masuk maintenance mode selama proses.
                    </p>
                    <div class="flex gap-3 justify-end">
                        <button
                            @click="showBackupModal = false"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        >
                            Batal
                        </button>
                        <button
                            @click="restoreBackup"
                            :disabled="restoringBackup"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <svg v-if="restoringBackup" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ restoringBackup ? 'Restoring...' : 'Ya, Restore' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Database Restore Modal -->
        <div v-if="showDbRestoreModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="showDbRestoreModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Konfirmasi Restore Database</h3>
                    <p class="text-gray-600 mb-4">
                        Apakah Anda yakin ingin restore database dari backup <strong>{{ selectedDbBackup?.name }}</strong>?
                    </p>
                    <div class="p-3 bg-red-50 rounded-lg mb-6">
                        <p class="text-sm text-red-700">
                            <strong>Peringatan:</strong> Semua data di database saat ini akan ditimpa dengan data dari backup ini. Pastikan Anda sudah membuat backup database terbaru sebelum melanjutkan.
                        </p>
                    </div>
                    <div class="flex gap-3 justify-end">
                        <button
                            @click="showDbRestoreModal = false"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                        >
                            Batal
                        </button>
                        <button
                            @click="restoreDbBackup"
                            :disabled="restoringDbBackup"
                            class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 disabled:opacity-50 flex items-center gap-2"
                        >
                            <svg v-if="restoringDbBackup" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ restoringDbBackup ? 'Restoring...' : 'Ya, Restore Database' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

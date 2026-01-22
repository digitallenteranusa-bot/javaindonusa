<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    olt: Object,
    types: Object,
    statuses: Object,
    ponPortOptions: Object,
})

const checkConnection = () => {
    router.post(`/admin/olts/${props.olt.id}/check-connection`)
}

const getStatusColor = (status) => {
    return {
        'active': 'bg-green-100 text-green-700',
        'inactive': 'bg-gray-100 text-gray-500',
        'maintenance': 'bg-yellow-100 text-yellow-700',
    }[status] || 'bg-gray-100 text-gray-500'
}

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text)
    alert('Disalin ke clipboard')
}
</script>

<template>
    <Head :title="`OLT - ${olt.name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/admin/olts" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h1 class="text-2xl font-bold text-gray-900">{{ olt.name }}</h1>
                    <span :class="['px-2 py-1 text-xs rounded-full', getStatusColor(olt.status)]">
                        {{ statuses[olt.status] }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <button
                        @click="checkConnection"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                        </svg>
                        Test Koneksi
                    </button>
                    <Link
                        :href="`/admin/olts/${olt.id}/edit`"
                        class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Info Card -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Informasi OLT</h2>
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Tipe</span>
                        <span class="font-medium">{{ olt.type }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">IP Address</span>
                        <div class="flex items-center gap-2">
                            <span class="font-mono">{{ olt.ip_address }}</span>
                            <button @click="copyToClipboard(olt.ip_address)" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">PON Ports</span>
                        <span class="font-medium">{{ olt.pon_ports }} Port</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Firmware</span>
                        <span class="font-medium">{{ olt.firmware_version || '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Last Check</span>
                        <span class="text-sm">{{ olt.last_checked_at ? new Date(olt.last_checked_at).toLocaleString('id-ID') : '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- Connection Info -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Koneksi</h2>
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Username</span>
                        <span class="font-mono">{{ olt.username || '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Telnet Port</span>
                        <span class="font-mono">{{ olt.telnet_port }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">SSH Port</span>
                        <span class="font-mono">{{ olt.ssh_port }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">SNMP Community</span>
                        <span class="font-mono">{{ olt.snmp_community ? '********' : '-' }}</span>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t">
                    <p class="text-sm text-gray-500 mb-2">Quick Access:</p>
                    <div class="space-y-2">
                        <button
                            @click="copyToClipboard(`telnet ${olt.ip_address} ${olt.telnet_port}`)"
                            class="w-full text-left px-3 py-2 bg-gray-100 rounded-lg font-mono text-sm hover:bg-gray-200"
                        >
                            telnet {{ olt.ip_address }} {{ olt.telnet_port }}
                        </button>
                        <button
                            @click="copyToClipboard(`ssh -p ${olt.ssh_port} ${olt.username || 'admin'}@${olt.ip_address}`)"
                            class="w-full text-left px-3 py-2 bg-gray-100 rounded-lg font-mono text-sm hover:bg-gray-200"
                        >
                            ssh -p {{ olt.ssh_port }} {{ olt.username || 'admin' }}@{{ olt.ip_address }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div v-if="olt.notes" class="bg-white rounded-xl shadow-sm p-6 lg:col-span-2">
                <h2 class="text-lg font-semibold mb-4">Catatan</h2>
                <p class="text-gray-600 whitespace-pre-wrap">{{ olt.notes }}</p>
            </div>
        </div>
    </AdminLayout>
</template>

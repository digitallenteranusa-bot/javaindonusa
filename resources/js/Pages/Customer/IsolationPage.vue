<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    customer: Object,
    isp_info: Object,
    transfer_proof_wa_url: String,
})

const formattedDebt = computed(() => {
    const amount = props.customer?.total_debt || 0
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount)
})
</script>

<template>
    <Head title="Layanan Terisolir" />

    <div class="min-h-screen bg-gradient-to-b from-red-600 to-red-800 flex flex-col">
        <!-- Header -->
        <div class="flex flex-col items-center pt-10 pb-6 px-6">
            <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg">
                <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="text-xl font-bold text-white text-center">
                {{ isp_info?.company_name || 'ISP Billing' }}
            </h1>
        </div>

        <!-- Content Card -->
        <div class="flex-1 bg-white rounded-t-3xl px-6 py-8">
            <!-- Warning Icon & Title -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                    <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-800">
                    Layanan Internet Anda Terisolir
                </h2>
                <p class="text-gray-500 text-sm mt-2">
                    Akses internet Anda dibatasi karena terdapat tunggakan pembayaran
                </p>
            </div>

            <!-- Customer Info -->
            <div class="bg-gray-50 rounded-xl p-4 mb-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">ID Pelanggan</span>
                    <span class="font-semibold text-gray-800">{{ customer.customer_id }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Nama</span>
                    <span class="font-semibold text-gray-800">{{ customer.name }}</span>
                </div>
                <div v-if="customer.isolation_reason" class="flex justify-between text-sm">
                    <span class="text-gray-500">Alasan</span>
                    <span class="font-semibold text-red-600">{{ customer.isolation_reason }}</span>
                </div>
            </div>

            <!-- Total Debt -->
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 text-center">
                <p class="text-sm text-red-600 mb-1">Total Tunggakan</p>
                <p class="text-2xl font-bold text-red-700">{{ formattedDebt }}</p>
            </div>

            <!-- Payment Info -->
            <div v-if="isp_info?.bank_accounts?.length || isp_info?.ewallet_accounts?.length" class="mb-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Informasi Pembayaran</h3>

                <!-- Bank Accounts -->
                <div v-if="isp_info.bank_accounts?.length" class="space-y-2 mb-3">
                    <div
                        v-for="(bank, index) in isp_info.bank_accounts"
                        :key="'bank-' + index"
                        class="bg-blue-50 border border-blue-200 rounded-xl p-3"
                    >
                        <p class="text-xs text-blue-600 font-medium">{{ bank.bank_name }}</p>
                        <p class="text-lg font-bold text-gray-800 tracking-wider">{{ bank.account_number }}</p>
                        <p class="text-sm text-gray-600">a.n. {{ bank.account_holder }}</p>
                    </div>
                </div>

                <!-- E-Wallet Accounts -->
                <div v-if="isp_info.ewallet_accounts?.length" class="space-y-2">
                    <div
                        v-for="(ewallet, index) in isp_info.ewallet_accounts"
                        :key="'ewallet-' + index"
                        class="bg-green-50 border border-green-200 rounded-xl p-3"
                    >
                        <p class="text-xs text-green-600 font-medium">{{ ewallet.provider }}</p>
                        <p class="text-lg font-bold text-gray-800 tracking-wider">{{ ewallet.phone_number || ewallet.account_number }}</p>
                        <p class="text-sm text-gray-600">a.n. {{ ewallet.account_holder }}</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <!-- WhatsApp Transfer Proof -->
                <a
                    v-if="transfer_proof_wa_url"
                    :href="transfer_proof_wa_url"
                    target="_blank"
                    class="flex items-center justify-center gap-2 w-full py-3 bg-green-500 text-white rounded-xl font-semibold hover:bg-green-600 transition-colors"
                >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    Kirim Bukti Transfer via WhatsApp
                </a>

                <!-- Portal Link -->
                <a
                    :href="route('customer.login')"
                    class="flex items-center justify-center gap-2 w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Masuk ke Portal Pelanggan
                </a>
            </div>

            <!-- Contact Info -->
            <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                <p class="text-sm text-gray-500">Butuh bantuan?</p>
                <p class="text-sm text-gray-700 mt-1">
                    Hubungi: {{ isp_info?.phone || '-' }}
                </p>
                <p v-if="isp_info?.operational_hours" class="text-xs text-gray-400 mt-1">
                    Jam operasional: {{ isp_info.operational_hours }}
                </p>
            </div>
        </div>
    </div>
</template>

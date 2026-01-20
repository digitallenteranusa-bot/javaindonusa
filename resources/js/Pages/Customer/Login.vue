<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({
    isp_info: Object,
})

const form = useForm({
    phone: '',
})

const submit = () => {
    form.post(route('customer.request-otp'))
}

// Format phone input
const formatPhone = (event) => {
    let value = event.target.value.replace(/\D/g, '')
    if (value.length > 13) {
        value = value.slice(0, 13)
    }
    form.phone = value
}
</script>

<template>
    <Head title="Login Pelanggan" />

    <div class="min-h-screen bg-gradient-to-b from-blue-600 to-blue-800 flex flex-col">
        <!-- Logo & Header -->
        <div class="flex-1 flex flex-col items-center justify-center px-6 py-12">
            <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mb-6 shadow-lg">
                <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-white text-center">
                {{ isp_info?.company_name || 'ISP Billing' }}
            </h1>
            <p class="text-blue-100 text-center mt-2">
                {{ isp_info?.tagline || 'Portal Pelanggan' }}
            </p>
        </div>

        <!-- Login Form -->
        <div class="bg-white rounded-t-3xl px-6 py-8">
            <h2 class="text-xl font-semibold text-gray-800 text-center mb-2">
                Login Pelanggan
            </h2>
            <p class="text-gray-500 text-center text-sm mb-6">
                Masukkan nomor HP yang terdaftar
            </p>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Nomor HP
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            +62
                        </span>
                        <input
                            type="tel"
                            :value="form.phone"
                            @input="formatPhone"
                            class="w-full pl-14 pr-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="8123456789"
                            required
                        >
                    </div>
                    <p v-if="form.errors.phone" class="mt-1 text-sm text-red-500">
                        {{ form.errors.phone }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || form.phone.length < 10"
                    class="w-full py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                >
                    <span v-if="form.processing">Mengirim...</span>
                    <span v-else>Kirim Kode OTP</span>
                </button>
            </form>

            <p class="text-center text-sm text-gray-500 mt-6">
                Kode OTP akan dikirim via WhatsApp ke nomor HP Anda
            </p>

            <!-- Contact Info -->
            <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                <p class="text-sm text-gray-500">Butuh bantuan?</p>
                <p class="text-sm text-gray-700 mt-1">
                    Hubungi: {{ isp_info?.phone || '-' }}
                </p>
            </div>
        </div>
    </div>
</template>

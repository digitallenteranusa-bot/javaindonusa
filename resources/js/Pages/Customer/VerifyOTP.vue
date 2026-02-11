<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
    phone: String,
    phone_masked: String,
    message: String,
})

const page = usePage()

const form = useForm({
    phone: props.phone,
    otp: '',
})

// Auto-focus dan input handling untuk 6 digit OTP
const otpInputs = ref([])
const otpDigits = ref(['', '', '', '', '', ''])

const focusInput = (index) => {
    if (otpInputs.value[index]) {
        otpInputs.value[index].focus()
    }
}

const handleInput = (index, event) => {
    const value = event.target.value.replace(/\D/g, '')

    if (value.length > 1) {
        // Handle paste - distribute digits across inputs
        const digits = value.split('')
        digits.forEach((digit, i) => {
            if (index + i < 6) {
                otpDigits.value[index + i] = digit
            }
        })
        const nextIndex = Math.min(index + digits.length, 5)
        focusInput(nextIndex)
    } else {
        otpDigits.value[index] = value
        if (value && index < 5) {
            focusInput(index + 1)
        }
    }

    form.otp = otpDigits.value.join('')

    // Auto submit jika 6 digit terisi
    if (form.otp.length === 6) {
        submit()
    }
}

const handleKeydown = (index, event) => {
    if (event.key === 'Backspace' && !otpDigits.value[index] && index > 0) {
        focusInput(index - 1)
    }
}

const handlePaste = (event) => {
    event.preventDefault()
    const pasted = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6)
    pasted.split('').forEach((digit, i) => {
        otpDigits.value[i] = digit
    })
    form.otp = otpDigits.value.join('')
    focusInput(Math.min(pasted.length, 5))

    if (form.otp.length === 6) {
        submit()
    }
}

const submit = () => {
    if (form.processing) return
    form.post(route('customer.verify-otp'))
}

// Countdown timer untuk resend OTP
const countdown = ref(60)
const canResend = ref(false)
let timer = null

const startTimer = () => {
    countdown.value = 60
    canResend.value = false
    timer = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0) {
            canResend.value = true
            clearInterval(timer)
        }
    }, 1000)
}

const resendOTP = () => {
    if (!canResend.value) return

    const resendForm = useForm({ phone: props.phone })
    resendForm.post(route('customer.request-otp'), {
        preserveState: false,
    })
}

onMounted(() => {
    startTimer()
    focusInput(0)
})

onUnmounted(() => {
    if (timer) clearInterval(timer)
})
</script>

<template>
    <Head title="Verifikasi OTP" />

    <div class="min-h-screen bg-gradient-to-b from-blue-600 to-blue-800 flex flex-col">
        <!-- Header -->
        <div class="flex-1 flex flex-col items-center justify-center px-6 py-12">
            <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mb-6 shadow-lg">
                <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-white text-center">
                Verifikasi OTP
            </h1>
            <p class="text-blue-100 text-center mt-2">
                Masukkan kode 6 digit yang dikirim ke
            </p>
            <p class="text-white font-semibold mt-1">
                {{ phone_masked }}
            </p>
        </div>

        <!-- OTP Form -->
        <div class="bg-white rounded-t-3xl px-6 py-8">
            <!-- Success Message -->
            <div v-if="message" class="mb-5 p-3 bg-green-50 border border-green-200 rounded-xl">
                <p class="text-green-700 text-sm text-center">
                    {{ message }}
                </p>
            </div>

            <!-- Error Message -->
            <div v-if="$page.props.flash?.error" class="mb-5 p-3 bg-red-50 border border-red-200 rounded-xl">
                <p class="text-red-700 text-sm text-center">
                    {{ $page.props.flash.error }}
                </p>
            </div>

            <form @submit.prevent="submit">
                <!-- OTP Input -->
                <div class="flex justify-center gap-2 mb-2">
                    <input
                        v-for="(digit, index) in otpDigits"
                        :key="index"
                        :ref="el => otpInputs[index] = el"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        :value="otpDigits[index]"
                        @input="handleInput(index, $event)"
                        @keydown="handleKeydown(index, $event)"
                        @paste="handlePaste"
                        class="w-12 h-14 text-center text-xl font-bold rounded-xl border-2 border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        :class="{ 'border-red-400': form.errors.otp }"
                    >
                </div>

                <p v-if="form.errors.otp" class="text-sm text-red-500 text-center mb-4">
                    {{ form.errors.otp }}
                </p>

                <button
                    type="submit"
                    :disabled="form.processing || form.otp.length < 6"
                    class="w-full py-3 mt-4 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors flex items-center justify-center gap-2"
                >
                    <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ form.processing ? 'Memverifikasi...' : 'Verifikasi' }}</span>
                </button>
            </form>

            <!-- Resend OTP -->
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-500">
                    Tidak menerima kode?
                </p>
                <button
                    v-if="canResend"
                    @click="resendOTP"
                    class="mt-2 text-blue-600 font-semibold text-sm hover:underline"
                >
                    Kirim Ulang OTP
                </button>
                <p v-else class="mt-2 text-sm text-gray-400">
                    Kirim ulang dalam <span class="font-semibold text-gray-600">{{ countdown }}</span> detik
                </p>
            </div>

            <!-- Back to Login -->
            <div class="mt-6 pt-6 border-t border-gray-200 text-center">
                <a
                    :href="route('customer.login')"
                    class="text-sm text-gray-500 hover:text-gray-700"
                >
                    &larr; Kembali ke halaman login
                </a>
            </div>
        </div>
    </div>
</template>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
        <div class="text-center">
            <h1 class="text-6xl font-bold text-gray-300">{{ status }}</h1>
            <p class="mt-4 text-lg text-gray-600">{{ message }}</p>
            <div class="mt-8 flex gap-3 justify-center">
                <button
                    @click="goBack"
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
                >
                    Kembali
                </button>
                <a
                    href="/login"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                >
                    Login
                </a>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    status: Number,
    message: String,
});

const goBack = () => {
    window.history.back();
};
</script>

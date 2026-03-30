import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

const isNavigating = ref(false)
let showTimer = null
let fallbackTimer = null

export function useNavigationLoading() {
    const startLoading = () => {
        showTimer = setTimeout(() => {
            isNavigating.value = true
        }, 250)

        // Fallback: force hide after 10s in case finish event never fires
        fallbackTimer = setTimeout(() => {
            isNavigating.value = false
        }, 10000)
    }

    const stopLoading = () => {
        clearTimeout(showTimer)
        clearTimeout(fallbackTimer)
        isNavigating.value = false
    }

    onMounted(() => {
        router.on('start', startLoading)
        router.on('finish', stopLoading)
        router.on('error', stopLoading)
        router.on('invalid', stopLoading)
    })

    onUnmounted(() => {
        router.off('start', startLoading)
        router.off('finish', stopLoading)
        router.off('error', stopLoading)
        router.off('invalid', stopLoading)
        clearTimeout(showTimer)
        clearTimeout(fallbackTimer)
    })

    return { isNavigating }
}

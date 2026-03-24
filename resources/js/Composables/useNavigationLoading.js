import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

const isNavigating = ref(false)
let timer = null

export function useNavigationLoading() {
    const startLoading = () => {
        // Only show overlay if navigation takes > 250ms (avoid flicker on fast pages)
        timer = setTimeout(() => {
            isNavigating.value = true
        }, 250)
    }

    const stopLoading = () => {
        clearTimeout(timer)
        isNavigating.value = false
    }

    onMounted(() => {
        router.on('start', startLoading)
        router.on('finish', stopLoading)
    })

    onUnmounted(() => {
        router.off('start', startLoading)
        router.off('finish', stopLoading)
        clearTimeout(timer)
    })

    return { isNavigating }
}

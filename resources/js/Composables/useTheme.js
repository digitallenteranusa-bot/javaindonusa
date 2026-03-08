import { ref, watch } from 'vue'

const isDark = ref(localStorage.getItem('theme') === 'dark')

const applyTheme = (dark) => {
    if (dark) {
        document.documentElement.classList.add('dark')
    } else {
        document.documentElement.classList.remove('dark')
    }
}

// Apply on load
applyTheme(isDark.value)

watch(isDark, (val) => {
    localStorage.setItem('theme', val ? 'dark' : 'light')
    applyTheme(val)
})

export function useTheme() {
    const toggleTheme = () => {
        isDark.value = !isDark.value
    }

    return { isDark, toggleTheme }
}

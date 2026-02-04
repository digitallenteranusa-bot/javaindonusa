import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

/**
 * Composable for checking user permissions
 */
export function usePermission() {
    const page = usePage()

    /**
     * Check if user has a specific permission
     * Admin role always returns true
     */
    const hasPermission = (permission) => {
        const user = page.props.auth?.user
        if (!user) return false

        // Admin has all permissions
        if (user.role === 'admin') return true

        // Check if user has the permission
        return user.permissions?.includes(permission) ?? false
    }

    /**
     * Check if user has any of the given permissions
     */
    const hasAnyPermission = (permissions) => {
        const user = page.props.auth?.user
        if (!user) return false

        // Admin has all permissions
        if (user.role === 'admin') return true

        // Check if user has any of the permissions
        return permissions.some(p => user.permissions?.includes(p) ?? false)
    }

    /**
     * Check if user has all of the given permissions
     */
    const hasAllPermissions = (permissions) => {
        const user = page.props.auth?.user
        if (!user) return false

        // Admin has all permissions
        if (user.role === 'admin') return true

        // Check if user has all permissions
        return permissions.every(p => user.permissions?.includes(p) ?? false)
    }

    /**
     * Get user's role
     */
    const userRole = computed(() => page.props.auth?.user?.role ?? null)

    /**
     * Check if user is admin
     */
    const isAdmin = computed(() => userRole.value === 'admin')

    /**
     * Get all user permissions
     */
    const permissions = computed(() => page.props.auth?.user?.permissions ?? [])

    return {
        hasPermission,
        hasAnyPermission,
        hasAllPermissions,
        userRole,
        isAdmin,
        permissions,
    }
}

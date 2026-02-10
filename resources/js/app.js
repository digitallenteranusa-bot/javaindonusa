import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'ISP Billing';

// Handle Inertia global errors (session expired, server errors)
router.on('invalid', (event) => {
    // When server returns non-Inertia response (e.g. login page HTML after session expired)
    event.preventDefault();
    const currentPath = window.location.pathname;
    if (currentPath.startsWith('/portal')) {
        window.location.href = '/portal/login';
    } else {
        window.location.href = '/login';
    }
});

router.on('exception', (event) => {
    // Network error or server unreachable
    event.preventDefault();
    // Show alert instead of blank page
    if (typeof window !== 'undefined' && window.Capacitor?.isNativePlatform?.()) {
        // In native app, show alert with retry option
        if (confirm('Koneksi ke server gagal. Coba lagi?')) {
            window.location.reload();
        }
    }
});

// Initialize Capacitor plugins (native app only)
const initCapacitor = async () => {
    // Only run if we're in a Capacitor native app (window.Capacitor is injected by native shell)
    if (typeof window !== 'undefined' && window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) {
        try {
            // Hide splash screen after app is ready
            const { SplashScreen } = await import('@capacitor/splash-screen');
            await SplashScreen.hide();

            // Setup status bar (Android)
            if (window.Capacitor.getPlatform() === 'android') {
                const { StatusBar, Style } = await import('@capacitor/status-bar');
                await StatusBar.setStyle({ style: Style.Dark });
                await StatusBar.setBackgroundColor({ color: '#1e40af' });
            }

            // Handle Android back button
            const { App } = await import('@capacitor/app');
            App.addListener('backButton', ({ canGoBack }) => {
                if (canGoBack) {
                    window.history.back();
                } else {
                    App.exitApp();
                }
            });
        } catch (e) {
            // Capacitor plugins not available
            console.log('Capacitor plugins not available:', e.message);
        }
    }
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);

        // Initialize Capacitor after app is mounted
        initCapacitor();

        return app;
    },
    progress: {
        color: '#3B82F6',
    },
});

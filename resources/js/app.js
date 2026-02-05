import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'ISP Billing';

// Initialize Capacitor plugins (native app only)
const initCapacitor = async () => {
    try {
        const { Capacitor } = await import('@capacitor/core');

        if (Capacitor.isNativePlatform()) {
            // Hide splash screen after app is ready
            const { SplashScreen } = await import('@capacitor/splash-screen');
            await SplashScreen.hide();

            // Setup status bar (Android)
            if (Capacitor.getPlatform() === 'android') {
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
        }
    } catch (e) {
        // Capacitor not available (running in web browser)
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

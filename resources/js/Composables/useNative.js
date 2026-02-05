import { ref, onMounted } from 'vue';

/**
 * Composable untuk mengakses fitur native Capacitor
 * Fitur: deteksi platform, network status, haptic feedback, dll
 */
export function useNative() {
    const isNative = ref(false);
    const platform = ref('web');
    const isOnline = ref(true);

    // Deteksi apakah running di native app
    const detectPlatform = async () => {
        try {
            const { Capacitor } = await import('@capacitor/core');
            isNative.value = Capacitor.isNativePlatform();
            platform.value = Capacitor.getPlatform();
        } catch (e) {
            isNative.value = false;
            platform.value = 'web';
        }
    };

    // Setup network listener
    const setupNetworkListener = async () => {
        try {
            const { Network } = await import('@capacitor/network');

            // Get initial status
            const status = await Network.getStatus();
            isOnline.value = status.connected;

            // Listen for changes
            Network.addListener('networkStatusChange', (status) => {
                isOnline.value = status.connected;
            });
        } catch (e) {
            // Fallback to browser API
            isOnline.value = navigator.onLine;
            window.addEventListener('online', () => isOnline.value = true);
            window.addEventListener('offline', () => isOnline.value = false);
        }
    };

    // Haptic feedback
    const hapticFeedback = async (type = 'light') => {
        if (!isNative.value) return;

        try {
            const { Haptics, ImpactStyle } = await import('@capacitor/haptics');

            switch (type) {
                case 'light':
                    await Haptics.impact({ style: ImpactStyle.Light });
                    break;
                case 'medium':
                    await Haptics.impact({ style: ImpactStyle.Medium });
                    break;
                case 'heavy':
                    await Haptics.impact({ style: ImpactStyle.Heavy });
                    break;
                case 'success':
                    await Haptics.notification({ type: 'SUCCESS' });
                    break;
                case 'warning':
                    await Haptics.notification({ type: 'WARNING' });
                    break;
                case 'error':
                    await Haptics.notification({ type: 'ERROR' });
                    break;
            }
        } catch (e) {
            // Haptics not available
        }
    };

    // Hide splash screen
    const hideSplash = async () => {
        if (!isNative.value) return;

        try {
            const { SplashScreen } = await import('@capacitor/splash-screen');
            await SplashScreen.hide();
        } catch (e) {
            // Splash screen not available
        }
    };

    // Set status bar style
    const setStatusBar = async (style = 'dark', color = '#1e40af') => {
        if (!isNative.value) return;

        try {
            const { StatusBar, Style } = await import('@capacitor/status-bar');
            await StatusBar.setStyle({ style: style === 'dark' ? Style.Dark : Style.Light });
            if (platform.value === 'android') {
                await StatusBar.setBackgroundColor({ color });
            }
        } catch (e) {
            // Status bar not available
        }
    };

    // Exit app (Android only)
    const exitApp = async () => {
        if (!isNative.value || platform.value !== 'android') return;

        try {
            const { App } = await import('@capacitor/app');
            await App.exitApp();
        } catch (e) {
            // Exit not available
        }
    };

    // Handle back button (Android)
    const setupBackButton = async (callback) => {
        if (!isNative.value || platform.value !== 'android') return;

        try {
            const { App } = await import('@capacitor/app');
            App.addListener('backButton', callback);
        } catch (e) {
            // Back button listener not available
        }
    };

    onMounted(async () => {
        await detectPlatform();
        await setupNetworkListener();
        await hideSplash();
    });

    return {
        isNative,
        platform,
        isOnline,
        hapticFeedback,
        hideSplash,
        setStatusBar,
        exitApp,
        setupBackButton,
    };
}

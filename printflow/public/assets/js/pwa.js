/**
 * PWA Registration and Installation
 * PrintFlow - Printing Shop PWA
 */

// Register service worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/printflow/public/sw.js')
            .then((registration) => {
                console.log('[PWA] Service Worker registered:', registration.scope);

                // Check for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    console.log('[PWA] New Service Worker found');

                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New service worker available, show update notification
                            showUpdateNotification();
                        }
                    });
                });
            })
            .catch((error) => {
                console.error('[PWA] Service Worker registration failed:', error);
            });
    });
}

// Show update notification
function showUpdateNotification() {
    if (confirm('A new version of PrintFlow is available. Reload to update?')) {
        window.location.reload();
    }
}

// Install prompt handling
let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
    console.log('[PWA] Install prompt triggered');

    // Prevent automatic prompt
    e.preventDefault();
    deferredPrompt = e;

    // Show custom install button
    showInstallButton();
});

function showInstallButton() {
    const installButton = document.getElementById('install-button');
    if (installButton) {
        installButton.style.display = 'block';

        installButton.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();

                const { outcome } = await deferredPrompt.userChoice;
                console.log('[PWA] User choice:', outcome);

                deferredPrompt = null;
                installButton.style.display = 'none';
            }
        });
    }
}

// Detect app installation
window.addEventListener('appinstalled', () => {
    console.log('[PWA] App installed successfully');
    deferredPrompt = null;

    // Hide install button
    const installButton = document.getElementById('install-button');
    if (installButton) {
        installButton.style.display = 'none';
    }
});

// Check if app is already installed
if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
    console.log('[PWA] App is running in standalone mode');
}

// Push notification subscription (optional)
async function subscribeToPushNotifications() {
    if ('PushManager' in window && 'serviceWorker' in navigator) {
        try {
            const registration = await navigator.serviceWorker.ready;

            // Check if already subscribed
            let subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                // Request permission
                const permission = await Notification.requestPermission();

                if (permission === 'granted') {
                    // TODO: Replace with your VAPID public key
                    const vapidPublicKey = 'YOUR_VAPID_PUBLIC_KEY';

                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
                    });

                    console.log('[PWA] Push subscription:', subscription);

                    // Send subscription to server
                    await sendSubscriptionToServer(subscription);
                }
            }

            return subscription;
        } catch (error) {
            console.error('[PWA] Push subscription failed:', error);
        }
    }
}

// Convert VAPID key
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

// Send subscription to server
async function sendSubscriptionToServer(subscription) {
    try {
        const response = await fetch('/printflow/api/push-subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(subscription)
        });

        if (response.ok) {
            console.log('[PWA] Subscription sent to server');
        }
    } catch (error) {
        console.error('[PWA] Failed to send subscription:', error);
    }
}

// Offline detection
window.addEventListener('online', () => {
    console.log('[PWA] Back online');
    hideOfflineNotification();
});

window.addEventListener('offline', () => {
    console.log('[PWA] Gone offline');
    showOfflineNotification();
});

function showOfflineNotification() {
    let notification = document.getElementById('offline-notification');

    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'offline-notification';
        notification.className = 'fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
        notification.innerHTML = `
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824 2.167a1 1 0 111.414 1.414m-1.414-1.414L3 3"></path>
                </svg>
                <span>You are offline. Some features may be unavailable.</span>
            </div>
        `;
        document.body.appendChild(notification);
    }
}

function hideOfflineNotification() {
    const notification = document.getElementById('offline-notification');
    if (notification) {
        notification.remove();
    }
}

// Auto-subscribe to push notifications on login (optional)
// Uncomment when ready to implement push notifications
// if (document.body.dataset.userLoggedIn === 'true') {
//     subscribeToPushNotifications();
// }

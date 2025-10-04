import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo;
    }
}

window.Pusher = Pusher;

// Only create echo if broadcasting is enabled
let echo: Echo | null = null;

try {
    echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY || 'local',
        wsHost: import.meta.env.VITE_PUSHER_HOST || '127.0.0.1',
        wsPort: import.meta.env.VITE_PUSHER_PORT || 8080,
        wssPort: import.meta.env.VITE_PUSHER_PORT || 8080,
        forceTLS: false,
        encrypted: false,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        cluster: 'local',
        disableAutoConnect: true, // Don't auto-connect to avoid errors
    });

    window.Echo = echo;
} catch (error) {
    console.warn('Broadcasting not configured:', error);
}

export default echo;

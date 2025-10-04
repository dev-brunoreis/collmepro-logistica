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
        disableAutoConnect: false, // Enable auto-connect
    });

    // Add connection event listeners for debugging
    echo.connector.pusher.connection.bind('connected', () => {
        console.log('Echo: Connected to server');
    });

    echo.connector.pusher.connection.bind('disconnected', () => {
        console.log('Echo: Disconnected from server');
    });

    echo.connector.pusher.connection.bind('error', (error: any) => {
        console.error('Echo: Connection error', error);
    });

    window.Echo = echo;
    console.log('Echo initialized successfully');
} catch (error) {
    console.warn('Broadcasting not configured:', error);
}

export default echo;

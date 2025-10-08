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
    // Detect if we're in production (HTTPS)
    const isProduction = window.location.protocol === 'https:';
    const pusherHost = import.meta.env.VITE_PUSHER_HOST || window.location.hostname;
    const pusherPort = import.meta.env.VITE_PUSHER_PORT ? parseInt(import.meta.env.VITE_PUSHER_PORT) : (isProduction ? 443 : 8080);
    const pusherScheme = import.meta.env.VITE_PUSHER_SCHEME || (isProduction ? 'https' : 'http');
    
    echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY || 'local',
        wsHost: pusherHost,
        wsPort: pusherPort,
        wssPort: pusherPort,
        forceTLS: isProduction,
        encrypted: isProduction,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        cluster: import.meta.env.VITE_PUSHER_CLUSTER || 'mt1',
        disableAutoConnect: false,
        // Add reconnection settings
        activityTimeout: 30000, // 30 seconds
        pongTimeout: 6000, // 6 seconds
        unavailableTimeout: 10000, // 10 seconds
    });

    // Add connection event listeners for debugging and management
    echo.connector.pusher.connection.bind('connected', () => {
        console.log('Echo: Connected to server', { host: pusherHost, port: pusherPort, scheme: pusherScheme });
    });

    echo.connector.pusher.connection.bind('disconnected', () => {
        console.log('Echo: Disconnected from server');
    });

    echo.connector.pusher.connection.bind('error', (error: any) => {
        console.error('Echo: Connection error', error);
    });

    echo.connector.pusher.connection.bind('connecting', () => {
        console.log('Echo: Connecting to server...', { host: pusherHost, port: pusherPort, tls: isProduction });
    });

    echo.connector.pusher.connection.bind('unavailable', () => {
        console.warn('Echo: Connection unavailable');
    });

    // Add global error handler
    echo.connector.pusher.connection.bind('failed', (error: any) => {
        console.error('Echo: Connection failed', error);
    });

    window.Echo = echo;
    console.log('Echo initialized successfully', {
        environment: isProduction ? 'production' : 'development',
        host: pusherHost,
        port: pusherPort,
        tls: isProduction
    });
} catch (error) {
    console.warn('Broadcasting not configured:', error);
}

export default echo;

import { ref, onMounted, onUnmounted } from 'vue';

interface OrderStatusUpdate {
    order: {
        id: number;
        numero: string;
        contato_nome: string;
    };
    user: {
        id: number;
        name: string;
    };
    action: string;
    action_name: string;
    status: boolean;
    timestamp: string;
}

export function useOrderBroadcasting() {
    const isConnected = ref(false);
    const lastUpdate = ref<OrderStatusUpdate | null>(null);
    const connectionRetryCount = ref(0);
    const maxRetries = 3;
    let retryTimeout: NodeJS.Timeout | null = null;

    const connectToOrdersChannel = async () => {
        try {
            // Dynamically import echo to avoid errors if broadcasting is not configured
            const { default: echo } = await import('../echo');
            
            if (!echo) {
                console.warn('Broadcasting not configured');
                isConnected.value = false;
                return;
            }
            
            console.log('Echo loaded, connecting to orders channel...');
            
            // Check if already connected to avoid duplicate connections
            if (echo.connector.pusher.connection.state === 'connected') {
                console.log('Already connected, setting up channel listeners...');
                setupChannelListeners(echo);
                isConnected.value = true;
                return;
            }
            
            // Wait for connection to be established
            await new Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    reject(new Error('Connection timeout'));
                }, 5000);
                
                if (echo.connector.pusher.connection.state === 'connected') {
                    clearTimeout(timeout);
                    resolve(true);
                } else {
                    echo.connector.pusher.connection.bind('connected', () => {
                        clearTimeout(timeout);
                        resolve(true);
                    });
                    
                    echo.connector.pusher.connection.bind('error', (error: any) => {
                        clearTimeout(timeout);
                        reject(error);
                    });
                }
            });
            
            setupChannelListeners(echo);
            isConnected.value = true;
            connectionRetryCount.value = 0;
            console.log('Connected to orders channel');
        } catch (error) {
            console.warn('Broadcasting connection failed:', error);
            isConnected.value = false;
            
            // Retry connection with exponential backoff
            if (connectionRetryCount.value < maxRetries) {
                connectionRetryCount.value++;
                const delay = Math.pow(2, connectionRetryCount.value) * 1000; // 2s, 4s, 8s
                console.log(`Retrying connection in ${delay}ms (attempt ${connectionRetryCount.value}/${maxRetries})`);
                
                retryTimeout = setTimeout(() => {
                    connectToOrdersChannel();
                }, delay);
            } else {
                console.error('Max retry attempts reached, giving up');
            }
        }
    };

    const setupChannelListeners = (echo: any) => {
        // Leave existing channel if any
        echo.leaveChannel('orders');
        
        // Listen to the orders channel for all order updates
        const channel = echo.channel('orders');
        
        channel.listen('status.updated', (data: any) => {
            console.log('Raw data received:', data);
            
            // Parse the data if it's a string (double-encoded JSON)
            let parsedData: OrderStatusUpdate;
            if (typeof data.data === 'string') {
                parsedData = JSON.parse(data.data);
            } else {
                parsedData = data.data || data;
            }
            
            console.log('Parsed data:', parsedData);
            lastUpdate.value = parsedData;
            console.log('Order status updated:', parsedData);
        });

        // Also listen to the event with a different syntax
        channel.listen('.status.updated', (data: any) => {
            lastUpdate.value = data;
        });
    };

    const connectToSpecificOrder = async (orderId: number) => {
        try {
            const { default: echo } = await import('../echo');
            // Listen to specific order channel
            echo.channel(`orders.${orderId}`)
                .listen('status.updated', (data: any) => {
                    // Parse the data if it's a string (double-encoded JSON)
                    let parsedData: OrderStatusUpdate;
                    if (typeof data.data === 'string') {
                        parsedData = JSON.parse(data.data);
                    } else {
                        parsedData = data.data || data;
                    }
                    
                    lastUpdate.value = parsedData;
                    console.log(`Order ${orderId} status updated:`, parsedData);
                });

            console.log(`Connected to order ${orderId} channel`);
        } catch (error) {
            console.warn(`Failed to connect to order ${orderId} channel:`, error);
        }
    };

    const disconnectFromOrdersChannel = async () => {
        try {
            const { default: echo } = await import('../echo');
            echo.leaveChannel('orders');
            isConnected.value = false;
            console.log('Disconnected from orders channel');
        } catch (error) {
            console.warn('Failed to disconnect from orders channel:', error);
        }
    };

    const disconnectFromSpecificOrder = async (orderId: number) => {
        try {
            const { default: echo } = await import('../echo');
            echo.leaveChannel(`orders.${orderId}`);
            console.log(`Disconnected from order ${orderId} channel`);
        } catch (error) {
            console.warn(`Failed to disconnect from order ${orderId} channel:`, error);
        }
    };

    const disconnectAll = async () => {
        try {
            // Clear any pending retry timeout
            if (retryTimeout) {
                clearTimeout(retryTimeout);
                retryTimeout = null;
            }
            
            const { default: echo } = await import('../echo');
            if (echo) {
                echo.leaveChannel('orders');
                // Don't disconnect completely to allow reconnection
                // echo.disconnect();
            }
            isConnected.value = false;
            console.log('Disconnected from orders channel');
        } catch (error) {
            console.warn('Failed to disconnect:', error);
        }
    };

    onMounted(async () => {
        await connectToOrdersChannel();
    });

    onUnmounted(() => {
        disconnectAll();
    });

    return {
        isConnected,
        lastUpdate,
        connectToOrdersChannel,
        connectToSpecificOrder,
        disconnectFromOrdersChannel,
        disconnectFromSpecificOrder,
        disconnectAll,
    };
}

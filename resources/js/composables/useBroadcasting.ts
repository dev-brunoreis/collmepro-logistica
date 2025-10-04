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
            
            // Wait a bit for connection to be established
            await new Promise(resolve => setTimeout(resolve, 1000));
            
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

            isConnected.value = true;
            console.log('Connected to orders channel');
        } catch (error) {
            console.warn('Broadcasting not available:', error);
            isConnected.value = false;
        }
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
            const { default: echo } = await import('../echo');
            echo.disconnect();
            isConnected.value = false;
            console.log('Disconnected from all channels');
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

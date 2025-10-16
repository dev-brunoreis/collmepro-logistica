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

interface NewOrder {
    order: {
        id: number;
        bling_id: number;
        numero: string;
        contato_nome: string;
        total: number;
        data: string;
        source: string;
        invoice_number?: string;
        items_quantity: number;
        tracking_code?: string;
        tracking_url?: string;
        order_status?: {
            picking: boolean;
            packing: boolean;
            picked_up: boolean;
            picking_marked_by?: string;
            picking_marked_at?: string;
            packing_marked_by?: string;
            packing_marked_at?: string;
            picked_up_marked_by?: string;
            picked_up_marked_at?: string;
        };
    };
    user: {
        id: number;
        name: string;
    };
    timestamp: string;
}

interface OrderUpdate {
    order: {
        id: number;
        bling_id: number;
        numero: string;
        contato_nome: string;
        total: number;
        data: string;
        source: string;
        invoice_number?: string;
        items_quantity: number;
        tracking_code?: string;
        tracking_url?: string;
        situacao_id?: number;
        situacao_valor?: number;
        order_status?: {
            picking: boolean;
            packing: boolean;
            picked_up: boolean;
            picking_marked_by?: string;
            picking_marked_at?: string;
            packing_marked_by?: string;
            packing_marked_at?: string;
            picked_up_marked_by?: string;
            picked_up_marked_at?: string;
        };
    };
    user: {
        id: number;
        name: string;
    };
    changed_fields: string[];
    change_types: string[];
    timestamp: string;
}

interface SyncStatusUpdate {
    is_running: boolean;
    last_sync_at: string | null;
    next_sync_at: string | null;
    account_type: string;
    timestamp: string;
}

export function useOrderBroadcasting() {
    const isConnected = ref(false);
    const lastUpdate = ref<OrderStatusUpdate | null>(null);
    const lastNewOrder = ref<NewOrder | null>(null);
    const lastOrderUpdate = ref<OrderUpdate | null>(null);
    const lastSyncStatus = ref<SyncStatusUpdate | null>(null);
    const connectionRetryCount = ref(0);
    const maxRetries = 3;
    let retryTimeout: ReturnType<typeof setTimeout> | null = null;

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
        // Leave existing channels if any
        echo.leaveChannel('orders');
        echo.leaveChannel('sync-status');
        
        // Listen to the orders channel for all order updates
        const ordersChannel = echo.channel('orders');
        
        // Listen to the sync-status channel for sync status updates
        const syncStatusChannel = echo.channel('sync-status');
        
        // Listen for order status updates
        ordersChannel.listen('status.updated', (data: any) => {
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
        ordersChannel.listen('.status.updated', (data: any) => {
            lastUpdate.value = data;
        });

        // Listen for sync status updates
        syncStatusChannel.listen('sync-status.updated', (data: any) => {
            console.log('Sync status updated:', data);
            lastSyncStatus.value = data;
        });

        // Listen for new orders
        ordersChannel.listen('order.created', (data: any) => {
            console.log('New order received:', data);
            
            // Parse the data if it's a string (double-encoded JSON)
            let parsedData: NewOrder;
            if (typeof data.data === 'string') {
                parsedData = JSON.parse(data.data);
            } else {
                parsedData = data.data || data;
            }
            
            console.log('Parsed new order data:', parsedData);
            lastNewOrder.value = parsedData;
            console.log('New order created:', parsedData);
        });

        // Also listen to the event with a different syntax
        ordersChannel.listen('.order.created', (data: any) => {
            lastNewOrder.value = data;
        });

        // Listen for order updates
        ordersChannel.listen('order.updated', (data: any) => {
            console.log('Order update received:', data);
            
            // Parse the data if it's a string (double-encoded JSON)
            let parsedData: OrderUpdate;
            if (typeof data.data === 'string') {
                parsedData = JSON.parse(data.data);
            } else {
                parsedData = data.data || data;
            }
            
            console.log('Parsed order update data:', parsedData);
            lastOrderUpdate.value = parsedData;
            console.log('Order updated:', parsedData);
        });

        // Also listen to the event with a different syntax
        ordersChannel.listen('.order.updated', (data: any) => {
            lastOrderUpdate.value = data;
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
        lastNewOrder,
        lastOrderUpdate,
        lastSyncStatus,
        connectToOrdersChannel,
        connectToSpecificOrder,
        disconnectFromOrdersChannel,
        disconnectFromSpecificOrder,
        disconnectAll,
    };
}

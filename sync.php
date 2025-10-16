<?php

/**
 * DEPRECATED: This file contains the original synchronization concept.
 * 
 * The system now uses an event-driven architecture implemented in Laravel.
 * 
 * NEW ARCHITECTURE (Event-Driven):
 * ================================
 * 
 * 1. BlingSyncJob (app/Jobs/BlingSyncJob.php)
 *    - Fetches and saves orders from Bling
 *    - Emits: OrderSynced event
 * 
 * 2. OrderSynced Event Listeners:
 *    - QueueInvoiceSync → Dispatches SyncInvoiceJob
 *    - QueueSellerSync → Dispatches SyncSellerJob
 * 
 * 3. SyncInvoiceJob (app/Jobs/SyncInvoiceJob.php)
 *    - Fetches invoice (NFe) from Bling for specific order
 *    - Emits: InvoiceSynced event
 *    - Queue: invoices
 * 
 * 4. InvoiceSynced Event Listener:
 *    - QueueTrackingExtraction → Dispatches ExtractTrackingJob
 * 
 * 5. ExtractTrackingJob (app/Jobs/ExtractTrackingJob.php)
 *    - Extracts tracking from invoice data
 *    - Emits: TrackingSynced event
 *    - Queue: tracking
 * 
 * 6. SyncSellerJob (app/Jobs/SyncSellerJob.php)
 *    - Fetches seller information from WooCommerce
 *    - Emits: SellerSynced event
 *    - Queue: sellers
 * 
 * BENEFITS:
 * - 5-10x faster (parallel processing)
 * - Independent job failures (resilience)
 * - Easy to scale (add more workers)
 * - Automatic retries on failures
 * - Better error tracking
 * 
 * USAGE:
 * 
 * Manual sync:
 *   php artisan bling:sync
 * 
 * Start queue workers:
 *   php artisan queue:work --queue=invoices,tracking,sellers
 * 
 * Monitor queues:
 *   php artisan queue:monitor invoices tracking sellers
 * 
 * See docs/07-event-driven-sync.md for complete documentation.
 */

class BlingSync
{
    public function run()
    {
        throw new \Exception(
            'This synchronization method is deprecated. ' .
            'Use: php artisan bling:sync and php artisan queue:work'
        );
    }
}
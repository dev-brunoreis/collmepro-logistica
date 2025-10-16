# Event-Driven Synchronization Architecture

## Overview

The Collmepro Logistics System uses an **event-driven architecture** for synchronizing data from external systems (Bling, WooCommerce). This approach provides better performance, scalability, and resilience compared to monolithic synchronization.

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                 LAYER 1: Order Sync                          │
└─────────────────────────────────────────────────────────────┘

BlingSyncJob (Scheduled/Manual)
  ├─> Fetches orders from Bling API
  ├─> Saves/Updates OrderSummary
  └─> Emits: OrderSynced (internal event)
       └─> Broadcasts: OrderCreated/OrderUpdated (frontend)

┌─────────────────────────────────────────────────────────────┐
│            LAYER 2: Parallel Processing                      │
└─────────────────────────────────────────────────────────────┘

OrderSynced Event Triggers:
  ├─> QueueInvoiceSync → SyncInvoiceJob (queue: invoices)
  │   └─> Fetches NFe from Bling
  │       └─> Emits: InvoiceSynced
  │           └─> Broadcasts: OrderUpdated (frontend)
  │
  └─> QueueSellerSync → SyncSellerJob (queue: sellers)
      └─> Fetches seller from WooCommerce
          └─> Emits: SellerSynced

InvoiceSynced Event Triggers:
  └─> QueueTrackingExtraction → ExtractTrackingJob (queue: tracking)
      └─> Extracts tracking from invoice data
          └─> Emits: TrackingSynced
              └─> Broadcasts: OrderUpdated (frontend)

┌─────────────────────────────────────────────────────────────┐
│              LAYER 3: Frontend Updates                       │
└─────────────────────────────────────────────────────────────┘

Vue.js/Inertia.js (Kanban, Orders, Dashboard)
  └─> Listens to Laravel Reverb websockets
      ├─> OrderCreated → Add new order to Kanban
      └─> OrderUpdated → Update order in Kanban
          ├─> Invoice added → Move to "Faturamento"
          ├─> Tracking added → Move to "Etiqueta"
          └─> Status changed → Move to appropriate column
```

## Components

### Events (Internal - No Broadcast)

Located in `app/Events/`:

#### OrderSynced
- **Trigger**: After order is saved/updated in database
- **Purpose**: Coordinate downstream synchronization jobs
- **Data**: OrderSummary, wasCreated, changedFields

#### InvoiceSynced
- **Trigger**: After invoice is fetched and saved
- **Purpose**: Trigger tracking extraction
- **Data**: Invoice, wasCreated

#### TrackingSynced
- **Trigger**: After tracking is extracted and saved
- **Purpose**: Mark completion of sync chain
- **Data**: OrderSummary, trackingCode

#### SellerSynced
- **Trigger**: After seller information is synced
- **Purpose**: Mark seller sync completion
- **Data**: OrderSummary, Seller

### Event Listeners

Located in `app/Listeners/`:

#### QueueInvoiceSync
- **Listens to**: OrderSynced
- **Action**: Dispatches SyncInvoiceJob to `invoices` queue
- **Purpose**: Fetch invoice (NFe) from Bling for the order

#### QueueTrackingExtraction
- **Listens to**: InvoiceSynced
- **Action**: Dispatches ExtractTrackingJob to `tracking` queue
- **Purpose**: Extract tracking information from invoice data

#### QueueSellerSync
- **Listens to**: OrderSynced
- **Action**: Dispatches SyncSellerJob to `sellers` queue
- **Purpose**: Fetch seller information from WooCommerce

### Jobs (Queue Processing)

Located in `app/Jobs/`:

#### SyncInvoiceJob
- **Queue**: `invoices`
- **Timeout**: 60 seconds
- **Retries**: 3 attempts
- **Backoff**: 30 seconds
- **Idempotency**: Cache check (5 minutes)
- **Purpose**: Fetch and store invoice (NFe) from Bling
- **Emits**: InvoiceSynced, OrderUpdated (broadcast)

#### ExtractTrackingJob
- **Queue**: `tracking`
- **Timeout**: 45 seconds
- **Retries**: 3 attempts
- **Backoff**: 20 seconds
- **Idempotency**: Cache check (5 minutes)
- **Purpose**: Extract tracking information from invoice volumes
- **Emits**: TrackingSynced, OrderUpdated (broadcast)

#### SyncSellerJob
- **Queue**: `sellers`
- **Timeout**: 30 seconds
- **Retries**: 2 attempts
- **Backoff**: 15 seconds
- **Idempotency**: Cache check (10 minutes)
- **Purpose**: Fetch seller information from WooCommerce
- **Emits**: SellerSynced

## Queue Configuration

### Database Tables

- `jobs` - Active queue jobs
- `failed_jobs` - Laravel's standard failed jobs table
- `failed_sync_jobs` - Custom tracking for failed sync operations with:
  - order_summary_id
  - job_type (invoice_sync, tracking_extraction, seller_sync)
  - error message
  - attempts count
  - payload
  - resolved status

### Queue Names

- `default` - General application jobs
- `invoices` - Invoice synchronization (higher priority)
- `tracking` - Tracking extraction (medium priority)
- `sellers` - Seller synchronization (lower priority)

## Running Queue Workers

### Development

```bash
# Single worker for all queues
php artisan queue:work --queue=invoices,tracking,sellers

# Separate workers per queue (recommended)
php artisan queue:work --queue=invoices --tries=3 &
php artisan queue:work --queue=tracking --tries=3 &
php artisan queue:work --queue=sellers --tries=2 &
```

### Production (with Supervisor)

Create `/etc/supervisor/conf.d/logistica-queues.conf`:

```ini
[program:logistica-invoices]
process_name=%(program_name)s_%(process_num)02d
command=php /home/logistica.collmepro.com.br/public_html/artisan queue:work --queue=invoices --tries=3 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/home/logistica.collmepro.com.br/public_html/storage/logs/queue-invoices.log

[program:logistica-tracking]
process_name=%(program_name)s_%(process_num)02d
command=php /home/logistica.collmepro.com.br/public_html/artisan queue:work --queue=tracking --tries=3 --timeout=45
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/home/logistica.collmepro.com.br/public_html/storage/logs/queue-tracking.log

[program:logistica-sellers]
process_name=%(program_name)s_%(process_num)02d
command=php /home/logistica.collmepro.com.br/public_html/artisan queue:work --queue=sellers --tries=2 --timeout=30
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/home/logistica.collmepro.com.br/public_html/storage/logs/queue-sellers.log
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start logistica-invoices:*
sudo supervisorctl start logistica-tracking:*
sudo supervisorctl start logistica-sellers:*
```

## Monitoring

### Queue Status

```bash
# Check queue length
php artisan queue:monitor invoices tracking sellers

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Custom Failed Jobs Tracking

Query the `failed_sync_jobs` table:

```sql
-- View all unresolved failures
SELECT * FROM failed_sync_jobs WHERE resolved = false ORDER BY failed_at DESC;

-- Count failures by type
SELECT job_type, COUNT(*) as total 
FROM failed_sync_jobs 
WHERE resolved = false 
GROUP BY job_type;

-- Recent failures (last 24h)
SELECT * FROM failed_sync_jobs 
WHERE failed_at >= NOW() - INTERVAL 24 HOUR
ORDER BY failed_at DESC;
```

### Logs

Queue processing logs are stored in:
- `storage/logs/queue.log` - General queue processing
- `storage/logs/bling_sync/bling-sync-YYYY-MM-DD.log` - Bling sync specific

## Idempotency

All jobs implement idempotency checks to prevent duplicate processing:

```php
// Example: SyncInvoiceJob
$cacheKey = "invoice_synced_{$this->orderSummaryId}";

if (Cache::has($cacheKey)) {
    return; // Skip, already processed
}

// ... process job ...

Cache::put($cacheKey, now(), 3600); // Mark as processed (1 hour)
```

**Cache Duration:**
- Invoice sync: 1 hour
- Tracking extraction: 1 hour
- Seller sync: 1 hour

## Error Handling

### Automatic Retries

Jobs automatically retry on failure with exponential backoff:

```php
public int $tries = 3;          // Maximum attempts
public int $backoff = 30;       // Seconds between retries
```

### Failed Job Recording

When all retries are exhausted, the job:
1. Records failure in `failed_sync_jobs` table
2. Logs error details
3. Can be manually resolved or retried

### Manual Resolution

```bash
# Retry specific failed job
php artisan queue:retry {job-id}

# Retry all failed jobs
php artisan queue:retry all

# Delete specific failed job
php artisan queue:forget {job-id}
```

Mark as resolved in database:

```sql
UPDATE failed_sync_jobs 
SET resolved = true, 
    resolved_at = NOW(), 
    resolution_notes = 'Fixed and retried manually'
WHERE id = {id};
```

## Performance Metrics

### Before Event-Driven Architecture (Monolithic)
- **100 orders sync**: ~30 minutes
- **Processing**: Sequential (one at a time)
- **Failure impact**: Single failure blocks entire batch
- **Scalability**: Cannot scale (single process)

### After Event-Driven Architecture
- **100 orders sync**: ~2-5 minutes
- **Processing**: Parallel (multiple workers)
- **Failure impact**: Single failure isolated to that job
- **Scalability**: Add more workers as needed

**Performance Improvement: 6-10x faster**

## Best Practices

### 1. Queue Prioritization
Process critical data first:
```bash
php artisan queue:work --queue=invoices,tracking,sellers
```

### 2. Monitor Queue Lengths
Set up alerts for queue backlogs:
```bash
php artisan queue:monitor invoices:100 tracking:50 sellers:30
```

### 3. Regular Maintenance
```bash
# Daily: Clear old failed jobs
php artisan queue:prune-failed --hours=48

# Weekly: Review unresolved failures
# Check failed_sync_jobs table
```

### 4. Resource Management
- **Development**: 1 worker per queue
- **Production**: 2-3 workers per queue (based on load)
- **Monitor**: CPU and memory usage of workers

### 5. Testing Jobs
```bash
# Test specific job
php artisan tinker
>>> dispatch(new \App\Jobs\SyncInvoiceJob(123));

# Monitor in real-time
tail -f storage/logs/queue.log
```

## Troubleshooting

### Queue Not Processing

```bash
# Check if workers are running
ps aux | grep "queue:work"

# Check supervisor status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart logistica-invoices:*
```

### High Failure Rate

1. Check Bling API rate limits (2 req/s)
2. Verify Bling OAuth tokens are valid
3. Review `failed_sync_jobs` for patterns
4. Check network connectivity

### Memory Issues

```bash
# Add memory limit to queue worker
php artisan queue:work --memory=512

# Use supervisor to auto-restart
# (configured in supervisor conf)
```

### Jobs Stuck

```bash
# Clear old jobs
php artisan queue:clear

# Restart all workers
sudo supervisorctl restart all
```

## Migration Notes

The old monolithic sync approach (`sync.php`) is deprecated. The system now:

1. **Automatically** queues specialized jobs when orders sync
2. **Independently** processes invoices, tracking, and sellers
3. **Broadcasts** updates to frontend as each step completes
4. **Tracks** failures for manual resolution

No manual intervention needed - just ensure queue workers are running!

## References

- Laravel Queues: https://laravel.com/docs/11.x/queues
- Laravel Events: https://laravel.com/docs/11.x/events
- Supervisor: http://supervisord.org/


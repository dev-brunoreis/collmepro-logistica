# Bling Two-Stage Sync Implementation

## 📋 Overview

Implemented a two-stage synchronization system for Bling API integration to optimize performance and control data processing flow.

## 🏗️ Architecture

### Stage 1: Order Summary Sync
- **Endpoint**: `/pedidos/vendas`
- **Frequency**: Every 2 minutes
- **Purpose**: Fetch basic order data and store in `order_summary` table
- **Job**: `BlingSummarySyncJob`

### Stage 2: Complete Data Sync
- **Endpoint**: `/pedidos/vendas/{id}`
- **Frequency**: Every 5 minutes
- **Purpose**: Process unprocessed summaries and fetch complete order data
- **Job**: `BlingDataSyncJob`

## 📊 Database Structure

### `order_summary` Table
Stores basic order information from `/pedidos/vendas`:
- Basic order data (id, numero, data, total, etc.)
- Contact information
- Status information
- Control flags (`data_processed`, `external_updated_at`)

### `order_data` Table
Stores complete order information from `/pedidos/vendas/{id}`:
- Extended order details
- Transport information
- Items, parcels, and volumes (JSON)
- Complete raw data for display
- Foreign key to `order_summary`

## 🔄 Sync Flow

1. **Summary Sync** (Every 2 minutes)
   - Fetches order summaries from Bling
   - Stores in `order_summary` table
   - Sets `data_processed = false` for new/updated orders

2. **Data Sync** (Every 5 minutes)
   - Finds unprocessed summaries (`data_processed = false`)
   - Fetches complete order data for each summary
   - Stores in `order_data` table
   - Marks summary as processed (`data_processed = true`)

## 🛠️ Components

### Jobs
- `BlingSummarySyncJob`: Handles stage 1 synchronization
- `BlingDataSyncJob`: Handles stage 2 data processing

### Models
- `OrderSummary`: Model for order_summary table
- `OrderData`: Model for order_data table

### Services
- `BlingService`: Updated with two-stage sync methods
  - `syncSummarySales()`: Stage 1 sync
  - `syncOrderData()`: Stage 2 sync

### Commands
- `bling:sync-summary`: Manual stage 1 sync
- `bling:sync-data`: Manual stage 2 sync

## 📝 Usage

### Manual Sync Commands

```bash
# Sync order summaries (Stage 1)
php artisan bling:sync-summary --days=7 --pages=20

# Sync complete order data (Stage 2)
php artisan bling:sync-data --batch=50
```

### Automatic Sync
Configured in `ScheduleServiceProvider`:
- Summary sync: Every 2 minutes
- Data sync: Every 5 minutes

## 🎯 Benefits

1. **Performance**: Reduces API calls by processing summaries first
2. **Control**: Separate processing stages with independent scheduling
3. **Reliability**: Failed data syncs don't affect summary sync
4. **Monitoring**: Clear separation of sync stages for better debugging
5. **Scalability**: Can adjust batch sizes and frequencies independently

## 🔧 Configuration

### Schedule Frequencies
- Summary sync: `everyTwoMinutes()`
- Data sync: `everyFiveMinutes()`

### Batch Sizes
- Summary sync: 20 pages (2000 orders max)
- Data sync: 50 orders per batch

### Incremental Sync
- Uses `external_updated_at` for incremental sync
- Syncs from 1 hour before last update to catch missed changes

## 📈 Monitoring

### Logs
- Separate log entries for each stage
- Detailed error tracking
- Performance metrics

### Database Flags
- `data_processed`: Tracks processing status
- `external_updated_at`: Tracks external system updates

## 🚀 Next Steps

1. Run migrations to create new tables
2. Test sync commands manually
3. Monitor automatic sync performance
4. Adjust batch sizes and frequencies as needed
5. Update frontend to use new data structure

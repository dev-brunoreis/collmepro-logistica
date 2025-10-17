#!/bin/bash

# Queue Monitoring Script
# This script monitors queue sizes and cleans duplicates if needed

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
LOG_FILE="$PROJECT_DIR/storage/logs/queue-monitor.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to log with timestamp
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Function to check queue status
check_queue_status() {
    local queue_name=$1
    local max_size=${2:-100}
    
    log "Checking queue: $queue_name (max: $max_size)"
    
    # Get queue size
    local size=$(cd "$PROJECT_DIR" && php artisan tinker --execute="echo DB::table('jobs')->where('queue', '$queue_name')->count();" 2>/dev/null | tail -1)
    
    if [ -z "$size" ] || [ "$size" = "0" ]; then
        log "${GREEN}✓${NC} Queue $queue_name: 0 jobs"
        return 0
    fi
    
    log "${BLUE}ℹ${NC} Queue $queue_name: $size jobs"
    
    # Check if queue is too large
    if [ "$size" -gt "$max_size" ]; then
        log "${RED}⚠${NC} Queue $queue_name is too large: $size jobs (max: $max_size)"
        
        # Check for duplicates
        local duplicates=$(cd "$PROJECT_DIR" && php artisan queue:clean-duplicates --queue="$queue_name" --dry-run 2>/dev/null | grep "Found" | awk '{print $2}')
        
        if [ -n "$duplicates" ] && [ "$duplicates" -gt 0 ]; then
            log "${YELLOW}⚠${NC} Found $duplicates duplicate jobs in $queue_name"
            log "${YELLOW}ℹ${NC} Cleaning duplicates..."
            
            # Clean duplicates
            cd "$PROJECT_DIR" && php artisan queue:clean-duplicates --queue="$queue_name" --no-interaction
            
            # Check size after cleaning
            local new_size=$(cd "$PROJECT_DIR" && php artisan tinker --execute="echo DB::table('jobs')->where('queue', '$queue_name')->count();" 2>/dev/null | tail -1)
            log "${GREEN}✓${NC} Queue $queue_name cleaned: $size → $new_size jobs"
        else
            log "${YELLOW}ℹ${NC} No duplicates found in $queue_name"
        fi
        
        return 1
    else
        log "${GREEN}✓${NC} Queue $queue_name is within limits"
        return 0
    fi
}

# Main monitoring function
monitor_queues() {
    log "Starting queue monitoring..."
    
    local exit_code=0
    
    # Check each queue with its limits
    check_queue_status "invoices" 50 || exit_code=1
    check_queue_status "tracking" 30 || exit_code=1
    check_queue_status "sellers" 20 || exit_code=1
    check_queue_status "default" 10 || exit_code=1
    
    if [ $exit_code -eq 0 ]; then
        log "${GREEN}✓${NC} All queues are healthy"
    else
        log "${RED}⚠${NC} Some queues need attention"
    fi
    
    return $exit_code
}

# Function to show queue status
show_status() {
    log "Current queue status:"
    cd "$PROJECT_DIR" && php artisan queue:monitor "invoices,tracking,sellers,default"
}

# Main script logic
case "${1:-monitor}" in
    "monitor")
        monitor_queues
        ;;
    "status")
        show_status
        ;;
    "clean")
        log "Cleaning all queues..."
        cd "$PROJECT_DIR" && php artisan queue:clean-duplicates --queue=invoices --no-interaction
        cd "$PROJECT_DIR" && php artisan queue:clean-duplicates --queue=tracking --no-interaction
        cd "$PROJECT_DIR" && php artisan queue:clean-duplicates --queue=sellers --no-interaction
        cd "$PROJECT_DIR" && php artisan queue:clean-duplicates --queue=default --no-interaction
        log "Queue cleaning completed"
        ;;
    "help"|"-h"|"--help")
        echo "Usage: $0 [monitor|status|clean|help]"
        echo ""
        echo "Commands:"
        echo "  monitor  - Monitor all queues and clean duplicates if needed (default)"
        echo "  status   - Show current queue status"
        echo "  clean    - Clean duplicates from all queues"
        echo "  help     - Show this help message"
        ;;
    *)
        echo "Unknown command: $1"
        echo "Use '$0 help' for usage information"
        exit 1
        ;;
esac

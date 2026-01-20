#!/bin/bash
# =============================================================================
# ISP Billing System - Status Check Script
# =============================================================================
# Script untuk mengecek status sistem
#
# Usage:
#   ./scripts/status.sh
# =============================================================================

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

APP_DIR=$(dirname "$(dirname "$(readlink -f "$0")")")

echo ""
echo "============================================="
echo "  ISP Billing System - Status Check"
echo "============================================="
echo ""

cd "$APP_DIR"

# Function to check status
check_status() {
    local name=$1
    local status=$2
    local detail=$3

    if [ "$status" == "OK" ]; then
        echo -e "${GREEN}[OK]${NC}     $name ${detail:+- $detail}"
    elif [ "$status" == "WARN" ]; then
        echo -e "${YELLOW}[WARN]${NC}   $name ${detail:+- $detail}"
    else
        echo -e "${RED}[FAIL]${NC}   $name ${detail:+- $detail}"
    fi
}

echo "=== Application ==="

# Check .env
if [ -f ".env" ]; then
    APP_ENV=$(grep ^APP_ENV .env | cut -d '=' -f2)
    APP_DEBUG=$(grep ^APP_DEBUG .env | cut -d '=' -f2)
    check_status "Environment" "OK" "$APP_ENV"

    if [ "$APP_DEBUG" == "true" ] && [ "$APP_ENV" == "production" ]; then
        check_status "Debug Mode" "WARN" "DEBUG=true in production!"
    else
        check_status "Debug Mode" "OK" "$APP_DEBUG"
    fi
else
    check_status ".env file" "FAIL" "File not found"
fi

# Check storage permissions
if [ -w "storage" ]; then
    check_status "Storage writable" "OK"
else
    check_status "Storage writable" "FAIL" "Permission denied"
fi

# Check cache permissions
if [ -w "bootstrap/cache" ]; then
    check_status "Cache writable" "OK"
else
    check_status "Cache writable" "FAIL" "Permission denied"
fi

echo ""
echo "=== Services ==="

# Check MySQL
if command -v mysql &> /dev/null; then
    DB_HOST=$(grep ^DB_HOST .env 2>/dev/null | cut -d '=' -f2)
    DB_HOST=${DB_HOST:-127.0.0.1}

    if mysqladmin ping -h"$DB_HOST" --silent 2>/dev/null; then
        check_status "MySQL" "OK" "$DB_HOST"
    else
        check_status "MySQL" "FAIL" "Cannot connect to $DB_HOST"
    fi
else
    check_status "MySQL" "WARN" "mysql client not found"
fi

# Check Redis
if command -v redis-cli &> /dev/null; then
    REDIS_HOST=$(grep ^REDIS_HOST .env 2>/dev/null | cut -d '=' -f2)
    REDIS_HOST=${REDIS_HOST:-127.0.0.1}
    REDIS_PORT=$(grep ^REDIS_PORT .env 2>/dev/null | cut -d '=' -f2)
    REDIS_PORT=${REDIS_PORT:-6379}

    REDIS_PING=$(redis-cli -h "$REDIS_HOST" -p "$REDIS_PORT" ping 2>/dev/null || echo "FAIL")
    if [ "$REDIS_PING" == "PONG" ]; then
        check_status "Redis" "OK" "$REDIS_HOST:$REDIS_PORT"
    else
        check_status "Redis" "FAIL" "Cannot connect to $REDIS_HOST:$REDIS_PORT"
    fi
else
    check_status "Redis" "WARN" "redis-cli not found"
fi

# Check Nginx
if systemctl is-active --quiet nginx 2>/dev/null; then
    check_status "Nginx" "OK" "running"
elif systemctl is-active --quiet apache2 2>/dev/null; then
    check_status "Apache" "OK" "running"
else
    check_status "Web Server" "WARN" "status unknown"
fi

# Check Supervisor
if command -v supervisorctl &> /dev/null; then
    WORKER_STATUS=$(supervisorctl status billing-worker:* 2>/dev/null | head -1 || echo "")
    if [[ "$WORKER_STATUS" == *"RUNNING"* ]]; then
        check_status "Queue Worker" "OK" "running"
    elif [[ "$WORKER_STATUS" == *"STOPPED"* ]]; then
        check_status "Queue Worker" "FAIL" "stopped"
    else
        check_status "Queue Worker" "WARN" "status unknown"
    fi
else
    check_status "Supervisor" "WARN" "not installed"
fi

# Check Cron
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    check_status "Scheduler (cron)" "OK" "configured"
else
    check_status "Scheduler (cron)" "WARN" "not configured"
fi

echo ""
echo "=== Queue Status ==="

# Check queue size
php artisan tinker --execute="echo 'Pending jobs: ' . \Illuminate\Support\Facades\Queue::size();" 2>/dev/null || echo "Cannot check queue"

echo ""
echo "=== Integrations ==="

# Check Mikrotik
MIKROTIK_HOST=$(grep ^MIKROTIK_HOST .env 2>/dev/null | cut -d '=' -f2)
if [ -n "$MIKROTIK_HOST" ]; then
    if ping -c 1 -W 2 "$MIKROTIK_HOST" &>/dev/null; then
        check_status "Mikrotik" "OK" "$MIKROTIK_HOST reachable"
    else
        check_status "Mikrotik" "FAIL" "$MIKROTIK_HOST unreachable"
    fi
else
    check_status "Mikrotik" "WARN" "not configured"
fi

# Check GenieACS
GENIEACS_ENABLED=$(grep ^GENIEACS_ENABLED .env 2>/dev/null | cut -d '=' -f2)
if [ "$GENIEACS_ENABLED" == "true" ]; then
    GENIEACS_URL=$(grep ^GENIEACS_NBI_URL .env 2>/dev/null | cut -d '=' -f2)
    if [ -n "$GENIEACS_URL" ]; then
        HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$GENIEACS_URL/devices?limit=1" 2>/dev/null || echo "000")
        if [ "$HTTP_STATUS" == "200" ]; then
            check_status "GenieACS" "OK" "$GENIEACS_URL"
        else
            check_status "GenieACS" "FAIL" "HTTP $HTTP_STATUS"
        fi
    fi
else
    check_status "GenieACS" "WARN" "disabled"
fi

# Check WhatsApp
WHATSAPP_ENABLED=$(grep ^WHATSAPP_ENABLED .env 2>/dev/null | cut -d '=' -f2)
if [ "$WHATSAPP_ENABLED" == "true" ]; then
    WHATSAPP_KEY=$(grep ^WHATSAPP_API_KEY .env 2>/dev/null | cut -d '=' -f2)
    if [ -n "$WHATSAPP_KEY" ]; then
        check_status "WhatsApp" "OK" "configured"
    else
        check_status "WhatsApp" "FAIL" "API key not set"
    fi
else
    check_status "WhatsApp" "WARN" "disabled"
fi

echo ""
echo "=== Disk Usage ==="

# Storage usage
STORAGE_SIZE=$(du -sh storage 2>/dev/null | cut -f1)
echo "Storage folder: $STORAGE_SIZE"

# Logs size
LOGS_SIZE=$(du -sh storage/logs 2>/dev/null | cut -f1)
echo "Logs folder: $LOGS_SIZE"

# Backups size
BACKUPS_SIZE=$(du -sh storage/backups 2>/dev/null | cut -f1 || echo "0")
echo "Backups folder: $BACKUPS_SIZE"

echo ""
echo "=== Recent Errors ==="
if [ -f "storage/logs/laravel.log" ]; then
    ERROR_COUNT=$(grep -c "\[error\]" storage/logs/laravel.log 2>/dev/null || echo "0")
    LAST_ERROR=$(grep "\[error\]" storage/logs/laravel.log 2>/dev/null | tail -1 || echo "No errors")
    echo "Error count today: $ERROR_COUNT"
    echo "Last error: ${LAST_ERROR:0:100}..."
else
    echo "Log file not found"
fi

echo ""
echo "============================================="
echo "  Status check completed"
echo "============================================="
echo ""

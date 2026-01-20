#!/bin/bash
# =============================================================================
# ISP Billing System - Deployment Script
# =============================================================================
# Script untuk deploy/update aplikasi di production server
#
# Usage:
#   ./scripts/deploy.sh [--fresh]
#
# Options:
#   --fresh    Fresh install (migrate:fresh, akan hapus semua data!)
# =============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Config
APP_DIR=$(dirname "$(dirname "$(readlink -f "$0")")")
BACKUP_DIR="$APP_DIR/storage/backups"
DATE=$(date +%Y%m%d_%H%M%S)

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
check_root() {
    if [ "$EUID" -eq 0 ]; then
        log_warning "Sebaiknya jangan jalankan sebagai root. Gunakan user www-data atau user deployment."
    fi
}

# Backup database before deployment
backup_database() {
    log_info "Membuat backup database..."

    mkdir -p "$BACKUP_DIR"

    # Get database credentials from .env
    DB_NAME=$(grep ^DB_DATABASE "$APP_DIR/.env" | cut -d '=' -f2)
    DB_USER=$(grep ^DB_USERNAME "$APP_DIR/.env" | cut -d '=' -f2)
    DB_PASS=$(grep ^DB_PASSWORD "$APP_DIR/.env" | cut -d '=' -f2)

    if [ -n "$DB_NAME" ] && [ -n "$DB_USER" ]; then
        BACKUP_FILE="$BACKUP_DIR/db_backup_$DATE.sql"
        mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null

        if [ $? -eq 0 ]; then
            gzip "$BACKUP_FILE"
            log_success "Database backup: ${BACKUP_FILE}.gz"
        else
            log_warning "Gagal backup database, melanjutkan deployment..."
        fi
    else
        log_warning "Database credentials tidak ditemukan, skip backup"
    fi
}

# Enable maintenance mode
enable_maintenance() {
    log_info "Mengaktifkan maintenance mode..."
    cd "$APP_DIR"
    php artisan down --render="errors::503" --retry=60
}

# Disable maintenance mode
disable_maintenance() {
    log_info "Menonaktifkan maintenance mode..."
    cd "$APP_DIR"
    php artisan up
}

# Pull latest code
pull_code() {
    log_info "Mengambil kode terbaru dari repository..."
    cd "$APP_DIR"
    git pull origin main
}

# Install/update dependencies
install_dependencies() {
    log_info "Menginstall PHP dependencies..."
    cd "$APP_DIR"
    composer install --no-dev --optimize-autoloader --no-interaction

    log_info "Menginstall Node dependencies..."
    npm ci
}

# Build assets
build_assets() {
    log_info "Building frontend assets..."
    cd "$APP_DIR"
    npm run build
}

# Run migrations
run_migrations() {
    log_info "Menjalankan database migrations..."
    cd "$APP_DIR"

    if [ "$1" == "--fresh" ]; then
        log_warning "FRESH MIGRATION - Semua data akan dihapus!"
        read -p "Yakin? (yes/no): " confirm
        if [ "$confirm" == "yes" ]; then
            php artisan migrate:fresh --seed --force
        else
            log_info "Fresh migration dibatalkan, menjalankan migrate biasa..."
            php artisan migrate --force
        fi
    else
        php artisan migrate --force
    fi
}

# Clear and rebuild cache
rebuild_cache() {
    log_info "Rebuilding cache..."
    cd "$APP_DIR"

    # Clear all cache
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear

    # Rebuild cache for production
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    # Optimize
    php artisan optimize
}

# Restart queue workers
restart_workers() {
    log_info "Restart queue workers..."
    cd "$APP_DIR"

    php artisan queue:restart

    # Restart supervisor if exists
    if command -v supervisorctl &> /dev/null; then
        sudo supervisorctl restart billing-worker:* 2>/dev/null || true
    fi
}

# Set permissions
set_permissions() {
    log_info "Setting permissions..."
    cd "$APP_DIR"

    chmod -R 775 storage
    chmod -R 775 bootstrap/cache

    # If running with www-data
    if id "www-data" &>/dev/null; then
        sudo chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
    fi
}

# Health check
health_check() {
    log_info "Running health check..."
    cd "$APP_DIR"

    # Check if app responds
    APP_URL=$(grep ^APP_URL "$APP_DIR/.env" | cut -d '=' -f2)
    if [ -n "$APP_URL" ]; then
        HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$APP_URL/up" 2>/dev/null || echo "000")

        if [ "$HTTP_STATUS" == "200" ]; then
            log_success "Health check passed (HTTP $HTTP_STATUS)"
        else
            log_warning "Health check returned HTTP $HTTP_STATUS"
        fi
    fi

    # Check queue connection
    php artisan queue:monitor default --max=100 2>/dev/null || true
}

# Main deployment process
main() {
    echo ""
    echo "============================================="
    echo "  ISP Billing System - Deployment"
    echo "============================================="
    echo ""

    FRESH_INSTALL=false
    if [ "$1" == "--fresh" ]; then
        FRESH_INSTALL=true
        log_warning "Mode: FRESH INSTALL"
    else
        log_info "Mode: UPDATE"
    fi

    echo ""

    # Check
    check_root

    # Backup
    backup_database

    # Enable maintenance
    enable_maintenance

    # Deploy
    pull_code
    install_dependencies
    build_assets

    if [ "$FRESH_INSTALL" == true ]; then
        run_migrations --fresh
    else
        run_migrations
    fi

    # Post-deploy
    rebuild_cache
    set_permissions
    restart_workers

    # Disable maintenance
    disable_maintenance

    # Verify
    health_check

    echo ""
    log_success "============================================="
    log_success "  Deployment selesai!"
    log_success "============================================="
    echo ""
}

# Run
main "$@"

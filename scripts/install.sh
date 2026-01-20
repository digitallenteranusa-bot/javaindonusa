#!/bin/bash
# =============================================================================
# ISP Billing System - Fresh Installation Script
# =============================================================================
# Script untuk instalasi pertama kali
#
# Usage:
#   ./scripts/install.sh
# =============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

APP_DIR=$(dirname "$(dirname "$(readlink -f "$0")")")

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

echo ""
echo "============================================="
echo "  ISP Billing System - Installation"
echo "============================================="
echo ""

cd "$APP_DIR"

# Check PHP version
log_info "Checking PHP version..."
PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
if (( $(echo "$PHP_VERSION < 8.2" | bc -l) )); then
    log_error "PHP 8.2+ required. Current: $PHP_VERSION"
    exit 1
fi
log_success "PHP $PHP_VERSION OK"

# Check Composer
log_info "Checking Composer..."
if ! command -v composer &> /dev/null; then
    log_error "Composer not found. Please install Composer first."
    exit 1
fi
log_success "Composer OK"

# Check Node
log_info "Checking Node.js..."
if ! command -v node &> /dev/null; then
    log_error "Node.js not found. Please install Node.js 18+ first."
    exit 1
fi
NODE_VERSION=$(node -v | cut -d "v" -f 2 | cut -d "." -f 1)
if [ "$NODE_VERSION" -lt 18 ]; then
    log_error "Node.js 18+ required. Current: $NODE_VERSION"
    exit 1
fi
log_success "Node.js v$NODE_VERSION OK"

# Check Redis
log_info "Checking Redis..."
if ! command -v redis-cli &> /dev/null; then
    log_warning "Redis CLI not found. Make sure Redis is running."
else
    REDIS_PING=$(redis-cli ping 2>/dev/null || echo "FAIL")
    if [ "$REDIS_PING" == "PONG" ]; then
        log_success "Redis OK"
    else
        log_warning "Redis not responding. Queue features may not work."
    fi
fi

# Install PHP dependencies
log_info "Installing PHP dependencies..."
composer install --optimize-autoloader

# Install Node dependencies
log_info "Installing Node dependencies..."
npm ci

# Setup environment
if [ ! -f ".env" ]; then
    log_info "Creating .env file..."
    cp .env.example .env
    log_warning "Edit file .env dan sesuaikan konfigurasi sebelum melanjutkan!"
    echo ""
    read -p "Tekan Enter setelah selesai edit .env..."
fi

# Generate app key
log_info "Generating application key..."
php artisan key:generate

# Build assets
log_info "Building frontend assets..."
npm run build

# Run migrations
log_info "Running database migrations..."
read -p "Jalankan migration dengan sample data? (yes/no): " with_seed
if [ "$with_seed" == "yes" ]; then
    php artisan migrate:fresh --seed
else
    php artisan migrate
fi

# Create storage link
log_info "Creating storage link..."
php artisan storage:link

# Set permissions
log_info "Setting permissions..."
chmod -R 775 storage bootstrap/cache

# Create required directories
mkdir -p storage/backups
mkdir -p storage/logs

# Optimize
log_info "Optimizing application..."
php artisan optimize

echo ""
log_success "============================================="
log_success "  Installation completed!"
log_success "============================================="
echo ""
log_info "Next steps:"
echo "  1. Configure web server (Nginx/Apache)"
echo "  2. Setup supervisor for queue worker"
echo "  3. Setup crontab for scheduler"
echo "  4. Access: http://localhost/login"
echo "  5. Login: admin@admin.com / password"
echo ""
log_warning "IMPORTANT: Change admin password after first login!"
echo ""

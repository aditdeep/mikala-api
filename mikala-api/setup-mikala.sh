#!/bin/bash

# ============================================
# MIKALA AUTO-SETUP SCRIPT
# Setup Backend + Frontend dalam 1 command
# ============================================

set -e  # Exit on error

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_error() { echo -e "${RED}✗ $1${NC}"; }
print_info() { echo -e "${BLUE}ℹ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠ $1${NC}"; }

echo -e "${BLUE}"
echo "╔════════════════════════════════════════╗"
echo "║   MIKALA AUTO-SETUP SCRIPT v1.0        ║"
echo "║   Backend + Frontend Installer         ║"
echo "╚════════════════════════════════════════╝"
echo -e "${NC}"

# ============================================
# CONFIGURATION
# ============================================

echo ""
print_info "Setup Configuration"
echo "────────────────────────────────────────"

# Ask setup location
read -p "📁 Install directory [./mikala-project]: " INSTALL_DIR
INSTALL_DIR=${INSTALL_DIR:-./mikala-project}

# Ask what to setup
echo ""
echo "What do you want to setup?"
echo "  1) Backend only"
echo "  2) Frontend only"
echo "  3) Both (recommended)"
read -p "Select [3]: " SETUP_CHOICE
SETUP_CHOICE=${SETUP_CHOICE:-3}

SETUP_BACKEND=false
SETUP_FRONTEND=false

if [ "$SETUP_CHOICE" = "1" ] || [ "$SETUP_CHOICE" = "3" ]; then
    SETUP_BACKEND=true
fi

if [ "$SETUP_CHOICE" = "2" ] || [ "$SETUP_CHOICE" = "3" ]; then
    SETUP_FRONTEND=true
fi

# Database configuration (if backend)
if [ "$SETUP_BACKEND" = true ]; then
    echo ""
    print_info "Database Configuration"
    echo "────────────────────────────────────────"
    read -p "MySQL Host [127.0.0.1]: " DB_HOST
    DB_HOST=${DB_HOST:-127.0.0.1}
    
    read -p "MySQL Port [3306]: " DB_PORT
    DB_PORT=${DB_PORT:-3306}
    
    read -p "Database Name [mikala_db]: " DB_NAME
    DB_NAME=${DB_NAME:-mikala_db}
    
    read -p "MySQL Username [root]: " DB_USER
    DB_USER=${DB_USER:-root}
    
    read -sp "MySQL Password: " DB_PASS
    echo ""
    
    # Ask if create database
    read -p "Create database if not exists? [Y/n]: " CREATE_DB
    CREATE_DB=${CREATE_DB:-Y}
fi

echo ""
print_warning "Installation will begin in 3 seconds..."
sleep 3

# ============================================
# CREATE INSTALL DIRECTORY
# ============================================

echo ""
print_info "Creating installation directory..."
mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"
print_success "Directory created: $(pwd)"

# ============================================
# BACKEND SETUP
# ============================================

if [ "$SETUP_BACKEND" = true ]; then
    echo ""
    echo -e "${GREEN}═══════════════════════════════════════${NC}"
    echo -e "${GREEN}   BACKEND SETUP (Laravel API)        ${NC}"
    echo -e "${GREEN}═══════════════════════════════════════${NC}"
    
    # 1. Clone repository
    echo ""
    print_info "[1/7] Cloning backend repository..."
    if [ -d "mikala-api" ]; then
        print_warning "Directory mikala-api exists, pulling latest..."
        cd mikala-api
        git pull origin main
        cd ..
    else
        git clone https://github.com/aditdeep/mikala-api.git
        print_success "Repository cloned"
    fi
    
    cd mikala-api
    
    # 2. Install dependencies
    echo ""
    print_info "[2/7] Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
    print_success "Dependencies installed"
    
    # 3. Setup .env
    echo ""
    print_info "[3/7] Configuring environment..."
    if [ ! -f ".env" ]; then
        cp .env.example .env
        print_success ".env created"
    else
        print_warning ".env already exists, backing up to .env.backup"
        cp .env .env.backup
    fi
    
    # Update .env with database config
    sed -i "s/DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
    sed -i "s/DB_HOST=.*/DB_HOST=$DB_HOST/" .env
    sed -i "s/DB_PORT=.*/DB_PORT=$DB_PORT/" .env
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
    
    print_success "Environment configured"
    
    # 4. Generate key
    echo ""
    print_info "[4/7] Generating application key..."
    php artisan key:generate --force
    print_success "Application key generated"
    
    # 5. Create database
    if [ "$CREATE_DB" = "Y" ] || [ "$CREATE_DB" = "y" ]; then
        echo ""
        print_info "[5/7] Creating database..."
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || {
            print_warning "Could not create database (might already exist or permission issue)"
        }
        print_success "Database ready"
    else
        echo ""
        print_info "[5/7] Skipping database creation..."
    fi
    
    # 6. Run migrations
    echo ""
    print_info "[6/7] Running migrations..."
    php artisan migrate --force
    print_success "Database tables created (13 tables)"
    
    # 7. Seed database
    echo ""
    print_info "[7/7] Seeding test data..."
    php artisan db:seed --force
    print_success "Test data seeded (8 users)"
    
    cd ..
    
    echo ""
    print_success "✓ BACKEND SETUP COMPLETE!"
    echo ""
    echo "Test credentials:"
    echo "  Admin:   admin@mikala.com / password"
    echo "  Finance: finance@mikala.com / password"
    echo "  Mitra:   siti@example.com / password"
    echo "  Klien:   aminah@example.com / password"
fi

# ============================================
# FRONTEND SETUP
# ============================================

if [ "$SETUP_FRONTEND" = true ]; then
    echo ""
    echo -e "${GREEN}═══════════════════════════════════════${NC}"
    echo -e "${GREEN}   FRONTEND SETUP (Next.js Apps)       ${NC}"
    echo -e "${GREEN}═══════════════════════════════════════${NC}"
    
    # 1. Clone repository
    echo ""
    print_info "[1/3] Cloning frontend repository..."
    if [ -d "mikala-web" ]; then
        print_warning "Directory mikala-web exists, pulling latest..."
        cd mikala-web
        git pull origin main
        cd ..
    else
        git clone https://github.com/aditdeep/mikala-web.git
        print_success "Repository cloned"
    fi
    
    cd mikala-web
    
    # 2. Install dependencies
    echo ""
    print_info "[2/3] Installing npm dependencies (this may take a while)..."
    npm install
    print_success "Dependencies installed"
    
    # 3. Configure .env.local
    echo ""
    print_info "[3/3] Configuring environment..."
    
    # Determine API URL
    if [ "$SETUP_BACKEND" = true ]; then
        API_URL="http://localhost:8000/api"
    else
        read -p "Backend API URL [http://localhost:8000/api]: " API_URL
        API_URL=${API_URL:-http://localhost:8000/api}
    fi
    
    echo "NEXT_PUBLIC_API_URL=$API_URL" > .env.local
    print_success "Environment configured"
    
    cd ..
    
    echo ""
    print_success "✓ FRONTEND SETUP COMPLETE!"
    echo ""
    echo "Apps available:"
    echo "  Internal Platform: http://localhost:3000"
    echo "  Mitra PWA:         http://localhost:3001"
    echo "  Klien PWA:         http://localhost:3002"
    echo "  MGM Website:       http://localhost:3003"
    echo "  MGA Website:       http://localhost:3004"
fi

# ============================================
# FINAL INSTRUCTIONS
# ============================================

echo ""
echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   🎉 SETUP COMPLETE!                   ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
echo ""

print_info "Next steps:"
echo "────────────────────────────────────────"

if [ "$SETUP_BACKEND" = true ]; then
    echo ""
    echo "🔥 Start Backend:"
    echo "   cd $(pwd)/mikala-api"
    echo "   php artisan serve"
    echo ""
    echo "   Backend will run at: http://localhost:8000"
    echo ""
    echo "📝 Test Backend:"
    echo "   cd $(pwd)/mikala-api"
    echo "   ./test_endpoints.sh"
fi

if [ "$SETUP_FRONTEND" = true ]; then
    echo ""
    echo "🎨 Start Frontend:"
    echo "   cd $(pwd)/mikala-web"
    echo "   npm run dev          # All apps"
    echo "   npm run internal     # Internal only"
    echo "   npm run mitra        # Mitra only"
    echo ""
    echo "   Apps will run at: http://localhost:3000-3004"
fi

echo ""
echo "📚 Documentation:"
if [ "$SETUP_BACKEND" = true ]; then
    echo "   Backend:  $(pwd)/mikala-api/README_BACKEND.md"
fi
if [ "$SETUP_FRONTEND" = true ]; then
    echo "   Frontend: $(pwd)/mikala-web/README.md"
fi

echo ""
print_success "Installation complete! Happy coding! 🚀"
echo ""

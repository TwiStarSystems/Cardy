#!/usr/bin/env bash
# ============================================================
#  Cardy Install Script
#  Supports: Ubuntu 22.04/24.04, Debian 11/12
# ============================================================
set -euo pipefail

CARDY_DIR="/var/www/cardy"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# -------- Colour helpers ------------------------------------
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

info()    { echo -e "${CYAN}[INFO]${RESET}  $*"; }
success() { echo -e "${GREEN}[OK]${RESET}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${RESET}  $*"; }
error()   { echo -e "${RED}[ERROR]${RESET} $*" >&2; exit 1; }
header()  { echo -e "\n${BOLD}${CYAN}══ $* ══${RESET}"; }

# -------- Root check ----------------------------------------
if [[ $EUID -ne 0 ]]; then
    error "This script must be run as root (use sudo)."
fi

MODE="${1:-install}"

# ============================================================
#  UPDATE MODE
# ============================================================
if [[ "$MODE" == "--update" ]]; then
    header "Updating Cardy"

    if [[ ! -f "${CARDY_DIR}/config/config.php" ]]; then
        error "Cardy does not appear to be installed at ${CARDY_DIR}."
    fi

    info "Copying application files..."
    rsync -a --exclude='config/config.php' --exclude='vendor/' \
        "${SCRIPT_DIR}/" "${CARDY_DIR}/"

    info "Running composer install..."
    cd "${CARDY_DIR}" && sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction

    info "Reloading PHP-FPM..."
    systemctl reload php8.2-fpm 2>/dev/null || systemctl reload php8.3-fpm 2>/dev/null || true

    success "Cardy updated successfully."
    exit 0
fi

# ============================================================
#  INSTALL MODE
# ============================================================
header "Cardy Installation"

# -------- Detect PHP version --------------------------------
PHP_VER=""
for v in 8.3 8.2 8.1; do
    if command -v "php${v}" &>/dev/null; then
        PHP_VER="$v"
        break
    fi
done
if [[ -z "$PHP_VER" ]]; then
    PHP_VER="8.2"   # will install this version
fi
info "PHP target version: ${PHP_VER}"

# -------- Collect configuration -----------------------------
echo ""
echo -e "${BOLD}Please provide configuration values (press Enter to accept defaults):${RESET}"
echo ""

read -r -p "Database host [localhost]: "           DB_HOST;     DB_HOST="${DB_HOST:-localhost}"
read -r -p "Database port [3306]: "               DB_PORT;     DB_PORT="${DB_PORT:-3306}"
read -r -p "Database name [cardy]: "              DB_NAME;     DB_NAME="${DB_NAME:-cardy}"
read -r -p "Database user [cardy]: "              DB_USER;     DB_USER="${DB_USER:-cardy}"
read -r -s -p "Database password: "               DB_PASS;     echo ""
read -r -p "Web UI base URL [http://localhost:8321]: " WEBUI_URL; WEBUI_URL="${WEBUI_URL:-http://localhost:8321}"
read -r -p "DAV service URL  [http://localhost]: "    DAV_URL;   DAV_URL="${DAV_URL:-http://localhost}"
read -r -p "Admin username [admin]: "             ADMIN_USER;  ADMIN_USER="${ADMIN_USER:-admin}"
read -r -s -p "Admin password (min 8 chars): "    ADMIN_PASS;  echo ""

if [[ ${#DB_PASS} -lt 1 ]]; then
    error "Database password cannot be empty."
fi
if [[ ${#ADMIN_PASS} -lt 8 ]]; then
    error "Admin password must be at least 8 characters."
fi

# -------- Install system packages ---------------------------
header "Installing system packages"

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq

apt-get install -y -qq \
    nginx \
    "php${PHP_VER}-fpm" \
    "php${PHP_VER}-mysql" \
    "php${PHP_VER}-xml" \
    "php${PHP_VER}-mbstring" \
    "php${PHP_VER}-curl" \
    "php${PHP_VER}-zip" \
    "php${PHP_VER}-intl" \
    mysql-server \
    composer \
    rsync

success "System packages installed."

# -------- Set up MySQL database -----------------------------
header "Configuring MySQL"

# Start MySQL if not running
systemctl enable --now mysql || systemctl enable --now mariadb || true

MYSQL_CMD="mysql -u root"
if ! ${MYSQL_CMD} -e "SELECT 1" &>/dev/null; then
    # Try with sudo
    MYSQL_CMD="sudo mysql -u root"
fi

info "Creating database '${DB_NAME}'..."
${MYSQL_CMD} <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${DB_HOST}';
FLUSH PRIVILEGES;
EOF

info "Running schema migration..."
${MYSQL_CMD} "${DB_NAME}" < "${SCRIPT_DIR}/sql/schema.sql"

success "Database configured."

# -------- Copy application files ---------------------------
header "Installing application files"

mkdir -p "${CARDY_DIR}"/{dav,webui/assets/{css,js}}

# Copy source
rsync -a --exclude='config/config.php' --exclude='vendor/' \
    "${SCRIPT_DIR}/" "${CARDY_DIR}/"

# Install PHP dependencies
info "Running composer install..."
cd "${CARDY_DIR}" && composer install --no-dev --optimize-autoloader --no-interaction

success "Application files installed."

# -------- Generate configuration ----------------------------
header "Writing configuration"

SESSION_SECRET=$(openssl rand -hex 32)

cat > "${CARDY_DIR}/config/config.php" <<PHP
<?php
return [
    'db' => [
        'host' => '${DB_HOST}',
        'port' => ${DB_PORT},
        'name' => '${DB_NAME}',
        'user' => '${DB_USER}',
        'pass' => '${DB_PASS}',
    ],
    'app' => [
        'webui_url'      => '${WEBUI_URL}',
        'dav_url'        => '${DAV_URL}',
        'session_secret' => '${SESSION_SECRET}',
        'timezone'       => 'UTC',
        'name'           => 'Cardy',
    ],
];
PHP

chmod 640 "${CARDY_DIR}/config/config.php"
success "Configuration written."

# -------- Create admin user ---------------------------------
header "Creating admin user"

ADMIN_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_BCRYPT);")

${MYSQL_CMD} "${DB_NAME}" <<EOF
INSERT IGNORE INTO users (username, password_hash, email, display_name, is_admin)
VALUES ('${ADMIN_USER}', '${ADMIN_HASH}', '', '${ADMIN_USER}', 1);
EOF

# Create SabreDAV principal + default address book + default calendar
ADMIN_ID=$(${MYSQL_CMD} -N -e "SELECT id FROM \`${DB_NAME}\`.users WHERE username='${ADMIN_USER}' LIMIT 1")
PRINCIPAL_URI="principals/${ADMIN_USER}"

${MYSQL_CMD} "${DB_NAME}" <<EOF
INSERT IGNORE INTO principals (uri, email, displayname) VALUES ('${PRINCIPAL_URI}', '', '${ADMIN_USER}');
INSERT IGNORE INTO addressbooks (principaluri, displayname, uri, description, synctoken)
    VALUES ('${PRINCIPAL_URI}', 'My Contacts', 'default', 'Default address book', 1);
SET @calid = 0;
INSERT IGNORE INTO calendars (synctoken, components) VALUES (1, 'VEVENT,VTODO,VJOURNAL');
SET @calid = LAST_INSERT_ID();
INSERT IGNORE INTO calendarinstances
    (calendarid, principaluri, access, displayname, uri, description, calendarcolor, timezone)
    VALUES (@calid, '${PRINCIPAL_URI}', 1, 'My Calendar', 'default', 'Default calendar', '#9600E1', 'UTC');
EOF

success "Admin user '${ADMIN_USER}' created."

# -------- Configure Nginx -----------------------------------
header "Configuring Nginx"

# Remove any default site that might conflict
rm -f /etc/nginx/sites-enabled/default

# Install Cardy vhost configs
cp "${CARDY_DIR}/config/nginx/cardy-dav.conf"   /etc/nginx/sites-available/cardy-dav
cp "${CARDY_DIR}/config/nginx/cardy-webui.conf" /etc/nginx/sites-available/cardy-webui

# Patch PHP-FPM socket path for actual installed version
FPM_SOCK="/var/run/php/php${PHP_VER}-fpm.sock"
sed -i "s|php8.2-fpm.sock|php${PHP_VER}-fpm.sock|g" \
    /etc/nginx/sites-available/cardy-dav \
    /etc/nginx/sites-available/cardy-webui

ln -sf /etc/nginx/sites-available/cardy-dav   /etc/nginx/sites-enabled/cardy-dav
ln -sf /etc/nginx/sites-available/cardy-webui /etc/nginx/sites-enabled/cardy-webui

nginx -t && systemctl reload nginx
success "Nginx configured."

# -------- Set up document root symlinks ---------------------
header "Setting up web roots"

# Web root for DAV: public/dav/
ln -sfn "${CARDY_DIR}/public/dav"    "${CARDY_DIR}/dav"
ln -sfn "${CARDY_DIR}/public/webui"  "${CARDY_DIR}/webui"

# Update nginx roots to the correct paths
sed -i "s|root /var/www/cardy/dav;|root ${CARDY_DIR}/public/dav;|" \
    /etc/nginx/sites-available/cardy-dav
sed -i "s|root /var/www/cardy/webui;|root ${CARDY_DIR}/public/webui;|" \
    /etc/nginx/sites-available/cardy-webui

nginx -t && systemctl reload nginx

# -------- Permissions ---------------------------------------
header "Setting permissions"

chown -R www-data:www-data "${CARDY_DIR}"
chmod -R 755 "${CARDY_DIR}"
chmod 640 "${CARDY_DIR}/config/config.php"

# -------- Install cardy-ctl ---------------------------------
cp "${CARDY_DIR}/cardy-ctl" /usr/local/bin/cardy-ctl
chmod +x /usr/local/bin/cardy-ctl

# -------- Start/restart services ----------------------------
header "Starting services"

systemctl enable --now "php${PHP_VER}-fpm" || true
systemctl enable --now nginx
systemctl restart "php${PHP_VER}-fpm" || true
systemctl restart nginx

# ============================================================
#  Done
# ============================================================
header "Installation Complete"

echo -e ""
echo -e "  ${BOLD}Web UI${RESET}         ${GREEN}${WEBUI_URL}${RESET}"
echo -e "  ${BOLD}CardDAV URL${RESET}    ${GREEN}${DAV_URL}/addressbooks/${ADMIN_USER}/default/${RESET}"
echo -e "  ${BOLD}CalDAV URL${RESET}     ${GREEN}${DAV_URL}/calendars/${ADMIN_USER}/default/${RESET}"
echo -e ""
echo -e "  ${BOLD}Admin user${RESET}     ${CYAN}${ADMIN_USER}${RESET}"
echo -e ""
echo -e "  Use ${CYAN}cardy-ctl${RESET} to manage users from the command line."
echo -e ""
success "Cardy is ready!"

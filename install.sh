#!/usr/bin/env bash
# ============================================================
#  Cardy Install Script
#  Optimized for: Debian 13+ (also supports Ubuntu 22.04/24.04)
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

MODE_RAW="${1:---fresh-install}"

usage() {
    cat <<USAGE
Usage: sudo bash install.sh [mode]

Modes:
  --fresh-install   Delete existing Cardy app data/config/db/nginx and install cleanly
  --update          Update app files, DB schema, and nginx config while preserving user data
  --uninstall       Delete Cardy app data/config/db/nginx

Legacy aliases:
  install, --install, fresh, --fresh
  update
  uninstall
USAGE
}

case "${MODE_RAW}" in
    --fresh-install|--install|install|fresh|--fresh)
        MODE="fresh-install"
        ;;
    --update|update)
        MODE="update"
        ;;
    --uninstall|uninstall)
        MODE="uninstall"
        ;;
    -h|--help|help)
        usage
        exit 0
        ;;
    *)
        usage
        error "Unknown mode: ${MODE_RAW}"
        ;;
esac

config_read() {
    local key="$1"
    local path="$2"
    php -r "\$c = @include '${path}'; if (!is_array(\$c)) { exit(1); } \$v = \$c; foreach (explode('.', '${key}') as \$k) { if (!is_array(\$v) || !array_key_exists(\$k, \$v)) { exit(1); } \$v = \$v[\$k]; } if (is_array(\$v)) { echo json_encode(\$v); } else { echo \$v; }" 2>/dev/null || true
}

get_mysql_root_cmd() {
    MYSQL_CMD="mysql -u root"
    if ! ${MYSQL_CMD} -e "SELECT 1" &>/dev/null; then
        MYSQL_CMD="sudo mysql -u root"
    fi
}

reload_php_fpm() {
    local reloaded=0
    while IFS= read -r svc; do
        if systemctl reload "$svc" 2>/dev/null; then
            reloaded=1
            break
        fi
    done < <(systemctl list-unit-files --type=service --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}' | sort -V -r)

    if [[ ${reloaded} -eq 0 ]]; then
        warn "No PHP-FPM service detected to reload; continuing."
    fi
}

run_composer_install() {
    local app_dir="$1"
    local composer_cmd=(composer install --no-dev --optimize-autoloader --no-interaction)

    if command -v runuser >/dev/null 2>&1; then
        (cd "${app_dir}" && runuser -u www-data -- "${composer_cmd[@]}")
        return
    fi

    if command -v su >/dev/null 2>&1; then
        (cd "${app_dir}" && su -s /bin/bash -c "${composer_cmd[*]}" www-data)
        return
    fi

    warn "runuser/su not available; running composer install as root."
    (cd "${app_dir}" && "${composer_cmd[@]}")
}

configure_php_upload_limits() {
    local php_ver="$1"
    if [[ -z "${php_ver}" ]]; then
        warn "PHP version unknown; skipping PHP upload-limit configuration."
        return
    fi

    local fpm_conf_dir="/etc/php/${php_ver}/fpm/conf.d"
    local cli_conf_dir="/etc/php/${php_ver}/cli/conf.d"

    mkdir -p "${fpm_conf_dir}" "${cli_conf_dir}"

    cat > "${fpm_conf_dir}/99-cardy-uploads.ini" <<'PHPINI'
file_uploads = On
upload_max_filesize = 16M
post_max_size = 20M
max_file_uploads = 100
PHPINI

    cat > "${cli_conf_dir}/99-cardy-uploads.ini" <<'PHPINI'
file_uploads = On
upload_max_filesize = 16M
post_max_size = 20M
max_file_uploads = 100
PHPINI

    success "Configured PHP upload limits for php${php_ver}."
}

detect_php_version_from_services() {
    systemctl list-unit-files --type=service --no-legend 'php*-fpm.service' 2>/dev/null \
        | awk '{print $1}' \
        | sed -nE 's/^php([0-9]+\.[0-9]+)-fpm\.service$/\1/p' \
        | sort -V -r \
        | head -n1
}

remove_cardy_nginx_configs() {
    rm -f /etc/nginx/sites-enabled/cardy /etc/nginx/sites-available/cardy
    rm -f /etc/nginx/sites-enabled/cardy-dav /etc/nginx/sites-available/cardy-dav
    rm -f /etc/nginx/sites-enabled/cardy-webui /etc/nginx/sites-available/cardy-webui

    if command -v nginx &>/dev/null; then
        nginx -t >/dev/null 2>&1 && systemctl reload nginx || true
    fi
}

drop_cardy_db_and_user() {
    local db_host="$1"
    local db_name="$2"
    local db_user="$3"

    if ! command -v mysql &>/dev/null; then
        warn "mysql client not found; skipping DB cleanup."
        return
    fi

    systemctl enable --now mysql >/dev/null 2>&1 || systemctl enable --now mariadb >/dev/null 2>&1 || true
    get_mysql_root_cmd

    info "Dropping database/user if they exist (${db_name}, ${db_user}@${db_host})..."
    ${MYSQL_CMD} <<EOF
DROP DATABASE IF EXISTS \`${db_name}\`;
DROP USER IF EXISTS '${db_user}'@'${db_host}';
FLUSH PRIVILEGES;
EOF
}

cleanup_cardy_artifacts() {
    local db_host="$1"
    local db_name="$2"
    local db_user="$3"

    header "Removing existing Cardy artifacts"
    remove_cardy_nginx_configs
    rm -rf "${CARDY_DIR}"
    rm -f /usr/local/bin/cardy-ctl
    drop_cardy_db_and_user "$db_host" "$db_name" "$db_user"
    success "Existing Cardy artifacts removed."
}

confirm_destructive_action() {
    local prompt_text="$1"
    local answer=""
    echo ""
    warn "${prompt_text}"
    read -r -p "Type YES to continue: " answer
    if [[ "${answer}" != "YES" ]]; then
        error "Aborted by user."
    fi
}

# ============================================================
#  UPDATE MODE
# ============================================================
if [[ "${MODE}" == "update" ]]; then
    header "Updating Cardy"

    if [[ ! -f "${CARDY_DIR}/config/config.php" ]]; then
        error "Cardy does not appear to be installed at ${CARDY_DIR}."
    fi

    DB_HOST="$(config_read 'db.host' "${CARDY_DIR}/config/config.php")"
    DB_PORT="$(config_read 'db.port' "${CARDY_DIR}/config/config.php")"
    DB_NAME="$(config_read 'db.name' "${CARDY_DIR}/config/config.php")"
    DB_USER="$(config_read 'db.user' "${CARDY_DIR}/config/config.php")"
    PHP_VER="$(detect_php_version_from_services)"

    info "Copying application files..."
    rsync -a --exclude='config/config.php' --exclude='vendor/' \
        "${SCRIPT_DIR}/" "${CARDY_DIR}/"

    info "Running composer install..."
    run_composer_install "${CARDY_DIR}"

    info "Applying database schema updates..."
    systemctl enable --now mysql >/dev/null 2>&1 || systemctl enable --now mariadb >/dev/null 2>&1 || true
    get_mysql_root_cmd
    ${MYSQL_CMD} "${DB_NAME}" < "${SCRIPT_DIR}/sql/schema.sql"

    if [[ -z "${PHP_VER}" ]]; then
        while IFS= read -r v; do
            CANDIDATE="$(apt-cache policy "php${v}-fpm" 2>/dev/null | awk -F': ' '/Candidate:/ {print $2}')"
            if [[ -n "$CANDIDATE" && "$CANDIDATE" != "(none)" ]]; then
                PHP_VER="$v"
                break
            fi
        done < <(apt-cache pkgnames | grep -E '^php[0-9]+\.[0-9]+-fpm$' | sed -E 's/^php([0-9]+\.[0-9]+)-fpm$/\1/' | sort -V -r)
    fi

    info "Refreshing Nginx configuration..."
    cp "${CARDY_DIR}/config/nginx/cardy.conf" /etc/nginx/sites-available/cardy
    if [[ -n "${PHP_VER}" ]]; then
        sed -i "s|php8.2-fpm.sock|php${PHP_VER}-fpm.sock|g" /etc/nginx/sites-available/cardy
    fi
    sed -i "s|root /var/www/cardy/public;|root ${CARDY_DIR}/public;|" /etc/nginx/sites-available/cardy
    sed -i "s|alias /var/www/cardy/public/webui/assets/;|alias ${CARDY_DIR}/public/webui/assets/;|" /etc/nginx/sites-available/cardy
    ln -sf /etc/nginx/sites-available/cardy /etc/nginx/sites-enabled/cardy
    rm -f /etc/nginx/sites-enabled/cardy-dav /etc/nginx/sites-available/cardy-dav
    rm -f /etc/nginx/sites-enabled/cardy-webui /etc/nginx/sites-available/cardy-webui

    nginx -t && systemctl reload nginx
    configure_php_upload_limits "${PHP_VER}"
    reload_php_fpm
    systemctl restart nginx

    success "Cardy updated successfully."
    exit 0
fi

# ============================================================
#  UNINSTALL MODE
# ============================================================
if [[ "${MODE}" == "uninstall" ]]; then
    header "Uninstalling Cardy"

    EXISTING_CONFIG="${CARDY_DIR}/config/config.php"
    EXIST_DB_HOST="$(config_read 'db.host' "${EXISTING_CONFIG}")"
    EXIST_DB_NAME="$(config_read 'db.name' "${EXISTING_CONFIG}")"
    EXIST_DB_USER="$(config_read 'db.user' "${EXISTING_CONFIG}")"

    echo ""
    echo -e "${BOLD}Confirm database cleanup target (press Enter to accept defaults):${RESET}"
    echo ""
    read -r -p "Database host [${EXIST_DB_HOST:-localhost}]: " DB_HOST; DB_HOST="${DB_HOST:-${EXIST_DB_HOST:-localhost}}"
    read -r -p "Database name [${EXIST_DB_NAME:-cardy}]: " DB_NAME; DB_NAME="${DB_NAME:-${EXIST_DB_NAME:-cardy}}"
    read -r -p "Database user [${EXIST_DB_USER:-cardy}]: " DB_USER; DB_USER="${DB_USER:-${EXIST_DB_USER:-cardy}}"

    confirm_destructive_action "Uninstall will permanently remove Cardy app files, DB data, and nginx configs."

    cleanup_cardy_artifacts "$DB_HOST" "$DB_NAME" "$DB_USER"
    success "Cardy uninstalled."
    exit 0
fi

# ============================================================
#  FRESH INSTALL MODE
# ============================================================
header "Cardy Fresh Installation"

# -------- OS check ------------------------------------------
if [[ -r /etc/os-release ]]; then
    . /etc/os-release
    if [[ "${ID:-}" == "debian" ]]; then
        DEBIAN_MAJOR="${VERSION_ID%%.*}"
        if [[ -n "${DEBIAN_MAJOR}" && "${DEBIAN_MAJOR}" =~ ^[0-9]+$ && ${DEBIAN_MAJOR} -lt 13 ]]; then
            warn "This installer is optimized for Debian 13+. Continuing on Debian ${VERSION_ID}."
        else
            info "Detected Debian ${VERSION_ID} (optimized target)."
        fi
    fi
fi

# -------- Select PHP version --------------------------------
PHP_VER=""

# -------- Collect configuration -----------------------------
echo ""
echo -e "${BOLD}Please provide configuration values (press Enter to accept defaults):${RESET}"
echo ""

read -r -p "Database host [localhost]: "           DB_HOST;     DB_HOST="${DB_HOST:-localhost}"
read -r -p "Database port [3306]: "               DB_PORT;     DB_PORT="${DB_PORT:-3306}"
read -r -p "Database name [cardy]: "              DB_NAME;     DB_NAME="${DB_NAME:-cardy}"
read -r -p "Database user [cardy]: "              DB_USER;     DB_USER="${DB_USER:-cardy}"
read -r -s -p "Database password [auto-generate if blank]: " DB_PASS; echo ""
read -r -p "Base URI [http://localhost]: "        BASE_URI;    BASE_URI="${BASE_URI:-http://localhost}"
WEBUI_URL="${BASE_URI}"
DAV_URL="${BASE_URI}"
read -r -p "Admin username [admin]: "             ADMIN_USER;  ADMIN_USER="${ADMIN_USER:-admin}"
read -r -s -p "Admin password [admin]: "          ADMIN_PASS;  echo ""
ADMIN_PASS="${ADMIN_PASS:-admin}"

DB_PASS_GENERATED=0
if [[ ${#DB_PASS} -lt 1 ]]; then
    DB_PASS="$(openssl rand -base64 48 | tr -d '=+/\n' | cut -c1-32)"
    DB_PASS_GENERATED=1
    warn "Database password not provided; generated a strong password automatically."
fi

if [[ "${ADMIN_PASS}" == "admin" ]]; then
    warn "Admin password is set to the default value 'admin'. Change it after installation."
fi

confirm_destructive_action "Fresh install will permanently delete existing Cardy app files, DB data, and nginx configs before reinstalling."

cleanup_cardy_artifacts "$DB_HOST" "$DB_NAME" "$DB_USER"

# -------- Install system packages ---------------------------
header "Installing system packages"

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq

while IFS= read -r v; do
    CANDIDATE="$(apt-cache policy "php${v}-fpm" 2>/dev/null | awk -F': ' '/Candidate:/ {print $2}')"
    if [[ -n "$CANDIDATE" && "$CANDIDATE" != "(none)" ]]; then
        PHP_VER="$v"
        break
    fi
done < <(apt-cache pkgnames | grep -E '^php[0-9]+\.[0-9]+-fpm$' | sed -E 's/^php([0-9]+\.[0-9]+)-fpm$/\1/' | sort -V -r)

if [[ -z "$PHP_VER" ]]; then
    error "No installable versioned PHP-FPM package found from apt repositories."
fi

info "PHP target version: ${PHP_VER}"

DB_SERVER_PKG=""
for pkg in mysql-server default-mysql-server mariadb-server; do
    CANDIDATE="$(apt-cache policy "$pkg" 2>/dev/null | awk -F': ' '/Candidate:/ {print $2}')"
    if [[ -n "$CANDIDATE" && "$CANDIDATE" != "(none)" ]]; then
        DB_SERVER_PKG="$pkg"
        break
    fi
done

if [[ -z "$DB_SERVER_PKG" ]]; then
    error "No supported database server package found (checked mysql-server/default-mysql-server/mariadb-server)."
fi

info "Database server package: ${DB_SERVER_PKG}"

apt-get install -y -qq \
    nginx \
    "php${PHP_VER}-fpm" \
    "php${PHP_VER}-mysql" \
    "php${PHP_VER}-xml" \
    "php${PHP_VER}-mbstring" \
    "php${PHP_VER}-curl" \
    "php${PHP_VER}-zip" \
    "php${PHP_VER}-intl" \
    "${DB_SERVER_PKG}" \
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
DROP DATABASE IF EXISTS \`${DB_NAME}\`;
DROP USER IF EXISTS '${DB_USER}'@'${DB_HOST}';
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
run_composer_install "${CARDY_DIR}"

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
        'trusted_proxies' => ['127.0.0.1', '::1'],
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
ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(16) NOT NULL DEFAULT 'user' AFTER is_admin;
UPDATE users SET role = CASE WHEN is_admin = 1 THEN 'admin' ELSE 'user' END WHERE role IS NULL OR role = '';
INSERT IGNORE INTO users (username, password_hash, email, display_name, is_admin)
VALUES ('${ADMIN_USER}', '${ADMIN_HASH}', '', '${ADMIN_USER}', 1);
UPDATE users SET role = 'admin', is_admin = 1 WHERE username = '${ADMIN_USER}';
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

# Install Cardy single unified vhost config
cp "${CARDY_DIR}/config/nginx/cardy.conf"   /etc/nginx/sites-available/cardy

# Patch PHP-FPM socket path for actual installed version
FPM_SOCK="/var/run/php/php${PHP_VER}-fpm.sock"
sed -i "s|php8.2-fpm.sock|php${PHP_VER}-fpm.sock|g" \
    /etc/nginx/sites-available/cardy

ln -sf /etc/nginx/sites-available/cardy   /etc/nginx/sites-enabled/cardy

# Remove legacy split-site config if it exists
rm -f /etc/nginx/sites-enabled/cardy-webui /etc/nginx/sites-available/cardy-webui

# Remove legacy DAV site config if it exists
rm -f /etc/nginx/sites-enabled/cardy-dav /etc/nginx/sites-available/cardy-dav

nginx -t && systemctl reload nginx
success "Nginx configured."

header "Configuring PHP upload limits"
configure_php_upload_limits "${PHP_VER}"

# -------- Set up document root symlinks ---------------------
header "Setting up web roots"

# Web root for DAV: public/dav/
ln -sfn "${CARDY_DIR}/public/dav"    "${CARDY_DIR}/dav"
ln -sfn "${CARDY_DIR}/public/webui"  "${CARDY_DIR}/webui"

# Update nginx root path to the installed location
sed -i "s|root /var/www/cardy/public;|root ${CARDY_DIR}/public;|" \
    /etc/nginx/sites-available/cardy

# Update assets alias to the installed location
sed -i "s|alias /var/www/cardy/public/webui/assets/;|alias ${CARDY_DIR}/public/webui/assets/;|" \
    /etc/nginx/sites-available/cardy

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
if [[ ${DB_PASS_GENERATED} -eq 1 ]]; then
    echo -e "  ${BOLD}Generated DB password${RESET} ${YELLOW}${DB_PASS}${RESET}"
fi
echo -e ""
echo -e "  ${BOLD}Admin user${RESET}     ${CYAN}${ADMIN_USER}${RESET}"
echo -e ""
echo -e "  Use ${CYAN}cardy-ctl${RESET} to manage users from the command line."
echo -e ""
success "Cardy is ready!"

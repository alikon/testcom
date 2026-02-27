#!/bin/bash

set -e

echo "--- Starting Post-Creation Setup ---"

DB_NAME="test_joomla"
DB_USER="joomla_ut"
DB_PASS="joomla_ut"
ADMIN_USER="ci-admin"
ADMIN_REAL_NAME="john doe"
ADMIN_PASS="joomla-17082005"
ADMIN_EMAIL="admin@example.org"
WORKSPACE_ROOT="/workspaces/testcom"
JOOMLA_ROOT="/var/www/html"

git config --global --add safe.directory "$WORKSPACE_ROOT"

# --- 1. Detect active DB profile ---
echo "--> Detecting active database profile..."
PROFILE="${PROFILE:-mysql}"
echo "--> Active profile: $PROFILE"

if [ "$PROFILE" = "mysql" ]; then
    DB_TYPE="mysqli"
    DB_HOST="mysql"
    DB_PREFIX="mysql_"

    echo "--> Waiting for MySQL (max 300s)..."
    for i in $(seq 1 60); do
        if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1;" >/dev/null 2>&1; then
            echo "--> MySQL is ready after $i attempts!"
            break
        fi
        MYSQL_ERR=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1;" 2>&1 | head -1)
        echo "--> Not ready yet (attempt $i/60): $MYSQL_ERR"
        sleep 5
    done

    if ! mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1;" >/dev/null 2>&1; then
        echo "❌ ERROR: MySQL non raggiungibile dopo 300 secondi"
        echo "--- Debug ---"
        getent hosts "$DB_HOST" || echo "Cannot resolve $DB_HOST"
        cat /etc/hosts
        exit 1
    fi

elif [ "$PROFILE" = "pgsql" ]; then
    DB_TYPE="pgsql"
    DB_HOST="db_pgsql"
    DB_PREFIX="pgsql_"

    echo "--> Waiting for PostgreSQL (max 60s)..."
    for i in $(seq 1 60); do
        if pg_isready -h "$DB_HOST" -U "$DB_USER" >/dev/null 2>&1; then
            echo "--> PostgreSQL is ready after $i attempts!"
            break
        fi
        echo "--> Not ready yet (attempt $i/60)..."
        sleep 1
    done

    if ! pg_isready -h "$DB_HOST" -U "$DB_USER" >/dev/null 2>&1; then
        echo "❌ ERROR: PostgreSQL non risponde dopo 60 secondi"
        exit 1
    fi

else
    echo "❌ ERROR: Profilo sconosciuto: $PROFILE. Valori validi: mysql, pgsql"
    exit 1
fi

# --- 2. Install Dependencies ---
echo "--> Installing Composer dependencies..."
cd "$WORKSPACE_ROOT"
composer install --no-interaction

echo "--> Installing Node dependencies..."
npm install

# --- 3. Build Extension ---
echo "--> Building extension..."
if [ -f "vendor/bin/robo" ]; then
    vendor/bin/robo build
else
    echo "Robo not found, skipping build."
fi

# --- 4. Install Joomla ---
if [ -f "$JOOMLA_ROOT/configuration.php" ]; then
    echo "--> Joomla already installed in volume, skipping."
else
    echo "--> Installing Joomla..."
    rm -f "$JOOMLA_ROOT/index.html"
    cd "$JOOMLA_ROOT"

    echo "--> Downloading Joomla nightly..."
    curl -o joomla.tar.zst -L https://developer.joomla.org/download-nightly.php/stable/debug/full/joomla.tar.zst
    tar xfa joomla.tar.zst
    rm joomla.tar.zst

    echo "--> Running Joomla installer..."
    php installation/joomla.php install \
        --site-name="Joomla CMS Test" \
        --admin-user="$ADMIN_REAL_NAME" \
        --admin-username="$ADMIN_USER" \
        --admin-password="$ADMIN_PASS" \
        --admin-email="$ADMIN_EMAIL" \
        --db-type="$DB_TYPE" \
        --db-host="$DB_HOST" \
        --db-name="$DB_NAME" \
        --db-user="$DB_USER" \
        --db-pass="$DB_PASS" \
        --db-prefix="$DB_PREFIX" \
        --db-encryption="0" \
        --public-folder=""

    # --- 5. Configure Joomla ---
    echo "--> Configuring Joomla..."
    php cli/joomla.php config:set debug=true error_reporting=maximum
    php cli/joomla.php config:set mailer=smtp
    php cli/joomla.php config:set smtphost=mailpit
    php cli/joomla.php config:set smtpport=1025
    php cli/joomla.php config:set smtpauth=0
    php cli/joomla.php config:set smtpsecure=none

    # --- 6. Install extension if present ---
    ALIKONWEB_PKG="${WORKSPACE_ROOT}/dist/pkg-alikonweb-current.zip"
    if [ -f "$ALIKONWEB_PKG" ]; then
        echo "--> Installing Alikonweb extension..."
        php "$JOOMLA_ROOT/cli/joomla.php" extension:install --path="$ALIKONWEB_PKG"
        cd "$WORKSPACE_ROOT" && vendor/bin/robo map "$JOOMLA_ROOT"
    fi

    # --- 7. phpMyAdmin (solo mysql) ---
    if [ "$PROFILE" = "mysql" ]; then
        PMA_ROOT="/var/www/html/phpmyadmin"
        PMA_VERSION="5.2.1"
        echo "--> Installing phpMyAdmin $PMA_VERSION..."
        mkdir -p "$PMA_ROOT"
        curl -sL -o /tmp/phpmyadmin.tar.gz \
            "https://files.phpmyadmin.net/phpMyAdmin/${PMA_VERSION}/phpMyAdmin-${PMA_VERSION}-all-languages.tar.gz"
        tar xf /tmp/phpmyadmin.tar.gz --strip-components=1 -C "$PMA_ROOT"
        rm /tmp/phpmyadmin.tar.gz
        cp "$PMA_ROOT/config.sample.inc.php" "$PMA_ROOT/config.inc.php"
        sed -i "s/\['host'\] = 'localhost'/['host'] = 'mysql'/" "$PMA_ROOT/config.inc.php"
        BLOWFISH=$(openssl rand -base64 32 | tr -d '=+/' | head -c 32)
        sed -i "s/\['blowfish_secret'\] = ''/['blowfish_secret'] = '$BLOWFISH'/" "$PMA_ROOT/config.inc.php"
    fi

    # --- 8. Codespaces proxy fix ---
    echo "--> Applying Codespaces reverse-proxy fix..."
    cat > "$JOOMLA_ROOT/fix.php" << 'EOF'
<?php
if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_HOST']   = $_SERVER['HTTP_X_FORWARDED_HOST'];
    $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
    $_SERVER['HTTPS']       = 'on';
    $_SERVER['SERVER_PORT'] = 443;
}
EOF

    cp "$JOOMLA_ROOT/fix.php" "$JOOMLA_ROOT/administrator/fix.php"
    sed -i '2i require_once __DIR__ . "/fix.php";' "$JOOMLA_ROOT/index.php"
    sed -i '2i require_once __DIR__ . "/../fix.php";' "$JOOMLA_ROOT/administrator/index.php"

    # --- 9. Permessi ---
    echo "--> Setting permissions..."
    chown -R www-data:www-data "$JOOMLA_ROOT"
    chmod -R 755 "$JOOMLA_ROOT"
fi

# --- 10. Xdebug: trova il gateway e scrivi xdebug.ini corretto ---
echo "--> Configuring Xdebug client host..."

GATEWAY_HEX=$(awk 'NR>1 && $2=="00000000" {print $3; exit}' /proc/net/route)
if [ -n "$GATEWAY_HEX" ]; then
    XDEBUG_HOST=$(printf '%d.%d.%d.%d' \
        $((16#${GATEWAY_HEX:6:2})) \
        $((16#${GATEWAY_HEX:4:2})) \
        $((16#${GATEWAY_HEX:2:2})) \
        $((16#${GATEWAY_HEX:0:2})))
    echo "--> Gateway trovato: $XDEBUG_HOST"
else
    XDEBUG_HOST="host.docker.internal"
    echo "--> Gateway non trovato, fallback: $XDEBUG_HOST"
fi

cat > /usr/local/etc/php/conf.d/99-xdebug.ini << EOF
zend_extension=xdebug

xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_port=9003
xdebug.client_host=${XDEBUG_HOST}
xdebug.idekey=VSCODE
xdebug.log_level=0
EOF

echo "--> xdebug.ini scritto con host: $XDEBUG_HOST"

# --- 11. Cypress ---
echo "--> Setting up Cypress..."
cd "$WORKSPACE_ROOT"

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    git update-index --assume-unchanged ./node_modules/.bin/cypress 2>/dev/null || true
fi

chmod +x ./node_modules/.bin/cypress
npx cypress install

cp cypress.config.dist.js cypress.config.js

if [ -n "$CODESPACE_NAME" ] && [ -n "$GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN" ]; then
    BASE_URL="https://${CODESPACE_NAME}-80.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
    echo "--> Codespace detected, baseUrl: $BASE_URL"
else
    BASE_URL="http://localhost"
    echo "--> Local environment, baseUrl: $BASE_URL"
fi

sed -i "s|baseUrl: 'http://localhost[^']*'|baseUrl: '${BASE_URL}'|" cypress.config.js
sed -i "/db_prefix: process.env.DB_PREFIX/a \    cmsPath: '${JOOMLA_ROOT}'," cypress.config.js

# --- 12. Restart Apache ---
echo "--> Restarting Apache..."
service apache2 restart || apache2ctl restart || true

# --- Done ---
DETAILS_FILE="${WORKSPACE_ROOT}/codespace-details.txt"
{
    echo ""
    echo "================================================"
    echo "✅ Setup complete! Your environment is ready."
    echo "================================================"
    echo ""
    echo "Profile attivo:  $PROFILE"
    echo "Xdebug host:     $XDEBUG_HOST"
    echo ""
    echo "Joomla Admin:"
    echo "  URL:      ${BASE_URL}/administrator"
    echo "  Username: $ADMIN_USER"
    echo "  Password: $ADMIN_PASS"
    echo ""
    if [ "$PROFILE" = "mysql" ]; then
    echo "phpMyAdmin:"
    echo "  URL:      ${BASE_URL}/phpmyadmin"
    echo "  Username: $DB_USER"
    echo "  Password: $DB_PASS"
    echo ""
    fi
    echo "Mailpit:  porta 8025"
    echo "Cypress:  pronto"
    echo "Xdebug:   porta 9003 → $XDEBUG_HOST"
    echo "================================================"
} | tee "$DETAILS_FILE"

cat "$DETAILS_FILE"

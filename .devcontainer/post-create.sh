#!/bin/bash

set -e

echo "--- Starting Post-Creation Setup ---"

# Configuration variables
DB_NAME="test_joomla"
DB_USER="joomla_ut"
DB_PASS="joomla_ut"
ADMIN_USER="ci-admin"
ADMIN_REAL_NAME="john doe"
ADMIN_PASS="joomla-17082005"
ADMIN_EMAIL="admin@example.org"
WORKSPACE_ROOT="/workspaces/testcom"
JOOMLA_ROOT="/var/www/html"

git config --global --add safe.directory $WORKSPACE_ROOT

# --- 1. Detect active DB profile ---
echo "--> Detecting active database profile..."

ENV_FILE="$WORKSPACE_ROOT/.devcontainer/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "❌ ERROR: File .env non trovato in $ENV_FILE"
    exit 1
fi

# New Way: Use the environment variable, fallback to .env if needed
if [ -z "$PROFILE" ]; then
    if [ -f "$ENV_FILE" ]; then
        PROFILE=$(grep -E '^PROFILE=' "$ENV_FILE" | cut -d '=' -f2)
    else
        PROFILE="mysql" # Default fallback
    fi
fi

if [ -z "$PROFILE" ]; then
    echo "❌ ERROR: PROFILE non definito nel file .env"
    exit 1
fi

echo "--> Active profile: $PROFILE"

if [ "$PROFILE" = "mysql" ]; then
    DB_TYPE="mysqli"
    DB_HOST="mysql"
    DB_PREFIX="mysql_"

    echo "--> Waiting for MySQL (max 60s)..."
    for i in {1..60}; do
        # We use a simple TCP check first to see if the port is even open
        if timeout 1s bash -c "cat < /dev/null > /dev/tcp/$DB_HOST/3306" 2>/dev/null; then
            echo "--> MySQL Port is open! Checking readiness..."
            # Now check if it's actually accepting logins
            if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1;" >/dev/null 2>&1; then
                echo "--> MySQL is ready and authenticated!"
                break
            fi
        fi
        echo "--> MySQL not ready yet (attempt $i)..."
        sleep 2
    done

elif [ "$PROFILE" = "pgsql" ]; then
    DB_TYPE="pgsql"
    DB_HOST="db_pgsql"
    DB_PREFIX="pgsql_"

    echo "--> Waiting for PostgreSQL (max 60s)..."
    for i in {1..60}; do
        if pg_isready -h "$DB_HOST" -U "$DB_USER" >/dev/null 2>&1; then
            echo "--> PostgreSQL is ready!"
            break
        fi
        sleep 1
    done

    if ! pg_isready -h "$DB_HOST" -U "$DB_USER" >/dev/null 2>&1; then
        echo "❌ ERROR: PostgreSQL non risponde dopo 60 secondi"
        exit 1
    fi

else
    echo "❌ ERROR: Profilo sconosciuto: $PROFILE"
    exit 1
fi

# --- 2. Install Dependencies ---
echo "--> Installing dependencies..."
composer install
npm install

# --- 3. Build Extension ---
echo "--> Building extension..."
[ -f "vendor/bin/robo" ] && vendor/bin/robo build || echo "Robo not found, skipping build."

# --- 4. Install Joomla ---
echo "--> Installing Joomla..."
rm -f $JOOMLA_ROOT/index.html
cd $JOOMLA_ROOT
curl -o joomla.tar.zst -L https://developer.joomla.org/download-nightly.php/stable/debug/full/joomla.tar.zst
tar xfa joomla.tar.zst
rm joomla.tar.zst

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

ALIKONWEB_PKG="${WORKSPACE_ROOT}/dist/pkg-alikonweb-current.zip"
if [ -f "$ALIKONWEB_PKG" ]; then
    php cli/joomla.php extension:install --path="$ALIKONWEB_PKG"
    cd $WORKSPACE_ROOT && vendor/bin/robo map $JOOMLA_ROOT
fi

# --- 6. phpMyAdmin ---
PMA_ROOT="/var/www/html/phpmyadmin"
echo "--> Downloading phpMyAdmin into $PMA_ROOT..."
PMA_VERSION=5.2.1
mkdir -p $PMA_ROOT
curl -o /tmp/phpmyadmin.tar.gz https://files.phpmyadmin.net/phpMyAdmin/${PMA_VERSION}/phpMyAdmin-${PMA_VERSION}-all-languages.tar.gz
tar xf /tmp/phpmyadmin.tar.gz --strip-components=1 -C $PMA_ROOT
rm /tmp/phpmyadmin.tar.gz
cp $PMA_ROOT/config.sample.inc.php $PMA_ROOT/config.inc.php
sed -i "/\['AllowNoPassword'\] = false/a \$cfg['Servers'][\$i]['host'] = 'mysql';" $PMA_ROOT/config.inc.php

# --- 7. Codespaces Fix ---
echo "--> Applying Codespaces fix..."

cat > $JOOMLA_ROOT/fix.php << 'EOF'
<?php
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost:80') {
    if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
    }
}
EOF

cp $JOOMLA_ROOT/fix.php $JOOMLA_ROOT/administrator/fix.php
sed -i '2i require_once __DIR__ . "/fix.php";' $JOOMLA_ROOT/index.php
sed -i '2i require_once __DIR__ . "/../fix.php";' $JOOMLA_ROOT/administrator/index.php

# --- 8. Cypress ---
echo "--> Finalizing and setting up Cypress..."
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    git update-index --assume-unchanged ./node_modules/.bin/cypress || true
fi
chmod +x ./node_modules/.bin/cypress
chown -R www-data:www-data $JOOMLA_ROOT
npx cypress install

cd "$WORKSPACE_ROOT"
cp cypress.config.dist.js cypress.config.js
sed -i "/db_prefix: process.env.DB_PREFIX/a \    cmsPath: '${JOOMLA_ROOT}'," cypress.config.js
sed -i "s|baseUrl: 'http://localhost/'|baseUrl: 'http://localhost'|" cypress.config.js
service apache2 restart

DETAILS_FILE="${WORKSPACE_ROOT}/codespace-details.txt"
{
    echo ""
    echo "---"
    echo "✅ Setup complete! Your environment is ready."
    echo ""
    echo "Joomla Admin Login:"
    echo "  Username: $ADMIN_USER"
    echo "  Password: $ADMIN_PASS"
    echo ""
    echo "phpMyAdmin Login:"
    echo "  Username: joomla_ut"
    echo "  Password: joomla_ut"
    echo ""
    echo "Mailpit Web UI available on port 8025"
    echo ""
    echo "Cypress ready to use"
    echo ""
    echo "Xdebug ready on port 9003"
    echo "---"
} | tee "$DETAILS_FILE"
#!/bin/bash

set -e

echo "--- Starting Local Setup ---"

DB_NAME="test_joomla"
DB_USER="joomla_ut"
DB_PASS="joomla_ut"
ADMIN_USER="ci-admin"
ADMIN_REAL_NAME="john doe"
ADMIN_PASS="joomla-17082005"
ADMIN_EMAIL="admin@example.org"
WORKSPACE_ROOT="/workspaces/testcom"
JOOMLA_ROOT="/var/www/html"

# --- 1. Install Dependencies ---
echo "--> Installing dependencies..."
cd $WORKSPACE_ROOT
composer install
npm install

# --- 2. Build Extension ---
echo "--> Building extension..."
[ -f "vendor/bin/robo" ] && vendor/bin/robo build || echo "Robo not found, skipping build."
rm -f $JOOMLA_ROOT/configuration.php

# --- 3. Install Joomla ---
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
    --db-type="mysqli" \
    --db-host="mysql" \
    --db-name="$DB_NAME" \
    --db-user="$DB_USER" \
    --db-pass="$DB_PASS" \
    --db-prefix="mysql_" \
    --db-encryption="0" \
    --public-folder=""

# --- 4. Configure Joomla ---
echo "--> Configuring Joomla..."
php cli/joomla.php config:set debug=true error_reporting=maximum
php cli/joomla.php config:set mailer=smtp
php cli/joomla.php config:set smtphost=mailpit
php cli/joomla.php config:set smtpport=1025
php cli/joomla.php config:set smtpauth=0
php cli/joomla.php config:set smtpsecure=none

# Install extension if available
ALIKONWEB_PKG="${WORKSPACE_ROOT}/dist/pkg-alikonweb-current.zip"
if [ -f "$ALIKONWEB_PKG" ]; then
    php cli/joomla.php extension:install --path="$ALIKONWEB_PKG"
    cd $WORKSPACE_ROOT && vendor/bin/robo map $JOOMLA_ROOT
fi

# --- 5. Download phpMyAdmin ---
PMA_ROOT="/var/www/html/phpmyadmin"
echo "--> Downloading phpMyAdmin into $PMA_ROOT..."
PMA_VERSION=5.2.2
mkdir -p $PMA_ROOT
curl -o /tmp/phpmyadmin.tar.gz https://files.phpmyadmin.net/phpMyAdmin/${PMA_VERSION}/phpMyAdmin-${PMA_VERSION}-all-languages.tar.gz
tar xf /tmp/phpmyadmin.tar.gz --strip-components=1 -C $PMA_ROOT
rm /tmp/phpmyadmin.tar.gz
cp $PMA_ROOT/config.sample.inc.php $PMA_ROOT/config.inc.php
sed -i "/\['AllowNoPassword'\] = false/a \$cfg['Servers'][\$i]['host'] = 'mysql';" $PMA_ROOT/config.inc.php

# --- 6. Configure Apache ---
echo "--> Configuring Apache..."
cat > /etc/apache2/sites-available/000-default.conf << 'EOF'
<VirtualHost *:80>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# --- 7. Finalize ---
echo "--> Finalizing..."
chown -R www-data:www-data $JOOMLA_ROOT
cd "$WORKSPACE_ROOT"
cp cypress.config.dist.js cypress.config.js
sed -i "/db_prefix: process.env.DB_PREFIX/a \    cmsPath: '${JOOMLA_ROOT}'," cypress.config.js
sed -i "s|baseUrl: 'http://localhost/'|baseUrl: 'http://localhost'|" cypress.config.js
apache2ctl restart

echo ""
echo "✅ Setup complete!"
echo ""
echo "Joomla:      http://localhost"
echo "Admin:       http://localhost/administrator  (user: $ADMIN_USER / pass: $ADMIN_PASS)"
echo "phpMyAdmin:  http://localhost/phpmyadmin     (user: $DB_USER / pass: $DB_PASS)"
echo "Mailpit:     http://localhost:8025"
echo "run docker compose exec app bash /usr/local/bin/setup.sh to re-run this setup script if needed."

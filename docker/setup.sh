#!/bin/bash
set -e
echo "--- Starting Local Setup ---"

# --- Configuration ---
DB_NAME="test_joomla"
DB_USER="joomla_ut"
DB_PASS="joomla_ut"
ADMIN_USER="ci-admin"
ADMIN_REAL_NAME="john doe"
ADMIN_PASS="joomla-17082005"
ADMIN_EMAIL="admin@example.org"
WORKSPACE_ROOT="/workspaces/testcom"
JOOMLA_ROOT="/var/www/html"
CACHE_DIR="/var/cache/setup-downloads"
FORCE_DOWNLOAD="${FORCE_DOWNLOAD:-0}"

# Joomla version to download (can be overridden via environment variable)
JOOMLA_VERSION="${JOOMLA_VERSION:-6.1.0}"  # Default to 6.1.0

mkdir -p "$CACHE_DIR"

# --- Function to get Joomla download URL from GitHub releases (tar.gz) ---
get_joomla_url() {
    local version="$1"
    local url
    
    echo "    Looking for Joomla $version on GitHub releases..." >&2
    
    # Try to find tar.gz package (Joomla provides both zip and tar.gz)
    # First, try to get the release info directly
    local release_info
    release_info=$(curl -s "https://api.github.com/repos/joomla/joomla-cms/releases/tags/$version" 2>/dev/null)
    
    # If direct tag fetch fails, try searching through releases
    if [ -z "$release_info" ] || echo "$release_info" | grep -q "Not Found"; then
        # Look for tar.gz files
        url=$(curl -s "https://api.github.com/repos/joomla/joomla-cms/releases" | \
              grep -i '"browser_download_url".*\.tar\.gz' | \
              grep -i "/$version/" | \
              sed -E 's/.*"browser_download_url":\s*"([^"]+)".*/\1/' | \
              head -1)
        
        # If no tar.gz found, look for zip as fallback
        if [ -z "$url" ]; then
            echo "    No tar.gz found, looking for zip fallback..." >&2
            url=$(curl -s "https://api.github.com/repos/joomla/joomla-cms/releases" | \
                  grep -i '"browser_download_url".*Full_Package\.zip' | \
                  grep -i "/$version/" | \
                  sed -E 's/.*"browser_download_url":\s*"([^"]+)".*/\1/' | \
                  head -1)
        fi
    else
        # Look for tar.gz in the specific release
        url=$(echo "$release_info" | grep -i '"browser_download_url".*\.tar\.gz' | \
              sed -E 's/.*"browser_download_url":\s*"([^"]+)".*/\1/' | \
              head -1)
        
        # If no tar.gz found, look for zip as fallback
        if [ -z "$url" ]; then
            echo "    No tar.gz found, looking for zip fallback..." >&2
            url=$(echo "$release_info" | grep -i '"browser_download_url".*Full_Package\.zip' | \
                  sed -E 's/.*"browser_download_url":\s*"([^"]+)".*/\1/' | \
                  head -1)
        fi
    fi
    
    if [ -z "$url" ]; then
        echo "ERROR: Could not find download URL for version $version in joomla/joomla-cms releases." >&2
        echo "" >&2
        echo "Available stable versions:" >&2
        curl -s "https://api.github.com/repos/joomla/joomla-cms/releases" | \
            grep -i '"tag_name":' | \
            grep -vE 'alpha|beta|rc' | \
            head -10 >&2
        return 1
    fi
    
    echo "$url"
}

# --- Function to download and extract Joomla from tar.gz ---
download_and_extract_joomla() {
    local version="$1"
    local archive_file="$CACHE_DIR/joomla_${version}.tar.gz"
    
    # Get the download URL
    local url
    url=$(get_joomla_url "$version")
    local url_exit_code=$?
    
    if [ $url_exit_code -ne 0 ]; then
        return 1
    fi
    
    # Trim any whitespace
    url=$(echo "$url" | xargs)
    
    echo "    URL found: $url"
    
    # Check if the version actually exists by testing the URL
    echo "    Checking if version $version exists..."
    local http_status
    http_status=$(curl -L -s -o /dev/null -w "%{http_code}" --max-time 10 "$url")
    
    if [ "$http_status" != "200" ]; then
        echo "ERROR: Version $version does not exist or is not accessible (HTTP $http_status)" >&2
        echo "" >&2
        echo "Tip: Joomla 6.x does not exist yet. Latest stable is 5.2.x" >&2
        echo "Use a valid version like: JOOMLA_VERSION=5.2.0" >&2
        return 1
    fi
    
    # Determine file type from URL
    local file_ext="tar.gz"
    if [[ "$url" == *.zip ]]; then
        file_ext="zip"
    fi
    
    # Update archive file extension if it's a zip
    if [ "$file_ext" == "zip" ]; then
        archive_file="$CACHE_DIR/joomla_${version}.zip"
    fi
    
    # Download if forced or not cached
    if [ "$FORCE_DOWNLOAD" = "1" ] || [ ! -f "$archive_file" ]; then
        echo "    Downloading Joomla $version ($file_ext)..."
        
        # Download with progress
        if ! curl -L --max-time 300 "$url" -o "$archive_file" --fail --show-error --progress-bar; then
            echo "ERROR: Failed to download from $url" >&2
            rm -f "$archive_file"
            return 1
        fi
        
        # Check file size
        local file_size=$(stat -c%s "$archive_file" 2>/dev/null || stat -f%z "$archive_file" 2>/dev/null)
        local file_size_hr=$(du -h "$archive_file" | cut -f1)
        
        echo "    Downloaded: $file_size_hr ($file_size bytes)"
        
        # Verify file integrity based on type
        echo "    Verifying archive integrity..."
        
        if [ "$file_ext" == "tar.gz" ]; then
            # Verify tar.gz integrity
            if ! tar -tzf "$archive_file" > /dev/null 2>&1; then
                echo "ERROR: Downloaded tar.gz file is corrupt!" >&2
                echo "    First 200 bytes of downloaded file:" >&2
                head -c 200 "$archive_file" >&2
                rm -f "$archive_file"
                return 1
            fi
            echo "    ✅ Joomla $version downloaded and verified (tar.gz)"
        else
            # Verify zip integrity
            if ! unzip -t "$archive_file" > /dev/null 2>&1; then
                echo "ERROR: Downloaded zip file is corrupt!" >&2
                echo "    First 200 bytes of downloaded file:" >&2
                head -c 200 "$archive_file" >&2
                rm -f "$archive_file"
                return 1
            fi
            echo "    ✅ Joomla $version downloaded and verified (zip)"
        fi
    else
        echo "    Joomla $version found in cache, using cached version"
        # Verify cached archive is still valid
        if [ "$file_ext" == "tar.gz" ]; then
            if ! tar -tzf "$archive_file" > /dev/null 2>&1; then
                echo "    Cached file is corrupt, re-downloading..." >&2
                rm -f "$archive_file"
                download_and_extract_joomla "$version"
                return $?
            fi
        else
            if ! unzip -t "$archive_file" > /dev/null 2>&1; then
                echo "    Cached file is corrupt, re-downloading..." >&2
                rm -f "$archive_file"
                download_and_extract_joomla "$version"
                return $?
            fi
        fi
    fi
    
    # Clean Joomla root directory
    echo "    Cleaning $JOOMLA_ROOT..."
    rm -rf "$JOOMLA_ROOT"/*
    
    # Extract archive to Joomla root
    echo "    Extracting Joomla $version to $JOOMLA_ROOT..."
    
    if [ "$file_ext" == "tar.gz" ]; then
        # Extract tar.gz
        if ! tar -xzf "$archive_file" -C "$JOOMLA_ROOT"; then
            echo "ERROR: Failed to extract tar.gz file!" >&2
            return 1
        fi
    else
        # Extract zip
        if ! unzip -q "$archive_file" -d "$JOOMLA_ROOT"; then
            echo "ERROR: Failed to extract zip file!" >&2
            return 1
        fi
    fi
    
    # Handle potential nested directory structure (sometimes Joomla extracts to a subdirectory)
    local extracted_dirs=$(find "$JOOMLA_ROOT" -maxdepth 1 -type d | wc -l)
    if [ $extracted_dirs -eq 2 ]; then
        # Only one subdirectory found, move its contents up
        local subdir=$(find "$JOOMLA_ROOT" -maxdepth 1 -type d ! -path "$JOOMLA_ROOT" | head -1)
        if [ -n "$subdir" ] && [ -f "$subdir/administrator/index.php" ]; then
            echo "    Moving files from nested directory..."
            mv "$subdir"/* "$subdir"/.[!.]* "$JOOMLA_ROOT"/ 2>/dev/null || true
            rm -rf "$subdir"
        fi
    fi
    
    echo "    ✅ Joomla $version extracted successfully"
    return 0
}

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
echo "--> Installing Joomla $JOOMLA_VERSION..."

# Remove old index.html if exists
rm -f $JOOMLA_ROOT/index.html

# Download and extract specific Joomla version
cd $JOOMLA_ROOT
if ! download_and_extract_joomla "$JOOMLA_VERSION"; then
    echo "ERROR: Failed to download/extract Joomla $JOOMLA_VERSION" >&2
    exit 1
fi

# Check if installation folder exists
if [ ! -d "$JOOMLA_ROOT/installation" ]; then
    echo "ERROR: Joomla installation folder not found after extraction!" >&2
    echo "Contents of $JOOMLA_ROOT:" >&2
    ls -la "$JOOMLA_ROOT" | head -20 >&2
    exit 1
fi

# Run Joomla installation
echo "    Running Joomla installer..."
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
    echo "--> Installing extension..."
    php cli/joomla.php extension:install --path="$ALIKONWEB_PKG"
    cd $WORKSPACE_ROOT && vendor/bin/robo map $JOOMLA_ROOT
fi

# --- 5. Download phpMyAdmin ---
PMA_ROOT="/var/www/html/phpmyadmin"
PMA_VERSION=5.2.3
PMA_CACHE="$CACHE_DIR/phpmyadmin-${PMA_VERSION}.tar.gz"
echo "--> Installing phpMyAdmin into $PMA_ROOT..."
mkdir -p $PMA_ROOT

if [ "$FORCE_DOWNLOAD" = "1" ] || [ ! -f "$PMA_CACHE" ]; then
    echo "    Downloading phpMyAdmin..."
    curl -o "$PMA_CACHE" https://files.phpmyadmin.net/phpMyAdmin/${PMA_VERSION}/phpMyAdmin-${PMA_VERSION}-all-languages.tar.gz --progress-bar
else
    echo "    phpMyAdmin found in cache, skipping download."
fi
tar xzf "$PMA_CACHE" --strip-components=1 -C $PMA_ROOT

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
cp cypress.config.dist.js cypress.config.js 2>/dev/null || echo "No cypress.config.dist.js found, skipping"
sed -i "/db_prefix: process.env.DB_PREFIX/a \    cmsPath: '${JOOMLA_ROOT}'," cypress.config.js 2>/dev/null || true
sed -i "s|baseUrl: 'http://localhost/'|baseUrl: 'http://localhost'|" cypress.config.js 2>/dev/null || true
apache2ctl restart

echo ""
echo "✅ Setup complete!"
echo ""
echo "Joomla $JOOMLA_VERSION: http://localhost"
echo "Admin:       http://localhost/administrator  (user: $ADMIN_USER / pass: $ADMIN_PASS)"
echo "phpMyAdmin:  http://localhost/phpmyadmin     (user: $DB_USER / pass: $DB_PASS)"
echo "Mailpit:     http://localhost:8025"
echo ""
echo "To use a different Joomla version: JOOMLA_VERSION=4.4.0 $0"
echo "To force re-download: FORCE_DOWNLOAD=1 $0"

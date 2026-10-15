#!/bin/sh
set -e

echo "Starting Twisted Oak BBQ (WordPress)..."

# Ensure uploads directory exists and is writable
echo "Setting permissions..."
mkdir -p wp-content/uploads
chmod -R 775 wp-content/uploads 2>/dev/null || true

echo "Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

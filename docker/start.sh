#!/bin/bash
set -e

echo "🚀 Démarrage Kairos Backend..."

# Lien storage public
php artisan storage:link --force 2>/dev/null || true

# Cache config/routes pour la prod
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations
php artisan migrate --force

echo "✅ Setup terminé, démarrage des services..."

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

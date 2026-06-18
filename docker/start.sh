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

# Seeder (uniquement si aucun utilisateur n'existe)
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1)
if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
    echo "🌱 Aucun utilisateur trouvé, lancement du seeder..."
    php artisan db:seed --force
fi

echo "✅ Setup terminé, démarrage des services..."

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

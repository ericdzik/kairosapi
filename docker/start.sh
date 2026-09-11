#!/bin/bash
set -euo pipefail

echo "🚀 Démarrage Kairos Backend..."

# ----- Attendre que PostgreSQL soit prêt -----
echo "⏳ Vérification de la connexion à la base de données..."
MAX_RETRIES=30
RETRY=0
until php artisan db:show --json > /dev/null 2>&1; do
    RETRY=$((RETRY + 1))
    if [ "$RETRY" -ge "$MAX_RETRIES" ]; then
        echo "❌ Impossible de joindre la base de données après ${MAX_RETRIES} tentatives. Abandon."
        exit 1
    fi
    echo "  → Tentative ${RETRY}/${MAX_RETRIES} — nouvelle tentative dans 2s..."
    sleep 2
done
echo "✅ Base de données accessible."

# ----- Lien storage public -----
php artisan storage:link --force 2>/dev/null || true

# ----- Vider les caches existants avant de reconstruire -----
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan event:clear

# ----- Reconstruire les caches pour la prod -----
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ----- Migrations -----
echo "🗄️  Exécution des migrations..."
php artisan migrate --force

# ----- Seeder : uniquement si la table users est vide -----
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::withTrashed()->count();" 2>/dev/null | grep -E '^[0-9]+$' | tail -1 || echo "1")
if [ "$USER_COUNT" = "0" ]; then
    echo "🌱 Table users vide — lancement du seeder initial..."
    php artisan db:seed --force
    echo "✅ Seeder exécuté."
else
    echo "ℹ️  ${USER_COUNT} utilisateur(s) déjà présent(s) — seeder ignoré."
    # Resetter le mot de passe admin si FORCE_ADMIN_RESET=true
    if [ "${FORCE_ADMIN_RESET:-false}" = "true" ]; then
        echo "🔑 FORCE_ADMIN_RESET=true — reset du mot de passe admin..."
        php artisan kairos:reset-admin
        echo "✅ Mot de passe admin réinitialisé."
    fi
fi

# ----- Optimisation finale -----
php artisan optimize

echo "✅ Setup terminé. Démarrage des services (nginx + php-fpm)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

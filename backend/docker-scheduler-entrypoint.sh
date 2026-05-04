#!/bin/sh
# EdgeRX scheduler worker — runs `php artisan schedule:work` continuously
# to fire scheduled commands (auto-release buying groups, daily digest).
#
# Waits for the backend service to finish migrating before starting so we
# don't race on schema changes. The probe is the same as docker-entrypoint.sh
# but we DO NOT run migrations / seeders here.

set -eu

echo "──────────────── EdgeRX scheduler boot ────────────────"
echo "APP_ENV=${APP_ENV:-?}"
echo "DB_CONNECTION=${DB_CONNECTION:-?}"
echo "DB_HOST=${DB_HOST:-?}"
echo "DB_DATABASE=${DB_DATABASE:-?}"
echo "DB_USERNAME=${DB_USERNAME:-?}"
echo "DB_PASSWORD set? $([ -n "${DB_PASSWORD:-}" ] && echo yes || echo no)"
echo "─────────────────────────────────────────────────────"

# Probe DB connectivity (same query as backend entrypoint) — give the backend
# extra time to run migrations on a fresh deploy.
echo "Probing DB + waiting for backend to finish migrating (up to 90s)..."
i=0
until php -r "
\$dsn = 'pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE') . ';sslmode=' . (getenv('DB_SSLMODE') ?: 'require');
try {
    \$pdo = new PDO(\$dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_TIMEOUT => 5]);
    // BE-28 fix: probe the LATEST migration's table (pricing_agreements from
    // Phase D2, plus the BE-10 cascade-restrict migration). If both are
    // present, the backend has fully migrated and we can start the scheduler.
    \$row = \$pdo->query(\"SELECT to_regclass('public.pricing_agreements') AS pa, to_regclass('public.transfer_requests') AS tr\")->fetch(PDO::FETCH_ASSOC);
    if (!\$row['pa'] || !\$row['tr']) {
        echo '  schema not ready yet (missing pricing_agreements or transfer_requests)' . PHP_EOL;
        exit(1);
    }
    echo '  schema ready (pricing_agreements + transfer_requests present)' . PHP_EOL;
    exit(0);
} catch (Throwable \$e) {
    echo '  PDO error: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"; do
    i=$((i + 1))
    if [ $i -ge 18 ]; then
        echo "ERROR: schema not ready after 90s — bailing"
        exit 1
    fi
    echo "  retrying in 5s ($i/18)..."
    sleep 5
done

echo ""
echo "──────── Starting Laravel scheduler (artisan schedule:work) ────────"
exec php artisan schedule:work

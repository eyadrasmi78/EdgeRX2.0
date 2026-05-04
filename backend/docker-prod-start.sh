#!/bin/sh
# BE-11 fix: production container start script.
# Runs the boot script (DB probe, migrate, seed-if-flagged, cache warmup),
# then hands control to supervisord which keeps php-fpm + nginx alive.

set -eu

# Run the boot tasks (migrations, config:cache, etc.)
/usr/local/bin/edgerx-entrypoint.sh

echo ""
echo "──────── Starting nginx + php-fpm via supervisord ────────"

# Hand off to supervisord (it becomes PID 1's primary worker)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

#!/bin/sh
set -e

# Unlike docker/php/entrypoint.sh's dev entrypoint (which tolerates a
# losing container in a three-way migration race and never fails the
# container), a production entrypoint should not start serving traffic
# against a schema migrations couldn't bring up to date — fail loud,
# let the platform's own restart/rollback policy handle it.
php artisan migrate --force

exec "$@"

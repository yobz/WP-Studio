#!/bin/sh
set -e

# Mirrors backend/composer.json's own "setup" script — the same
# bootstrap sequence a non-Docker developer already runs by hand
# (backend/README.md), so a fresh clone needs no undocumented step.
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --ansi
fi

mkdir -p database
touch database/database.sqlite

# database/ is bind-mounted from the host (see the ADR's Volume
# Strategy — kept host-inspectable, unlike storage/vendor/node_modules),
# so its ownership comes from Docker Desktop's host-to-container UID
# mapping (root:root here), not this image — and www-data (the PHP-FPM
# worker user) is in its own group, not root, so a group-only chmod
# doesn't reach it. Confirmed during this milestone's own live
# validation (every session write failed with SQLite's "attempt to
# write a readonly database" until `o+rw` was added here). Must run at
# container start, after the bind mount is attached — a build-time
# chmod in the Dockerfile has nothing to act on yet.
chmod -R o+rwX database

# backend, queue, and scheduler all use this entrypoint, so all three
# race to migrate on a genuinely simultaneous first `docker compose up`
# against the same SQLite file. `depends_on` in docker-compose.yml gives
# `backend` a head start in practice; tolerating a losing container's
# migrate failing (rather than crashing its entrypoint) is the simple
# fix for the remaining unlikely-but-possible race, deliberately chosen
# over a lock file for a three-container dev tool — see
# docs/adr/0013-docker-development-environment.md.
php artisan migrate --force || echo "migrate: skipped (already applied by another container, or a transient lock)"

exec "$@"

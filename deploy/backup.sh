#!/usr/bin/env bash
set -euo pipefail
umask 077
: "${BACKUP_DIR:?Set BACKUP_DIR to a protected directory on separate backup storage}"
cd "$(dirname "$0")"
mkdir -p "$BACKUP_DIR"
stamp=$(date -u +%Y%m%dT%H%M%SZ)
work=$(mktemp -d "$BACKUP_DIR/.rental-$stamp.XXXXXX")
resume() {
  docker compose start app >/dev/null || true
  docker compose exec -T app php artisan up >/dev/null || true
  docker compose start worker scheduler >/dev/null || true
}
trap resume EXIT
# Freeze synchronous and asynchronous writers before taking the paired snapshot.
docker compose exec -T app php artisan down --retry=60
docker compose stop worker scheduler app
docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$work/database.dump"
docker compose run --rm --no-deps -T app tar -C storage/app -czf - . > "$work/attachments.tar.gz"
(cd "$work" && sha256sum database.dump attachments.tar.gz > SHA256SUMS)
mv "$work" "$BACKUP_DIR/rental-$stamp"
echo "Backup ready: $BACKUP_DIR/rental-$stamp"

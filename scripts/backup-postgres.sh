#!/usr/bin/env bash
set -euo pipefail

# Daily PostgreSQL backup for Manolya Pharma.
# Requires: pg_dump, gzip (for plain SQL) or custom format.

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-manolya}"
DB_USERNAME="${DB_USERNAME:-manolya}"
BACKUP_DIR="${BACKUP_DIR:-./storage/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
FILE="${BACKUP_DIR}/manolya-${STAMP}.dump"

mkdir -p "${BACKUP_DIR}"

echo "[manolya-backup] starting ${FILE}"

pg_dump \
  -h "${DB_HOST}" \
  -p "${DB_PORT}" \
  -U "${DB_USERNAME}" \
  -d "${DB_DATABASE}" \
  -Fc \
  -f "${FILE}"

# Prune old dumps
find "${BACKUP_DIR}" -type f -name 'manolya-*.dump' -mtime "+${RETENTION_DAYS}" -delete || true

echo "[manolya-backup] done $(du -h "${FILE}" | cut -f1)"

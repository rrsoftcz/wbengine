#!/bin/sh
# Dump/restore only the fixed isolated API schema. Source data is never modified.
set -eu
umask 077
PILOT_DIRECTORY=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PILOT_RUNTIME="$PILOT_DIRECTORY/runtime"
PILOT_DUMP="$PILOT_RUNTIME/backup/api.sql"
PILOT_ERROR_LOG="$PILOT_RUNTIME/backup/error.log"
PILOT_BACKUP_CLEANED=false
PILOT_BACKUP_CLEANUP_FAILED=false
if [ ! -f "$PILOT_RUNTIME/bootstrap.env" ]; then printf 'Start the pilot first.\n' >&2; exit 1; fi
mkdir -p "$PILOT_RUNTIME/backup"
chmod 0700 "$PILOT_RUNTIME/backup"
pilot_compose() {
    PILOT_UID=$(id -u)
    PILOT_GID=$(id -g)
    export PILOT_UID PILOT_GID
    docker compose --project-name wbengine-admin-api-pilot --env-file "$PILOT_RUNTIME/bootstrap.env" --file "$PILOT_DIRECTORY/compose.yaml" "$@"
}
pilot_backup_cleanup() {
    if [ "$PILOT_BACKUP_CLEANED" = true ]; then return; fi
    PILOT_BACKUP_CLEANED=true
    if ! pilot_compose run --rm --no-deps seed php tests/pilot/backup-check.php cleanup > /dev/null 2>> "$PILOT_ERROR_LOG"; then
        printf 'Pilot cleanup could not remove the disposable restore schema.\n' >&2
        PILOT_BACKUP_CLEANUP_FAILED=true
    fi
    rm -f "$PILOT_DUMP"
}
trap pilot_backup_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
# Credentials expand only inside the owned DB container, never as process arguments.
if ! pilot_compose exec -T db sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysqldump -uroot --single-transaction --skip-lock-tables --skip-add-locks --no-tablespaces --set-gtid-purged=OFF --column-statistics=0 --hex-blob --default-character-set=utf8mb4 wbengine_api_pilot' > "$PILOT_DUMP" 2> "$PILOT_ERROR_LOG"; then
    printf 'The isolated API dump failed.\n' >&2
    exit 1
fi
pilot_compose run --rm --no-deps seed php tests/pilot/backup-check.php prepare 2>> "$PILOT_ERROR_LOG"
if ! pilot_compose exec -T db sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysql -uroot --binary-mode=1 --default-character-set=utf8mb4 wbengine_api_pilot_restore' < "$PILOT_DUMP" 2>> "$PILOT_ERROR_LOG"; then
    printf 'The isolated API restore failed.\n' >&2
    exit 1
fi
pilot_compose run --rm --no-deps seed php tests/pilot/backup-check.php compare 2>> "$PILOT_ERROR_LOG"
pilot_backup_cleanup
trap - EXIT INT TERM
if [ "$PILOT_BACKUP_CLEANUP_FAILED" = true ]; then exit 1; fi

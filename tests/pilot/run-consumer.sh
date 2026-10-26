#!/bin/sh
# Host-side, finite test controls. The consumer never receives a Docker socket.
set -eu
PILOT_DIRECTORY=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PILOT_RUNTIME="$PILOT_DIRECTORY/runtime"
PILOT_ADMIN_DIRECTORY=$(CDPATH= cd -- "${1:?Pass the admin checkout directory}" && pwd)
PILOT_CONTROL="$PILOT_RUNTIME/control"
PILOT_CONTAINER="wbengine-admin-api-pilot-consumer-$$"
PILOT_IMAGE=${PILOT_CONSUMER_IMAGE:-wbengine-admin-foundation-check:local}
PILOT_CLEANED=false
PILOT_CLEANUP_FAILED=false
if [ ! -f "$PILOT_RUNTIME/consumer.env" ] || [ ! -f "$PILOT_ADMIN_DIRECTORY/tests/api-pilot.ts" ]; then
    printf 'Start the provider and supply the admin pilot test checkout.\n' >&2
    exit 1
fi
mkdir -p "$PILOT_CONTROL"
# Only nonsecret request/acknowledgement files are writable by the test container.
chmod 0777 "$PILOT_CONTROL"
for PILOT_ACTION in revoke-second stop-second start-second; do
    rm -f "$PILOT_CONTROL/$PILOT_ACTION.request" "$PILOT_CONTROL/$PILOT_ACTION.done"
done
pilot_acknowledge() {
    # Stage outside the writable control dir, then replace the directory entry.
    # A consumer-created symlink can never redirect this write to a secret file.
    PILOT_ACK="$PILOT_RUNTIME/control-ack-$$.new"
    printf '%s\n' "$2" > "$PILOT_ACK"
    chmod 0644 "$PILOT_ACK"
    python3 -c 'import os, sys; os.replace(sys.argv[1], sys.argv[2])' "$PILOT_ACK" "$PILOT_CONTROL/$1.done"
}
pilot_cleanup() {
    if [ "$PILOT_CLEANED" = true ]; then return; fi
    PILOT_CLEANED=true
    docker rm -f "$PILOT_CONTAINER" > /dev/null 2>&1 || true
    if ! "$PILOT_DIRECTORY/pilot.sh" start-second > /dev/null 2>&1; then
        printf 'Pilot cleanup could not restart the second installation.\n' >&2
        PILOT_CLEANUP_FAILED=true
    fi
    if ! "$PILOT_DIRECTORY/pilot.sh" refresh-second > /dev/null 2>&1; then
        printf 'Pilot cleanup could not refresh the second test key.\n' >&2
        PILOT_CLEANUP_FAILED=true
    fi
}
trap pilot_cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
if ! "$PILOT_DIRECTORY/pilot.sh" start-second > /dev/null 2>&1 || ! "$PILOT_DIRECTORY/pilot.sh" refresh-second > /dev/null 2>&1; then
    printf 'Pilot consumer could not establish a healthy second installation.\n' >&2
    exit 1
fi
# Only the public CA is mounted; bootstrap credentials and signing keys stay outside.
docker run --rm --name "$PILOT_CONTAINER" --network wbengine-admin-api-pilot \
    --env-file "$PILOT_RUNTIME/consumer.env" -e PILOT_CONTROL_DIR=/pilot-control \
    -v "$PILOT_RUNTIME/ca.crt:/pilot-runtime/ca.crt:ro" \
    -v "$PILOT_CONTROL:/pilot-control:rw" \
    -v "$PILOT_ADMIN_DIRECTORY/tests:/app/tests:ro" \
    "$PILOT_IMAGE" node --experimental-strip-types --test tests/api-pilot.ts &
PILOT_RUNNER=$!
PILOT_RESULT=0
PILOT_DEADLINE=$(($(date +%s) + 180))
while kill -0 "$PILOT_RUNNER" 2> /dev/null; do
    if [ "$(date +%s)" -gt "$PILOT_DEADLINE" ]; then
        printf 'Pilot consumer exceeded its three-minute limit.\n' >&2
        PILOT_RESULT=124
        docker rm -f "$PILOT_CONTAINER" > /dev/null 2>&1 || true
        break
    fi
    for PILOT_ACTION in stop-second start-second revoke-second; do
        if [ -f "$PILOT_CONTROL/$PILOT_ACTION.request" ]; then
            rm -f "$PILOT_CONTROL/$PILOT_ACTION.request"
            if "$PILOT_DIRECTORY/pilot.sh" "$PILOT_ACTION" > /dev/null 2>&1; then
                pilot_acknowledge "$PILOT_ACTION" ok
            else
                pilot_acknowledge "$PILOT_ACTION" failed
            fi
        fi
    done
    sleep 0.1
done
if [ "$PILOT_RESULT" -eq 0 ]; then wait "$PILOT_RUNNER" || PILOT_RESULT=$?; else wait "$PILOT_RUNNER" || true; fi
pilot_cleanup
trap - EXIT INT TERM
if [ "$PILOT_CLEANUP_FAILED" = true ] && [ "$PILOT_RESULT" -eq 0 ]; then PILOT_RESULT=1; fi
exit "$PILOT_RESULT"

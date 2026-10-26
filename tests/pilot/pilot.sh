#!/bin/sh
# Local test fixtures only. No host ports, production credentials or TLS bypass.
set -eu
PILOT_DIRECTORY=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PILOT_RUNTIME="$PILOT_DIRECTORY/runtime"
PILOT_ENV="$PILOT_RUNTIME/bootstrap.env"
PILOT_PROJECT=wbengine-admin-api-pilot
pilot_compose() {
    PILOT_UID=$(id -u)
    PILOT_GID=$(id -g)
    export PILOT_UID PILOT_GID
    docker compose --project-name "$PILOT_PROJECT" --env-file "$PILOT_ENV" --file "$PILOT_DIRECTORY/compose.yaml" "$@"
}
case "${1:-status}" in
    start)
        umask 077
        mkdir -p "$PILOT_RUNTIME/app/Config" "$PILOT_RUNTIME/app/View/Compiled" "$PILOT_RUNTIME/images" "$PILOT_RUNTIME/cache" "$PILOT_RUNTIME/app2/Config" "$PILOT_RUNTIME/app2/View/Compiled" "$PILOT_RUNTIME/images2" "$PILOT_RUNTIME/cache2" "$PILOT_RUNTIME/tls"
        # Consumers run as node: public CA can be traversed, private files remain 0600.
        chmod 0711 "$PILOT_RUNTIME"
        if [ ! -f "$PILOT_ENV" ]; then
            {
                printf 'PILOT_DB_ROOT_PASSWORD=%s\n' "$(openssl rand -hex 32)"
                printf 'PILOT_DB_API_PASSWORD=%s\n' "$(openssl rand -hex 32)"
                printf 'PILOT_DB_ADMIN_PASSWORD=%s\n' "$(openssl rand -hex 32)"
                printf 'PILOT_MASTER_KEY=%s\n' "$(openssl rand -hex 32)"
            } > "$PILOT_ENV"
        fi
        if ! grep -q '^PILOT_DB_SECOND_PASSWORD=' "$PILOT_ENV"; then
            printf 'PILOT_DB_SECOND_PASSWORD=%s\n' "$(openssl rand -hex 32)" >> "$PILOT_ENV"
        fi
        PILOT_CA_RENEWED=false
        if [ ! -f "$PILOT_RUNTIME/ca.crt" ] || ! openssl x509 -checkend 3600 -noout -in "$PILOT_RUNTIME/ca.crt" > /dev/null 2>&1; then
            openssl req -x509 -newkey rsa:2048 -nodes -sha256 -days 7 -subj '/CN=WBengine isolated API pilot CA' -addext 'basicConstraints=critical,CA:TRUE' -addext 'keyUsage=critical,keyCertSign,cRLSign' -keyout "$PILOT_RUNTIME/ca.key" -out "$PILOT_RUNTIME/ca.crt" > "$PILOT_RUNTIME/certificate.log" 2>&1
            PILOT_CA_RENEWED=true
        fi
        if [ "$PILOT_CA_RENEWED" = true ] || [ ! -f "$PILOT_RUNTIME/tls/server.crt" ] || ! openssl x509 -checkend 3600 -noout -in "$PILOT_RUNTIME/tls/server.crt" > /dev/null 2>&1 || ! openssl x509 -checkhost api2.wbengine.test -noout -in "$PILOT_RUNTIME/tls/server.crt" > /dev/null 2>&1; then
            openssl req -newkey rsa:2048 -nodes -sha256 -subj '/CN=api.wbengine.test' -keyout "$PILOT_RUNTIME/tls/server.key" -out "$PILOT_RUNTIME/server.csr" >> "$PILOT_RUNTIME/certificate.log" 2>&1
            openssl x509 -req -sha256 -days 7 -in "$PILOT_RUNTIME/server.csr" -CA "$PILOT_RUNTIME/ca.crt" -CAkey "$PILOT_RUNTIME/ca.key" -CAcreateserial -extfile "$PILOT_DIRECTORY/server.ext" -out "$PILOT_RUNTIME/tls/server.crt" >> "$PILOT_RUNTIME/certificate.log" 2>&1
            rm -f "$PILOT_RUNTIME/server.csr" "$PILOT_RUNTIME/ca.srl"
            chmod 0644 "$PILOT_RUNTIME/ca.crt" "$PILOT_RUNTIME/tls/server.crt"
        fi
        pilot_compose up -d --pull never db php php2 api
        PILOT_API_CONTAINER=$(pilot_compose ps -q api)
        docker inspect --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' "$PILOT_API_CONTAINER" > "$PILOT_RUNTIME/proxy-ip.txt"
        pilot_compose restart api
        pilot_compose run --rm --no-deps seed
        printf 'Pilot ready. Network: %s\nConsumer env: %s\n' "$PILOT_PROJECT" "$PILOT_RUNTIME/consumer.env"
        ;;
    probe)
        if [ ! -f "$PILOT_RUNTIME/consumer.env" ]; then printf 'Start the pilot first.\n' >&2; exit 1; fi
        docker run --rm --network "$PILOT_PROJECT" --env-file "$PILOT_RUNTIME/consumer.env" -v "$PILOT_DIRECTORY/probe.mjs:/pilot/probe.mjs:ro" -v "$PILOT_RUNTIME/ca.crt:/pilot-runtime/ca.crt:ro" node:22-alpine node --test /pilot/probe.mjs
        ;;
    revoke-second)
        pilot_compose run --rm --no-deps seed php tests/pilot/control.php revoke-second
        ;;
    refresh-second)
        pilot_compose run --rm --no-deps seed php tests/pilot/control.php refresh-second
        ;;
    stop-second)
        pilot_compose stop php2
        ;;
    start-second)
        pilot_compose start php2
        pilot_compose exec -T php2 php -r 'for ($n = 0; $n < 50; $n++) { $socket = @fsockopen("127.0.0.1", 8080); if (is_resource($socket)) { fclose($socket); exit(0); } usleep(100000); } exit(1);'
        ;;
    backup-check)
        "$PILOT_DIRECTORY/backup-check.sh"
        ;;
    consumer)
        "$PILOT_DIRECTORY/run-consumer.sh" "${2:?Pass the admin checkout directory}"
        ;;
    status)
        if [ -f "$PILOT_ENV" ]; then pilot_compose ps; else printf 'Pilot is not initialized.\n'; fi
        ;;
    stop)
        if [ -f "$PILOT_ENV" ]; then pilot_compose down --remove-orphans; fi
        ;;
    reset)
        if [ -f "$PILOT_ENV" ]; then pilot_compose down --volumes --remove-orphans; fi
        rm -rf "$PILOT_RUNTIME"
        ;;
    *) printf 'Usage: %s start|probe|backup-check|consumer ADMIN_DIR|revoke-second|refresh-second|stop-second|start-second|status|stop|reset\n' "$0" >&2; exit 2 ;;
esac

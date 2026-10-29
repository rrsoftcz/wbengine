# Isolated shared WBengine 4.0 pilot

A test provider of two independent WBengine installations and the MySQL database of the central administration. Only its own Docker project/network wbengine-admin-api-pilot, no host ports. No production hosts or databases. Browser forms are verified by a Node HTTP/SSR client; this is not graphical Chromium automation.

## Preparation

You need Docker Compose, openssl, python3, a trusted checkout of the administration and a prepared core vendor. Commands from the framework root:

```sh
docker build -t wbengine-modernization-php:latest docker
docker run --rm -v "$PWD:/app" -w /app wbengine-modernization-php:latest composer install --no-dev --no-interaction --prefer-dist
docker build -t wbengine-admin-foundation-check:local /path/to/wbengine-admin
docker pull mysql:8.4
docker pull nginx:stable-alpine
docker pull node:22-alpine
sh tests/pilot/pilot.sh start
```

If you are developing the core, keep your dev vendor and do not run the no-dev installation here over a test suite in use. CI has its own checkout. Start does not run general migrations or an arbitrary SQL file: the seed uses fixed test databases and a checked list of its own schemas. API and API2 have their own users, keys, configuration, cache and storage. Nginx distinguishes two fixed TLS hostnames and does not build a path from an untrusted Host header.

## Verification

```sh
sh tests/pilot/pilot.sh probe
sh tests/pilot/pilot.sh consumer /path/to/wbengine-admin
sh tests/pilot/pilot.sh backup-check
```

Probe verifies real HTTPS and public render, separate changes and the rejection of another site's key, as well as the rejection of a private CA without explicit trust. Consumer runs the administration tests in its production Node 24 image: login/MFA, real forms, draft/translation/preview/publish, revisions, concurrent 412/manual merge, structure recovery, images, two sites and a restricted editor, outage/manual retry, a revoked key and public sites after the central administration is stopped.

Outage and revocation are controlled by the final local test protocol revoke-second/stop-second/start-second. Consumer has no Docker socket, no public control endpoint and no way to pass a shell command. After the test, the wrapper restores the second service and restores its key for the next run. Changes submitted during an outage must not go through by themselves after the site returns.

Backup-check uses a real single-transaction dump of only its own API schema, restores the fixed helper wbengine_api_pilot_restore and compares the database defaults, table definitions and deterministic SHA256 hashes of all rows. In finally it removes the helper DB and the dump. It does not modify the source DB. Backup of file media and keys is a separate operational procedure in book-wbengine-4-upgrade.md; this DB test does not simulate it.

## Secrets and cleanup

The runtime is ignored by Git. The bootstrap, integration keys and the private CA have 0600; the Linux seed uses the host UID/GID so that the host Docker client can read the private env files. Consumer receives env through Docker and a mount of only the public CA, never of the bootstrap or the private CA key. Do not send the runtime to Git, screenshots or logs.

```sh
sh tests/pilot/pilot.sh status
sh tests/pilot/pilot.sh stop
sh tests/pilot/pilot.sh reset
```

Stop stops only its own project, reset additionally removes its DB volume and the generated runtime. Do not use a general Docker prune or stopping foreign containers.

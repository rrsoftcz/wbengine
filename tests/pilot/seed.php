<?php
/** Explicit CLI setup of two fixed test installations, never a production upgrade runner. */
require __DIR__ . '/support.php';
ini_set('display_errors', '0');
try {
    $root = new mysqli('db', 'root', pilotSecret('PILOT_DB_ROOT_PASSWORD'));
    // Every identifier is a fixed fixture name; passwords are generated strict hex.
    foreach ([['wbengine_admin_pilot', 'pilot_admin', 'PILOT_DB_ADMIN_PASSWORD'], ['wbengine_api_pilot_second', 'pilot_api_second', 'PILOT_DB_SECOND_PASSWORD']] as [$database, $user, $secret]) {
        $password = pilotSecret($secret);
        $root->query('CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $root->query("CREATE USER IF NOT EXISTS '" . $user . "'@'%' IDENTIFIED BY '" . $password . "'");
        $root->query('GRANT ALL PRIVILEGES ON `' . $database . "`.* TO '" . $user . "'@'%'");
    }
    $root->close();
    foreach (['first', 'second'] as $installation) {
        $process = proc_open([PHP_BINARY, __DIR__ . '/seed-installation.php', $installation], [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
        if (!is_resource($process) || proc_close($process) !== 0) { throw new RuntimeException('Pilot child initialization failed.'); }
    }
    pilotConsumerEnvironment();
    echo "Both isolated pilot installations and the private consumer environment are ready.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Pilot initialization failed (' . get_class($error) . "). No credentials were logged.\n");
    exit(1);
}

<?php
/** Finite CLI-only controls. No HTTP entry point and no caller-supplied path/command. */
require __DIR__ . '/support.php';
use Wbengine\Application\Path\File;
use Wbengine\Api\V2\Model;
use Wbengine\Config;
use Wbengine\Db;
try {
    $action = $argv[1] ?? '';
    if (!in_array($action, ['revoke-second', 'refresh-second'], true)) { throw new InvalidArgumentException('Unknown pilot control.'); }
    $installation = pilotInstallation('second');
    Config::load(new File($installation['app'] . '/Config/Settings.json'));
    if (Db::fetchAssoc('SELECT DATABASE() AS name')['name'] !== $installation['database']) { throw new RuntimeException('Pilot database guard rejected the target.'); }
    $saved = json_decode(file_get_contents($installation['seed']), true, 64, JSON_THROW_ON_ERROR);
    $model = new Model();
    if ('revoke-second' === $action) { $model->revoke(1, $saved['integration_id']); }
    elseif (null === $model->authenticate($saved['key'])) {
        $saved = $model->provision(1, 'Isolated central administration second pilot', Model::SCOPES);
        pilotWrite($installation['seed'], json_encode($saved, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    }
    pilotConsumerEnvironment();
    echo "Pilot control completed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Pilot control failed (' . get_class($error) . "). No credentials were logged.\n");
    exit(1);
}

<?php

// Independent CLI request: the file handler supplies a real inter-process lock.
require dirname(__DIR__) . '/bootstrap.php';

ini_set('session.save_handler', 'files');
session_save_path($argv[1]);
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$_SERVER['HTTP_USER_AGENT'] = 'SessionWorker';
if ($argv[2] !== '') {
    $_COOKIE[session_name()] = $argv[2];
    session_id($argv[2]);
}
$started = hrtime(true);
$session = new Wbengine\Session();
$session->setAutoClean(false);
// STDOUT signals are IPC, not HTTP output. Keep headers available for rotation.
$reply = static function ($value): void {
    fwrite(STDOUT, json_encode($value, JSON_THROW_ON_ERROR) . "\n");
    fflush(STDOUT);
};
$reply(['id' => $session->getSessionId(), 'waitMs' => (hrtime(true) - $started) / 1e6]);
while (($line = fgets(STDIN)) !== false) {
    $command = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    switch ($command['op']) {
        case 'set':
            $session->setValue($command['key'], $command['value']);
            break;
        case 'read':
            $reply($session->getValue($command['key']));
            continue 2;
        case 'close':
            $session->close();
            break;
        case 'mutateSnapshot':
            $session->getSessionData()->first = 999;
            $session->flush();
            break;
        case 'logout':
            $session->destroy();
            break;
        case 'exit':
            $reply(true);
            exit(0);
    }
    $reply(true);
}

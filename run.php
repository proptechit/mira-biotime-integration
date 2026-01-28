<?php
$config = require __DIR__ . '/config/config.php';

date_default_timezone_set($config['timezone']);

require_once __DIR__ . '/src/BiotimeClient.php';
require_once __DIR__ . '/src/BitrixClient.php';
require_once __DIR__ . '/src/PunchProcessor.php';

$currentTime = date('H:i');
$dayOfWeek = date('w'); // 0 (Sunday) to 6 (Saturday)
$dayName = date('l');   // Full day name

$valid = false;

if ($dayOfWeek == 6) {
    // Saturday: 10:30 to 16:30
    $valid = ($currentTime >= '10:30' && $currentTime <= '16:30');
} elseif ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
    // Monday to Friday: 09:30 to 19:30
    $valid = ($currentTime >= '09:30' && $currentTime <= '19:30');
} else {
    // Sunday (0) — no processing
    require_once __DIR__ . '/helpers/Logger.php';
    Logger::log("Skipped processing — $dayName is a non-working day.");
    exit;
}

if (!$valid) {
    require_once __DIR__ . '/helpers/Logger.php';
    Logger::log("Skipped processing — current time $currentTime is outside allowed window for $dayName. Expected: 09:30 to 19:30.");
    exit;
}

$biotimeClient = new BiotimeClient($config['biotime']);
$bitrixClient = new BitrixClient($config['bitrix']);
$processor = new PunchProcessor($biotimeClient, $bitrixClient, $config);

$processor->process();

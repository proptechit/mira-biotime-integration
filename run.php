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

if ($dayOfWeek >= 1 && $dayOfWeek <= 6) {
    // Monday to Saturday: 05:00 to 12:00
    $valid = ($currentTime >= '05:00' && $currentTime <= '22:00');
} else {
    // Sunday (0) — no processing
    require_once __DIR__ . '/helpers/Logger.php';
    Logger::log("Skipped processing — $dayName is a non-working day.");
    exit;
}

if (!$valid) {
    require_once __DIR__ . '/helpers/Logger.php';
    Logger::log("Skipped processing — current time $currentTime is outside allowed window for $dayName. Expected: 05:00 to 22:00.");
    exit;
}

$biotimeClient = new BiotimeClient($config['biotime']);
$bitrixClient = new BitrixClient($config['bitrix']);
$processor = new PunchProcessor($biotimeClient, $bitrixClient, $config);

$processor->process();

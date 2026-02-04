<?php

$jsonFile = __DIR__ . '/../storage/user_map.json';

if (!file_exists($jsonFile)) {
    throw new Exception('user_map.json not found');
}

$userMap = json_decode(file_get_contents($jsonFile), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception('Invalid JSON in user_map.json');
}

return [
    'biotime' => [
        'api_token' => 'd7fd11e694764fb29af32c3a4032dcfb',
        'transactions_url' => 'http://65.108.192.26:8204/api_gettransctions',
        // production
        'start_time' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
        'end_time' => date('Y-m-d H:i:s'),
        // testing
        // 'start_time' => '2026-01-27 00:00:00',
        // 'end_time' => '2026-01-27 23:59:59',
    ],
    'bitrix' => [
        'user_map' => $userMap,
        'biotime_transactions_entity_type_id' => 1060,
    ],
    'timezone' => 'Asia/Dubai',
    'log_timezone' => 'Asia/Kolkata',
];

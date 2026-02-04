<?php
require_once __DIR__ . '/../crest/crest.php';
require_once __DIR__ . '/../helpers/Logger.php';

class BitrixClient
{
    private $map;
    private $transactionFields;
    private $transactionEntityType;

    public function __construct($config)
    {
        $this->map = $config['user_map'];
        $this->transactionEntityType = $config['biotime_transactions_entity_type_id'];
    }

    public function clockInOut($badgeNumber, $type)
    {
        if (!isset($this->map[$badgeNumber])) {
            Logger::log("BioTime badge number $badgeNumber is invalid — not found in map.");
            return false;
        }

        if (empty($this->map[(int)$badgeNumber])) {
            Logger::log("BioTime badge number $badgeNumber is valid but not mapped to a Bitrix user.");
            return false;
        }

        $bitrixUserId = (int)$this->map[(int)$badgeNumber];

        if ($bitrixUserId <= 0) {
            Logger::log("BioTime badge number $badgeNumber is mapped to a non-Bitrix user.");
            return false;
        }

        $statusResponse = CRest::call('timeman.status', ['USER_ID' => $bitrixUserId]);

        if (isset($statusResponse['error'])) {
            Logger::log("Error fetching status for user $bitrixUserId: " . $statusResponse['error']);
            return false;
        }

        $status = $statusResponse['result']['STATUS'] ?? null;

        if ($type === 'IN' && $status === 'OPENED') {
            Logger::log("Skipping clock-in for $bitrixUserId: already clocked in.");
            return false;
        }

        if ($type === 'OUT' && $status === 'CLOSED') {
            Logger::log("Skipping clock-out for $bitrixUserId: already clocked out.");
            return false;
        }

        $method = ($type === 'IN' ? 'timeman.open' : 'timeman.close');
        $params = ['USER_ID' => $bitrixUserId];
        $response = CRest::call($method, $params);

        if (isset($response['error'])) {
            Logger::log("Error in Bitrix API for user $bitrixUserId: " . $response['error']);
            return false;
        }

        Logger::log("Successfully clocked " . ($type === 'IN' ? 'in' : 'out') . " user $bitrixUserId");
        return $response['result'];
    }

    public function addTransaction($punch, $type)
    {
        $badgeNumber = $punch['BadgeNumber'];

        // if (!isset($this->map[$badgeNumber])) {
        //     Logger::log("BioTime badge number $badgeNumber is invalid — not found in map.");
        //     return false;
        // }

        // if (empty($this->map[$badgeNumber]['bitrix_id'])) {
        //     Logger::log("BioTime badge number $badgeNumber is valid but not mapped to a Bitrix user.");
        //     return false;
        // }

        $punchDate = date('Y-m-d', strtotime($punch['VerifyTime']));

        $filter = [
            'filter' => [
                'ufCrm9BadgeNumber' => $badgeNumber,
                'ufCrm9PunchType' => $type,
                '>=ufCrm9VerifyTime' => $punchDate . 'T00:00:00',
                '<=ufCrm9VerifyTime' => $punchDate . 'T23:59:59',
            ],
            'entityTypeId' => $this->transactionEntityType,
            'select' => ['id'],
        ];

        $existing = CRest::call('crm.item.list', $filter);

        if (!empty($existing['result']['items'])) {
            Logger::log("Skipping duplicate $type transaction for $badgeNumber on $punchDate.");
            return false;
        }

        $fields = [
            'ufCrm9EmployeeName' => ucwords(strtolower($punch['EmployeeName'])) ?? '',
            'ufCrm9EmployeeDepartment' => ucwords(strtolower($punch['DepartmentName'])) ?? '',
            'ufCrm9BadgeNumber' => $punch['BadgeNumber'] ?? '',
            'ufCrm9VerifyTime' => $punch['VerifyTime'] ?? '',
            'ufCrm9VerifyType' => $punch['VerifyType'] ?? '',
            'ufCrm9PunchType' => $type,
            'ufCrm9DeviceSerialNumber' => $punch['DeviceSerialNumber'] ?? '',
            'ufCrm9DeviceAliasName' => ucwords(strtolower($punch['DeviceAliasName'])) ?? '',
        ];

        $params = [
            'entityTypeId' => $this->transactionEntityType,
            'fields' => $fields,
        ];

        $response = CRest::call('crm.item.add', $params);

        if (isset($response['error'])) {
            Logger::log("Error saving transaction to Bitrix: " . $response['error']);
            return false;
        }

        Logger::log("Transaction saved in Bitrix with ID: " . $response['result']['item']['id']);
        return $response['result'];
    }
}

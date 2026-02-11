<?php
require_once __DIR__ . '/../helpers/Logger.php';

class PunchProcessor
{
    private $map;
    private $biotime;
    private $bitrix;
    private $processedPunchesFile;

    public function __construct($biotimeClient, $bitrixClient, $config)
    {
        $this->map = $config['bitrix']['user_map'];
        $this->biotime = $biotimeClient;
        $this->bitrix = $bitrixClient;
        $this->processedPunchesFile = __DIR__ . '/../storage/processed_punches.json';

        if (!file_exists($this->processedPunchesFile)) {
            file_put_contents($this->processedPunchesFile, json_encode([]));
        }
    }

    private function getProcessedPunchIds()
    {
        $data = json_decode(file_get_contents($this->processedPunchesFile), true);
        return is_array($data) ? $data : [];
    }

    private function saveProcessedPunchIds($ids)
    {
        file_put_contents($this->processedPunchesFile, json_encode($ids, JSON_PRETTY_PRINT));
    }

    public function process()
    {
        try {
            $punches = $this->biotime->fetchNewPunches();
            $processedIds = $this->getProcessedPunchIds();
            $grouped = [];

            foreach ($punches as $punch) {
                $badgeNumber = $punch['BadgeNumber'] ?? null;
                $verifyTime = $punch['VerifyTime'] ?? null;
                $verifyDate = date('Y-m-d', strtotime($verifyTime));
                $id = $punch['Id'] ?? null;

                if (!$badgeNumber || !$id || in_array($id, $processedIds)) {
                    continue;
                }

                $grouped[$badgeNumber][$verifyDate][] = $punch;
            }

            $toProcess = [];

            foreach ($grouped as $badgeNumber => $dates) {
                foreach ($dates as $date => $entries) {

                    // Sort punches by time ASC
                    usort($entries, function ($a, $b) {
                        return strtotime($a['VerifyTime']) <=> strtotime($b['VerifyTime']);
                    });

                    // Get last state from Bitrix
                    $lastType = $this->bitrix->getLastTransactionType($badgeNumber);

                    foreach ($entries as $entry) {
                        if (!$lastType || $lastType === 'OUT') {
                            $currentType = 'IN';
                        } else {
                            $currentType = 'OUT';
                        }

                        $entry['PunchType'] = $currentType;
                        $toProcess[] = $entry;

                        $lastType = $currentType;
                    }
                }
            }

            foreach ($toProcess as $punch) {
                $id = $punch['Id'];
                $badgeNumber = $punch['BadgeNumber'];
                $type = $punch['PunchType'];
                $time = $punch['VerifyTime'] ?? 'N/A';

                Logger::log("Processing punch ID $id: badge number: $badgeNumber | Type: $type | Time: $time", true);

                $resultClock = $this->bitrix->clockInOut($badgeNumber, $type);
                $resultTransaction = $this->bitrix->addTransaction($punch, $type);

                Logger::log("Clock In/Out Response: " . json_encode($resultClock));
                Logger::log("Transaction Save Response: " . json_encode($resultTransaction));

                $processedIds[] = $id;
            }

            $this->saveProcessedPunchIds($processedIds);
        } catch (Exception $e) {
            Logger::log("Error while processing punches: " . $e->getMessage());
        }
    }
}

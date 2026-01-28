<?php
class Logger
{
    /**
     * Logs a message to a daily log file, creating directories as needed.
     *
     * @param string $message The message to log.
     * @param bool $newline Whether to prepend a newline to the message.
     */

    /*******  a6b3e3b1-42b7-4fe4-8f61-3c77136a2569  *******/
    public static function log($message, $newline = false)
    {
        $config = require __DIR__ . '/../config/config.php';

        $date = new DateTime('now', new DateTimeZone($config['log_timezone']));
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');

        $logDir = __DIR__ . "/../logs/$year/$month";
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
            $newline = false;
        }

        $filePath = "$logDir/$day.log";
        $timestamp = $date->format('Y-m-d H:i:s');
        file_put_contents($filePath, ($newline ? "\n" : '') . "[$timestamp] $message\n", FILE_APPEND);
    }
}

<?php

namespace Framework;

class Logger
{
    private static function write($level, $message)
    {
        $logDir = BASE_PATH . '/storage/logs';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $file = $logDir . '/app.log';

        $time = date('Y-m-d H:i:s');

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

        $line = "[{$time}] [{$level}] [{$ip}] {$message}" . PHP_EOL;

        file_put_contents($file, $line, FILE_APPEND);
    }

    public static function info($message)
    {
        self::write('INFO', $message);
    }

    public static function warning($message)
    {
        self::write('WARNING', $message);
    }

    public static function error($message)
    {
        self::write('ERROR', $message);
    }

    public static function debug($message)
    {
        self::write('DEBUG', $message);
    }
}

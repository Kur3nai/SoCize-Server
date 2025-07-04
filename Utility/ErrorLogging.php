<?php
error_reporting(0);
date_default_timezone_set('Asia/Kuala_Lumpur');

/**
 * 
 * @param string $error the error message to be logged
 * 
 */

function log_error(string $message): void {
    $logMessage = date('[Y-m-d H:i:s]') . " ERROR: " . $message . PHP_EOL;
    file_put_contents(__DIR__ . '../logs/ErrorLogs.txt', $logMessage, FILE_APPEND);
}
?>
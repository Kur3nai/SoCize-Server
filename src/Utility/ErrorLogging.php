<?php
error_reporting(0);
date_default_timezone_set('Asia/Kuala_Lumpur');

/**
 * 
 * @param string $error the error message to be logged
 * 
 */

function log_error(string $error) : void{
    $path = "../Log/ErrorLogs.txt";

    $error_message = date('Y-m-d H:i:s') . " -> " . $error . "\n";
    file_put_contents($path, $error_message, FILE_APPEND);
}
?>
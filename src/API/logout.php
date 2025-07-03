<?php declare(strict_types=1);

error_reporting(0);

try {
    require_once "../Utility/ErrorLogging.php";
    require_once "../Utility/SessionHelper.php";
    require_once "../Utility/ResponseHelper.php";
} catch (Error $e) {
    if (function_exists('log_error')) {
        log_error("Initialization failed: " . $e->getMessage());
    }
}

/**
 * Completely destroys the specified session
 */
function delete_session(string $sessionId): void {
    session_id($sessionId);
    
    session_start();
    
    $_SESSION = [];
    
    session_destroy();
    
    session_write_close();
}

/**
 * Main logout handler
 */
function handle_logout(): void {
    try {
        $requiredKeys = ['sessionId']
        fetch_json_data($requiredKeys)

        if (!isset($input['sessionId'])) {
            exit; 
        }
        
        delete_session($input['sessionId']);
    } catch (Exception $e) {
        log_error("Logout error: " . $e->getMessage());
    }
}

handle_logout();

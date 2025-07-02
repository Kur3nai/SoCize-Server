<?php declare(strict_types=1);

class FileDownloadResponse {
    public bool $success;
    public ?string $errorMessage;

    public function __construct(bool $success, ?string $errorMessage) {
        $this->success = $success;
        $this->errorMessage = $errorMessage;
    }

    public static function createErrorResponse(string $errorMessage) {
        return new FileDownloadResponse(false, $errorMessage);
    }
}

error_reporting(0);

try {
    require_once "../Utility/ErrorLogging.php";
    require_once "../Utility/ResponseHelper.php";
    require_once "../Utility/SessionHelper.php";
    require_once "../Config/DatabaseConfig.php";
} catch (Throwable $e) {
    if (function_exists('log_error')) {
        log_error("Initialization failed: " . $e->getMessage());
    }
    exit(json_encode(FileDownloadResponse::createErrorResponse("Initialization failed")));
}

/**
 * Verifies user session and returns session data if valid
 */
function verify_session(string $sessionId): ?array {
    session_id($sessionId);
    session_start();

    if (!check_login_status()) {
        return null;
    }

    return [
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'] ?? null,
        'csrf_token' => $_SESSION['csrf_token'] ?? null
    ];
}

/**
 * Gets the actual file path from database after verifying ownership
 */
function get_file_path_with_ownership(mysqli $conn, string $username, string $userFilename): ?string {
    $stmt = null;
    try {
        $stmt = mysqli_prepare($conn, "CALL get_file_path(?, ?)");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }

        mysqli_stmt_bind_param($stmt, "ss", $username, $userFilename);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['file_directory']; 
        }
        
        return null;
    } catch (mysqli_sql_exception $e) {
        log_error("Database error: " . $e->getMessage());
        return null;
    } finally {
        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
    }
}

/**
 * Sends file to client with required headers
 */
function send_file_to_client(string $filePath, string $userFilename) {
    if (!file_exists($filePath)) {
        send_api_response(FileDownloadResponse::createErrorResponse("File not found on server"));
        return false;
    }

    header("Content-Type: application/octet-stream");
    header("Content-Length: " . filesize($filePath));
    header("Content-Disposition: attachment; filename=\"" . basename($userFilename) . "\"");
    
    readfile($filePath);
    return true;
}

function Main($db_credentials) {
    $conn = null;
    try {
        if (!verifyPostMethod()) {
            send_api_response(FileDownloadResponse::createErrorResponse("Invalid request method"));
            return;
        }

        $requiredFields = ['sessionId', 'filename'];
        $input = fetch_json_data($requiredFields);

        $session = verify_session($input['sessionId']);
        if (!$session || !isset($session['username'])) {
            send_api_response(FileDownloadResponse::createErrorResponse("Invalid session"));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(FileDownloadResponse::createErrorResponse("Database connection failed"));
            return;
        }

        $fileInfo = get_file_path_with_ownership($conn, $session['username'], $input['filename']);
        if ($fileInfo === null) {
            send_api_response(FileDownloadResponse::createErrorResponse("File not found or access denied"));
            return;
        }

        $fullPath = __DIR__ . '/..' . $fileInfo['file_directory'];
        $userFilename = $fileInfo['user_filename']; 

        if (!send_file_to_client($fullPath, $userFilename)) {
            return;
        }

        exit;

    } catch (Exception $e) {
        log_error("Application error: " . $e->getMessage());
        send_api_response(FileDownloadResponse::createErrorResponse("An error occurred"));
    } finally {
        if ($conn) {
            mysqli_close($conn);
        }
    }
}

Main($db_credentials);
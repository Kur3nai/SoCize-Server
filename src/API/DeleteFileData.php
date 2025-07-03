<?php declare(strict_types=1);

class FileDeleteResponse {
    public bool $success;
    public ?string $errorMessage;

    public function __construct(bool $success, ?string $errorMessage) {
        $this->success = $success;
        $this->errorMessage = $errorMessage;
    }

    public static function createErrorResponse(string $errorMessage) {
        return new FileDeleteResponse(false, $errorMessage);
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
    exit(json_encode(FileDeleteResponse::createErrorResponse("Something went wrong.....")));
}

function verify_session(string $sessionId): ?array {
    session_id($sessionId);
    session_start();

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
        return null;
    }

    return [
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'], 
    ];
}

function Main($db_credentials) {
    $conn = null;
    $stmt = null;
    $result = null;
    
    try {
        if (!verifyPostMethod()) {
            send_api_response(new FileDeleteResponse(false, "Invalid request method"));
            return;
        }

        $requiredFields = ['sessionId', 'filename'];
        $input = fetch_json_data($requiredFields);

        $session = verify_session($input['sessionId']);
        if (!$session || $session['role'] !== 'customer') {
            send_api_response(new FileDeleteResponse(false, "Invalid session or insufficient privileges"));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(new FileDeleteResponse(false, "Database connection failed"));
            return;
        }

        $stmt = mysqli_prepare($conn, "CALL get_verified_file_path(?, ?)");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }

        mysqli_stmt_bind_param($stmt, "ss", $session['username'], $input['filename']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        if (empty($row['filepath'])) {
            send_api_response(new FileDeleteResponse(false, "File not found or access denied"));
            return;
        }

        $fullPath = __DIR__ . '/../' . $row['filepath'];
        
        $deleteStmt = mysqli_prepare($conn, "CALL delete_file_record(?, ?)");
        if (!$deleteStmt) {
            throw new Exception("Failed to prepare delete statement");
        }

        mysqli_stmt_bind_param($deleteStmt, "ss", $session['username'], $input['filename']);
        $deleteSuccess = mysqli_stmt_execute($deleteStmt);
        
        if (!$deleteSuccess) {
            send_api_response(new FileDeleteResponse(false, "Failed to delete file record"));
            return;
        }

        if (file_exists($fullPath)) {
            if (!unlink($fullPath)) {
                log_error("Failed to delete physical file: " . $fullPath);
            }
        }

        send_api_response(new FileDeleteResponse(true, null));

    } catch (Exception $e) {
        log_error("Application error: " . $e->getMessage());
        send_api_response(new FileDeleteResponse(false, "An error occurred"));
    } finally {
        if (isset($result)) {
            mysqli_free_result($result);
        }
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }
        if (isset($deleteStmt)) {
            mysqli_stmt_close($deleteStmt);
        }
        if (isset($conn)) {
            mysqli_close($conn);
        }
    }
}

Main($db_credentials);
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
    exit(json_encode(FileDownloadResponse::createErrorResponse("Something Went Wrong....")));
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

        if(!mysqli_stmt_bind_param($stmt, "ss", $username, $userFilename)) {
            throw new Exception("Failed to bind parameter for prepared statement.");
        }

        if(!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to execute database query");
        }

        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['file_directory']; 
        }
        
        return null;
    } catch (mysqli_sql_exception $e) {
        log_error("Database error: " . $e->getMessage());
        throw $e;
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

        $session = verify_customer_session($input['sessionId']);
        if (!$session || !isset($session['username'])) {
            send_api_response(FileDownloadResponse::createErrorResponse("User is not logged in.."));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(FileDownloadResponse::createErrorResponse("Something Went Wrong..."));
            return;
        }

        $fileInfo = get_file_path_with_ownership($conn, $session['username'], $input['filename']);
        if ($fileInfo === null) {
            send_api_response(FileDownloadResponse::createErrorResponse("File not found or access denied"));
            return;
        }

        $fullPath = __DIR__ . '/../' . $fileInfo;
        $userFilename = $fileInfo; 

        if (!send_file_to_client($fullPath, $userFilename)) {
            return;
        }

        exit;

    } catch (Exception $e) {
        log_error("Application error: " . $e->getMessage());
        send_api_response(FileDownloadResponse::createErrorResponse("Something Went wrong.."));
    } finally {
        if ($conn) {
            mysqli_close($conn);
        }
    }
}

Main($db_credentials);
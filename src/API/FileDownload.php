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
    exit(json_encode(FileDownloadResponse::createErrorResponse("Initialization failed")));
}

/**
 * @param This function will verify whether the user is the owner of the file and return true or false
 */
function get_file_path_with_ownership(mysqli $conn, string $username, string $filename): ?string {
    try {
        $stmt = mysqli_prepare($conn, "CALL get_file_path(?, ?)");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }

        mysqli_stmt_bind_param($stmt, "ss", $username, $filename);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $path = $row['file_directory'] . $row['filename'];
            mysqli_stmt_close($stmt);
            return $path;
        }
        
        mysqli_stmt_close($stmt);
        return null;
    } catch (mysqli_sql_exception $e) {
        log_error("Database error: " . $e->getMessage());
        return null;
    }
}

function send_file_to_client(string $filePath) {
    if (!file_exists($filePath)) {
        send_api_response(FileDownloadResponse::createErrorResponse("File not found on server"));
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);

    header("Content-Type: " . $mimeType);
    header("Content-Length: " . filesize($filePath));
    header("Content-Disposition: attachment; filename=\"" . basename($filePath) . "\"");
    
    readfile($filePath);
    return true;
}

function Main($db_credentials) {
    try {
        if (!verifyPostMethod()) {
            send_api_response(FileDownloadResponse::createErrorResponse("Something went wrong...."));
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['sessionId']) || !isset($input['filename'])) {
            send_api_response(FileDownloadResponse::createErrorResponse("Something went wrong...."));
            return;
        }

        $sessionId = $input['sessionId'];
        $filename = $input['filename'];

        $session = verify_session($sessionId);
        if (!$session || !isset($session['username'])) {
            send_api_response(FileDownloadResponse::createErrorResponse("User not logged in..."));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(FileDownloadResponse::createErrorResponse("Something went wrong with the server...."));
            return;
        }

        $filePath = get_file_path_with_ownership($conn, $session['username'], $filename);
        if ($filePath === null) {
            send_api_response(FileDownloadResponse::createErrorResponse("File not found or access denied"));
            mysqli_close($conn);
            return;
        }

        mysqli_close($conn);

        if (!send_file_to_client(__DIR__ . '/../' . $filePath)) {
            return;
        }

        exit;

    } catch (Exception $e) {
        if (isset($conn)) {
            mysqli_close($conn);
        }
        log_error("Application error: " . $e->getMessage());
        send_api_response(FileDownloadResponse::createErrorResponse("Something went wrong with the server...."));
    }
}

Main($db_credentials);
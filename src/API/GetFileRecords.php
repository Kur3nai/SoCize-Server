<?php declare(strict_types=1);

class FileDownloadResponse {
    public bool $success;
    public ?string $errorMessage;
    public ?array $files;

    public function __construct(bool $success, ?string $errorMessage, ?array $files = null) {
        $this->success = $success;
        $this->errorMessage = $errorMessage;
        $this->files = $success ? ($files ?? []) : null; // Enforce empty array for success, null for failure
    }

    public static function createErrorResponse(string $errorMessage) {
        return new FileDownloadResponse(false, $errorMessage);
    }
}

class FileInfo {
    public string $filename;
    public string $file_directory;
    public string $upload_time;

    public function __construct(array $fileData) {
        $this->filename = $fileData['filename'];
        $this->file_directory = $fileData['file_directory'];
        $this->upload_time = $fileData['upload_time'];
    }

    public function toDownloadFormat(): array {
        return ['filename' => $this->filename];
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

function get_user_files(mysqli $conn, string $username): array {
    try {
        $stmt = mysqli_prepare($conn, "CALL get_user_files(?)");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }

        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        $files = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $files[] = new FileInfo($row);
        }
        
        mysqli_stmt_close($stmt);
        return $files;
    } catch (mysqli_sql_exception $e) {
        log_error("Database error: " . $e->getMessage());
        throw $e; // Re-throw to be caught in Main()
    }
}

function verify_session(string $sessionId): ?array {
    if (!check_login_status()) {
        return null;
    }

    return [
        'username' => $_SESSION['username'],
        'csrf_token' => $_SESSION['csrf_token']
    ];
}

function verifyPostMethod(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function Main($db_credentials) {
    try {
        if (!verifyPostMethod()) {
            send_api_response(new FileDownloadResponse(false, "Only POST requests allowed"));
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['sessionId'])) {
            send_api_response(new FileDownloadResponse(false, "Missing sessionId"));
            return;
        }

        $sessionId = $input['sessionId'];
        $session = verify_session($sessionId);
        if (!$session || !isset($session['username'])) {
            send_api_response(new FileDownloadResponse(false, "Session has expired, please login or relogin to continue using the service."));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(new FileDownloadResponse(false, "Database connection failed"));
            return;
        }

        try {
            $fileObjects = get_user_files($conn, $session['username']);
            $formattedFiles = array_map(fn($file) => $file->toDownloadFormat(), $fileObjects);
            send_api_response(new FileDownloadResponse(true, null, $formattedFiles));
        } catch (Exception $e) {
            send_api_response(new FileDownloadResponse(false, "Failed to retrieve files"));
        }

        mysqli_close($conn);
    } catch (Exception $e) {
        if (isset($conn)) {
            mysqli_close($conn);
        }
        log_error("Application error: " . $e->getMessage());
        send_api_response(new FileDownloadResponse(false, "Internal server error"));
    }
}

Main($db_credentials);
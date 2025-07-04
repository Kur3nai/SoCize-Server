<?php declare(strict_types=1);

class FileDownloadResponse {
    public bool $success;
    public ?string $errorMessage;
    public ?array $files;

    public function __construct(bool $success, ?string $errorMessage, ?array $files = null) {
        $this->success = $success;
        $this->errorMessage = $errorMessage;
        $this->files = $success ? ($files ?? []) : null; 
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
        return [
            'filename' => $this->filename,
            'upload_time' => $this->upload_time
        ];
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

function get_user_files(mysqli $conn, string $username): array {
    $stmt = null;
    $result = null;
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
        
        return $files;
    } finally {
        if ($result) {
            mysqli_free_result($result);
        }
        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
    }
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
    try {
        if (!verifyPostMethod()) {
            send_api_response(new FileDownloadResponse(false, "Only POST requests allowed"));
            return;
        }

        $requiredFields = ['sessionId'];
        $input = fetch_json_data($requiredFields);

        $session = verify_session($input['sessionId']);
        if (!$session || !isset($session['username'])) {
            send_api_response(new FileDownloadResponse(false, "Session has expired, please login or relogin to continue using the service."));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(new FileDownloadResponse(false, "Database connection failed"));
            return;
        }

        $fileObjects = get_user_files($conn, $session['username']);
        $formattedFiles = array_map(fn($file) => $file->toDownloadFormat(), $fileObjects);
        send_api_response(new FileDownloadResponse(true, null, $formattedFiles));

    } catch (Exception $e) {
        log_error("Application error: " . $e->getMessage());
        send_api_response(new FileDownloadResponse(false, "Internal server error"));
    } finally {
        if ($conn) {
            mysqli_close($conn);
        }
    }
}

Main($db_credentials);
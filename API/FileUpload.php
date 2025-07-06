<?php declare(strict_types=1);

class FileUploadResponse {
    public bool $success;
    public ?string $errorMessage;

    public function __construct(bool $success, ?string $errorMessage) {
        $this->success = $success;
        $this->errorMessage = $errorMessage;
    }

    public static function createErrorResponse(string $errorMessage) {
        return new FileUploadResponse(false, $errorMessage);
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
    exit(json_encode(FileUploadResponse::createErrorResponse("Initialization failed")));
}

function validate_file_upload(array $fileData, string $newFileName): ?string {
    if (!isset($fileData['error'])) {
        return "Invalid file upload";
    }

    switch ($fileData['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "File too large (maximum 50MB allowed)";
        case UPLOAD_ERR_PARTIAL:
            return "File was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "File upload stopped by extension";
        case UPLOAD_ERR_OK:
            break;
        default:
            return "Unknown upload error";
    }

    if ($fileData['size'] > 50 * 1024 * 1024) {
        return "File too large (maximum 50MB allowed)";
    }
    if ($fileData['size'] < 1024) {
        return "File too small (minimum 1KB required)";
    }

    if (empty($newFileName)) {
        return "Filename cannot be empty";
    }
    if (strlen($newFileName) > 255) {
        return "Filename too long (maximum 255 characters)";
    }

    $sanitized = preg_replace("/[^a-zA-Z0-9\.\-_]/", "", basename($newFileName));
    if ($sanitized !== $newFileName) {
        return "Filename contains invalid characters";
    }

    return null;
}

function save_file_record(mysqli $conn, string $filename, string $username, string $directory): bool {
    $stmt = null;
    try {
        $stmt = mysqli_prepare($conn, "CALL add_file_record(?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }

        mysqli_stmt_bind_param($stmt, "sss", $filename, $username, $directory);
        return mysqli_stmt_execute($stmt);
    } finally {
        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
    }
}

function save_uploaded_file(mysqli $conn, string $userFilename, array $fileData, string $username): bool {
    $baseUploadDir = __DIR__ . '/../uploads/';
    
    $userDir = $baseUploadDir . $username . '/';
    
    $relativeDir = 'uploads/' . $username . '/';
    
    if (!file_exists($userDir)) {
        if (!mkdir($userDir, 0755, true)) {
            log_error("Failed to create directory for user: " . $username);
            return false;
        }
    }

    $fileExt = pathinfo($userFilename, PATHINFO_EXTENSION);
    $uniqueFilename = uniqid() . ($fileExt ? '.' . $fileExt : '');
    $filePath = $userDir . $uniqueFilename;

    if (!move_uploaded_file($fileData['tmp_name'], $filePath)) {
        log_error("Failed to move uploaded file for user: " . $username);
        return false;
    }

    return save_file_record($conn, $userFilename, $username, $relativeDir . $uniqueFilename);
}

function Main($db_credentials) {
    $conn = null;
    try {
        if (!verifyPostMethod()) {
            send_api_response(FileUploadResponse::createErrorResponse("Only POST requests allowed"));
            return;
        }

        if (!isset($_POST['sessionId']) || !isset($_POST['newFileName']) || !isset($_FILES['file'])) {
            send_api_response(FileUploadResponse::createErrorResponse("Missing required fields"));
            return;
        }

        $sessionId = $_POST['sessionId'];
        $newFileName = $_POST['newFileName'];
        $fileData = $_FILES['file'];

        $session = verify_customer_session($sessionId);
        if (!$session || !isset($session['username'])) {
            send_api_response(FileUploadResponse::createErrorResponse("Invalid or expired session"));
            return;
        }

        if ($error = validate_file_upload($fileData, $newFileName)) {
            send_api_response(FileUploadResponse::createErrorResponse($error));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            log_error("Database connection failed for user: " . $session['username']);
            send_api_response(FileUploadResponse::createErrorResponse("Something went wrong on the server..."));
            return;
        }

        if (!save_uploaded_file($conn, $newFileName, $fileData, $session['username'])) {
            send_api_response(FileUploadResponse::createErrorResponse("Failed to save file"));
            return;
        }

        send_api_response(new FileUploadResponse(true, null));

    } catch (Exception $e) {
        log_error("Application error: " . $e->getMessage());
        send_api_response(FileUploadResponse::createErrorResponse("Something went wrong on the server..."));
    } finally {
        if ($conn) {
            mysqli_close($conn);
        }
    }
}

Main($db_credentials);

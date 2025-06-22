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
    exit(json_encode(FileUploadResponse::createErrorResponse("Initialization failed")));
}

function validate_file_upload(array $fileData, string $newFileName): ?string {
    if (!isset($fileData['error']) || $fileData['error'] === UPLOAD_ERR_NO_FILE) {
        return "No file was uploaded";
    }

    switch ($fileData['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "File too large (maximum 5MB allowed)";
        case UPLOAD_ERR_PARTIAL:
            return "File was only partially uploaded";
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

    if ($fileData['size'] > 5 * 1024 * 1024) {
        return "File too large (maximum 5MB allowed)";
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

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $fileData['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = [
        'text/plain',
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];
    
    if (!in_array($mime, $allowedMimes)) {
        return "File type not allowed";
    }

    return null;
}

function save_file_record(mysqli $conn, string $filename, string $username, string $directory): bool {
    try {
        $stmt = mysqli_prepare($conn, "CALL add_file_record(?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }

        mysqli_stmt_bind_param($stmt, "sss", $filename, $username, $directory);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $success;
    } catch (mysqli_sql_exception $e) {
        log_error("Database error: " . $e->getMessage());
        return false;
    }
}

function save_uploaded_file(mysqli $conn, string $newFileName, array $fileData, string $username): bool {
    $uploadDir = __DIR__ . '/../uploads/';
    $relativeDir = 'uploads/';
    
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return false;
        }
    }

    $i = 0;
    $parts = pathinfo($newFileName);
    $fileName = $newFileName;
    
    while (file_exists($uploadDir . $fileName)) {
        $i++;
        $fileName = $parts['filename'] . "_" . $i;
        if (isset($parts['extension'])) {
            $fileName .= "." . $parts['extension'];
        }
    }

    if (!move_uploaded_file($fileData['tmp_name'], $uploadDir . $fileName)) {
        return false;
    }

    return save_file_record($conn, $fileName, $username, $relativeDir);
}

function Main($db_credentials) {
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

        $session = verify_session($sessionId);
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
            send_api_response(FileUploadResponse::createErrorResponse("Database connection failed"));
            return;
        }

        if (!save_uploaded_file($conn, $newFileName, $fileData, $session['username'])) {
            send_api_response(FileUploadResponse::createErrorResponse("Failed to save file"));
            mysqli_close($conn);
            return;
        }

        mysqli_close($conn);
        send_api_response(new FileUploadResponse(true, null));

    } catch (Exception $e) {
        if (isset($conn)) {
            mysqli_close($conn);
        }
        log_error("Application error: " . $e->getMessage());
        send_api_response(FileUploadResponse::createErrorResponse("Internal server error"));
    }
}

Main($db_credentials);
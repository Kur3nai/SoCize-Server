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
    exit(json_encode(FileDeleteResponse::createErrorResponse("Something went wrong.....")));
}

/**
 * this function will find both the physical location and filepath and delete based on what is returned from the sql
 * 
 */
function delete_user_file(mysqli $conn, string $username, string $filename): bool {
    try {
        $stmt = mysqli_prepare($conn, "CALL get_file_path(?)");
        if (!$stmt) {
            throw new Exception("Something went wrong...");
        }

        mysqli_stmt_bind_param($stmt, "s", $filename);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $filePath = __DIR__ . '/../' . $row['file_directory'] . $row['filename'];
            
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "CALL delete_file_record(?, ?)");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }

        mysqli_stmt_bind_param($stmt, "ss", $username, $filename);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $success;
    } catch (mysqli_sql_exception $e) {
        log_error("Database error: " . $e->getMessage());
        return false;
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

function Main($db_credentials) {
    try {
        if (!verifyPostMethod()) {
            send_api_response(new FileDeleteResponse(false, "Something went wrong...."));
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['sessionId']) || !isset($input['filename'])) {
            send_api_response(new FileDeleteResponse(false, "Something went wrong...."));
            return;
        }

        $session = verify_session($input['sessionId']);
        if (!$session || !isset($session['username'])) {
            send_api_response(new FileDeleteResponse(false, "Something went wrong......."));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(new FileDeleteResponse(false, "Something went wrong with the server...."));
            return;
        }

        mysqli_query($conn, "SET @filepath = NULL");
        
        $stmt = mysqli_prepare($conn, "CALL get_verified_file_path(?, ?, @filepath)");
        mysqli_stmt_bind_param($stmt, "ss", $session['username'], $input['filename']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $result = mysqli_query($conn, "SELECT @filepath AS filepath");
        $row = mysqli_fetch_assoc($result);
        
        if (empty($row['filepath'])) {
            send_api_response(new FileDeleteResponse(false, "Something went wrong....."));
            mysqli_close($conn);
            return;
        }

        $fullPath = __DIR__ . '/../' . $row['filepath'];
        
        $stmt = mysqli_prepare($conn, "CALL delete_file_record(?, ?)");
        mysqli_stmt_bind_param($stmt, "ss", $session['username'], $input['filename']);
        $deleteSuccess = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if (!$deleteSuccess) {
            send_api_response(new FileDeleteResponse(false, "Something went wrong with the server...."));
            mysqli_close($conn);
            return;
        }

        if (file_exists($fullPath)) {
            if (!unlink($fullPath)) {
                log_error("Failed to delete physical file: " . $fullPath);
            }
        }

        mysqli_close($conn);
        send_api_response(new FileDeleteResponse(true, null));

    } catch (Exception $e) {
        if (isset($conn)) {
            mysqli_close($conn);
        }
        log_error("Application error: " . $e->getMessage());
        send_api_response(new FileDeleteResponse(false, "Something went wrong..."));
    }
}

Main($db_credentials);
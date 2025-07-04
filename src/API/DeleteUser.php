<?php declare(strict_types=1);

class DeleteUserResponse {
    public bool $success;
    public ?string $errorMessage;

    public function __construct(bool $success, ?string $errorMessage) {
        $this->success = $success;
        $this->errorMessage = $errorMessage;
    }

    public static function createErrorResponse(string $errorMessage) {
        return new DeleteUserResponse(false, $errorMessage);
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
    exit(json_encode(DeleteUserResponse::createErrorResponse("Something went wrong....")));
}

function verify_admin_session(string $sessionId): ?array {
    session_id($sessionId);
    session_start();

    if (!check_login_status()) {
        return null;
    }

    if ($_SESSION['role'] !== 'admin') {
        return null;
    }

    return [
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role']
    ];
}

function delete_user_account(mysqli $conn, string $targetUsername): bool {
    $stmt = null;
    try {
        $stmt = mysqli_prepare($conn, "CALL delete_user_account(?)");
        if (!$stmt) {
            throw new Exception("Something went wrong with the server....");
        }

        mysqli_stmt_bind_param($stmt, "s", $targetUsername);
        $success = mysqli_stmt_execute($stmt);
        $affectedRows = mysqli_stmt_affected_rows($stmt);
        
        return $affectedRows > 0;
    } catch (mysqli_sql_exception $e) {
        log_error("Database error: " . $e->getMessage());
        return false;
    } finally {
        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
    }
}

function Main($db_credentials) {
    $conn = null;
    try {
        if (!verifyPostMethod()) {
            send_api_response(new DeleteUserResponse(false, "Something went wrong..."));
            return;
        }

        $requiredFields = ['sessionId', 'accountUsername'];
        $input = fetch_json_data($requiredFields);

        $session = verify_admin_session($input['sessionId']);
        if (!$session) {
            send_api_response(new DeleteUserResponse(false, "Unauthorized Access"));
            return;
        }

        if ($session['username'] === $input['accountUsername']) {
            send_api_response(new DeleteUserResponse(false, "Unable to delete user, this is your account."));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(new DeleteUserResponse(false, "Something went wrong with the server...."));
            return;
        }

        $deletionSuccess = delete_user_account($conn, $input['accountUsername']);

        if (!$deletionSuccess) {
            send_api_response(new DeleteUserResponse(false, "User not found or cannot be deleted"));
            return;
        }

        send_api_response(new DeleteUserResponse(true, null));

    } catch (Exception $e) {
        log_error("Application error: " . $e->getMessage());
        send_api_response(new DeleteUserResponse(false, "Something went wrong...."));
    } finally {
        if ($conn) {
            mysqli_close($conn);
        }
    }
}

Main($db_credentials);
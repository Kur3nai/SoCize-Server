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
    exit(json_encode(DeleteUserResponse::createErrorResponse("Something went wrong....")));
}

function verify_admin_session(string $sessionId): ?array {
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
    try {
        $stmt = mysqli_prepare($conn, "CALL delete_user_account(?)");
        if (!$stmt) {
            throw new Exception("Something went wrong with the server....");
        }

        mysqli_stmt_bind_param($stmt, "s", $targetUsername);
        $success = mysqli_stmt_execute($stmt);
        $affectedRows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        return $affectedRows > 0;
    } catch (mysqli_sql_exception $e) {
        log_error("Database error: " . $e->getMessage());
        return false;
    }
}

function Main($db_credentials) {
    try {
        if (!verifyPostMethod()) {
            send_api_response(new DeleteUserResponse(false, "Something went wrong..."));
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['sessionId']) || !isset($input['accountUsername'])) {
            send_api_response(new DeleteUserResponse(false, "Something went wrong...."));
            return;
        }

        $session = verify_admin_session($input['sessionId']);
        if (!$session) {
            send_api_response(new DeleteUserResponse(false, "Unauthorized Access"));
            return;
        }

        if ($session['username'] === $input['accountUsername']) {
            send_api_response(new DeleteUserResponse(false, "Something went wrong..."));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(new DeleteUserResponse(false, "Something went wrong with the server...."));
            return;
        }

        $deletionSuccess = delete_user_account($conn, $input['accountUsername']);
        mysqli_close($conn);

        if (!$deletionSuccess) {
            send_api_response(new DeleteUserResponse(false, "User not found"));
            return;
        }

        send_api_response(new DeleteUserResponse(true, null));

    } catch (Exception $e) {
        if (isset($conn)) {
            mysqli_close($conn);
        }
        log_error("Application error: " . $e->getMessage());
        send_api_response(new DeleteUserResponse(false, "Something went wrong...."));
    }
}

Main($db_credentials);
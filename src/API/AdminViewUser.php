<?php declare(strict_types=1);

class UserListResponse {
    public bool $success;
    public ?string $errorMessage;
    public ?array $accounts;

    public function __construct(bool $success, ?string $errorMessage, ?array $accounts = null) {
        $this->success = $success;
        $this->errorMessage = $errorMessage;
        $this->accounts = $success ? ($accounts ?? []) : null;
    }

    public static function createErrorResponse(string $errorMessage) {
        return new UserListResponse(false, $errorMessage);
    }
}

error_reporting(0);

try {
    require_once "../Utility/ErrorLogging.php";
    require_once "../Utility/ResponseHelper.php";
    require_once "../Utility/SessionHelper.php";
    require_once "../Config/DatabaseConfig.php";
} catch (Throwable $e) {
    exit(json_encode(UserListResponse::createErrorResponse("Something went wrong....")));
}

function get_all_usernames(mysqli $conn): array {
    try {
        $stmt = mysqli_prepare($conn, "CALL get_all_usernames()");
        if (!$stmt) {
            throw new Exception("Something went wrong....");
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $accounts = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $accounts[] = ['username' => $row['username']];
        }
        
        mysqli_stmt_close($stmt);
        return $accounts;
    } catch (mysqli_sql_exception $e) {
        log_error("Database error: " . $e->getMessage());
        throw $e;
    }
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
        'role' => $_SESSION['role'],
        'csrf_token' => $_SESSION['csrf_token']
    ];
}

function Main($db_credentials) {
    try {
        if (!verifyPostMethod()) {
            send_api_response(new UserListResponse(false, "Something went wrong....."));
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['sessionId'])) {
            send_api_response(new UserListResponse(false, "User is not logged in"));
            return;
        }

        $session = verify_admin_session($input['sessionId']);
        if (!$session) {
            send_api_response(new UserListResponse(false, "Access denied: Admin privileges required"));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(new UserListResponse(false, "Something went wrong with the server....."));
            return;
        }

        $accounts = get_all_usernames($conn);
        mysqli_close($conn);
        
        send_api_response(new UserListResponse(true, null, $accounts));

    } catch (Exception $e) {
        if (isset($conn)) {
            mysqli_close($conn);
        }
        log_error("Application error: " . $e->getMessage());
        send_api_response(new UserListResponse(false, "Something went wrong with the server...."));
    }
}

Main($db_credentials);
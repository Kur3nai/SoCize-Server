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
    if (function_exists('log_error')) {
        log_error("Initialization failed: " . $e->getMessage());
    }
    exit(json_encode(UserListResponse::createErrorResponse("Initialization failed")));
}

function get_all_usernames(mysqli $conn): array {
    $stmt = null;
    $result = null;
    try {
        $stmt = mysqli_prepare($conn, "CALL get_all_usernames()");
        if (!$stmt) {
            throw new Exception("Database operation failed");
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $accounts = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $accounts[] = ['username' => $row['username']];
        }
        
        return $accounts;
    } finally {
        if ($result) {
            mysqli_free_result($result);
        }
        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
    }
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
        'role' => $_SESSION['role'],
        'csrf_token' => $_SESSION['csrf_token']
    ];
}

function Main($db_credentials) {
    $conn = null;
    try {
        if (!verifyPostMethod()) {
            send_api_response(new UserListResponse(false, "Only POST requests allowed"));
            return;
        }

        $requiredFields = ['sessionId'];
        $input = fetch_json_data($requiredFields);

        $session = verify_admin_session($input['sessionId']);
        if (!$session) {
            send_api_response(new UserListResponse(false, "Access denied: Admin privileges required"));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(new UserListResponse(false, "Database connection failed"));
            return;
        }

        $accounts = get_all_usernames($conn);
        send_api_response(new UserListResponse(true, null, $accounts));

    } catch (Exception $e) {
        log_error("Application error: " . $e->getMessage());
        send_api_response(new UserListResponse(false, "An error occurred"));
    } finally {
        if ($conn) {
            mysqli_close($conn);
        }
    }
}

Main($db_credentials);
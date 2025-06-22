<?php declare(strict_types=1);

class AccountDetailsResponse {
    public bool $success;
    public ?string $errorMessage;
    public ?array $details;

    public function __construct(bool $success, ?string $errorMessage, ?array $details = null) {
        $this->success = $success;
        $this->errorMessage = $errorMessage;
        $this->details = $success ? $details : null;
    }

    public static function createErrorResponse(string $errorMessage) {
        return new AccountDetailsResponse(false, $errorMessage);
    }
}

error_reporting(0);

try {
    require_once "../Utility/ErrorLogging.php";
    require_once "../Utility/ResponseHelper.php";
    require_once "../Utility/SessionHelper.php";
    require_once "../Config/DatabaseConfig.php";
} catch (Throwable $e) {
    exit(json_encode(AccountDetailsResponse::createErrorResponse("Something went wrong.....")));
}

function get_account_details(mysqli $conn, string $username): ?array {
    try {
        $stmt = mysqli_prepare($conn, "CALL get_account_details(?)");
        if (!$stmt) {
            throw new Exception("Something went wrong with the server.....");
        }

        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $details = [
                'username' => $row['username'],
                'email' => $row['email'],
                'phoneNumber' => $row['phoneNumber']
            ];
            mysqli_stmt_close($stmt);
            return $details;
        }
        
        mysqli_stmt_close($stmt);
        return null;
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
        'role' => $_SESSION['role']
    ];
}

function Main($db_credentials) {
    try {
        if (!verifyPostMethod()) {
            send_api_response(new AccountDetailsResponse(false, "Something went wrong....."));
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['sessionId']) || !isset($input['accountUsername'])) {
            send_api_response(new AccountDetailsResponse(false, "Something went wrong....."));
            return;
        }

        $session = verify_admin_session($input['sessionId']);
        if (!$session) {
            send_api_response(new AccountDetailsResponse(false, "Access denied: Admin privileges required"));
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            send_api_response(new AccountDetailsResponse(false, "Something went wrong with the server...."));
            return;
        }

        $details = get_account_details($conn, $input['accountUsername']);
        mysqli_close($conn);

        if ($details === null) {
            send_api_response(new AccountDetailsResponse(false, "User not found"));
            return;
        }

        send_api_response(new AccountDetailsResponse(true, null, $details));

    } catch (Exception $e) {
        if (isset($conn)) {
            mysqli_close($conn);
        }
        log_error("Application error: " . $e->getMessage());
        send_api_response(new AccountDetailsResponse(false, "Something went wrong...."));
    }
}

Main($db_credentials);
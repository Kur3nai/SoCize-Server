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
    if (function_exists('log_error')) {
        log_error("Initialization failed: " . $e->getMessage());
    }
    exit(json_encode(AccountDetailsResponse::createErrorResponse("Something went wrong.....")));
}

function get_account_details(mysqli $conn, string $username): ?array {
    try {
        $stmt = mysqli_prepare($conn, "CALL get_account_details(?)");
        if (!$stmt) {
            throw new Exception("Something went wrong with the server.....");
        }

        if(!mysqli_stmt_bind_param($stmt, "s", $username)) {
            throw new Exception("Failed to bind parameter for prepared statement");
        }

        if(!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to execute database query");
        }

        $result = mysqli_stmt_get_result($stmt);
        if(!$result) {
            throw new Exception("Failed to get result set");
        }

        if ($row = mysqli_fetch_assoc($result)) {
            $details = [
                'username' => $row['username'],
                'email' => $row['email'],
                'phoneNumber' => $row['phone_number']
            ];

            return $details;
        }
        
        return null;

    } catch (mysqli_sql_exception $e) {
        log_error("Database error: " . $e->getMessage());
        throw $e;

    } finally {
        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
    }
}

function Main($db_credentials) {
    try {
        if (!verifyPostMethod()) {
            send_api_response(new AccountDetailsResponse(false, "Something went wrong....."));
            return;
        }

        $requiredFields = ['sessionId', 'accountUsername'];
        $input = fetch_json_data($requiredFields);

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

        if ($details === null) {
            send_api_response(new AccountDetailsResponse(false, "User not found"));
            return;
        }

        send_api_response(new AccountDetailsResponse(true, null, $details));

    } catch (Exception $e) {
        log_error("Application error: " . $e->getMessage());
        send_api_response(new AccountDetailsResponse(false, "Something went wrong...."));
    } finally {
        if (isset($conn)) {
            mysqli_close($conn);
        }
    }
}

Main($db_credentials);
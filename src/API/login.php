<?php declare(strict_types=1);

class ValidationError {
    public ?string $username;
    public ?string $password;

    private bool $hasError;

    public function __construct() {
        $this->hasError = false;
        $this->username = null;
        $this->password = null;
    }

    public function setUsernameError(string $error) {
        $this->username = $error;
        $this->hasError = true;
    }

    public function setPasswordError(string $error) {
        $this->password = $error;
        $this->hasError = true;
    }

    public function hasErrors() {
        return $this->hasError;
    }
}

class LoginResponse {
    public bool $success;
    public ?string $sessionId;
    public ?string $role;
    public ?string $errorMessage;
    public ?ValidationError $validationError;

    public function __construct(bool $success, ?string $sessionId, ?string $role, ?string $errorMessage, ?ValidationError $validationError) {
        $this->success = $success;
        $this->sessionId = $sessionId;
        $this->role = $role;
        $this->errorMessage = $errorMessage;
        $this->validationError = $validationError;
    }

    public static function createErrorResponse(string $errorMessage) {
        return new LoginResponse(false, null, null, $errorMessage, null);
    }
}

error_reporting(0);

try {
    require_once "../Utility/ErrorLogging.php";
    require_once "../Utility/RequestResponseHelper.php";
    require_once "../Utility/StringCheck.php";
    require_once "../Config/DatabaseConfig.php";
    require_once "../Utility/RoleCheck.php";
} catch (Throwable $e) {
    if (function_exists('log_error')) {
        log_error("Initialization failed: " . $e->getMessage());
    }
    exit(json_encode(LoginResponse::createErrorResponse("Something went wrong on the server..")));
}

/**
 * Verifies user credentials and returns user data if valid
 */
function verify_user_credentials(mysqli|bool $conn, string $username, string $password): array|false {
    try {
        $sql_query = "CALL get_user_login_credentials(?)";

        if(!$conn) {
            throw new Exception("Unable to connect to database");
        }

        $stmt = mysqli_prepare($conn, $sql_query);

        if(!$stmt) {
            throw new Exception("Unable to prepare statement");
        }

        if(!mysqli_stmt_bind_param($stmt, 's', $username)) {
            throw new Exception("Can't bind statement parameter");
        }

        if(!mysqli_stmt_execute($stmt)) {
            throw new Exception("Can't execute statement");
        }

        $result = mysqli_stmt_get_result($stmt);

        if($result === false) {
            throw new Exception("Unable to get result object");
        }

        $row_count = mysqli_num_rows($result);

        if($row_count > 1) {
            throw new Exception("ALERT, sql query returned more than one row for username: ". $username);
        }

        if($row_count == 0) {
            return false;
        }

        $user = mysqli_fetch_assoc($result);

        if(!$user) {
            throw new Exception("Unable to fetch result data");
        }

        if(!isset($user['user_password'])) {
            throw new Exception("Password field not returned in query result");
        }

        if(!password_verify($password, $user['user_password'])) {
            return false;
        }

        return $user;
        
    } finally {
        if(isset($stmt)) {
            mysqli_stmt_close($stmt);
        }
    }
}

/**
 * Creates a new session for the authenticated user
 */
function create_user_session(array $user_data): string {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    } else {
        session_start([
            'use_only_cookies' => 0, // Since we're not using cookies
            'use_strict_mode' => true,
            'cookie_httponly' => true,
            'cookie_secure' => true,
            'cookie_samesite' => 'Strict'
        ]);
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = $user_data['user_id'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['role'] = $user_data['role'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    return session_id();
}

function Main($db_credentials) {
    try {
        if (has_session()) {
            $response = LoginResponse::createErrorResponse("User already logged in");
            send_api_response($response);
            return;
        }

        $requiredFields = ['username', 'password'];
        $requestData = fetch_json_data($requiredFields);

        $validation = new ValidationError();

        if ($error = ValidationHelper::validateUsername($requestData['username'])) {
            $validation->setUsernameError($error);
        }

        if ($error = ValidationHelper::validatePassword($requestData['password'])) {
            $validation->setPasswordError($error);
        }

        if ($validation->hasErrors()) {
            $response = new LoginResponse(false, null, null, null, $validation);
            send_api_response($response);
            return;
        }

        // Connect to database and verify credentials
        $conn = mysqli_connect(...$db_credentials);
        $user_data = verify_user_credentials($conn, $requestData['username'], $requestData['password']);

        if (!$user_data) {
            $response = new LoginResponse(false, null, null, "Incorrect username or password", null);
            send_api_response($response);
            return;
        }

        // Create session and return success response
        $session_id = create_user_session($user_data);
        
        $response = new LoginResponse(
            true,
            $session_id,
            $user_data['role'],
            null,
            null
        );
        
        send_api_response($response);

    } catch (Exception $e) {
        log_error("Application error: " . $e->getMessage());
        $response = LoginResponse::createErrorResponse("Something went wrong on the server...");
        send_api_response($response);
    } catch (Error $err) {
        log_error("Application error: " . $err->getMessage());
        $response = LoginResponse::createErrorResponse("Something went wrong on the server...");
        send_api_response($response);
    } finally {
        if(isset($conn)) {
            mysqli_close($conn);
        }
    }
}

Main($db_credentials);
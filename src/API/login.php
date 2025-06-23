<?php declare(strict_types=1);

class SignInValidationError {
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

class SignInResponse {
    public bool $success;
    public ?string $sessionId;
    public ?string $role;
    public ?string $errorMessage;
    public ?SignInValidationError $validationError;

    public function __construct(bool $success, ?string $sessionId, ?string $role, ?string $errorMessage, ?SignInValidationError $validationError) {
        $this->success = $success;
        $this->sessionId = $sessionId;
        $this->role = $role;
        $this->errorMessage = $errorMessage;
        $this->validationError = $validationError;
    }

    public static function createErrorResponse(string $errorMessage) {
        return new SignInResponse(false, null, null, $errorMessage, null);
    }
}

error_reporting(0);

try {
    require_once "../Utility/ErrorLogging.php";
    require_once "../Utility/ValidationHelper.php";
    require_once "../Utility/ResponseHelper.php";
    require_once "../Config/HashPassword.php";
    require_once "../Config/DatabaseConfig.php";
    require_once "../Utility/SessionHelper.php";
} catch (Throwable $e) {
    if (function_exists('log_error')) {
        log_error("Initialization failed: " . $e->getMessage());
    }
    exit(json_encode(SignInResponse::createErrorResponse("Something went wrong on the server..")));
}

function verify_user_credentials(mysqli|bool $conn, string $username, string $password) : ?array {
    try {
        $sql_query = "CALL verify_user_credentials(?)";

        if(!$conn) {
            throw new Exception("Unable to connect to database");
        }

        $stmt = mysqli_prepare($conn, $sql_query);

        if(!$stmt) {
            throw new Exception("Unable to prepare statement");
        }

        if(!mysqli_stmt_bind_param($stmt, "s", $username)) {
            throw new Exception("Can't bind statement parameter");
        }

        if(!mysqli_stmt_execute($stmt)) {
            throw new Exception("Can't execute statement");
        }

        $result = mysqli_stmt_get_result($stmt);
        $user_data = mysqli_fetch_assoc($result);

        if(!$user_data) {
            return null;
        }

        if(!password_verify($password, $user_data['password'])) {
            return null;
        }

        return $user_data;

    } catch(mysqli_sql_exception $sql_e) {
        log_error($sql_e->getMessage());
        return null;

    } catch (Exception $e) {
        log_error($e->getMessage());
        return null;

    } catch(Error $err) {
        log_error($err->getMessage());
        return null;

    } finally {
        if(isset($stmt)) {
            mysqli_stmt_close($stmt);
        }

        if(isset($conn)) {
            mysqli_close($conn);
        }
    }
}

function Main($db_credentials) {
    if(!verifyPostMethod()) {
        send_api_response(SignInResponse::createErrorResponse("Something went wrong..."));
    }

    try {
        $requiredFields = ['username', 'password'];
        $requestData = fetch_json_data($requiredFields);

        $validation = new SignInValidationError();

        if (empty($requestData['username'])) {
            $validation->setUsernameError("Username is required");
        }

        if (empty($requestData['password'])) {
            $validation->setPasswordError("Password is required");
        }

        if($validation->hasErrors()) {
            $response = new SignInResponse(false, null, null, null, $validation);
            send_api_response($response);
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            $response = SignInResponse::createErrorResponse("Something went wrong on the server..");
            send_api_response($response);
            return;
        }

        $user_data = verify_user_credentials($conn, $requestData['username'], $requestData['password']);
        
        if(!$user_data) {
            $response = SignInResponse::createErrorResponse("Invalid username or password");
            send_api_response($response);
            return;
        }

        check_login_status();
        session_regenerate_id(true);
        
        $_SESSION['username'] = $user_data['username'];
        $_SESSION['role'] = $user_data['role'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        $response = new SignInResponse(
            true,
            session_id(),
            $user_data['role'],
            null,
            null
        );
        
        send_api_response($response);

    } catch (Exception $e) {
        log_error("Application error: " . $e->getMessage());
        $response = SignInResponse::createErrorResponse("Something went wrong on the server..");
        send_api_response($response);
        return;
    }
}

Main($db_credentials);
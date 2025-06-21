<?php declare(strict_types=1);

class ValidationError {
    public ?string $username;
    public ?string $password;
    public ?string $email;
    public ?string $phoneNumber;

    private bool $hasError;

    public function __construct() {
        $this->hasError = false;
        $this->username = null;
        $this->password = null;
        $this->email = null;
        $this->phoneNumber = null;
    }

    public function setUsernameError(string $error) {
        $this->username = $error;
        $this->hasError = true;
    }

    public function setPasswordError(string $error) {
        $this->password = $error;
        $this->hasError = true;
    }

    public function setEmailError(string $error) {
        $this->email = $error;
        $this->hasError = true;
    }

    public function setPhoneNumberError(string $error) {
        $this->phoneNumber = $error;
        $this->hasError = true;
    }

    public function hasErrors() {
        return $this->hasError;
    }
}

class SignUpResponse {
    public bool $success;
    public ?string $errorMessage;
    public ?ValidationError $validationError;

    public function __construct(bool $success, ?string $errorMessage, ?ValidationError $validationError) {
        $this->success = $success;
        $this->errorMessage = $errorMessage;
        $this->validationError = $validationError;
    }

    public static function createErrorResponse(string $errorMessage) {
        return new SignUpResponse(false, $errorMessage, null);
    }
}

error_reporting(0);

try {
    require_once "../Utility/ErrorLogging.php";
    require_once "../Utility/ValidationHelper.php";
    require_once "../Utility/ResponseHelper.php";
    require_once "../Config/HashPassword.php";
    require_once "../Config/DatabaseConfig.php";
} catch (Throwable $e) {
    if (function_exists('log_error')) {
        log_error("Initialization failed: " . $e->getMessage());
    }
    exit(json_encode(SignUpResponse::createErrorResponse("Something went wrong on the server..")));
}

function add_user_to_db(mysqli|bool $conn, string $username, string $password, string $email, string $phone_number) : void {
    try {
        $sql_query = "CALL create_user_account(?,?,?,?)";

        if(!$conn) {
            throw new Exception("Unable to connect to database");
        }

        $stmt = mysqli_prepare($conn, $sql_query);

        if(!$stmt) {
            throw new Exception("Unable to prepare statement");
        }

        if(!mysqli_stmt_bind_param($stmt, "ssss", $username, $password, $email, $phone_number)) {
            throw new Exception("Can't bind statement parameter");
        }

        if(!mysqli_stmt_execute($stmt)) {
            throw new Exception("Can't execute statement");
        }

    } catch(mysqli_sql_exception $sql_e) {
        if($sql_e->getCode() == 1062) {
            $response = SignUpResponse::createErrorResponse("Username already exist..");
            send_api_response($response);
            return;
            exit;
        } else {
            log_error($sql_e->getMessage());
            $response = SignUpResponse::createErrorResponse("Something went wrong on the server..");
            send_api_response($response);
            return;
        }

    } catch (Exception $e) {
        log_error($e->getMessage());
        $response = SignUpResponse::createErrorResponse("Something went wrong on the server..");
        send_api_response($response);
        return;

    } catch(Error $err) {
        log_error($err->getMessage());
        $response = SignUpResponse::createErrorResponse("Something went wrong on the server..");
        send_api_response($response);
        return;

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
    verifyPostMethod();

    try {
        $requiredFields = ['username', 'password', 'email', 'phoneNumber'];
        $requestData = fetch_json_data($requiredFields);

        $validation = new ValidationError();

        if ($error = ValidationHelper::validateUsername($requestData['username'])) {
            $validation->setUsernameError($error);
        }

        if ($error = ValidationHelper::validatePassword($requestData['password'])) {
            $validation->setPasswordError($error);
        }

        if ($error = ValidationHelper::validateEmail($requestData['email'])) {
            $validation->setEmailError($error);
        }

        if ($error = ValidationHelper::validatePhoneNumber($requestData['phoneNumber'])) {
            $validation->setPhoneNumberError($error);
        }

        if($validation->hasErrors()) {
            $response = new SignUpResponse(false, null, $validation);
            send_api_response($response);
        }

        $hashed_password = createHashedPassword($requestData["password"]);

        if(!$hashed_password) {
            $response = SignUpResponse::createErrorResponse("Something went wrong on the server..");
            send_api_response($response);
            return;
        }

        $conn = mysqli_connect(...$db_credentials);
        if (!$conn) {
            $response = SignUpResponse::createErrorResponse("Something went wrong on the server..");
            send_api_response($response);
            return;
        }

        add_user_to_db($conn, $requestData['username'], $hashed_password, $requestData['email'], $requestData['phoneNumber']);
        
        send_api_response([
            'success' => true,
            'errorMessage' => null,
            'validationError' => null
        ]);

    } catch (Exception $e) {
        log_error("Application error: " . $e->getMessage());
        $response = SignUpResponse::createErrorResponse("Something went wrong on the server..");
        send_api_response($response);
        return;
    }
}

Main($db_credentials);
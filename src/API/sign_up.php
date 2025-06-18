<?php declare(strict_types=1);

error_reporting(0);

try {
    require_once "../Utility/ErrorLogging.php";

} catch(Error) {
    http_response_code(500);
    exit;

}

try {
    require_once "../Utility/ResponseHelper.php";
    require_once "../Utility/FormatChecker.php";
    require_once "../Config/DatabaseConfig.php";

} catch (Error $e) {
    log_error($e->getMessage());
    http_response_code(500);
    exit;

}

class User {
    public string $username;
    public string $role_id;
    public string $password;
    public string $email;
    public string $phoneNumber;

    public function __construct(string $username, string $role_id, string $password, string $email, string $phoneNumber) {
        $this->username = $username;
        $this->role_id = $role_id;
        $this->password = $password;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
    }
}

class Response {
    public bool $success;
    public ?string $errorMessage;
    public ?array $validationError;

    public function __construct(bool $success, ?string $errorMessage, ?array $validationError) {
        $this->success = $success;
        $this->errorMessage = $errorMessage;
        $this->validationError = $validationError;
    }

    public static function createSuccessResponse(): Response {
        return new Response(true, null, null);
    }

    public static function createErrorResponse(string $errorMessage): Response {
        return new Response(false, $errorMessage, null);
    }

    public static function createValidationErrorResponse(array $validationError): Response {
        return new Response(false, null, $validationError);
    }
}

/**
 * Sends a JSON API response in the specified format.
 *
 * @param Response $response
 */
function send_api_response(Response $response): void {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Attempts to add the user into the database.
 *
 * @param mysqli|bool $conn
 * @param User $user
 */
function add_user_to_db(mysqli|bool $conn, User $user): void {
    try {
        $sql_query = "CALL add_new_user(?,?,?,?)";

        if (!$conn) {
            throw new Exception("Unable to connect to database");
        }

        $stmt = mysqli_prepare($conn, $sql_query);
        if (!$stmt) {
            throw new Exception("Unable to prepare statement");
        }

        if (!mysqli_stmt_bind_param($stmt, "ssss", $user->username, $user->role_id, $user->password, $user->email, $user->phoneNumber)) {
            throw new Exception("Can't bind statement parameter");
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Can't execute statement");
        }

    } catch (mysqli_sql_exception $sql_e) {
        if ($sql_e->getCode() == 1062) {
            send_api_response(Response::createErrorResponse("Username already exists"));
        } else {
            log_error($sql_e->getMessage());
            send_api_response(Response::createErrorResponse("Something went wrong on the server..."));
        }

    } catch (Exception $e) {
        log_error($e->getMessage());
        send_api_response(Response::createErrorResponse("Something went wrong on the server..."));

    } catch (Error $err) {
        log_error($err->getMessage());
        send_api_response(Response::createErrorResponse("Something went wrong on the server..."));

    } finally {
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }

        if (isset($conn)) {
            mysqli_close($conn);
        }
    }
}

/**
 * Main entry for processing the sign-up request.
 *
 * @param array $db_creds
 */
function main(array $db_creds) {
    try {
        // Validate incoming JSON payload
        $required_fields = ["username", "password", "email", "phone_number"];
        $data_received = verifyJsonInput($required_fields);

        $errors = new ValidationError();

        // Validate user input formats
        if (!isValidUsername($data_received["username"])) {
            $errors->setUsernameError("Invalid username format");
        }

        if (!isValidPassword($data_received["password"])) {
            $errors->setPasswordError("Invalid password format");
        }

        if (!isValidEmail($data_received["email"])) {
            $errors->setEmailError("Invalid email format");
        }

if (!isValidPhoneNumber($data_received["phone_number"])) {
            $errors->setPhoneNumberError("Invalid phone number format");
        }

        if ($errors->hasErrors()) {
            send_api_response([
                "success" => false,
                "validationError" => [
                    "username" => $errors->username,
                    "password" => $errors->password,
                    "email" => $errors->email,
                    "phoneNumber" => $errors->phoneNumber
                ]
            ], BAD_REQUEST);
        }

        $hashed_password = get_password_hash($data_received["password"]);
        if (!$hashed_password) {
            log_error("Unable to get password hash");
            send_api_response(["success" => false, "errorMessage" => "Something went wrong on the server..."], INTERNAL_ERROR);
        }

        $conn = mysqli_connect(...$db_creds);
        if (!$conn) {
            send_api_response(["success" => false, "errorMessage" => "Database connection failed"], INTERNAL_ERROR);
        }

        $role_id = 'user'; 

        $user = new User($data_received["username"], $role_id, $hashed_password, $data_received["email"], $data_received["phone_number"]);
        
        add_user_to_db($conn, $user);

        send_api_response(["success" => true], OK);

    } catch (Exception $e) {
        log_error($e->getMessage());
        send_api_response(["success" => false, "errorMessage" => "Something went wrong on the server..."], INTERNAL_ERROR);

    } catch (Error $err) {
        log_error($err->getMessage());
        send_api_response(["success" => false, "errorMessage" => "Something went wrong on the server..."], INTERNAL_ERROR);
    }
}

main($db_credentials);

?>

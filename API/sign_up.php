<?php declare(strict_types=1);

try {
    require_once "../Utility/ErrorLogging.php";
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
        $required_fields = ["username", "role_id", "password", "email", "phone_number"];
        try {
            
        // Get the raw POST data
        $json_input = file_get_contents('php://input');
        
        // Decode the JSON data into an associative array
        $data_received = json_decode($json_input, true);

        // Check if decoding was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            send_api_response(Response::createErrorResponse("Invalid JSON input"));
            return;
        }

        // Validate required fields
        $required_fields = ["username", "role_id", "password", "email", "phone_number"];
        foreach ($required_fields as $field) {
            if (!isset($data_received[$field])) {
                send_api_response(Response::createErrorResponse("Missing required field: $field"));
                return;
            }
        }

        $validationErrors = [
            "username" => null,
            "role_id" => null,
            "password" => null,
            "email" => null,
            "phoneNumber" => null
        ];

        // Validate user input formats
        if (!isValidUsername($data_received["username"])) {
            $validationErrors["username"] = "Invalid username format";
        }

        if (!isValidPassword($data_received["password"])) {
            $validationErrors["password"] = "Invalid password format";
        }

        if (!isValidEmail($data_received["email"])) {
            $validationErrors["email"] = "Invalid email format";
        }

        if (!isValidPhoneNumber($data_received["phone_number"])) {
            $validationErrors["phoneNumber"] = "Invalid phone number format";
        }

        // If any validation error exists, respond with them
        if (array_filter($validationErrors)) {
            send_api_response(Response::createValidationErrorResponse($validationErrors));
        }

        // $hashed_password = get_password_hash($data_received["password"]);
        // if (!$hashed_password) {
        //     log_error("Unable to get password hash");
        //     send_api_response(Response::createErrorResponse("Something went wrong on the server..."));
        // }

        // $conn = mysqli_connect(...$db_creds);
        // $user = new User($data_received["username"], $data_received["role_id"], $hashed_password, $data_received["email"], $data_received["phone_number"]);
        // add_user_to_db($conn, $user);

        send_api_response(Response::createSuccessResponse());

    } catch (Exception $e) {
        log_error($e->getMessage());
        send_api_response(Response::createErrorResponse("Something went wrong on the server..."));

    } catch (Error $err) {
        log_error($err->getMessage());
        send_api_response(Response::createErrorResponse("Something went wrong on the server..."));
    }
}

// Execute the main function with the DB credentials
main($db_credentials);

?>

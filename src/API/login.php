<?php declare(strict_types=1);

error_reporting(0); // To not display error message to client

try {
    require_once "../Utility/ErrorLogging.php";
    require_once "../Utility/ResponseHelper.php";
    require_once "../Utility/FormatChecker.php";
    require_once "../Config/DatabaseConfig.php";
    require_once "../Utility/RoleCheck.php";
} catch (Error $e) {
    log_error($e->getMessage());
    http_response_code(500);
    exit;
}

class User {
    public string $username;
    public string $password;
    public string $role;

    public function __construct(string $username, string $password, string $role) {
        $this->username = $username;
        $this->password = $password;
        $this->role = $role;
    }

    public static function getUserData(mysqli $conn, string $username, string $password): ?User {
        $sql_query = "CALL get_user_login_credentials(?)";
        $db_password_field = "user_password";

        if (!$conn) {
            throw new Exception("Unable to connect to database");
        }

        $stmt = mysqli_prepare($conn, $sql_query);
        if (!$stmt) {
            throw new Exception("Unable to prepare statement");
        }

        if (!mysqli_stmt_bind_param($stmt, 's', $username)) {
            throw new Exception("Can't bind statement parameter");
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Can't execute statement");
        }

        $result = mysqli_stmt_get_result($stmt);
        if ($result === false) {
            throw new Exception("Unable to get result object");
        }

        if (mysqli_num_rows($result) === 0) {
            return null; // User not found
        }

        $user_data = mysqli_fetch_assoc($result);
        if (!$user_data || !array_key_exists($db_password_field, $user_data)) {
            throw new Exception("User data is invalid");
        }

        if (password_verify($password, $user_data[$db_password_field])) {
            return new User($username, $user_data[$db_password_field], $user_data['role']); // Assuming 'role' is a field in the user data
        }

        return null; // Invalid password
    }
}

class Response {
    public bool $success;
    public ?string $sessionId;
    public ?string $role;
    public ?string $errorMessage;
    public ?array $validationError;

    public function __construct(bool $success, ?string $sessionId, ?string $role, ?string $errorMessage, ?array $validationError) {
        $this->success = $success;
        $this->sessionId = $sessionId;
        $this->role = $role;
        $this->errorMessage = $errorMessage;
        $this->validationError = $validationError;
    }

    public function toArray(): array {
        return [
            "success" => $this->success,
            "sessionId" => $this->sessionId,
            "role" => $this->role,
            "errorMessage" => $this->errorMessage,
            "validationError" => $this->validationError
        ];
    }
}

/**
 * The main entry for this script, coordinates the process flow of this script to authenticate users.
 * 
 * @param array $db_creds the database credentials
 */
function main(array $db_creds) {
    try {
        if (has_session()) {
            $response = new Response(false, null, null, "Already logged in", null);
            send_api_response($response->toArray(), HTTP_CONFLICT); // Conflict
        }

        $required_fields = ["username", "password"];
        $data_received = validate_submitted_json_data($required_fields);

        // Validate username format
        if (!isValidUsername($data_received["username"])) {
            $response = new Response(false, null, null, null, ["username" => "Invalid format"]);
            send_api_response($response->toArray(), HTTP_BAD_REQUEST); // Bad Request
        }

        // Validate password format
        if (!isValidPassword($data_received["password"])) {
            $response = new Response(false, null, null, null, ["password" => "Invalid format"]);
            send_api_response($response->toArray(), HTTP_BAD_REQUEST); // Bad Request
        }

        $conn = mysqli_connect(...$db_creds);
        $user = User::getUserData($conn, $data_received["username"], $data_received["password"]);

        if ($user === null) {
            $response = new Response(false, null, null, "Incorrect username or password", null);
            send_api_response($response->toArray(), HTTP_UNAUTHORIZED); // Unauthorized
        } else {
            session_start();

            // Generate a session ID
            $sessionId = session_id();
            $_SESSION["username"] = $user->username;
            $_SESSION["role"] = $user->role; // Assuming role is stored in the session

            // Validate the role to ensure it is either "admin" or "user"
            if (!in_array($user->role, ['admin', 'user'])) {
                $response = new Response(false, null, null, "Invalid user role", null);
                send_api_response($response->toArray(), HTTP_FORBIDDEN); // Forbidden
            }

            // Prepare the successful response
            $response = new Response(true, $sessionId, $user->role, null, null);
            send_api_response($response->toArray(), HTTP_OK); // OK
        }

    } catch (Exception $ex) {
        log_error($ex->getMessage());
        $response = new Response(false, null, null, "Something went wrong on the server...", null);
        send_api_response($response->toArray(), HTTP_INTERNAL_ERROR); // Internal Server Error

    } catch (Error $err) {
        log_error($err->getMessage());
        $response = new Response(false, null, null, "Something went wrong on the server...", null);
        send_api_response($response->toArray(), HTTP_INTERNAL_ERROR); // Internal Server Error
    }
}

// Execute the main function with the DB credentials
main($db_credentials);


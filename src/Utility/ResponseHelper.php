<?php

// HTTP status code constants
const OK = 200;
const BAD_REQUEST = 400;
const UNAUTHORIZED = 401;
const NOT_FOUND = 404;
const CONFLICT = 409;
const INTERNAL_ERROR = 500;

/**
 * Retrieves and decodes raw JSON data from the request body.
 *
 * @return mixed Returns an associative array if valid, otherwise null.
 */
function receiveJsonInput(): mixed {
    $raw = file_get_contents("php://input");
    return json_decode($raw, true);
}


// /**
//  * Sends a JSON API response in the specified format.
//  *
//  * @param Response $response
//  */
// function send_api_response(Response $response): void {
//     header('Content-Type: application/json');
//     echo json_encode($response);
//     exit;
// }

/**
 * Sends a JSON API response in the specified format.
 *
 * @param array $response
 * @param int $statusCode
 */
function send_api_response(array $response, int $statusCode = HTTP_OK): void {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($response);
    exit;
}

/**
 * Sends a structured JSON response and halts script execution.
 *
 * @param mixed $content The content to return in the response.
 * @param int $statusCode The HTTP status code to include in the response.
 */
function outputJsonResponse(mixed $content, int $statusCode): void {
    header("Content-Type: application/json");
    http_response_code($statusCode);

    echo json_encode($response);
    exit;
}

/**
 * Validates incoming JSON payload by ensuring exact match with required fields.
 *
 * Rules:
 * - Must decode into a valid associative array
 * - Must contain exactly the keys listed in $requiredKeys
 *
 * @param array $requiredKeys List of expected field names
 * @return array Parsed and validated input
 */
function verifyJsonInput(array $requiredKeys): array {
    $input = receiveJsonInput();

    if (!is_array($input)) {
        outputJsonResponse("JSON decoding failed", HTTP_BAD_REQUEST);
    }

    if (count($input) !== count($requiredKeys)) {
        outputJsonResponse("Invalid number of fields provided", HTTP_BAD_REQUEST);
    }

    foreach ($requiredKeys as $field) {
        if (!array_key_exists($field, $input)) {
            outputJsonResponse("Missing expected field: {$field}", HTTP_BAD_REQUEST);
        }
    }

    return $input;
}

?>

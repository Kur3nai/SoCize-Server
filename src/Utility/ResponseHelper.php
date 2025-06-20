<?php

/**
 * Retrieves and decodes raw JSON data from the request body.
 *
 * @return mixed Returns an associative array if valid, otherwise null.
 */
function receive_json_input(): mixed {
    $raw = file_get_contents("php://input");
    return json_decode($raw, true);
}


/**
 * Sends a JSON API response in the specified format.
 *
 * @param mixed $response
 * @param int $statusCode
 */
function send_api_response(mixed $response, int $statusCode = HTTP_OK): void {
    header('Content-Type: application/json');
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
    $input = receive_json_input();

    if (!is_array($input)) {
        throw new Exception("JSON decoding failed");
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
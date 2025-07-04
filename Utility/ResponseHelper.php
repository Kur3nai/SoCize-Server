<?php

/**
 * Retrieves and decodes raw JSON data from the request body.
 *
 * @return mixed Returns an associative array if valid, otherwise null.
 */
function fetch_json_data(array $requiredKeys): array {
    $jsonInput = file_get_contents("php://input");
    if ($jsonInput === false) {
        throw new Exception("Failed to read input data");
    }

    $data = json_decode($jsonInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON input: " . json_last_error_msg());
    }

    if (!is_array($data)) {
        throw new Exception("Input must be a JSON object");
    }

    if (count($data) !== count($requiredKeys)) {
        throw new Exception(sprintf(
            "Invalid number of fields. Expected %d, got %d",
            count($requiredKeys),
            count($data)
        ));
    }

    foreach ($requiredKeys as $field) {
        if (!array_key_exists($field, $data)) {
            throw new Exception("Missing required field: " . $field);
        }
        
        if (empty($data[$field])) {
            throw new Exception("Field cannot be empty: " . $field);
        }
    }

    return $data;
}

/**
 * Sends a JSON API response in the specified format.
 *
 * @param mixed $response
 * @param int $statusCode
 */
function send_api_response(mixed $response): void {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}


function verifyPostMethod(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}


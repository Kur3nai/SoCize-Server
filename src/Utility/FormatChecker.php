<?php

/**
 * Determines if a character is an uppercase English letter.
 *
 * @param string $char
 * @return bool
 * @throws Error if input length is not 1
 */
function isUpper(string $char): bool {
    if (strlen($char) !== 1) {
        throw new Error("Only single characters are allowed");
    }

    return ($char >= 'A' && $char <= 'Z');
}

/**
 * Determines if a character is a lowercase English letter.
 *
 * @param string $char
 * @return bool
 * @throws Error if input length is not 1
 */
function isLower(string $char): bool {
    if (strlen($char) !== 1) {
        throw new Error("Only single characters are allowed");
    }

    return ($char >= 'a' && $char <= 'z');
}

/**
 * Checks if a character is numeric (0-9).
 *
 * @param string $char
 * @return bool
 * @throws Error if input is not a single character
 */
function isDigit(string $char): bool {
    if (strlen($char) !== 1) {
        throw new Error("Only single characters are allowed");
    }

    return ($char >= '0' && $char <= '9');
}

/**
 * Verifies if a character is whitespace (space, newline, or carriage return).
 *
 * @param string $char
 * @return bool
 * @throws Error if input is not a single character
 */
function isWhitespace(string $char): bool {
    if (strlen($char) !== 1) {
        throw new Error("Only single characters are allowed");
    }

    return ($char === ' ' || $char === "\n" || $char === "\r");
}

/**
 * Validates if a username meets required criteria:
 * - 3 to 20 characters
 * - Contains only alphanumeric characters, dashes or underscores
 *
 * @param string $user
 * @return bool
 */
function isValidUsername(string $user): bool {
    $length = strlen($user);

    if ($length < 3 || $length > 20) {
        return false;
    }

    for ($i = 0; $i < $length; $i++) {
        $ch = $user[$i];

        if (
            isUpper($ch) ||
            isLower($ch) ||
            isDigit($ch) ||
            $ch === '-' ||
            $ch === '_'
        ) {
            continue;
        }

        return false;
    }

    return true;
}

/**
 * Validates a password based on these rules:
 * - 8 to 30 characters
 * - At least one special symbol
 * - No spaces or newlines
 *
 * @param string $pass
 * @return bool
 */
function isValidPassword(string $pass): void {
    $length = strlen($pass);
    $symbolIncluded = false;

    if ($length < 8 || $length > 30) {
        throw new Exception("STupid bich");
    }

    for ($i = 0; $i < $length; $i++) {
        $ch = $pass[$i];

        if (isUpper($ch) || isLower($ch) || isDigit($ch)) {
            continue;
        }

        if (isWhitespace($ch)) {
            return false;
        }

        $symbolIncluded = true;
    }

    return $symbolIncluded;
}

/**
 * Validates a phone number by checking:
 * - Total string length: 10 to 20
 * - At least 10 numeric digits
 *
 * @param string $phone
 * @return bool
 */
function isValidPhoneNumber(string $phone): bool {
    $length = strlen($phone);
    $digitsOnly = '';

    if ($length < 10 || $length > 20) {
        return false;
    }

    for ($i = 0; $i < $length; $i++) {
        if (isDigit($phone[$i])) {
            $digitsOnly .= $phone[$i];
        }
    }

    return (strlen($digitsOnly) >= 10);
}

/**
 * Validates an email address using:
 * - Length range (5–30)
 * - Built-in filter validation
 *
 * @param string $email
 * @return bool
 */
function isValidEmail(string $email): bool {
    $length = strlen($email);

    if ($length < 5 || $length > 30) {
        return false;
    }

    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

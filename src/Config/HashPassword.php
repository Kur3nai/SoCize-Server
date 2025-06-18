<?php
/**
 * Generates a hashed version of the given password using a secure algorithm.
 * Centralizes password encryption logic for consistency across the application.
 *
 * @param string $plainPassword The password to be encrypted.
 * @return string|false|null Returns hashed string on success, false on error, or null if algorithm fails.
 */

function createHashedPassword(string $plainPassword): string|false|null {
    $settings = [
        'cost' => 10
    ];

    $result = password_hash($plainPassword, PASSWORD_BCRYPT, $settings);

    return $result;
}
?>
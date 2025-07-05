<?php
/**
 * Checks if the user's session contains the required session variables for a user that's logged in
 * 
 * Note :
 * 1. This function checks if the user is an admin or not, if the system detects that the user is not an admin, it will return null.
 * 2. Same goes for the customer one.
 *
 */

function verify_admin_session(string $sessionId): ?array {
    session_id($sessionId);
    session_start();

    if ($_SESSION['role'] !== 'admin') {
        return null;
    }

    return [
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role']
    ];
}

function verify_customer_session(string $sessionId): ?array {
    session_id($sessionId);
    session_start();

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
        return null;
    }

    return [
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'], 
    ];
}

?>

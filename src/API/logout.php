<?php declare(strict_types=1);

error_reporting(0);

try {
    require_once "../Utility/ErrorLogging.php";

} catch(Error) {
    http_response_code(500);
    exit;

}

try {
    require_once "../Utility/RoleCheck.php";
    require_once "../Utility/RequestResponseHelper.php";

} catch (Error $e) {
    log_error($e->getMessage());

    http_response_code(server_error);
    exit;
}

/**
 * Procedure to clear the session data in the server and remove session cookie for the user.
 */
function delete_session_data() : void {
    session_unset();
    session_destroy();
    session_write_close();
}

/**
 * The main entry for this script, coordinates the process flow of this script to allow the user to log out.
 * 
 * Process flow :
 * 1. Check if the user is logged in to begin with
 * 2. Clear the session data and remove session cookie for the user.
 * 3. Redirect user back to the main menu page
 */
function main() : void {
    if(!check_login_status()) {
        http_response_code(UNAUTHORIZED);
        exit;
    }

    delete_session_data();
    http_response_code(OK);
    exit; 
}

main();
?>
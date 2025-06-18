<?php

/**
 * Checks if the client request contains the php session id cookie. Can be used to check if user has an ongoing session aka logged in
 * 
 * Mainly used to determine if calling `session_start()` will create a new session or resume an old session
 * since it will use the php session id cookie. So if cookie exist then `session_start()` will resume the session, else it'll create a new one
 * 
 * @return bool true if exist, else false
 */
function has_session() : bool {
    if(isset($_COOKIE[session_name()])) {
        return true;

    } else {
        return false;
    }
}

/**
 * Checks if the user's session contains the required session variables for a user that's logged in
 * 
 * Note :
 * 1. This function doesn't call `session_start()`, so you need to call it before calling this function
 * 
 * @return bool true if session has required variables, else false
 */
function is_logged_in() : bool {
    if(!isset($_SESSION["username"])) {
        return false;
    }

    if(!isset($_SESSION["csrf_token"])) {
        return false;
    }

    return true;
}

/**
 * Checks if the user's session contains the required session variables for a user that has an active quiz attempt.
 * 
 * Note :
 * 1. This function doesn't call `session_start()`, so you need to call it before calling this function
 * 
 * @return bool true if user has an active quiz attempt, else false
 */
function has_quiz_attempt() : bool {
    if(!isset($_SESSION["quiz_attempt"])) {
        return false;
    }

    return true;
}

/**
 * Checks the login status of the user. 
 * This function combines the whole logic of checking if the user is logged in or not.
 * 
 * Note :
 * 1. This function will call `session_start()`
 * 
 * @see has_session() For checking if user submitted a valid php session id cookie
 * @see is_logged_in() For checking if the user session contains the required session variables
 * @return bool true if the user is logged in, else false
 */
function check_login_status() : bool {
    // Checks if the request contains a php session id cookie first before calling session_start()
    // to avoid creating a new session unintentionally
    if(!has_session()) {
        return false;
    }

    session_start();

    if(!is_logged_in()) {
        return false;
    }

    if($_SESSION["csrf_token"] !== $_COOKIE["csrf_token"]) {
        return false;
    }

    return true;
}

/**
 * Checks if the user is logged in and has an active quiz attempt.
 * This function combines the whole logic of checking if the user has an active quiz attempt or not.
 * 
 * Note :
 * 1. This function will call `session_start()`
 * 
 * @see check_login_status() For checking if the user is logged in or not.
 * @see has_quiz_attempt() For checking if the user session contains the required session variable
 * @return bool true if user has active quiz attempt, else false.
 */
function check_quiz_attempt_status() : bool {
    if(!check_login_status()) {
        return false;
    }

    return has_quiz_attempt();
}
?>
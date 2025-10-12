<?php
// start session so we can destroy it
session_start();

// clear all session variables
$_SESSION = [];

// destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// finally destroy the session
session_destroy();

// redirect to home page
header('Location: /');
exit;

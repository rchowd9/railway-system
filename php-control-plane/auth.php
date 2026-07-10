<?php
session_start();

define('ADMIN_USER', 'dispatcher_one');
define('ADMIN_PASS', 'TransitCore2026'); 

function login_dispatcher($user, $pass) {
    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['authenticated'] = true;
        $_SESSION['operator_id'] = $user;
        return true;
    }
    return false;
}

function verify_session_clearance() {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        header("Location: login.php");
        exit();
    }
}
?>
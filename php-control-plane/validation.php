<?php

/**
 * Validates password against strict complexity metrics.
 * Rules: Min 8 chars, 1 Uppercase, 1 Lowercase, 1 Number, 1 Special Character.
 *
 * @param string $password
 * @return array Array containing 'valid' boolean and an array of 'errors'
 */
function ValidatePasswordRules($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must include at least one uppercase letter (A-Z).";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must include at least one lowercase letter (a-z).";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must include at least one numerical digit (0-9).";
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must include at least one special character (e.g., !, @, #, $, %, ^, &).";
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
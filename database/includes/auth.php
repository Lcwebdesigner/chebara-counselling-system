<?php
/**
 * Chebara TVC Online Counselling Booking System
 * Authentication & Access Control
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check whether a user is logged in.
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/**
 * Require the user to be logged in.
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }
}

/**
 * Get the currently logged-in user's ID.
 */
function currentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get the currently logged-in user's role.
 */
function currentUserRole()
{
    return $_SESSION['role'] ?? null;
}

/**
 * Require a specific role.
 */
function requireRole($role)
{
    requireLogin();

    if (currentUserRole() !== $role) {
        header("Location: ../index.php");
        exit;
    }
}

/**
 * Require one of several roles.
 */
function requireAnyRole($roles = [])
{
    requireLogin();

    if (!in_array(currentUserRole(), $roles, true)) {
        header("Location: ../index.php");
        exit;
    }
}
?>

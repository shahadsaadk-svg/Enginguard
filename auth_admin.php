<?php
// auth_admin.php – protects admin-only pages
// Include this file at the TOP of any page that requires admin access

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Allow access only if user is logged in AND is an admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    // Redirect unauthorized users to login page
    header('Location: login.php');
    exit;
}

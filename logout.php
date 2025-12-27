<?php
// logout.php – ends admin session and redirects to login

session_start();

// Remove all session variables
$_SESSION = [];
session_unset();

// Destroy the session completely
session_destroy();

// Redirect user back to login page
header("Location: login.php");
exit;

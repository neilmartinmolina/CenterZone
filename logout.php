<?php
require "includes/core.php";

// Clear session data
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear security headers
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Redirect to login page
    header("Location: login.php");
exit;
?>

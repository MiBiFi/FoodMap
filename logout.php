<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
if (session_id() != "" || isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}
session_destroy();

// Redirect to the main page
header("Location: index.php");
exit;
?>
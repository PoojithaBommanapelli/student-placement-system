<?php
include '../includes/auth.php';

// Destroy all session data
session_destroy();

// Redirect to home page
header("Location: ../index.html");
exit();
?>
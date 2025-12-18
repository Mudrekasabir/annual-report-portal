<?php
session_start();
session_unset(); // clear session variables
session_destroy(); // destroy the session

// Redirect to login page
header("Location: login.php");
exit();
?>

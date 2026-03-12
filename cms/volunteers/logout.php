<?php
session_start();
// Unset all session values
$_SESSION = array();
// Destroy session
session_destroy();
// Redirect to volunteer login
header("Location: index.php?status=logged_out&v=" . time());
exit;

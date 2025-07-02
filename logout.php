<?php
require_once 'includes/config.php';
require_once 'includes/auth_functions.php';

logout();
header("Location: login.php");
exit;
?>

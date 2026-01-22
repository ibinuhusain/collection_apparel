<?php
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if (hasRole('admin')) {
    header("Location: admin/dashboard.php");
} else {
    header("Location: agent/dashboard.php");
}
exit();
?>
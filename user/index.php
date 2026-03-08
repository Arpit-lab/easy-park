<?php
// Redirect to dashboard if logged in, otherwise to login page
require_once '../includes/session.php';

if (Session::isLoggedIn()) {
    if (Session::isUser()) {
        header('Location: dashboard.php');
    } else {
        header('Location: ../admin/dashboard.php');
    }
} else {
    header('Location: ../index.php');
}
exit();
?>
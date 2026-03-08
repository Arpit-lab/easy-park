<?php
require_once '../includes/session.php';

// Log activity if user was logged in
if (Session::isLoggedIn()) {
    require_once '../includes/functions.php';
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
}

// Destroy session
Session::destroy();

// Redirect to login page
header('Location: ../index.php?success=' . urlencode('You have been logged out successfully'));
exit();
?>
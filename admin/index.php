<?php
require_once '../includes/session.php';

if (Session::isAdmin()) {
    header('Location: dashboard.php');
} else {
    header('Location: ../index.php');
}
exit();
?>
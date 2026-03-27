<?php
/**
 * Admin Account Checker
 * Run this to check your admin account status
 * Access: http://localhost:8888/check_admin.php
 */

require_once 'includes/config.php';
require_once 'includes/db_connection.php';

$conn = getDB();
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Admin Account Checker - EasyPark</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #0066cc; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; border-left: 4px solid #0066cc; }
    </style>
</head>
<body>
    <div class='container'>
        <h1 class='mb-4'><i class='fas fa-user-shield'></i> Admin Account Checker</h1>";

echo "<div class='card mb-4'>
            <div class='card-header bg-primary text-white'>
                <h5 class='mb-0'>🔍 Checking Admin Account</h5>
            </div>
            <div class='card-body'>";

$stmt = $conn->prepare("SELECT id, username, email, user_type, status, password FROM users WHERE username = ? OR email = ?");
$username = 'admin';
$email = 'admin@easypark.com';
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='alert alert-danger'><strong>❌ No admin account found!</strong></div>";
    echo "<p>You need to create an admin account first.</p>";
} else {
    $user = $result->fetch_assoc();
    echo "<div class='alert alert-success'><strong>✅ Admin account found:</strong></div>";
    echo "<ul class='list-group mb-3'>";
    echo "<li class='list-group-item'><strong>ID:</strong> " . $user['id'] . "</li>";
    echo "<li class='list-group-item'><strong>Username:</strong> " . $user['username'] . "</li>";
    echo "<li class='list-group-item'><strong>Email:</strong> " . $user['email'] . "</li>";
    echo "<li class='list-group-item'><strong>Type:</strong> " . $user['user_type'] . "</li>";
    echo "<li class='list-group-item'><strong>Status:</strong> " . $user['status'] . "</li>";
    echo "<li class='list-group-item'><strong>Password Hash:</strong> " . substr($user['password'], 0, 30) . "...</li>";
    echo "</ul>";

    // Check status
    if ($user['status'] !== 'active') {
        echo "<div class='alert alert-warning'><strong>⚠️ Account is not active!</strong> Status: " . $user['status'] . "</div>";
    }

    if ($user['user_type'] !== 'admin') {
        echo "<div class='alert alert-warning'><strong>⚠️ Account is not admin!</strong> Type: " . $user['user_type'] . "</div>";
    }
}

echo "</div></div>";

echo "<div class='card mb-4'>
            <div class='card-header bg-info text-white'>
                <h5 class='mb-0'>🔐 Testing Common Passwords</h5>
            </div>
            <div class='card-body'>";

if (isset($user)) {
    $test_passwords = ['admin', 'Admin@123', 'password', '123456', 'root', 'admin123'];

    echo "<p>Testing these common passwords:</p>";
    echo "<ul class='list-group mb-3'>";

    $found = false;
    foreach ($test_passwords as $pwd) {
        if (password_verify($pwd, $user['password'])) {
            echo "<li class='list-group-item list-group-item-success'><strong>✅ Password match found:</strong> <code>$pwd</code></li>";
            $found = true;
            break;
        } else {
            echo "<li class='list-group-item list-group-item-danger'><strong>❌ Password '$pwd' does not match</strong></li>";
        }
    }

    if (!$found) {
        echo "</ul>";
        echo "<div class='alert alert-warning'><strong>⚠️ No common passwords match!</strong></div>";
        echo "<p>The password might be different. Let's reset it to 'Admin@123'.</p>";
    }
}

echo "</div></div>";

echo "<div class='card'>
            <div class='card-header bg-success text-white'>
                <h5 class='mb-0'>🔧 Reset Admin Password</h5>
            </div>
            <div class='card-body'>";

if (isset($user)) {
    // Reset password to Admin@123
    $new_password = 'Admin@123';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $update_stmt = $conn->prepare("UPDATE users SET password = ?, status = 'active' WHERE id = ?");
    $update_stmt->bind_param("si", $hashed_password, $user['id']);

    if ($update_stmt->execute()) {
        echo "<div class='alert alert-success'><strong>✅ Password reset successful!</strong></div>";
        echo "<div class='alert alert-info'>";
        echo "<h6>New Admin Credentials:</h6>";
        echo "<pre>Username: admin
Email: admin@easypark.com
Password: Admin@123</pre>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-danger'><strong>❌ Password reset failed:</strong> " . $conn->error . "</div>";
    }
} else {
    echo "<div class='alert alert-warning'><strong>⚠️ No admin account found to reset!</strong></div>";
}

echo "</div></div>";

echo "<hr>";
echo "<div class='text-center'>";
echo "<a href='index.php' class='btn btn-primary'>Go to Login</a>";
echo "<a href='admin/dashboard.php' class='btn btn-success ms-2'>Try Admin Dashboard</a>";
echo "</div>";

echo "</div></body></html>";

$conn->close();
?>
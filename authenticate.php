<?php
// authenticate.php - Fixed version
ob_start(); // Start output buffering

require_once 'includes/session.php';
require_once 'includes/functions.php';

// Debug mode - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Validate input
if (empty($username) || empty($password)) {
    header('Location: index.php?error=' . urlencode('Please fill in all fields'));
    exit();
}

$conn = getDB();

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Prepare statement to prevent SQL injection
$stmt = $conn->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // For debugging - remove in production
    error_log("Login failed: User not found - $username");
    header('Location: index.php?error=' . urlencode('Invalid username or password'));
    exit();
}

$user = $result->fetch_assoc();

// Verify password
if (password_verify($password, $user['password'])) {
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Set remember me cookie
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
        
        // Store token in database
        $expires = date('Y-m-d H:i:s', time() + (86400 * 30));
        $token_stmt = $conn->prepare("UPDATE users SET remember_token = ?, token_expires = ? WHERE id = ?");
        $token_stmt->bind_param("ssi", $token, $expires, $user['id']);
        $token_stmt->execute();
    }
    
    // Log activity
    logActivity($user['id'], 'login', 'User logged in successfully');
    
    // Redirect based on user type
    if ($user['user_type'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }
    exit();
} else {
    // For debugging - remove in production
    error_log("Login failed: Invalid password for user - $username");
    header('Location: index.php?error=' . urlencode('Invalid username or password'));
    exit();
}
?>
<?php
// index.php - Green Theme Landing Page without Demo Credentials
ob_start();
require_once 'includes/session.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (Session::isLoggedIn()) {
    if (Session::isAdmin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasyPark - Smart Parking Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            min-height: 100vh;
            position: relative;
        }

        /* Main Container */
        .main-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .container-custom {
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        /* Hero Section */
        .hero-section {
            color: white;
            padding-right: 40px;
        }

        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .hero-section .highlight {
            background: linear-gradient(135deg, #ffd700 0%, #ffa500 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-section .lead {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.95;
            line-height: 1.6;
        }

        /* Stats Cards */
        .stats-container {
            display: flex;
            gap: 40px;
            margin: 40px 0;
        }

        .stat-item {
            text-align: left;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            display: block;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Feature Grid */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .feature-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s;
        }

        .feature-item:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.2);
        }

        .feature-item i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #ffd700;
        }

        .feature-item h5 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .feature-item p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }

        /* Login Card */
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-card .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-card .logo i {
            font-size: 3.5rem;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-card .logo h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-top: 10px;
        }

        .login-card .logo p {
            color: #666;
            margin-top: 5px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            z-index: 10;
        }

        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
            outline: none;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #999;
            cursor: pointer;
            z-index: 10;
        }

        .toggle-password:hover {
            color: #28a745;
        }

        /* Buttons */
        .btn-login {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }

        .btn-login i {
            margin-right: 8px;
        }

        /* Remember Me & Forgot Password */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
        }

        .form-check-label {
            color: #666;
            font-size: 0.95rem;
        }

        .forgot-link {
            color: #28a745;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        /* Register Link */
        .register-section {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .register-section p {
            color: #666;
            margin-bottom: 5px;
        }

        .register-section a {
            color: #28a745;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .register-section a:hover {
            text-decoration: underline;
        }

        .register-section i {
            font-size: 0.9rem;
            margin-left: 5px;
        }

        /* Footer */
        .footer {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            color: white;
            padding: 15px 0;
            text-align: center;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .footer p {
            margin: 0;
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .footer i {
            color: #ff6b6b;
            margin: 0 3px;
        }

        .footer .heart {
            animation: heartbeat 1.5s ease infinite;
        }

        .footer .green-text {
            color: #ffd700;
            font-weight: 600;
        }

        @keyframes heartbeat {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* Alerts */
        .alert {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
        }

        .alert-danger {
            background: #fee;
            color: #c33;
        }

        .alert-success {
            background: #efe;
            color: #3a3;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .hero-section {
                padding-right: 0;
                text-align: center;
                margin-bottom: 40px;
            }

            .stats-container {
                justify-content: center;
            }

            .stat-item {
                text-align: center;
            }

            .feature-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer {
                position: relative;
                margin-top: 40px;
            }
        }

        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2.5rem;
            }

            .stats-container {
                flex-wrap: wrap;
                gap: 20px;
            }

            .stat-item {
                flex: 1 1 calc(50% - 20px);
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .stat-item {
                flex: 1 1 100%;
            }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="container-custom">
            <div class="row align-items-center">
                <!-- Left Column - Hero Content -->
                <div class="col-lg-7">
                    <div class="hero-section">
                        <h1>
                            Smart Parking 
                            <span class="highlight">Management</span> 
                            System
                        </h1>
                        
                        <p class="lead">
                            Find, Book, and Manage Parking Spaces Intelligently with 
                            AI-Powered Recommendations and Real-time Availability.
                        </p>

                        <!-- Stats Section -->
                        <div class="stats-container">
                            <div class="stat-item">
                                <span class="stat-number">500+</span>
                                <span class="stat-label">Parking Spaces</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">10,000+</span>
                                <span class="stat-label">Happy Users</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">24/7</span>
                                <span class="stat-label">Support</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">98%</span>
                                <span class="stat-label">Satisfaction</span>
                            </div>
                        </div>

                        <!-- Features Grid -->
                        <div class="feature-grid">
                            <div class="feature-item">
                                <i class="fas fa-search"></i>
                                <h5>Smart Search</h5>
                                <p>Find nearest parking with AI recommendations</p>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-clock"></i>
                                <h5>Real-time</h5>
                                <p>Live availability updates</p>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-chart-line"></i>
                                <h5>Predict Demand</h5>
                                <p>ML-based forecasting</p>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h5>Anomaly Detection</h5>
                                <p>Smart alerts system</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Login Card -->
                <div class="col-lg-5">
                    <div class="login-card">
                        <div class="logo">
                            <i class="fas fa-parking"></i>
                            <h3>Welcome Back!</h3>
                            <p>Sign in to access your account</p>
                        </div>

                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($_GET['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($_GET['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form action="authenticate.php" method="POST" id="loginForm">
                            <div class="form-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="username" placeholder="Username or Email" required autofocus>
                            </div>

                            <div class="form-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" id="password" placeholder="Password" required>
                                <button type="button" class="toggle-password" onclick="togglePassword()">
                                    <i class="far fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>

                            <div class="form-options">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                    <label class="form-check-label" for="remember">
                                        Remember me
                                    </label>
                                </div>
                                <a href="forgot_password.php" class="forgot-link">
                                    Forgot Password?
                                </a>
                            </div>

                            <button type="submit" class="btn-login">
                                <i class="fas fa-sign-in-alt"></i>
                                Sign In
                            </button>
                        </form>

                        <!-- Register Link -->
                        <div class="register-section">
                            <p>Don't have an account?</p>
                            <a href="user/register.php">
                                Create Account <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>
                <i class="fas fa-parking me-2"></i>
                <strong>EasyPark</strong> - Smart Parking Management System
                <span class="mx-2">|</span>
                Created by 
                <span class="green-text">Arpit & Bhuwan</span>
                <span class="mx-2">|</span>
                All Rights Reserved © <?php echo date('Y'); ?>
            </p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const password = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (password.type === 'password') {
                password.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.querySelector('input[name="username"]').value.trim();
            const password = document.querySelector('input[name="password"]').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('⚠️ Please fill in all fields');
            }
        });

        // Add loading state to button
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.querySelector('.btn-login');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
            btn.disabled = true;
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
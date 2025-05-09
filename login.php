<?php
// Make sure no output is sent before headers
ob_start();
session_start();
require_once 'functions/db.php';
require_once 'functions/auth.php';
require_once 'functions/otp.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Getting form data
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember']) ? true : false;
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Try to authenticate user
        $authResult = authenticate($email, $password);
        
        if ($authResult['success']) {
            // Authentication successful, get user ID
            // Create a new connection instead of reusing
            $conn = new mysqli(DB_HOST, DB_USER_CORE2, DB_PASS_CORE2, DB_NAME_CORE2);
            if ($conn->connect_error) {
                $error = 'Database connection error. Please try again later.';
            } else {
                $sanitizedEmail = $email; // Email is already sanitized in authenticate()
                $query = "SELECT user_id FROM users WHERE email = '$sanitizedEmail' LIMIT 1";
                $result = $conn->query($query);
                
                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    $userId = $user['user_id'];
                    
                    // Check if OTP is enabled for this user
                    if (isOtpEnabledForUser($userId)) {
                        // Generate and send OTP
                        $otpCode = generateOtp($userId);
                        if (!$otpCode) {
                            error_log("Failed to generate OTP for user $userId");
                            $error = 'Failed to generate OTP code. Please try again.';
                            
                            // Add diagnostic link for admins
                            if (hasRole(['super_admin', 'admin'])) {
                                $error .= ' <a href="otp_diagnostics.php" class="alert-link">Troubleshoot OTP</a>';
                            }
                        } else if (!sendOtp($userId, $otpCode)) {
                            error_log("Failed to send OTP for user $userId");
                            $error = 'Failed to send OTP code. Please check your email configuration.';
                            
                            // Add diagnostic link for admins
                            if (hasRole(['super_admin', 'admin'])) {
                                $error .= ' <a href="otp_diagnostics.php" class="alert-link">Troubleshoot OTP</a>';
                            }
                        } else {
                            // Set OTP session variables
                            $_SESSION['otp_user_id'] = $userId;
                            $_SESSION['otp_email'] = $sanitizedEmail;
                            $_SESSION['otp_remember'] = $rememberMe;
                            
                            // Close this connection before redirecting
                            $conn->close();
                            
                            // Redirect to OTP verification page
                            header('Location: otp_verify.php');
                            exit;
                        }
                    } else {
                        // No OTP required, set full session directly
                        loginUser($userId, $rememberMe);
                        
                        // Close this connection
                        $conn->close();
                        
                        header('Location: index.php');
                        exit;
                    }
                } else {
                    $error = 'User not found.';
                }
                
                // Ensure connection is closed if we reach here
                $conn->close();
            }
        } else {
            // Authentication failed
            $error = $authResult['message'];
        }
    }
}

// Function to login user and set up session
function loginUser($userId, $rememberMe = false) {
    // Get user data
    $conn = new mysqli(DB_HOST, DB_USER_CORE2, DB_PASS_CORE2, DB_NAME_CORE2);
    if ($conn->connect_error) {
        error_log("Connection failed in loginUser: " . $conn->connect_error);
        return false;
    }
    
    $query = "SELECT email, role, firstname, lastname FROM users WHERE user_id = $userId LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_full_name'] = $user['firstname'] . ' ' . $user['lastname'];
        $_SESSION['user_firstname'] = $user['firstname'];
        $_SESSION['user_lastname'] = $user['lastname'];
        
        // Set remember me cookie if requested
        if ($rememberMe) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + (86400 * 30); // 30 days
            
            // Store token in database
            $tokenHash = password_hash($token, PASSWORD_DEFAULT);
            $expiresDate = date('Y-m-d H:i:s', $expires);
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $storeQuery = "INSERT INTO auth_tokens (user_id, token_hash, expires_at, ip_address, user_agent) 
                          VALUES ($userId, '$tokenHash', '$expiresDate', '$ipAddress', '$userAgent')";
            $conn->query($storeQuery);
            
            // Set cookie
            setcookie('remember_token', $token, $expires, '/', '', false, true);
            setcookie('remember_user', $userId, $expires, '/', '', false, true);
        }
        
        // Record login in history
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $loginQuery = "INSERT INTO login_history (user_id, login_time, ip_address, user_agent, used_otp) 
                      VALUES ($userId, NOW(), '$ipAddress', '$userAgent', 0)";
        $conn->query($loginQuery);
        
        $conn->close();
        return true;
    }
    
    $conn->close();
    return false;
}

// Sample credentials information - updated to match the seeded data in the database
    'super_admin' => ['email' => 'admin@moverstaxisystem.com', 'password' => 'password'],
    'dispatch' => ['email' => 'dispatch@moverstaxisystem.com', 'password' => 'password'],
    'finance' => ['email' => 'finance@moverstaxisystem.com', 'password' => 'password'],
    'driver' => ['email' => 'driver1@example.com', 'password' => 'password']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Movers Taxi System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            background-color: var(--light-color);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 15px;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            overflow: hidden;
            border: none;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 25px 20px;
            text-align: center;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 10px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .login-logo {
            width: 120px;
            height: auto;
            margin-bottom: 15px;
            border-radius: 10px;
            padding: 5px;
            background-color: white;
        }
        
        .form-control, .form-select {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }
        
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.3);
            border-color: var(--secondary-color);
        }
        
        .card-body {
            padding: 30px;
        }
        
        .card-footer {
            background-color: white;
            border-top: 1px solid #eee;
            padding: 15px;
        }
        
        .text-accent {
            color: var(--accent-color);
        }
        
        /* Sample credentials box */
        .sample-credentials {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .sample-credentials h6 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .sample-credentials .credential {
            padding: 8px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .sample-credentials .credential:last-child {
            border-bottom: none;
        }
        
        /* Back to landing page link */
        .back-to-landing {
            position: absolute;
            top: 20px;
            left: 20px;
            color: var(--dark-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-to-landing:hover {
            color: var(--secondary-color);
            transform: translateX(-5px);
        }
    </style>
</head>
<body>
    <!-- Back to landing page link -->
    <a href="landing.php" class="back-to-landing">
        <i class="fas fa-arrow-left me-2"></i> Back to Home
    </a>
    
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <!-- Updated logo path to match the root directory -->
                <img src="logo.png" alt="Movers Taxi" class="login-logo">
                <h4 class="mb-0">Movers Taxi System</h4>
                <p class="text-light mb-0">Login to your account</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label for="email" class="form-label"><i class="fas fa-envelope me-2"></i> Email</label>
                        <input type="email" class="form-control" id="email" name="email" required autofocus placeholder="Enter your email">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label"><i class="fas fa-lock me-2"></i> Password</label>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                    </div>
                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block w-100">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </button>
                </form>
                
                <div class="text-center mt-3">
                    <a href="forgot_password.php" class="text-decoration-none text-accent">Forgot Password?</a>
                </div>
                
                <!-- Sample Credentials Box (only visible in development) -->
              <!--  <div class="sample-credentials mt-4">
                    <h6><i class="fas fa-info-circle me-2"></i> Sample Login Credentials</h6>
                        <div class="credential">
                            <strong><?php echo ucwords(str_replace('_', ' ', $role)); ?>:</strong><br>
                            <small>Email: <?php echo $credentials['email']; ?></small><br>
                            <small>Password: <?php echo $credentials['password']; ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>-->
            </div>
            <div class="card-footer text-center">
                <small class="text-muted">© <?php echo date('Y'); ?> Movers Taxi System. All rights reserved.</small>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
</body>
</html> 

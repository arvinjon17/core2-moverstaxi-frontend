<?php
// Make sure no output is sent before headers
ob_start();
session_start();
require_once 'functions/db.php';
require_once 'functions/otp.php';
require_once 'functions/auth.php';

// If the user is not in OTP verification flow, redirect to login
if (!isset($_SESSION['otp_user_id']) || !isset($_SESSION['otp_email'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['otp_user_id'];
$email = $_SESSION['otp_email'];
$rememberMe = $_SESSION['otp_remember'] ?? false;
$error = '';
$success = '';

// Handle OTP verification form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otpCode = $_POST['otp'] ?? '';
    
    if (empty($otpCode)) {
        $error = 'Please enter the OTP code.';
    } else {
        if (verifyOtp($userId, $otpCode)) {
            // OTP verified successfully
            
            // Get user details
            $conn = connectToCore2DB();
            $query = "SELECT user_id, email, role, firstname, lastname FROM users WHERE user_id = $userId LIMIT 1";
            $result = $conn->query($query);
            $user = $result->fetch_assoc();
            
            // Set login session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_full_name'] = $user['firstname'] . ' ' . $user['lastname'];
            $_SESSION['user_firstname'] = $user['firstname'];
            $_SESSION['user_lastname'] = $user['lastname'];
            
            // Handle remember me if requested
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
            
            // Remove OTP session variables
            unset($_SESSION['otp_user_id']);
            unset($_SESSION['otp_email']);
            unset($_SESSION['otp_remember']);
            
            // Record login in history
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $loginQuery = "INSERT INTO login_history (user_id, login_time, ip_address, user_agent, used_otp) 
                           VALUES ($userId, NOW(), '$ipAddress', '$userAgent', 1)";
            $conn->query($loginQuery);
            
            // Log successful OTP verification in auth_logs
            $logQuery = "INSERT INTO auth_logs (user_id, email, action, ip_address, user_agent, created_at) 
                        VALUES ($userId, '$email', 'otp_success', '$ipAddress', '$userAgent', NOW())";
            $conn->query($logQuery);
            
            // Update last_login in users table
            $updateLastLoginQuery = "UPDATE users SET last_login = NOW() WHERE user_id = $userId";
            $conn->query($updateLastLoginQuery);
            
            $conn->close();
            
            // Redirect to dashboard
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid or expired OTP code. Please try again.';
            
            // Log failed OTP verification
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $conn = connectToCore2DB();
            $logQuery = "INSERT INTO auth_logs (user_id, email, action, ip_address, user_agent, created_at) 
                        VALUES ($userId, '$email', 'otp_failed', '$ipAddress', '$userAgent', NOW())";
            $conn->query($logQuery);
            $conn->close();
        }
    }
}

// Handle resend OTP request
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    $otpCode = generateOtp($userId);
    
    if ($otpCode && sendOtp($userId, $otpCode)) {
        $success = 'A new OTP code has been sent to your email.';
    } else {
        $error = 'Failed to resend OTP. Please try again.';
    }
}

// Mask email for privacy
function maskEmail($email) {
    $arr = explode('@', $email);
    if (count($arr) < 2) return $email;
    
    $name = $arr[0];
    $domain = $arr[1];
    
    if (strlen($name) <= 2) {
        $maskedName = $name;
    } else {
        $maskedName = substr($name, 0, 2) . str_repeat('*', strlen($name) - 2);
    }
    
    return $maskedName . '@' . $domain;
}

$maskedEmail = maskEmail($email);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification - Movers Taxi System</title>
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
        
        .otp-container {
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
        
        .form-control {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            text-align: center;
            letter-spacing: 8px;
            font-size: 24px;
            font-weight: 600;
        }
        
        .form-control:focus {
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
        
        .resend-link {
            color: var(--secondary-color);
            text-decoration: none;
        }
        
        .resend-link:hover {
            text-decoration: underline;
        }
        
        .countdown {
            font-weight: 600;
            color: var(--accent-color);
        }
        
        /* Back to login link */
        .back-to-login {
            color: var(--dark-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .back-to-login:hover {
            color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="otp-container">
        <div class="card">
            <div class="card-header">
                <img src="logo.png" alt="Movers Taxi" class="login-logo">
                <h4 class="mb-0">OTP Verification</h4>
                <p class="text-light mb-0">Enter the code sent to your email</p>
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
                
                <p class="text-center mb-4">
                    We've sent a one-time verification code to 
                    <br><strong><?php echo htmlspecialchars($maskedEmail); ?></strong>
                </p>
                
                <form method="POST" action="otp_verify.php">
                    <div class="mb-4">
                        <label for="otp" class="form-label text-center d-block">Verification Code</label>
                        <input type="text" class="form-control" id="otp" name="otp" required autofocus 
                               maxlength="6" autocomplete="off" placeholder="______">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block w-100">
                        <i class="fas fa-check-circle me-2"></i> Verify Code
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <div class="resend-container" id="resendContainer">
                        <span>Didn't receive the code? </span>
                        <span id="countdownSpan" class="countdown">Wait <span id="countdown">60</span>s</span>
                        <a href="otp_verify.php?resend=1" class="resend-link d-none" id="resendLink">Resend Code</a>
                    </div>
                    
                    <a href="login.php" class="back-to-login d-block mt-3">
                        <i class="fas fa-arrow-left me-2"></i> Back to Login
                    </a>
                </div>
            </div>
            <div class="card-footer text-center">
                <small class="text-muted">© <?php echo date('Y'); ?> Movers Taxi System. All rights reserved.</small>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // OTP input handling
        document.getElementById('otp').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Countdown timer for resend
        let countdown = 60;
        const countdownElement = document.getElementById('countdown');
        const countdownSpan = document.getElementById('countdownSpan');
        const resendLink = document.getElementById('resendLink');
        
        function updateCountdown() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                countdownSpan.classList.add('d-none');
                resendLink.classList.remove('d-none');
            }
        }
        
        const timer = setInterval(updateCountdown, 1000);
    </script>
</body>
</html> 
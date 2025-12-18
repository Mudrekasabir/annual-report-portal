<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') {
        header("Location: admin/dashboard.php");
        exit();
    } elseif ($role === 'teacher' || $role === 'coordinator') {
        header("Location: teacher/dashboard.php");
        exit();
    } else {
        header("Location: student/dashboard.php");
        exit();
    }
}

// Include your existing database connection
include('includes/db_connect.php');

$error = "";
$success = "";

// Handle logout message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = "You have been successfully logged out.";
}

// Handle signup success message
if (isset($_GET['signup']) && $_GET['signup'] === 'success') {
    $success = "Account created successfully! Please login with your credentials.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Rate limiting check
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = time();
    }
    
    // Reset attempts if 15 minutes have passed
    if (time() - $_SESSION['last_attempt_time'] > 900) {
        $_SESSION['login_attempts'] = 0;
    }
    
    if ($_SESSION['login_attempts'] >= 5) {
        $remaining_time = 900 - (time() - $_SESSION['last_attempt_time']);
        $minutes = ceil($remaining_time / 60);
        $error = "⚠️ Too many failed login attempts. Please try again in $minutes minute(s).";
    } elseif (empty($identifier) || empty($password)) {
        $error = "⚠️ Please fill in all fields.";
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt_time'] = time();
    } else {
        try {
            // Check if identifier is email
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $identifier);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if ($user && password_verify($password, $user['password'])) {
                // Check if user is approved
                if (in_array($user['role'], ['teacher', 'coordinator']) && $user['approval_status'] !== 'approved') {
                    $error = "⏳ Your account is pending admin approval. Please contact the administrator.";
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                } else {
                    // ✅ CRITICAL: Check for active coordinator status
                    $actual_role = $user['role'];
                    
                    // If user is teacher or coordinator, check for active coordinator assignment
                    if (in_array($user['role'], ['teacher', 'coordinator'])) {
                        $stmt_coord = $conn->prepare("
                            SELECT id, permissions, access_start, access_end 
                            FROM coordinators 
                            WHERE user_id = ? 
                            AND status = 'active'
                            AND NOW() BETWEEN access_start AND access_end
                            LIMIT 1
                        ");
                        
                        if ($stmt_coord) {
                            $stmt_coord->bind_param("i", $user['id']);
                            $stmt_coord->execute();
                            $coord_result = $stmt_coord->get_result();
                            
                            if ($coord_result->num_rows > 0) {
                                // Active coordinator found
                                $actual_role = 'coordinator';
                                
                                // Update database role if needed
                                if ($user['role'] !== 'coordinator') {
                                    $update_role = $conn->prepare("UPDATE users SET role = 'coordinator' WHERE id = ?");
                                    if ($update_role) {
                                        $update_role->bind_param("i", $user['id']);
                                        $update_role->execute();
                                        $update_role->close();
                                    }
                                }
                            } else {
                                // No active coordinator - must be teacher
                                $actual_role = 'teacher';
                                
                                // Update database role if it says coordinator but isn't active
                                if ($user['role'] === 'coordinator') {
                                    $update_role = $conn->prepare("UPDATE users SET role = 'teacher' WHERE id = ?");
                                    if ($update_role) {
                                        $update_role->bind_param("i", $user['id']);
                                        $update_role->execute();
                                        $update_role->close();
                                    }
                                }
                            }
                            $stmt_coord->close();
                        }
                    }
                    
                    // Successful login
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $actual_role; // Use the determined role
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['login_time'] = time();
                    
                    // Reset login attempts
                    $_SESSION['login_attempts'] = 0;
                    
                    // Update last login timestamp
                    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->bind_param("i", $user['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    // ✅ CRITICAL: Redirect based on actual role
                    if ($actual_role === 'admin') {
                        header("Location: admin/dashboard.php");
                        exit();
                    } elseif ($actual_role === 'teacher' || $actual_role === 'coordinator') {
                        header("Location: teacher/dashboard.php");
                        exit();
                    } else {
                        header("Location: student/dashboard.php");
                        exit();
                    }
                }
            } else {
                $error = "❌ Invalid credentials. Please check your Email and password.";
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
            }
        } catch (Exception $e) {
            $error = "❌ Database error. Please try again later.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Annual Report Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --text-main: #2c3e50;
            --text-muted: #7f8c8d;
            --bg-accent: #f8f9ff;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Poppins", sans-serif;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            animation: slideUp 0.6s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .logo-icon i {
            font-size: 40px;
            color: white;
        }
        
        .login-card h3 {
            color: var(--text-main);
            font-weight: 700;
            text-align: center;
            margin-bottom: 8px;
        }
        
        .login-card .subtitle {
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group-text {
            background: var(--bg-accent);
            border: 2px solid #e9ecef;
            border-right: none;
            color: var(--primary);
            padding: 12px 15px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-left: none;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .input-group:focus-within .input-group-text {
            border-color: var(--primary);
            background: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            padding: 14px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px 15px;
            margin-bottom: 20px;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 20px;
        }
        
        .forgot-password a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .signup-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e9ecef;
        }
        
        .signup-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .signup-link a:hover {
            text-decoration: underline;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-muted);
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        .input-wrapper {
            position: relative;
        }
        
        @media (max-width: 576px) {
            .login-card {
                padding: 30px 20px;
            }
            
            .logo-icon {
                width: 70px;
                height: 70px;
            }
            
            .logo-icon i {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="logo-container">
            <div class="logo-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <h3>Welcome Back</h3>
            <p class="subtitle">Annual Report Portal</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-at"></i>
                    </span>
                    <input 
                        type="email" 
                        name="identifier" 
                        id="identifier"
                        class="form-control" 
                        placeholder="Enter your email"
                        value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                        required
                        autocomplete="username"
                    >
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-lock"></i> Password
                </label>
                <div class="input-wrapper">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-key"></i>
                        </span>
                        <input 
                            type="password" 
                            name="password" 
                            id="password"
                            class="form-control" 
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                    </div>
                    <span class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </span>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </div>

            <div class="signup-link">
                Don't have an account? <a href="signup.php">Create one now</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle password visibility
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Auto-hide success messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.transition = 'opacity 0.5s ease';
            successAlert.style.opacity = '0';
            setTimeout(() => {
                successAlert.remove();
            }, 500);
        }, 5000);
    }
});

// Form validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const identifier = document.getElementById('identifier').value.trim();
    const password = document.getElementById('password').value;
    
    if (!identifier || !password) {
        e.preventDefault();
        alert('Please fill in all fields');
    }
});

// Prevent multiple form submissions
let isSubmitting = false;
document.getElementById('loginForm').addEventListener('submit', function(e) {
    if (isSubmitting) {
        e.preventDefault();
        return false;
    }
    isSubmitting = true;
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
    
    // Re-enable after 3 seconds in case of error
    setTimeout(() => {
        isSubmitting = false;
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
    }, 3000);
});
</script>

</body>
</html>
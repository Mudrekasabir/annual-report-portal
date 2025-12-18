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
    } elseif ($role === 'teacher') {
        header("Location: teacher/dashboard.php");
    } else {
        header("Location: student/dashboard.php");
    }
    exit();
}

// Include your existing database connection
require_once __DIR__ . '/includes/db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = strtolower(trim($_POST['role'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $error = "⚠️ Please fill out all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "❌ Invalid email address.";
    } elseif (strlen($password) < 8) {
        $error = "❌ Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "❌ Passwords do not match.";
    } elseif (!in_array($role, ['student', 'teacher'])) {
        $error = "❌ Invalid role selected.";
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "❌ Password must contain at least one uppercase letter, one lowercase letter, and one number.";
    } else {
        try {
            // Check for duplicate Email using prepared statement
            if (isset($conn) && $conn instanceof mysqli) {
                // Using mysqli
                $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                if (!$check) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $check->bind_param("s", $email);
                $check->execute();
                $result = $check->get_result();
                $exists = $result->fetch_assoc();
                $check->close();
            } elseif (isset($conn) && $conn instanceof PDO) {
                // Using PDO
                $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $check->execute([$email]);
                $exists = $check->fetch(PDO::FETCH_ASSOC);
            } else {
                throw new Exception("Invalid database connection");
            }
            
            if ($exists) {
                $error = "⚠️ A user with this email already exists.";
            } else {
                // Teachers require admin approval; students auto-approved
                $approval_status = ($role === 'teacher') ? 'pending' : 'approved';
                $password_hash = password_hash($password, PASSWORD_BCRYPT);

                // Check which columns exist in the users table
                $has_approval_status = false;
                $has_created_at = false;
                
                if (isset($conn) && $conn instanceof mysqli) {
                    // mysqli column detection
                    $columns_result = $conn->query("SHOW COLUMNS FROM users");
                    if ($columns_result) {
                        while ($col = $columns_result->fetch_assoc()) {
                            if ($col['Field'] === 'approval_status') {
                                $has_approval_status = true;
                            }
                            if ($col['Field'] === 'created_at') {
                                $has_created_at = true;
                            }
                        }
                    }
                } elseif (isset($conn) && $conn instanceof PDO) {
                    // PDO column detection
                    $columns_result = $conn->query("SHOW COLUMNS FROM users");
                    if ($columns_result) {
                        while ($col = $columns_result->fetch(PDO::FETCH_ASSOC)) {
                            if ($col['Field'] === 'approval_status') {
                                $has_approval_status = true;
                            }
                            if ($col['Field'] === 'created_at') {
                                $has_created_at = true;
                            }
                        }
                    }
                }

                // Build dynamic INSERT query based on available columns
                $columns = ['name', 'email', 'password', 'role'];
                $values = [$name, $email, $password_hash, $role];
                $placeholders = ['?', '?', '?', '?'];
                $types = 'ssss';
                
                if ($has_approval_status) {
                    $columns[] = 'approval_status';
                    $values[] = $approval_status;
                    $placeholders[] = '?';
                    $types .= 's';
                }
                
                if ($has_created_at) {
                    $columns[] = 'created_at';
                    $placeholders[] = 'NOW()';
                }
                
                $sql = "INSERT INTO users (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

                if (isset($conn) && $conn instanceof mysqli) {
                    // Using mysqli
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    
                    // Bind parameters dynamically
                    $bind_params = [$types];
                    foreach ($values as $key => $value) {
                        $bind_params[] = &$values[$key];
                    }
                    call_user_func_array([$stmt, 'bind_param'], $bind_params);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Execute failed: " . $stmt->error);
                    }
                    
                    $user_id = $conn->insert_id;
                    $stmt->close();
                } else {
                    // Using PDO
                    $pdo_columns = ['name', 'email', 'password', 'role'];
                    $pdo_values = [$name, $email, $password_hash, $role];
                    
                    if ($has_approval_status) {
                        $pdo_columns[] = 'approval_status';
                        $pdo_values[] = $approval_status;
                    }
                    
                    if ($has_created_at) {
                        $pdo_columns[] = 'created_at';
                        $pdo_values[] = date('Y-m-d H:i:s');
                    }
                    
                    $placeholders = str_repeat('?,', count($pdo_columns) - 1) . '?';
                    $pdo_sql = "INSERT INTO users (" . implode(', ', $pdo_columns) . ") VALUES (" . $placeholders . ")";
                    
                    $stmt = $conn->prepare($pdo_sql);
                    $stmt->execute($pdo_values);
                    $user_id = $conn->lastInsertId();
                }

                // Generate success message
                if ($role === 'teacher') {
                    if ($has_approval_status) {
                        $success = "✅ Your teacher account has been created successfully!<br>
                                    <strong>Your ID:</strong> <span class='user-id-badge'>#" . htmlspecialchars($user_id) . "</span><br>
                                    <small class='text-warning'>⏳ Your account is pending admin approval. Please save your ID for login.</small>";
                    } else {
                        $success = "✅ Your teacher account has been created successfully!<br>
                                    <strong>Your ID:</strong> <span class='user-id-badge'>#" . htmlspecialchars($user_id) . "</span><br>
                                    <small class='text-success'>✓ You can now log in with your credentials.</small>";
                    }
                } else {
                    $success = "🎉 Account created successfully!<br>
                                <strong>Your ID:</strong> <span class='user-id-badge'>#" . htmlspecialchars($user_id) . "</span><br>
                                <small class='text-success'>✓ You can now log in with your credentials.</small>";
                }
            }
        } catch (Exception $e) {
            $error = "❌ Database error. Please try again later.";
            error_log("Signup error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
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
            padding: 30px 20px;
        }
        
        .signup-container {
            width: 100%;
            max-width: 520px;
        }
        
        .signup-card {
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
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 20px;
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
            font-size: 35px;
            color: white;
        }
        
        .signup-card h3 {
            color: var(--text-main);
            font-weight: 700;
            text-align: center;
            margin-bottom: 8px;
        }
        
        .signup-card .subtitle {
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
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 0.95rem;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            z-index: 10;
            transition: color 0.3s;
        }
        
        .input-icon:hover {
            color: var(--primary);
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .password-strength {
            height: 4px;
            border-radius: 3px;
            margin-top: 8px;
            transition: all 0.3s;
            width: 0;
        }
        
        .password-requirements {
            font-size: 0.75rem;
            margin-top: 8px;
            color: var(--text-muted);
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }
        
        .requirement.met {
            color: var(--success);
        }
        
        .requirement i {
            font-size: 0.7rem;
        }
        
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .role-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            position: relative;
        }
        
        .role-card:hover {
            border-color: var(--primary);
            background: var(--bg-accent);
            transform: translateY(-2px);
        }
        
        .role-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .role-card input[type="radio"]:checked + .role-content {
            color: var(--primary);
        }
        
        .role-card input[type="radio"]:checked ~ .checkmark {
            display: block;
        }
        
        .role-card.selected {
            border-color: var(--primary);
            background: var(--bg-accent);
        }
        
        .role-icon {
            width: 50px;
            height: 50px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 24px;
            color: var(--text-muted);
            transition: all 0.3s;
        }
        
        .role-card.selected .role-icon {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }
        
        .role-card strong {
            display: block;
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .role-card small {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .role-card.selected small {
            color: var(--primary);
        }
        
        .checkmark {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 24px;
            height: 24px;
            background: var(--success);
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
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
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px;
            margin-bottom: 20px;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .user-id-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1.1rem;
            margin: 10px 0;
            letter-spacing: 1px;
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e9ecef;
        }
        
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .approval-notice {
            background: #fef3c7;
            border-left: 4px solid var(--warning);
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-top: 15px;
            display: none;
        }
        
        .approval-notice.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @media (max-width: 576px) {
            .signup-card {
                padding: 30px 20px;
            }
            
            .role-selector {
                grid-template-columns: 1fr;
            }
            
            .logo-icon {
                width: 70px;
                height: 70px;
            }
            
            .logo-icon i {
                font-size: 30px;
            }
        }
    </style>
</head>
<body>

<div class="signup-container">
    <div class="signup-card">
        <div class="logo-container">
            <div class="logo-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h3>Create Your Account</h3>
            <p class="subtitle">Join our annual report portal</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success text-center">
                <?= $success ?>
                <div class="mt-3">
                    <a href="login.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($success)): ?>
        <form method="POST" action="" id="signupForm">
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-user"></i> Full Name
                </label>
                <input 
                    type="text" 
                    name="name" 
                    class="form-control" 
                    placeholder="Enter your full name"
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                    maxlength="100"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
                <input 
                    type="email" 
                    name="email" 
                    class="form-control" 
                    placeholder="your.email@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    maxlength="150"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-lock"></i> Password
                </label>
                <div class="input-wrapper">
                    <input 
                        type="password" 
                        name="password" 
                        id="password"
                        class="form-control" 
                        placeholder="Create a strong password"
                        required
                        minlength="8"
                        maxlength="255"
                        autocomplete="new-password"
                    >
                    <span class="input-icon" onclick="togglePassword('password', 'toggleIcon1')" role="button" tabindex="0" aria-label="Toggle password visibility">
                        <i class="fas fa-eye" id="toggleIcon1"></i>
                    </span>
                </div>
                <div class="password-strength" id="passwordStrength"></div>
                <div class="password-requirements">
                    <div class="requirement" id="req-length">
                        <i class="fas fa-circle"></i>
                        <span>At least 8 characters</span>
                    </div>
                    <div class="requirement" id="req-upper">
                        <i class="fas fa-circle"></i>
                        <span>One uppercase letter</span>
                    </div>
                    <div class="requirement" id="req-lower">
                        <i class="fas fa-circle"></i>
                        <span>One lowercase letter</span>
                    </div>
                    <div class="requirement" id="req-number">
                        <i class="fas fa-circle"></i>
                        <span>One number</span>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-lock"></i> Confirm Password
                </label>
                <div class="input-wrapper">
                    <input 
                        type="password" 
                        name="confirm_password" 
                        id="confirmPassword"
                        class="form-control" 
                        placeholder="Re-enter your password"
                        required
                        maxlength="255"
                        autocomplete="new-password"
                    >
                    <span class="input-icon" onclick="togglePassword('confirmPassword', 'toggleIcon2')" role="button" tabindex="0" aria-label="Toggle confirm password visibility">
                        <i class="fas fa-eye" id="toggleIcon2"></i>
                    </span>
                </div>
                <small id="passwordMatch" class="text-muted"></small>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-user-tag"></i> Select Your Role
                </label>
                <div class="role-selector">
                    <label class="role-card" data-role="student">
                        <input type="radio" name="role" value="student" <?= (isset($_POST['role']) && $_POST['role'] === 'student') ? 'checked' : '' ?> required>
                        <div class="role-content">
                            <div class="role-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <strong>Student</strong>
                            <small>Access courses &amp; grades</small>
                        </div>
                        <div class="checkmark">
                            <i class="fas fa-check"></i>
                        </div>
                    </label>

                    <label class="role-card" data-role="teacher">
                        <input type="radio" name="role" value="teacher" <?= (isset($_POST['role']) && $_POST['role'] === 'teacher') ? 'checked' : '' ?> required>
                        <div class="role-content">
                            <div class="role-icon">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <strong>Teacher</strong>
                            <small>Manage classes</small>
                        </div>
                        <div class="checkmark">
                            <i class="fas fa-check"></i>
                        </div>
                    </label>
                </div>
                
                <div class="approval-notice" id="approvalNotice">
                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> Teacher accounts require admin approval before you can log in.
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </div>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle password visibility
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (input && icon) {
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
}

// Password strength and requirements
const password = document.getElementById('password');
const strengthBar = document.getElementById('passwordStrength');

if (password && strengthBar) {
    password.addEventListener('input', function() {
        const value = this.value;
        let strength = 0;
        
        // Check requirements
        const hasLength = value.length >= 8;
        const hasUpper = /[A-Z]/.test(value);
        const hasLower = /[a-z]/.test(value);
        const hasNumber = /[0-9]/.test(value);
        
        // Update requirement indicators
        updateRequirement('req-length', hasLength);
        updateRequirement('req-upper', hasUpper);
        updateRequirement('req-lower', hasLower);
        updateRequirement('req-number', hasNumber);
        
        // Calculate strength
        if (hasLength) strength += 25;
        if (hasUpper) strength += 25;
        if (hasLower) strength += 25;
        if (hasNumber) strength += 25;
        
        // Update strength bar
        strengthBar.style.width = strength + '%';
        
        if (strength <= 25) {
            strengthBar.style.backgroundColor = '#ef4444';
        } else if (strength <= 50) {
            strengthBar.style.backgroundColor = '#f59e0b';
        } else if (strength <= 75) {
            strengthBar.style.backgroundColor = '#3b82f6';
        } else {
            strengthBar.style.backgroundColor = '#10b981';
        }
    });
}

function updateRequirement(id, met) {
    const element = document.getElementById(id);
    if (element) {
        const icon = element.querySelector('i');
        if (met) {
            element.classList.add('met');
            if (icon) {
                icon.classList.remove('fa-circle');
                icon.classList.add('fa-check-circle');
            }
        } else {
            element.classList.remove('met');
            if (icon) {
                icon.classList.remove('fa-check-circle');
                icon.classList.add('fa-circle');
            }
        }
    }
}

// Password match indicator
const confirmPassword = document.getElementById('confirmPassword');
const matchIndicator = document.getElementById('passwordMatch');

if (confirmPassword && matchIndicator && password) {
    confirmPassword.addEventListener('input', function() {
        const pwd = password.value;
        const confirmPwd = this.value;
        
        if (confirmPwd === '') {
            matchIndicator.textContent = '';
            matchIndicator.className = 'text-muted';
        } else if (pwd === confirmPwd) {
            matchIndicator.textContent = '✓ Passwords match';
            matchIndicator.className = 'text-success';
        } else {
            matchIndicator.textContent = '✗ Passwords do not match';
            matchIndicator.className = 'text-danger';
        }
    });
}

// Role card selection
document.querySelectorAll('.role-card').forEach(card => {
    card.addEventListener('click', function() {
        const radio = this.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
            
            // Update visual state
            document.querySelectorAll('.role-card').forEach(c => {
                c.classList.remove('selected');
            });
            this.classList.add('selected');
            
            // Show approval notice for teachers
            const approvalNotice = document.getElementById('approvalNotice');
            if (approvalNotice && radio.value === 'teacher') {
                approvalNotice.classList.add('show');
            } else if (approvalNotice) {
                approvalNotice.classList.remove('show');
            }
        }
    });
});

// Initialize selected role on page load
document.addEventListener('DOMContentLoaded', function() {
    const checkedRadio = document.querySelector('input[name="role"]:checked');
    if (checkedRadio) {
        const card = checkedRadio.closest('.role-card');
        if (card) {
            card.classList.add('selected');
            
            const approvalNotice = document.getElementById('approvalNotice');
            if (checkedRadio.value === 'teacher' && approvalNotice) {
                approvalNotice.classList.add('show');
            }
        }
    }
});

// Form validation and submission
const signupForm = document.getElementById('signupForm');
if (signupForm) {
    signupForm.addEventListener('submit', function(e) {
        const pwd = password ? password.value : '';
        const confirmPwd = confirmPassword ? confirmPassword.value : '';
        
        // Check password requirements
        if (pwd.length < 8 || !/[A-Z]/.test(pwd) || !/[a-z]/.test(pwd) || !/[0-9]/.test(pwd)) {
            e.preventDefault();
            alert('Please meet all password requirements');
            return false;
        }
        
        if (pwd !== confirmPwd) {
            e.preventDefault();
            alert('Passwords do not match');
            return false;
        }
        
        // Disable button and show loading
        const btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
        }
    });
}

// Add keyboard accessibility for toggle icons
document.querySelectorAll('.input-icon').forEach(icon => {
    icon.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.click();
        }
    });
});
</script>

</body>
</html>
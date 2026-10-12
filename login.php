<?php
require_once __DIR__ . '/includes/db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php';
    header("Location: $redirect");
    exit();
}

$errors = [];
$email_or_username = '';
$role = isset($_GET['role']) && $_GET['role'] === 'admin' ? 'admin' : 'student';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_username = trim($_POST['email_or_username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student'; // 'student' or 'admin'
    
    // Validation
    if (empty($email_or_username)) {
        $errors[] = $role === 'admin' ? "Username is required." : "Email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    
    if (empty($errors)) {
        try {
            if ($role === 'admin') {
                // Admin Authentication
                $stmt = $conn->prepare("SELECT * FROM admin WHERE username = :username");
                $stmt->execute([':username' => $email_or_username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    // Start Admin Session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = 'admin';
                    $_SESSION['name'] = 'System Admin (' . $user['username'] . ')';
                    $_SESSION['photo'] = ''; // Admins don't have a profile photo in the schema
                    
                    $_SESSION['success_msg'] = "Welcome back, Admin!";
                    header("Location: admin/dashboard.php");
                    exit();
                } else {
                    $errors[] = "Invalid administrator username or password.";
                }
            } else {
                // Student Authentication
                $stmt = $conn->prepare("SELECT * FROM students WHERE email = :email");
                $stmt->execute([':email' => $email_or_username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] === 'inactive') {
                        $errors[] = "Your account has been deactivated. Please contact the administrator.";
                    } else {
                        // Start Student Session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role'] = 'student';
                        $_SESSION['student_id'] = $user['student_id'];
                        $_SESSION['name'] = $user['full_name'];
                        $_SESSION['photo'] = $user['photo'];
                        
                        $_SESSION['success_msg'] = "Welcome back, " . htmlspecialchars($user['full_name']) . "!";
                        header("Location: student/dashboard.php");
                        exit();
                    }
                } else {
                    $errors[] = "Invalid student email or password.";
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Login - SMS Portal</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h2>SMS Portal Login</h2>
                <p>Access your Student Management System dashboard</p>
            </div>
            
            <!-- Auth Navigation Tabs -->
            <div class="auth-tabs">
                <div class="auth-tab <?php echo $role === 'student' ? 'active' : ''; ?>" id="student-tab" onclick="switchTab('student')">
                    <i class="fas fa-user-graduate"></i> Student Login
                </div>
                <div class="auth-tab <?php echo $role === 'admin' ? 'active' : ''; ?>" id="admin-tab" onclick="switchTab('admin')">
                    <i class="fas fa-user-shield"></i> Admin Login
                </div>
            </div>
            
            <div class="auth-body">
                <!-- Session messages -->
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Errors display -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" style="display: block;">
                        <ul style="padding-left: 1.25rem; margin: 0;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="login.php" method="POST" id="loginForm">
                    <!-- Hidden field to send target role -->
                    <input type="hidden" name="role" id="loginRole" value="<?php echo htmlspecialchars($role); ?>">
                    
                    <!-- Dynamic Username/Email Label -->
                    <div class="auth-form-group">
                        <label for="email_or_username" id="inputLabel">
                            <?php echo $role === 'admin' ? 'Admin Username' : 'Student Email Address'; ?>
                        </label>
                        <div class="auth-input-group">
                            <i class="fas <?php echo $role === 'admin' ? 'fa-user' : 'fa-envelope'; ?>" id="inputIcon"></i>
                            <input type="text" name="email_or_username" id="email_or_username" class="auth-input" 
                                   placeholder="<?php echo $role === 'admin' ? 'Enter admin username' : 'johndoe@example.com'; ?>" 
                                   value="<?php echo htmlspecialchars($email_or_username); ?>" required>
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div class="auth-form-group">
                        <label for="password">Password</label>
                        <div class="auth-input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="password" class="auth-input" placeholder="••••••••" required>
                        </div>
                    </div>
                    
                    <div class="auth-helper">
                        <label class="auth-checkbox">
                            <input type="checkbox" name="remember" style="cursor: pointer;"> Remember me
                        </label>
                        <a href="forgot-password.php" id="forgotPasswordLink" style="font-size: 0.875rem; <?php echo $role === 'admin' ? 'display:none;' : ''; ?>">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Authenticate
                    </button>
                    
                    <!-- Register Footer -->
                    <div class="auth-footer" id="registerFooter" style="<?php echo $role === 'admin' ? 'display:none;' : ''; ?>">
                        Don't have an account yet? <a href="register.php">Register now</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab Toggling Logic Script -->
    <script>
        function switchTab(roleType) {
            // Update hidden role input
            document.getElementById('loginRole').value = roleType;
            
            // Toggle tab UI classes
            const studentTab = document.getElementById('student-tab');
            const adminTab = document.getElementById('admin-tab');
            
            const inputLabel = document.getElementById('inputLabel');
            const inputIcon = document.getElementById('inputIcon');
            const inputField = document.getElementById('email_or_username');
            
            const forgotLink = document.getElementById('forgotPasswordLink');
            const registerFooter = document.getElementById('registerFooter');
            
            if (roleType === 'admin') {
                studentTab.classList.remove('active');
                adminTab.classList.add('active');
                
                inputLabel.innerText = 'Admin Username';
                inputIcon.className = 'fas fa-user';
                inputField.placeholder = 'Enter admin username';
                
                forgotLink.style.display = 'none';
                registerFooter.style.display = 'none';
            } else {
                adminTab.classList.remove('active');
                studentTab.classList.add('active');
                
                inputLabel.innerText = 'Student Email Address';
                inputIcon.className = 'fas fa-envelope';
                inputField.placeholder = 'johndoe@example.com';
                
                forgotLink.style.display = '';
                registerFooter.style.display = '';
            }
        }
        
        // Ensure state is correct on load (e.g. if errors occur)
        window.addEventListener('DOMContentLoaded', () => {
            switchTab(document.getElementById('loginRole').value);
        });
    </script>
</body>
</html>

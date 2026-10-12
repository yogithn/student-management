<?php
require_once __DIR__ . '/includes/db_connect.php';

$errors = [];
$success = false;
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$student = null;

if (empty($token)) {
    $errors[] = "A valid password reset token is required.";
} else {
    try {
        // Query to check if token exists and is NOT expired
        $stmt = $conn->prepare("SELECT * FROM students WHERE reset_token = :token AND reset_token_expiry > NOW()");
        $stmt->execute([':token' => $token]);
        $student = $stmt->fetch();
        
        if (!$student) {
            $errors[] = "The password reset token is invalid or has expired. Please request a new link.";
        }
    } catch (PDOException $e) {
        $errors[] = "Database Error: " . $e->getMessage();
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($errors)) {
        try {
            // Hash new password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Update password and clear token
            $update_stmt = $conn->prepare("UPDATE students SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE id = :id");
            $update_stmt->execute([
                ':password' => $password_hash,
                ':id' => $student['id']
            ]);
            
            $_SESSION['success_msg'] = "Password reset successfully! You can now log in with your new password.";
            header("Location: login.php?role=student");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Failed to update password: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - SMS Portal</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="auth-header">
                <div class="auth-logo" style="background: linear-gradient(135deg, var(--success), var(--secondary)); box-shadow: 0 8px 16px -4px rgba(16, 185, 129, 0.4);">
                    <i class="fas fa-lock-open"></i>
                </div>
                <h2>Reset Password</h2>
                <p>Create a secure new password for your student account</p>
            </div>
            
            <div class="auth-body">
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
                
                <?php if ($student): ?>
                    <form action="reset-password.php" method="POST">
                        <!-- Hidden token field -->
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div class="auth-form-group">
                            <label for="password">New Password</label>
                            <div class="auth-input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" id="password" class="auth-input" placeholder="••••••••" required>
                            </div>
                        </div>
                        
                        <div class="auth-form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="auth-input-group">
                                <i class="fas fa-shield-alt"></i>
                                <input type="password" name="confirm_password" id="confirm_password" class="auth-input" placeholder="••••••••" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-key"></i> Update Password
                        </button>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="forgot-password.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Request New Link
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

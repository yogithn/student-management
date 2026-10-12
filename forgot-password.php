<?php
require_once __DIR__ . '/includes/db_connect.php';

$errors = [];
$success_message = '';
$simulated_email_body = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($errors)) {
        try {
            // Check if email exists in students
            $stmt = $conn->prepare("SELECT id, full_name FROM students WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $student = $stmt->fetch();
            
            if ($student) {
                // Generate secure random token
                $token = bin2hex(random_bytes(32));
                // Set expiry time (1 hour from now)
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store in database
                $update_stmt = $conn->prepare("UPDATE students SET reset_token = :token, reset_token_expiry = :expiry WHERE id = :id");
                $update_stmt->execute([
                    ':token' => $token,
                    ':expiry' => $expiry,
                    ':id' => $student['id']
                ]);
                
                // Construct the reset URL
                // Support dynamically determining HTTP vs HTTPS and Host
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $host = $_SERVER['HTTP_HOST'];
                // Get path of current file directory
                $current_dir = dirname($_SERVER['PHP_SELF']);
                $reset_link = $protocol . $host . $current_dir . '/reset-password.php?token=' . $token;
                
                $success_message = "Password reset link has been generated successfully.";
                
                // Construct simulated email message body
                $simulated_email_body = "
                    <div class='simulated-email'>
                        <div class='email-header'>
                            <strong>From:</strong> support@smsportal.com<br>
                            <strong>To:</strong> " . htmlspecialchars($email) . "<br>
                            <strong>Subject:</strong> Password Reset Request - SMS Portal
                        </div>
                        <div class='email-body'>
                            <p>Hi " . htmlspecialchars($student['full_name']) . ",</p>
                            <p>We received a request to reset your password. You can reset it by clicking the button below:</p>
                            <p style='text-align: center; margin: 1.5rem 0;'>
                                <a href='" . $reset_link . "' class='btn btn-primary' style='display: inline-block; width: auto; text-decoration: none;'>Reset Password</a>
                            </p>
                            <p>If you did not request a password reset, please ignore this email. The link is valid for 1 hour.</p>
                            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 1.5rem 0;'>
                            <p style='font-size: 0.8rem; color: #64748b;'>If the button above does not work, copy and paste the following link into your browser:</p>
                            <code style='word-break: break-all; font-size: 0.8rem; color: var(--primary);'>" . $reset_link . "</code>
                        </div>
                    </div>
                ";
            } else {
                // To prevent email enumeration, we can say "If the email is registered, a link has been sent"
                // But in a helper/demo application, it's friendlier to state if the email isn't registered.
                $errors[] = "No student account is registered with that email address.";
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
    <title>Forgot Password - SMS Portal</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .simulated-email {
            margin-top: 1.5rem;
            border: 2px dashed #3b82f6;
            border-radius: var(--radius-lg);
            background-color: #eff6ff;
            overflow: hidden;
            animation: fadeIn 0.4s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .email-header {
            background-color: #dbeafe;
            padding: 0.75rem 1.25rem;
            font-size: 0.85rem;
            color: #1e3a8a;
            border-bottom: 1px solid #bfdbfe;
        }
        
        .email-body {
            padding: 1.25rem;
            font-size: 0.9rem;
            color: #1e293b;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container" style="max-width: 520px;">
            <div class="auth-header">
                <div class="auth-logo" style="background: linear-gradient(135deg, var(--warning), var(--danger)); box-shadow: 0 8px 16px -4px rgba(239, 68, 68, 0.4);">
                    <i class="fas fa-key"></i>
                </div>
                <h2>Recover Password</h2>
                <p>Enter your registered email address to reset your password</p>
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
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                    
                    <!-- Simulated email delivery display box -->
                    <?php echo $simulated_email_body; ?>
                    
                    <div style="text-align: center; margin-top: 1.5rem;">
                        <a href="login.php" class="btn btn-secondary" style="width: auto; padding: 0.5rem 1.25rem;">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </div>
                <?php else: ?>
                    <form action="forgot-password.php" method="POST">
                        <div class="auth-form-group">
                            <label for="email">Email Address</label>
                            <div class="auth-input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" id="email" class="auth-input" placeholder="johndoe@example.com" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-paper-plane"></i> Send Password Reset Link
                        </button>
                        
                        <div class="auth-footer">
                            Remember your password? <a href="login.php?role=student">Back to Login</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

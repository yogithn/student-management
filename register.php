<?php
require_once __DIR__ . '/includes/db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php';
    header("Location: $redirect");
    exit();
}

$errors = [];
$full_name = '';
$student_id = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitization
    $full_name = trim($_POST['full_name'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validations
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($student_id)) $errors[] = "Student ID is required.";
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($phone)) $errors[] = "Phone number is required.";
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // Check if profile photo was uploaded
    $photo_filename = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file_error = $_FILES['photo']['error'];
        if ($file_error === UPLOAD_ERR_OK) {
            $file_size = $_FILES['photo']['size'];
            $file_tmp = $_FILES['photo']['tmp_name'];
            $file_name = $_FILES['photo']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            
            // Verify extension and mime type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            if (!in_array($file_ext, $allowed_extensions) || !in_array($mime_type, $allowed_types)) {
                $errors[] = "Only JPG, JPEG, PNG, and GIF image files are allowed.";
            }
            
            // Check size (2MB max)
            if ($file_size > 2 * 1024 * 1024) {
                $errors[] = "Profile photo must be less than 2MB.";
            }
            
            if (empty($errors)) {
                // Ensure uploads directory exists
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Create unique filename
                $photo_filename = uniqid('student_', true) . '.' . $file_ext;
                $dest_path = $upload_dir . $photo_filename;
                
                if (!move_uploaded_file($file_tmp, $dest_path)) {
                    $errors[] = "Failed to upload profile photo. Please try again.";
                }
            }
        } else {
            $errors[] = "File upload error code: " . $file_error;
        }
    }
    
    // Database validations (Unique Email and Student ID)
    if (empty($errors)) {
        try {
            // Check Student ID
            $stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE student_id = :student_id");
            $stmt->execute([':student_id' => $student_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Student ID is already registered.";
            }
            
            // Check Email
            $stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Email is already registered.";
            }
            
            // Insert Student Record if no duplication errors
            if (empty($errors)) {
                // Hash Password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO students (student_id, full_name, email, phone, password, photo, status) 
                        VALUES (:student_id, :full_name, :email, :phone, :password, :photo, 'registered')";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':student_id' => $student_id,
                    ':full_name' => $full_name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':password' => $password_hash,
                    ':photo' => $photo_filename
                ]);
                
                $_SESSION['success_msg'] = "Registration successful! You can now log in.";
                header("Location: login.php?role=student");
                exit();
            }
        } catch (PDOException $e) {
            $errors[] = "Database Error: " . $e->getMessage();
            // Delete uploaded file if database insert failed
            if ($photo_filename && file_exists(__DIR__ . '/uploads/' . $photo_filename)) {
                unlink(__DIR__ . '/uploads/' . $photo_filename);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - SMS Portal</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .photo-preview-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 0.5rem;
            padding: 0.5rem;
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-md);
        }
        
        .photo-preview {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-light);
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container" style="max-width: 550px;">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2>Student Registration</h2>
                <p>Register a new student account to access courses assignment</p>
            </div>
            
            <div class="auth-body">
                <!-- Error messages -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" style="display: block;">
                        <ul style="padding-left: 1.25rem; margin: 0;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="register.php" method="POST" enctype="multipart/form-data">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <!-- Full Name -->
                        <div class="auth-form-group">
                            <label for="full_name">Full Name</label>
                            <div class="auth-input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="full_name" id="full_name" class="auth-input" placeholder="John Doe" value="<?php echo htmlspecialchars($full_name); ?>" required>
                            </div>
                        </div>
                        
                        <!-- Student ID -->
                        <div class="auth-form-group">
                            <label for="student_id">Student ID / Reg No</label>
                            <div class="auth-input-group">
                                <i class="fas fa-id-card"></i>
                                <input type="text" name="student_id" id="student_id" class="auth-input" placeholder="STD1002" value="<?php echo htmlspecialchars($student_id); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <!-- Email -->
                        <div class="auth-form-group">
                            <label for="email">Email Address</label>
                            <div class="auth-input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" id="email" class="auth-input" placeholder="johndoe@example.com" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                        </div>
                        
                        <!-- Phone Number -->
                        <div class="auth-form-group">
                            <label for="phone">Phone Number</label>
                            <div class="auth-input-group">
                                <i class="fas fa-phone"></i>
                                <input type="tel" name="phone" id="phone" class="auth-input" placeholder="+123456789" value="<?php echo htmlspecialchars($phone); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <!-- Password -->
                        <div class="auth-form-group">
                            <label for="password">Password</label>
                            <div class="auth-input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" id="password" class="auth-input" placeholder="••••••••" required>
                            </div>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div class="auth-form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="auth-input-group">
                                <i class="fas fa-shield-alt"></i>
                                <input type="password" name="confirm_password" id="confirm_password" class="auth-input" placeholder="••••••••" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Photo Upload -->
                    <div class="auth-form-group">
                        <label for="photo">Profile Photo</label>
                        <input type="file" name="photo" id="photo" class="auth-file-input photo-upload-input" accept="image/*">
                        <div class="photo-preview-container">
                            <img src="assets/images/default-avatar.svg" alt="Preview Photo" class="photo-preview photo-preview-target">
                            <span style="font-size: 0.8rem; color: var(--text-muted);">
                                <strong>Select an image:</strong> JPG, PNG, GIF formats (max 2MB).
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-4" style="margin-top: 1.5rem;">
                        <i class="fas fa-user-plus"></i> Complete Register
                    </button>
                    
                    <div class="auth-footer">
                        Already have an account? <a href="login.php?role=student">Log In here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS Scripts -->
    <script src="assets/js/main.js"></script>
</body>
</html>

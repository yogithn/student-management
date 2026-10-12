<?php
$page_title = "My Profile";
require_once __DIR__ . '/../includes/db_connect.php';

// Auth Guard: Student access only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    $_SESSION['error_msg'] = "Access denied. Please log in as a student.";
    header("Location: ../login.php?role=student");
    exit();
}

$student_id = (int)$_SESSION['user_id'];
$errors = [];
$success_msg = '';

// Fetch fresh details from database
try {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        header("Location: ../logout.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database Query Error: " . $e->getMessage());
}

// ==========================================
// 1. ACTION HANDLERS
// ==========================================

// ACTION: PROFILE PHOTO UPLOAD (Triggers automatically via JS form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_name = $_FILES['photo']['name'];
        $file_size = $_FILES['photo']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_ext, $allowed_exts) && $file_size <= 2 * 1024 * 1024) {
            $upload_dir = __DIR__ . '/../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            // Create unique photo name
            $new_filename = uniqid('student_', true) . '.' . $file_ext;
            
            if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                // Delete previous photo from disk if present
                $old_photo = $student['photo'];
                if (!empty($old_photo) && file_exists($upload_dir . $old_photo)) {
                    unlink($upload_dir . $old_photo);
                }
                
                // Update in database
                $update_stmt = $conn->prepare("UPDATE students SET photo = :photo WHERE id = :id");
                $update_stmt->execute([':photo' => $new_filename, ':id' => $student_id]);
                
                // Update session
                $_SESSION['photo'] = $new_filename;
                
                $_SESSION['success_msg'] = "Profile picture updated successfully.";
                header("Location: profile.php");
                exit();
            } else {
                $errors[] = "Failed to save the uploaded image file.";
            }
        } else {
            $errors[] = "Invalid profile photo. Must be JPG, PNG, or GIF, and under 2MB.";
        }
    } else {
        $errors[] = "Image upload failed. Error code: " . $_FILES['photo']['error'];
    }
}

// ACTION: UPDATE PERSONAL DETAILS (POST name = update_details)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_details') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email address is required.";
    if (empty($phone)) $errors[] = "Phone number is required.";
    
    if (empty($errors)) {
        try {
            // Check if email already registered by someone else
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE email = :email AND id != :id");
            $check_stmt->execute([':email' => $email, ':id' => $student_id]);
            if ($check_stmt->fetchColumn() > 0) {
                $errors[] = "This email address is already in use by another student.";
            }
            
            if (empty($errors)) {
                // Update
                $update_stmt = $conn->prepare("UPDATE students SET full_name = :name, email = :email, phone = :phone WHERE id = :id");
                $update_stmt->execute([
                    ':name' => $full_name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':id' => $student_id
                ]);
                
                // Refresh local values
                $student['full_name'] = $full_name;
                $student['email'] = $email;
                $student['phone'] = $phone;
                
                // Update session name representation
                $_SESSION['name'] = $full_name;
                
                $success_msg = "Personal details updated successfully.";
            }
        } catch (PDOException $e) {
            $errors[] = "Failed to update profile info: " . $e->getMessage();
        }
    }
}

// ACTION: CHANGE PASSWORD (POST name = change_password)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    
    if (empty($current_pass)) $errors[] = "Current password is required.";
    if (empty($new_pass) || strlen($new_pass) < 6) $errors[] = "New password must be at least 6 characters.";
    if ($new_pass !== $confirm_pass) $errors[] = "New passwords do not match.";
    
    if (empty($errors)) {
        try {
            // Verify current password hash
            if (password_verify($current_pass, $student['password'])) {
                // Hash new password
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                
                // Update database
                $update_stmt = $conn->prepare("UPDATE students SET password = :password WHERE id = :id");
                $update_stmt->execute([':password' => $new_hash, ':id' => $student_id]);
                
                $success_msg = "Password changed successfully.";
            } else {
                $errors[] = "Incorrect current password.";
            }
        } catch (PDOException $e) {
            $errors[] = "Failed to update password: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Session/Action Messages -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="display: block;">
        <ul style="padding-left: 1.25rem; margin: 0;">
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($success_msg); ?></span>
    </div>
<?php endif; ?>

<!-- Layout Container Grid -->
<div class="profile-layout">
    
    <!-- Left Column: Student Avatar Box -->
    <div class="profile-card-left">
        <!-- Hidden Photo Upload Form -->
        <form id="avatarUploadForm" action="profile.php" method="POST" enctype="multipart/form-data">
            <div class="profile-avatar-container">
                <?php 
                $photo_src = $student['photo'] ? '../uploads/' . $student['photo'] : '../assets/images/default-avatar.svg';
                ?>
                <img src="<?php echo htmlspecialchars($photo_src); ?>" alt="Student Photo" onerror="this.src='../assets/images/default-avatar.svg'" class="profile-avatar-large">
                
                <!-- Upload Icon Overlay -->
                <label for="photo_file" class="profile-photo-upload-overlay" title="Upload New Photo">
                    <i class="fas fa-camera"></i>
                </label>
                <input type="file" name="photo" id="photo_file" style="display: none;" accept="image/*" onchange="document.getElementById('avatarUploadForm').submit();">
            </div>
        </form>
        
        <h3><?php echo htmlspecialchars($student['full_name']); ?></h3>
        <p>Student Account</p>
        
        <hr style="border: none; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">
        
        <div style="text-align: left; font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.75rem;">
            <div>
                <strong style="color: var(--text-muted); display: block;">Student registration ID</strong>
                <code style="font-size: 0.95rem; font-weight: 600; color: var(--dark);"><?php echo htmlspecialchars($student['student_id']); ?></code>
            </div>
            <div>
                <strong style="color: var(--text-muted); display: block;">Registration Date</strong>
                <span style="color: var(--text-main); font-weight: 500;"><?php echo date('F d, Y', strtotime($student['created_at'])); ?></span>
            </div>
            <div>
                <strong style="color: var(--text-muted); display: block;">Account Status</strong>
                <?php 
                $badge = 'badge-warning';
                if ($student['status'] === 'active') $badge = 'badge-success';
                if ($student['status'] === 'inactive') $badge = 'badge-danger';
                ?>
                <span class="badge <?php echo $badge; ?>" style="margin-top: 0.25rem;"><?php echo $student['status']; ?></span>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Profile details form panels -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Tab Form 1: Personal details -->
        <div class="card" style="margin: 0;">
            <div class="card-header">
                <h2><i class="fas fa-user-edit"></i> Edit Personal Information</h2>
            </div>
            <div class="card-body">
                <form action="profile.php" method="POST">
                    <input type="hidden" name="action" value="update_details">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="student_id_disabled">Student ID (Cannot be modified)</label>
                            <input type="text" id="student_id_disabled" class="form-control" value="<?php echo htmlspecialchars($student['student_id']); ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($student['phone']); ?>" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: auto; padding: 0.625rem 1.5rem; margin-top: 0.5rem;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Tab Form 2: Password modifier -->
        <div class="card" style="margin: 0;">
            <div class="card-header">
                <h2><i class="fas fa-key"></i> Change Password</h2>
            </div>
            <div class="card-body">
                <form action="profile.php" method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password *</label>
                        <input type="password" name="current_password" id="current_password" class="form-control" placeholder="••••••••" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password *</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Min 6 characters" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password *</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: auto; padding: 0.625rem 1.5rem; margin-top: 0.5rem; background-color: var(--dark);">
                        <i class="fas fa-shield-alt"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

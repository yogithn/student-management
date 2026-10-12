<?php
$page_title = "Manage Students";
require_once __DIR__ . '/../includes/db_connect.php';

// Auth Guard: Admin access only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_msg'] = "Access denied. Please log in as an administrator.";
    header("Location: ../login.php?role=admin");
    exit();
}

$errors = [];

// ==========================================
// 1. HANDLERS FOR POST / GET CRUD ACTIONS
// ==========================================

// DELETE STUDENT (GET parameter action=delete)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    try {
        // Fetch photo filename to delete from disk
        $stmt = $conn->prepare("SELECT photo FROM students WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        $photo = $stmt->fetchColumn();

        // Delete record (cascades automatically to student_courses due to FOREIGN KEY CONSTRAINT)
        $stmt = $conn->prepare("DELETE FROM students WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);

        // Unlink profile photo from disk
        if (!empty($photo) && file_exists(__DIR__ . '/../uploads/' . $photo)) {
            unlink(__DIR__ . '/../uploads/' . $photo);
        }

        $_SESSION['success_msg'] = "Student record deleted successfully.";
        header("Location: students.php");
        exit();
    } catch (PDOException $e) {
        $errors[] = "Error deleting student: " . $e->getMessage();
    }
}

// ADD OR EDIT STUDENT (POST requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $post_action = $_POST['action'];

    // ADD STUDENT ACTION
    if ($post_action === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $student_id = trim($_POST['student_id'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $status = $_POST['status'] ?? 'registered';

        // Validations
        if (empty($full_name)) $errors[] = "Full name is required.";
        if (empty($student_id)) $errors[] = "Student ID is required.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
        if (empty($phone)) $errors[] = "Phone number is required.";
        if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";

        // Unique check
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE student_id = :student_id OR email = :email");
            $stmt->execute([':student_id' => $student_id, ':email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Student ID or Email is already registered.";
            }
        }

        // Photo Upload Handling
        $photo_filename = null;
        if (empty($errors) && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']) && $_FILES['photo']['size'] <= 2 * 1024 * 1024) {
                // Ensure upload folder exists
                $upload_dir = __DIR__ . '/../uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $photo_filename = uniqid('student_', true) . '.' . $file_ext;
                if (!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo_filename)) {
                    $errors[] = "Failed to upload profile photo.";
                }
            } else {
                $errors[] = "Invalid profile photo. Must be JPG/PNG/GIF and less than 2MB.";
            }
        }

        // Database insert
        if (empty($errors)) {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO students (student_id, full_name, email, phone, password, photo, status) 
                                        VALUES (:student_id, :full_name, :email, :phone, :password, :photo, :status)");
                $stmt->execute([
                    ':student_id' => $student_id,
                    ':full_name' => $full_name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':password' => $password_hash,
                    ':photo' => $photo_filename,
                    ':status' => $status
                ]);

                $_SESSION['success_msg'] = "New student added successfully.";
                header("Location: students.php");
                exit();
            } catch (PDOException $e) {
                $errors[] = "Database Error: " . $e->getMessage();
            }
        }
    }

    // EDIT STUDENT ACTION
    if ($post_action === 'edit') {
        $db_id = (int)$_POST['db_id'];
        $full_name = trim($_POST['full_name'] ?? '');
        $student_id = trim($_POST['student_id'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $status = $_POST['status'] ?? 'registered';

        if (empty($full_name)) $errors[] = "Full name is required.";
        if (empty($student_id)) $errors[] = "Student ID is required.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
        if (empty($phone)) $errors[] = "Phone number is required.";

        // Unique check excluding current student ID
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE (student_id = :student_id OR email = :email) AND id != :id");
            $stmt->execute([':student_id' => $student_id, ':email' => $email, ':id' => $db_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Student ID or Email already exists for another student.";
            }
        }

        // File upload photo check
        $photo_filename = null;
        if (empty($errors) && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']) && $_FILES['photo']['size'] <= 2 * 1024 * 1024) {
                $upload_dir = __DIR__ . '/../uploads/';
                
                // Get old photo filename to delete
                $stmt_photo = $conn->prepare("SELECT photo FROM students WHERE id = :id");
                $stmt_photo->execute([':id' => $db_id]);
                $old_photo = $stmt_photo->fetchColumn();

                $photo_filename = uniqid('student_', true) . '.' . $file_ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo_filename)) {
                    // Remove old photo
                    if (!empty($old_photo) && file_exists($upload_dir . $old_photo)) {
                        unlink($upload_dir . $old_photo);
                    }
                } else {
                    $errors[] = "Failed to upload new profile photo.";
                }
            } else {
                $errors[] = "Invalid profile photo. Must be JPG/PNG/GIF and less than 2MB.";
            }
        }

        // Database Update
        if (empty($errors)) {
            try {
                // Update photo if new file uploaded
                if ($photo_filename !== null) {
                    $conn->prepare("UPDATE students SET photo = :photo WHERE id = :id")->execute([':photo' => $photo_filename, ':id' => $db_id]);
                }

                // Update password if fields filled in
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $errors[] = "New password must be at least 6 characters.";
                    } else {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $conn->prepare("UPDATE students SET password = :password WHERE id = :id")->execute([':password' => $password_hash, ':id' => $db_id]);
                    }
                }

                if (empty($errors)) {
                    $stmt = $conn->prepare("UPDATE students SET student_id = :student_id, full_name = :full_name, 
                                            email = :email, phone = :phone, status = :status WHERE id = :id");
                    $stmt->execute([
                        ':student_id' => $student_id,
                        ':full_name' => $full_name,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':status' => $status,
                        ':id' => $db_id
                    ]);

                    $_SESSION['success_msg'] = "Student details updated successfully.";
                    header("Location: students.php");
                    exit();
                }
            } catch (PDOException $e) {
                $errors[] = "Update Error: " . $e->getMessage();
            }
        }
    }
}

// ==========================================
// 2. SEARCH, FILTER, AND PAGINATION
// ==========================================

$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

$where_clauses = [];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(student_id LIKE :search OR full_name LIKE :search OR email LIKE :search OR phone LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status_filter !== '' && in_array($status_filter, ['registered', 'active', 'inactive'])) {
    $where_clauses[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);

// Pagination variables
$limit = 5; // records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    // Count total records
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM students $where_sql");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    if ($total_pages < 1) $total_pages = 1;
    if ($page > $total_pages) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    // Select page records
    $select_sql = "SELECT * FROM students $where_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $select_stmt = $conn->prepare($select_sql);
    $select_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $select_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $select_stmt->bindValue($key, $val);
    }
    $select_stmt->execute();
    $students = $select_stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Query Error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Action Bar & Error Message Block -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="display: block;">
        <ul style="padding-left: 1.25rem; margin: 0;">
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-users"></i> Student Records</h2>
        <button class="btn btn-primary" onclick="openModal('addStudentModal')" style="width: auto; padding: 0.5rem 1.25rem;">
            <i class="fas fa-plus"></i> Add New Student
        </button>
    </div>
    
    <div class="card-body">
        <!-- Search and Filters Form -->
        <form method="GET" action="students.php" class="filters-wrapper">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by name, ID, email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <select name="status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="registered" <?php echo $status_filter === 'registered' ? 'selected' : ''; ?>>Registered</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            
            <button type="submit" class="btn btn-secondary" style="width: auto; padding: 0.5rem 1.25rem;">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <?php if ($search !== '' || $status_filter !== ''): ?>
                <a href="students.php" class="btn btn-outline" style="width: auto; padding: 0.5rem 1.25rem;">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Students List Table -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Date Registered</th>
                        <th style="width: 130px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="8" class="text-center" style="padding: 2rem; color: var(--text-muted);">No student records found matching the criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $photo_src = $student['photo'] ? '../uploads/' . $student['photo'] : '../assets/images/default-avatar.svg';
                                    ?>
                                    <img src="<?php echo htmlspecialchars($photo_src); ?>" alt="Student Photo" onerror="this.src='../assets/images/default-avatar.svg'" class="table-photo">
                                </td>
                                <td><code><?php echo htmlspecialchars($student['student_id']); ?></code></td>
                                <td><strong style="color: var(--dark);"><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                <td><a href="mailto:<?php echo htmlspecialchars($student['email']); ?>"><?php echo htmlspecialchars($student['email']); ?></a></td>
                                <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                <td>
                                    <?php 
                                    $badge = 'badge-warning';
                                    if ($student['status'] === 'active') $badge = 'badge-success';
                                    if ($student['status'] === 'inactive') $badge = 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo $student['status']; ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons" style="justify-content: center;">
                                        <!-- Assign Courses Icon -->
                                        <a href="assign-courses.php?student_id=<?php echo $student['id']; ?>" class="btn-icon btn-icon-assign" title="Assign Courses">
                                            <i class="fas fa-book-reader"></i>
                                        </a>
                                        <!-- Edit Details Icon (passes data attributes to fill modal) -->
                                        <button type="button" class="btn-icon btn-icon-edit" title="Edit details"
                                                onclick="openEditStudentModal(this)"
                                                data-id="<?php echo $student['id']; ?>"
                                                data-student-id="<?php echo htmlspecialchars($student['student_id']); ?>"
                                                data-full-name="<?php echo htmlspecialchars($student['full_name']); ?>"
                                                data-email="<?php echo htmlspecialchars($student['email']); ?>"
                                                data-phone="<?php echo htmlspecialchars($student['phone']); ?>"
                                                data-status="<?php echo htmlspecialchars($student['status']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <!-- Delete Icon -->
                                        <button type="button" class="btn-icon btn-icon-delete" title="Delete Student"
                                                onclick="confirmDelete('students.php?action=delete&id=<?php echo $student['id']; ?>', '<?php echo addslashes(htmlspecialchars($student['full_name'])); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div>
                    Showing <strong><?php echo min($total_records, $offset + 1); ?></strong> to <strong><?php echo min($total_records, $offset + $limit); ?></strong> of <strong><?php echo $total_records; ?></strong> records
                </div>
                <div class="pagination-buttons">
                    <a href="students.php?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="pagination-link <?php echo $page == 1 ? 'disabled' : ''; ?>"><i class="fas fa-angle-double-left"></i></a>
                    <a href="students.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="pagination-link <?php echo $page == 1 ? 'disabled' : ''; ?>"><i class="fas fa-angle-left"></i></a>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="students.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="pagination-link <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <a href="students.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="pagination-link <?php echo $page == $total_pages ? 'disabled' : ''; ?>"><i class="fas fa-angle-right"></i></a>
                    <a href="students.php?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="pagination-link <?php echo $page == $total_pages ? 'disabled' : ''; ?>"><i class="fas fa-angle-double-right"></i></a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==========================================
// 3. POPUP MODALS (ADD & EDIT STUDENTS)
// ========================================== -->

<!-- Add Student Modal -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New Student</h3>
            <button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button>
        </div>
        <form action="students.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
                    </div>
                    <div class="form-group">
                        <label for="student_id">Student ID *</label>
                        <input type="text" name="student_id" class="form-control" placeholder="STD1001" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" name="email" class="form-control" placeholder="johndoe@example.com" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" name="phone" class="form-control" placeholder="+1 234 567" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Account Status</label>
                        <select name="status" class="form-control">
                            <option value="registered">Registered</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="photo">Profile Photo</label>
                    <input type="file" name="photo" class="form-control photo-upload-input" accept="image/*">
                    <div style="display:flex; align-items:center; gap:1rem; margin-top:0.5rem;">
                        <img src="../assets/images/default-avatar.svg" alt="Preview Photo" class="table-photo photo-preview-target" style="width: 50px; height: 50px;">
                        <span style="font-size:0.75rem; color:var(--text-muted);">JPG/PNG/GIF image files under 2MB.</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')" style="width: auto;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="width: auto;">Save Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editStudentModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Edit Student Details</h3>
            <button class="modal-close" onclick="closeModal('editStudentModal')">&times;</button>
        </div>
        <form action="students.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="db_id" id="edit_db_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_full_name">Full Name *</label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_student_id">Student ID *</label>
                        <input type="text" name="student_id" id="edit_student_id" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_email">Email Address *</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">Phone Number *</label>
                        <input type="tel" name="phone" id="edit_phone" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_password">New Password</label>
                        <input type="password" name="password" id="edit_password" class="form-control" placeholder="Leave blank to keep unchanged">
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Account Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="registered">Registered</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_photo">Update Profile Photo</label>
                    <input type="file" name="photo" id="edit_photo" class="form-control photo-upload-input" accept="image/*">
                    <div style="display:flex; align-items:center; gap:1rem; margin-top:0.5rem;">
                        <img src="../assets/images/default-avatar.svg" alt="Preview Photo" class="table-photo photo-preview-target" style="width: 50px; height: 50px;">
                        <span style="font-size:0.75rem; color:var(--text-muted);">Select a new file to overwrite the current profile photo (JPG/PNG/GIF max 2MB).</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')" style="width: auto;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="width: auto;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
// 4. JAVASCRIPT INJECTS FOR PRE-POPULATING MODALS
// ========================================== -->
<script>
    function openEditStudentModal(button) {
        // Retrieve values from data-attributes
        const id = button.getAttribute('data-id');
        const studentId = button.getAttribute('data-student-id');
        const fullName = button.getAttribute('data-full-name');
        const email = button.getAttribute('data-email');
        const phone = button.getAttribute('data-phone');
        const status = button.getAttribute('data-status');
        
        // Populate modal inputs
        document.getElementById('edit_db_id').value = id;
        document.getElementById('edit_student_id').value = studentId;
        document.getElementById('edit_full_name').value = fullName;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_phone').value = phone;
        document.getElementById('edit_status').value = status;
        
        // Reset password field
        document.getElementById('edit_password').value = '';
        
        // Open modal
        openModal('editStudentModal');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

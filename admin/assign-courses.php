<?php
$page_title = "Assign Student Courses";
require_once __DIR__ . '/../includes/db_connect.php';

// Auth Guard: Admin access only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_msg'] = "Access denied. Please log in as an administrator.";
    header("Location: ../login.php?role=admin");
    exit();
}

$errors = [];
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if ($student_id <= 0) {
    $_SESSION['error_msg'] = "Invalid Student ID provided.";
    header("Location: students.php");
    exit();
}

// Fetch student details
try {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        $_SESSION['error_msg'] = "Student not found.";
        header("Location: students.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// 1. HANDLERS FOR ASSIGNMENT ACTIONS
// ==========================================

// ASSIGN NEW COURSE ACTION (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign') {
    $course_id = (int)($_POST['course_id'] ?? 0);
    
    if ($course_id <= 0) {
        $errors[] = "Please select a valid course to assign.";
    }
    
    if (empty($errors)) {
        try {
            // Insert course assignment
            $stmt = $conn->prepare("INSERT INTO student_courses (student_id, course_id) VALUES (:student_id, :course_id)");
            $stmt->execute([
                ':student_id' => $student_id,
                ':course_id' => $course_id
            ]);
            
            // Automatically update student status to 'active' on new course assignment
            if ($student['status'] === 'registered') {
                $status_stmt = $conn->prepare("UPDATE students SET status = 'active' WHERE id = :id");
                $status_stmt->execute([':id' => $student_id]);
                // Refresh student detail locally
                $student['status'] = 'active';
            }
            
            $_SESSION['success_msg'] = "Course assigned successfully.";
            header("Location: assign-courses.php?student_id=" . $student_id);
            exit();
        } catch (PDOException $e) {
            $errors[] = "Assignment failed: " . $e->getMessage();
        }
    }
}

// REMOVE ASSIGNED COURSE ACTION (GET parameter action=remove)
if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['course_id'])) {
    $course_id = (int)$_GET['course_id'];
    
    try {
        // Remove course assignment
        $stmt = $conn->prepare("DELETE FROM student_courses WHERE student_id = :student_id AND course_id = :course_id");
        $stmt->execute([
            ':student_id' => $student_id,
            ':course_id' => $course_id
        ]);
        
        // Check if student still has courses assigned
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM student_courses WHERE student_id = :student_id");
        $check_stmt->execute([':student_id' => $student_id]);
        $courses_left = $check_stmt->fetchColumn();
        
        // Revert status to 'registered' if all assigned courses are removed
        if ($courses_left == 0 && $student['status'] === 'active') {
            $status_stmt = $conn->prepare("UPDATE students SET status = 'registered' WHERE id = :id");
            $status_stmt->execute([':id' => $student_id]);
            // Refresh student details locally
            $student['status'] = 'registered';
        }
        
        $_SESSION['success_msg'] = "Course removed from student enrollment.";
        header("Location: assign-courses.php?student_id=" . $student_id);
        exit();
    } catch (PDOException $e) {
        $errors[] = "Removal failed: " . $e->getMessage();
    }
}

// ==========================================
// 2. FETCH QUERY SETS FOR LAYOUT RENDER
// ==========================================

try {
    // Query 1: Fetch currently assigned courses
    $assigned_stmt = $conn->prepare("
        SELECT c.*, sc.assigned_at 
        FROM courses c 
        JOIN student_courses sc ON c.id = sc.course_id 
        WHERE sc.student_id = :student_id
        ORDER BY sc.assigned_at DESC
    ");
    $assigned_stmt->execute([':student_id' => $student_id]);
    $assigned_courses = $assigned_stmt->fetchAll();
    
    // Query 2: Fetch available courses NOT assigned to student
    $available_stmt = $conn->prepare("
        SELECT * FROM courses 
        WHERE id NOT IN (
            SELECT course_id FROM student_courses WHERE student_id = :student_id
        )
        ORDER BY course_name ASC
    ");
    $available_stmt->execute([':student_id' => $student_id]);
    $available_courses = $available_stmt->fetchAll();
} catch (PDOException $e) {
    die("Query Loading Error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Action Alerts Block -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="display: block;">
        <ul style="padding-left: 1.25rem; margin: 0;">
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Student Profile Info Bar -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1.5rem 2rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <?php 
                $avatar_src = $student['photo'] ? '../uploads/' . $student['photo'] : '../assets/images/default-avatar.svg';
                ?>
                <img src="<?php echo htmlspecialchars($avatar_src); ?>" alt="Student Photo" onerror="this.src='../assets/images/default-avatar.svg'" class="profile-avatar-large" style="width: 80px; height: 80px; margin: 0; border-width: 3px;">
                <div>
                    <h2 style="font-size: 1.5rem; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                    <div style="display: flex; gap: 1rem; align-items: center; font-size: 0.875rem; color: var(--text-muted);">
                        <span><i class="fas fa-id-card"></i> ID: <code><?php echo htmlspecialchars($student['student_id']); ?></code></span>
                        <span>&bull;</span>
                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></span>
                    </div>
                </div>
            </div>
            <div>
                <?php 
                $badge = 'badge-warning';
                if ($student['status'] === 'active') $badge = 'badge-success';
                if ($student['status'] === 'inactive') $badge = 'badge-danger';
                ?>
                <span class="badge <?php echo $badge; ?>" style="font-size: 0.85rem; padding: 0.35rem 0.85rem;"><?php echo $student['status']; ?></span>
                <a href="students.php" class="btn btn-secondary" style="width: auto; padding: 0.5rem 1rem; margin-left: 1rem;">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Courses Configuration Panels Grid -->
<div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start;">
    
    <!-- Left panel: Enrolled Courses List -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-book-open"></i> Currently Enrolled Courses</h2>
            <span class="badge badge-info"><?php echo count($assigned_courses); ?> Assigned</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Course Name</th>
                            <th>Assigned Date</th>
                            <th style="width: 80px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assigned_courses)): ?>
                            <tr>
                                <td colspan="4" class="text-center" style="padding: 3rem; color: var(--text-muted);">
                                    <i class="fas fa-folder-open" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.5;"></i>
                                    No courses currently assigned to this student.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assigned_courses as $course): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($course['course_code']); ?></code></td>
                                    <td><strong style="color: var(--dark);"><?php echo htmlspecialchars($course['course_name']); ?></strong></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($course['assigned_at'])); ?></td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn-icon btn-icon-delete" title="Remove Assignment"
                                                onclick="confirmDelete('assign-courses.php?student_id=<?php echo $student_id; ?>&action=remove&course_id=<?php echo $course['id']; ?>', 'this course enrollment')">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Panel: Assign New Course Selection form -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-plus-circle"></i> Assign Course</h2>
        </div>
        <div class="card-body">
            <?php if (empty($available_courses)): ?>
                <div style="text-align: center; color: var(--text-muted); padding: 1rem 0;">
                    <i class="fas fa-check-double" style="font-size: 1.5rem; color: var(--success); margin-bottom: 0.5rem; display: block;"></i>
                    All available curriculum courses have already been assigned to this student.
                </div>
            <?php else: ?>
                <form action="assign-courses.php?student_id=<?php echo $student_id; ?>" method="POST">
                    <input type="hidden" name="action" value="assign">
                    
                    <div class="form-group">
                        <label for="course_id">Select Course</label>
                        <select name="course_id" id="course_id" class="form-control" required style="padding-right: 2rem;">
                            <option value="">-- Choose Course --</option>
                            <?php foreach ($available_courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-2">
                        <i class="fas fa-user-plus"></i> Assign Selected Course
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    @media (max-width: 991px) {
        div[style*="grid-template-columns: 1fr 340px"] {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

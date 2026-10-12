<?php
$page_title = "Student Dashboard";
require_once __DIR__ . '/../includes/db_connect.php';

// Auth Guard: Student access only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    $_SESSION['error_msg'] = "Access denied. Please log in as a student.";
    header("Location: ../login.php?role=student");
    exit();
}

$student_id = (int)$_SESSION['user_id'];

try {
    // 1. Fetch student information from database
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        // Session stale fallback
        header("Location: ../logout.php");
        exit();
    }
    
    // Sync session photo name just in case it was updated on profile page
    $_SESSION['photo'] = $student['photo'];
    $_SESSION['name'] = $student['full_name'];

    // 2. Fetch assigned courses list
    $courses_stmt = $conn->prepare("
        SELECT c.*, sc.assigned_at 
        FROM courses c
        JOIN student_courses sc ON c.id = sc.course_id
        WHERE sc.student_id = :student_id
        ORDER BY sc.assigned_at DESC
    ");
    $courses_stmt->execute([':student_id' => $student_id]);
    $assigned_courses = $courses_stmt->fetchAll();
    
    $total_assigned_courses = count($assigned_courses);
} catch (PDOException $e) {
    die("Database Query Error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Section Banner -->
<div class="card" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: var(--white); border: none; margin-bottom: 2rem;">
    <div class="card-body" style="padding: 2.5rem 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;">
        <div>
            <h2 style="color: var(--white); font-size: 1.85rem; margin-bottom: 0.5rem; font-weight: 700;">
                Welcome Back, <?php echo htmlspecialchars($student['full_name']); ?>!
            </h2>
            <p style="opacity: 0.9; max-width: 550px; font-size: 0.95rem;">
                Here is your student console. You can view your current class schedules, syllabus outlines, and edit your profile details.
            </p>
        </div>
        <div>
            <a href="profile.php" class="btn btn-secondary" style="width: auto; padding: 0.625rem 1.5rem; font-weight: 600; border: none; box-shadow: var(--shadow-sm);">
                <i class="fas fa-user-edit"></i> Modify Profile
            </a>
        </div>
    </div>
</div>

<!-- Quick Statistics cards -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <!-- Enrolled Courses -->
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo $total_assigned_courses; ?></h3>
            <p>Enrolled Courses</p>
        </div>
        <div class="stat-icon info">
            <i class="fas fa-book-open"></i>
        </div>
    </div>
    
    <!-- Account Status -->
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo ucfirst(htmlspecialchars($student['status'])); ?></h3>
            <p>Account Status</p>
        </div>
        <div class="stat-icon <?php echo $student['status'] === 'active' ? 'success' : 'warning'; ?>">
            <i class="fas <?php echo $student['status'] === 'active' ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
        </div>
    </div>
    
    <!-- Registration ID -->
    <div class="stat-card">
        <div class="stat-info">
            <h3 style="font-size: 1.35rem; font-family: monospace; font-weight: 700; word-break: break-all; margin-top: 0.5rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($student['student_id']); ?></h3>
            <p>Student ID</p>
        </div>
        <div class="stat-icon primary">
            <i class="fas fa-id-badge"></i>
        </div>
    </div>
    
    <!-- Date Registered -->
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo date('M d', strtotime($student['created_at'])); ?></h3>
            <p>Joined: <?php echo date('Y', strtotime($student['created_at'])); ?></p>
        </div>
        <div class="stat-icon warning">
            <i class="fas fa-calendar-alt"></i>
        </div>
    </div>
</div>

<!-- Courses Enrollment Panel -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-book-reader"></i> My Assigned Courses</h2>
        <span class="badge badge-success"><?php echo $total_assigned_courses; ?> Active</span>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 150px;">Course Code</th>
                        <th>Course Name</th>
                        <th>Syllabus / Description</th>
                        <th style="width: 200px;">Assigned Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assigned_courses)): ?>
                        <tr>
                            <td colspan="4" class="text-center" style="padding: 3.5rem; color: var(--text-muted);">
                                <i class="fas fa-book-open" style="font-size: 2.5rem; margin-bottom: 0.75rem; display: block; opacity: 0.3;"></i>
                                No courses have been assigned to you by the administrator yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assigned_courses as $course): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($course['course_code']); ?></code></td>
                                <td><strong style="color: var(--dark); font-weight: 600;"><?php echo htmlspecialchars($course['course_name']); ?></strong></td>
                                <td style="color: var(--text-muted); max-width: 400px;">
                                    <?php echo htmlspecialchars($course['description'] ?? 'No syllabus description provided.'); ?>
                                </td>
                                <td><?php echo date('F d, Y h:i A', strtotime($course['assigned_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

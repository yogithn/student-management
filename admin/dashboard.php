<?php
$page_title = "Admin Dashboard";
require_once __DIR__ . '/../includes/db_connect.php';

// Auth Guard: Restrict to administrator role only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_msg'] = "Access denied. Please log in as an administrator.";
    header("Location: ../login.php?role=admin");
    exit();
}

try {
    // Query statistics
    $total_students = $conn->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $total_courses = $conn->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $active_students = $conn->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
    $registered_students = $conn->query("SELECT COUNT(*) FROM students WHERE status = 'registered'")->fetchColumn();

    // Fetch recent 5 student signups
    $recent_students = $conn->query("SELECT * FROM students ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // Fetch recent 5 courses added
    $recent_courses = $conn->query("SELECT * FROM courses ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Statistics Panel Cards -->
<div class="stats-grid">
    <!-- Card 1: Total Students -->
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo number_format($total_students); ?></h3>
            <p>Total Students</p>
        </div>
        <div class="stat-icon primary">
            <i class="fas fa-user-graduate"></i>
        </div>
    </div>
    
    <!-- Card 2: Total Courses -->
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo number_format($total_courses); ?></h3>
            <p>Total Courses</p>
        </div>
        <div class="stat-icon info">
            <i class="fas fa-book"></i>
        </div>
    </div>
    
    <!-- Card 3: Active Students -->
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo number_format($active_students); ?></h3>
            <p>Active Students</p>
        </div>
        <div class="stat-icon success">
            <i class="fas fa-user-check"></i>
        </div>
    </div>
    
    <!-- Card 4: Registered Students -->
    <div class="stat-card">
        <div class="stat-info">
            <h3><?php echo number_format($registered_students); ?></h3>
            <p>Registered Students</p>
        </div>
        <div class="stat-icon warning">
            <i class="fas fa-user-clock"></i>
        </div>
    </div>
</div>

<!-- Main Activity Panels -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 2rem;">
    <!-- Recent Student registrations card -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-clock"></i> Recent Student Registrations</h2>
            <a href="students.php" class="btn btn-secondary" style="width: auto; padding: 0.25rem 0.75rem; font-size: 0.8rem;">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Date Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_students)): ?>
                            <tr>
                                <td colspan="4" class="text-center" style="padding: 1.5rem; color: var(--text-muted);">No student registrations found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_students as $student): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <?php 
                                            $avatar = $student['photo'] ? '../uploads/' . $student['photo'] : '../assets/images/default-avatar.svg';
                                            ?>
                                            <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Student Photo" onerror="this.src='../assets/images/default-avatar.svg'" class="table-photo">
                                            <span style="font-weight: 500;"><?php echo htmlspecialchars($student['full_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($student['student_id']); ?></code></td>
                                    <td>
                                        <?php 
                                        $badge_class = 'badge-warning';
                                        if ($student['status'] === 'active') $badge_class = 'badge-success';
                                        if ($student['status'] === 'inactive') $badge_class = 'badge-danger';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo $student['status']; ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent courses curriculum card -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-bookmark"></i> Recently Added Courses</h2>
            <a href="courses.php" class="btn btn-secondary" style="width: auto; padding: 0.25rem 0.75rem; font-size: 0.8rem;">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Course Name</th>
                            <th>Code</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_courses)): ?>
                            <tr>
                                <td colspan="3" class="text-center" style="padding: 1.5rem; color: var(--text-muted);">No courses registered yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_courses as $course): ?>
                                <tr>
                                    <td><strong style="color: var(--dark);"><?php echo htmlspecialchars($course['course_name']); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($course['course_code']); ?></code></td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($course['description'] ?? 'No description'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Responsive helper style block -->
<style>
    @media (max-width: 991px) {
        div[style*="grid-template-columns: 1fr 1fr"] {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

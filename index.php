<?php
// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optional Database connection for live statistics
$students_count = 0;
$courses_count = 0;
$db_connected = false;

try {
    // Attempt database load
    if (file_exists(__DIR__ . '/config/database.php')) {
        require_once __DIR__ . '/config/database.php';
        if (isset($conn)) {
            $students_count = $conn->query("SELECT COUNT(*) FROM students")->fetchColumn();
            $courses_count = $conn->query("SELECT COUNT(*) FROM courses")->fetchColumn();
            $db_connected = true;
        }
    }
} catch (Exception $e) {
    // Graceful fallback to dummy metrics for preview
    $students_count = 154;
    $courses_count = 12;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management System - Portal Home</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Base Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .landing-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: linear-gradient(135deg, #eef2f6 0%, #cbd5e1 100%);
        }
        
        .landing-header {
            background-color: var(--white);
            box-shadow: var(--shadow-sm);
            padding: 1.25rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .landing-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .landing-logo i {
            color: var(--primary);
        }
        
        .landing-body {
            flex-grow: 1;
            display: flex;
            align-items: center;
            padding: 3rem 1.5rem;
        }
        
        .hero-section {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero-tag {
            background-color: var(--primary-light);
            color: var(--primary);
            padding: 0.375rem 1rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 1.5rem;
            line-height: 1.15;
        }
        
        .hero-subtitle {
            color: var(--text-muted);
            font-size: 1.125rem;
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .stat-badge-grid {
            display: flex;
            justify-content: center;
            gap: 2.5rem;
            margin-top: 3.5rem;
            flex-wrap: wrap;
        }
        
        .stat-badge {
            background-color: var(--white);
            border-radius: var(--radius-md);
            padding: 1rem 1.75rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            text-align: center;
            min-width: 140px;
        }
        
        .stat-badge h4 {
            font-size: 1.75rem;
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 0.15rem;
        }
        
        .stat-badge span {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .landing-footer {
            padding: 1.5rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
            background-color: var(--white);
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.25rem;
            }
            .stat-badge-grid {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="landing-wrapper">
        <header class="landing-header">
            <div class="landing-logo">
                <i class="fas fa-graduation-cap"></i>
                <span>SMS Portal</span>
            </div>
            <div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo $_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'; ?>" class="btn btn-primary" style="width: auto; padding: 0.5rem 1.25rem;">
                        <i class="fas fa-desktop"></i> Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline" style="width: auto; padding: 0.5rem 1.25rem;">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <main class="landing-body">
            <div class="container">
                <div class="hero-section">
                    <span class="hero-tag">Unified Management System</span>
                    <h1 class="hero-title">Empowering Education Management</h1>
                    <p class="hero-subtitle">
                        An elegant, intuitive, and responsive administrative environment for managing course selections, class records, and student portfolios.
                    </p>
                    
                    <div class="landing-grid">
                        <!-- Student card -->
                        <div class="landing-card">
                            <div class="landing-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <h3>Student Portal</h3>
                            <p>Access your registered courses, view and customize your profile photo, and manage your personal database fields.</p>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
                                <a href="student/dashboard.php" class="btn btn-primary">
                                    Open Portal <i class="fas fa-arrow-right"></i>
                                </a>
                            <?php else: ?>
                                <a href="login.php?role=student" class="btn btn-primary">
                                    Student Login <i class="fas fa-arrow-right"></i>
                                </a>
                                <div style="margin-top: 0.75rem; font-size: 0.85rem;">
                                    New student? <a href="register.php">Create Account</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Admin card -->
                        <div class="landing-card">
                            <div class="landing-icon" style="background-color: #e0f2fe; color: var(--secondary);">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <h3>Admin Console</h3>
                            <p>Oversee courses curriculum, enroll students, execute database crud modifications, and view statistics telemetry.</p>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
                                <a href="admin/dashboard.php" class="btn btn-secondary" style="background-color: var(--dark); color: var(--white);">
                                    Open Dashboard <i class="fas fa-arrow-right"></i>
                                </a>
                            <?php else: ?>
                                <a href="login.php?role=admin" class="btn btn-secondary">
                                    Admin Login <i class="fas fa-arrow-right"></i>
                                </a>
                                <div style="margin-top: 0.75rem; font-size: 0.85rem; color: var(--text-light);">
                                    Default demo: <code>admin</code> / <code>admin123</code>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="stat-badge-grid">
                        <div class="stat-badge">
                            <h4><?php echo htmlspecialchars($students_count); ?></h4>
                            <span>Students</span>
                        </div>
                        <div class="stat-badge">
                            <h4><?php echo htmlspecialchars($courses_count); ?></h4>
                            <span>Courses</span>
                        </div>
                        <div class="stat-badge">
                            <h4>100%</h4>
                            <span>Responsive</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="landing-footer">
            <p>&copy; <?php echo date('Y'); ?> Student Management System. Designed for educational excellence.</p>
        </footer>
    </div>
</body>
</html>

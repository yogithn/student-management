<?php
// Initialize session and connection
require_once __DIR__ . '/db_connect.php';

// Auth Guard: check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION['role'];
$username = $_SESSION['name'] ?? 'User';
$photo = $_SESSION['photo'] ?? '';

// Determine photo path relative to the subfolder (admin/ or student/)
if (empty($photo)) {
    $photo_path = '../assets/images/default-avatar.svg';
} else {
    $photo_path = '../uploads/' . $photo;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - Student Management System" : "Student Management System"; ?></title>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <!-- Main Panel -->
        <div class="main-panel">
            <!-- Topbar Header -->
            <header class="topbar">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="page-title">
                        <h1><?php echo isset($page_title) ? htmlspecialchars($page_title) : "Dashboard"; ?></h1>
                    </div>
                </div>
                <div class="topbar-actions">
                    <div class="topbar-user" onclick="window.location.href='<?php echo $role === 'student' ? '../student/profile.php' : '#'; ?>'">
                        <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Profile" onerror="this.src='../assets/images/default-avatar.svg'" class="topbar-avatar">
                        <span class="topbar-user-name"><?php echo htmlspecialchars($username); ?></span>
                    </div>
                </div>
            </header>
            
            <!-- Content Wrapper -->
            <main class="content-wrapper">
                
                <!-- Display Session Success/Error Toasts if Set -->
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></span>
                    </div>
                <?php endif; ?>

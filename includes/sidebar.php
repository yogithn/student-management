<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'student';
$username = $_SESSION['name'] ?? 'User';
$photo = $_SESSION['photo'] ?? '';

// Photo path relative to the subfolders
if (empty($photo)) {
    $photo_path = '../assets/images/default-avatar.svg';
} else {
    $photo_path = '../uploads/' . $photo;
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <div class="sidebar-title">SMS Portal</div>
    </div>
    
    <ul class="sidebar-menu">
        <?php if ($role === 'admin'): ?>
            <!-- Admin Menu -->
            <li class="sidebar-item">
                <a href="../admin/dashboard.php" class="sidebar-link <?php echo $current_page === 'dashboard.php' && strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="../admin/students.php" class="sidebar-link <?php echo $current_page === 'students.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Students</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="../admin/courses.php" class="sidebar-link <?php echo $current_page === 'courses.php' || $current_page === 'assign-courses.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>
            </li>
        <?php else: ?>
            <!-- Student Menu -->
            <li class="sidebar-item">
                <a href="../student/dashboard.php" class="sidebar-link <?php echo $current_page === 'dashboard.php' && strpos($_SERVER['PHP_SELF'], '/student/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-columns"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="../student/profile.php" class="sidebar-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i>
                    <span>My Profile</span>
                </a>
            </li>
        <?php endif; ?>
        
        <!-- Shared Log out Link -->
        <li class="sidebar-item" style="margin-top: 2rem;">
            <a href="../logout.php" class="sidebar-link" style="color: #f87171;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Sidebar Avatar" onerror="this.src='../assets/images/default-avatar.svg'">
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($username); ?></div>
                <div class="sidebar-user-role"><?php echo $role === 'admin' ? 'Administrator' : 'Student'; ?></div>
            </div>
        </div>
    </div>
</aside>

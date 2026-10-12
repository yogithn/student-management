<?php
$page_title = "Manage Courses";
require_once __DIR__ . '/../includes/db_connect.php';

// Auth Guard: Admin access only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_msg'] = "Access denied. Please log in as an administrator.";
    header("Location: ../login.php?role=admin");
    exit();
}

$errors = [];

// ==========================================
// 1. ACTION HANDLERS FOR CRUD OPERATIONS
// ==========================================

// DELETE COURSE ACTION (GET parameter action=delete)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    try {
        $stmt = $conn->prepare("DELETE FROM courses WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        
        $_SESSION['success_msg'] = "Course deleted successfully.";
        header("Location: courses.php");
        exit();
    } catch (PDOException $e) {
        $errors[] = "Error deleting course: " . $e->getMessage();
    }
}

// POST ADD OR EDIT COURSE ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $post_action = $_POST['action'];

    // ADD COURSE
    if ($post_action === 'add') {
        $course_name = trim($_POST['course_name'] ?? '');
        $course_code = trim($_POST['course_code'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($course_name)) $errors[] = "Course name is required.";
        if (empty($course_code)) $errors[] = "Course code is required.";

        if (empty($errors)) {
            // Check unique course code
            $stmt = $conn->prepare("SELECT COUNT(*) FROM courses WHERE course_code = :course_code");
            $stmt->execute([':course_code' => $course_code]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Course code is already in use.";
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("INSERT INTO courses (course_name, course_code, description) VALUES (:name, :code, :desc)");
                $stmt->execute([
                    ':name' => $course_name,
                    ':code' => $course_code,
                    ':desc' => $description
                ]);

                $_SESSION['success_msg'] = "New course created successfully.";
                header("Location: courses.php");
                exit();
            } catch (PDOException $e) {
                $errors[] = "Database Error: " . $e->getMessage();
            }
        }
    }

    // EDIT COURSE
    if ($post_action === 'edit') {
        $db_id = (int)$_POST['db_id'];
        $course_name = trim($_POST['course_name'] ?? '');
        $course_code = trim($_POST['course_code'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($course_name)) $errors[] = "Course name is required.";
        if (empty($course_code)) $errors[] = "Course code is required.";

        if (empty($errors)) {
            // Check uniqueness excluding current row
            $stmt = $conn->prepare("SELECT COUNT(*) FROM courses WHERE course_code = :course_code AND id != :id");
            $stmt->execute([':course_code' => $course_code, ':id' => $db_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Course code is already in use by another course.";
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("UPDATE courses SET course_name = :name, course_code = :code, description = :desc WHERE id = :id");
                $stmt->execute([
                    ':name' => $course_name,
                    ':code' => $course_code,
                    ':desc' => $description,
                    ':id' => $db_id
                ]);

                $_SESSION['success_msg'] = "Course details updated successfully.";
                header("Location: courses.php");
                exit();
            } catch (PDOException $e) {
                $errors[] = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// ==========================================
// 2. SEARCH & PAGINATION CONTROLLERS
// ==========================================

$search = trim($_GET['search'] ?? '');
$where_sql = '';
$params = [];

if ($search !== '') {
    $where_sql = 'WHERE course_name LIKE :search OR course_code LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

// Pagination setup
$limit = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    // Count records query
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM courses $where_sql");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    if ($total_pages < 1) $total_pages = 1;
    if ($page > $total_pages) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    // Select query
    $select_stmt = $conn->prepare("SELECT * FROM courses $where_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $select_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $select_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $select_stmt->bindValue($key, $val);
    }
    $select_stmt->execute();
    $courses = $select_stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Query Error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Alert Blocks -->
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
        <h2><i class="fas fa-book"></i> Course Curriculum</h2>
        <button class="btn btn-primary" onclick="openModal('addCourseModal')" style="width: auto; padding: 0.5rem 1.25rem;">
            <i class="fas fa-plus"></i> Add New Course
        </button>
    </div>
    
    <div class="card-body">
        <!-- Search filter layout -->
        <form method="GET" action="courses.php" class="filters-wrapper">
            <div class="search-box" style="max-width: 400px;">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by course name or code..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <button type="submit" class="btn btn-secondary" style="width: auto; padding: 0.5rem 1.25rem;">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if ($search !== ''): ?>
                <a href="courses.php" class="btn btn-outline" style="width: auto; padding: 0.5rem 1.25rem;">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Course Table View -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Description</th>
                        <th>Date Created</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="5" class="text-center" style="padding: 2rem; color: var(--text-muted);">No courses listed in the curriculum database.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($course['course_code']); ?></code></td>
                                <td><strong style="color: var(--dark);"><?php echo htmlspecialchars($course['course_name']); ?></strong></td>
                                <td style="max-width: 320px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?php echo htmlspecialchars($course['description'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($course['description'] ?? 'No description'); ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons" style="justify-content: center;">
                                        <!-- Edit button populating Edit modal attributes -->
                                        <button type="button" class="btn-icon btn-icon-edit" title="Edit course"
                                                onclick="openEditCourseModal(this)"
                                                data-id="<?php echo $course['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($course['course_name']); ?>"
                                                data-code="<?php echo htmlspecialchars($course['course_code']); ?>"
                                                data-description="<?php echo htmlspecialchars($course['description'] ?? ''); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <!-- Delete course link button -->
                                        <button type="button" class="btn-icon btn-icon-delete" title="Delete course"
                                                onclick="confirmDelete('courses.php?action=delete&id=<?php echo $course['id']; ?>', '<?php echo addslashes(htmlspecialchars($course['course_name'])); ?>')">
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

        <!-- Pagination Panel -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div>
                    Showing <strong><?php echo min($total_records, $offset + 1); ?></strong> to <strong><?php echo min($total_records, $offset + $limit); ?></strong> of <strong><?php echo $total_records; ?></strong> courses
                </div>
                <div class="pagination-buttons">
                    <a href="courses.php?page=1&search=<?php echo urlencode($search); ?>" class="pagination-link <?php echo $page == 1 ? 'disabled' : ''; ?>"><i class="fas fa-angle-double-left"></i></a>
                    <a href="courses.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="pagination-link <?php echo $page == 1 ? 'disabled' : ''; ?>"><i class="fas fa-angle-left"></i></a>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="courses.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="pagination-link <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <a href="courses.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="pagination-link <?php echo $page == $total_pages ? 'disabled' : ''; ?>"><i class="fas fa-angle-right"></i></a>
                    <a href="courses.php?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>" class="pagination-link <?php echo $page == $total_pages ? 'disabled' : ''; ?>"><i class="fas fa-angle-double-right"></i></a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==========================================
// 3. POPUP FORM MODALS
// ========================================== -->

<!-- Add Course Modal -->
<div class="modal-overlay" id="addCourseModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Add New Course</h3>
            <button class="modal-close" onclick="closeModal('addCourseModal')">&times;</button>
        </div>
        <form action="courses.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_name">Course Name *</label>
                        <input type="text" name="course_name" class="form-control" placeholder="Introduction to Web Development" required>
                    </div>
                    <div class="form-group">
                        <label for="course_code">Course Code *</label>
                        <input type="text" name="course_code" class="form-control" placeholder="CS-101" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Course Description</label>
                    <textarea name="description" class="form-control" placeholder="Provide syllabus outline or course objectives..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCourseModal')" style="width: auto;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="width: auto;">Save Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Course Modal -->
<div class="modal-overlay" id="editCourseModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Course details</h3>
            <button class="modal-close" onclick="closeModal('editCourseModal')">&times;</button>
        </div>
        <form action="courses.php" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="db_id" id="edit_db_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_course_name">Course Name *</label>
                        <input type="text" name="course_name" id="edit_course_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_course_code">Course Code *</label>
                        <input type="text" name="course_code" id="edit_course_code" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Course Description</label>
                    <textarea name="description" id="edit_description" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editCourseModal')" style="width: auto;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="width: auto;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditCourseModal(button) {
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        const code = button.getAttribute('data-code');
        const desc = button.getAttribute('data-description');
        
        document.getElementById('edit_db_id').value = id;
        document.getElementById('edit_course_name').value = name;
        document.getElementById('edit_course_code').value = code;
        document.getElementById('edit_description').value = desc;
        
        openModal('editCourseModal');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

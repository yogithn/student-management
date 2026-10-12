<?php
/**
 * Environment Diagnostic Utility
 * 
 * Run this file in your browser (e.g. http://localhost/student%20management/check-env.php)
 * to verify if your database configuration and table structures are correctly configured.
 */

$config_file = __DIR__ . '/config/database.php';
$has_config = file_exists($config_file);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Diagnostic Checklist</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 2rem;
            max-width: 700px;
            margin: 0 auto;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
        }
        h1 {
            font-size: 1.5rem;
            color: #0f172a;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .check-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .check-item:last-child {
            border-bottom: none;
        }
        .check-info {
            display: flex;
            flex-direction: column;
        }
        .check-title {
            font-weight: 600;
        }
        .check-desc {
            font-size: 0.85rem;
            color: #64748b;
        }
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .details-box {
            background-color: #f1f5f9;
            padding: 1rem;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.85rem;
            margin-top: 1.5rem;
            overflow-x: auto;
            white-space: pre-wrap;
            border: 1px solid #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-stethoscope"></i> SMS Database Diagnostics</h1>
        
        <!-- Check database.php config -->
        <div class="check-item">
            <div class="check-info">
                <span class="check-title">Database Config File</span>
                <span class="check-desc">Verifying config/database.php exists</span>
            </div>
            <div>
                <?php if ($has_config): ?>
                    <span class="badge badge-success"><i class="fas fa-check"></i> Found</span>
                <?php else: ?>
                    <span class="badge badge-danger"><i class="fas fa-times"></i> Missing</span>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $db_connected = false;
        $db_details = "";
        $tables_status = [];
        $admin_seeded = false;

        if ($has_config) {
            try {
                // Buffer output of database.php in case it dies
                ob_start();
                require_once $config_file;
                ob_end_clean();

                if (isset($conn)) {
                    $db_connected = true;
                    $db_details = "Successfully connected to host: " . DB_HOST . "\nDatabase: " . DB_NAME;
                    
                    // Check tables
                    $required_tables = ['admin', 'students', 'courses', 'student_courses'];
                    foreach ($required_tables as $table) {
                        try {
                            $q = $conn->query("SELECT 1 FROM `$table` LIMIT 1");
                            $tables_status[$table] = true;
                        } catch (PDOException $e) {
                            $tables_status[$table] = false;
                        }
                    }

                    // Check if Admin Seeded
                    if ($tables_status['admin']) {
                        $admin_count = $conn->query("SELECT COUNT(*) FROM `admin`")->fetchColumn();
                        $admin_seeded = $admin_count > 0;
                    }
                }
            } catch (Exception $e) {
                $db_connected = false;
                $db_details = "Connection failed: " . $e->getMessage();
            }
        }
        ?>

        <!-- Check Database Connection -->
        <div class="check-item">
            <div class="check-info">
                <span class="check-title">MySQL Database Connection</span>
                <span class="check-desc">Testing connection using PDO drivers</span>
            </div>
            <div>
                <?php if ($db_connected): ?>
                    <span class="badge badge-success"><i class="fas fa-check"></i> Connected</span>
                <?php else: ?>
                    <span class="badge badge-danger"><i class="fas fa-times"></i> Connection Failed</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Table checks -->
        <?php if ($db_connected): ?>
            <?php foreach ($tables_status as $table => $exists): ?>
                <div class="check-item">
                    <div class="check-info">
                        <span class="check-title">Table: <code><?php echo $table; ?></code></span>
                        <span class="check-desc">Verifying table structure schema definition</span>
                    </div>
                    <div>
                        <?php if ($exists): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Present</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><i class="fas fa-times"></i> Missing</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Seed Admin Check -->
            <div class="check-item">
                <div class="check-info">
                    <span class="check-title">Default Admin Account</span>
                    <span class="check-desc">Verifying if admin username seed is inserted</span>
                </div>
                <div>
                    <?php if ($admin_seeded): ?>
                        <span class="badge badge-success"><i class="fas fa-check"></i> Seeded</span>
                    <?php else: ?>
                        <span class="badge badge-danger"><i class="fas fa-times"></i> Empty</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Details & Console logs -->
        <div class="details-box">
            <strong>System Logs:</strong><br>
            <?php 
            if (!$has_config) {
                echo "ERROR: Please create the 'config/database.php' file first.\n";
            }
            if (!empty($db_details)) {
                echo htmlspecialchars($db_details) . "\n";
            }
            if ($db_connected) {
                $missing_any = in_array(false, $tables_status);
                if ($missing_any) {
                    echo "WARNING: Some tables are missing. Please run and import 'schema.sql' in your phpMyAdmin / MySQL CLI.\n";
                } else {
                    echo "SUCCESS: Database schema matches standard definitions. Everything is ready to go!\n";
                }
            }
            ?>
        </div>
        
        <div style="margin-top: 1.5rem; text-align: center;">
            <a href="index.php" style="color: #4f46e5; text-decoration: underline; font-weight: 500;">Go to Portal Landing Page</a>
        </div>
    </div>
</body>
</html>

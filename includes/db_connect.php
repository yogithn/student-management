<?php
// Initialize session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load database credentials and create connection
require_once __DIR__ . '/../config/database.php';
?>

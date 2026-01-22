<?php
// Simple test to check if the database connection works
require_once 'config.php';

try {
    $pdo = getConnection();
    echo "Database connection successful!<br>";
    
    // Test if tables exist by checking users table
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "Users table exists.<br>";
        
        // Check if admin user exists
        $stmt = $pdo->query("SELECT username, name, role FROM users WHERE role = 'admin' LIMIT 1");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            echo "Admin user found: " . $admin['name'] . " (" . $admin['username'] . ")<br>";
        }
    } else {
        echo "Users table does not exist yet. It will be created when auth.php is loaded.<br>";
    }
    
    echo "Configuration:<br>";
    echo "DB Host: " . DB_HOST . "<br>";
    echo "DB Name: " . DB_NAME . "<br>";
    echo "DB User: " . DB_USER . "<br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
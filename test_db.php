<?php
/**
 * Simple test to verify database connectivity
 */
echo "<h2>Database Connection Test</h2>";

require_once 'config.php';

try {
    $pdo = getConnection();
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // Test if tables exist by counting users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>User records found: " . $result['count'] . "</p>";
    
    // Initialize database (this will create tables if they don't exist)
    initializeDatabase();
    echo "<p style='color: green;'>✓ Database tables verified/created!</p>";
    
    // Check if default admin exists
    $stmt = $pdo->prepare("SELECT username, name FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "<p style='color: green;'>✓ Default admin user exists:</p>";
        echo "<ul>";
        echo "<li>Username: " . htmlspecialchars($admin['username']) . "</li>";
        echo "<li>Name: " . htmlspecialchars($admin['name']) . "</li>";
        echo "</ul>";
        echo "<p>Default password: admin123</p>";
    } else {
        echo "<p style='color: orange;'>⚠ No admin user found (will be created on first access)</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<h3>Next Steps:</h3>
<ol>
    <li>Place this entire folder in your XAMPP htdocs directory</li>
    <li>Start Apache and MySQL through XAMPP Control Panel</li>
    <li>Access the application via browser at http://localhost/<?php echo basename(getcwd()); ?></li>
    <li>Login with admin credentials: username=admin, password=admin123</li>
</ol>
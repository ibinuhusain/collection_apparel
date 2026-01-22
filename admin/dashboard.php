<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Get counts for dashboard statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_agents FROM users WHERE role = 'agent'");
$stmt->execute();
$total_agents = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total_shops FROM stores");
$stmt->execute();
$total_shops = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total_malls FROM malls");
$stmt->execute();
$total_malls = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total_entities FROM entities");
$stmt->execute();
$total_entities = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total_regions FROM regions");
$stmt->execute();
$total_regions = $stmt->fetchColumn() ?: 0;

// Calculate completion rate
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN da.status = 'completed' THEN 1 END) as completed_assignments,
        COUNT(*) as total_assignments
    FROM daily_assignments da
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$completed_assignments = $result['completed_assignments'] ?: 0;
$total_assignments = $result['total_assignments'] ?: 1; // Avoid division by zero

$completion_rate = $total_assignments > 0 ? round(($completed_assignments / $total_assignments) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Collection Tracking</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="manifest" href="../manifest.json">
    <script>
        // Register service worker for PWA functionality
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('../sw.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed');
                    });
            });
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Apparels Collection Tracker - Admin Dashboard</h1>
            <div class="nav-links">
                <a href="dashboard.php" class="active">Home</a>
                <a href="assignments.php">Assignments</a>
                <a href="agents.php">Agents</a>
                <a href="management.php">Management</a>
                <a href="store_data.php">Store Data</a>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <div class="content">
            <h2>Dashboard Overview</h2>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><?php echo $total_agents; ?></h3>
                    <p>Assigned Agents</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $total_shops; ?></h3>
                    <p>Assigned Shops</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $total_malls; ?></h3>
                    <p>Malls</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $total_entities; ?></h3>
                    <p>Entities</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $total_regions; ?></h3>
                    <p>Regions</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $completion_rate; ?>%</h3>
                    <p>Completion Status</p>
                </div>
            </div>
            
            <div style="margin-top: 30px;">
                <h3>Daily Data Export</h3>
                <a href="export_daily.php" class="btn btn-success">Export Today's Data</a>
                <a href="export_weekly.php" class="btn btn-success">Export Weekly Data</a>
            </div>
        </div>
    </div>
</body>
</html>
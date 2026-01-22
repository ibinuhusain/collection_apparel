<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Get today's date for statistics
$today = date('Y-m-d');

// Total collections today
$stmt = $pdo->prepare("
    SELECT SUM(c.amount_collected) as total_collected 
    FROM collections c 
    JOIN daily_assignments da ON c.assignment_id = da.id 
    WHERE DATE(da.date_assigned) = ?
");
$stmt->execute([$today]);
$total_collected = $stmt->fetchColumn() ?: 0;

// Total agents in transit (have assignments for today)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT da.agent_id) as agents_in_transit 
    FROM daily_assignments da 
    WHERE DATE(da.date_assigned) = ? AND da.status != 'submitted'
");
$stmt->execute([$today]);
$agents_in_transit = $stmt->fetchColumn() ?: 0;

// Completed orders today
$stmt = $pdo->prepare("
    SELECT COUNT(*) as completed_orders 
    FROM daily_assignments da 
    WHERE DATE(da.date_assigned) = ? AND da.status = 'completed'
");
$stmt->execute([$today]);
$completed_orders = $stmt->fetchColumn() ?: 0;

// Get all agents for the stats
$stmt = $pdo->prepare("SELECT COUNT(*) as total_agents FROM users WHERE role = 'agent'");
$stmt->execute();
$total_agents = $stmt->fetchColumn() ?: 0;
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
            <h1>Admin Dashboard</h1>
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
            <h2>Today's Summary</h2>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><?php echo number_format($total_collected, 2); ?></h3>
                    <p>Total Collected Today</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $agents_in_transit; ?></h3>
                    <p>Agents In Transit</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $completed_orders; ?></h3>
                    <p>Completed Orders</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $total_agents; ?></h3>
                    <p>Total Agents</p>
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
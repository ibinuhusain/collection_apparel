<?php
require_once '../includes/auth.php';
requireLogin();

if (hasRole('admin')) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$pdo = getConnection();
$agent_id = $_SESSION['user_id'];

// Get today's date for statistics
$today = date('Y-m-d');

// Get agent's assignments for today
$stmt = $pdo->prepare("
    SELECT da.*, s.name as store_name, s.address as store_address
    FROM daily_assignments da
    JOIN stores s ON da.store_id = s.id
    WHERE da.agent_id = ? AND DATE(da.date_assigned) = ?
    ORDER BY s.name
");
$stmt->execute([$agent_id, $today]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_target = 0;
$total_collected = 0;
$completed_count = 0;
$total_assignments = count($assignments);

foreach ($assignments as $assignment) {
    $total_target += $assignment['target_amount'];
    
    // Get collection for this assignment
    $collection_stmt = $pdo->prepare("SELECT amount_collected FROM collections WHERE assignment_id = ?");
    $collection_stmt->execute([$assignment['id']]);
    $collection = $collection_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($collection) {
        $total_collected += $collection['amount_collected'];
        if ($assignment['status'] === 'completed') {
            $completed_count++;
        }
    }
}

$collection_percentage = $total_target > 0 ? round(($total_collected / $total_target) * 100, 2) : 0;
$remaining_assignments = $total_assignments - $completed_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - Collection Tracking</title>
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
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
            <div class="nav-links">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="store.php">Store</a>
                <a href="submissions.php">Submissions</a>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <div class="content">
            <h2>Today's Collection Target</h2>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><?php echo number_format($total_target, 2); ?></h3>
                    <p>Total Target</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo number_format($total_collected, 2); ?></h3>
                    <p>Collected</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $collection_percentage; ?>%</h3>
                    <p>Completion Rate</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $remaining_assignments; ?></h3>
                    <p>Remaining Stores</p>
                </div>
            </div>
            
            <h2>Your Assigned Entities (<?php echo date('M j, Y'); ?>)</h2>
            <?php if (count($assignments) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Store Name</th>
                            <th>Region</th>
                            <th>Mall</th>
                            <th>Entity</th>
                            <th>Brand</th>
                            <th>Address</th>
                            <th>Target Amount</th>
                            <th>Collected</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                            <?php
                            // Get collection for this assignment
                            $collection_stmt = $pdo->prepare("SELECT amount_collected FROM collections WHERE assignment_id = ?");
                            $collection_stmt->execute([$assignment['id']]);
                            $collection = $collection_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $collected_amount = $collection ? $collection['amount_collected'] : 0;
                            $status = $assignment['status'];
                            
                            // Get store details including new fields
                            $store_stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
                            $store_stmt->execute([$assignment['store_id']]);
                            $store = $store_stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($assignment['store_name']); ?></td>
                                <td><?php echo htmlspecialchars($store['region_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($store['mall'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($store['entity'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($store['brand'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($assignment['store_address']); ?></td>
                                <td><?php echo number_format($assignment['target_amount'], 2); ?></td>
                                <td><?php echo number_format($collected_amount, 2); ?></td>
                                <td>
                                    <span class="<?php echo $status === 'completed' ? 'status-completed' : 'status-pending'; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="store.php?assignment_id=<?php echo $assignment['id']; ?>" class="btn">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>You have no assignments for today.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
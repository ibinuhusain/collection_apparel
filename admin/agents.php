<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Get all agents with their collection stats
$agents_stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(da.id) as total_assignments,
           COUNT(CASE WHEN da.status = 'completed' THEN 1 END) as completed_assignments,
           COALESCE(SUM(CASE WHEN da.status = 'completed' THEN c.amount_collected END), 0) as total_collected
    FROM users u
    LEFT JOIN daily_assignments da ON u.id = da.agent_id AND DATE(da.date_assigned) = ?
    LEFT JOIN collections c ON da.id = c.assignment_id
    WHERE u.role = 'agent'
    GROUP BY u.id
    ORDER BY u.name
");
$today = date('Y-m-d');
$agents_stmt->execute([$today]);
$agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending collections for each agent
$pending_stmt = $pdo->prepare("
    SELECT u.id as agent_id, COUNT(c.id) as pending_count
    FROM users u
    LEFT JOIN daily_assignments da ON u.id = da.agent_id AND DATE(da.date_assigned) = ?
    LEFT JOIN collections c ON da.id = c.assignment_id AND c.pending_amount > 0
    WHERE u.role = 'agent'
    GROUP BY u.id
");
$pending_stmt->execute([$today]);
$pending_results = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of agent ID to pending count
$pending_map = [];
foreach ($pending_results as $result) {
    $pending_map[$result['agent_id']] = $result['pending_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agents - Collection Tracking</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="manifest" href="../manifest.json">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Agents</h1>
            <div class="nav-links">
                <a href="dashboard.php">Home</a>
                <a href="assignments.php">Assignments</a>
                <a href="agents.php" class="active">Agents</a>
                <a href="management.php">Management</a>
                <a href="store_data.php">Store Data</a>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <div class="content">
            <h2>Agents Status Today (<?php echo date('M j, Y'); ?>)</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Agent Name</th>
                        <th>Region</th>
                        <th>Mall</th>
                        <th>Entity</th>
                        <th>Brand</th>
                        <th>Username</th>
                        <th>Phone</th>
                        <th>Total Assignments</th>
                        <th>Completed</th>
                        <th>Pending Items</th>
                        <th>Total Collected</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agents as $agent): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($agent['name']); ?></td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td><?php echo htmlspecialchars($agent['username']); ?></td>
                            <td><?php echo htmlspecialchars($agent['phone']); ?></td>
                            <td><?php echo $agent['total_assignments']; ?></td>
                            <td><?php echo $agent['completed_assignments']; ?></td>
                            <td>
                                <?php 
                                    $pending_count = $pending_map[$agent['id']] ?? 0;
                                    echo $pending_count; 
                                ?>
                            </td>
                            <td><?php echo number_format($agent['total_collected'], 2); ?></td>
                            <td>
                                <?php 
                                    if ($agent['total_assignments'] > 0) {
                                        if ($agent['completed_assignments'] == $agent['total_assignments']) {
                                            echo '<span style="color: green;">Completed</span>';
                                        } else {
                                            echo '<span style="color: orange;">In Progress</span>';
                                        }
                                    } else {
                                        echo '<span style="color: gray;">No Assignments</span>';
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Agents In Transit</h2>
            <table>
                <thead>
                    <tr>
                        <th>Agent Name</th>
                        <th>Region</th>
                        <th>Mall</th>
                        <th>Entity</th>
                        <th>Brand</th>
                        <th>Current Assignments</th>
                        <th>Progress</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $in_transit_agents = array_filter($agents, function($agent) {
                        return $agent['total_assignments'] > $agent['completed_assignments'] && $agent['total_assignments'] > 0;
                    });
                    
                    if (count($in_transit_agents) > 0):
                        foreach ($in_transit_agents as $agent):
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($agent['name']); ?></td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td><?php echo ($agent['total_assignments'] - $agent['completed_assignments']); ?> remaining</td>
                            <td>
                                <?php 
                                    $progress = $agent['total_assignments'] > 0 ? 
                                        round(($agent['completed_assignments'] / $agent['total_assignments']) * 100, 2) : 0;
                                    echo $progress . '%';
                                ?>
                            </td>
                            <td>
                                <?php 
                                    // In a real system, we'd track the last update time
                                    echo 'Just now';
                                ?>
                            </td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="8">No agents currently in transit.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
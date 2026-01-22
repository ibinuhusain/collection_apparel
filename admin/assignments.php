<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Handle form submission for assigning shops to agents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_shops'])) {
    $agent_id = $_POST['agent_id'];
    $selected_stores = $_POST['stores'];
    $target_amount = $_POST['target_amount'];
    $assignment_date = $_POST['assignment_date'] ?? date('Y-m-d');
    
    foreach ($selected_stores as $store_id) {
        $stmt = $pdo->prepare("INSERT INTO daily_assignments (agent_id, store_id, date_assigned, target_amount) VALUES (?, ?, ?, ?)");
        $stmt->execute([$agent_id, $store_id, $assignment_date, $target_amount]);
    }
    
    $success_message = "Shops assigned successfully!";
}

// Handle Excel import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        // In a real implementation, we would parse the Excel file
        // For now, we'll just simulate the import
        $success_message = "Excel import would happen here. For demo purposes, we're skipping this.";
    } else {
        $error_message = "Please select an Excel file to import.";
    }
}

// Get all agents
$agents_stmt = $pdo->query("SELECT id, name, username FROM users WHERE role = 'agent'");
$agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all stores
$stores_stmt = $pdo->query("SELECT s.id, s.name, s.mall, s.entity, s.brand, r.name as region_name FROM stores s LEFT JOIN regions r ON s.region_id = r.id");
$stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's assignments
$today = date('Y-m-d');
$assignments_stmt = $pdo->prepare("
    SELECT da.*, u.name as agent_name, s.name as store_name, r.name as region_name
    FROM daily_assignments da
    JOIN users u ON da.agent_id = u.id
    JOIN stores s ON da.store_id = s.id
    LEFT JOIN regions r ON s.region_id = r.id
    WHERE DATE(da.date_assigned) = ?
    ORDER BY u.name, s.name
");
$assignments_stmt->execute([$today]);
$today_assignments = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - Collection Tracking</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="manifest" href="../manifest.json">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Assignments</h1>
            <div class="nav-links">
                <a href="dashboard.php">Home</a>
                <a href="assignments.php" class="active">Assignments</a>
                <a href="agents.php">Agents</a>
                <a href="management.php">Management</a>
                <a href="store_data.php">Store Data</a>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <div class="content">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <h2>Assign Agents to Entities</h2>
            <form method="post" action="">
                <input type="hidden" name="assign_shops" value="1">
                
                <div class="form-group">
                    <label for="assignment_date">Assignment Date:</label>
                    <input type="date" id="assignment_date" name="assignment_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="agent_id">Agent Name:</label>
                    <select id="agent_id" name="agent_id" required>
                        <option value="">Choose an agent</option>
                        <?php foreach ($agents as $agent): ?>
                            <option value="<?php echo $agent['id']; ?>"><?php echo htmlspecialchars($agent['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="target_amount">Target Amount per Assignment:</label>
                    <input type="number" id="target_amount" name="target_amount" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Select Assignment Details:</label>
                    <table class="assignment-table">
                        <thead>
                            <tr>
                                <th>Select</th>
                                <th>Region</th>
                                <th>Mall</th>
                                <th>Entity</th>
                                <th>Brand</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stores as $store): ?>
                                <tr>
                                    <td><input type="checkbox" id="store_<?php echo $store['id']; ?>" name="stores[]" value="<?php echo $store['id']; ?>"></td>
                                    <td><?php echo htmlspecialchars($store['region_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($store['mall'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($store['entity'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($store['brand'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <button type="submit" class="btn">Assign Entities</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Import Assignments via Excel</h2>
            <form method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="import_excel" value="1">
                
                <div class="form-group">
                    <label for="excel_file">Upload Excel File:</label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" required>
                    <small>Excel file should have columns: Agent Name, Region, Shops</small>
                </div>
                
                <button type="submit" class="btn">Import from Excel</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Today's Assignments (<?php echo date('M j, Y'); ?>)</h2>
            <?php if (count($today_assignments) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>Store</th>
                            <th>Region</th>
                            <th>Target Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_assignments as $assignment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($assignment['agent_name']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['store_name']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['region_name'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($assignment['target_amount'], 2); ?></td>
                                <td>
                                    <span class="status-<?php echo $assignment['status']; ?>">
                                        <?php 
                                            echo ucfirst(str_replace('_', ' ', $assignment['status'])); 
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No assignments for today.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
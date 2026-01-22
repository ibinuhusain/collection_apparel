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
        $file_tmp = $_FILES['excel_file']['tmp_name'];
        $file_type = $_FILES['excel_file']['type'];
        
        // Check if it's a valid Excel file
        if ($file_type == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' || 
            $file_type == 'application/vnd.ms-excel' ||
            pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION) === 'xlsx' ||
            pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION) === 'xls') {
            
            // Read CSV file (for simplicity)
            if (($handle = fopen($file_tmp, "r")) !== FALSE) {
                // Skip header row
                fgetcsv($handle, 1000, ",");
                
                $success_count = 0;
                $error_count = 0;
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Check if we have enough columns
                    if (count($data) >= 7) {
                        $agent_name = trim($data[0]);
                        $shop_name = trim($data[2]);
                        $mall_name = trim($data[3]);
                        $region_name = trim($data[4]);
                        $entity_name = trim($data[5]);
                        $brand = trim($data[6]);
                        
                        // Find agent by name
                        $agent_stmt = $pdo->prepare("SELECT id FROM users WHERE name = ? AND role = 'agent'");
                        $agent_stmt->execute([$agent_name]);
                        $agent_result = $agent_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($agent_result) {
                            $agent_id = $agent_result['id'];
                            
                            // Find mall by name
                            $mall_stmt = $pdo->prepare("SELECT id FROM malls WHERE name = ?");
                            $mall_stmt->execute([$mall_name]);
                            $mall_result = $mall_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $mall_id = $mall_result ? $mall_result['id'] : null;
                            
                            // Find region by name
                            $region_stmt = $pdo->prepare("SELECT id FROM regions WHERE name = ?");
                            $region_stmt->execute([$region_name]);
                            $region_result = $region_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $region_id = $region_result ? $region_result['id'] : null;
                            
                            // Find entity by name
                            $entity_stmt = $pdo->prepare("SELECT id FROM entities WHERE name = ?");
                            $entity_stmt->execute([$entity_name]);
                            $entity_result = $entity_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $entity_id = $entity_result ? $entity_result['id'] : null;
                            
                            // Find store by name and related fields
                            $store_query = "SELECT id FROM stores WHERE name = ?";
                            $store_params = [$shop_name];
                            
                            if ($mall_id) {
                                $store_query .= " AND mall_id = ?";
                                $store_params[] = $mall_id;
                            }
                            
                            if ($region_id) {
                                $store_query .= " AND region_id = ?";
                                $store_params[] = $region_id;
                            }
                            
                            if ($entity_id) {
                                $store_query .= " AND entity_id = ?";
                                $store_params[] = $entity_id;
                            }
                            
                            if ($brand) {
                                $store_query .= " AND brand = ?";
                                $store_params[] = $brand;
                            }
                            
                            $store_stmt = $pdo->prepare($store_query);
                            $store_stmt->execute($store_params);
                            $store_result = $store_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($store_result) {
                                $store_id = $store_result['id'];
                                
                                // Insert assignment for today
                                $today = date('Y-m-d');
                                $assignment_stmt = $pdo->prepare("INSERT INTO daily_assignments (agent_id, store_id, date_assigned, target_amount) VALUES (?, ?, ?, ?)");
                                $assignment_stmt->execute([$agent_id, $store_id, $today, 0]); // Default target amount to 0
                                $success_count++;
                            } else {
                                $error_count++; // Store not found
                            }
                        } else {
                            $error_count++; // Agent not found
                        }
                    } else {
                        $error_count++; // Not enough columns
                    }
                }
                fclose($handle);
                
                $success_message = "Import completed: $success_count assignments added successfully, $error_count errors occurred.";
            } else {
                $error_message = "Could not read the uploaded file.";
            }
        } else {
            $error_message = "Invalid file type. Please upload a CSV, XLS, or XLSX file.";
        }
    } else {
        $error_message = "Please select an Excel file to import.";
    }
}

// Handle clearing daily assignments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_daily_assignments'])) {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("DELETE FROM daily_assignments WHERE DATE(date_assigned) = ?");
    $stmt->execute([$today]);
    
    $success_message = "Daily assignments cleared successfully!";
}

// Get all agents
$agents_stmt = $pdo->query("SELECT id, name, username FROM users WHERE role = 'agent'");
$agents = $agents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all stores with related data
$stores_stmt = $pdo->query("
    SELECT s.id, s.name, s.mall, e.name as entity, s.brand, r.name as region_name 
    FROM stores s 
    LEFT JOIN entities e ON s.entity_id = e.id
    LEFT JOIN regions r ON s.region_id = r.id
");
$stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's assignments
$today = date('Y-m-d');
$assignments_stmt = $pdo->prepare("
    SELECT da.*, u.name as agent_name, s.name as store_name, r.name as region_name, s.mall, e.name as entity
    FROM daily_assignments da
    JOIN users u ON da.agent_id = u.id
    JOIN stores s ON da.store_id = s.id
    LEFT JOIN regions r ON s.region_id = r.id
    LEFT JOIN entities e ON s.entity_id = e.id
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
            
            <h2>Assignments - Import via Excel</h2>
            <form method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="import_excel" value="1">
                
                <div class="form-group">
                    <label for="excel_file">Upload Excel File:</label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" required>
                    <small>Excel file should have columns: AgentName, ID, Shop, Mall, Region, Entity, Brand</small>
                </div>
                
                <button type="submit" class="btn">Import Assignments from Excel</button>
            </form>
            
            <div style="margin-top: 20px;">
                <form method="post" action="">
                    <input type="hidden" name="clear_daily_assignments" value="1">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to clear today\'s assignments?')">Clear Daily Assignments</button>
                </form>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <h2>Today's Assignments (<?php echo date('M j, Y'); ?>)</h2>
            <?php if (count($today_assignments) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Agent Name</th>
                            <th>ID</th>
                            <th>Shop</th>
                            <th>Mall</th>
                            <th>Region</th>
                            <th>Entity</th>
                            <th>Brand</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_assignments as $assignment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($assignment['agent_name']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['agent_id']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['store_name']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['mall'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($assignment['region_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($assignment['entity'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($assignment['brand'] ?? 'N/A'); ?></td>
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
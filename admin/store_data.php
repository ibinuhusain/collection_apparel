<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_region'])) {
        $region_name = trim($_POST['region_name']);
        
        if (empty($region_name)) {
            $error = 'Region name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO regions (name) VALUES (?)");
                $stmt->execute([$region_name]);
                $message = 'Region added successfully!';
            } catch (PDOException $e) {
                $error = 'Error adding region: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_store'])) {
        $store_name = trim($_POST['store_name']);
        $mall = trim($_POST['mall']);
        $entity = trim($_POST['entity']);
        $brand = trim($_POST['brand']);
        $store_address = trim($_POST['store_address']);
        $region_id = $_POST['region_id'];
        
        if (empty($store_name) || empty($region_id)) {
            $error = 'Store name and region are required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO stores (name, mall, entity, brand, address, region_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$store_name, $mall, $entity, $brand, $store_address, $region_id]);
                $message = 'Store added successfully!';
            } catch (PDOException $e) {
                $error = 'Error adding store: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_store'])) {
        $store_id = $_POST['store_id'];
        $stmt = $pdo->prepare("DELETE FROM stores WHERE id = ?");
        $stmt->execute([$store_id]);
        $message = 'Store deleted successfully!';
    } elseif (isset($_POST['update_approval'])) {
        $submission_id = $_POST['submission_id'];
        $status = $_POST['status'];
        $approved_by = $_SESSION['user_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE bank_submissions SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $approved_by, $submission_id]);
            $message = 'Submission status updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating submission: ' . $e->getMessage();
        }
    }
}

// Get all regions
$regions_stmt = $pdo->query("SELECT * FROM regions ORDER BY name");
$regions = $regions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all stores with region names
$stores_stmt = $pdo->query("
    SELECT s.*, r.name as region_name 
    FROM stores s 
    LEFT JOIN regions r ON s.region_id = r.id 
    ORDER BY r.name, s.name
");
$stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending bank submissions for approval
$pending_submissions_stmt = $pdo->query("
    SELECT bs.*, u.name as agent_name, u.username as agent_username
    FROM bank_submissions bs
    JOIN users u ON bs.agent_id = u.id
    WHERE bs.status = 'pending'
    ORDER BY bs.created_at DESC
");
$pending_submissions = $pending_submissions_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Data - Collection Tracking</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="manifest" href="../manifest.json">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Store Data</h1>
            <div class="nav-links">
                <a href="dashboard.php">Home</a>
                <a href="assignments.php">Assignments</a>
                <a href="agents.php">Agents</a>
                <a href="management.php">Management</a>
                <a href="store_data.php" class="active">Store Data</a>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <h2>Add New Region</h2>
            <form method="post" action="">
                <input type="hidden" name="add_region" value="1">
                
                <div class="form-group">
                    <label for="region_name">Region Name:</label>
                    <input type="text" id="region_name" name="region_name" required>
                </div>
                
                <button type="submit" class="btn">Add Region</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Add New Store</h2>
            <form method="post" action="">
                <input type="hidden" name="add_store" value="1">
                
                <div class="form-group">
                    <label for="store_name">Store Name:</label>
                    <input type="text" id="store_name" name="store_name" required>
                </div>
                
                <div class="form-group">
                    <label for="mall">Mall:</label>
                    <input type="text" id="mall" name="mall">
                </div>
                
                <div class="form-group">
                    <label for="entity">Entity:</label>
                    <input type="text" id="entity" name="entity">
                </div>
                
                <div class="form-group">
                    <label for="brand">Brand:</label>
                    <input type="text" id="brand" name="brand">
                </div>
                
                <div class="form-group">
                    <label for="store_address">Store Address:</label>
                    <textarea id="store_address" name="store_address"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="region_id">Region:</label>
                    <select id="region_id" name="region_id" required>
                        <option value="">Select a region</option>
                        <?php foreach ($regions as $region): ?>
                            <option value="<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn">Add Store</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Regions</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Region Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($regions as $region): ?>
                        <tr>
                            <td><?php echo $region['id']; ?></td>
                            <td><?php echo htmlspecialchars($region['name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Stores</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Store Name</th>
                        <th>Mall</th>
                        <th>Entity</th>
                        <th>Brand</th>
                        <th>Address</th>
                        <th>Region</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stores as $store): ?>
                        <tr>
                            <td><?php echo $store['id']; ?></td>
                            <td><?php echo htmlspecialchars($store['name']); ?></td>
                            <td><?php echo htmlspecialchars($store['mall'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($store['entity'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($store['brand'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($store['address']); ?></td>
                            <td><?php echo htmlspecialchars($store['region_name'] ?? 'N/A'); ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this store?');">
                                    <input type="hidden" name="delete_store" value="1">
                                    <input type="hidden" name="store_id" value="<?php echo $store['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <hr style="margin: 30px 0;">
            
            <h2>Bank Submissions for Approval</h2>
            <?php if (count($pending_submissions) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>Amount</th>
                            <th>Receipt Image</th>
                            <th>Date Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_submissions as $submission): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($submission['agent_name']); ?> (<?php echo htmlspecialchars($submission['agent_username']); ?>)</td>
                                <td><?php echo number_format($submission['total_amount'], 2); ?></td>
                                <td>
                                    <?php if ($submission['receipt_image']): ?>
                                        <a href="../uploads/<?php echo htmlspecialchars($submission['receipt_image']); ?>" target="_blank">View Receipt</a>
                                    <?php else: ?>
                                        No receipt
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($submission['created_at'])); ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="update_approval" value="1">
                                        <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                        <select name="status" required>
                                            <option value="">Choose action</option>
                                            <option value="approved">Approve</option>
                                            <option value="rejected">Reject</option>
                                        </select>
                                        <button type="submit" class="btn">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No pending bank submissions for approval.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
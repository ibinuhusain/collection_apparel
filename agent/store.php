<?php
require_once '../includes/auth.php';
requireLogin();

if (hasRole('admin')) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$pdo = getConnection();
$agent_id = $_SESSION['user_id'];

// Get assignment ID from URL parameter
$assignment_id = $_GET['assignment_id'] ?? null;

if (!$assignment_id) {
    header("Location: dashboard.php");
    exit();
}

// Verify that this assignment belongs to the current agent
$stmt = $pdo->prepare("
    SELECT da.*, s.name as store_name, s.address as store_address, u.name as agent_name
    FROM daily_assignments da
    JOIN stores s ON da.store_id = s.id
    JOIN users u ON da.agent_id = u.id
    WHERE da.id = ? AND da.agent_id = ?
");
$stmt->execute([$assignment_id, $agent_id]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    header("Location: dashboard.php");
    exit();
}

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount_collected = floatval($_POST['amount_collected']);
    $pending_amount = floatval($_POST['pending_amount']);
    $mode_of_payment = trim($_POST['mode_of_payment']);
    $comments = trim($_POST['comments']);
    
    // Validate mode of payment
    $valid_modes = ['cash', 'cheque', 'online_transfer', 'credit_card', 'other'];
    if (!in_array($mode_of_payment, $valid_modes)) {
        $error = 'Please select a valid mode of payment.';
    }
    
    // Handle file uploads
    $zslip_images = [];
    if (isset($_FILES['zslip_images']) && $_FILES['zslip_images']['error'][0] !== UPLOAD_ERR_NO_FILE) {
        $upload_dir = '../uploads/';
        
        for ($i = 0; $i < count($_FILES['zslip_images']['name']); $i++) {
            if ($_FILES['zslip_images']['error'][$i] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['zslip_images']['tmp_name'][$i];
                $name = $_FILES['zslip_images']['name'][$i];
                
                // Sanitize filename
                $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
                    $new_filename = uniqid() . '_' . $name;
                    $destination = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($tmp_name, $destination)) {
                        $zslip_images[] = $new_filename;
                    } else {
                        $error = 'Failed to upload one or more images.';
                    }
                } else {
                    $error = 'Only JPG, JPEG, PNG, GIF, and PDF files are allowed.';
                }
            }
        }
    }
    
    if (empty($error)) {
        try {
            // Check if collection record already exists
            $check_stmt = $pdo->prepare("SELECT id FROM collections WHERE assignment_id = ?");
            $check_stmt->execute([$assignment_id]);
            $existing_collection = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_collection) {
                // Update existing collection
                $update_stmt = $pdo->prepare("
                    UPDATE collections 
                    SET amount_collected = ?, pending_amount = ?, mode_of_payment = ?, comments = ?, receipt_images = ?
                    WHERE assignment_id = ?
                ");
                $update_stmt->execute([
                    $amount_collected, 
                    $pending_amount, 
                    $mode_of_payment,
                    $comments, 
                    json_encode($zslip_images), 
                    $assignment_id
                ]);
            } else {
                // Insert new collection
                $insert_stmt = $pdo->prepare("
                    INSERT INTO collections (assignment_id, amount_collected, pending_amount, mode_of_payment, comments, receipt_images)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([
                    $assignment_id, 
                    $amount_collected, 
                    $pending_amount, 
                    $mode_of_payment,
                    $comments, 
                    json_encode($zslip_images)
                ]);
            }
            
            // Update assignment status to completed
            $update_assignment = $pdo->prepare("UPDATE daily_assignments SET status = 'completed' WHERE id = ?");
            $update_assignment->execute([$assignment_id]);
            
            $message = 'Store collection completed successfully!';
        } catch (PDOException $e) {
            $error = 'Error saving collection: ' . $e->getMessage();
        }
    }
}

// Get existing collection data if it exists
$collection = null;
$stmt = $pdo->prepare("SELECT * FROM collections WHERE assignment_id = ?");
$stmt->execute([$assignment_id]);
$collection = $stmt->fetch(PDO::FETCH_ASSOC);

// Set default values if no collection exists yet
$amount_collected = $collection ? $collection['amount_collected'] : 0;
$pending_amount = $collection ? $collection['pending_amount'] : 0;
$mode_of_payment = $collection ? $collection['mode_of_payment'] : '';
$comments = $collection ? $collection['comments'] : '';
$zslip_images = $collection ? json_decode($collection['receipt_images'], true) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Collection - Collection Tracking</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="manifest" href="../manifest.json">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Assigned Store Session</h1>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="store.php" class="active">Store</a>
                <a href="submissions.php">Submissions</a>
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
            
            <h2>Store Information</h2>
            <div class="card">
                <p><strong>Store Name:</strong> <?php echo htmlspecialchars($assignment['store_name']); ?></p>
                <p><strong>Region:</strong> <?php 
                    $store_details = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
                    $store_details->execute([$assignment['store_id']]);
                    $store_info = $store_details->fetch(PDO::FETCH_ASSOC);
                    echo htmlspecialchars($store_info['region_name'] ?? 'N/A'); 
                ?></p>
                <p><strong>Mall:</strong> <?php echo htmlspecialchars($store_info['mall'] ?? 'N/A'); ?></p>
                <p><strong>Entity:</strong> <?php echo htmlspecialchars($store_info['entity'] ?? 'N/A'); ?></p>
                <p><strong>Brand:</strong> <?php echo htmlspecialchars($store_info['brand'] ?? 'N/A'); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($assignment['store_address']); ?></p>
                <p><strong>Assigned Agent:</strong> <?php echo htmlspecialchars($assignment['agent_name']); ?></p>
                <p><strong>Target Amount:</strong> <?php echo number_format($assignment['target_amount'], 2); ?></p>
            </div>
            
            <h2>Assigned Store Session</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="amount_collected">Amount Collected:</label>
                    <input type="number" id="amount_collected" name="amount_collected" 
                           value="<?php echo $amount_collected; ?>" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="pending_amount">Pending Amount:</label>
                    <input type="number" id="pending_amount" name="pending_amount" 
                           value="<?php echo $pending_amount; ?>" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="mode_of_payment">Mode of Pending Payment:</label>
                    <select id="mode_of_payment" name="mode_of_payment" required>
                        <option value="">Select Mode</option>
                        <option value="cash" <?php echo ($collection && $collection['mode_of_payment'] === 'cash') ? 'selected' : ''; ?>>Cash</option>
                        <option value="cheque" <?php echo ($collection && $collection['mode_of_payment'] === 'cheque') ? 'selected' : ''; ?>>Cheque</option>
                        <option value="online_transfer" <?php echo ($collection && $collection['mode_of_payment'] === 'online_transfer') ? 'selected' : ''; ?>>Online Transfer</option>
                        <option value="credit_card" <?php echo ($collection && $collection['mode_of_payment'] === 'credit_card') ? 'selected' : ''; ?>>Credit Card</option>
                        <option value="other" <?php echo ($collection && $collection['mode_of_payment'] === 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="comments">Comment:</label>
                    <textarea id="comments" name="comments" rows="4"><?php echo htmlspecialchars($comments); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="zslip_images">Upload Z-Slip to Complete Store Collection:</label>
                    <input type="file" id="zslip_images" name="zslip_images[]" multiple accept="image/*,.pdf">
                    <small>Upload Z-slip images to complete the store collection (JPG, PNG, GIF, PDF)</small>
                </div>
                
                <button type="submit" class="btn">Complete Store Collection</button>
            </form>
            
            <?php if (!empty($zslip_images)): ?>
                <h2>Uploaded Z-Slips</h2>
                <div class="image-preview">
                    <?php foreach ($zslip_images as $img): ?>
                        <a href="../uploads/<?php echo htmlspecialchars($img); ?>" target="_blank">
                            <img src="../uploads/<?php echo htmlspecialchars($img); ?>" alt="Z-Slip">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <a href="dashboard.php" class="btn">Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
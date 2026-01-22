<?php
require_once '../includes/auth.php';
requireLogin();

if (hasRole('admin')) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$pdo = getConnection();
$agent_id = $_SESSION['user_id'];

// Handle form submission for bank submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_to_bank'])) {
    $total_amount = floatval($_POST['total_amount']);
    
    // Handle receipt image upload
    $receipt_image = null;
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        $tmp_name = $_FILES['receipt_image']['tmp_name'];
        $name = $_FILES['receipt_image']['name'];
        
        // Sanitize filename
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
            $new_filename = uniqid() . '_' . $name;
            $destination = $upload_dir . $new_filename;
            
            if (move_uploaded_file($tmp_name, $destination)) {
                $receipt_image = $new_filename;
            } else {
                $error = 'Failed to upload receipt image.';
            }
        } else {
            $error = 'Only JPG, JPEG, PNG, GIF, and PDF files are allowed.';
        }
    } else {
        $error = 'Receipt image is required.';
    }
    
    if (empty($error)) {
        try {
            // Insert bank submission
            $stmt = $pdo->prepare("
                INSERT INTO bank_submissions (agent_id, total_amount, receipt_image, status)
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$agent_id, $total_amount, $receipt_image]);
            
            // Mark all collections for today as submitted to bank
            $today = date('Y-m-d');
            $update_stmt = $pdo->prepare("
                UPDATE collections c
                JOIN daily_assignments da ON c.assignment_id = da.id
                SET c.submitted_to_bank = TRUE, c.submitted_at = NOW()
                WHERE da.agent_id = ? AND DATE(da.date_assigned) = ?
            ");
            $update_stmt->execute([$agent_id, $today]);
            
            $message = 'Bank submission sent for approval successfully!';
        } catch (PDOException $e) {
            $error = 'Error submitting to bank: ' . $e->getMessage();
        }
    }
}

// Get today's collections to calculate total amount
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT SUM(c.amount_collected) as total_collected
    FROM collections c
    JOIN daily_assignments da ON c.assignment_id = da.id
    WHERE da.agent_id = ? AND DATE(da.date_assigned) = ?
");
$stmt->execute([$agent_id, $today]);
$today_total = $stmt->fetchColumn() ?: 0;

// Get submission history
$history_stmt = $pdo->prepare("
    SELECT bs.*, u2.name as approved_by_name, 
           DATE_FORMAT(bs.approved_at, '%M %d, %Y at %h:%i %p') as formatted_approved_at,
           DATE_FORMAT(bs.created_at, '%M %d, %Y at %h:%i %p') as formatted_created_at
    FROM bank_submissions bs
    LEFT JOIN users u2 ON bs.approved_by = u2.id
    WHERE bs.agent_id = ?
    ORDER BY bs.created_at DESC
    LIMIT 10
");
$history_stmt->execute([$agent_id]);
$submission_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Submissions - Collection Tracking</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="manifest" href="../manifest.json">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bank Submissions</h1>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="store.php">Store</a>
                <a href="submissions.php" class="active">Submissions</a>
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
            
            <h2>Submit to Bank</h2>
            <div class="card">
                <p><strong>Today's Total Collected:</strong> <?php echo number_format($today_total, 2); ?></p>
                <p><strong>Date:</strong> <?php echo date('M j, Y'); ?></p>
            </div>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="submit_to_bank" value="1">
                
                <div class="form-group">
                    <label for="total_amount">Total Amount to Submit:</label>
                    <input type="number" id="total_amount" name="total_amount" 
                           value="<?php echo $today_total; ?>" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="receipt_image">Bank Deposit Receipt:</label>
                    <input type="file" id="receipt_image" name="receipt_image" accept="image/*,.pdf" required>
                    <small>Upload the bank deposit slip/receipt</small>
                </div>
                
                <button type="submit" class="btn btn-success">Submit to Bank</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Submission History</h2>
            <?php if (count($submission_history) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date Submitted</th>
                            <th>Amount</th>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th>Approved By</th>
                            <th>Approved At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submission_history as $submission): ?>
                            <tr>
                                <td><?php echo $submission['formatted_created_at']; ?></td>
                                <td><?php echo number_format($submission['total_amount'], 2); ?></td>
                                <td>
                                    <?php if ($submission['receipt_image']): ?>
                                        <a href="../uploads/<?php echo htmlspecialchars($submission['receipt_image']); ?>" target="_blank">View Receipt</a>
                                    <?php else: ?>
                                        No receipt
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $status_class = '';
                                    if ($submission['status'] === 'approved') {
                                        $status_class = 'status-approved';
                                    } elseif ($submission['status'] === 'rejected') {
                                        $status_class = 'status-rejected';
                                    } else {
                                        $status_class = 'status-pending';
                                    }
                                    ?>
                                    <span class="<?php echo $status_class; ?>">
                                        <?php echo ucfirst($submission['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $submission['approved_by_name'] ?? 'N/A'; ?></td>
                                <td><?php echo $submission['formatted_approved_at'] ?? 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No submission history found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
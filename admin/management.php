<?php
require_once '../includes/auth.php';
requireAdmin();

$pdo = getConnection();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $role = $_POST['role'];
        $password = $_POST['password'];
        
        if (empty($username) || empty($name) || empty($phone) || empty($role) || empty($password)) {
            $error = 'All fields are required.';
        } else {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, name, phone, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $name, $phone, $role]);
                $message = 'User added successfully!';
            } catch (PDOException $e) {
                $error = 'Error adding user: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        if ($user_id != $_SESSION['user_id']) { // Prevent deleting own account
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'"); // Prevent deleting other admins
            $stmt->execute([$user_id]);
            $message = 'User deleted successfully!';
        } else {
            $error = 'You cannot delete your own account.';
        }
    } elseif (isset($_POST['add_mall'])) {
        $mall_name = trim($_POST['mall_name']);
        $mall_location = trim($_POST['mall_location']);
        
        if (empty($mall_name)) {
            $error = 'Mall name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO malls (name, location) VALUES (?, ?)");
                $stmt->execute([$mall_name, $mall_location]);
                $message = 'Mall added successfully!';
            } catch (PDOException $e) {
                $error = 'Error adding mall: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_mall'])) {
        $mall_id = $_POST['mall_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM malls WHERE id = ?");
            $stmt->execute([$mall_id]);
            $message = 'Mall deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting mall: ' . $e->getMessage();
        }
    } elseif (isset($_POST['add_entity'])) {
        $entity_name = trim($_POST['entity_name']);
        
        if (empty($entity_name)) {
            $error = 'Entity name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO entities (name) VALUES (?)");
                $stmt->execute([$entity_name]);
                $message = 'Entity added successfully!';
            } catch (PDOException $e) {
                $error = 'Error adding entity: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_entity'])) {
        $entity_id = $_POST['entity_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM entities WHERE id = ?");
            $stmt->execute([$entity_id]);
            $message = 'Entity deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting entity: ' . $e->getMessage();
        }
    } elseif (isset($_POST['add_region'])) {
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
    } elseif (isset($_POST['delete_region'])) {
        $region_id = $_POST['region_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM regions WHERE id = ?");
            $stmt->execute([$region_id]);
            $message = 'Region deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting region: ' . $e->getMessage();
        }
    }
}

// Get all users except current admin
$users_stmt = $pdo->prepare("SELECT * FROM users WHERE id != ? ORDER BY role, name");
$users_stmt->execute([$_SESSION['user_id']]);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management - Collection Tracking</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="manifest" href="../manifest.json">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Management</h1>
            <div class="nav-links">
                <a href="dashboard.php">Home</a>
                <a href="assignments.php">Assignments</a>
                <a href="agents.php">Agents</a>
                <a href="management.php" class="active">Management</a>
                <a href="store_data.php">Store Data</a>
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
            
            <h2>Add New User</h2>
            <form method="post" action="">
                <input type="hidden" name="add_user" value="1">
                
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number:</label>
                    <input type="text" id="phone" name="phone" required>
                </div>
                
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="agent">Agent</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn">Add User</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <!-- Manage Users Section -->
            <h2>Manage Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="delete_user" value="1">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:gray">Self</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Manage Malls Section -->
            <hr style="margin: 30px 0;">
            
            <h2>Manage Malls</h2>
            <form method="post" action="">
                <input type="hidden" name="add_mall" value="1">
                
                <div class="form-group">
                    <label for="mall_name">Mall Name:</label>
                    <input type="text" id="mall_name" name="mall_name" required>
                </div>
                
                <div class="form-group">
                    <label for="mall_location">Location:</label>
                    <input type="text" id="mall_location" name="mall_location">
                </div>
                
                <button type="submit" class="btn">Add Mall</button>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $malls_stmt = $pdo->prepare("SELECT * FROM malls ORDER BY name");
                    $malls_stmt->execute();
                    $malls = $malls_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php foreach ($malls as $mall): ?>
                        <tr>
                            <td><?php echo $mall['id']; ?></td>
                            <td><?php echo htmlspecialchars($mall['name']); ?></td>
                            <td><?php echo htmlspecialchars($mall['location']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($mall['created_at'])); ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this mall?');">
                                    <input type="hidden" name="delete_mall" value="1">
                                    <input type="hidden" name="mall_id" value="<?php echo $mall['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Manage Entities Section -->
            <hr style="margin: 30px 0;">
            
            <h2>Manage Entities</h2>
            <form method="post" action="">
                <input type="hidden" name="add_entity" value="1">
                
                <div class="form-group">
                    <label for="entity_name">Entity Name:</label>
                    <input type="text" id="entity_name" name="entity_name" required>
                </div>
                
                <button type="submit" class="btn">Add Entity</button>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $entities_stmt = $pdo->prepare("SELECT * FROM entities ORDER BY name");
                    $entities_stmt->execute();
                    $entities = $entities_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php foreach ($entities as $entity): ?>
                        <tr>
                            <td><?php echo $entity['id']; ?></td>
                            <td><?php echo htmlspecialchars($entity['name']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($entity['created_at'])); ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this entity?');">
                                    <input type="hidden" name="delete_entity" value="1">
                                    <input type="hidden" name="entity_id" value="<?php echo $entity['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Manage Regions Section -->
            <hr style="margin: 30px 0;">
            
            <h2>Manage Regions</h2>
            <form method="post" action="">
                <input type="hidden" name="add_region" value="1">
                
                <div class="form-group">
                    <label for="region_name">Region Name:</label>
                    <input type="text" id="region_name" name="region_name" required>
                </div>
                
                <button type="submit" class="btn">Add Region</button>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $regions_stmt = $pdo->prepare("SELECT * FROM regions ORDER BY name");
                    $regions_stmt->execute();
                    $regions = $regions_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php foreach ($regions as $region): ?>
                        <tr>
                            <td><?php echo $region['id']; ?></td>
                            <td><?php echo htmlspecialchars($region['name']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($region['created_at'])); ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this region?');">
                                    <input type="hidden" name="delete_region" value="1">
                                    <input type="hidden" name="region_id" value="<?php echo $region['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
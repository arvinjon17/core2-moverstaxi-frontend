<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required functions with absolute paths
$functionsPath = __DIR__ . '/../functions/';
require_once $functionsPath . 'auth.php';
require_once $functionsPath . 'role_management.php';
require_once $functionsPath . 'db.php';

// Check if the user is logged in
if (!isLoggedIn()) {
    echo "<div class='alert alert-danger'>You must be logged in to access this page.</div>";
    exit();
}

// Simple storeroom page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Storeroom Page</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Storeroom Management (Simple Version)</h1>
        
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5>System Status</h5>
            </div>
            <div class="card-body">
                <p><strong>User ID:</strong> <?php echo $_SESSION['user_id'] ?? 'Not found'; ?></p>
                <p><strong>User Role:</strong> <?php echo $_SESSION['user_role'] ?? 'Not found'; ?></p>
                <p><strong>Database Connection:</strong> 
                <?php
                try {
                    $conn = connectToCore2DB();
                    echo '<span class="text-success">Connected successfully</span>';
                    
                    // Check if inventory_categories table exists
                    $result = $conn->query("SHOW TABLES LIKE 'inventory_categories'");
                    if ($result && $result->num_rows > 0) {
                        echo '<br><span class="text-success">inventory_categories table exists</span>';
                    } else {
                        echo '<br><span class="text-danger">inventory_categories table does not exist</span>';
                    }
                    
                    // Check if inventory_items table exists
                    $result = $conn->query("SHOW TABLES LIKE 'inventory_items'");
                    if ($result && $result->num_rows > 0) {
                        echo '<br><span class="text-success">inventory_items table exists</span>';
                    } else {
                        echo '<br><span class="text-danger">inventory_items table does not exist</span>';
                    }
                    
                    $conn->close();
                } catch (Exception $e) {
                    echo '<span class="text-danger">Connection failed: ' . $e->getMessage() . '</span>';
                }
                ?>
                </p>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5>Permission Check</h5>
            </div>
            <div class="card-body">
                <?php
                $permissions = [
                    'access_storeroom',
                    'view_storeroom',
                    'inventory_management',
                    'create_item',
                    'edit_item',
                    'delete_item'
                ];
                
                echo '<table class="table table-striped">';
                echo '<thead><tr><th>Permission</th><th>Status</th></tr></thead>';
                echo '<tbody>';
                
                foreach ($permissions as $permission) {
                    echo '<tr>';
                    echo '<td>' . $permission . '</td>';
                    echo '<td>';
                    if (function_exists('hasPermission')) {
                        if (hasPermission($permission)) {
                            echo '<span class="badge bg-success">Granted</span>';
                        } else {
                            echo '<span class="badge bg-danger">Denied</span>';
                        }
                    } else {
                        echo '<span class="badge bg-warning">hasPermission() function not found</span>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
                ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
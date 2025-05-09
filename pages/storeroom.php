<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use correct absolute paths for includes with dirname()
require_once dirname(__FILE__) . '/../functions/auth.php';
require_once dirname(__FILE__) . '/../functions/role_management.php';
require_once dirname(__FILE__) . '/../functions/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Store error message in session and redirect using JavaScript at end of file
    $_SESSION['error'] = "You need to log in to access this page";
    $redirectTo = "/login.php";
    $needRedirect = true;
} else if (!hasPermission('view_storeroom')) {
    // Check if user has permission to access this page
    $_SESSION['error'] = "You don't have permission to access this page";
    $redirectTo = "/index.php?page=dashboard";
    $needRedirect = true;
} else {
    $needRedirect = false;
    
    // Database operations for storeroom management
    $conn = connectToCore2DB();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle item creation
        if (isset($_POST['create_item'])) {
            // Sanitize inputs
            $itemName = sanitizeInput($_POST['item_name']);
            $category = sanitizeInput($_POST['category']);
            $quantity = (int)$_POST['quantity'];
            $unitPrice = (float)$_POST['unit_price'];
            $location = sanitizeInput($_POST['location']);
            $minStockLevel = (int)$_POST['min_stock_level'];
            $description = sanitizeInput($_POST['description']);
            $createdBy = (int)$_SESSION['user_id'];
    
            // Insert new item
            $sql = "INSERT INTO inventory_items (name, category, description, quantity, unit_price, location, minimum_stock, created_by, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssdssii", $itemName, $category, $description, $quantity, $unitPrice, $location, $minStockLevel, $createdBy);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Item added successfully!";
            } else {
                $_SESSION['error_message'] = "Error adding item: " . $conn->error;
            }
            $stmt->close();
            
            // Set JavaScript redirect
            $redirectTo = "/index.php?page=storeroom";
            $needRedirect = true;
        }
    
        // Handle item update
        if (isset($_POST['update_item'])) {
            // Sanitize inputs
            $itemId = (int)$_POST['item_id'];
            $itemName = sanitizeInput($_POST['item_name']);
            $category = sanitizeInput($_POST['category']);
            $quantity = (int)$_POST['quantity'];
            $unitPrice = (float)$_POST['unit_price'];
            $location = sanitizeInput($_POST['location']);
            $minStockLevel = (int)$_POST['min_stock_level'];
            $description = sanitizeInput($_POST['description']);
    
            // Update item
            $sql = "UPDATE inventory_items SET 
                    name = ?, 
                    category = ?, 
                    description = ?, 
                    quantity = ?, 
                    unit_price = ?, 
                    location = ?, 
                    minimum_stock = ?, 
                    updated_at = NOW() 
                    WHERE item_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssdssii", $itemName, $category, $description, $quantity, $unitPrice, $location, $minStockLevel, $itemId);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Item updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating item: " . $conn->error;
            }
            $stmt->close();
            
            // Set JavaScript redirect flag
            $redirectTo = "/index.php?page=storeroom";
            $needRedirect = true;
        }
    
        // Handle item deletion
        if (isset($_POST['delete_item'])) {
            $itemId = (int)$_POST['item_id'];
            
            $sql = "DELETE FROM inventory_items WHERE item_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $itemId);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Item deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Error deleting item: " . $conn->error;
            }
            $stmt->close();
            
            // Set JavaScript redirect
            $redirectTo = "/index.php?page=storeroom";
            $needRedirect = true;
        }
    }
    
    // Get inventory categories for dropdown
    $sql = "SELECT name FROM inventory_categories ORDER BY name";
    $categoriesResult = $conn->query($sql);
    $categories = [];
    if ($categoriesResult && $categoriesResult->num_rows > 0) {
        while ($row = $categoriesResult->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    // Get all inventory items
    $sql = "SELECT * FROM inventory_items ORDER BY name";
    $result = $conn->query($sql);
    $inventoryItems = [];
    $totalItems = 0;
    $totalValue = 0;
    $lowStockCount = 0;
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $inventoryItems[] = $row;
            $totalItems++;
            $totalValue += $row['quantity'] * $row['unit_price'];
            
            if ($row['quantity'] <= $row['minimum_stock']) {
                $lowStockCount++;
            }
        }
    }
    
    // Get total quantity of all items
    $sql = "SELECT SUM(quantity) as total_quantity FROM inventory_items";
    $quantityResult = $conn->query($sql);
    $totalQuantity = 0;
    if ($quantityResult && $row = $quantityResult->fetch_assoc()) {
        $totalQuantity = $row['total_quantity'] ?: 0;
    }
    
    $conn->close();
}

// If we need to redirect, we'll do it with JavaScript at the end of the file
?>

<!-- The content section will be loaded into the main content area of the page -->
<?php if (!$needRedirect): ?>
<div class="container-fluid px-4">
    <h1 class="mt-4">Storeroom Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
        <li class="breadcrumb-item active">Storeroom Management</li>
    </ol>
    
    <!-- View Toggle Buttons -->
    <div class="mb-4">
        <div class="btn-group" role="group" aria-label="View Toggle">
            <button type="button" class="btn btn-primary active" id="inventoryViewBtn">Inventory Management</button>
            <button type="button" class="btn btn-secondary" id="taxiViewBtn">Taxi Availability</button>
            <a href="index.php?page=playground_booking" class="btn btn-info text-white" id="playgroundBookingBtn">
                <i class="fas fa-futbol me-1"></i> Playground Booking
            </a>
        </div>
    </div>
    
    <!-- Dashboard Statistics -->
    <div id="inventoryView">
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card bg-primary text-white mb-4">
                    <div class="card-body">
                        <h2 class="text-center"><?= $totalItems ?></h2>
                        <p class="text-center mb-0">Total Unique Items</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-success text-white mb-4">
                    <div class="card-body">
                        <h2 class="text-center">₱<?= number_format($totalValue, 2) ?></h2>
                        <p class="text-center mb-0">Total Inventory Value</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-warning text-white mb-4">
                    <div class="card-body">
                        <h2 class="text-center"><?= $lowStockCount ?></h2>
                        <p class="text-center mb-0">Low Stock Items</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-danger text-white mb-4">
                    <div class="card-body">
                        <h2 class="text-center"><?= $totalQuantity ?></h2>
                        <p class="text-center mb-0">Total Quantity</p>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- Inventory Management -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-table me-1"></i>
                Inventory Management
                <button type="button" class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="fas fa-plus"></i> Add New Item
                </button>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success_message']; 
                        unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error_message']; 
                        unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <table id="inventoryTable" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total Value</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventoryItems as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['category']) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>₱<?= number_format($item['unit_price'], 2) ?></td>
                                <td>₱<?= number_format($item['quantity'] * $item['unit_price'], 2) ?></td>
                                <td><?= htmlspecialchars($item['location']) ?></td>
                                <td>
                                    <?php if ($item['quantity'] <= 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php elseif ($item['quantity'] <= $item['minimum_stock']): ?>
                                        <span class="badge bg-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info view-item" data-bs-toggle="modal" data-bs-target="#viewItemModal" 
                                        data-id="<?= $item['item_id'] ?>"
                                        data-name="<?= htmlspecialchars($item['name']) ?>"
                                        data-category="<?= htmlspecialchars($item['category']) ?>"
                                        data-quantity="<?= $item['quantity'] ?>"
                                        data-price="<?= $item['unit_price'] ?>"
                                        data-location="<?= htmlspecialchars($item['location']) ?>"
                                        data-minstock="<?= $item['minimum_stock'] ?>"
                                        data-description="<?= htmlspecialchars($item['description']) ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary edit-item" data-bs-toggle="modal" data-bs-target="#editItemModal" 
                                        data-id="<?= $item['item_id'] ?>"
                                        data-name="<?= htmlspecialchars($item['name']) ?>"
                                        data-category="<?= htmlspecialchars($item['category']) ?>"
                                        data-quantity="<?= $item['quantity'] ?>"
                                        data-price="<?= $item['unit_price'] ?>"
                                        data-location="<?= htmlspecialchars($item['location']) ?>"
                                        data-minstock="<?= $item['minimum_stock'] ?>"
                                        data-description="<?= htmlspecialchars($item['description']) ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger delete-item" data-bs-toggle="modal" data-bs-target="#deleteItemModal" 
                                        data-id="<?= $item['item_id'] ?>"
                                        data-name="<?= htmlspecialchars($item['name']) ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Taxi Availability View (Initially Hidden) -->
    <div id="taxiView" style="display: none;">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-map-marked-alt me-1"></i>
                        Taxi Availability Map
                        <button id="refresh-map-btn" class="btn btn-sm btn-primary float-end">
                            <i class="fas fa-sync-alt"></i> Refresh Map
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="input-group">
                                    <input type="text" id="destination-input" class="form-control" placeholder="Enter your destination">
                                    <button class="btn btn-primary" id="calculate-route-btn" type="button">Calculate Route</button>
                                </div>
                                <small class="text-muted">Or click on the map to set your destination</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-9">
                                <div id="map" style="height: 600px; width: 100%;"></div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-header">Route Information</div>
                                    <div class="card-body">
                                        <div id="route-info">
                                            <p class="text-muted">Select a taxi and destination to calculate route</p>
                                        </div>
                                        <hr>
                                        <div id="fare-info">
                                            <h5>Fare Estimate</h5>
                                            <div id="fare-details">
                                                <p class="text-muted">No route selected</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mt-3">
                                    <div class="card-header">Available Taxis</div>
                                    <div class="card-body">
                                        <div id="taxi-list">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p>Loading available taxis...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel">Add New Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="item_name" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="item_name" name="item_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category['name']) ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="unit_price" class="form-label">Unit Price (₱)</label>
                            <input type="number" class="form-control" id="unit_price" name="unit_price" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label for="min_stock_level" class="form-label">Min Stock Level</label>
                            <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" min="1" value="5" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="location" class="form-label">Storage Location</label>
                        <input type="text" class="form-control" id="location" name="location" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_item" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editItemModalLabel">Edit Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_item_id" name="item_id">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_item_name" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="edit_item_name" name="item_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_category" class="form-label">Category</label>
                            <select class="form-select" id="edit_category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category['name']) ?>"><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="edit_quantity" name="quantity" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_unit_price" class="form-label">Unit Price (₱)</label>
                            <input type="number" class="form-control" id="edit_unit_price" name="unit_price" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_min_stock_level" class="form-label">Min Stock Level</label>
                            <input type="number" class="form-control" id="edit_min_stock_level" name="min_stock_level" min="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_location" class="form-label">Storage Location</label>
                        <input type="text" class="form-control" id="edit_location" name="location" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_item" class="btn btn-primary">Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Item Modal -->
<div class="modal fade" id="viewItemModal" tabindex="-1" aria-labelledby="viewItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewItemModalLabel">Item Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <tr>
                        <th>Name:</th>
                        <td id="view_item_name"></td>
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td id="view_category"></td>
                    </tr>
                    <tr>
                        <th>Quantity:</th>
                        <td id="view_quantity"></td>
                    </tr>
                    <tr>
                        <th>Unit Price:</th>
                        <td id="view_unit_price"></td>
                    </tr>
                    <tr>
                        <th>Total Value:</th>
                        <td id="view_total_value"></td>
                    </tr>
                    <tr>
                        <th>Location:</th>
                        <td id="view_location"></td>
                    </tr>
                    <tr>
                        <th>Min Stock Level:</th>
                        <td id="view_min_stock_level"></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td id="view_status"></td>
                    </tr>
                    <tr>
                        <th>Description:</th>
                        <td id="view_description"></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Item Modal -->
<div class="modal fade" id="deleteItemModal" tabindex="-1" aria-labelledby="deleteItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteItemModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="delete_item_name"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="">
                    <input type="hidden" id="delete_item_id" name="item_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_item" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Google Maps API Script -->
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCS7rhxuCiYKeXpraOxq-GJCrYTmPiSaMU&libraries=places"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($needRedirect): ?>
        // Use JavaScript to redirect instead of PHP header()
        window.location.href = "<?= $redirectTo ?>";
        <?php else: ?>
        
        // Toggle between Inventory and Taxi views
        const inventoryViewBtn = document.getElementById('inventoryViewBtn');
        const taxiViewBtn = document.getElementById('taxiViewBtn');
        const inventoryView = document.getElementById('inventoryView');
        const taxiView = document.getElementById('taxiView');
        
        inventoryViewBtn.addEventListener('click', function() {
            inventoryView.style.display = 'block';
            taxiView.style.display = 'none';
            inventoryViewBtn.classList.add('active', 'btn-primary');
            inventoryViewBtn.classList.remove('btn-secondary');
            taxiViewBtn.classList.add('btn-secondary');
            taxiViewBtn.classList.remove('active', 'btn-primary');
        });
        
        taxiViewBtn.addEventListener('click', function() {
            inventoryView.style.display = 'none';
            taxiView.style.display = 'block';
            taxiViewBtn.classList.add('active', 'btn-primary');
            taxiViewBtn.classList.remove('btn-secondary');
            inventoryViewBtn.classList.add('btn-secondary');
            inventoryViewBtn.classList.remove('active', 'btn-primary');
            
            // Initialize map if it hasn't been yet
            if (!window.mapInitialized) {
                initMap();
                window.mapInitialized = true;
            }
        });
        
        // Initialize DataTable
        $('#inventoryTable').DataTable({
            responsive: true,
            order: [[0, 'asc']]
        });
        
        // Update view item modal
        $('.view-item').on('click', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const category = $(this).data('category');
            const quantity = $(this).data('quantity');
            const price = $(this).data('price');
            const location = $(this).data('location');
            const minStock = $(this).data('minstock');
            const description = $(this).data('description');
            
            // Log values for debugging
            console.log('View item clicked:', { id, name, category, quantity, price, location, minStock, description });
            
            $('#view_item_name').text(name);
            $('#view_category').text(category);
            $('#view_quantity').text(quantity);
            $('#view_unit_price').text('₱' + parseFloat(price).toFixed(2));
            $('#view_total_value').text('₱' + (parseFloat(price) * parseInt(quantity)).toFixed(2));
            $('#view_location').text(location);
            $('#view_min_stock_level').text(minStock);
            $('#view_description').text(description || 'No description provided');
            
            if (quantity <= 0) {
                $('#view_status').html('<span class="badge bg-danger">Out of Stock</span>');
            } else if (quantity <= minStock) {
                $('#view_status').html('<span class="badge bg-warning">Low Stock</span>');
            } else {
                $('#view_status').html('<span class="badge bg-success">In Stock</span>');
            }
        });
        
        // Update edit item modal
        $('.edit-item').on('click', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const category = $(this).data('category');
            const quantity = $(this).data('quantity');
            const price = $(this).data('price');
            const location = $(this).data('location');
            const minStock = $(this).data('minstock');
            const description = $(this).data('description');
            
            // Log values for debugging
            console.log('Edit item clicked:', { id, name, category, quantity, price, location, minStock, description });
            
            $('#edit_item_id').val(id);
            $('#edit_item_name').val(name);
            $('#edit_category').val(category);
            $('#edit_quantity').val(quantity);
            $('#edit_unit_price').val(parseFloat(price).toFixed(2));
            $('#edit_location').val(location);
            $('#edit_min_stock_level').val(minStock);
            $('#edit_description').val(description);
        });
        
        // Update delete item modal
        $('.delete-item').on('click', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            
            $('#delete_item_id').val(id);
            $('#delete_item_name').text(name);
        });
        
        // Variables for Google Maps
        let map;
        let directionsService;
        let directionsRenderer;
        let infoWindow;
        let markers = [];
        let selectedTaxi = null;
        let destinationMarker = null;
        let pickupMarker = null;
        
        // Fare calculation settings - in production these would come from the system_settings table
        const fareSettings = {
            baseFare: 40.00,
            perKmRate: 13.50,
            perMinuteRate: 2.00
        };
        
        // Initialize the Google Map
        function initMap() {
            // Center map on Metro Manila
            const metroManila = { lat: 14.5995, lng: 120.9842 };
            
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 12,
                center: metroManila,
                mapTypeControl: true,
                streetViewControl: false,
                fullscreenControl: true,
            });
            
            // Initialize directions service and renderer
            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({
                map: map,
                suppressMarkers: true,
                polylineOptions: {
                    strokeColor: "#0d6efd",
                    strokeWeight: 5,
                    strokeOpacity: 0.7
                }
            });
            
            // Initialize info window
            infoWindow = new google.maps.InfoWindow();
            
            // Fetch real taxi data from the database instead of using mock data
            fetchAvailableTaxis();
            
            // Add click listener to map for destination selection
            map.addListener("click", function(event) {
                if (!pickupMarker) {
                    setPickupPoint(event.latLng);
                    Swal.fire({
                        title: 'Pickup Point Set',
                        text: 'Now click on the map to set your destination',
                        icon: 'info',
                        timer: 3000,
                        showConfirmButton: false
                    });
                } else if (!destinationMarker) {
                    setDestination(event.latLng);
                } else {
                    // Ask user which marker they want to update
                    Swal.fire({
                        title: 'Update Which Point?',
                        text: 'Which point would you like to update?',
                        icon: 'question',
                        showDenyButton: true,
                        showCancelButton: true,
                        confirmButtonText: 'Pickup',
                        denyButtonText: 'Destination',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            setPickupPoint(event.latLng);
                        } else if (result.isDenied) {
                            setDestination(event.latLng);
                        }
                    });
                }
            });
            
            // Setup destination input with autocomplete
            const destinationInput = document.getElementById('destination-input');
            const autocomplete = new google.maps.places.Autocomplete(destinationInput, {
                componentRestrictions: { country: "ph" },
                fields: ["geometry", "name"],
                bounds: new google.maps.LatLngBounds(
                    { lat: 14.4, lng: 120.9 }, // Southwest bound
                    { lat: 14.8, lng: 121.1 }  // Northeast bound
                ),
                strictBounds: false
            });
            
            autocomplete.addListener("place_changed", function() {
                const place = autocomplete.getPlace();
                if (!place.geometry) {
                    alert("No location found for: " + place.name);
                    return;
                }
                setDestination(place.geometry.location);
            });
            
            // Setup calculate route button
            document.getElementById('calculate-route-btn').addEventListener('click', function() {
                const address = destinationInput.value;
                if (!address) {
                    alert("Please enter a destination");
                    return;
                }
                
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ address: address + ", Metro Manila, Philippines" }, function(results, status) {
                    if (status === "OK" && results[0]) {
                        setDestination(results[0].geometry.location);
                    } else {
                        alert("Geocode was not successful for the following reason: " + status);
                    }
                });
            });
            
            // Setup refresh button
            document.getElementById('refresh-map-btn').addEventListener('click', function() {
                refreshTaxiData();
            });
            
            // Set up auto-refresh every 30 seconds
            setInterval(refreshTaxiData, 30000);
        }
        
        // Fetch available taxis from the database
        function fetchAvailableTaxis() {
            // Show loading indicator
            document.getElementById('taxi-list').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading available taxis...</p>
                </div>
            `;
            
            // Use AJAX to fetch real data from the server
            fetch('api/taxis/available')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.length > 0) {
                        // Use the real taxi data
                        window.taxiData = data;
                    } else {
                        // Fallback to mock data if no real data is available
                        console.warn('No taxis found in database, using mock data');
                        window.taxiData = [
                            { id: 1, driverId: 1, vehicleId: 1, name: "Juan Dela Cruz", plate: "ABC-123", lat: 14.5995, lng: 120.9842, status: "available" }, // Manila
                            { id: 2, driverId: 2, vehicleId: 2, name: "Maria Santos", plate: "XYZ-789", lat: 14.5378, lng: 121.0014, status: "available" },   // Makati
                            { id: 3, driverId: 3, vehicleId: 4, name: "Pedro Reyes", plate: "DEF-456", lat: 14.6091, lng: 121.0223, status: "available" },   // Quezon City
                            { id: 4, driverId: 4, vehicleId: 3, name: "Ana Gonzales", plate: "GHI-789", lat: 14.5176, lng: 121.0509, status: "busy" },      // Taguig
                            { id: 5, driverId: 5, vehicleId: 5, name: "Jose Lim", plate: "JKL-012", lat: 14.5794, lng: 120.9788, status: "available" }       // Ermita
                        ];
                    }
                    
                    // Add taxi markers and update list
                    addTaxiMarkers();
                    updateTaxiList();
                })
                .catch(error => {
                    console.error('Error fetching taxis:', error);
                    // Fallback to mock data in case of error
                    window.taxiData = [
                        { id: 1, driverId: 1, vehicleId: 1, name: "Juan Dela Cruz", plate: "ABC-123", lat: 14.5995, lng: 120.9842, status: "available" }, // Manila
                        { id: 2, driverId: 2, vehicleId: 2, name: "Maria Santos", plate: "XYZ-789", lat: 14.5378, lng: 121.0014, status: "available" },   // Makati
                        { id: 3, driverId: 3, vehicleId: 4, name: "Pedro Reyes", plate: "DEF-456", lat: 14.6091, lng: 121.0223, status: "available" },   // Quezon City
                        { id: 4, driverId: 4, vehicleId: 3, name: "Ana Gonzales", plate: "GHI-789", lat: 14.5176, lng: 121.0509, status: "busy" },      // Taguig
                        { id: 5, driverId: 5, vehicleId: 5, name: "Jose Lim", plate: "JKL-012", lat: 14.5794, lng: 120.9788, status: "available" }       // Ermita
                    ];
                    
                    // Show error message in taxi list
                    document.getElementById('taxi-list').innerHTML = `
                        <div class="alert alert-warning">
                            <p><i class="fas fa-exclamation-triangle me-2"></i> Could not fetch real taxi data. Using sample data instead.</p>
                        </div>
                        <ul class="list-group">
                            ${window.taxiData.map(taxi => `
                                <li class="list-group-item" data-taxi-id="${taxi.id}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0">${taxi.name}</h6>
                                            <small>${taxi.plate}</small>
                                        </div>
                                        <span class="badge ${taxi.status === 'available' ? 'bg-success' : 'bg-danger'}">${taxi.status === 'available' ? 'Available' : 'Busy'}</span>
                                    </div>
                                </li>
                            `).join('')}
                        </ul>
                    `;
                    
                    // Add taxi markers and update list
                    addTaxiMarkers();
                    updateTaxiList();
                });
        }
        
        // Add taxi markers to the map
        function addTaxiMarkers() {
            // Clear existing markers
            markers.forEach(marker => marker.setMap(null));
            markers = [];
            
            window.taxiData.forEach(taxi => {
                const position = { lat: taxi.lat, lng: taxi.lng };
                const icon = {
                    url: taxi.status === 'available' ? 'https://maps.google.com/mapfiles/ms/icons/green-dot.png' : 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
                    scaledSize: new google.maps.Size(32, 32)
                };
                
                const marker = new google.maps.Marker({
                    position: position,
                    map: map,
                    icon: icon,
                    title: `${taxi.name} (${taxi.plate})`,
                    animation: google.maps.Animation.DROP
                });
                
                marker.set('taxiId', taxi.id);
                markers.push(marker);
                
                marker.addListener("click", function() {
                    if (taxi.status !== 'available') {
                        Swal.fire({
                            title: 'Taxi Unavailable',
                            text: 'This taxi is currently busy with another booking.',
                            icon: 'info'
                        });
                        return;
                    }
                    
                    selectedTaxi = taxi;
                    
                    // Format last updated time
                    const lastUpdatedStr = taxi.lastUpdated ? 
                        `Last updated: ${new Date(taxi.lastUpdated).toLocaleString()}` : '';
                    
                    // Open info window
                    infoWindow.setContent(`
                        <div style="width: 200px; text-align: center;">
                            <h6 style="margin-bottom: 5px;">${taxi.name}</h6>
                            <p style="margin-bottom: 5px;">Plate: ${taxi.plate}</p>
                            <p style="margin-bottom: 5px; font-size: 12px; color: #666;">${lastUpdatedStr}</p>
                            <button id="select-taxi-btn" class="btn btn-sm btn-primary">Select This Taxi</button>
                        </div>
                    `);
                    infoWindow.open(map, marker);
                    
                    // Add event listener to the select button inside info window
                    google.maps.event.addListenerOnce(infoWindow, 'domready', function() {
                        document.getElementById('select-taxi-btn').addEventListener('click', function() {
                            if (!pickupMarker && !destinationMarker) {
                                Swal.fire({
                                    title: 'Set Pickup & Destination',
                                    text: 'Please click on the map to set your pickup point and destination.',
                                    icon: 'info'
                                });
                            } else if (!pickupMarker) {
                                Swal.fire({
                                    title: 'Set Pickup Point',
                                    text: 'Please click on the map to set your pickup point.',
                                    icon: 'info'
                                });
                            } else if (!destinationMarker) {
                                Swal.fire({
                                    title: 'Set Destination',
                                    text: 'Please click on the map to set your destination.',
                                    icon: 'info'
                                });
                            } else {
                                calculateRoute(taxi, pickupMarker.getPosition(), destinationMarker.getPosition());
                            }
                            infoWindow.close();
                        });
                    });
                    
                    // Highlight the selected taxi in the list
                    updateTaxiList(taxi.id);
                });
            });
        }
        
        // Set pickup point
        function setPickupPoint(location) {
            // Remove existing pickup marker
            if (pickupMarker) {
                pickupMarker.setMap(null);
            }
            
            // Create new pickup marker
            pickupMarker = new google.maps.Marker({
                position: location,
                map: map,
                draggable: true,
                animation: google.maps.Animation.DROP,
                icon: {
                    url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
                    scaledSize: new google.maps.Size(32, 32)
                }
            });
            
            // Add info window to the pickup marker
            const pickupInfoWindow = new google.maps.InfoWindow({
                content: '<div style="text-align: center;"><strong>Pickup Point</strong><br><small>Drag to adjust</small></div>'
            });
            pickupInfoWindow.open(map, pickupMarker);
            setTimeout(() => pickupInfoWindow.close(), 3000);
            
            // Add drag end listener to update route
            pickupMarker.addListener('dragend', function() {
                if (selectedTaxi && destinationMarker) {
                    calculateRoute(selectedTaxi, pickupMarker.getPosition(), destinationMarker.getPosition());
                }
            });
            
            // If destination marker and taxi already selected, calculate route
            if (selectedTaxi && destinationMarker) {
                calculateRoute(selectedTaxi, location, destinationMarker.getPosition());
            }
        }
        
        // Set destination marker and update route if taxi is selected
        function setDestination(location) {
            // Remove existing destination marker
            if (destinationMarker) {
                destinationMarker.setMap(null);
            }
            
            // Create new destination marker
            destinationMarker = new google.maps.Marker({
                position: location,
                map: map,
                draggable: true,
                animation: google.maps.Animation.DROP,
                icon: {
                    url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                    scaledSize: new google.maps.Size(32, 32)
                }
            });
            
            // Add info window to the destination marker
            const destInfoWindow = new google.maps.InfoWindow({
                content: '<div style="text-align: center;"><strong>Destination</strong><br><small>Drag to adjust</small></div>'
            });
            destInfoWindow.open(map, destinationMarker);
            setTimeout(() => destInfoWindow.close(), 3000);
            
            // Add drag end listener to update route
            destinationMarker.addListener('dragend', function() {
                if (selectedTaxi && pickupMarker) {
                    calculateRoute(selectedTaxi, pickupMarker.getPosition(), destinationMarker.getPosition());
                }
            });
            
            // Calculate route if a taxi is already selected and pickup point exists
            if (selectedTaxi && pickupMarker) {
                calculateRoute(selectedTaxi, pickupMarker.getPosition(), location);
            }
        }
        
        // Calculate route between pickup and destination
        function calculateRoute(taxi, pickup, destination) {
            const taxiLocation = { lat: taxi.lat, lng: taxi.lng };
            
            // First route: Taxi to pickup point
            directionsService.route(
                {
                    origin: taxiLocation,
                    destination: pickup,
                    travelMode: google.maps.TravelMode.DRIVING,
                    provideRouteAlternatives: false
                },
                function(pickupResponse, pickupStatus) {
                    if (pickupStatus === "OK") {
                        // Second route: Pickup to destination
                        directionsService.route(
                            {
                                origin: pickup,
                                destination: destination,
                                travelMode: google.maps.TravelMode.DRIVING,
                                provideRouteAlternatives: false
                            },
                            function(destResponse, destStatus) {
                                if (destStatus === "OK") {
                                    // Use the pickup to destination route for display
                                    directionsRenderer.setDirections(destResponse);
                                    
                                    const pickupLeg = pickupResponse.routes[0].legs[0];
                                    const destLeg = destResponse.routes[0].legs[0];
                                    
                                    // Calculate fare for the customer route (pickup to destination)
                                    const distance = destLeg.distance.value / 1000; // Convert to kilometers
                                    const duration = destLeg.duration.value / 60; // Convert to minutes
                                    const fare = calculateFare(distance, duration);
                                    
                                    // Update route info
                                    document.getElementById('route-info').innerHTML = `
                                        <h5 class="card-title">Route Details</h5>
                                        <p><strong>Taxi arrives in:</strong> ${pickupLeg.duration.text}</p>
                                        <p><strong>Trip distance:</strong> ${destLeg.distance.text}</p>
                                        <p><strong>Trip duration:</strong> ${destLeg.duration.text}</p>
                                        <p><strong>From:</strong> ${destLeg.start_address}</p>
                                        <p><strong>To:</strong> ${destLeg.end_address}</p>
                                    `;
                                    
                                    // Update fare info
                                    document.getElementById('fare-details').innerHTML = `
                                        <p><strong>Base Fare:</strong> ₱${fareSettings.baseFare.toFixed(2)}</p>
                                        <p><strong>Distance Charge:</strong> ₱${(distance * fareSettings.perKmRate).toFixed(2)}</p>
                                        <p><strong>Time Charge:</strong> ₱${(duration * fareSettings.perMinuteRate).toFixed(2)}</p>
                                        <hr>
                                        <h4 class="text-primary">Total: ₱${fare.toFixed(2)}</h4>
                                        <button id="book-now-btn" class="btn btn-success mt-3 w-100">Book Now</button>
                                    `;
                                    
                                    // Add event listener for booking
                                    document.getElementById('book-now-btn').addEventListener('click', function() {
                                        Swal.fire({
                                            title: 'Booking Confirmed',
                                            text: `Your taxi is on the way! Fare estimate: ₱${fare.toFixed(2)}`,
                                            icon: 'success'
                                        });
                                    });
                                    
                                    // Add markers for taxi, pickup, and destination
                                    markers.forEach(m => {
                                        if (m.get('taxiId') === taxi.id) {
                                            m.setAnimation(google.maps.Animation.BOUNCE);
                                            setTimeout(() => m.setAnimation(null), 1500);
                                        }
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Routing Error',
                                        text: 'Could not calculate route to destination: ' + destStatus,
                                        icon: 'error'
                                    });
                                }
                            }
                        );
                    } else {
                        Swal.fire({
                            title: 'Routing Error',
                            text: 'Could not calculate route to pickup: ' + pickupStatus,
                            icon: 'error'
                        });
                    }
                }
            );
        }
        
        // Calculate fare based on distance and duration
        function calculateFare(distance, duration) {
            return fareSettings.baseFare + (distance * fareSettings.perKmRate) + (duration * fareSettings.perMinuteRate);
        }
        
        // Update the taxi list in the sidebar
        function updateTaxiList(selectedId = null) {
            const taxiListElement = document.getElementById('taxi-list');
            let listHTML = '<ul class="list-group">';
            
            window.taxiData.forEach(taxi => {
                const isSelected = selectedId === taxi.id;
                const statusClass = taxi.status === 'available' ? 'text-success' : 'text-danger';
                const statusText = taxi.status === 'available' ? 'Available' : 'Busy';
                const selectedClass = isSelected ? 'active' : '';
                
                // Format last updated time to be more user-friendly
                let lastUpdatedStr = '';
                if (taxi.lastUpdated) {
                    const lastUpdatedDate = new Date(taxi.lastUpdated);
                    const now = new Date();
                    const diffMs = now - lastUpdatedDate;
                    const diffMins = Math.floor(diffMs / 60000);
                    
                    if (diffMins < 5) {
                        lastUpdatedStr = 'Updated just now';
                    } else if (diffMins < 60) {
                        lastUpdatedStr = `Updated ${diffMins} mins ago`;
                    } else if (diffMins < 1440) {
                        const hours = Math.floor(diffMins / 60);
                        lastUpdatedStr = `Updated ${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;
                    } else {
                        lastUpdatedStr = `Updated on ${lastUpdatedDate.toLocaleDateString()}`;
                    }
                }
                
                listHTML += `
                    <li class="list-group-item ${selectedClass}" data-taxi-id="${taxi.id}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">${taxi.name}</h6>
                                <small>${taxi.plate} - ${taxi.model}</small>
                                ${lastUpdatedStr ? `<div class="text-muted small">${lastUpdatedStr}</div>` : ''}
                            </div>
                            <span class="badge ${taxi.status === 'available' ? 'bg-success' : 'bg-danger'}">${statusText}</span>
                        </div>
                    </li>
                `;
            });
            
            listHTML += '</ul>';
            taxiListElement.innerHTML = listHTML;
            
            // Add click event to taxi list items
            document.querySelectorAll('#taxi-list li').forEach(item => {
                item.addEventListener('click', function() {
                    const taxiId = parseInt(this.getAttribute('data-taxi-id'));
                    const taxi = window.taxiData.find(t => t.id === taxiId);
                    
                    if (taxi.status !== 'available') {
                        Swal.fire({
                            title: 'Taxi Unavailable',
                            text: 'This taxi is currently busy with another booking.',
                            icon: 'info'
                        });
                        return;
                    }
                    
                    // Find and trigger click on the corresponding marker
                    const marker = markers.find(m => m.get('taxiId') === taxiId);
                    if (marker) {
                        google.maps.event.trigger(marker, 'click');
                    }
                    
                    // Highlight the selected taxi in the list
                    document.querySelectorAll('#taxi-list li').forEach(li => {
                        li.classList.remove('active');
                    });
                    this.classList.add('active');
                });
            });
        }
        
        // Add a function to refresh the taxi data
        function refreshTaxiData() {
            // Show loading spinner in the refresh button
            const refreshBtn = document.getElementById('refresh-map-btn');
            const originalContent = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Refreshing...';
            refreshBtn.disabled = true;
            
            fetch('api/taxis/available')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.length > 0) {
                        window.taxiData = data;
                        addTaxiMarkers();
                        updateTaxiList();
                        
                        // Show success toast
                        Swal.fire({
                            title: 'Map Updated',
                            text: `Found ${data.length} available taxis`,
                            icon: 'success',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    } else {
                        console.warn('No taxis found during refresh, keeping existing data');
                        
                        // Show warning toast
                        Swal.fire({
                            title: 'No Taxis Found',
                            text: 'Using existing taxi data',
                            icon: 'warning',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                })
                .catch(error => {
                    console.error('Error refreshing taxi data:', error);
                    
                    // Show error toast
                    Swal.fire({
                        title: 'Refresh Failed',
                        text: 'Could not update taxi locations',
                        icon: 'error',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                })
                .finally(() => {
                    // Restore refresh button
                    refreshBtn.innerHTML = originalContent;
                    refreshBtn.disabled = false;
                });
        }
        
        <?php endif; ?>
    });
</script>
<?php endif; ?>

<!-- API endpoints for taxi management -->
<?php
// Simple API endpoint for getting available taxis
// In a production environment, this would be in a separate file with proper routing
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'api/taxis/available') !== false) {
    header('Content-Type: application/json');
    
    // Connect to database
    $conn = connectToCore1DB();
    $core2Conn = connectToCore2DB();
    
    if (!$conn || !$core2Conn) {
        echo json_encode([
            'error' => 'Database connection failed'
        ]);
        exit;
    }
    
    // Get available taxis with location data
    // Method 1: Using the drivers table directly (if latitude/longitude columns are present)
    $sql = "SELECT 
        d.driver_id, 
        d.user_id, 
        d.status, 
        d.latitude, 
        d.longitude, 
        d.location_updated_at,
        v.vehicle_id, 
        v.plate_number, 
        v.model, 
        v.year 
    FROM drivers d 
    LEFT JOIN vehicles v ON v.assigned_driver_id = d.driver_id 
    WHERE d.status = 'available'
    AND d.latitude IS NOT NULL 
    AND d.longitude IS NOT NULL";
    
    $result = $conn->query($sql);
    $driversFromDriversTable = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $driversFromDriversTable[] = $row;
        }
    }
    
    // Method 2: Using the driver_locations table (if it exists)
    $sql2 = "SELECT 
        d.driver_id, 
        d.user_id, 
        d.status,
        v.vehicle_id, 
        v.plate_number, 
        v.model, 
        v.year,
        dl.latitude, 
        dl.longitude, 
        dl.last_updated as location_updated_at
    FROM drivers d 
    LEFT JOIN vehicles v ON v.assigned_driver_id = d.driver_id 
    LEFT JOIN driver_locations dl ON d.driver_id = dl.driver_id
    WHERE d.status = 'available'
    AND dl.latitude IS NOT NULL 
    AND dl.longitude IS NOT NULL";
    
    $result2 = $conn->query($sql2);
    $driversFromLocationsTable = [];
    
    if ($result2 && $result2->num_rows > 0) {
        while ($row = $result2->fetch_assoc()) {
            $driversFromLocationsTable[] = $row;
        }
    }
    
    // Use drivers from driver_locations table if available, otherwise use drivers table
    $driversData = !empty($driversFromLocationsTable) ? $driversFromLocationsTable : $driversFromDriversTable;
    
    // If both queries failed or returned no results, check if the driver_locations table exists
    if (empty($driversData)) {
        $checkTableQuery = "SHOW TABLES LIKE 'driver_locations'";
        $tableResult = $conn->query($checkTableQuery);
        $driverLocationsTableExists = ($tableResult && $tableResult->num_rows > 0);
        
        // Log for debugging
        error_log("Storeroom: driver_locations table " . ($driverLocationsTableExists ? "exists" : "does not exist"));
    }
    
    $taxis = [];
    
    if (!empty($driversData)) {
        foreach ($driversData as $driver) {
            // Get user details
            $userSql = "SELECT firstname, lastname FROM users WHERE user_id = " . $driver['user_id'];
            $userResult = $core2Conn->query($userSql);
            $userData = $userResult->fetch_assoc();
            
            if ($userData) {
                $taxis[] = [
                    'id' => $driver['driver_id'],
                    'driverId' => $driver['driver_id'],
                    'vehicleId' => $driver['vehicle_id'],
                    'name' => $userData['firstname'] . ' ' . $userData['lastname'],
                    'plate' => $driver['plate_number'],
                    'model' => $driver['model'] . ' ' . $driver['year'],
                    'lat' => (float)$driver['latitude'],
                    'lng' => (float)$driver['longitude'],
                    'lastUpdated' => $driver['location_updated_at'],
                    'status' => $driver['status']
                ];
            }
        }
    }
    
    // If no taxis found, provide sample data
    if (empty($taxis)) {
        error_log("Storeroom: No taxis found in database, using sample data");
        
        $taxis = [
            [
                'id' => 1, 
                'driverId' => 1, 
                'vehicleId' => 1, 
                'name' => "Juan Dela Cruz", 
                'plate' => "ABC-123", 
                'lat' => 14.5995, 
                'lng' => 120.9842, 
                'status' => "available",
                'model' => "Toyota Camry 2020",
                'lastUpdated' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 2, 
                'driverId' => 2, 
                'vehicleId' => 2, 
                'name' => "Maria Santos", 
                'plate' => "XYZ-789", 
                'lat' => 14.5378, 
                'lng' => 121.0014, 
                'status' => "available",
                'model' => "Honda Civic 2019",
                'lastUpdated' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 3, 
                'driverId' => 3, 
                'vehicleId' => 4, 
                'name' => "Pedro Reyes", 
                'plate' => "DEF-456", 
                'lat' => 14.6091, 
                'lng' => 121.0223, 
                'status' => "available",
                'model' => "Mitsubishi Mirage 2018",
                'lastUpdated' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 4, 
                'driverId' => 4, 
                'vehicleId' => 3, 
                'name' => "Ana Gonzales", 
                'plate' => "GHI-789", 
                'lat' => 14.5176, 
                'lng' => 121.0509, 
                'status' => "busy",
                'model' => "Toyota Fortuner 2021",
                'lastUpdated' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 5, 
                'driverId' => 5, 
                'vehicleId' => 5, 
                'name' => "Jose Lim", 
                'plate' => "JKL-012", 
                'lat' => 14.5794, 
                'lng' => 120.9788, 
                'status' => "available",
                'model' => "Ford Everest 2022",
                'lastUpdated' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    // Close database connections
    if ($conn) $conn->close();
    if ($core2Conn) $core2Conn->close();
    
    echo json_encode($taxis);
    exit;
}

// Add JavaScript redirect if needed
if (isset($needRedirect) && $needRedirect) {
    echo '<script>window.location.href = "' . $redirectTo . '";</script>';
    exit;
}
?> 
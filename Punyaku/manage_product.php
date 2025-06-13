<?php
require_once 'config.php';
requireAuth();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_game'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $base_price = (float)$_POST['base_price'];
        $stock = (int)$_POST['stock'];
        
        $stmt = $conn->prepare("INSERT INTO games (name, description, base_price, stock) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdi", $name, $description, $base_price, $stock);
        
        if ($stmt->execute()) {
            $success_message = "Game added successfully!";
        } else {
            $error_message = "Error adding game: " . $conn->error;
        }
    }
    
    if (isset($_POST['update_game'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $base_price = (float)$_POST['base_price'];
        $stock = (int)$_POST['stock'];
        
        $stmt = $conn->prepare("UPDATE games SET name = ?, description = ?, base_price = ?, stock = ? WHERE id = ?");
        $stmt->bind_param("ssdii", $name, $description, $base_price, $stock, $id);
        
        if ($stmt->execute()) {
            $success_message = "Game updated successfully!";
        } else {
            $error_message = "Error updating game: " . $conn->error;
        }
    }
    
    if (isset($_POST['delete_game'])) {
        $id = (int)$_POST['id'];
        
        // Check if game has transactions
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions WHERE game_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            $error_message = "Cannot delete game with existing transactions!";
        } else {
            $stmt = $conn->prepare("DELETE FROM games WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success_message = "Game deleted successfully!";
            } else {
                $error_message = "Error deleting game: " . $conn->error;
            }
        }
    }
}

// Get games with pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
$types = '';

if ($search) {
    $where_clause = "WHERE name LIKE ? OR description LIKE ?";
    $params = ["%$search%", "%$search%"];
    $types = 'ss';
}

// Get total games
$total_query = "SELECT COUNT(*) as total FROM games $where_clause";
if ($params) {
    $total_stmt = $conn->prepare($total_query);
    $total_stmt->bind_param($types, ...$params);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
} else {
    $total_result = $conn->query($total_query);
}
$total = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

// Get games
$games_query = "SELECT * FROM games $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
if ($params) {
    $games_stmt = $conn->prepare($games_query);
    $games_stmt->bind_param($types, ...$params);
    $games_stmt->execute();
    $games = $games_stmt->get_result();
} else {
    $games = $conn->query($games_query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Product</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Global styles */
        :root {
            --primary-color: #6c5ce7;
            --dark-color: #2d3436;
            --light-color: #f7f7f7;
            --sidebar-width: 250px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            color: #333;
        }

        img {
            width: 105px;
            height: 65px;
            margin-top: 20px;
            margin-bottom: 17px;
            margin-left: 55px;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 80px;
            height: 100%;
            background: linear-gradient(135deg, #2a0845 0%, #6441a5 100%);
            backdrop-filter: blur(40px);
            border-right: 2px solid rgba(255, 20, 147, 0.3); /* Pink magenta border */
            box-shadow: 0 0 20px rgba(139, 0, 139, 0.5); /* Dark purple shadow */
            padding: 6px 14px;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 100;
        }

        .sidebar.active {
            width: 260px;
            background: linear-gradient(135deg, #1a052e 0%, #4b2a8a 100%);
        }

        .sidebar .logo-menu {
            display: flex;
            align-items: center;
            width: 100%;
            height: 70px;
            border-bottom: 1px solid rgba(255, 20, 147, 0.2); /* Pink magenta subtle divider */
        }

        .sidebar .logo-menu .logo {
            font-size: 25px;
            color: #ff1493; /* Pink magenta */
            font-weight: 600;
            pointer-events: none;
            opacity: 0;
            transition: all 0.3s ease;
            text-shadow: 0 0 10px rgba(255, 20, 147, 0.5);
        }

        .sidebar.active .logo-menu .logo {
            opacity: 1;
            transition-delay: 0.2s;
        }

        .sidebar .logo-menu .toggle-btn {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            font-size: 30px;
            color: #ff1493; /* Pink magenta */
            text-align: center;
            line-height: 40px;
            cursor: pointer;
            transition: all 0.5s ease;
            background: rgba(106, 13, 173, 0.3);
            border-radius: 50%;
        }

        .sidebar.active .logo-menu .toggle-btn {
            left: 90%;
            background: rgba(255, 20, 147, 0.2);
        }

        .sidebar .logo-menu .toggle-btn:hover {
            color: #ff69b4; /* Lighter pink */
            background: rgba(255, 20, 147, 0.3);
        }

        .sidebar .list {
            margin-top: 30px;
        }

        .list .list-item {
            list-style: ;
            width: 100%;
            height: 50px;
            margin: 10px 0;
            line-height: 50px;
        }

        .list .list-item a {
            display: flex;
            align-items: center;
            text-align: none;
            font-size: 18px;
            color: #e2b4ff; /* Light purple text */
            text-decoration: none;
            border-radius: 6px;
            white-space: nowrap;
            transition: all 0.3s ease;
            padding: 0 10px;
        }

        .list .list-item.active a,
        .list .list-item a:hover {
            background: linear-gradient(90deg, rgba(255, 20, 147, 0.3) 0%, rgba(106, 13, 173, 0.3) 100%);
            color: #fff;
            box-shadow: 0 5px 15px rgba(139, 0, 139, 0.4);
        }

        .list .list-item a i {
            min-width: 30px;
            height: 50px;
            text-align: center;
            line-height: 50px;
            color: #ff1493; /* Pink magenta icons */
            font-size: 22px;
        }

        .list .list-item.active a i,
        .list .list-item a:hover i {
            color: #ff69b4; /* Lighter pink on hover/active */
        }

        .sidebar .link-name {
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar.active .link-name {
            margin-left: 8px;
            opacity: 1;
            pointer-events: auto;
            transition-delay: calc(0.1s * var(--i));
        }

        .main-content {
            margin-left: 80px;
            padding: 20px;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            background-color: #f8f9fc;
        }

        .sidebar.active + .main-content {
            margin-left: 260px;
        }

        /* Add glowing effect on active/hover */
        @keyframes glow {
            0% { box-shadow: 0 0 5px rgba(255, 20, 147, 0.5); }
            50% { box-shadow: 0 0 20px rgba(255, 20, 147, 0.8); }
            100% { box-shadow: 0 0 5px rgba(255, 20, 147, 0.5); }
        }

        .list .list-item.active a {
            animation: glow 2s infinite;
        }

        .main-content {
            margin-left: 80px;
            padding: 20px;
            transition: .5s;
        }

        .sidebar.active + .main-content {
            margin-left: 260px;
        }

        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            background-color: #f0f2f5;
            min-height: 100vh;
        }

        /* Topbar Styles */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 25px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            border-radius: 10px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .topbar h2 {
            font-weight: 600;
            color: #1a237e;
        }

        .search-container {
            position: relative;
            width: 300px;
        }

        .search-container input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-container input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(92, 107, 192, 0.2);
        }

        .search-container i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
        }

        .topbar-icons {
            display: flex;
            gap: 20px;
        }

        .topbar-icons i {
            font-size: 20px;
            color: #5c6bc0;
            cursor: pointer;
            transition: color 0.3s;
        }

        .topbar-icons i:hover {
            color: #1a237e;
        }

        /* Content Card */
        .content-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        /* Add Product Button */
        .add-product-btn {
            background: linear-gradient(135deg, #6c5ce7 0%, #4834d4 50%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 60;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            box-shadow: 0 4px 10px rgba(92, 107, 192, 0.3);
            font-size: 1rem;
            margin: 20px;
            position: fixed;
            bottom: 0;
            right: 0;
            z-index: 99;
        }

        .add-product-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(82, 107, 192, 0.4);
        }

        /* Table Styles */
        .product-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .product-table th {
            background-color: #f8f9fc;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #5c6bc0;
            border-bottom: 1px solid #eee;
        }

        .product-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            color: #555;
            vertical-align: middle;
        }

        .product-table tr:last-child td {
            border-bottom: none;
        }

        .product-table tr:hover {
            background-color: #f9fafc;
        }

        .product-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #eee;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #bbb;
            font-size: 12px;
            text-align: center;
            padding: 5px;
        }

        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .product-category {
            font-size: 13px;
            color: #777;
        }
        .product-status {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-inactive {
            background: #ffebee;
            color: #c62828;
        }

        /* Action Buttons */
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            margin: 0 5px;
            font-size: 16px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .action-btn.edit {
            color: #1976d2;
        }

        .action-btn.edit:hover {
            background: #e3f2fd;
        }

        .action-btn.delete {
            color: #d32f2f;
        }

        .action-btn.delete:hover {
            background: #ffebee;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            width: 450px;
            max-width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            color: #1a237e;
            margin: 0;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #777;
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(92, 107, 192, 0.2);
        }

        .preview-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 10px;
        }

        .preview-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #eee;
            display: none;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 10px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-secondary {
            background: #f0f2f5;
            color: #555;
        }

        .btn-secondary:hover {
            background: #e4e6e9;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6c5ce7 0%, #4834d4 100%);
            color: white;
            box-shadow: 0 4px 10px rgba(92, 107, 192, 0.3);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 15px rgba(92, 107, 192, 0.4);
            transform: translateY(-2px);
        }

      
        
        @media (max-width: 768px) {
            .topbar {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            
            .search-container {
                width: 100%;
            }
            
            .product-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="manageproduct-container">
        <!-- Sidebar Navigation -->
                <div class="sidebar">
                    <div class="logo-menu">
                        <h2 class="logo"><img src="RVS_LOGO.png" alt=""></h2>
                        <i class='bx bx-menu toggle-btn'></i>
                    </div>
                    <nav>
                        <ul class="list">
                            <li class="list-item">
                                <a href="dashboard.php">
                                    <i class='bx bx-home-alt-2'></i>
                                    <span class="link-name" style="--i:1;">Dashboard</span>
                                </a>
                            </li>
                            <li class="list-item">
                                <a href="transaction_history.php">
                                    <i class='bx bx-history'></i>
                                    <span class="link-name" style="--i:2;">Transaction History</span>
                                </a>
                            </li>
                            <li class="list-item active">
                                <a href="manage_product.php">
                                    <i class='bx bx-box'></i>
                                    <span class="link-name" style="--i:3;">Manage Product</span>
                                </a>
                            </li>
                            <li class="list-item">
                                <a href="sales_report.php">
                                    <i class='bx bx-bar-chart-alt-2'></i>
                                    <span class="link-name" style="--i:4;">Sales Report</span>
                                </a>
                            </li>
                            <li class="list-item">
                                <a href="accounts.php">
                                    <i class='bx bx-user'></i>
                                    <span class="link-name" style="--i:5;">Accounts</span>
                                </a>
                            </li>
                            <li class="list-item">
                                <a href="logout.php">
                                    <i class='bx bx-log-out'></i>
                                    <span class="link-name" style="--i:6;">Logout</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>

                <script>
                    const sidebar = document.querySelector('.sidebar');
                    const toggleBtn = document.querySelector('.toggle-btn');

                    toggleBtn.addEventListener('click', () => {
                        sidebar.classList.toggle('active');
                    });

                    // Set active menu item based on current page
                    document.addEventListener('DOMContentLoaded', function() {
                        const currentPage = window.location.pathname.split('/').pop();
                        const menuItems = document.querySelectorAll('.list-item');
                        
                        menuItems.forEach(item => {
                            item.classList.remove('active');
                            const link = item.querySelector('a').getAttribute('href');
                            if (link === currentPage) {
                                item.classList.add('active');
                            }
                        });
                    });
                </script>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <h2>Manage Product</h2>
                <form method="GET" action="" style="display: inline;">
                    <div class="search-container">
                        <input type="text" name="search" placeholder="Search product..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-search"></i>
                    </div>
                </form>
                <div class="topbar-icons">
                    <i class="fas fa-cog"></i>
                    <i class="fas fa-bell"></i>
                    <i class="fas fa-user-circle"></i>
                </div>
            </div>

            <!-- Add Product Button -->
            <button class="add-product-btn" id="addProductBtn">
                <i class="fas fa-plus"></i> Add New Product
            </button>

            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="content-card" style="background-color: #d4edda; color: #155724; margin-bottom: 20px;">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="content-card" style="background-color: #f8d7da; color: #721c24; margin-bottom: 20px;">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Product Table -->
            <div class="content-card">
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($game = $games->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <div class="product-image">
                                            <i class="fas fa-gamepad"></i>
                                        </div>
                                        <div>
                                            <div class="product-name"><?php echo htmlspecialchars($game['name']); ?></div>
                                            <div class="product-category"><?php echo htmlspecialchars($game['description']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>Rp <?php echo number_format($game['base_price'], 2); ?></td>
                                <td><?php echo $game['stock']; ?></td>
                                <td>
                                    <span class="status-badge <?php echo ($game['stock'] > 0) ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo ($game['stock'] > 0) ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="action-btn edit" onclick="editProduct(<?php echo $game['id']; ?>, '<?php echo addslashes($game['name']); ?>', '<?php echo addslashes($game['description']); ?>', <?php echo $game['base_price']; ?>, <?php echo $game['stock']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="confirmDelete(<?php echo $game['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="margin-top: 20px; display: flex; justify-content: center;">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" style="margin: 0 5px; padding: 5px 10px; background: <?php echo ($i == $page) ? '#6c5ce7' : '#f0f2f5'; ?>; color: <?php echo ($i == $page) ? 'white' : '#333'; ?>; border-radius: 3px; text-decoration: none;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Form -->
    <div class="modal" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Product</h3>
                <button class="close-modal" id="closeModal">&times;</button>
            </div>
            <form id="productForm" method="POST" action="">
                <input type="hidden" id="editId" name="id">
                <input type="hidden" id="formAction" name="add_game" value="1">
                
                <div class="form-group">
                    <label for="gameName">Product Name</label>
                    <input type="text" id="gameName" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="gameDescription">Description</label>
                    <textarea id="gameDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="gamePrice">Price</label>
                    <input type="number" id="gamePrice" name="base_price" class="form-control" min="0" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="gameStock">Stock</label>
                    <input type="number" id="gameStock" name="stock" class="form-control" min="0" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="id" id="deleteId">
        <input type="hidden" name="delete_game" value="1">
    </form>

    <script>
        // Modal logic
        const modal = document.getElementById("productModal");
        const addBtn = document.getElementById("addProductBtn");
        const closeModal = document.getElementById("closeModal");
        const cancelBtn = document.getElementById("cancelBtn");
        const form = document.getElementById("productForm");
        const modalTitle = document.getElementById("modalTitle");
        const editId = document.getElementById("editId");
        const formAction = document.getElementById("formAction");

        // Open modal for adding new product
        addBtn.onclick = () => {
            modalTitle.textContent = "Add New Product";
            editId.value = "";
            formAction.name = "add_game";
            formAction.value = "1";
            form.reset();
            modal.classList.add("active");
        };

        // Edit product function
        window.editProduct = function(id, name, description, price, stock) {
            modalTitle.textContent = "Edit Product";
            editId.value = id;
            formAction.name = "update_game";
            formAction.value = "1";
            
            document.getElementById("gameName").value = name;
            document.getElementById("gameDescription").value = description;
            document.getElementById("gamePrice").value = price;
            document.getElementById("gameStock").value = stock;
            
            modal.classList.add("active");
        };

        // Delete confirmation
        window.confirmDelete = function(id) {
            if (confirm("Are you sure you want to delete this product?")) {
                document.getElementById("deleteId").value = id;
                document.getElementById("deleteForm").submit();
            }
        };

        // Close modal
        function closeModalFunc() {
            modal.classList.remove("active");
        }
        
        closeModal.onclick = closeModalFunc;
        cancelBtn.onclick = closeModalFunc;

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === modal) {
                closeModalFunc();
            }
        };

        // Auto-submit search form when typing (with delay)
        let searchTimeout;
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    </script>
</body>
</html>
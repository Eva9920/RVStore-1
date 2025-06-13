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

         /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background-color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100%;
            z-index: 100;
        }

        .logo {
            padding: 20px;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        .sidebar nav ul {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar nav ul li {
            margin: 5px 0;
        }

        .sidebar nav ul li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--dark-color);
            text-decoration: none;
            transition: var(--transition);
        }

        .sidebar nav ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar nav ul li.active a,
        .sidebar nav ul li a:hover {
            background-color: var(--light-color);
            color: var(--primary-color);
            border-left: 4px solid var(--primary-color);
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
    <!-- Dashboard Container -->
    <div class="dashboard-container">
      <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="logo">LOGO</div>
            <nav>
                <ul>
                    <li><a href="dashboard.html"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="transactionhistory.html"><i class="fas fa-history"></i> Transaction History</a></li>
                    <li class="active"><a href="manageproduct.html"><i class="fas fa-boxes"></i> Manage Product</a></li>
                    <li><a href="salesreport.html"><i class="fas fa-chart-bar"></i> Sales Report</a></li>
                    <li><a href="accounts.html"><i class="fas fa-users"></i> Accounts</a></li>
                </ul>
            </nav>
        </div>


        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <h2>Manage Product</h2>
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search product...">
                    <i class="fas fa-search"></i>
                </div>
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

            <!-- Product Table -->
            <div class="content-card">
                <table class="product-table" id="productTable">
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
                        <!-- Data produk akan di-generate oleh JS -->
                    </tbody>
                </table>
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
            <form id="productForm">
                <input type="hidden" id="editIndex">
                <div class="form-group">
                    <label for="namaProduk">Product Name</label>
                    <input type="text" id="namaProduk" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="hargaProduk">Price</label>
                    <input type="number" id="hargaProduk" class="form-control" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="stockProduk">Stock</label>
                    <input type="number" id="stockProduk" class="form-control" min="0" required>
                </div>

                <div class="form-group">
                    <label for="statusProduk">Status</label>
                    <select id="statusProduk" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="fotoProduk">Product Image</label>
                    <input type="file" id="fotoProduk" class="form-control" accept="image/*">
                    <div class="preview-container">
                        <img id="previewImg" class="preview-image" alt="Preview">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Data produk dummy (bisa diganti dengan data dari backend)
    let products = [
        { 
            nama: "Gabriela Cashmere Blazer", 
            harga: 113.99, 
            stock: 1113, 
            views: 14012,
            foto: "",
            status: "active"
        },
        { 
            nama: "Loree blend Jacket - Blue", 
            harga: 113.99, 
            stock: 721, 
            views: 13212,
            foto: "",
            status: "active"
        },
        { 
            nama: "Sandro - Jacket - Black", 
            harga: 113.99, 
            stock: 407, 
            views: 8201,
            foto: "",
            status: "active"
        },
        { 
            nama: "Addas By Stella McCartney", 
            harga: 113.99, 
            stock: 1203, 
            views: 1002,
            foto: "",
            status: "active"
        },
        { 
            nama: "Meteo Hooded Wool Jacket", 
            harga: 113.99, 
            stock: 306, 
            views: 807,
            foto: "",
            status: "active"
        },
        { 
            nama: "Hida Down Ski Jacket - Red", 
            harga: 113.99, 
            stock: 201, 
            views: 406,
            foto: "",
            status: "inactive"
        },
        { 
            nama: "Dolce & Gabbana", 
            harga: 113.99, 
            stock: 108, 
            views: 204,
            foto: "",
            status: "active"
        },
        { 
            nama: "500 THFIB", 
            harga: 89.99, 
            stock: 0, 
            views: 150,
            foto: "",
            status: "inactive"
        }
    ];

    // Render table
    function renderTable(filter = "") {
        const tbody = document.querySelector("#productTable tbody");
        tbody.innerHTML = "";
        let filtered = products.filter(p => p.nama.toLowerCase().includes(filter.toLowerCase()));
        filtered.forEach((p, i) => {
            tbody.innerHTML += `
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div class="product-image">
                                ${p.foto ? `<img src="${p.foto}" alt="${p.nama}" class="product-image">` : 
                                  `<i class="fas fa-image"></i>`}
                            </div>
                            <div>
                                <div class="product-name">${p.nama}</div>
                            </div>
                        </div>
                    </td>
                    <td>Rp ${p.harga.toLocaleString('id-ID', { minimumFractionDigits: 2 })}</td>
                    <td>${p.stock.toLocaleString()}</td>
                    <td>
                        <span class="status-badge ${p.status === 'active' ? 'status-active' : 'status-inactive'}">
                            ${p.status.charAt(0).toUpperCase() + p.status.slice(1)}
                        </span>
                    </td>
                    <td>
                        <button class="action-btn edit" onclick="editProduct(${i})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn delete" onclick="deleteProduct(${i})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    }
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

        // Close modal
        function closeModalFunc() {
            modal.classList.remove("active");
        }
        
        closeModal.onclick = closeModalFunc;
        cancelBtn.onclick = closeModalFunc;

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
<?php
require_once 'config.php';
requireAuth(); // Pastikan pengguna sudah login

// Handle form submissions
$message = '';
$messageType = '';

// Add new user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $full_name = trim($_POST['full_name']);
    
    if (empty($username) || empty($password) || empty($email) || empty($role) || empty($full_name)) {
        $message = "All fields are required for adding a user!";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format!";
        $messageType = "error";
    } else {
        // Check if username or email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = "Username or Email already exists!";
            $messageType = "error";
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $conn->prepare("INSERT INTO users (username, password, email, role, full_name, created_at, status) VALUES (?, ?, ?, ?, ?, NOW(), 'active')");
            $insert_stmt->bind_param("sssss", $username, $hashed_password, $email, $role, $full_name);
            
            if ($insert_stmt->execute()) {
                $message = "User added successfully!";
                $messageType = "success";
            } else {
                $message = "Error adding user: " . $conn->error;
                $messageType = "error";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Update user status (Activate/Deactivate)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'toggle_status') {
    $user_id = (int)$_POST['user_id'];
    $current_status = $_POST['current_status'];
    $new_status = ($current_status == 'active') ? 'inactive' : 'active';

    // Prevent current logged-in admin from deactivating themselves
    if ($user_id == $_SESSION['user_id']) {
        $message = "You cannot change your own status!";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        if ($stmt->execute()) {
            $message = "User status updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating user status: " . $conn->error;
            $messageType = "error";
        }
        $stmt->close();
    }
}

// Delete user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    $user_id = (int)$_POST['user_id'];

    // Prevent current logged-in admin from deleting themselves
    if ($user_id == $_SESSION['user_id']) {
        $message = "You cannot delete your own account!";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = "User deleted successfully!";
            $messageType = "success";
        } else {
            $message = "Error deleting user: " . $conn->error;
            $messageType = "error";
        }
        $stmt->close();
    }
}

// Fetch all users
$users_query = $conn->query("SELECT id, username, email, role, full_name, status, created_at FROM users ORDER BY created_at DESC");
// $users = $users_query->fetch_all(MYSQLI_ASSOC);
// $users_query->close();
// ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #3a5bc7;
            --secondary: #858796;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --primary-color: #6c5ce7;
            --dark-color: #2d3436;
            --light-color: #f7f7f7;
            --sidebar-width: 250px;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            margin: 0;
            display: flex;
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

        .main-content {
            margin-left: 250px;
            flex-grow: 1;
            padding: 20px;
            width: calc(100% - 250px);
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
        
        .card {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card-header {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 15px;
            color: #555;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table th, .table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .table th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #333;
        }
        .table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .table tr:hover {
            background-color: #f1f1f1;
        }
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
            font-size: 0.9em;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .btn-success {
            background-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #333;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-info {
            background-color: #17a2b8;
        }
        .btn-info:hover {
            background-color: #138496;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Modal styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 80%; /* Could be more specific */
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
            animation-name: animatetop;
            animation-duration: 0.4s
        }

        /* Add Animation */
        @-webkit-keyframes animatetop {
            from {top:-300px; opacity:0} 
            to {top:0; opacity:1}
        }

        @keyframes animatetop {
            from {top:-300px; opacity:0}
            to {top:0; opacity:1}
        }

        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .modal .form-group {
            margin-bottom: 15px;
        }
        .modal .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .modal .form-group input,
        .modal .form-group select {
            width: calc(100% - 22px); /* Adjust for padding and border */
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .modal-footer {
            margin-top: 20px;
            text-align: right;
        }
        .modal-footer .btn {
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
                <div class="sidebar">
                    <div class="logo-menu">
                        <h2 class="logo"><img src="RVS_LOGO.png" alt=""></h2>
                        <i class='bx bx-menu toggle-btn'></i>
                    </div>
                    <nav>
                        <ul class="list">
                            <li class="list-item active">
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
                            <li class="list-item">
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

    <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <h2>Manage Accounts</h2>
                <form method="GET" class="search-container">
                    <input type="text" name="search" placeholder="Search product..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                    <i class="fas fa-search"></i>
                </form>
                <div class="topbar-icons">
                    <i class="fas fa-cog"></i>
                    <i class="fas fa-bell"></i>
                    <i class="fas fa-user-circle"></i>
                </div>
            </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>All Users</h3>
                <button class="btn btn-primary" onclick="openAddModal()">Add New User</button>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['status'] == 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): // Prevent actions on current user ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to change status for this user?');">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $user['status']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $user['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $user['status'] == 'active' ? 'Deactivate User' : 'Activate User'; ?>">
                                                <i class="fas fa-<?php echo $user['status'] == 'active' ? 'pause' : 'play'; ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeAddModal()">&times;</span>
            <h2>Add New User</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                <div class="form-group">
                    <label for="add_full_name">Full Name</label>
                    <input type="text" id="add_full_name" name="full_name" required>
                </div>
                <div class="form-group">
                    <label for="add_username">Username</label>
                    <input type="text" id="add_username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="add_email">Email</label>
                    <input type="email" id="add_email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="add_password">Password</label>
                    <input type="password" id="add_password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="add_role">Role</label>
                    <select id="add_role" name="role" required>
                        <option value="customer">Customer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex'; // Use flex to center
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
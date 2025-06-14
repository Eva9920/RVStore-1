<?php
require_once 'config.php';
requireAuth();

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($search) {
    $where_conditions[] = "(g.name LIKE ? OR pm.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

if ($status_filter) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($date_from) {
    $where_conditions[] = "DATE(t.transaction_time) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $where_conditions[] = "DATE(t.transaction_time) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total transactions with filters
$total_query = "SELECT COUNT(*) as total FROM transactions t
    JOIN games g ON t.game_id = g.id
    JOIN payment_methods pm ON t.payment_method_id = pm.id
    $where_clause";

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

// Get transactions with pagination and filters
$transactions_query = "
    SELECT t.id, g.name as game_name, t.amount, pm.name as payment_method, 
           t.transaction_time, t.status, t.notes
    FROM transactions t
    JOIN games g ON t.game_id = g.id
    JOIN payment_methods pm ON t.payment_method_id = pm.id
    $where_clause
    ORDER BY t.transaction_time DESC 
    LIMIT $per_page OFFSET $offset
";

if ($params) {
    $transactions_stmt = $conn->prepare($transactions_query);
    $transactions_stmt->bind_param($types, ...$params);
    $transactions_stmt->execute();
    $transactions = $transactions_stmt->get_result();
} else {
    $transactions = $conn->query($transactions_query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        img {
            width: 105px;
            height: 65px;
            margin-top: 20px;
            margin-bottom: -10px;
            margin-left: 65px;
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
        
        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 20px;
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
        
        .content-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .filters-section {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .filter-group input, .filter-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: var(--secondary);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #6c757d;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .transaction-table th, .transaction-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .transaction-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
            position: sticky;
            top: 0;
        }
        
        .transaction-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .status-badge.pending {
            background-color: #FFF3CD;
            color: #856404;
        }
        
        .status-badge.success {
            background-color: #D4EDDA;
            color: #155724;
        }
        
        .status-badge.failed {
            background-color: #F8D7DA;
            color: #721C24;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 10px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: var(--dark);
            border-radius: 5px;
        }
        
        .pagination a:hover {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .current {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .results-info {
            margin-bottom: 15px;
            color: var(--secondary);
            font-size: 14px;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #17a673;
        }
        
        .btn-info {
            background-color: var(--info);
            color: white;
        }
        
        .btn-info:hover {
            background-color: #2c9faf;
        }

        /* Improved Chatbot Styles */
        .chatbot-container {
            position: fixed;
            bottom: 30px;
            left: 50px;
            z-index: 999;
        }
        
        .chatbot-toggle {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #6c5ce7 0%, #4834d4 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .chatbot-toggle:hover {
            transform: scale(1.1);
        }
        
        .chatbot-toggle i {
            color: white;
            font-size: 28px;
        }
        
        .chatbot-window {
            width: 400px;
            height: 550px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            display: none;
            flex-direction: column;
        }
        
        .chatbot-window.active {
            display: flex;
            animation: fadeInUp 0.3s ease;
        }
        
        .chatbot-header {
            background: linear-gradient(135deg, #6c5ce7 0%, #4834d4 100%);
            color: white;
            padding: 15px;
            text-align: center;
        }
        
        .chatbot-header h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .chatbot-header i {
            position: absolute;
            right: 15px;
            cursor: pointer;
        }
        
        .chatbot-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background: #f5f7fb;
            display: flex;
            flex-direction: column;
        }
        
        .message {
            max-width: 80%;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
        }
        
        .bot-message {
            background: white;
            border-bottom-left-radius: 5px;
            align-self: flex-start;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-right: auto;
        }
        
        .user-message {
            background: #6c5ce7;
            color: white;
            border-bottom-right-radius: 5px;
            align-self: flex-end;
            margin-left: auto;
        }
        
        .chatbot-input {
            display: flex;
            padding: 15px;
            background: white;
            border-top: 1px solid #eee;
        }
        
        .chatbot-input input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 30px;
            outline: none;
        }
        
        .chatbot-input button {
            background: #6c5ce7;
            color: white;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            margin-left: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .chatbot-input button:hover {
            background: #4834d4;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .typing-indicator {
            display: flex;
            padding: 10px 15px;
            background: white;
            border-radius: 18px;
            border-bottom-left-radius: 5px;
            align-self: flex-start;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
            margin-right: auto;
        }
        
        .typing-indicator span {
            height: 8px;
            width: 8px;
            background: #6c5ce7;
            border-radius: 50%;
            display: inline-block;
            margin: 0 2px;
            animation: bounce 1.5s infinite ease-in-out;
        }
        
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes bounce {
            0%, 60%, 100% {
                transform: translateY(0);
            }
            30% {
                transform: translateY(-5px);
            }
        }
    </style>
</head>
<body>
    <div class="transcation-container">
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
                            <li class="list-item active">
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
                                    <span class="link-name" style="--i:5;">Manage Accounts</span>
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
                    const mainContent = document.querySelector('.main-content');

                    // Cek state sidebar dari localStorage saat halaman dimuat
                    document.addEventListener('DOMContentLoaded', function() {
                        // Set active menu item based on current page
                        const currentPage = window.location.pathname.split('/').pop();
                        const menuItems = document.querySelectorAll('.list-item');
                        
                        menuItems.forEach(item => {
                            item.classList.remove('active');
                            const link = item.querySelector('a').getAttribute('href');
                            if (link === currentPage) {
                                item.classList.add('active');
                            }
                        });

                        // Cek state sidebar dari localStorage
                        const sidebarState = localStorage.getItem('sidebarState');
                        if (sidebarState === 'active') {
                            sidebar.classList.add('active');
                            mainContent.style.marginLeft = '260px';
                            mainContent.style.width = 'calc(100% - 260px)';
                        }
                    });

                    // Toggle sidebar dan simpan state ke localStorage
                    toggleBtn.addEventListener('click', () => {
                        sidebar.classList.toggle('active');
                        
                        if (sidebar.classList.contains('active')) {
                            localStorage.setItem('sidebarState', 'active');
                            mainContent.style.marginLeft = '260px';
                            mainContent.style.width = 'calc(100% - 260px)';
                        } else {
                            localStorage.setItem('sidebarState', 'inactive');
                            mainContent.style.marginLeft = '80px';
                            mainContent.style.width = 'calc(100% - 80px)';
                        }
                    });
                </script>

        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <h2>Transaction History</h2>
                <form method="GET" class="search-container">
                    <input type="text" name="search" placeholder="Search product..." value="<?= htmlspecialchars($search) ?>">
                    <i class="fas fa-search"></i>
                </form>
                <div class="topbar-icons">
                    <i class="fas fa-cog"></i>
                    <i class="fas fa-bell"></i>
                    <i class="fas fa-user-circle"></i>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" placeholder="Game name or payment method..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="success" <?php echo $status_filter === 'success' ? 'selected' : ''; ?>>Success</option>
                                <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="date_from">Date From</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="date_to">Date To</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="transaction_history.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Export Buttons -->
            <div class="export-buttons">
                <a href="export_transactions.php?format=csv<?php echo '&' . http_build_query($_GET); ?>" class="btn btn-success">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
                <a href="export_transactions.php?format=excel<?php echo '&' . http_build_query($_GET); ?>" class="btn btn-info">
                    <i class="fas fa-file-excel"></i> Export Excel
                </a>
            </div>

            <!-- Transactions Table -->
            <div class="content-card">
                <div class="results-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?> transactions
                </div>
                
                <div class="table-responsive">
                    <table class="transaction-table">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Game</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($transactions->num_rows > 0): ?>
                                <?php while($row = $transactions->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($row['game_name']); ?></td>
                                        <td>IDR <?php echo number_format($row['amount'], 0, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                        <td><?php echo date('d M Y H:i:s', strtotime($row['transaction_time'])); ?></td>
                                        <td><span class="status-badge <?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['notes'] ?? '-'); ?></td>
                                        <td>
                                            <a href="transaction_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                                        <p>No transactions found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=1<?php echo '&' . http_build_query(array_merge($_GET, ['page' => 1])); ?>">&laquo; First</a>
                            <a href="?page=<?php echo $page - 1; ?><?php echo '&' . http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&lsaquo; Previous</a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo '&' . http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo '&' . http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &rsaquo;</a>
                            <a href="?page=<?php echo $total_pages; ?><?php echo '&' . http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">Last &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Global search functionality
        document.getElementById('globalSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchValue = this.value;
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('search', searchValue);
                urlParams.delete('page');
                window.location.href = window.location.pathname + '?' + urlParams.toString();
            }
        });
    </script>

    <div class="chatbot-container">
        <div class="chatbot-window" id="chatbotWindow">
            <div class="chatbot-header">
                <h3>RVStore AI Assistant</h3>
                <i class="fas fa-times" onclick="toggleChatbot()"></i>
            </div>
            <div class="chatbot-messages" id="chatbotMessages">
                <div class="message bot-message">
                    Hello! How can I help you today?
                </div>
            </div>
            <div class="chatbot-input">
                <input type="text" id="chatbotInput" placeholder="Type your message..." onkeypress="if(event.keyCode==13) sendMessage()">
                <button onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
        <div class="chatbot-toggle" onclick="toggleChatbot()">
            <i class="fas fa-robot"></i>
        </div>
    </div>

    <script>
        function toggleChatbot() {
            const chatbotWindow = document.getElementById('chatbotWindow');
            chatbotWindow.classList.toggle('active');
        }
        
        function sendMessage() {
            const input = document.getElementById('chatbotInput');
            const message = input.value.trim();
            
            if (message === '') return;
            
            // Add user message (right aligned)
            addMessage(message, 'user-message');
            input.value = '';
            
            // Show typing indicator (left aligned)
            const typingIndicator = document.createElement('div');
            typingIndicator.className = 'message typing-indicator';
            typingIndicator.innerHTML = '<span></span><span></span><span></span>';
            document.getElementById('chatbotMessages').appendChild(typingIndicator);
            
            // Scroll to bottom
            scrollToBottom();
            
            // Simulate bot response (left aligned)
            setTimeout(() => {
                // Remove typing indicator
                const indicator = document.querySelector('.typing-indicator');
                if (indicator) indicator.remove();
                
                // Add bot response
                const responses = [
                    "I can help you with your questions about our products and services.",
                    "For order inquiries, please check the Transaction History page.",
                    "Our support team is available 24/7 to assist you.",
                    "You can find more information in our FAQ section.",
                    "Is there anything else I can help you with?"
                ];
                const randomResponse = responses[Math.floor(Math.random() * responses.length)];
                addMessage(randomResponse, 'bot-message');
                
                // Scroll to bottom again after response
                scrollToBottom();
            }, 1500);
        }
        
        function addMessage(text, className) {
            const messagesContainer = document.getElementById('chatbotMessages');
            const messageElement = document.createElement('div');
            messageElement.className = `message ${className}`;
            messageElement.textContent = text;
            messagesContainer.appendChild(messageElement);
        }
        
        function scrollToBottom() {
            const messagesContainer = document.getElementById('chatbotMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    </script>
</body>
</html>
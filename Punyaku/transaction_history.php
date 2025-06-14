<?php
require_once 'config.php';
requireAuth();

// Get stats
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total_orders, SUM(amount) as total_sales FROM transactions WHERE status = 'success'");
$stats = $result->fetch_assoc();

$result = $conn->query("SELECT COUNT(DISTINCT user_id) as total_customers FROM transactions");
$stats['total_customers'] = $result->fetch_assoc()['total_customers'];

$result = $conn->query("SELECT SUM(stock) as total_stock FROM games");
$stats['total_stock'] = $result->fetch_assoc()['total_stock'];

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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Copy all CSS from dashboard.php */
        :root {
            --primary-gradient: linear-gradient(135deg, #2a0845 0%, #6441a5 100%);
            --accent-gradient: linear-gradient(135deg, #ff1493 0%, #6441a5 100%);
            --card-gradient: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.95) 100%);
            --background-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --border-radius: 20px;
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: var(--background-gradient);
            color: var(--text-primary);
            min-height: 100vh;
        }

        img {
            width: 105px;
            height: 65px;
            margin-top: 20px;
            margin-bottom: 15px;
            margin-left: 55px;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 80px;
            height: 100%;
            background: var(--primary-gradient);
            backdrop-filter: blur(40px);
            border-right: 2px solid rgba(255, 20, 147, 0.3);
            box-shadow: 0 0 30px rgba(139, 0, 139, 0.5);
            padding: 6px 14px;
            transition: var(--transition);
            z-index: 100;
        }

        .sidebar.active {
            width: 260px;
        }

        .sidebar .logo-menu {
            display: flex;
            align-items: center;
            width: 100%;
            height: 70px;
            border-bottom: 1px solid rgba(255, 20, 147, 0.2);
        }

        .sidebar .logo-menu .logo {
            font-size: 25px;
            color: #ff1493;
            font-weight: 600;
            pointer-events: none;
            opacity: 0;
            transition: var(--transition);
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
            color: #ff1493;
            text-align: center;
            line-height: 40px;
            cursor: pointer;
            transition: var(--transition);
            background: rgba(106, 13, 173, 0.3);
            border-radius: 50%;
        }

        .sidebar.active .logo-menu .toggle-btn {
            left: 90%;
            background: rgba(255, 20, 147, 0.2);
        }

        .sidebar .logo-menu .toggle-btn:hover {
            color: #ff69b4;
            background: rgba(255, 20, 147, 0.3);
            transform: translateX(-50%) scale(1.1);
        }

        .sidebar .list {
            margin-top: 30px;
        }

        .list .list-item {
            list-style: none;
            width: 100%;
            height: 50px;
            margin: 10px 0;
            line-height: 50px;
        }

        .list .list-item a {
            display: flex;
            align-items: center;
            text-decoration: none;
            font-size: 18px;
            color: #e2b4ff;
            border-radius: 12px;
            white-space: nowrap;
            transition: var(--transition);
            padding: 0 10px;
        }

        .list .list-item.active a,
        .list .list-item a:hover {
            background: var(--accent-gradient);
            color: #fff;
            box-shadow: 0 8px 25px rgba(255, 20, 147, 0.4);
            transform: translateX(5px);
        }

        .list .list-item a i {
            min-width: 30px;
            height: 50px;
            text-align: center;
            line-height: 50px;
            color: #ff1493;
            font-size: 22px;
        }

        .list .list-item.active a i,
        .list .list-item a:hover i {
            color: #fff;
        }

        .sidebar .link-name {
            opacity: 0;
            pointer-events: none;
            transition: var(--transition);
            font-weight: 500;
            margin-left: 8px;
        }

        .sidebar.active .link-name {
            opacity: 1;
            pointer-events: auto;
            transition-delay: calc(0.1s * var(--i));
        }

        /* Main Content */
        .main-content {
            margin-left: 80px;
            padding: 20px;
            transition: var(--transition);
            width: calc(100% - 80px);
            min-height: 100vh;
        }

        .sidebar.active + .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
        }

       /* Hamburger Toggle Button */
       .sidebar .hamburger-toggle {
            position: absolute;
            top: 15px;
            right: -70px; /* Posisi di luar sidebar sebelah kanan */
            width: 50px;
            height: 50px;
            background: var(--accent-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1001;
            transition: var(--transition);
        }

        .sidebar.active .hamburger-toggle {
            right: 20px; /* Saat sidebar aktif, posisi di dalam kanan atas */
        }

        .sidebar .hamburger-toggle i {
            color: white;
            font-size: 20px;
        }

        /* Sidebar Adjustment */
        .sidebar {
            position: fixed;
            top: 0;
            left: -260px;
            width: 260px;
            z-index: 1000;
            transition: var(--transition);
        }

        .sidebar.active {
            left: 0;
        }

        /* Topbar */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 30px;
            background: var(--card-gradient);
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            border-radius: var(--border-radius);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .topbar h2 {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 28px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .search-container {
            position: relative;
            width: 350px;
        }

        .search-container input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid rgba(255, 20, 147, 0.2);
            border-radius: 25px;
            font-size: 14px;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .search-container input:focus {
            border-color: #ff1493;
            outline: none;
            box-shadow: 0 0 20px rgba(255, 20, 147, 0.3);
            background: rgba(255, 255, 255, 0.95);
        }

        .search-container i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #ff1493;
            font-size: 18px;
        }

        /* Content Cards */
        .content-card {
            background: var(--card-gradient);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
            margin-bottom: 30px;
        }

        .content-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
        }

        .content-card.wide {
            grid-column: span 2;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 20, 147, 0.1);
        }

        .card-header h2 {
            font-size: 22px;
            font-weight: 700;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Filters Section */
        .filters-section {
            background: var(--card-gradient);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 30px;
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .filter-group input,
        .filter-group select {
            padding: 12px 15px;
            border: 2px solid rgba(255, 20, 147, 0.2);
            border-radius: 12px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.8);
            transition: var(--transition);
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #ff1493;
            outline: none;
            box-shadow: 0 0 15px rgba(255, 20, 147, 0.2);
        }

        .filter-buttons {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--accent-gradient);
            color: white;
            box-shadow: 0 5px 15px rgba(255, 20, 147, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 20, 147, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.8);
            color: var(--text-primary);
            border: 2px solid rgba(255, 20, 147, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.9);
            border-color: rgba(255, 20, 147, 0.3);
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            overflow: hidden;
        }

        .transaction-table th,
        .transaction-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 20, 147, 0.1);
        }

        .transaction-table th {
            background: var(--accent-gradient);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .transaction-table tr:hover {
            background: rgba(255, 20, 147, 0.05);
        }

        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            letter-spacing: 0.5px;
        }

        .status-badge.pending {
            background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);
            color: white;
        }

        .status-badge.success {
            background: linear-gradient(135deg, #00ff88 0%, #00cc70 100%);
            color: white;
        }

        .status-badge.failed {
            background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
            color: white;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: var(--accent-gradient);
            color: white;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 20, 147, 0.3);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 10px;
        }

        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border-radius: 12px;
            text-decoration: none;
            transition: var(--transition);
        }

        .pagination a {
            background: rgba(255, 255, 255, 0.5);
            border: 2px solid rgba(255, 20, 147, 0.2);
            color: var(--text-primary);
        }

        .pagination a:hover {
            background: var(--accent-gradient);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
        }

        .pagination .current {
            background: var(--accent-gradient);
            color: white;
            border: 2px solid transparent;
        }

        .results-info {
            margin-bottom: 15px;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .export-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .btn-success {
            background: linear-gradient(135deg, #00ff88 0%, #00cc70 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(0, 204, 112, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 204, 112, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, #00c9ff 0%, #0095d9 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(0, 201, 255, 0.3);
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 201, 255, 0.4);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 0;
        }

        .empty-state i {
            font-size: 48px;
            color: rgba(255, 20, 147, 0.3);
            margin-bottom: 15px;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .filters-row {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .export-buttons {
                flex-direction: column;
            }
            
            .search-container {
                width: 250px;
            }
            
            .topbar h2 {
                font-size: 22px;
            }
        }

        /* Chatbot Styles */
        .chatbot-container {
            position: fixed;
            bottom: 30px;
            right: 50px;
            z-index: 999;
        }

        .chatbot-toggle {
            width: 70px;
            height: 70px;
            background: var(--accent-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(255, 20, 147, 0.4);
            transition: var(--transition);
        }

        .chatbot-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 15px 40px rgba(255, 20, 147, 0.5);
        }

        .chatbot-toggle i {
            color: white;
            font-size: 28px;
        }

        .chatbot-window {
            width: 400px;
            height: 550px;
            background: var(--card-gradient);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            display: none;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .chatbot-window.active {
            display: flex;
            animation: fadeInUp 0.3s ease;
        }

        .chatbot-header {
            background: var(--accent-gradient);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        .chatbot-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }

        .chatbot-header i {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 20px;
            transition: var(--transition);
        }

        .chatbot-header i:hover {
            transform: translateY(-50%) scale(1.2);
        }

        .chatbot-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
        }

        .message {
            max-width: 80%;
            padding: 12px 18px;
            margin-bottom: 15px;
            border-radius: 20px;
            position: relative;
            word-wrap: break-word;
            font-size: 14px;
            line-height: 1.5;
        }

        .bot-message {
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-primary);
            border-bottom-left-radius: 8px;
            align-self: flex-start;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-right: auto;
        }

        .user-message {
            background: var(--accent-gradient);
            color: white;
            border-bottom-right-radius: 8px;
            align-self: flex-end;
            margin-left: auto;
            box-shadow: 0 5px 15px rgba(255, 20, 147, 0.3);
        }

        .chatbot-input {
            display: flex;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-top: 1px solid rgba(255, 20, 147, 0.2);
        }

        .chatbot-input input {
            flex: 1;
            padding: 12px 18px;
            border: 2px solid rgba(255, 20, 147, 0.2);
            border-radius: 25px;
            outline: none;
            font-size: 14px;
            transition: var(--transition);
        }

        .chatbot-input input:focus {
            border-color: #ff1493;
            box-shadow: 0 0 15px rgba(255, 20, 147, 0.2);
        }

        .chatbot-input button {
            background: var(--accent-gradient);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            margin-left: 10px;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 5px 15px rgba(255, 20, 147, 0.3);
        }

        .chatbot-input button:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(255, 20, 147, 0.4);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .typing-indicator {
            display: flex;
            padding: 12px 18px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            border-bottom-left-radius: 8px;
            align-self: flex-start;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
            margin-right: auto;
        }

        .typing-indicator span {
            height: 8px;
            width: 8px;
            background: #ff1493;
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
                transform: translateY(-8px);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .content-row {
                grid-template-columns: 1fr;
            }
            
            .search-container {
                width: 250px;
            }
            
            .topbar h2 {
                font-size: 22px;
            }
        }

        /* Bottom Icons */
        .bottom-icons {
            position: fixed;
            bottom: 20px;
            left: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            z-index: 100;
        }

        .bottom-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--accent-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(255, 20, 147, 0.4);
            transition: var(--transition);
        }

        .bottom-icon:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 20, 147, 0.5);
        }
    </style>
</head>
<body>
    <div class="transactionhistory-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="hamburger-toggle">
                <i class='bx bx-menu'></i>
            </div>

            <script>
                document.querySelector('.hamburger-toggle').addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('active');
                });
            </script>
            
            <div class="logo-menu">
                <h2 class="logo"><img src="RVS_LOGO.png" alt=""></h2>
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

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <h2>Transaction History</h2>
                <form method="GET" class="search-container">
                    <input type="text" name="search" placeholder="Search product..." value="<?php echo htmlspecialchars($search); ?>">
                    <i class="fas fa-search"></i>
                </form>
            </div>

            <!-- Bottom Icons -->
            <div class="bottom-icons">
                <div class="bottom-icon" onclick="window.location.href='profile.php'">
                    <i class='bx bx-user'></i>
                </div>
                <div class="bottom-icon" onclick="window.location.href='notifications.php'">
                    <i class='bx bx-bell'></i>
                </div>
                <div class="bottom-icon" onclick="window.location.href='settings.php'">
                    <i class='bx bx-cog'></i>
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
                                            <a href="transaction_detail.php?id=<?php echo $row['id']; ?>" class="action-btn">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <i class="fas fa-inbox"></i>
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

    <!-- Chatbot (same as dashboard) -->
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
        // Chatbot functionality (same as dashboard)
        function toggleChatbot() {
            const chatbotWindow = document.getElementById('chatbotWindow');
            chatbotWindow.classList.toggle('active');
        }
        
        function sendMessage() {
            const input = document.getElementById('chatbotInput');
            const message = input.value.trim();
            
            if (message === '') return;
            
            // Add user message
            addMessage(message, 'user-message');
            input.value = '';
            
            // Show typing indicator
            showTypingIndicator();
            
            // Simulate bot response after delay
            setTimeout(() => {
                removeTypingIndicator();
                const response = getBotResponse();
                addMessage(response, 'bot-message');
                scrollToBottom();
            }, 1500);
        }
        
        // Helper Functions
        function addMessage(text, className) {
            const messagesContainer = document.getElementById('chatbotMessages');
            const messageElement = document.createElement('div');
            messageElement.className = `message ${className}`;
            messageElement.textContent = text;
            messagesContainer.appendChild(messageElement);
            scrollToBottom();
        }
        
        function showTypingIndicator() {
            const messagesContainer = document.getElementById('chatbotMessages');
            const typingIndicator = document.createElement('div');
            typingIndicator.className = 'message typing-indicator';
            typingIndicator.innerHTML = '<span></span><span></span><span></span>';
            typingIndicator.id = 'typingIndicator';
            messagesContainer.appendChild(typingIndicator);
            scrollToBottom();
        }
        
        function removeTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) indicator.remove();
        }
        
        function getBotResponse() {
            const responses = [
                "I can help you with your questions about our products and services.",
                "For order inquiries, please check the Transaction History page.",
                "Our support team is available 24/7 to assist you.",
                "You can find more information in our FAQ section.",
                "Is there anything else I can help you with?"
            ];
            return responses[Math.floor(Math.random() * responses.length)];
        }
        
        function scrollToBottom() {
            const messagesContainer = document.getElementById('chatbotMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Handle Enter key press
        document.getElementById('chatbotInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    </script>
</body>
</html>
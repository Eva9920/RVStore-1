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

// Get recent transactions
$transactions = $conn->query("
    SELECT t.id, g.name as game_name, t.amount, pm.name as payment_method, t.transaction_time, t.status 
    FROM transactions t
    JOIN games g ON t.game_id = g.id
    JOIN payment_methods pm ON t.payment_method_id = pm.id
    ORDER BY t.transaction_time DESC 
    LIMIT 5
");

// Get top games
$top_games = $conn->query("
    SELECT g.name, COUNT(t.id) as transaction_count 
    FROM transactions t
    JOIN games g ON t.game_id = g.id
    GROUP BY g.name 
    ORDER BY transaction_count DESC 
    LIMIT 5
");

$sales_data = $conn->query("
    SELECT 
        DATE_FORMAT(transaction_time, '%Y-%m') as month,
        SUM(amount) as total_sales
    FROM transactions
    WHERE status = 'success'
    GROUP BY DATE_FORMAT(transaction_time, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
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

        .topbar-icons {
            display: flex;
            gap: 20px;
        }

        .topbar-icons i {
            font-size: 24px;
            color: #6441a5;
            cursor: pointer;
            transition: var(--transition);
            padding: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
        }

        .topbar-icons i:hover {
            color: #ff1493;
            background: rgba(255, 20, 147, 0.1);
            transform: scale(1.1);
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--card-gradient);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--accent-gradient);
        }

        .stat-card h3 {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card p {
            font-size: 32px;
            font-weight: 800;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .stat-card .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 40px;
            color: rgba(255, 20, 147, 0.2);
        }

        /* Content Cards */
        .content-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .content-card {
            background: var(--card-gradient);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
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

        .period-select {
            padding: 10px 15px;
            border: 2px solid rgba(255, 20, 147, 0.2);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            transition: var(--transition);
        }

        .period-select:focus {
            border-color: #ff1493;
            outline: none;
            box-shadow: 0 0 15px rgba(255, 20, 147, 0.2);
        }

        /* Chart Styles */
        .chart-placeholder {
            height: 350px;
            position: relative;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 15px;
            padding: 20px;
        }

        /* Progress Bars */
        .game-distribution {
            margin-top: 20px;
        }

        .game-item {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            transition: var(--transition);
        }

        .game-item:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: translateX(5px);
        }

        .game-item span {
            display: block;
            margin-bottom: 8px;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .progress-bar {
            height: 12px;
            background: var(--accent-gradient);
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Country List */
        .country-list {
            list-style: none;
        }

        .country-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 20, 147, 0.1);
            transition: var(--transition);
        }

        .country-list li:hover {
            background: rgba(255, 20, 147, 0.05);
            padding-left: 10px;
            border-radius: 8px;
        }

        .country-list li:last-child {
            border-bottom: none;
        }

        .percentage {
            font-weight: 700;
            font-size: 18px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Activity Chart */
        .activity-chart {
            display: flex;
            align-items: flex-end;
            height: 200px;
            gap: 20px;
            margin-top: 20px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 15px;
        }

        .activity-bar {
            flex: 1;
            background: var(--accent-gradient);
            border-radius: 8px 8px 0 0;
            position: relative;
            transition: var(--transition);
            box-shadow: 0 5px 15px rgba(255, 20, 147, 0.3);
        }

        .activity-bar:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(255, 20, 147, 0.4);
        }

        /* Table Styles */
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

        .view-all {
            text-align: right;
            margin-top: 20px;
        }

        .view-all a {
            color: #ff1493;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            transition: var(--transition);
            padding: 10px 20px;
            border: 2px solid rgba(255, 20, 147, 0.3);
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.5);
        }

        .view-all a:hover {
            background: var(--accent-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 20, 147, 0.3);
        }

        /* Chatbot Styles */
        .chatbot-container {
            position: fixed;
            bottom: 30px;
            left: 50px;
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
    </style>
</head>
<body>
    <div class="dashboard-container">
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
                <h2>Dashboard</h2>
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

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Total Sales</h3>
                    <p>IDR <?php echo number_format($stats['total_sales'] ?? 0, 0, ',', '.'); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Orders</h3>
                    <p><?php echo number_format($stats['total_orders'] ?? 0, 0, ',', '.'); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Customers</h3>
                    <p><?php echo number_format($stats['total_customers'] ?? 0, 0, ',', '.'); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Stock</h3>
                    <p><?php echo number_format($stats['total_stock'] ?? 0, 0, ',', '.'); ?></p>
                </div>
            </div>

            <!-- Recent Transactions Section -->
            <div class="content-card wide">
                <h2>Recent Transactions</h2>
                <div class="table-responsive">
                    <table class="transaction-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Game</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($transactions->num_rows > 0): ?>
                                <?php while($row = $transactions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['game_name']); ?></td>
                                        <td>IDR <?php echo number_format($row['amount'], 0, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                        <td><?php echo date('d M Y H:i', strtotime($row['transaction_time'])); ?></td>
                                        <td><span class="status-badge <?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No recent transactions</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="view-all">
                    <a href="transaction_history.php">View All Transactions →</a>
                </div>
            </div>

            <!-- Main Content Sections -->
            <div class="content-row">
                <!-- Sales Report Section -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>Sales Report</h2>
                        <select class="period-select">
                            <option>Last 12 Months</option>
                            <option>Last 6 Months</option>
                            <option>Last 3 Months</option>
                        </select>
                    </div>
                    <div class="chart-placeholder">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Game Distribution Section -->
                <div class="content-card">
                    <h2>Top Games</h2>
                    <div class="game-distribution">
                        <?php if ($top_games->num_rows > 0): ?>
                            <?php 
                            $max_count = 0;
                            $top_games_data = [];
                            while($game = $top_games->fetch_assoc()) {
                                $top_games_data[] = $game;
                                if ($game['transaction_count'] > $max_count) {
                                    $max_count = $game['transaction_count'];
                                }
                            }
                            ?>
                            <?php foreach ($top_games_data as $game): ?>
                                <div class="game-item">
                                    <span><?php echo htmlspecialchars($game['name']); ?></span>
                                    <div class="progress-bar" style="width: <?php echo ($game['transaction_count'] / $max_count) * 100; ?>%"></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No game data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="content-row">
                <!-- Users by Country Section -->
                <div class="content-card">
                    <h2>Users by Country</h2>
                    <ul class="country-list">
                        <li>
                            <span>Indonesia</span>
                            <span class="percentage">65%</span>
                        </li>
                        <li>
                            <span>Malaysia</span>
                            <span class="percentage">20%</span>
                        </li>
                        <li>
                            <span>Singapore</span>
                            <span class="percentage">15%</span>
                        </li>
                    </ul>
                </div>

                <!-- Activity Section -->
                <div class="content-card">
                    <h2>Activity</h2>
                    <div class="activity-chart">
                        <div class="activity-bar" style="height: 60%"></div>
                        <div class="activity-bar" style="height: 30%"></div>
                        <div class="activity-bar" style="height: 45%"></div>
                        <div class="activity-bar" style="height: 20%"></div>
                        <div class="time-labels">
                            <span>00</span>
                            <span>06</span>
                            <span>12</span>
                            <span>18</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    $sales_data_array = [];
                    while($row = $sales_data->fetch_assoc()) {
                        $sales_data_array[] = $row;
                        echo "'" . date('M Y', strtotime($row['month'] . '-01')) . "',";
                    }
                    ?>
                ].reverse(),
                datasets: [{
                    label: 'Sales (IDR)',
                    data: [
                        <?php 
                        foreach (array_reverse($sales_data_array) as $row) {
                            echo ($row['total_sales'] / 1000000) . ",";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: '#fff',
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointHoverBorderColor: '#fff',
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'IDR ' + value + 'M';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'IDR ' + (context.raw * 1000000).toLocaleString();
                            }
                        }
                    }
                }
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
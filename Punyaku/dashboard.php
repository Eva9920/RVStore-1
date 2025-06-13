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
//aaaaaaaaaaaaaaaaaaaaaaaa
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
    <title>Dashboard </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
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
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: var(--secondary);
            margin-bottom: 10px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .stat-card p {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
        }
        
        /* Content Rows */
        .content-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .content-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .content-card.wide {
            grid-column: span 2;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-header h2 {
            font-size: 18px;
            color: var(--dark);
        }
        
        .period-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
            font-size: 14px;
        }
        
        /* Chart Placeholder */
        .chart-placeholder {
            height: 300px;
            position: relative;
        }
        
        /* Game Distribution */
        .game-distribution {
            margin-top: 20px;
        }
        
        .game-item {
            margin-bottom: 15px;
        }
        
        .game-item span {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .progress-bar {
            height: 10px;
            background-color: var(--primary);
            border-radius: 5px;
        }
        
        /* Country List */
        .country-list {
            list-style: none;
        }
        
        .country-list li {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .country-list li:last-child {
            border-bottom: none;
        }
        
        .percentage {
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Activity Chart */
        .activity-chart {
            display: flex;
            align-items: flex-end;
            height: 200px;
            gap: 20px;
            margin-top: 20px;
        }
        
        .activity-bar {
            flex: 1;
            background-color: var(--primary);
            border-radius: 5px;
            position: relative;
        }
        
        .time-labels {
            position: absolute;
            bottom: -25px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
        }
        
        /* Recent Transactions */
        .recent-transactions {
            margin-top: 20px;
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
        
        .view-all {
            text-align: right;
            margin-top: 15px;
        }
        
        .view-all a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
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
            <div class="dashboard-container">
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
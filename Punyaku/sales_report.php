<?php
require_once 'config.php';
requireAuth();

// Get date range (default to last 30 days)
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('');

// Get sales summary
$summary_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(amount) as total_sales,
        AVG(amount) as average_sale
    FROM transactions
    WHERE status = 'success'
    AND DATE(transaction_time) BETWEEN ? AND ?
");
$summary_stmt->bind_param("ss", $date_from, $date_to);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$summary = $summary_result->fetch_assoc();
$summary_stmt->close();

// Get sales by game
$games_stmt = $conn->prepare("
    SELECT 
        g.name as game_name,
        COUNT(t.id) as transaction_count,
        SUM(t.amount) as total_sales
    FROM transactions t
    JOIN games g ON t.game_id = g.id
    WHERE t.status = 'success'
    AND DATE(t.transaction_time) BETWEEN ? AND ?
    GROUP BY g.name
    ORDER BY total_sales DESC
");
$games_stmt->bind_param("ss", $date_from, $date_to);
$games_stmt->execute();
$games_result = $games_stmt->get_result();
$sales_by_game = $games_result->fetch_all(MYSQLI_ASSOC);
$games_stmt->close();

// Get sales by payment method
$pm_stmt = $conn->prepare("
    SELECT 
        pm.name as payment_method_name,
        COUNT(t.id) as transaction_count,
        SUM(t.amount) as total_sales
    FROM transactions t
    JOIN payment_methods pm ON t.payment_method_id = pm.id
    WHERE t.status = 'success'
    AND DATE(t.transaction_time) BETWEEN ? AND ?
    GROUP BY pm.name
    ORDER BY total_sales DESC
");
$pm_stmt->bind_param("ss", $date_from, $date_to);
$pm_stmt->execute();
$pm_result = $pm_stmt->get_result();
$sales_by_payment_method = $pm_result->fetch_all(MYSQLI_ASSOC);
$pm_stmt->close();

// Sales data for chart (daily sales for selected range)
$chartLabels = [];
$chartData = [];
$daily_sales_stmt = $conn->prepare("
    SELECT DATE(transaction_time) as sale_date, SUM(amount) as daily_revenue 
    FROM transactions 
    WHERE status = 'success' AND DATE(transaction_time) BETWEEN ? AND ?
    GROUP BY sale_date 
    ORDER BY sale_date ASC
");
$daily_sales_stmt->bind_param("ss", $date_from, $date_to);
$daily_sales_stmt->execute();
$daily_sales_result = $daily_sales_stmt->get_result();

$daily_sales = [];
while($row = $daily_sales_result->fetch_assoc()) {
    $daily_sales[$row['sale_date']] = $row['daily_revenue'];
}
$daily_sales_stmt->close();

// Populate chart data for all days in the range
$current_date = strtotime($date_from);
$end_date = strtotime($date_to);

while ($current_date <= $end_date) {
    $date_str = date('Y-m-d', $current_date);
    $chartLabels[] = date('d M', $current_date);
    $chartData[] = isset($daily_sales[$date_str]) ? round($daily_sales[$date_str] / 1000000, 2) : 0; // Convert to millions
    $current_date = strtotime('+1 day', $current_date);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        .salesreport-container {
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
            padding: 0;
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
            margin-bottom: 20px;
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
            margin-bottom: 10px;
        }

        .content-card {
            background: var(--card-gradient);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
            margin-bottom: 20px;
        }

        .content-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
        }

        .content-card.wide {
            grid-column: span 2;
            margin-bottom: 20px;
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

        /* Filter Section */
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--text-secondary);
        }

        .filter-group input[type="date"] {
            padding: 12px 15px;
            border: 2px solid rgba(255, 20, 147, 0.2);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            transition: var(--transition);
        }

        .filter-group input[type="date"]:focus {
            border-color: #ff1493;
            outline: none;
            box-shadow: 0 0 15px rgba(255, 20, 147, 0.2);
        }

        .filter-group button {
            padding: 12px 20px;
            background: var(--accent-gradient);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 5px 15px rgba(255, 20, 147, 0.3);
        }

        .filter-group button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 20, 147, 0.4);
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

        .download-btn {
            display: inline-block;
            padding: 12px 25px;
            background: var(--accent-gradient);
            color: white;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 16px;
            box-shadow: 0 5px 15px rgba(255, 20, 147, 0.3);
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 20, 147, 0.4);
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
            
            .filter-section {
                flex-direction: column;
                align-items: stretch;
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
    <div class="salesreport-container">
        <div class="sidebar">
            <div class="hamburger-toggle">
                <i class="fas fa-bars"></i>
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
                            <i class="fas fa-home"></i>
                            <span class="link-name" style="--i:1;">Dashboard</span>
                        </a>
                    </li>
                    <li class="list-item">
                        <a href="transaction_history.php">
                            <i class="fas fa-history"></i>
                            <span class="link-name" style="--i:2;">Transaction History</span>
                        </a>
                    </li>
                    <li class="list-item">
                        <a href="manage_product.php">
                            <i class="fas fa-box-open"></i>
                            <span class="link-name" style="--i:3;">Manage Product</span>
                        </a>
                    </li>
                    <li class="list-item">
                        <a href="orders.php">
                            <i class="bi bi-cart3"></i>
                            <span class="link-name" style="--i:3;">Orders</span>
                        </a>
                    </li>
                    <li class="list-item active">
                        <a href="sales_report.php">
                            <i class="fas fa-chart-bar"></i>
                            <span class="link-name" style="--i:4;">Sales Report</span>
                        </a>
                    </li>
                    <li class="list-item">
                        <a href="accounts.php">
                            <i class="fas fa-users-cog"></i>
                            <span class="link-name" style="--i:5;">Manage Accounts</span>
                        </a>
                    </li>
                    <li class="list-item">
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
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
                <h2>Sales Report</h2>
                <form method="GET" class="search-container">
                    <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                    <i class="fas fa-search"></i>
                </form>
            </div>

            <!-- Bottom Icons -->
            <div class="bottom-icons">
                <div class="bottom-icon" onclick="window.location.href='profile.php'">
                    <i class="fas fa-user"></i>
                </div>
                <div class="bottom-icon" onclick="window.location.href='notifications.php'">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="bottom-icon" onclick="window.location.href='settings.php'">
                    <i class="fas fa-cog"></i>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Sales Overview</h2>
                </div>
                <form method="GET" action="" class="filter-section">
                    <div class="filter-group">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="filter-group">
                        <button type="submit">Apply Filter</button>
                    </div>
                    <div class="filter-group">
                        <button type="button" onclick="window.location.href='sales_report.php'">Reset Dates</button>
                    </div>
                </form>

                <!-- Download Button -->
            <div class="view-all">
                <button class="download-btn" onclick="downloadPDF()">
                    <i class="fas fa-download"></i> Download Report
                </button>
            </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Total Transactions</h3>
                    <p><?php echo number_format($summary['total_transactions'] ?? 0, 0, ',', '.'); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Sales</h3>
                    <p>IDR <?php echo number_format($summary['total_sales'] ?? 0, 0, ',', '.'); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Average Sale</h3>
                    <p>IDR <?php echo number_format($summary['average_sale'] ?? 0, 0, ',', '.'); ?></p>
                </div>
            </div>

            <!-- Sales Chart -->
            <div class="content-card wide">
                <div class="card-header">
                    <h2>Daily Sales Chart</h2>
                </div>
                <div class="chart-placeholder">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Sales Tables -->
            <div class="content-row">
                <!-- Sales by Game -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>Sales By Game</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="transaction-table">
                            <thead>
                                <tr>
                                    <th>Game Name</th>
                                    <th>Transactions</th>
                                    <th>Total Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sales_by_game)): ?>
                                    <tr>
                                        <td colspan="3">No sales by game found for this period.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sales_by_game as $sale): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sale['game_name']); ?></td>
                                            <td><?php echo number_format($sale['transaction_count'], 0, ',', '.'); ?></td>
                                            <td>IDR <?php echo number_format($sale['total_sales'], 0, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sales by Payment Method -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>Sales By Payment Method</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="transaction-table">
                            <thead>
                                <tr>
                                    <th>Payment Method</th>
                                    <th>Transactions</th>
                                    <th>Total Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sales_by_payment_method)): ?>
                                    <tr>
                                        <td colspan="3">No sales by payment method found for this period.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sales_by_payment_method as $sale): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sale['payment_method_name']); ?></td>
                                            <td><?php echo number_format($sale['transaction_count'], 0, ',', '.'); ?></td>
                                            <td>IDR <?php echo number_format($sale['total_sales'], 0, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Sales (IDR)',
                    data: <?php echo json_encode($chartData); ?>,
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

        // PDF Download function
        function downloadPDF() {
            const element = document.querySelector('.main-content');
            const opt = {
                margin: 1,
                filename: 'sales-report.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };

            // Show loading state
            const downloadBtn = document.querySelector('.download-btn');
            const originalText = downloadBtn.innerHTML;
            downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
            downloadBtn.disabled = true;

            // Generate PDF
            html2pdf().set(opt).from(element).save().then(() => {
                // Reset button state
                downloadBtn.innerHTML = originalText;
                downloadBtn.disabled = false;
            });
        }

        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.querySelector('.toggle-btn');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');

            // Check sidebar state from localStorage
            const sidebarState = localStorage.getItem('sidebarState');
            if (sidebarState === 'active') {
                sidebar.classList.add('active');
                mainContent.style.marginLeft = '260px';
                mainContent.style.width = 'calc(100% - 260px)';
            }

            // Toggle sidebar and save state
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
        });
    </>

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

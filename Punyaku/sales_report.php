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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
            margin-left: 80px;
            padding: 20px;
            transition: .5s;
        }

        .sidebar.active + .main-content {
            margin-left: 260px;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 20px;
            width: calc(100% - var(--sidebar-width));
            min-width: 0;
        }
        .header {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 2rem;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-info .avatar {
            width: 40px;
            height: 40px;
            background-color: #ccc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #fff;
            font-size: 1.2em;
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
        .badge {
            display: inline-block;
            padding: .35em .65em;
            font-size: .75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: .375rem;
        }
        .badge-success { background-color: #28a745; color: white; }
        .badge-danger { background-color: #dc3545; color: white; }
        .badge-warning { background-color: #ffc107; color: #333; }
        .badge-info { background-color: #17a2b8; color: white; }
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
            color: #555;
        }
        .filter-group input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .filter-group button {
            padding: 10px 15px;
            background-color: #4e73df;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .filter-group button:hover {
            background-color: #2e59d9;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .summary-card {
            background-color: #e9f0f9;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        .summary-card .title {
            font-size: 1em;
            color: #666;
            margin-bottom: 10px;
        }
        .summary-card .value {
            font-size: 1.8em;
            font-weight: bold;
            color: #4e73df;
        }
        .top-selling {
            margin-top: 30px;
        }
        .top-selling h3 {
            margin-bottom: 10px;
        }
      
        .chart-container {
            margin-top: 30px;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        .download-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #6c5ce7 0%, #4834d4 100%);
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
        }
        .download-btn:hover {
            background: linear-gradient(135deg, #4834d4 0%, #6c5ce7 100%);
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
                <h2>Sales Report</h2>
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
        <div class="card">
            <div class="card-header">
                <span>Sales Overview</span>
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
                    <button type="submit" class="btn">Apply Filter</button>
                </div>
                <div class="filter-group">
                    <button type="button" class="btn" onclick="window.location.href='sales_report.php'">Reset Dates</button>
                </div>
            </form>
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="title">Total Transactions</div>
                    <div class="value"><?php echo number_format($summary['total_transactions']); ?></div>
                </div>
                <div class="summary-card">
                    <div class="title">Total Sales</div>
                    <div class="value"><?php echo formatCurrency($summary['total_sales']); ?></div>
                </div>
                <div class="summary-card">
                    <div class="title">Average Sale</div>
                    <div class="value"><?php echo formatCurrency($summary['average_sale']); ?></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
            <span>Daily Sales Chart</span>
            </div>
            <canvas id="salesChart" style="max-height: 400px; width: 100%;"></canvas>
            <script>
            // Initialize line chart with dummy data
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            new Chart(salesCtx, {
                type: 'line',
                data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Daily Sales (Rp)',
                    data: [250000, 320000, 280000, 450000, 380000, 520000, 480000],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1,
                    fill: false
                }]
                },
                options: {
                responsive: true,
                scales: {
                    y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                        return 'Rp' + value.toLocaleString();
                        }
                    }
                    }
                }
                }
            });
            </script>
        </div>
        <div class="card">
            <div class="card-header">
                <span>Sales By Game</span>
            </div>
            <div class="table-responsive">
                <table class="table">
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
                                <td colspan="3" class="text-center">No sales by game found for this period.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sales_by_game as $sale) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sale['game_name']); ?></td>
                                    <td><?php echo $sale['transaction_count']; ?></td>
                                    <td><?php echo formatCurrency($sale['total_sales']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <span>Sales By Payment Method</span>
            </div>
            <div class="table-responsive">
                <table class="table">
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
                                <td colspan="3" class="text-center">No sales by payment method found for this period.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sales_by_payment_method as $sale) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sale['payment_method_name']); ?></td>
                                    <td><?php echo $sale['transaction_count']; ?></td>
                                    <td><?php echo formatCurrency($sale['total_sales']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card top-selling"> <h3>Top 5 Selling</h3>
            
            <div class="table-responsive">
                <table id="topSellingTable" class="table">
                    <thead>
                        <tr><th>#</th><th>Title</th><th>Price</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>01.</td><td>Roblox</td><td>Rp150.000,00</td></tr>
                        <tr><td>02.</td><td>Free Fire</td><td>Rp150.000,00</td></tr>
                        <tr><td>03.</td><td>Mobile Legends</td><td>Rp300.000,00</td></tr>
                        <tr><td>04.</td><td>PUBG</td><td>Rp150.000,00</td></tr>
                        <tr><td>05.</td><td>CODM</td><td>Rp200.000,00</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="chart-container">
            <h3 style="margin-bottom:10px;">Top Selling Bar Chart</h3>
            <canvas id="topSellingChart" height="120"></canvas>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            // Initialize the bar chart
            const ctx = document.getElementById('topSellingChart').getContext('2d');
            new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Roblox', 'Free Fire', 'Mobile Legends', 'PUBG', 'CODM'],
                datasets: [{
                label: 'Sales Amount (Rp)',
                data: [150000, 150000, 300000, 150000, 200000],
                backgroundColor: [
                    'rgba(78, 115, 223, 0.8)',
                    'rgba(54, 185, 204, 0.8)',
                    'rgba(28, 200, 138, 0.8)', 
                    'rgba(246, 194, 62, 0.8)',
                    'rgba(231, 74, 59, 0.8)'
                ],
                borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                    callback: function(value) {
                        return 'Rp' + value.toLocaleString();
                    }
                    }
                }
                }
            }
            });
        </script>
        
        <div style="text-align:right;">
            <a href="#" class="download-btn" onclick="downloadPDF()">Download Report</a>
        </div>
    </div>
    <script>
   
    // Add HTML2PDF library script in the head section
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
    document.head.appendChild(script);

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
        downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
        downloadBtn.style.pointerEvents = 'none';

        // Generate PDF
        html2pdf().set(opt).from(element).save().then(() => {
            // Reset button state
            downloadBtn.innerHTML = '<i class="fas fa-download"></i> Download Report';
            downloadBtn.style.pointerEvents = 'auto';
        });
    }

    // Initialize charts and other functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Your existing chart initialization code here
        const ctx = document.getElementById('topSellingChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Roblox', 'Free Fire', 'Mobile Legends', 'PUBG', 'CODM'],
                datasets: [{
                    label: 'Sales Amount',
                    data: [150000, 150000, 300000, 150000, 200000],
                    backgroundColor: '#4e73df'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
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

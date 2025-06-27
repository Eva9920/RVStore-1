<?php
require_once 'config.php';
requireAuth();

// Get dashboard stats
$stats = [];
$result = $conn->query("SELECT SUM(final_amount) as total_transactions FROM transactions WHERE status='success'");
$stats['total_transactions'] = $result->fetch_assoc()['total_transactions'];

$result = $conn->query("SELECT SUM(amount - cost_price) as net_income FROM transactions WHERE status='success'");
$stats['net_income'] = $result->fetch_assoc()['net_income'];

// Get target from database or default
$result = $conn->query("SELECT value FROM settings WHERE name='annual_target'");
$target_row = $result->fetch_assoc();
$stats['annual_target'] = $target_row ? $target_row['value'] : 50000000;

// Date filter
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'monthly';

// Get game sales data for charts
$games = $conn->query("SELECT id, name FROM games");
$game_sales_data = [];
$game_profit_data = [];

while($game = $games->fetch_assoc()) {
    // Sales data
    $result = $conn->query("
        SELECT 
            DATE_FORMAT(transaction_time, '%Y-%m-%d') as day,
            SUM(final_amount) as total_sales
        FROM transactions
        WHERE status = 'success' AND game_id = {$game['id']}
        GROUP BY DATE_FORMAT(transaction_time, '%Y-%m-%d')
        ORDER BY day DESC
        LIMIT 30
    ");
    
    $game_sales_data[$game['id']] = [
        'name' => $game['name'],
        'data' => []
    ];
    
    while($row = $result->fetch_assoc()) {
        $game_sales_data[$game['id']]['data'][] = $row;
    }
    
    // Profit data
    $result = $conn->query("
        SELECT 
            DATE_FORMAT(transaction_time, '%Y-%m') as month,
            SUM(profit) as total_profit
        FROM transactions
        WHERE status = 'success' AND game_id = {$game['id']}
        GROUP BY DATE_FORMAT(transaction_time, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    
    $game_profit_data[$game['id']] = [
        'name' => $game['name'],
        'data' => []
    ];
    
    while($row = $result->fetch_assoc()) {
        $game_profit_data[$game['id']]['data'][] = $row;
    }
}

// Get periodic sales data for line chart
$periodic_sales = [];
if ($date_filter == 'daily') {
    $result = $conn->query("
        SELECT 
            DATE(transaction_time) as period,
            SUM(final_amount) as total_sales,
            SUM(profit) as total_profit
        FROM transactions
        WHERE status = 'success'
        AND DATE(transaction_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(transaction_time)
        ORDER BY period ASC
    ");
} elseif ($date_filter == 'yearly') {
    $result = $conn->query("
        SELECT 
            YEAR(transaction_time) as period,
            SUM(final_amount) as total_sales,
            SUM(profit) as total_profit
        FROM transactions
        WHERE status = 'success'
        GROUP BY YEAR(transaction_time)
        ORDER BY period ASC
    ");
} else {
    // Default monthly
    $result = $conn->query("
        SELECT 
            DATE_FORMAT(transaction_time, '%Y-%m') as period,
            SUM(final_amount) as total_sales,
            SUM(profit) as total_profit
        FROM transactions
        WHERE status = 'success'
        AND DATE(transaction_time) >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(transaction_time, '%Y-%m')
        ORDER BY period ASC
    ");
}

while($row = $result->fetch_assoc()) {
    $periodic_sales[] = $row;
}

// Get game distribution data for bar chart
$game_distribution = [];
$result = $conn->query("
    SELECT 
        g.name as game_name,
        SUM(t.final_amount) as total_sales,
        SUM(t.profit) as total_profit
    FROM transactions t
    JOIN games g ON t.game_id = g.id
    WHERE t.status = 'success'
    GROUP BY g.name
    ORDER BY total_sales DESC
");

while($row = $result->fetch_assoc()) {
    $game_distribution[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
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
            margin-bottom: 20px;
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
            gap: 20px;
            margin-bottom: 25px;
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
            gap: 25px;
            margin-bottom: 25px;
        }

        .content-card {
            background: var(--card-gradient);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
            margin-bottom: 25px;
        }

        .content-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
        }

        .content-card.wide {
            grid-column: span 2;
            margin-bottom: 25px;
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
 
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
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

    </style>
</head>
<body>
    <div class="dashboard-container">
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
                    <li class="list-item active">
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
                    <li class="list-item">
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
                <h2>Dashboard</h2>
                <div>
                    <select class="period-select" id="dateFilter" onchange="updateDateFilter()">
                        <option value="daily" <?php echo $date_filter == 'daily' ? 'selected' : ''; ?>>Daily</option>
                        <option value="monthly" <?php echo $date_filter == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="yearly" <?php echo $date_filter == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>
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

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Total Transactions</h3>
                    <p>IDR <?php echo number_format($stats['total_transactions'] ?? 0, 0, ',', '.'); ?></p>
                    <i class="fas fa-money-bill-wave stat-icon"></i>
                </div>
                
                <div class="stat-card">
                    <h3>Net Income</h3>
                    <p>IDR <?php echo number_format($stats['net_income'] ?? 0, 0, ',', '.'); ?></p>
                    <i class="fas fa-chart-line stat-icon"></i>
                </div>
                
                <div class="stat-card">
                    <h3>Annual Target</h3>
                    <p>IDR <?php echo number_format($stats['annual_target'], 0, ',', '.'); ?></p>
                    <i class="fas fa-bullseye stat-icon"></i>
                </div>
            </div>

            <!-- Sales Trend Chart -->
            <div class="content-card wide">
                <div class="card-header">
                    <h2>Sales & Profit Trend (<?php echo ucfirst($date_filter); ?>)</h2>
                </div>
                <div class="chart-container">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>

            <!-- Game Distribution Chart -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Game Distribution</h2>
                </div>
                <div class="chart-container">
                    <canvas id="gameDistributionChart"></canvas>
                </div>
            </div>

            <!-- Game Profit Analysis -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Game Profit Analysis</h2>
                </div>
                <div class="chart-container">
                    <canvas id="gameProfitChart"></canvas>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="content-card wide">
                <div class="card-header">
                    <h2>Recent Transactions</h2>
                </div>
                <div class="chart-container">
                    <table class="transaction-table">
                        <thead>
                            <tr>
                                <th>Game</th>
                                <th>Package</th>
                                <th>Amount</th>
                                <th>Profit</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recent_transactions = $conn->query("
                                SELECT 
                                    g.name as game_name,
                                    tp.package_name,
                                    t.final_amount,
                                    t.profit,
                                    t.transaction_time,
                                    t.status
                                FROM transactions t
                                JOIN games g ON t.game_id = g.id
                                JOIN topup_packages tp ON t.package_id = tp.id
                                ORDER BY t.transaction_time DESC
                                LIMIT 5
                            ");
                            
                            if ($recent_transactions->num_rows > 0):
                                while($row = $recent_transactions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['game_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                                        <td>IDR <?php echo number_format($row['final_amount'], 0, ',', '.'); ?></td>
                                        <td>IDR <?php echo number_format($row['profit'], 0, ',', '.'); ?></td>
                                        <td><?php echo date('d M Y', strtotime($row['transaction_time'])); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $row['status']; ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr><td colspan="6">No recent transactions</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Toggle sidebar
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
        
        // Update date filter
        function updateDateFilter() {
            const filter = document.getElementById('dateFilter').value;
            window.location.href = `?date_filter=${filter}`;
        }
        
        // Sales Trend Chart
        const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
        const salesTrendChart = new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($periodic_sales as $period): ?>
                        '<?php 
                        if ($date_filter == 'daily') {
                            echo date('d M', strtotime($period['period']));
                        } elseif ($date_filter == 'yearly') {
                            echo $period['period'];
                        } else {
                            echo date('M Y', strtotime($period['period'] . '-01'));
                        }
                        ?>',
                    <?php endforeach; ?>
                ],
                datasets: [
                    {
                        label: 'Total Sales',
                        data: [
                            <?php foreach ($periodic_sales as $period): ?>
                                <?php echo $period['total_sales']; ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Total Profit',
                        data: [
                            <?php foreach ($periodic_sales as $period): ?>
                                <?php echo $period['total_profit']; ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#ff1493',
                        backgroundColor: 'rgba(255, 20, 147, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': IDR ' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'IDR ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Game Distribution Chart
        const gameDistributionCtx = document.getElementById('gameDistributionChart').getContext('2d');
        const gameDistributionChart = new Chart(gameDistributionCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($game_distribution as $game): ?>
                        '<?php echo $game['game_name']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [
                    {
                        label: 'Total Sales',
                        data: [
                            <?php foreach ($game_distribution as $game): ?>
                                <?php echo $game['total_sales']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: 'rgba(67, 97, 238, 0.7)',
                        borderColor: '#4361ee',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Total Sales: IDR ' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'IDR ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Game Profit Chart
        const gameProfitCtx = document.getElementById('gameProfitChart').getContext('2d');
        const gameProfitChart = new Chart(gameProfitCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($game_distribution as $game): ?>
                        '<?php echo $game['game_name']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [
                    {
                        label: 'Total Profit',
                        data: [
                            <?php foreach ($game_distribution as $game): ?>
                                <?php echo $game['total_profit']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: 'rgba(255, 20, 147, 0.7)',
                        borderColor: '#ff1493',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Total Profit: IDR ' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'IDR ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
<?php
require_once 'config.php';
requireAuth();

// Handle form submissions
$message = '';
$messageType = '';

// Update order status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    if ($stmt->execute()) {
        $message = "Order status updated successfully!";
        $messageType = "success";
    } else {
        $message = "Error updating order status: " . $conn->error;
        $messageType = "error";
    }
    $stmt->close();
}

// Process order (complete the topup)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'process_order') {
    $order_id = (int)$_POST['order_id'];
    
    $stmt = $conn->prepare("UPDATE orders SET status = 'completed', processed_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    if ($stmt->execute()) {
        $message = "Order processed successfully!";
        $messageType = "success";
    } else {
        $message = "Error processing order: " . $conn->error;
        $messageType = "error";
    }
    $stmt->close();
}

// Fetch orders with search and filter functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$game_filter = isset($_GET['game']) ? $_GET['game'] : '';

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(o.customer_name LIKE ? OR o.game_id LIKE ? OR o.customer_email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($game_filter)) {
    $where_conditions[] = "o.game_name = ?";
    $params[] = $game_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination setup
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Get total orders count
$total_query = $conn->prepare("SELECT COUNT(*) as total FROM orders o $where_clause");
if (!empty($params)) {
    $total_query->bind_param($param_types, ...$params);
}
$total_query->execute();
$total_result = $total_query->get_result();
$total_row = $total_result->fetch_assoc();
$total = $total_row['total'];
$total_pages = ceil($total / $per_page);
$total_query->close();

// Prepare for orders query (with pagination)
$orders_params = $params;
$orders_param_types = $param_types;
$orders_params[] = $per_page;
$orders_params[] = $offset;
$orders_param_types .= 'ii';

// Fetch orders with pagination
$orders_query = $conn->prepare("SELECT 
    o.id AS order_id, 
    o.customer_name, 
    o.customer_email, 
    o.game_name, 
    o.game_id, 
    o.package_name, 
    o.amount, 
    o.price, 
    o.status AS order_status, 
    o.payment_method, 
    o.created_at AS order_date
    FROM orders o 
    $where_clause 
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?");

$orders_query->bind_param($orders_param_types, ...$orders_params);
$orders_query->execute();
$orders_result = $orders_query->get_result();
$orders = $orders_result->fetch_all(MYSQLI_ASSOC);
$orders_query->close();

// Get statistics
$stats_query = $conn->query("SELECT 
    COUNT(*) as total_orders,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
    COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_orders,
    SUM(CASE WHEN status = 'completed' THEN price ELSE 0 END) as total_revenue
    FROM orders");
$stats = $stats_query->fetch_assoc();

// Get unique games for filter
$games_query = $conn->query("SELECT DISTINCT game_name FROM orders ORDER BY game_name");
$games = $games_query->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Admin Panel</title>
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

        .orders-container {
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
            right: -70px;
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
            right: 20px;
        }

        .sidebar .hamburger-toggle i {
            color: white;
            font-size: 20px;
        }

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

        .search-filter-container {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-container {
            position: relative;
            width: 300px;
        }

        .search-container input {
            width: 100%;
            padding: 12px 20px 12px 45px;
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
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #ff1493;
            font-size: 16px;
        }

        .filter-select {
            padding: 12px 15px;
            border: 2px solid rgba(255, 20, 147, 0.2);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            color: var(--text-primary);
            font-size: 14px;
            transition: var(--transition);
        }

        .filter-select:focus {
            border-color: #ff1493;
            outline: none;
            box-shadow: 0 0 20px rgba(255, 20, 147, 0.3);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-gradient);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
        }

        .stat-card .stat-icon {
            font-size: 35px;
            margin-bottom: 10px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Content Card */
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

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            overflow: hidden;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 20, 147, 0.1);
            vertical-align: middle;
        }

        .table th {
            background: var(--accent-gradient);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 11px;
        }

        .table tr:hover {
            background: rgba(255, 20, 147, 0.05);
        }

        /* Badge Styles */
        .badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            letter-spacing: 0.5px;
            color: white;
        }

        .bg-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
        }

        .bg-info {
            background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
        }

        .bg-success {
            background: linear-gradient(135deg, #00ff88 0%, #00cc70 100%);
        }

        .bg-danger {
            background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
        }

        /* Action Buttons */
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }

        .btn-primary {
            background: var(--accent-gradient);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #00ff88 0%, #00cc70 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Game Icon */
        .game-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: var(--accent-gradient);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
            margin-right: 8px;
        }

        /* Price styling */
        .price {
            font-weight: 700;
            color: #00cc70;
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

        /* Chatbot Container */
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

        /* Results Info */
        .results-info {
            margin-bottom: 15px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }
        
        .pagination a {
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.3);
            color: var(--text-primary);
        }
        
        .pagination a:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }
        
        .pagination a.active {
            background: var(--accent-gradient);
            color: white;
            box-shadow: 0 5px 15px rgba(255, 20, 147, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .search-filter-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .search-container {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table {
                font-size: 11px;
            }
            
            .table th,
            .table td {
                padding: 8px 10px;
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
            width: 1350px;
            height: 600px;
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

        .footer {
            bottom: 0;
            left: 0;
            width: 100%;
            color: #fff;
            padding: 5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="orders-container">
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
                    <li class="list-item active">
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
                <h2>Orders Management</h2>
                <form method="GET" class="search-filter-container">
                    <div class="search-container">
                        <input type="text" name="search" placeholder="Search orders..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-search"></i>
                    </div>
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                    <select name="game" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Games</option>
                        <?php foreach ($games as $game): ?>
                            <option value="<?php echo htmlspecialchars($game['game_name']); ?>" <?php echo $game_filter == $game['game_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($game['game_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- Bottom Icons -->
            <div class="bottom-icons">
                <div class="bottom-icon" onclick="window.location.href='profile.php'">
                    <i class="fas fa-user"></i>
                </div>
                <div class="bottom-icon" onclick="window.location.href='notifications.php'">
                    <i class="fas fa-bell"></i>
                    <?php if ($stats['pending_orders'] > 0): ?>
                        <span class="notification-badge"><?php echo $stats['pending_orders']; ?></span>
                    <?php endif; ?>
                </div>
                <div class="bottom-icon" onclick="window.location.href='settings.php'">
                    <i class="fas fa-cog"></i>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_orders']); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['pending_orders']); ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['completed_orders']); ?></div>
                    <div class="stat-label">Completed Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-times"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['failed_orders']); ?></div>
                    <div class="stat-label">Failed Orders</div>
                </div>
            </div>

            <!-- Order List -->
            <div class="content-card">
                <div class="results-info">
                    Showing <?php echo min($offset + 1, $total); ?> to <?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?> orders
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Game</th>
                                <th>Order Date</th>
                                <th>Order Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['game_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            switch($order['order_status']) {
                                                case 'pending': echo 'bg-warning'; break;
                                                case 'processing': echo 'bg-info'; break;
                                                case 'completed': echo 'bg-success'; break;
                                                case 'failed': echo 'bg-danger'; break;
                                                default: echo '';
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars($order['order_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="order_details.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye"></i> Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&game=<?= urlencode($game_filter) ?>" 
                           class="<?= ($i == $page) ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chatbot -->
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

    <!-- Footer -->
    <div class="footer">
        <p>&copy; 2025 RVStore. All rights reserved.</p>
    </div>

    <script>
        function showOrderDetails(orderId) {
            window.location.href = "order_details.php?order_id=" + orderId;
        }
    </script>

    <script>
    // Chatbot functionality
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
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
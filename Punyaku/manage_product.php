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

        .manageproduct-container {
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
        .product-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            overflow: hidden;
        }

        .product-table th,
        .product-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 20, 147, 0.1);
        }

        .product-table th {
            background: var(--accent-gradient);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .product-table tr:hover {
            background: rgba(255, 20, 147, 0.05);
        }

        .product-table tr:last-child td {
            border-bottom: none;
        }

        .product-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
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
            color: var(--text-primary);
        }

        .product-category {
            font-size: 13px;
            color: var(--text-secondary);
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

        .status-active {
            background: linear-gradient(135deg, #00ff88 0%, #00cc70 100%);
            color: white;
        }

        .status-inactive {
            background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
            color: white;
        }

        /* Action Buttons */
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            margin: 0 5px;
            font-size: 16px;
            padding: 8px 12px;
            border-radius: 8px;
            transition: var(--transition);
        }

        .action-btn.edit {
            color: #1976d2;
            background: rgba(25, 118, 210, 0.1);
        }

        .action-btn.edit:hover {
            background: rgba(25, 118, 210, 0.2);
            transform: scale(1.1);
        }

        .action-btn.delete {
            color: #d32f2f;
            background: rgba(211, 47, 47, 0.1);
        }

        .action-btn.delete:hover {
            background: rgba(211, 47, 47, 0.2);
            transform: scale(1.1);
        }

        /* Add Product Button */
        .add-product-btn {
            background: var(--accent-gradient);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            box-shadow: 0 5px 15px rgba(255, 20, 147, 0.3);
            font-size: 16px;
            margin-bottom: 20px;
        }

        .add-product-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 20, 147, 0.4);
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
            background: var(--card-gradient);
            backdrop-filter: blur(20px);
            padding: 30px;
            border-radius: var(--border-radius);
            width: 450px;
            max-width: 90%;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 20, 147, 0.1);
        }

        .modal-header h3 {
            font-size: 22px;
            font-weight: 700;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #ff1493;
            transition: var(--transition);
        }

        .close-modal:hover {
            color: #ff69b4;
            transform: scale(1.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid rgba(255, 20, 147, 0.2);
            border-radius: 12px;
            font-size: 15px;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.8);
        }

        .form-control:focus {
            border-color: #ff1493;
            outline: none;
            box-shadow: 0 0 15px rgba(255, 20, 147, 0.2);
            background: rgba(255, 255, 255, 0.95);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.3);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--accent-gradient);
            color: white;
            box-shadow: 0 5px 15px rgba(255, 20, 147, 0.3);
        }

        .btn-primary:hover {
            box-shadow: 0 8px 25px rgba(255, 20, 147, 0.4);
            transform: translateY(-2px);
        }

        /* Pagination */
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

        /* Messages */
        .message-alert {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-weight: 600;
            box-shadow: var(--shadow);
        }

        .message-success {
            background: linear-gradient(135deg, #00ff88 0%, #00cc70 100%);
            color: white;
        }

        .message-error {
            background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .search-container {
                width: 250px;
            }
            
            .topbar h2 {
                font-size: 22px;
            }
            
            .product-table {
                display: block;
                overflow-x: auto;
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
    <div class="manageproduct-container">
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
                    <li class="list-item active">
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
                <h2>Manage Product</h2>
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

            <!-- Add Product Button -->
            <button class="add-product-btn" id="addProductBtn">
                <i class="fas fa-plus"></i> Add New Product
            </button>

            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="message-alert message-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="message-alert message-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Product Table -->
            <div class="content-card">
                <table class="product-table">
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
                        <?php while ($game = $games->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <div class="product-image">
                                            <i class="fas fa-gamepad"></i>
                                        </div>
                                        <div>
                                            <div class="product-name"><?php echo htmlspecialchars($game['name']); ?></div>
                                            <div class="product-category"><?php echo htmlspecialchars($game['description']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>IDR <?php 
    $price = $game['base_price'] ?? 0; // Default to 0 if missing
    echo number_format($price, 0, ',', '.'); 
?></td>
<td><?php echo $game['stock'] ?? 0; ?></td>
<td>
    <span class="status-badge <?php 
        $stock = $game['stock'] ?? 0;
        echo ($stock > 0) ? 'status-active' : 'status-inactive'; 
    ?>">
        <?php echo ($stock > 0) ? 'Active' : 'Inactive'; ?>
    </span>
</td>
                                <td>
                                    <button class="action-btn edit" onclick="editProduct(
    <?php echo $game['id']; ?>,
    '<?php echo addslashes($game['name']); ?>',
    '<?php echo addslashes($game['description']); ?>',
    <?php echo $game['base_price'] ?? 0; ?>,
    <?php echo $game['stock'] ?? 0; ?>
)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="confirmDelete(<?php echo $game['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
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
            <form id="productForm" method="POST" action="">
                <input type="hidden" id="editId" name="id">
                <input type="hidden" id="formAction" name="add_game" value="1">
                
                <div class="form-group">
                    <label for="gameName">Product Name</label>
                    <input type="text" id="gameName" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="gameDescription">Description</label>
                    <textarea id="gameDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="gamePrice">Price</label>
                    <input type="number" id="gamePrice" name="base_price" class="form-control" min="0" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="gameStock">Stock</label>
                    <input type="number" id="gameStock" name="stock" class="form-control" min="0" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="id" id="deleteId">
        <input type="hidden" name="delete_game" value="1">
    </form>

    <script>
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

        // Delete confirmation
        window.confirmDelete = function(id) {
            if (confirm("Are you sure you want to delete this product?")) {
                document.getElementById("deleteId").value = id;
                document.getElementById("deleteForm").submit();
            }
        };

        // Close modal
        function closeModalFunc() {
            modal.classList.remove("active");
        }
        
        closeModal.onclick = closeModalFunc;
        cancelBtn.onclick = closeModalFunc;

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


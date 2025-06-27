<?php
require_once 'config.php';
requireAuth();

// Get notifications from database
$notifications = [];
$result = $conn->query("
    SELECT 
        n.id,
        n.title,
        n.message,
        n.is_read as `read`,
        DATE_FORMAT(n.created_at, '%e %b %Y %H:%i') as formatted_time,
        TIMESTAMPDIFF(HOUR, n.created_at, NOW()) as hours_ago,
        n.created_at
    FROM notifications n
    WHERE n.user_id = " . $_SESSION['user_id'] . "
    ORDER BY n.created_at DESC
    LIMIT 50
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Format time display
        if ($row['hours_ago'] < 24) {
            $time_text = $row['hours_ago'] . " hours ago";
        } else {
            $time_text = $row['formatted_time'];
        }
        
        $notifications[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'time' => $time_text,
            'read' => (bool)$row['read'],
            'created_at' => $row['created_at']
        ];
    }
}

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = " . $_SESSION['user_id'] . " AND is_read = 0");
    
    // Update all notifications in the array to read
    foreach ($notifications as &$notification) {
        $notification['read'] = true;
    }
}

// Mark single notification as read
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_read'])) {
    $notification_id = (int)$_POST['notification_id'];
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notification_id AND user_id = " . $_SESSION['user_id']);
    
    // Update the specific notification in the array
    foreach ($notifications as &$notification) {
        if ($notification['id'] == $notification_id) {
            $notification['read'] = true;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - RVStore Admin</title>
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

        /* Notification specific styles */
        .notification-container {
            padding: 20px;
        }
        
        .notification-item {
            background: var(--card-gradient);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: var(--shadow);
            border-left: 4px solid rgba(255, 20, 147, 0.5);
            transition: var(--transition);
            position: relative;
        }
        
        .notification-item.unread {
            border-left: 4px solid #00ff88;
            background: rgba(0, 255, 136, 0.05);
        }
        
        .notification-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .notification-title {
            font-weight: 700;
            font-size: 18px;
            color: var(--text-primary);
        }
        
        .notification-time {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .notification-message {
            color: var(--text-secondary);
            line-height: 1.5;
        }
        
        .mark-all-read {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .mark-all-read button {
            background: var(--accent-gradient);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .mark-all-read button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 20, 147, 0.3);
        }

        .mark-read-btn {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 12px;
            cursor: pointer;
            transition: var(--transition);
        }

        .mark-read-btn:hover {
            color: #ff1493;
        }

        /* Responsive */
        @media (max-width: 768px) {
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
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="hamburger-toggle">
                <i class='bx bx-menu'></i>
            </div>
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
                        <a href="orders.php">
                            <i class="bi bi-cart3"></i>
                            <span class="link-name" style="--i:3;">Orders</span>
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
                <h2>Notifications</h2>
                <form method="GET" class="search-container">
                    <input type="text" name="search" placeholder="Search notifications...">
                    <i class="fas fa-search"></i>
                </form>
                <div class="topbar-icons">
                    <i class="fas fa-cog"></i>
                    <i class="fas fa-bell"></i>
                    <i class="fas fa-user-circle"></i>
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

            <!-- Notification Content -->
            <div class="content-card wide">
                <div class="card-header">
                    <h2>Recent Notifications</h2>
                    <form method="POST">
                        <button type="submit" name="mark_all_read" class="mark-all-read">Mark All as Read</button>
                    </form>
                </div>
                
                <div class="notification-container">
                    <?php if (count($notifications) > 0): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?php echo $notification['read'] ? '' : 'unread'; ?>">
                                <div class="notification-header">
                                    <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                    <div class="notification-time"><?php echo htmlspecialchars($notification['time']); ?></div>
                                </div>
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </div>
                                <?php if (!$notification['read']): ?>
                                    <form method="POST" class="mark-read-form">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" name="mark_read" class="mark-read-btn">Mark as Read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="notification-item">
                            <div class="notification-message">No notifications found</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality
        document.querySelector('.hamburger-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        document.querySelector('.toggle-btn').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
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

        // Handle mark as read forms
        document.querySelectorAll('.mark-read-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const notificationItem = this.closest('.notification-item');
                notificationItem.classList.remove('unread');
                this.remove();
                
                // Submit the form in the background
                fetch('notification.php', {
                    method: 'POST',
                    body: new FormData(this)
                });
            });
        });
    </script>
</body>
</html>
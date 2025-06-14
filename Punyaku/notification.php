<?php
require_once 'config.php';
requireAuth();

$notifications = [
    ['id' => 1, 'title' => 'New Order', 'message' => 'You have received a new order #12345', 'time' => '2 hours ago', 'read' => false],
    ['id' => 2, 'title' => 'Payment Received', 'message' => 'Payment for order #12344 has been received', 'time' => '5 hours ago', 'read' => true],
    ['id' => 3, 'title' => 'Low Stock', 'message' => 'Game "Cyberpunk 2077" is running low on stock', 'time' => '1 day ago', 'read' => true],
    ['id' => 4, 'title' => 'System Update', 'message' => 'New system update available. Please update at your convenience.', 'time' => '2 days ago', 'read' => true],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Copy all the CSS from dashboard.php */
        :root {
            --primary-gradient: linear-gradient(135deg, #2a0845 0%, #6441a5 100%);
            --accent-gradient: linear-gradient(135deg, #ff1493 0%, #6441a5 100%);
            /* ... rest of the CSS variables ... */
        }
        
        /* ... rest of the CSS rules from dashboard.php ... */
        
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
            border-left: 4px solid #ff1493;
            transition: var(--transition);
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation (same as dashboard.php) -->
        <div class="sidebar">
            <div class="hamburger-toggle">
                <i class='bx bx-menu'></i>
            </div>
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
                    <!-- ... rest of the menu items ... -->
                </ul>
            </nav>
            <div class="sidebar-bottom-icons">
                <a href="notification.php" class="list-item active">
                    <i class='bx bx-bell'></i>
                    <span class="link-name" style="--i:7;">Notifications</span>
                </a>
                <a href="settings.php" class="list-item">
                    <i class='bx bx-cog'></i>
                    <span class="link-name" style="--i:8;">Settings</span>
                </a>
            </div>
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
            </div>

            <!-- Notification Content -->
            <div class="content-card wide">
                <div class="mark-all-read">
                    <button>Mark All as Read</button>
                </div>
                
                <div class="notification-container">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['read'] ? '' : 'unread'; ?>">
                            <div class="notification-header">
                                <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                <div class="notification-time"><?php echo htmlspecialchars($notification['time']); ?></div>
                            </div>
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Include the same scripts as dashboard.php -->
    <script>
        // Same sidebar toggle functionality as dashboard.php
        document.querySelector('.hamburger-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Mark all as read functionality
        document.querySelector('.mark-all-read button').addEventListener('click', function() {
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.remove('unread');
            });
        });
    </script>
</body>
</html>
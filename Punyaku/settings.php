<?php
require_once 'config.php';
requireAuth();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
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
        
        /* Settings specific styles */
        .settings-container {
            padding: 20px;
        }
        
        .settings-section {
            margin-bottom: 30px;
        }
        
        .settings-section h3 {
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 20, 147, 0.1);
            color: var(--text-primary);
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
        
        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid rgba(255, 20, 147, 0.2);
            border-radius: 12px;
            font-size: 16px;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.8);
        }
        
        .form-group input:focus, 
        .form-group select:focus {
            border-color: #ff1493;
            outline: none;
            box-shadow: 0 0 15px rgba(255, 20, 147, 0.2);
        }
        
        .save-btn {
            background: var(--accent-gradient);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .save-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255, 20, 147, 0.3);
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background: var(--accent-gradient);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            gap: 15px;
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
                <a href="notification.php" class="list-item">
                    <i class='bx bx-bell'></i>
                    <span class="link-name" style="--i:7;">Notifications</span>
                </a>
                <a href="settings.php" class="list-item active">
                    <i class='bx bx-cog'></i>
                    <span class="link-name" style="--i:8;">Settings</span>
                </a>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <h2>Settings</h2>
                <form method="GET" class="search-container">
                    <input type="text" name="search" placeholder="Search settings...">
                    <i class="fas fa-search"></i>
                </form>
            </div>

            <!-- Settings Content -->
            <div class="content-card wide">
                <div class="settings-container">
                    <form>
                        <div class="settings-section">
                            <h3>Account Settings</h3>
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" value="admin" readonly>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" value="admin@example.com">
                            </div>
                            <div class="form-group">
                                <label for="password">Change Password</label>
                                <input type="password" id="password" placeholder="Enter new password">
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3>Notification Preferences</h3>
                            <div class="form-group">
                                <label class="toggle-label">
                                    <span>Email Notifications</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" checked>
                                        <span class="slider"></span>
                                    </label>
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="toggle-label">
                                    <span>Push Notifications</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" checked>
                                        <span class="slider"></span>
                                    </label>
                                </label>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3>System Settings</h3>
                            <div class="form-group">
                                <label for="timezone">Timezone</label>
                                <select id="timezone">
                                    <option>Asia/Jakarta</option>
                                    <option>Asia/Singapore</option>
                                    <option>UTC</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="theme">Theme</label>
                                <select id="theme">
                                    <option>Light</option>
                                    <option>Dark</option>
                                    <option>System Default</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="save-btn">Save Changes</button>
                    </form>
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
    </script>
</body>
</html>
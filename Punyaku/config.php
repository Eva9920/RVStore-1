<?php
// Pastikan session_start() hanya dipanggil sekali
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "game_topup_management";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");

// Enable mysqli error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Authentication function
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
}

// Admin authentication function
function requireAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: dashboard.php');
        exit();
    }
}

function formatCurrency($amount) {
    return 'IDR ' . number_format($amount, 0, ',', '.');
}

// Format date
function formatDate($date) {
    return date('d M Y H:i', strtotime($date));
}

// Sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Get user initials for avatar
function getInitials($name) {
    $initials = '';
    $words = explode(' ', $name);
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    return $initials;
}

// Enhanced database functions for better error handling
function executeQuery($sql, $params = [], $types = '') {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    return $stmt;
}

// Function for SELECT queries
function selectQuery($sql, $params = [], $types = '') {
    try {
        $stmt = executeQuery($sql, $params, $types);
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    } catch (Exception $e) {
        error_log("Database SELECT Error: " . $e->getMessage());
        return false;
    }
}

// Function for single row SELECT
function selectSingle($sql, $params = [], $types = '') {
    try {
        $stmt = executeQuery($sql, $params, $types);
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    } catch (Exception $e) {
        error_log("Database SELECT SINGLE Error: " . $e->getMessage());
        return false;
    }
}

// Function for INSERT queries
function insertQuery($sql, $params = [], $types = '') {
    try {
        $stmt = executeQuery($sql, $params, $types);
        $insert_id = $conn->insert_id;
        $stmt->close();
        return $insert_id;
    } catch (Exception $e) {
        error_log("Database INSERT Error: " . $e->getMessage());
        return false;
    }
}

// Function for UPDATE/DELETE queries
function modifyQuery($sql, $params = [], $types = '') {
    try {
        $stmt = executeQuery($sql, $params, $types);
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $affected_rows;
    } catch (Exception $e) {
        error_log("Database MODIFY Error: " . $e->getMessage());
        return false;
    }
}

// Function to close database connection
function closeConnection() {
    global $conn;
    if ($conn) {
        $conn->close();
    }
}

// Enhanced authentication with remember me
function login($username, $password, $remember = false) {
    $sql = "SELECT id, username, email, password, role, status FROM users WHERE username = ? OR email = ?";
    $user = selectSingle($sql, [$username, $username], 'ss');
    
    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] === 'active') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Update last login
            $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            modifyQuery($updateSql, [$user['id']], 'i');
            
            // Remember me functionality
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $tokenSql = "INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
                insertQuery($tokenSql, [$user['id'], $token, $expiry], 'iss');
                
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
            }
            
            return true;
        }
    }
    return false;
}

// Get user settings
function getUserSettings($user_id) {
    $sql = "SELECT * FROM user_settings WHERE user_id = ?";
    $settings = selectSingle($sql, [$user_id], 'i');
    
    if (!$settings) {
        // Create default settings if none exist
        $defaultSettings = [
            'email_notifications' => 1,
            'push_notifications' => 1,
            'timezone' => 'Asia/Jakarta',
            'theme' => 'Light',
            'language' => 'Indonesia'
        ];
        
        $sql = "INSERT INTO user_settings (user_id, email_notifications, push_notifications, timezone, theme, language) 
                VALUES (?, ?, ?, ?, ?, ?)";
        insertQuery($sql, [
            $user_id, 
            $defaultSettings['email_notifications'],
            $defaultSettings['push_notifications'],
            $defaultSettings['timezone'],
            $defaultSettings['theme'],
            $defaultSettings['language']
        ], 'iisss');
        
        return $defaultSettings;
    }
    
    return $settings;
}

// Update user settings
function updateUserSettings($user_id, $settings) {
    $sql = "UPDATE user_settings SET 
            email_notifications = ?,
            push_notifications = ?,
            timezone = ?,
            theme = ?,
            language = ?,
            updated_at = NOW()
            WHERE user_id = ?";
    
    return modifyQuery($sql, [
        $settings['email_notifications'],
        $settings['push_notifications'],
        $settings['timezone'],
        $settings['theme'],
        $settings['language'],
        $user_id
    ], 'iisssi');
}

// Logout function
function logout() {
    // Remove remember token if exists
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $deleteSql = "DELETE FROM remember_tokens WHERE token = ?";
        modifyQuery($deleteSql, [$token], 's');
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Clear session
    session_unset();
    session_destroy();
    
    // Redirect to login
    header('Location: index.php');
    exit();
}

// Check remember me token
function checkRememberToken() {
    if (isset($_COOKIE['remember_token']) && !isset($_SESSION['user_id'])) {
        $token = $_COOKIE['remember_token'];
        $sql = "SELECT u.id, u.username, u.email, u.role, u.status 
                FROM users u 
                JOIN remember_tokens rt ON u.id = rt.user_id 
                WHERE rt.token = ? AND rt.expires_at > NOW()";
        
        $user = selectSingle($sql, [$token], 's');
        
        if ($user && $user['status'] === 'active') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Update last login
            $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            modifyQuery($updateSql, [$user['id']], 'i');
        } else {
            // Invalid token, remove cookie
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
}

// Flash message functions
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// Enhanced validation functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    // Minimum 8 characters, at least one letter and one number
    return preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*#?&]{8,}$/', $password);
}

function validateUsername($username) {
    // 3-20 characters, alphanumeric and underscore only
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

// Generate secure random password
function generateRandomPassword($length = 12) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// File upload function
function uploadFile($file, $uploadDir = 'uploads/', $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmp = $file['tmp_name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Check file type
    if (!in_array($fileExt, $allowedTypes)) {
        return false;
    }
    
    // Check file size (5MB max)
    if ($fileSize > 5 * 1024 * 1024) {
        return false;
    }
    
    // Generate unique filename
    $newFileName = uniqid() . '.' . $fileExt;
    $uploadPath = $uploadDir . $newFileName;
    
    // Create directory if not exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    if (move_uploaded_file($fileTmp, $uploadPath)) {
        return $newFileName;
    }
    
    return false;
}

// Logging function
function logActivity($user_id, $action, $details = '') {
    $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    insertQuery($sql, [$user_id, $action, $details, $ip, $userAgent], 'issss');
}

// Get user info by ID
function getUserById($user_id) {
    $sql = "SELECT id, username, email, full_name, role, status, avatar, created_at, last_login 
            FROM users WHERE id = ?";
    return selectSingle($sql, [$user_id], 'i');
}

// Check if user exists
function userExists($username, $email) {
    $sql = "SELECT id FROM users WHERE username = ? OR email = ?";
    $user = selectSingle($sql, [$username, $email], 'ss');
    return $user !== false;
}

// Get user statistics
function getUserStats($user_id) {
    $stats = [];
    
    // Total orders
    $sql = "SELECT COUNT(*) as total_orders FROM orders WHERE user_id = ?";
    $result = selectSingle($sql, [$user_id], 'i');
    $stats['total_orders'] = $result['total_orders'] ?? 0;
    
    // Total spent
    $sql = "SELECT SUM(total_amount) as total_spent FROM orders WHERE user_id = ? AND status = 'completed'";
    $result = selectSingle($sql, [$user_id], 'i');
    $stats['total_spent'] = $result['total_spent'] ?? 0;
    
    // Recent orders
    $sql = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
    $stats['recent_orders'] = selectQuery($sql, [$user_id], 'i');
    
    return $stats;
}

// Pagination helper
function getPagination($currentPage, $totalRecords, $recordsPerPage = 10) {
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $offset = ($currentPage - 1) * $recordsPerPage;
    
    return [
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'records_per_page' => $recordsPerPage,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

// Format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check remember me token on page load
checkRememberToken();

// Register shutdown function to close connection
register_shutdown_function('closeConnection');

// Fungsi untuk mendapatkan data user saat ini
function getCurrentUser() {
    global $conn;
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT id, nama_lengkap as username, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

// Fungsi untuk update profile
function updateProfile($userId, $username, $email, $profileImage = null) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ? WHERE id = ?");
    $stmt->bind_param("ssi", $username, $email, $userId);
    
    return $stmt->execute();
}

// Fungsi untuk menghandle upload gambar
function handleImageUpload($file) {
    $targetDir = "uploads/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = uniqid() . '_' . basename($file['name']);
    $targetPath = $targetDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $fileName;
    }
    return null;
}

// function getUnreadNotificationsCount($user_id) {
//     global $conn;
//     $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("i", $user_id);
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $row = $result->fetch_assoc();
//     return $row['count'];
// }

// function createNotification($user_id, $title, $message) {
//     global $conn;
//     $sql = "INSERT INTO notifications (user_id, title, message, is_read, created_at) 
//             VALUES (?, ?, ?, 0, NOW())";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("iss", $user_id, $title, $message);
//     return $stmt->execute();
// }
?>



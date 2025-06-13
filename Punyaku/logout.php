<?php
session_start();

// Include database configuration
require_once 'config.php';

// Log logout activity (optional)
if (isset($_SESSION['user_id']) && $conn) { // Ensure $conn is available before attempting to use it
    try {
        $user_id = $_SESSION['user_id'];
        $logout_time = date('Y-m-d H:i:s');
        
        // Update last activity or log logout event (optional)
        // Check if prepare was successful
        if ($stmt = $conn->prepare("UPDATE users SET last_activity = ? WHERE id = ?")) {
            $stmt->bind_param("si", $logout_time, $user_id);
            $stmt->execute();
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for last_activity update: " . $conn->error);
        }
        
    } catch (Exception $e) {
        // Log error if needed, but don't prevent logout
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Regardless of logging success, proceed with session destruction and redirect
// Clear all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any authentication cookies if you're using them
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/'); // Adjust path as needed
}

// Prevent caching of this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page
header("Location: index.php"); // Assuming your login page is login.php, adjust if it's index.php as in config.php
exit();
?>
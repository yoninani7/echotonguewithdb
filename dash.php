<?php
// Start session with security settings
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();
session_regenerate_id(true);

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com/ajax/libs/; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com/ajax/libs/ https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;");

// Error reporting (production mode)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Check if user is logged in
// if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
//     header('Location: login.php');
//     exit;
// }

// Initialize rate limiting
if (!isset($_SESSION['last_request'])) {
    $_SESSION['last_request'] = time();
    $_SESSION['request_count'] = 0;
}

$current_time = time();
if ($current_time - $_SESSION['last_request'] > 60) {
    // Reset counter if more than a minute has passed
    $_SESSION['request_count'] = 0;
    $_SESSION['last_request'] = $current_time;
}

$_SESSION['request_count']++;
if ($_SESSION['request_count'] > 30) {
    // Rate limit exceeded
    header('HTTP/1.1 429 Too Many Requests');
    die('Rate limit exceeded. Please wait and try again.');
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'echotongue';

try {
    $conn = new mysqli($host, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    $conn->set_charset("utf8mb4");
    $conn->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}

// Validation function
function validateThoughtText($text) {
    $text = trim($text);
    if (empty($text)) {
        return false;
    }
    
    // Check length
    if (strlen($text) > 1000) {
        return false;
    }
    
    // Check for excessive newlines (potential spam)
    $newline_count = substr_count($text, "\n");
    if ($newline_count > 50) {
        return false;
    }
    
    // Remove harmful content but preserve safe formatting
    $text = strip_tags($text);
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    
    return $text;
}

// Initialize variables
$message = '';
$message_type = '';
$confirm_message = '';
$id = 0;
$confirm_id = 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Invalid security token. Please refresh the page and try again.";
        $message_type = "error";
    } else {
        // Add new thought
        if (isset($_POST['add_thought'])) {
            $thought_text = validateThoughtText($_POST['thought_text'] ?? '');
            
            if ($thought_text !== false) {
                $stmt = $conn->prepare("INSERT INTO authors_thoughts (thought_date, thought_text) VALUES (NOW(), ?)");
                
                if ($stmt) {
                    $stmt->bind_param("s", $thought_text);
                    
                    if ($stmt->execute()) {
                        // Regenerate CSRF token after successful operation
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $_SESSION['success_message'] = "Thought added successfully!";
                        $stmt->close();
                        header("Location: " . htmlspecialchars($_SERVER['PHP_SELF']));
                        exit();
                    } else {
                        error_log("Error adding thought: " . $stmt->error);
                        $message = "An error occurred while adding the thought. Please try again.";
                        $message_type = "error";
                    }
                    $stmt->close();
                } else {
                    error_log("Database prepare error: " . $conn->error);
                    $message = "An error occurred. Please try again.";
                    $message_type = "error";
                }
            } else {
                $message = "Invalid thought text. Please enter text between 1 and 1000 characters.";
                $message_type = "error";
            }
        }
        
        // Update thought
        if (isset($_POST['update_thought'])) {
            $id = intval($_POST['edit_id'] ?? 0);
            $thought_text = validateThoughtText($_POST['edit_text'] ?? '');
            
            if ($id > 0 && $thought_text !== false) {
                $stmt = $conn->prepare("UPDATE authors_thoughts SET thought_text = ?, thought_date = NOW() WHERE id = ?");
                
                if ($stmt) {
                    $stmt->bind_param("si", $thought_text, $id);
                    
                    if ($stmt->execute()) {
                        // Regenerate CSRF token after successful operation
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $_SESSION['success_message'] = "Thought updated successfully!";
                        $stmt->close();
                        header("Location: " . htmlspecialchars($_SERVER['PHP_SELF']));
                        exit();
                    } else {
                        error_log("Error updating thought: " . $stmt->error);
                        $message = "An error occurred while updating the thought. Please try again.";
                        $message_type = "error";
                    }
                    $stmt->close();
                } else {
                    error_log("Database prepare error: " . $conn->error);
                    $message = "An error occurred. Please try again.";
                    $message_type = "error";
                }
            } else {
                $message = "Invalid thought data!";
                $message_type = "error";
            }
        }
    }
}

// Handle delete requests
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    if ($id <= 0) {
        $message = "Invalid thought ID.";
        $message_type = "error";
    } else {
        $token = $_GET['csrf_token'] ?? '';
        
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            $message = "Invalid security token for deletion.";
            $message_type = "error";
        } elseif (!isset($_GET['confirm'])) {
            // Show confirmation dialog
            $confirm_message = "Are you sure you want to delete this thought? This action cannot be undone.";
            $confirm_id = $id;
        } elseif (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
            // Perform deletion
            $stmt = $conn->prepare("DELETE FROM authors_thoughts WHERE id = ?");
            
            if ($stmt) {
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    // Regenerate CSRF token after successful operation
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['success_message'] = "Thought deleted successfully!";
                    $stmt->close();
                    header("Location: " . htmlspecialchars($_SERVER['PHP_SELF']));
                    exit();
                } else {
                    error_log("Error deleting thought: " . $stmt->error);
                    $message = "An error occurred while deleting the thought. Please try again.";
                    $message_type = "error";
                }
                $stmt->close();
            } else {
                error_log("Database prepare error: " . $conn->error);
                $message = "An error occurred. Please try again.";
                $message_type = "error";
            }
        }
    }
}

// Check for success message from session
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = "success";
    unset($_SESSION['success_message']);
}

// Fetch all thoughts for display
$thoughts = [];
$result = $conn->query("SELECT * FROM authors_thoughts ORDER BY thought_date DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $thoughts[] = $row;
    }
    $result->free();
} else {
    error_log("Error fetching thoughts: " . $conn->error);
    $message = "An error occurred while loading thoughts.";
    $message_type = "error";
}

// Close database connection (will be closed at end of file)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Author's Thoughts</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Montserrat:wght@300;400;500;600&family=Orbitron:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Import the existing theme variables */
        :root {
            --primary-red: #c91313c9;
            --dark-red: #b30000;
            --bg-color: #000000;
            --text-color: #e0e0e0;
            --font-heading: 'Orbitron', sans-serif;
            --font-body: 'Montserrat', sans-serif;
            --elegant-black: #0a0a0a;
            --dark-black: #000;
            --light-black: #1a1a1a;
            --medium-black: #111;
            --gray: #333;
            --light-gray: #444;
            --text-light: #ddd;
            --text-white: #fff;
            --gold: rgb(223, 222, 222);
            --transition: all 0.3s ease;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            --header-height: 80px;
            --header-shrink-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: var(--font-body);
            overflow-x: hidden;
            min-height: 100vh;
            position: relative;
        }

        /* Dashboard Container */
        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: rgba(15, 15, 15, 0.95);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px 20px;
            position: sticky;
            top: 0;
            height: 100vh;
            backdrop-filter: blur(10px);
            z-index: 100;
        }

        .sidebar-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(201, 19, 19, 0.3);
        }

        .logo {
            font-family: 'Cinzel Decorative', serif;
            font-weight: 900;
            font-size: 24px;
            color: #ffffff;
            text-transform: uppercase;
            text-decoration: none;
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .logo i {
            color: var(--primary-red);
        }

        .user-info {
            text-align: center;
            margin-bottom: 30px;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #c91313, #8c0a0a);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            font-weight: 600;
        }

        .user-name {
            font-family: 'Cinzel', serif;
            font-size: 1.2rem;
            color: var(--text-white);
        }

        .user-role {
            color: var(--primary-red);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 10px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: #aaa;
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(201, 19, 19, 0.1);
            color: var(--text-white);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .logout-btn {
            margin-top: 30px;
            background: rgba(201, 19, 19, 0.2);
            border: 1px solid rgba(201, 19, 19, 0.3);
        }

        .logout-btn:hover {
            background: rgba(201, 19, 19, 0.3);
        }

        /* Main Content */
        .main-content {
            padding: 30px;
            overflow-y: auto;
            position: relative;
            z-index: 1;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .page-title {
            font-family: 'Cinzel', serif;
            font-size: 2.5rem;
            color: var(--text-white);
        }

        .page-subtitle {
            color: #aaa;
            font-size: 1rem;
            margin-top: 5px;
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
            animation: slideIn 0.5s ease;
        }

        .message.success {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.3);
            color: #4CAF50;
        }

        .message.error {
            background: rgba(192, 0, 0, 0.1);
            border: 1px solid rgba(192, 0, 0, 0.3);
            color: #ff6b6b;
        }

        .message-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            margin-left: auto;
            font-size: 1.2rem;
            opacity: 0.7;
            transition: var(--transition);
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .message-close:hover {
            opacity: 1;
        }

        /* Form Card */
        .form-card {
            background: rgba(20, 20, 20, 0.8);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
        }

        .form-card h2 {
            font-family: 'Cinzel', serif;
            font-size: 1.8rem;
            margin-bottom: 25px;
            color: var(--text-white);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--primary-red);
        }

        .form-input,
        .form-textarea {
            width: 100%;
            padding: 14px 18px;
            background: rgba(30, 30, 30, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            color: var(--text-white);
            font-size: 1rem;
            font-family: var(--font-body);
            transition: var(--transition);
        }

        .form-textarea {
            min-height: 150px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-red);
            background: rgba(40, 40, 40, 0.9);
            box-shadow: 0 0 0 3px rgba(201, 19, 19, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Button Styles */
        .btn {
            padding: 12px 25px;
            background: radial-gradient(100% 120% at 50% 0%, #df0211 0%, #500D11 100%);
            color: #fdfdfd;
            border-radius: 8px;
            border: none;
            font-family: 'Cinzel', serif;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 0.7px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(201, 19, 19, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .btn-danger {
            background: rgba(192, 0, 0, 0.2);
            border: 1px solid rgba(192, 0, 0, 0.3);
        }

        .btn-danger:hover {
            background: rgba(192, 0, 0, 0.3);
            box-shadow: 0 10px 25px rgba(192, 0, 0, 0.2);
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 12px;
        }

        /* Thoughts Table */
        .table-container {
            background: rgba(20, 20, 20, 0.8);
            border-radius: 15px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .table-title {
            font-family: 'Cinzel', serif;
            font-size: 1.8rem;
            color: var(--text-white);
        }

        .thoughts-table {
            width: 100%;
            border-collapse: collapse;
        }

        .thoughts-table th {
            text-align: left;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary-red);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
        }

        .thoughts-table td {
            padding: 20px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .thoughts-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .thought-text {
            max-width: 500px;
            line-height: 1.6;
            color: #ccc;
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: rgba(255, 255, 255, 0.1);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: fixed;
                width: 100%;
                height: auto;
                z-index: 1000;
                padding: 15px;
                height: 70px;
            }
            
            .sidebar-header,
            .user-info {
                display: none;
            }
            
            .nav-menu {
                display: flex;
                justify-content: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .nav-item {
                margin: 0;
            }
            
            .nav-link span {
                font-size: 0.8rem;
            }
            
            .main-content {
                margin-top: 70px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .thoughts-table {
                display: block;
                overflow-x: auto;
            }
            
            .table-actions {
                flex-direction: column;
            }
            
            .nav-link span {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .form-card,
            .table-container {
                padding: 20px;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .nav-menu {
                flex-wrap: wrap;
            }
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            display: flex !important;
            opacity: 1;
        }

        .modal-content {
            background: rgba(20, 20, 20, 0.98);
            border-radius: 15px;
            padding: 40px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid rgba(201, 19, 19, 0.3);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
            transform: translateY(-30px);
            opacity: 0;
            transition: all 0.4s ease;
        }

        .modal.show .modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .modal-header {
            margin-bottom: 25px;
        }

        .modal-title {
            font-family: 'Cinzel', serif;
            font-size: 1.8rem;
            color: var(--text-white);
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            color: #aaa;
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
            padding: 5px;
            z-index: 1;
        }

        .modal-close:hover {
            color: var(--primary-red);
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        /* Preview Section */
        .preview-section {
            margin-top: 40px;
            padding-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .preview-title {
            font-family: 'Cinzel', serif;
            font-size: 1.8rem;
            margin-bottom: 25px;
            color: var(--text-white);
        }

        .preview-card {
            background: rgba(30, 30, 30, 0.5);
            border-radius: 10px;
            padding: 25px;
            border-left: 4px solid var(--primary-red);
        }

        .preview-date {
            color: var(--primary-red);
            font-size: 0.9rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-text {
            line-height: 1.6;
            color: #ddd;
            word-wrap: break-word;
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            border-top-color: var(--primary-red);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Add some missing styles */
        .current-time {
            background: rgba(20, 20, 20, 0.8);
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.9rem;
            color: #aaa;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .current-time i {
            color: var(--primary-red);
            margin-right: 8px;
        }
        
        /* Fix for the logo */
        .logo span {
            color: white;
        }
        
        /* Add style for action buttons container */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        /* Confirmation modal styles */
        .confirmation-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .confirmation-modal.show {
            display: flex !important;
            opacity: 1;
        }
        
        .confirmation-content {
            background: rgba(20, 20, 20, 0.98);
            border-radius: 15px;
            padding: 40px;
            width: 90%;
            max-width: 500px;
            border: 1px solid rgba(201, 19, 19, 0.3);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
            text-align: center;
            transform: translateY(-30px);
            opacity: 0;
            transition: all 0.4s ease;
        }
        
        .confirmation-modal.show .confirmation-content {
            transform: translateY(0);
            opacity: 1;
        }
        
        .confirmation-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }
        
        /* Responsive fixes */
        @media (max-width: 1024px) {
            .main-content {
                margin-top: 70px;
                padding-top: 20px;
            }
        }
        
        /* Make sure the form textarea doesn't overflow */
        .form-textarea {
            max-width: 100%;
            min-height: 150px;
        }
        
        /* Add smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Animation classes */
        @keyframes slideIn {
            from { 
                opacity: 0; 
                transform: translateY(-20px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        @keyframes fadeOut {
            from { 
                opacity: 1; 
                transform: translateY(0); 
            }
            to { 
                opacity: 0; 
                transform: translateY(-10px); 
                max-height: 0;
                margin-bottom: 0;
                padding-top: 0;
                padding-bottom: 0;
                overflow: hidden;
            }
        }
        
        /* Validation styles */
        .validation-error {
            color: #ff6b6b;
            font-size: 0.8rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            animation: slideIn 0.3s ease;
        }
        
        .char-limit-warning {
            color: #ffa726 !important;
        }
        
        .char-limit-danger {
            color: #ff6b6b !important;
        }
        
        /* Character counter */
        .char-counter {
            text-align: right;
            font-size: 0.8rem;
            margin-top: 5px;
            color: #666;
        }
        
        /* Modal backdrop */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9998;
            display: none;
        }
        
        .modal-backdrop.show {
            display: block;
        }
        
        /* Textarea auto-resize */
        .auto-resize {
            transition: height 0.2s ease;
        }
        
        /* Button focus states */
        .btn:focus {
            outline: 2px solid var(--primary-red);
            outline-offset: 2px;
        }
        
        /* Accessibility improvements */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.html" class="logo"> 
                    <i class="fas fa-feather-alt"></i>
                    <span>ECHOTONGUE</span>
                </a>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3 class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Author', ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="user-role">Administrator</p>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#" class="nav-link active">
                        <i class="fas fa-pen"></i>
                        <span>Manage Thoughts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.html" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Back to Site</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="current-time" id="currentTime">
                <i class="far fa-clock"></i> 
                <span><?php echo htmlspecialchars(date('F j, Y, H:i:s'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            
            <div class="dashboard-header">  
                <div>
                    <h1 class="page-title">Author's Thoughts Dashboard</h1>
                    <p class="page-subtitle">Manage your writing journey and share insights with readers</p>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>" id="messageBox">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                <button class="message-close" onclick="closeMessage(this)" aria-label="Close message">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>
            
            <!-- Add New Thought Form -->
            <div class="form-card">
                <h2><i class="fas fa-plus-circle"></i> Add New Thought</h2>
                
                <form method="POST" action="" id="thoughtForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group">
                        <label class="form-label" for="thought_text">Thought Text *</label>
                        <textarea 
                            id="thought_text" 
                            name="thought_text" 
                            class="form-textarea auto-resize"  
                            placeholder="Share your writing insights, inspirations, or reflections..."
                            required
                            maxlength="1000"
                            oninput="updateCharCounter(this, 'charCount'); autoResize(this); clearError('thoughtError');"></textarea> 
                        <div class="char-counter">
                            <span id="charCount">0</span> / 1000 characters
                        </div>
                        <div id="thoughtError" class="validation-error" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i>
                            <span></span>
                        </div>
                    </div>
                    
                    <div class="form-group action-buttons">
                        <button type="submit" name="add_thought" class="btn" onclick="return validateForm()">
                            <i class="fas fa-paper-plane"></i> Publish Thought
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="previewThought()">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-redo"></i> Clear
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Preview Section -->
            <div class="preview-section" id="previewSection" style="display: none;">
                <h2 class="preview-title"><i class="fas fa-eye"></i> Thought Preview</h2>
                <div class="preview-card" id="thoughtPreview">
                    <!-- Preview will be inserted here -->
                </div>
            </div>
            
            <!-- Existing Thoughts Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">Published Thoughts</h2>
                    <span class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i> <?php echo htmlspecialchars(count($thoughts), ENT_QUOTES, 'UTF-8'); ?> Total
                    </span>
                </div>
                
                <?php if (count($thoughts) > 0): ?>
                    <table class="thoughts-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Thought</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($thoughts as $thought): ?>
                                <tr>
                                    <td>
                                        <div class="preview-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo htmlspecialchars(date('M j, Y \a\t g:i A', strtotime($thought['thought_date'])), ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="thought-text">
                                            <?php 
                                                $text = htmlspecialchars($thought['thought_text'], ENT_QUOTES, 'UTF-8');
                                                echo strlen($text) > 200 ? substr($text, 0, 200) . '...' : $text;
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn btn-secondary btn-sm" 
                                                    onclick="editThought(<?php echo (int)$thought['id']; ?>, '<?php echo addslashes(htmlspecialchars($thought['thought_text'], ENT_QUOTES, 'UTF-8')); ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="?delete=<?php echo (int)$thought['id']; ?>&csrf_token=<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirmDelete(event, <?php echo (int)$thought['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-pen-nib"></i>
                        <h3>No Thoughts Published Yet</h3>
                        <p>Start sharing your writing journey by adding your first thought above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()" aria-label="Close modal">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Thought</h2>
            </div>
            <form id="editForm" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="update_thought" value="1">
                
                <div class="form-group">
                    <label class="form-label" for="edit_text">Thought Text *</label>
                    <textarea 
                        id="edit_text" 
                        name="edit_text" 
                        class="form-textarea auto-resize" 
                        required 
                        maxlength="1000"
                        placeholder="Edit your thought..."
                        oninput="updateCharCounter(this, 'editCharCount'); autoResize(this); clearError('editError');"></textarea>
                    <div class="char-counter">
                        <span id="editCharCount">0</span> / 1000 characters
                    </div>
                    <div id="editError" class="validation-error" style="display: none;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span></span>
                    </div>
                </div>
                
                <input type="hidden" id="edit_id" name="edit_id">
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn" onclick="return validateEditForm()">
                        <i class="fas fa-save"></i> Update Thought
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <?php if (isset($confirm_message)): ?>
    <div class="confirmation-modal <?php echo $confirm_message ? 'show' : ''; ?>" id="confirmationModal">
        <div class="confirmation-content">
            <h2 style="color: var(--primary-red); margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
            </h2>
            <p style="color: #ddd; font-size: 1.1rem; margin-bottom: 30px;">
                <?php echo htmlspecialchars($confirm_message, ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <div class="confirmation-actions">
                <a href="?delete=<?php echo (int)$confirm_id; ?>&confirm=yes&csrf_token=<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Yes, Delete
                </a>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
    // Update current time
    function updateTime() {
        const now = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            const timeString = now.toLocaleDateString('en-US', options);
            timeElement.innerHTML = `
                <i class="far fa-clock"></i> ${timeString}
            `;
        }
    }
    
    // Update time every second
    updateTime();
    setInterval(updateTime, 1000);
    
    // Character counter for thought text
    function updateCharCounter(textarea, counterId) {
        const counter = document.getElementById(counterId);
        if (textarea && counter) {
            const length = textarea.value.length;
            counter.textContent = length;
            
            // Add warning color if approaching limit
            if (length > 900) {
                counter.classList.add('char-limit-danger');
                counter.classList.remove('char-limit-warning');
            } else if (length > 800) {
                counter.classList.add('char-limit-warning');
                counter.classList.remove('char-limit-danger');
            } else {
                counter.classList.remove('char-limit-warning', 'char-limit-danger');
            }
        }
    }
    
    // Initialize character counters on page load
    document.addEventListener('DOMContentLoaded', function() {
        const thoughtText = document.getElementById('thought_text');
        const editText = document.getElementById('edit_text');
        
        if (thoughtText) {
            updateCharCounter(thoughtText, 'charCount');
        }
        if (editText) {
            updateCharCounter(editText, 'editCharCount');
        }
        
        // Initialize textarea auto-resize
        document.querySelectorAll('.auto-resize').forEach(textarea => {
            autoResize(textarea);
        });
        
        // Auto-close success messages after 5 seconds
        const successMessages = document.querySelectorAll('.message.success');
        successMessages.forEach(message => {
            setTimeout(() => {
                const closeBtn = message.querySelector('.message-close');
                if (closeBtn) closeMessage(closeBtn);
            }, 5000);
        });
        
        // Auto-close error messages after 8 seconds
        const errorMessages = document.querySelectorAll('.message.error');
        errorMessages.forEach(message => {
            setTimeout(() => {
                const closeBtn = message.querySelector('.message-close');
                if (closeBtn) closeMessage(closeBtn);
            }, 8000);
        });
        
        // Show confirmation modal if needed
        const confirmationModal = document.getElementById('confirmationModal');
        if (confirmationModal) {
            setTimeout(() => {
                confirmationModal.classList.add('show');
            }, 100);
        }
        
        // Add form validation
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!this.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                this.classList.add('was-validated');
            });
        });
    });
    
    // Form validation
    function validateForm() {
        const textElement = document.getElementById('thought_text');
        const text = textElement.value.trim();
        const csrfToken = document.querySelector('input[name="csrf_token"]');
        const errorDiv = document.getElementById('thoughtError');
        
        // Clear previous errors
        clearError('thoughtError');
        
        if (!csrfToken || !csrfToken.value) {
            showError('thoughtError', 'Security token missing. Please refresh the page.');
            return false;
        }
        
        if (!text) {
            showError('thoughtError', 'Please enter your thought.');
            textElement.focus();
            return false;
        }
        
        if (text.length > 1000) {
            showError('thoughtError', 'Thought must be 1000 characters or less.');
            textElement.focus();
            return false;
        }
        
        // Check for excessive newlines (potential spam)
        const newlineCount = (text.match(/\n/g) || []).length;
        if (newlineCount > 50) {
            showError('thoughtError', 'Too many line breaks. Please format your thought properly.');
            textElement.focus();
            return false;
        }
        
        return true;
    }
    
    // Validate edit form
    function validateEditForm() {
        const textElement = document.getElementById('edit_text');
        const text = textElement.value.trim();
        const errorDiv = document.getElementById('editError');
        
        clearError('editError');
        
        if (!text) {
            showError('editError', 'Please enter your thought.');
            textElement.focus();
            return false;
        }
        
        if (text.length > 1000) {
            showError('editError', 'Thought must be 1000 characters or less.');
            textElement.focus();
            return false;
        }
        
        return true;
    }
    
    // Show error message
    function showError(elementId, message) {
        const errorDiv = document.getElementById(elementId);
        if (errorDiv) {
            errorDiv.querySelector('span').textContent = message;
            errorDiv.style.display = 'flex';
            errorDiv.style.animation = 'slideIn 0.3s ease';
        }
    }
    
    // Clear error message
    function clearError(elementId) {
        const errorDiv = document.getElementById(elementId);
        if (errorDiv) {
            errorDiv.style.display = 'none';
        }
    }
    
    // Reset form
    function resetForm() {
        const thoughtText = document.getElementById('thought_text');
        if (thoughtText) {
            updateCharCounter(thoughtText, 'charCount');
        }
        clearError('thoughtError');
        document.getElementById('previewSection').style.display = 'none';
    }
    
    // Preview thought with XSS protection
    function previewThought() {
        const textElement = document.getElementById('thought_text');
        const text = textElement.value.trim();
        const previewSection = document.getElementById('previewSection');
        const previewCard = document.getElementById('thoughtPreview');
        
        // Clear previous errors
        clearError('thoughtError');
        
        if (!text) {
            showError('thoughtError', 'Please enter a thought to preview.');
            textElement.focus();
            return;
        }
        
        if (text.length > 1000) {
            showError('thoughtError', 'Thought must be 1000 characters or less.');
            textElement.focus();
            return;
        }
        
        // Sanitize text for display
        const sanitizedText = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/\n/g, '<br>');
        
        previewCard.innerHTML = `
            <div class="preview-date">
                <i class="far fa-calendar"></i> ${new Date().toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                })}
            </div>
            <div class="preview-text">${sanitizedText}</div>
        `;
        
        previewSection.style.display = 'block';
        previewSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    // Edit thought modal
    function editThought(id, text) {
        // Decode HTML entities
        const decodedText = decodeHtmlEntities(text);
        
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_text').value = decodedText;
        updateCharCounter(document.getElementById('edit_text'), 'editCharCount');
        
        // Show the modal
        const modal = document.getElementById('editModal');
        modal.classList.add('show');
        
        // Animate modal content
        setTimeout(() => {
            modal.style.opacity = '1';
        }, 10);
        
        clearError('editError');
        
        // Focus on textarea and resize
        setTimeout(() => {
            const editTextarea = document.getElementById('edit_text');
            if (editTextarea) {
                editTextarea.focus();
                autoResize(editTextarea);
                // Set cursor at end
                editTextarea.selectionStart = editTextarea.selectionEnd = editTextarea.value.length;
            }
        }, 100);
    }
    
    // Helper function to decode HTML entities
    function decodeHtmlEntities(text) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }
    
    // Confirm deletion
    function confirmDelete(event, id) {
        event.preventDefault();
        event.stopPropagation();
        
        if (confirm('Are you sure you want to delete this thought?\nThis action cannot be undone.')) {
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
            if (csrfToken) {
                window.location.href = `?delete=${id}&confirm=yes&csrf_token=${encodeURIComponent(csrfToken)}`;
            } else {
                // Fallback if csrf token not found
                if (confirm('Security token not found. Refresh page and try again.')) {
                    window.location.reload();
                }
            }
        }
        return false;
    }
    
    // Close edit modal
    function closeModal() {
        const modal = document.getElementById('editModal');
        modal.classList.remove('show');
        
        // Reset form after animation
        setTimeout(() => {
            document.getElementById('editForm').reset();
            const editCharCount = document.getElementById('editCharCount');
            if (editCharCount) {
                editCharCount.textContent = '0';
                editCharCount.classList.remove('char-limit-warning', 'char-limit-danger');
            }
            clearError('editError');
        }, 300);
    }
    
    // Close confirmation modal
    function closeConfirmationModal() {
        const modal = document.getElementById('confirmationModal');
        if (modal) {
            modal.classList.remove('show');
        }
        // Don't redirect, just close
    }
    
    // Close message
    function closeMessage(button) {
        const message = button.closest('.message');
        if (message) {
            message.style.opacity = '0';
            message.style.transform = 'translateY(-20px)';
            message.style.marginBottom = '0';
            message.style.paddingTop = '0';
            message.style.paddingBottom = '0';
            message.style.height = '0';
            message.style.overflow = 'hidden';
            message.style.transition = 'all 0.3s ease';
            
            setTimeout(() => {
                message.remove();
            }, 300);
        }
    }
    
    // Auto-resize textarea
    function autoResize(textarea) {
        if (!textarea) return;
        
        // Reset height
        textarea.style.height = 'auto';
        
        // Calculate new height (min 150px, max 400px)
        const minHeight = 150;
        const maxHeight = 400;
        const scrollHeight = textarea.scrollHeight;
        
        if (scrollHeight > maxHeight) {
            textarea.style.height = maxHeight + 'px';
            textarea.style.overflowY = 'auto';
        } else if (scrollHeight < minHeight) {
            textarea.style.height = minHeight + 'px';
            textarea.style.overflowY = 'hidden';
        } else {
            textarea.style.height = scrollHeight + 'px';
            textarea.style.overflowY = 'hidden';
        }
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + Enter to submit main form
        if (e.ctrlKey && e.key === 'Enter' && !document.getElementById('editModal').classList.contains('show')) {
            if (document.getElementById('thoughtForm')) {
                document.getElementById('thoughtForm').submit();
            }
        }
        
        // Escape to close modal and messages
        if (e.key === 'Escape') {
            closeModal();
            closeConfirmationModal();
        }
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const editModal = document.getElementById('editModal');
        const confirmationModal = document.getElementById('confirmationModal');
        
        if (event.target === editModal && editModal.classList.contains('show')) {
            closeModal();
        }
        if (event.target === confirmationModal && confirmationModal.classList.contains('show')) {
            closeConfirmationModal();
        }
    });
    
    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    // Store message state in sessionStorage to prevent showing again if page is refreshed
    window.addEventListener('beforeunload', function() {
        const messages = document.querySelectorAll('.message');
        messages.forEach(message => {
            const messageText = message.querySelector('span')?.textContent;
            if (messageText) {
                sessionStorage.setItem('lastMessage', messageText);
                sessionStorage.setItem('lastMessageTime', Date.now());
            }
        });
    });
    
    // Check if same message was shown recently
    window.addEventListener('load', function() {
        const lastMessage = sessionStorage.getItem('lastMessage');
        const lastMessageTime = sessionStorage.getItem('lastMessageTime');
        const currentTime = Date.now();
        
        if (lastMessage && lastMessageTime && (currentTime - lastMessageTime) < 3000) {
            // Same message was shown within 3 seconds, hide it faster
            const currentMessage = document.querySelector('.message span');
            if (currentMessage && currentMessage.textContent === lastMessage) {
                const message = currentMessage.closest('.message');
                setTimeout(() => {
                    const closeBtn = message.querySelector('.message-close');
                    if (closeBtn) closeMessage(closeBtn);
                }, 1000);
            }
        }
        
        // Clear stored message after checking
        sessionStorage.removeItem('lastMessage');
        sessionStorage.removeItem('lastMessageTime');
    });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>
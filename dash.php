<?php 
/**
 * SECURITY BEST PRACTICE: Set session cookie parameters before session_start()
 * This prevents JavaScript from accessing the session ID (HttpOnly) and 
 * ensures cookies are only sent over HTTPS (Secure).
 */
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '', 
    'secure' => true,     // Set to false only if developing on localhost without SSL
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start(); 
// Regenerate ID to prevent Session Fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// --- SECURITY HEADERS ---
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin'); 

// --- RATE LIMITING ---
if (!isset($_SESSION['last_request'])) {
    $_SESSION['last_request'] = time();
    $_SESSION['request_count'] = 0;
}

$current_time = time();
if ($current_time - $_SESSION['last_request'] > 60) {
    $_SESSION['request_count'] = 0;
    $_SESSION['last_request'] = $current_time;
}

$_SESSION['request_count']++;
if ($_SESSION['request_count'] > 30) {
    header('HTTP/1.1 429 Too Many Requests');
    die('Rate limit exceeded. Please wait a minute.');
}

// --- CSRF TOKEN GENERATION ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// --- DATABASE CONNECTION ---
// $host = 'localhost';
// $username = 'dsintevr_echotongue';
// $password = 'aEZ6gWB2EQDgjsZehKGN';
// $database = 'dsintevr_echotongue';
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'echotongue';

// Use mysqli reporting for cleaner try/catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("A technical error occurred. Please try again later.");
}

// Sanitize input - PHP function
function sanitizeInput($text) {
    if (!is_string($text)) {
        // Log error for debugging (don't output to user)
        error_log('Invalid input type in sanitizeInput');
        return '';
    }

    // Remove null characters
    $text = str_replace("\0", '', $text);
    
    // Trim whitespace
    $text = trim($text);
    
    // Remove HTML tags and encode special characters
    $text = strip_tags($text);
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Additional security: prevent excessive whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    
    return $text;
}


// Time elapsed helper function
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// --- TAB MANAGEMENT ---
// Determine active tab from GET parameter or default to 'thoughts'
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['thoughts', 'feedbacks', 'newsletter']) 
    ? $_GET['tab'] 
    : 'thoughts';
// --- FORM HANDLING ---
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security token mismatch.");
    }

    // 2. Process Actions for thoughts tab
    if (isset($_POST['add_thought'])) {
        $thought_text = sanitizeInput($_POST['thought_text'] ?? '');
        if ($thought_text) {
            $stmt = $conn->prepare("INSERT INTO authors_thoughts (thought_date, thought_text) VALUES (NOW(), ?)");
            $stmt->bind_param("s", $thought_text);
            $stmt->execute();
            $_SESSION['success_message'] = "Thought shared.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=thoughts");
            exit;
        }
    }

    if (isset($_POST['update_thought'])) {
        $id = filter_input(INPUT_POST, 'edit_id', FILTER_VALIDATE_INT);
        $thought_text = sanitizeInput($_POST['edit_text'] ?? '');
        if ($id && $thought_text) {
            $stmt = $conn->prepare("UPDATE authors_thoughts SET thought_text = ?, thought_date = NOW() WHERE id = ?");
            $stmt->bind_param("si", $thought_text, $id);
            $stmt->execute();
            $_SESSION['success_message'] = "Thought updated.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=thoughts");
            exit;
        }
    }

    if (isset($_POST['delete_id'])) {
        $id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM authors_thoughts WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $_SESSION['success_message'] = "Thought removed.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=thoughts");
            exit;
        }
    }
    
    // 3. Process Actions for feedbacks tab
    if (isset($_POST['mark_read'])) {
        $id = filter_input(INPUT_POST, 'mark_read', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $conn->prepare("UPDATE feedbacks SET status = 'read' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $_SESSION['success_message'] = "Feedback marked as read.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=feedbacks");
            exit;
        }
    }
    
    if (isset($_POST['delete_feedback'])) {
        $id = filter_input(INPUT_POST, 'delete_feedback_id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM feedbacks WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $_SESSION['success_message'] = "Feedback deleted.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=feedbacks");
            exit;
        }
    }
    // 4. Process Actions for newsletter tab
     
if (isset($_POST['delete_subscriber'])) {
    $id = filter_input(INPUT_POST, 'delete_subscriber_id', FILTER_VALIDATE_INT);
    if ($id) {
        try {
            $stmt = $conn->prepare("DELETE FROM newsletter WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $_SESSION['success_message'] = "Subscriber removed successfully.";
            } else {
                $_SESSION['error_message'] = "Subscriber not found or already deleted.";
            }
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=newsletter");
            exit;
        } catch (Exception $e) {
            error_log("Delete failed: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to delete subscriber. Please try again.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=newsletter");
            exit;
        }
    }
}
 
if (isset($_POST['export_newsletter'])) {
    // Check if there are subscribers before exporting
    $checkResult = $conn->query("SELECT COUNT(*) as total FROM newsletter");
    $checkRow = $checkResult->fetch_assoc();
    
    if ($checkRow['total'] == 0) {
        $_SESSION['error_message'] = "No subscribers to export.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=newsletter");
        exit;
    }
    
    // Export newsletter subscribers to CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=newsletter_subscribers_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, array('ID', 'Email', 'Date Subscribed'));
    
    $result = $conn->query("SELECT id, email, date_subscribed FROM newsletter ORDER BY date_subscribed DESC");
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

}

// Fetch Success Messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = "success";
    unset($_SESSION['success_message']);
}

// --- FETCH DATA BASED ON ACTIVE TAB ---
$thoughts = [];
$feedbacks = [];
$newsletter_subscribers = [];


if ($active_tab === 'thoughts') {
    // Fetch thoughts data
    $result = $conn->query("SELECT id, thought_date, thought_text FROM authors_thoughts ORDER BY thought_date DESC");
    while ($row = $result->fetch_assoc()) {
        $thoughts[] = $row;
    }
} elseif ($active_tab === 'feedbacks') {
    // Fetch feedbacks data with rating
    $result = $conn->query("SELECT id, name, email, message, rating, created_at, status FROM feedbacks ORDER BY created_at DESC");
    while ($row = $result->fetch_assoc()) {
        $feedbacks[] = $row;
    }
} elseif ($active_tab === 'newsletter') {
    // Fetch newsletter subscribers data
    $result = $conn->query("SELECT id, email, date_subscribed FROM newsletter ORDER BY date_subscribed DESC");
    while ($row = $result->fetch_assoc()) {
        $newsletter_subscribers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title><?php 
    if ($active_tab === 'thoughts') {
        echo "Author's Thoughts";
    } elseif ($active_tab === 'feedbacks') {
        echo "Feedback Management";
    } elseif ($active_tab === 'newsletter') {
        echo "Newsletter Subscribers";
    }
?> | Dashboard</title>
    <link rel="icon" href="echologo.png" sizes="32x32" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/echologo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Montserrat:wght@300;400;500;600&family=Orbitron:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
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

        .skip-to-content {
            position: absolute;
            top: -40px;
            left: 0;
            background: var(--primary-red);
            color: white;
            padding: 8px;
            z-index: 9999;
            text-decoration: none;
        }

        .skip-to-content:focus {
            top: 0;
        }

        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
       .sidebar {
            background: rgba(8, 8, 8, 0.993); 
            background-image: 
            radial-gradient(white, rgba(255, 255, 255, .1) 2px, transparent 3px),
            radial-gradient(white, rgba(255, 255, 255, .15) 1px, transparent 2px), 
            radial-gradient(white, rgba(255, 255, 255, .15) 1px, transparent 2px) ;
            background-size: 390px 450px, 350px 350px,  350px 450px, 450px 650px, 250px 250px ;
            background-position: 0 0, 40px 1200px, 230px 370px;
            scroll-behavior: smooth;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px 20px;
            position: sticky;
            top: 0;
            height: 100vh;
            backdrop-filter: blur(10px);
            z-index: 100;
            overflow-y: auto;
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

        .main-content {
             background: rgba(8, 8, 8, 0.952); 
                scroll-behavior: smooth;
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

        .form-input:focus-visible,
        .form-textarea:focus-visible {
            outline: 2px solid var(--primary-red);
            outline-offset: 2px;
        }

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

        .btn:focus-visible {
            outline: 2px solid var(--primary-red);
            outline-offset: 3px;
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

        .table-actions form {
            display: inline;
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

        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .main-content {
                padding: 20px;
                padding-top: 80px;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .form-card,
            .table-container {
                padding: 20px;
            }
            
            .thoughts-table {
                display: block;
                overflow-x: auto;
            }
            
            .thoughts-table thead {
                display: none;
            }
            
            .thoughts-table tbody, 
            .thoughts-table tr, 
            .thoughts-table td {
                display: block;
                width: 100%;
            }
            
            .thoughts-table tr {
                margin-bottom: 20px;
                background: rgba(30, 30, 30, 0.5);
                border-radius: 10px;
                padding: 15px;
            }
            
            .thoughts-table td {
                padding: 10px 0;
                border: none;
                position: relative;
                padding-left: 120px;
            }
            
            .thoughts-table td:before {
                content: attr(data-label);
                position: absolute;
                left: 15px;
                top: 10px;
                font-weight: 600;
                color: var(--primary-red);
                text-transform: uppercase;
                font-size: 0.8rem;
                letter-spacing: 1px;
            }
            
            .table-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

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
            font-weight:bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-text {
            line-height: 1.6;
            color: #ddd;
            word-wrap: break-word;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            border-top-color: var(--primary-red);
            animation: spin 1s ease-in-out infinite;
        }

        .btn-loading {
            position: relative;
            color: transparent !important;
        }

        .btn-loading:after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .current-time {
            background: rgba(20, 20, 20, 0.8);
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.9rem;
            color: #aaa;
            margin-bottom: 20px;
            display: inline-block;
            float:right;
        }
        
        .current-time i {
            color: var(--primary-red);
            margin-right: 8px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
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
        
        html {
            scroll-behavior: smooth;
        }
        
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
        
        .validation-error {
            color: #d41d1dff;
            font-size: 0.8rem;
            font-weight:bold;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            animation: slideIn 0.3s ease;
        }
        
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
        
        .mobile-header {
            display: none;
            background: rgba(20, 18, 18, 0.95);
            backdrop-filter: blur(8px);
            color: #ffffff;
            height: 70px;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 9999;
            padding: 0 20px;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .mobile-logo {
            font-family: 'Cinzel Decorative', serif;
            font-weight: 700;
            font-size: 20px;
            color: white;
            text-decoration: none;
        }
        
        .mobile-menu-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 10px;
        }
        
        .mobile-nav {
            position: fixed;
            top: 70px;
            left: 0;
            width: 100%;
            background: rgba(15, 15, 15, 0.98);
            backdrop-filter: blur(10px);
            z-index: 9998;
            display: none;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .mobile-nav.show {
            display: block;
        }
        
        .mobile-nav .nav-menu {
            flex-direction: column;
        }
        
        .mobile-nav .nav-item {
            margin-bottom: 10px;
        }
        
        .mobile-nav .nav-link {
            padding: 15px;
            border-radius: 8px;
        }
        
        /* Status badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-block;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-read {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .status-archived {
            background: rgba(158, 158, 158, 0.1);
            color: #9e9e9e;
            border: 1px solid rgba(158, 158, 158, 0.3);
        }
        
        /* Tab indicator */
        .tab-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: var(--primary-red);
            border-radius: 50%;
            margin-left: 8px;
            vertical-align: middle;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .mobile-header {
                display: flex;
            }
            
            .current-time {
                display: none;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-header {
                display: none;
            }
        }
         /* 1. Set the height and overflow on the div */
#viewFeedbackModal { 
    overflow-y: auto; 
    scrollbar-color: #5e5d5d var(--light-black); /* thumb and track colors for Firefox */ 
}

/* 2. Chrome, Edge, and Safari styles */
#viewFeedbackModal::-webkit-scrollbar {
    width: 8px; border-radius: 10px;
}

#viewFeedbackModal::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

#viewFeedbackModal::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

#viewFeedbackModal::-webkit-scrollbar-thumb:hover {
    background: #555;
}
@keyframes slideIn {
    from { 
        opacity: 0; 
        transform: translateX(100%); 
    }
    to { 
        opacity: 1; 
        transform: translateX(0); 
    }
}

.subscriber-email {
    font-family: monospace;
    background: rgba(0,0,0,0.2);
    padding: 2px 6px;
    border-radius: 3px;
    border: 1px solid rgba(255,255,255,0.1);
}
/* Add this to your CSS section */
.copy-notification {
    animation: slideInFromRight 0.3s ease;
}

@keyframes slideInFromRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Add this to the existing @keyframes section if it doesn't exist */
@keyframes slideInFromRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <a href="https://echotongue.dsintertravel.com" class="mobile-logo">ECHOTONGUE</a>
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <div class="mobile-nav" id="mobileNav">
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="?tab=thoughts" class="nav-link <?php echo $active_tab === 'thoughts' ? 'active' : ''; ?>" onclick="closeMobileMenu()">
                <i class="fas fa-pen"></i>
                <span>Manage Thoughts</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="?tab=feedbacks" class="nav-link <?php echo $active_tab === 'feedbacks' ? 'active' : ''; ?>" onclick="closeMobileMenu()">
                <i class="fas fa-comment"></i>
                <span>Feedbacks</span>
                <?php if ($active_tab !== 'feedbacks' && count($feedbacks) > 0): ?>
                    <span class="tab-indicator" title="<?php echo count($feedbacks); ?> new feedback"></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
    <a href="?tab=newsletter" class="nav-link <?php echo $active_tab === 'newsletter' ? 'active' : ''; ?>">
        <i class="fas fa-envelope-open-text"></i>
        <span>Newsletter</span>
        <?php if ($active_tab !== 'newsletter' && count($newsletter_subscribers) > 0): ?>
            <span class="tab-indicator" title="<?php echo count($newsletter_subscribers); ?> new subscribers"></span>
        <?php endif; ?>
    </a>
</li>
        <li class="nav-item">
            <a href="https://echotongue.dsintertravel.com" class="nav-link" onclick="closeMobileMenu()">
                <i class="fas fa-home"></i>
                <span>Back to Site</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="logout.php" class="nav-link logout-btn" onclick="closeMobileMenu()">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div> 
    <div class="dashboard-container">
        <!-- Desktop Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="https://echotongue.dsintertravel.com" class="logo">  
                    <span>ECHOTONGUE</span>
                </a>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div> 
                <h3 class="user-name">Hermona</h3>
                <p class="user-role">Administrator</p>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="?tab=thoughts" class="nav-link <?php echo $active_tab === 'thoughts' ? 'active' : ''; ?>">
                        <i class="fas fa-pen"></i>
                        <span>Manage Thoughts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?tab=feedbacks" class="nav-link <?php echo $active_tab === 'feedbacks' ? 'active' : ''; ?>">
                        <i class="fas fa-comment"></i>
                        <span>Feedbacks</span>
                        <?php if ($active_tab !== 'feedbacks' && count($feedbacks) > 0): ?>
                            <span class="tab-indicator" title="<?php echo count($feedbacks); ?> new feedback"></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?tab=newsletter" class="nav-link <?php echo $active_tab === 'newsletter' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope-open-text"></i>
                        <span>Newsletter</span>
                        <?php if ($active_tab !== 'newsletter' && count($newsletter_subscribers) > 0): ?>
                            <span class="tab-indicator" title="<?php echo count($newsletter_subscribers); ?> New subsciber"></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="https://echotongue.dsintertravel.com" class="nav-link">
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
        <main class="main-content" id="main-content" role="main" aria-label="Dashboard content">
            <div class="current-time" id="currentTime" aria-live="polite">
                <i class="far fa-clock"></i> 
                <span><?php echo htmlspecialchars(date('F j, Y, H:i:s'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            
            <div class="dashboard-header">  
                <div>
                    <h1 class="page-title">
                        <?php 
                            echo $active_tab === 'thoughts' 
                                ? "Author's Dashboard" 
                                : "Feedback Management";
                        ?>
                    </h1>
                    <p class="page-subtitle">
    <?php 
        if ($active_tab === 'thoughts') {
            echo "Manage your writing journey and share insights with readers";
        } elseif ($active_tab === 'feedbacks') {
            echo "View and manage user feedback and comments";
        } elseif ($active_tab === 'newsletter') {
            echo "Manage newsletter subscribers and export data";
        }
    ?>
</p>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>" id="messageBox" role="alert">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                <button class="message-close" onclick="closeMessage(this)" aria-label="Close message">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>
            
            <!-- Thoughts Tab Content -->
            <?php if ($active_tab === 'thoughts'): ?>
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
                                oninput="autoResize(this); clearError('thoughtError');"></textarea> 
                          
                            <div id="thoughtError" class="validation-error" style="display: none;">
                                <i class="fas fa-exclamation-circle"></i>
                                <span></span>
                            </div>
                        </div>
                        
                        <div class="form-group action-buttons">
                            <button type="submit" name="add_thought" class="btn" onclick="return validateForm(event)" id="submitBtn">
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
                <div class="table-container" style="margin-top:20px;">
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
                                        <td data-label="Date">
                                            <div class="preview-date">
                                                <i class="far fa-calendar"></i>
                                                <?php echo htmlspecialchars(date('M j, Y \a\t g:i A', strtotime($thought['thought_date'])), ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        </td>
                                        <td data-label="Thought">
                                            <div class="thought-text">
                                                <?php 
                                                    $text = htmlspecialchars($thought['thought_text'], ENT_QUOTES, 'UTF-8');
                                                    echo strlen($text) > 200 ? substr($text, 0, 200) . '...' : $text;
                                                ?>
                                            </div>
                                        </td>
                                        <td data-label="Actions">
                                            <div class="table-actions">
                                                <button class="btn btn-secondary btn-sm" 
                                                    onclick="editThought(<?php echo (int)$thought['id']; ?>)"
                                                    data-thought-text="<?php echo htmlspecialchars($thought['thought_text'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                                <form method="POST" action="" class="delete-form" data-thought-id="<?php echo (int)$thought['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="delete_id" value="<?php echo (int)$thought['id']; ?>">
                                                <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(event, <?php echo (int)$thought['id']; ?>)">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
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
            
            <!-- Feedbacks Tab Content -->
            <?php elseif ($active_tab === 'feedbacks'): ?>
                <div class="table-container">
                    <div class="table-header">
                        <h2 class="table-title"><i class="fas fa-comments"></i> User Feedbacks</h2>
                        <div style="display: flex; gap: 10px;">
                            <span class="btn btn-secondary">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars(count($feedbacks), ENT_QUOTES, 'UTF-8'); ?> Total
                            </span>
                            <?php 
                            $pendingCount = 0;
                            foreach ($feedbacks as $feedback) {
                                if ($feedback['status'] === 'pending') $pendingCount++;
                            }
                            if ($pendingCount > 0): ?>
                            <span class="btn" style="background: rgba(255, 193, 7, 0.2); border: 1px solid rgba(255, 193, 7, 0.3); color: #ffc107;">
                                <i class="fas fa-clock"></i> <?php echo $pendingCount; ?> Pending
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (count($feedbacks) > 0): ?>
                        <table class="thoughts-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Name</th>
                                    <th>Rating</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedbacks as $feedback): ?>
                                    <tr>
                                        <td data-label="Date">
                                            <?php echo htmlspecialchars(date('M j, Y', strtotime($feedback['created_at'])), ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td data-label="Name">
                                            <div style="margin-bottom: 5px;">
                                                <?php echo htmlspecialchars($feedback['name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <small style="color: #aaa; font-size: 0.8rem;">
                                                <?php echo htmlspecialchars($feedback['email'], ENT_QUOTES, 'UTF-8'); ?>
                                            </small>
                                        </td>
                                        <td data-label="Rating">
                                            <div style="color: #ffc107;">
                                                <?php 
                                                $rating = (int)$feedback['rating'];
                                                for ($i = 1; $i <= 5; $i++): 
                                                    echo $i <= $rating ? '★' : '☆';
                                                endfor; 
                                                ?>
                                                <span style="color: #ddd; margin-left: 5px;">(<?php echo $rating; ?>/5)</span>
                                            </div>
                                        </td>
                                        <td data-label="Message">
                                            <div class="thought-text">
                                                <?php 
                                                    $text = htmlspecialchars($feedback['message'], ENT_QUOTES, 'UTF-8');
                                                    echo strlen($text) > 100 ? substr($text, 0, 100) . '...' : $text;
                                                ?>
                                            </div>
                                        </td>
                                        <td data-label="Status">
                                            <span class="status-badge status-<?php echo htmlspecialchars($feedback['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars(ucfirst($feedback['status']), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <td data-label="Actions">
                                            <div class="table-actions">
                                                <button class="btn btn-secondary btn-sm" onclick="viewFeedback(<?php echo (int)$feedback['id']; ?>, '<?php echo htmlspecialchars(addslashes($feedback['name']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars(addslashes($feedback['email']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars(addslashes($feedback['message']), ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int)$feedback['rating']; ?>, '<?php echo htmlspecialchars($feedback['created_at'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($feedback['status'], ENT_QUOTES, 'UTF-8'); ?>')">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if ($feedback['status'] === 'pending'): ?>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="mark_read" value="<?php echo (int)$feedback['id']; ?>">
                                                        <button type="submit" class="btn btn-sm" style="background: rgba(76, 175, 80, 0.2); border: 1px solid rgba(76, 175, 80, 0.3); color: #4CAF50;">
                                                            <i class="fas fa-check"></i> Mark Read
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-comment-slash"></i>
                            <h3>No Feedbacks Yet</h3>
                            <p>User feedback will appear here once submitted.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <!-- Newsletter Tab Content -->

            <?php if ($active_tab === 'newsletter'): ?>
    <div class="table-container">
        <div class="table-header">
            <h2 class="table-title"><i class="fas fa-envelope-open-text"></i> Newsletter Subscribers</h2> 
               <div style="display: flex; gap: 10px; align-items: center;">
                    <span class="btn btn-secondary">
                        <i class="fas fa-users"></i> <?php echo htmlspecialchars(count($newsletter_subscribers), ENT_QUOTES, 'UTF-8'); ?> Total
                    </span>
                     <?php if (count($newsletter_subscribers) > 0): ?>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="export_newsletter" value="1">
                               <button type="submit" class="btn" 
                                    aria-label="Export newsletter subscribers to CSV file"
                                    title="Download CSV file with all subscribers">
                                <i class="fas fa-download"></i> Export CSV (<?php echo count($newsletter_subscribers); ?>)
                            </button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn" style="background: rgba(158, 158, 158, 0.2); border: 1px solid rgba(158, 158, 158, 0.3); color: #9e9e9e; cursor: not-allowed;" disabled>
                                <i class="fas fa-download"></i> Export CSV (0)
                            </button>
                        <?php endif; ?>
                </div>
          
        </div>
        
        <?php if (count($newsletter_subscribers) > 0): ?>
            <table class="thoughts-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email Address</th>
                        <th>Date Subscribed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($newsletter_subscribers as $subscriber): ?>
                        <tr>
                            <td data-label="ID">
                                <?php echo htmlspecialchars($subscriber['id'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td data-label="Email">
                                <div style="margin-bottom: 5px;">
                                    <?php echo htmlspecialchars($subscriber['email'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </td>
                            <td data-label="Date Subscribed">
                                <div style="color: #ddd;">
                                    <?php echo htmlspecialchars(date('M j, Y \a\t g:i A', strtotime($subscriber['date_subscribed'])), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <small style="color: #aaa; font-size: 0.8rem;">
                                    <?php echo htmlspecialchars(time_elapsed_string($subscriber['date_subscribed']), ENT_QUOTES, 'UTF-8'); ?> ago
                                </small>
                            </td>
                            <td data-label="Actions">
                                <div class="table-actions">
                                    <!-- For copy button -->
                        <button class="btn btn-secondary btn-sm" 
                                onclick="copyEmail('<?php echo htmlspecialchars(addslashes($subscriber['email']), ENT_QUOTES, 'UTF-8'); ?>')"
                                aria-label="Copy email address to clipboard"
                                title="Copy email to clipboard">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                                    <!-- Replace the delete form in newsletter section (around line 1280) with: -->
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="delete_subscriber_id" value="<?php echo (int)$subscriber['id']; ?>">
                                    <button type="submit" name="delete_subscriber" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to remove this subscriber?');">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Statistics Section -->
            <div style="margin-top: 30px; padding: 20px; background: rgba(30, 30, 30, 0.5); border-radius: 10px;">
                <h3 style="color: var(--primary-red); margin-bottom: 15px;">
                    <i class="fas fa-chart-bar"></i> Newsletter Statistics
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div style="text-align: center; padding: 15px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                        <div style="font-size: 2rem; color: var(--primary-red); font-weight: bold;">
                            <?php echo count($newsletter_subscribers); ?>
                        </div>
                        <div style="color: #aaa;">Total Subscribers</div>
                    </div>
                    <?php 
                    // Calculate subscribers by month
                    if (count($newsletter_subscribers) > 0) {
                        $currentMonth = date('Y-m');
                        $lastMonth = date('Y-m', strtotime('-1 month'));
                        $currentMonthCount = 0;
                        $lastMonthCount = 0;
                        
                        foreach ($newsletter_subscribers as $subscriber) {
                            $subMonth = date('Y-m', strtotime($subscriber['date_subscribed']));
                            if ($subMonth === $currentMonth) $currentMonthCount++;
                            if ($subMonth === $lastMonth) $lastMonthCount++;
                        }
                        
                        // This month's subscribers
                        echo '
                        <div style="text-align: center; padding: 15px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                            <div style="font-size: 2rem; color: #4CAF50; font-weight: bold;">
                                ' . $currentMonthCount . '
                            </div>
                            <div style="color: #aaa;">This Month</div>
                        </div>';
                        
                        // Last month's subscribers
                        echo '
                        <div style="text-align: center; padding: 15px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                            <div style="font-size: 2rem; color: #ff9800; font-weight: bold;">
                                ' . $lastMonthCount . '
                            </div>
                            <div style="color: #aaa;">Last Month</div>
                        </div>';
                        
                        // Today's subscribers
                        $today = date('Y-m-d');
                        $todayCount = 0;
                        foreach ($newsletter_subscribers as $subscriber) {
                            if (date('Y-m-d', strtotime($subscriber['date_subscribed'])) === $today) {
                                $todayCount++;
                            }
                        }
                        echo '
                        <div style="text-align: center; padding: 15px; background: rgba(0,0,0,0.3); border-radius: 8px;">
                            <div style="font-size: 2rem; color: #2196F3; font-weight: bold;">
                                ' . $todayCount . '
                            </div>
                            <div style="color: #aaa;">Today</div>
                        </div>';
                    }
                    ?>
                </div>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-envelope-open-text"></i>
                <h3>No Newsletter Subscribers Yet</h3>
                <p>Subscribers will appear here once they sign up through the website.</p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

  
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
                        placeholder="Edit your thought..."
                        oninput="autoResize(this); clearError('editError');"></textarea>
                   
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
                    <button type="submit" class="btn" onclick="return validateEditForm(event)">
                        <i class="fas fa-save"></i> Update Thought
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Feedback Modal -->
    <div class="modal" id="viewFeedbackModal" style="border-radius: 0px;">
        <div class="modal-content">
            <button class="modal-close" onclick="closeViewFeedbackModal()" aria-label="Close modal">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-comment"></i> Feedback Details</h2>
            </div>
            <div id="feedbackContent">
                <!-- Feedback content will be loaded here -->
            </div>
        </div>
        // Inside the viewFeedback function, in the modal-actions section, add:
<form method="POST" action="" style="display: inline;">
    <input type="hidden" name="csrf_token" value="${csrfToken}">
    <input type="hidden" name="delete_feedback_id" value="${id}">
    <button type="submit" name="delete_feedback" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this feedback?')">
        <i class="fas fa-trash"></i> Delete
    </button>
</form>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-content">
            <h2 style="color: var(--primary-red); margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
            </h2>
            <p style="color: #ddd; font-size: 1.1rem; margin-bottom: 30px;">
                Are you sure you want to delete this item? This action cannot be undone.
            </p>
            <div class="confirmation-actions">
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeConfirmationModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>
    <!-- Newsletter Subscriber Delete Confirmation Modal -->
<div class="confirmation-modal" id="newsletterConfirmationModal">
    <div class="confirmation-content">
        <h2 style="color: var(--primary-red); margin-bottom: 20px;">
            <i class="fas fa-exclamation-triangle"></i> Remove Subscriber
        </h2>
        <p style="color: #ddd; font-size: 1.1rem; margin-bottom: 30px;">
            Are you sure you want to remove this subscriber from the newsletter? This action cannot be undone.
        </p>
        <div class="confirmation-actions">
            <button type="button" class="btn btn-danger" id="confirmNewsletterDeleteBtn">
                <i class="fas fa-trash"></i> Yes, Remove
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeNewsletterConfirmationModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>
</div>
    
    <!-- Feedback Delete Confirmation Modal -->
    <div class="confirmation-modal" id="feedbackConfirmationModal">
        <div class="confirmation-content">
            <h2 style="color: var(--primary-red); margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> Delete Feedback
            </h2>
            <p style="color: #ddd; font-size: 1.1rem; margin-bottom: 30px;">
                Are you sure you want to delete this feedback? This action cannot be undone.
            </p>
            <div class="confirmation-actions"> 
                <button type="button" class="btn btn-secondary" onclick="closeFeedbackConfirmationModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // Global variables
    let deleteFormToSubmit = null;
    let feedbackDeleteFormToSubmit = null;
    
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
    
    // Mobile menu functions
  function toggleMobileMenu() {
    const mobileNav = document.getElementById('mobileNav');
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const icon = menuBtn.querySelector('i');
    
    mobileNav.classList.toggle('show');
    menuBtn.classList.toggle('active');
    
    // Toggle icon
    if (mobileNav.classList.contains('show')) {
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-times');
        document.body.style.overflow = 'hidden';
    } else {
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
        document.body.style.overflow = '';
    }
}

function closeMobileMenu() {
    const mobileNav = document.getElementById('mobileNav');
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const icon = menuBtn.querySelector('i');
    
    mobileNav.classList.remove('show');
    menuBtn.classList.remove('active');
    icon.classList.remove('fa-times');
    icon.classList.add('fa-bars');
    document.body.style.overflow = '';
}
   
    
    // Form validation
    function validateForm(event) {
        const textElement = document.getElementById('thought_text');
        let text = textElement.value.trim();
        const errorDiv = document.getElementById('thoughtError');
        const submitBtn = document.getElementById('submitBtn');
        
        // Clear previous errors
        clearError('thoughtError');
        
        // Basic validation
        if (!text) {
            showError('thoughtError', 'Please enter your thought.');
            textElement.focus();
            return false;
        }
         
        // Sanitize input
        text = sanitizeInput(text);
        textElement.value = text;
        
        return true;
    }
    
    // Validate edit form
    function validateEditForm(event) {
        const textElement = document.getElementById('edit_text');
        let text = textElement.value;
        
        // Sanitize input
        text = sanitizeInput(text);
        textElement.value = text;
        
        clearError('editError');
        
        if (!text) {
            showError('editError', 'Please enter your thought.');
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
            
            // Scroll to error
            setTimeout(() => {
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
        }
    }
    
    // Clear error message
    function clearError(elementId) {
        const errorDiv = document.getElementById(elementId);
        if (errorDiv) {
            errorDiv.style.display = 'none';
        }
    }
    
    // Client-side sanitization
    function sanitizeInput(text) {
        if (typeof text !== 'string') return '';
        
        // Remove HTML tags
        text = text.replace(/<[^>]*>/g, '');
        
        // Encode special characters
        const div = document.createElement('div');
        div.textContent = text;
        text = div.innerHTML;
        
        // Trim and clean whitespace
        text = text.trim();
        text = text.replace(/\s+/g, ' ');
        
        return text;
    }

    function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
    }


    // Reset form
    function resetForm() {
        const thoughtText = document.getElementById('thought_text'); 
        clearError('thoughtError');
        document.getElementById('previewSection').style.display = 'none';
    }
    
    function previewThought() {
        try {
            const textElement = document.getElementById('thought_text');
            const previewSection = document.getElementById('previewSection');
            const previewCard = document.getElementById('thoughtPreview');
            
            // Check if all required elements exist
            if (!textElement || !previewSection || !previewCard) {
                console.error('Required elements not found');
                return;
            }
            
            let text = textElement.value.trim();
            
            // Clear previous errors
            const errorElement = document.getElementById('thoughtError');
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.style.display = 'none';
            }
            
            if (!text) {
                if (errorElement) {
                    errorElement.textContent = 'Please enter a thought to preview.';
                    errorElement.style.display = 'block';
                }
                textElement.focus();
                return;
            }
            
            // Just use one sanitization method
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
            
        } catch (error) {
            console.error('Error in previewThought:', error);
        }
    }
    
    // Edit thought modal
    function editThought(id) {
        // Get the thought text from the data attribute
        const button = event.currentTarget;
        const text = button.getAttribute('data-thought-text');
        
        if (!text) {
            console.error('Could not retrieve thought text');
            return;
        }
        
        document.getElementById('edit_id').value = id;
        const editTextarea = document.getElementById('edit_text');
        
        // Decode HTML entities and set the value
        const decodedText = text
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#039;/g, "'")
            .replace(/&nbsp;/g, ' ');
        
        editTextarea.value = decodedText; 
        
        // Show the modal
        const modal = document.getElementById('editModal');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        clearError('editError');
        
        // Focus and resize
        setTimeout(() => {
            autoResize(editTextarea);
            editTextarea.focus();
            editTextarea.selectionStart = editTextarea.selectionEnd = editTextarea.value.length;
        }, 100);
    }
    
    // View feedback function
   // View feedback function (update it in your dashboard JavaScript)
function viewFeedback(id, name, email, message, rating, createdAt, status) {
    const modal = document.getElementById('viewFeedbackModal');
    const content = document.getElementById('feedbackContent');
    
    // Format the date
    const date = new Date(createdAt);
    const formattedDate = date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    // Get status class
    const statusClass = `status-${status}`;
    const statusText = status.charAt(0).toUpperCase() + status.slice(1);
    
    // Create star rating display
    let starsHtml = '';
    for (let i = 1; i <= 5; i++) {
        starsHtml += i <= rating ? '★' : '☆';
    }
    
    content.innerHTML = `
        <div style="margin-bottom: 20px; ">
            <div style="background: rgba(30, 30, 30, 0.5); padding: 25px; border-radius: 10px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="color: var(--text-white); margin: 0;">Feedback Details</h3>
                    <span class="status-badge ${statusClass}">${statusText}</span>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <strong style="color: var(--primary-red); display: block; margin-bottom: 5px;">From:</strong>
                    <div style="color: #ddd; font-size: 1.1rem;">${sanitizeInput(name)}</div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <strong style="color: var(--primary-red); display: block; margin-bottom: 5px;">Email:</strong>
                    <div style="color: #ddd; font-size: 1.1rem;">${sanitizeInput(email)}</div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <strong style="color: var(--primary-red); display: block; margin-bottom: 5px;">Rating:</strong>
                    <div style="color: #ffc107; font-size: 1.2rem;">
                        ${starsHtml}
                        <span style="color: #ddd; margin-left: 10px;">${rating}/5</span>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <strong style="color: var(--primary-red); display: block; margin-bottom: 5px;">Date:</strong>
                    <div style="color: #ddd; font-size: 1.1rem;">${formattedDate}</div>
                </div>
                
                <div>
                    <strong style="color: var(--primary-red); display: block; margin-bottom: 5px;">Message:</strong>
                    <div style="color: #ddd; line-height: 1.6; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 5px; border-left: 3px solid var(--primary-red);">
                        ${sanitizeInput(message).replace(/\n/g, '<br>')}
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeViewFeedbackModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                ${status === 'pending' ? `
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="mark_read" value="${id}">
                    <button type="submit" class="btn" style="background: rgba(76, 175, 80, 0.2); border: 1px solid rgba(76, 175, 80, 0.3); color: #4CAF50;">
                        <i class="fas fa-check"></i> Mark as Read
                    </button>
                </form>
                ` : ''}
                 
            </div>
        </div>
    `;
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}
    
    function closeViewFeedbackModal() {
        const modal = document.getElementById('viewFeedbackModal');
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
    
    // Confirm deletion (for thoughts)
    function confirmDelete(event, id) {
        event.preventDefault();
        event.stopPropagation();
        
        // Store the form to submit
        deleteFormToSubmit = event.target.closest('.delete-form');
        
        // Update confirmation message
        const message = document.querySelector('#confirmationModal .confirmation-content p');
        if (message) {
            message.textContent = 'Are you sure you want to delete this thought? This action cannot be undone.';
        }
        
        // Show confirmation modal
        const modal = document.getElementById('confirmationModal');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        return false;
    }
    
    
    // Close edit modal
    function closeModal() {
        const modal = document.getElementById('editModal');
        modal.classList.remove('show');
        document.body.style.overflow = '';
        
        // Reset form after animation
        setTimeout(() => {
            document.getElementById('editForm').reset();
            clearError('editError');
        }, 300);
    }
    
    // Close confirmation modal
    function closeConfirmationModal() {
        const modal = document.getElementById('confirmationModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
        deleteFormToSubmit = null;
    }
    
    // Close feedback confirmation modal
    function closeFeedbackConfirmationModal() {
        const modal = document.getElementById('feedbackConfirmationModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
        feedbackDeleteFormToSubmit = null;
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
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
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
        
        // Setup delete confirmation for thoughts
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function() {
                if (deleteFormToSubmit) {
                    deleteFormToSubmit.submit();
                }
            });
        }
        
        
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter to submit main form (only in thoughts tab)
            if (e.ctrlKey && e.key === 'Enter' && !document.getElementById('editModal').classList.contains('show')) {
                const thoughtForm = document.getElementById('thoughtForm');
                if (thoughtForm) {
                    const submitBtn = thoughtForm.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.click();
                    }
                }
            }
            
            // Escape to close modal and messages
            if (e.key === 'Escape') {
                closeModal();
                closeConfirmationModal();
                closeFeedbackConfirmationModal();
                closeViewFeedbackModal();
                closeMobileMenu();
            }
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const editModal = document.getElementById('editModal');
            const confirmationModal = document.getElementById('confirmationModal');
            const feedbackConfirmationModal = document.getElementById('feedbackConfirmationModal');
            const viewFeedbackModal = document.getElementById('viewFeedbackModal');
            const mobileNav = document.getElementById('mobileNav');
            
            if (event.target === editModal && editModal.classList.contains('show')) {
                closeModal();
            }
            if (event.target === confirmationModal && confirmationModal.classList.contains('show')) {
                closeConfirmationModal();
            }
            if (event.target === feedbackConfirmationModal && feedbackConfirmationModal.classList.contains('show')) {
                closeFeedbackConfirmationModal();
            }
            if (event.target === viewFeedbackModal && viewFeedbackModal.classList.contains('show')) {
                closeViewFeedbackModal();
            }
            if (mobileNav.classList.contains('show') && !event.target.closest('.mobile-nav') && !event.target.closest('.mobile-menu-btn')) {
                closeMobileMenu();
            }
        });
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Highlight active tab in URL
        const activeTab = "<?php echo $active_tab; ?>";
        if (activeTab === 'feedbacks') {
            // Add subtle animation to feedback table
            const feedbackTable = document.querySelector('.table-container');
            if (feedbackTable) {
                feedbackTable.style.animation = 'slideIn 0.5s ease';
            }
        }
    });
      

// Newsletter functionality
let newsletterDeleteFormToSubmit = null;
               function copyEmail(email) {
    // Decode HTML entities properly
    const textarea = document.createElement('textarea');
    textarea.innerHTML = email;
    const cleanEmail = textarea.value
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'")
        .replace(/&nbsp;/g, ' ');
    
    // Try modern Clipboard API first
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(cleanEmail).then(() => {
            showCopyNotification('Email copied to clipboard!');
        }).catch(err => {
            // Fallback for older browsers
            useFallbackCopy(cleanEmail);
        });
    } else {
        // Use fallback
        useFallbackCopy(cleanEmail);
    }
}

function showCopyNotification(message) {
    // Remove existing notification if any
    const existing = document.querySelector('.copy-notification');
    if (existing) existing.remove();
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = 'copy-notification';
    notification.innerHTML = `
        <i class="fas fa-check-circle"></i> ${message}
    `;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: rgba(30, 30, 30, 0.95);
        color: #4CAF50;
        padding: 15px 20px;
        border-radius: 8px;
        border-left: 4px solid #4CAF50;
        z-index: 99999;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        animation: slideInFromRight 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function useFallbackCopy(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    textArea.style.opacity = '0';
    document.body.appendChild(textArea);
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopyNotification('Email copied to clipboard!');
        } else {
            showCopyNotification('Failed to copy. Please copy manually.');
        }
    } catch (err) {
        showCopyNotification('Copy failed. Please copy manually: ' + text);
    } finally {
        document.body.removeChild(textArea);
    }
}

function showNotification(message, type = 'success') {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? 'rgba(30, 30, 30, 0.95)' : 'rgba(30, 30, 30, 0.95)'};
        color: ${type === 'success' ? '#4CAF50' : '#ff6b6b'};
        padding: 15px 20px;
        border-radius: 8px;
        border-left: 4px solid ${type === 'success' ? '#4CAF50' : '#ff6b6b'};
        z-index: 99999;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease;
        max-width: 300px;
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add to newsletter delete buttons
function confirmSubscriberDelete(event, id) {
    event.preventDefault();
    const form = event.target.closest('form');
    const button = event.target;
    
    if (confirm('Are you sure you want to remove this subscriber?')) {
        // Add loading state
        button.classList.add('loading');
        button.disabled = true;
        
        // Submit after short delay for visual feedback
        setTimeout(() => {
            form.submit();
        }, 300);
    }
    return false;
}
function closeNewsletterConfirmationModal() {
    const modal = document.getElementById('newsletterConfirmationModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
    newsletterDeleteFormToSubmit = null;
}

// Add this to your DOMContentLoaded event listener
document.addEventListener('DOMContentLoaded', function() {
    // ... existing code ...
    
    // Setup newsletter delete confirmation
    const confirmNewsletterDeleteBtn = document.getElementById('confirmNewsletterDeleteBtn');
    if (confirmNewsletterDeleteBtn) {
        confirmNewsletterDeleteBtn.addEventListener('click', function() {
            if (newsletterDeleteFormToSubmit) {
                newsletterDeleteFormToSubmit.submit();
            }
        });
    }
    
    // Update keyboard shortcuts to include newsletter modal
    document.addEventListener('keydown', function(e) {
        // ... existing code ...
        
        if (e.key === 'Escape') {
            closeModal();
            closeConfirmationModal();
            closeFeedbackConfirmationModal();
            closeNewsletterConfirmationModal(); // Add this
            closeViewFeedbackModal();
            closeMobileMenu();
        }
    });
    
    // Update click outside handler to include newsletter modal
    window.addEventListener('click', function(event) {
        // ... existing code ...
        
        const newsletterConfirmationModal = document.getElementById('newsletterConfirmationModal');
        
        if (event.target === newsletterConfirmationModal && newsletterConfirmationModal.classList.contains('show')) {
            closeNewsletterConfirmationModal();
        }
    });
});

function confirmNewsletterDelete(id) {
    const deleteForm = document.getElementById('deleteForm' + id);
    if (!deleteForm) return;
    
    if (confirm('Are you sure you want to remove this subscriber from the newsletter?')) {
        deleteForm.submit();
    }
}
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>
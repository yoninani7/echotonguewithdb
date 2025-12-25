<?php
session_start();

// Check if user is logged in (uncomment for production)
// if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
//     header('Location: login.php');
//     exit;
// }

// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'echotongue';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new thought
    if (isset($_POST['add_thought'])) {
        $thought_text = trim($_POST['thought_text'] ?? '');
        
        if (!empty($thought_text)) {
            $stmt = $conn->prepare("INSERT INTO authors_thoughts (thought_date, thought_text) VALUES (NOW(), ?)");
            $stmt->bind_param("s", $thought_text);
            
            if ($stmt->execute()) {
                $message = "Thought added successfully!";
                $message_type = "success";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            } else {
                $message = "Error adding thought: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        } else {
            $message = "Please enter a thought!";
            $message_type = "error";
        }
    }
    
    // Update thought
    if (isset($_POST['update_thought'])) {
        $id = intval($_POST['edit_id'] ?? 0);
        $thought_text = trim($_POST['edit_text'] ?? '');
        
        if ($id > 0 && !empty($thought_text)) {
            $stmt = $conn->prepare("UPDATE authors_thoughts SET thought_text = ?, thought_date = NOW() WHERE id = ?");
            $stmt->bind_param("si", $thought_text, $id);
            
            if ($stmt->execute()) {
                $message = "Thought updated successfully!";
                $message_type = "success";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            } else {
                $message = "Error updating thought: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

// Delete thought
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Confirm deletion
    if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
        $stmt = $conn->prepare("DELETE FROM authors_thoughts WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "Thought deleted successfully!";
            $message_type = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
        } else {
            $message = "Error deleting thought: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    } else {
        // Show confirmation dialog
        $confirm_message = "Are you sure you want to delete this thought?";
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = isset($_GET['action']) ? 
        ucfirst($_GET['action']) . " completed successfully!" : 
        "Operation completed successfully!";
    $message_type = "success";
}

// Fetch all thoughts for display
$thoughts = [];
$result = $conn->query("SELECT * FROM authors_thoughts ORDER BY thought_date DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $thoughts[] = $row;
    }
    $result->free();
}
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
            animation: fadeIn 0.5s ease;
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

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form Card */
        .form-card {
            background: rgba(20, 20, 20, 0.8);
            border-radius: 15px;
            padding-top: 30px;
            padding-left: 30px;
            padding-right: 30px; 
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
            resize: none;
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
            }
            
            .sidebar-header,
            .user-info {
                display: none;
            }
            
            .nav-menu {
                display: flex;
                justify-content: center;
                gap: 10px;
            }
            
            .nav-item {
                margin: 0;
            }
            
            .nav-link span {
                display: none;
            }
            
            .main-content {
                margin-top: 80px;
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
            background: rgba(0, 0, 0, 0.8);
            z-index: 1001;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: rgba(20, 20, 20, 0.95);
            border-radius: 15px;
            padding: 40px;
            width: 90%;
            max-width: 500px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
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
            background: rgba(0, 0, 0, 0.8);
            z-index: 1002;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }
        
        .confirmation-content {
            background: rgba(20, 20, 20, 0.95);
            border-radius: 15px;
            padding: 40px;
            width: 90%;
            max-width: 500px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
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
                margin-top: 100px;
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.html" class="logo"> 
                    <span>ECHOTONGUE</span>
                </a>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3 class="user-name">Author</h3>
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
                <span><?php echo date('F j, Y, H:i:s'); ?></span>
            </div>
            
            <div class="dashboard-header">  
                <div>
                    <h1 class="page-title">Author's Thoughts Dashboard</h1>
                    <p class="page-subtitle">Manage your writing journey and share insights with readers</p>
                </div>
            </div>
            
            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>" id="messageBox">
                    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Add New Thought Form -->
            <div class="form-card">
                <h2><i class="fas fa-plus-circle"></i> Add New Thought</h2>
                
                <form method="POST" action="" id="thoughtForm" onsubmit="return validateForm()">
                    <div class="form-group">
                        <label class="form-label" for="thought_text">Thought Text *</label>
                        <textarea 
                            id="thought_text" 
                            name="thought_text" 
                            class="form-textarea"  
                            placeholder="Share your writing insights, inspirations, or reflections..."
                            required ></textarea> 
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="add_thought" class="btn">
                            <i class="fas fa-paper-plane"></i> Publish Thought
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="previewThought()" style="margin-left: 10px;">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Thoughts Preview -->
            <div class="preview-section" id="previewSection" style="display: none;">
                <h3 class="preview-title">Preview</h3>
                <div class="preview-card" id="thoughtPreview">
                    <!-- Preview will be inserted here -->
                </div>
            </div>
            
            <!-- Existing Thoughts Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">Published Thoughts</h2>
                    <span class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i> <?php echo count($thoughts); ?> Total
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
                                            <?php echo date('M j, Y \a\t g:i A', strtotime($thought['thought_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="thought-text">
                                            <?php 
                                                $text = htmlspecialchars($thought['thought_text']);
                                                echo strlen($text) > 200 ? substr($text, 0, 200) . '...' : $text;
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn btn-secondary btn-sm" onclick="editThought(<?php echo $thought['id']; ?>, '<?php echo addslashes($thought['thought_text']); ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="?delete=<?php echo $thought['id']; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirmDelete(event, <?php echo $thought['id']; ?>)">
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
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title">Edit Thought</h2>
            </div>
            <form id="editForm" method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="edit_text">Thought Text *</label>
                    <textarea id="edit_text" name="edit_text" class="form-textarea" required></textarea>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        Maximum 1000 characters. Current: <span id="editCharCount">0</span>
                    </small>
                </div>
                <input type="hidden" id="edit_id" name="edit_id">
                <input type="hidden" name="update_thought" value="1">
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn">Update Thought</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <?php if (isset($confirm_message)): ?>
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-content">
            <h2 style="color: var(--primary-red); margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
            </h2>
            <p style="color: #ddd; font-size: 1.1rem; margin-bottom: 30px;">
                <?php echo htmlspecialchars($confirm_message); ?>
            </p>
            <div class="confirmation-actions">
                <a href="?delete=<?php echo $id; ?>&confirm=yes" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Yes, Delete
                </a>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
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
            document.getElementById('currentTime').innerHTML = `
                <i class="far fa-clock"></i> ${now.toLocaleDateString('en-US', options)}
            `;
        }
        
        // Update time every second
        updateTime();
        setInterval(updateTime, 1000);
        
        // Character counter for thought text
        const thoughtText = document.getElementById('thought_text');
        const charCount = document.getElementById('charCount');
        const editText = document.getElementById('edit_text');
        const editCharCount = document.getElementById('editCharCount');
        
        if (thoughtText) {
            thoughtText.addEventListener('input', function() {
                charCount.textContent = this.value.length;
            });
            // Initialize count
            charCount.textContent = thoughtText.value.length;
        }
        
        if (editText) {
            editText.addEventListener('input', function() {
                editCharCount.textContent = this.value.length;
            });
        }
        
        // Form validation
        function validateForm() {
            const text = document.getElementById('thought_text').value.trim();
            
            if (!text) {
                alert('Please enter your thought.');
                return false;
            }
            
            if (text.length > 1000) {
                alert('Thought text cannot exceed 1000 characters.');
                return false;
            }
            
            return true;
        }
        
        // Preview thought
        function previewThought() {
            const text = document.getElementById('thought_text').value.trim();
            const previewSection = document.getElementById('previewSection');
            const previewCard = document.getElementById('thoughtPreview');
            
            if (!text) {
                alert('Please enter a thought to preview.');
                return;
            }
            
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
                <div class="preview-text">${text.replace(/\n/g, '<br>')}</div>
            `;
            
            previewSection.style.display = 'block';
            previewSection.scrollIntoView({ behavior: 'smooth' });
        }
        
        // Edit thought modal
        function editThought(id, text) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_text').value = text.replace(/\\'/g, "'").replace(/\\"/g, '"');
            editCharCount.textContent = text.length;
            document.getElementById('editModal').style.display = 'flex';
            
            // Focus on textarea
            setTimeout(() => {
                document.getElementById('edit_text').focus();
            }, 100);
        }
        
        // Confirm deletion
        function confirmDelete(event, id) {
            event.preventDefault();
            if (confirm('Are you sure you want to delete this thought?\nThis action cannot be undone.')) {
                window.location.href = `?delete=${id}&confirm=yes`;
            }
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
            // Reset form if needed
            document.getElementById('editForm').reset();
        }
        
        // Close confirmation modal
        function closeConfirmationModal() {
            document.getElementById('confirmationModal').style.display = 'none';
            window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>';
        }
        
        // Auto-resize textarea
        function autoResize(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
        }
        
        // Initialize textarea auto-resize
        document.querySelectorAll('.form-textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                autoResize(this);
            });
            // Initial resize
            autoResize(textarea);
        });
        
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.style.display = 'none';
                    }
                }, 500);
            });
        }, 5000);
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            const confirmationModal = document.getElementById('confirmationModal');
            
            if (event.target === modal) {
                closeModal();
            }
            if (event.target === confirmationModal) {
                closeConfirmationModal();
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter to submit main form
            if (e.ctrlKey && e.key === 'Enter' && !document.getElementById('editModal').style.display) {
                if (document.getElementById('thoughtForm')) {
                    document.getElementById('thoughtForm').submit();
                }
            }
            
            // Escape to close modal
            if (e.key === 'Escape') {
                closeModal();
                closeConfirmationModal();
            }
        });
        
        // Check if there's a hash in URL (for scrolling)
        window.addEventListener('load', function() {
            if (window.location.hash) {
                const element = document.querySelector(window.location.hash);
                if (element) {
                    element.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>
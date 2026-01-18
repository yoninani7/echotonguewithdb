<?php
// --- 1. SESSION SETTINGS (Must match index.php) ---
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 2. THE LOCK (Check if user is logged in) ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // If not logged in, send them back to the login page
    header("Location: index.php");
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();
define('ITEMS_PER_PAGE', 10);
include 'db.php';

// --- 3. HELPER FUNCTIONS ---
function sanitize($text)
{
    return htmlspecialchars(trim($text ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function generate_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// --- 4. FORM HANDLING (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))
        die("Invalid CSRF Token");

    $action = $_POST['action'] ?? '';
    $tab_redirect = $_POST['redirect_tab'] ?? 'thoughts';

    // Export CSV
    if ($action === 'export_csv') {
        $check = $conn->query("SELECT COUNT(*) FROM newsletter")->fetch_row()[0];

        if ($check > 0) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=subscribers_' . date('Y-m-d') . '.csv');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Email', 'Date Subscribed']);
            $res = $conn->query("SELECT id, email, date_subscribed FROM newsletter ORDER BY date_subscribed DESC");
            while ($row = $res->fetch_assoc())
                fputcsv($out, $row);
            fclose($out);
            exit;
        }
    }

    // CRUD Operations
    elseif ($action === 'add_thought') {
        if (!empty(trim($_POST['thought_text']))) {
            $stmt = $conn->prepare("INSERT INTO authors_thoughts (thought_date, thought_text) VALUES (NOW(), ?)");
            $stmt->bind_param("s", $_POST['thought_text']);
            $stmt->execute();
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Thought published successfully!'];
        }
    } elseif ($action === 'update_thought') {
        $stmt = $conn->prepare("UPDATE authors_thoughts SET thought_text = ?, thought_date = NOW() WHERE id = ?");
        $stmt->bind_param("si", $_POST['edit_text'], $_POST['id']);
        $stmt->execute();
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Thought updated!'];
    } elseif ($action === 'delete_item') {
        $table = ($_POST['type'] === 'thought') ? 'authors_thoughts' : (($_POST['type'] === 'feedback') ? 'feedbacks' : 'newsletter');
        $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param("i", $_POST['id']);
        $stmt->execute();
        $_SESSION['toast'] = ['type' => 'success', 'msg' => ucfirst($_POST['type']) . ' deleted.'];
    } elseif ($action === 'mark_read') {
        $conn->query("UPDATE feedbacks SET status = 'read' WHERE id = " . (int) $_POST['id']);
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Marked as read.'];
    } elseif ($action === 'mark_all_read') {
        $conn->query("UPDATE feedbacks SET status = 'read'");
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'All feedback marked as read.'];
    } elseif ($action === 'delete_all_feedback') {
        $conn->query("DELETE FROM feedbacks");
        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'All feedback records deleted.'];
    }


    header("Location: ?tab=$tab_redirect");
    exit;
}

// --- 5. DATA FETCHING & PAGINATION ---
$active_tab = $_GET['tab'] ?? 'thoughts';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Stats
$stats = [
    'thoughts' => $conn->query("SELECT COUNT(*) as c FROM authors_thoughts")->fetch_assoc()['c'],
    'feedback' => $conn->query("SELECT COUNT(*) as c FROM feedbacks")->fetch_assoc()['c'],
    'subs' => $conn->query("SELECT COUNT(*) as c FROM newsletter")->fetch_assoc()['c'],
    'avg_rating' => $conn->query("SELECT AVG(rating) as a FROM feedbacks")->fetch_assoc()['a'] ?? 0,
    'unread' => $conn->query("SELECT COUNT(*) as c FROM feedbacks WHERE status = 'pending'")->fetch_assoc()['c']
];

// Fetch Data
$data = [];
$total_rows = 0;
if ($active_tab === 'thoughts') {
    $sql_count = "SELECT COUNT(*) as c FROM authors_thoughts";
    $sql_data = "SELECT * FROM authors_thoughts ORDER BY thought_date DESC LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset";
} elseif ($active_tab === 'feedbacks') {
    $sql_count = "SELECT COUNT(*) as c FROM feedbacks";
    $sql_data = "SELECT * FROM feedbacks ORDER BY created_at DESC LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset";
} elseif ($active_tab === 'newsletter') {
    $sql_count = "SELECT COUNT(*) as c FROM newsletter";
    $sql_data = "SELECT * FROM newsletter ORDER BY date_subscribed DESC LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset";
}

if (isset($sql_count)) {
    $total_rows = $conn->query($sql_count)->fetch_assoc()['c'];
    $res = $conn->query($sql_data);
    while ($row = $res->fetch_assoc())
        $data[] = $row;
    $total_pages = ceil($total_rows / ITEMS_PER_PAGE);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EchoTongue Dashboard</title>
    <link rel="icon" href="echologo.png" sizes="32x32" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/echologo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&family=Space+Grotesk:wght@400;700&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700&display=swap" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Montserrat:wght@300;400;500;600&family=Orbitron:wght@400;500;600&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg-body: #050505;
            --bg-card: rgba(20, 20, 20, 0.6);
            --bg-sidebar: #0a0a0a;
            --primary: #c91313;
            --primary-glow: rgba(201, 19, 19, 0.35);
            --text-main: #ffffff;
            --text-muted: #888888;
            --border: 1px solid rgba(255, 255, 255, 0.08);
            --glass: blur(12px);
            --radius: 16px;
        }

        /* Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            outline: none;
        }

        body {
            background: var(--bg-body);
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #000;
        }

        ::-webkit-scrollbar-thumb {
            background: #333;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a5010c;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--bg-sidebar);
            border-right: var(--border);
            display: flex;
            flex-direction: column;
            padding: 25px;
            position: fixed;
            height: 100vh;
            z-index: 100;
            transition: 0.3s;
        }

        .brand {
            font-family: 'Cinzel Decorative', sans-serif;
            font-size: 26px;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            margin: 0 auto;
            margin-bottom: 40px;
        }

        .brand span {
            color: var(--primary);
            text-shadow: 0 0 15px var(--primary-glow);
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }

        .nav-item {
            padding: 14px 18px;
            border-radius: 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: 0.2s;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .nav-item.active {
            border-left: 3px solid var(--primary);
            background: linear-gradient(90deg, rgba(201, 19, 19, 0.1), transparent);
        }

        /* Main Content */
        .main {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
            width: calc(100% - 280px);
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
        }

        /* Cards & Glass */
        .glass-card {
            background: var(--bg-card);
            backdrop-filter: var(--glass);
            border: var(--border);
            border-radius: var(--radius);
            padding: 25px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), transparent);
            opacity: 0.5;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #0f0f0f;
            border: var(--border);
            padding: 20px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(201, 19, 19, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .stat-info p {
            color: var(--text-muted);
            font-size: 13px;
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th {
            text-align: left;
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px;
            border-bottom: var(--border);
        }

        td {
            padding: 18px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            vertical-align: middle;
            color: #eee;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        /* Truncate CSS for Table Cells */
        .text-ellipsis {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            /* Limits to 1 line */
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            word-break: break-word;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-pending {
            background: rgba(255, 193, 7, 0.15);
            color: #ffca28;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .badge-read {
            background: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        /* Buttons */
        .btn {
            padding: 12px 25px;
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

        .btn-primary {
            background: radial-gradient(100% 120% at 50% 0%, #df0211 0%, #500D11 100%);
            color: white;
        }

        .btn-primary:hover {
            background: radial-gradient(100% 120% at 50% 0%, rgba(255, 12, 29, 0.95) 0%, #500D11 100%);
            transform: translateY(-1px);
        }

        /* Disabled Button Style */
        .btn:disabled,
        .btn-disabled {
            background: #333;
            color: #777;
            cursor: not-allowed;
            box-shadow: none;
            opacity: 0.6;
            pointer-events: none;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            color: #aaa;
        }

        .btn-icon:hover {
            background: #fff;
            color: #000;
        }

        /* Textarea */
        .thought-input {
            width: 100%;
            background: #0a0a0a;
            border: var(--border);
            color: #fff;
            padding: 15px;
            border-radius: 12px;
            font-family: inherit;
            font-size: 15px;
            resize: vertical;
            min-height: 120px;
            margin-bottom: 15px;
            transition: 0.3s;
        }

        .thought-input:focus {
            border-color: var(--primary);
        }

        /* Empty State */
        .empty-state {
            padding: 40px;
            text-align: center;
            color: var(--text-muted);
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            margin: 15px;
        }

        .empty-state i {
            font-size: 30px;
            margin-bottom: 15px;
            display: block;
            opacity: 0.5;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .page-link {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            color: #fff;
            text-decoration: none;
        }

        .page-link.active {
            background: var(--primary);
        }

        /* Toast */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #151515;
            border-left: 4px solid var(--primary);
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            transform: translateX(120%);
            transition: 0.3s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .toast.show {
            transform: translateX(0);
        }

        /* Mobile */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: #fff;
            font-size: 24px;
        }

        @media(max-width: 900px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
                box-shadow: 10px 0 30px rgba(0, 0, 0, 0.5);
            }

            .main {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }

            .mobile-toggle {
                display: block;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            td:nth-child(3),
            th:nth-child(3) {
                display: none;
            }
        }

        @media(max-width: 600px) {
            .auth {
                display: none;
            }

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
    </style>
</head>

<body>

    <!-- TOAST -->
    <div id="toast" class="toast">
        <i class="fas fa-check-circle" style="color: var(--primary);"></i>
        <span id="toast-msg">Action Successful</span>
    </div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <a style="margin-top:5px;" class="brand"> ECHOTONGUE</span></a>
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h3 class="user-name">Hermona</h3>
            <p class="user-role">Administrator</p>
        </div>
        <div class="nav-links">
            <a href="?tab=thoughts" class="nav-item <?php echo $active_tab === 'thoughts' ? 'active' : ''; ?>">
                <i class="fas fa-pen-nib"></i> Thoughts
            </a>
            <a href="?tab=feedbacks" class="nav-item <?php echo $active_tab === 'feedbacks' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> Feedbacks
                <?php if ($stats['unread'] > 0): ?>
                    <span
                        style="background:var(--primary); font-size:10px; padding:2px 6px; border-radius:10px; margin-left:auto"><?php echo $stats['unread']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=newsletter" class="nav-item <?php echo $active_tab === 'newsletter' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Newsletter
            </a>
            <a href="https://echotongue.dsintertravel.com/" target="_blank" class="nav-item">
                <i class="fa fa-external-link" aria-hidden="true"></i>Visit site
            </a>
        </div>

        <div style="margin-top: auto; border-top: var(--border); padding-top: 20px;">
            <a href="logout.php" class="nav-item" style="color: #d10000;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">

        <!-- TOP BAR -->
        <div class="top-bar">
            <div style="display:flex; align-items:center; gap:15px;">
                <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')">
                    <i class="fas fa-bars"></i>
                </button>
                <h2 style="font-weight:700; font-family: 'Cinzel Decorative', sans-serif">
                    <?php echo ucfirst($active_tab); ?>
                </h2>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-pen"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['thoughts']; ?></h3>
                    <p>Total Thoughts</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['subs']; ?></h3>
                    <p>Subscribers</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['avg_rating'], 1); ?></h3>
                    <p>Avg Rating</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['unread']; ?></h3>
                    <p>Unread Feedback</p>
                </div>
            </div>
        </div>

        <!-- === THOUGHTS TAB === -->
        <?php if ($active_tab === 'thoughts'): ?>
            <div class="glass-card">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                    <input type="hidden" name="action" value="add_thought">
                    <input type="hidden" name="redirect_tab" value="thoughts">
                    <h3 style="margin-bottom:15px">Share a new thought</h3>
                    <textarea name="thought_text" id="newThoughtText" class="thought-input"
                        placeholder="What is on your mind today?" required></textarea>

                    <!-- Improved Buttons -->
                    <div style="display:flex; gap:12px; justify-content:flex-end;">
                        <button type="button" class="btn btn-secondary"
                            onclick="document.getElementById('newThoughtText').value = ''">
                            <i class="fas fa-eraser"></i> Clear
                        </button>
                        <button type="submit" class="btn btn-primary" style="padding-left:30px; padding-right:30px;">
                            <i class="fas fa-paper-plane"></i> Publish thought
                        </button>
                    </div>
                </form>
            </div>

            <div class="glass-card" style="padding:0">
                <?php if (empty($data)): ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <i class="fas fa-wind"></i>
                        <p>No thoughts added yet. Start writing above!</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Published Date</th>
                                    <th>Content</th>
                                    <th style="text-align:right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <!-- Full Date Format -->
                                        <td style="white-space:nowrap; color:var(--text-muted); font-size:13px;">
                                            <?php echo date('M j, Y, g:i a', strtotime($row['thought_date'])); ?>
                                        </td>
                                        <td><?php echo nl2br(substr(sanitize($row['thought_text']), 0, 100)) . (strlen($row['thought_text']) > 100 ? '...' : ''); ?>
                                        </td>
                                        <td style="text-align:right">
                                            <button class="btn btn-icon"
                                                onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($row)); ?>)"><i
                                                    class="fas fa-edit"></i></button>
                                            <button class="btn btn-icon"
                                                onclick="openDeleteModal(<?php echo $row['id']; ?>, 'thought')"><i
                                                    class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- === FEEDBACKS TAB === -->
        <?php if ($active_tab === 'feedbacks'): ?>
            <!-- NEW: Bulk Action Buttons -->
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-bottom:20px;">
                <?php if ($stats['feedback'] > 0): ?>
                    <!-- Mark All Read Form -->
                    <form method="POST" id="markAllReadFormBulk">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                        <input type="hidden" name="action" value="mark_all_read">
                        <input type="hidden" name="redirect_tab" value="feedbacks">
                        <button type="button" class="btn btn-secondary" onclick="openMarkReadModal()">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                    </form>

                    <!-- Delete All Button (Triggers Modal) -->
                    <button type="button" class="btn btn-primary" onclick="openBulkDeleteModal()">
                        <i class="fas fa-dumpster"></i> Delete All
                    </button>
                <?php endif; ?>
            </div>
            <div class="glass-card" style="padding:0">
                <?php if (empty($data)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i>
                        <p>No feedback received yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:10%">Status</th>
                                    <th style="width:25%">User</th>
                                    <th style="width:10%">Rating</th>
                                    <!-- Increased Width for Message -->
                                    <th style="width:45%">Message</th>
                                    <th style="width:10%; text-align:right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><span
                                                class="badge badge-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span>
                                        </td>
                                        <td>
                                            <div style="font-weight:600"><?php echo sanitize($row['name']); ?></div>
                                            <div style="font-size:12px; color:var(--text-muted)">
                                                <?php echo sanitize($row['email']); ?>
                                            </div>
                                        </td>
                                        <td style="color:#ffc107"><?php echo str_repeat('★', $row['rating']); ?></td>
                                        <!-- UPDATED: Removed PHP truncation and added CSS ellipsis -->
                                        <td>
                                            <div class="text-ellipsis"><?php echo sanitize($row['message']); ?></div>
                                        </td>
                                        <td style="text-align:right">
                                            <button class="btn btn-icon"
                                                onclick="openModal('view', <?php echo htmlspecialchars(json_encode($row)); ?>)"><i
                                                    class="fas fa-eye"></i></button>
                                            <button class="btn btn-icon"
                                                onclick="openDeleteModal(<?php echo $row['id']; ?>, 'feedback')"><i
                                                    class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- === NEWSLETTER TAB === -->
        <?php if ($active_tab === 'newsletter'): ?>
            <div style="display:flex; justify-content:flex-end; margin-bottom:20px;">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                    <input type="hidden" name="action" value="export_csv">

                    <!-- Disabled Button Logic -->
                    <?php if ($stats['subs'] > 0): ?>
                        <button class="btn btn-primary"><i class="fas fa-download"></i> Export CSV</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-disabled" disabled title="No subscribers to export"><i
                                class="fas fa-download"></i> Export CSV</button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="glass-card" style="padding:0">
                <?php if (empty($data)): ?>
                    <div class="empty-state"><i class="fas fa-users-slash"></i>
                        <p>No subscribers yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Email</th>
                                    <th>Subscribed</th>
                                    <th style="text-align:right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td style="color:var(--text-muted)">#<?php echo $row['id']; ?></td>
                                        <td style="font-family:monospace;"><?php echo sanitize($row['email']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($row['date_subscribed'])); ?></td>
                                        <td style="text-align:right">
                                            <button class="btn btn-icon"
                                                onclick="copyText('<?php echo sanitize($row['email']); ?>')"><i
                                                    class="fas fa-copy"></i></button>
                                            <button class="btn btn-icon"
                                                onclick="openDeleteModal(<?php echo $row['id']; ?>, 'subscriber')"><i
                                                    class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?tab=<?php echo $active_tab; ?>&page=<?php echo $i; ?>"
                        class="page-link <?php echo $page === $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- === MODALS === -->

    <!-- 1. Edit Thought Modal -->
    <div id="editModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(5px); z-index:1000; align-items:center; justify-content:center;">
        <div class="glass-card" style="width:90%; max-width:500px; background:#111;">
            <h3 style="margin-bottom:20px">Edit Thought</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                <input type="hidden" name="action" value="update_thought">
                <input type="hidden" name="redirect_tab" value="thoughts">
                <input type="hidden" name="id" id="edit_id">
                <textarea name="edit_text" id="edit_text" class="thought-input"></textarea>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:15px">
                    <button type="button" class="btn" style="background:#222; color:#fff"
                        onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. View Feedback Modal -->
    <div id="viewModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(5px); z-index:1000; align-items:center; justify-content:center;">
        <div class="glass-card" style="width:90%; max-width:600px; background:#111;">
            <h3 style="margin-bottom:20px">Feedback Details</h3>
            <div id="viewContent" style="color:#ccc; line-height:1.6; margin-bottom:20px"></div>

            <form method="POST" id="markReadForm" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="redirect_tab" value="feedbacks">
                <input type="hidden" name="id" id="view_id">
                <button type="submit" class="btn btn-primary">Mark Read</button>
            </form>
            <button type="button" class="btn" style="background:#222; color:#fff; float:right"
                onclick="document.getElementById('viewModal').style.display='none'">Close</button>
        </div>
    </div>

    <!-- 3. NEW CUSTOM DELETE MODAL -->
    <div id="deleteModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); backdrop-filter:blur(5px); z-index:2000; align-items:center; justify-content:center;">
        <div class="glass-card"
            style="width:90%; max-width:400px; background:#151515; text-align:center; border: 1px solid rgba(138, 9, 9, 0.2);">
            <div style="font-size:40px; color: #c90202; margin-bottom:15px;"><i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 style="margin-bottom:10px; color:#fff;">Are you sure?</h3>
            <p style="color:#aaa; margin-bottom:25px;">This action cannot be undone. Do you really want to delete this?
            </p>

            <div style="display:flex; justify-content:center; gap:15px;">
                <button class="btn btn-secondary"
                    onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
                <button class="btn btn-primary" onclick="confirmDeleteAction()">Yes, Delete</button>
            </div>
        </div>
    </div>
    <!-- 4. Mark All Read Confirmation Modal -->
    <div id="markReadModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); backdrop-filter:blur(5px); z-index:2000; align-items:center; justify-content:center;">
        <div class="glass-card"
            style="width:90%; max-width:400px; background:#151515; text-align:center; border: 1px solid rgba(46, 204, 113, 0.2);">
            <div style="font-size:40px; color: #2ecc71; margin-bottom:15px;"><i class="fas fa-clipboard-check"></i>
            </div>
            <h3 style="margin-bottom:10px; color:#fff;">Mark everything as read?</h3>
            <p style="color:#aaa; margin-bottom:25px;">This will update the status of all pending feedback messages to
                "read".</p>

            <div style="display:flex; justify-content:center; gap:15px;">
                <button class="btn btn-secondary"
                    onclick="document.getElementById('markReadModal').style.display='none'">Cancel</button>
                <button class="btn btn-primary"
                    style="background: linear-gradient(135deg, #2ecc71, #135830); border:none;"
                    onclick="document.getElementById('markAllReadFormBulk').submit()">Yes, Mark All</button>
            </div>
        </div>
    </div>

    <!-- Hidden Delete Form -->
    <form id="deleteForm" method="POST" style="display:none">
        <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
        <input type="hidden" name="action" value="delete_item">
        <input type="hidden" name="redirect_tab" value="<?php echo $active_tab; ?>">
        <input type="hidden" name="id" id="del_id">
        <input type="hidden" name="type" id="del_type">
    </form>

    <script>
        // Toast Logic
        <?php if (isset($_SESSION['toast'])): ?>
            const toast = document.getElementById('toast');
            document.getElementById('toast-msg').innerText = "<?php echo $_SESSION['toast']['msg']; ?>";
            toast.classList.add('show');
            <?php if ($_SESSION['toast']['type'] === 'error'): ?>
                toast.style.borderLeftColor = 'red';
                toast.querySelector('i').style.color = 'red';
                toast.querySelector('i').className = 'fas fa-exclamation-circle';
            <?php endif; ?>
            setTimeout(() => toast.classList.remove('show'), 3000);
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        function copyText(text) {
            navigator.clipboard.writeText(text);
            document.getElementById('toast-msg').innerText = "Copied to clipboard!";
            document.getElementById('toast').classList.add('show');
            setTimeout(() => document.getElementById('toast').classList.remove('show'), 2000);
        }

        function openModal(type, data) {
            if (type === 'edit') {
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_text').value = data.thought_text;
                document.getElementById('editModal').style.display = 'flex';
            } else if (type === 'view') {
                const html = `
                <div style="margin-bottom:15px"><strong style="color:var(--primary)">From:</strong> ${data.name} <span style="color:#666">(${data.email})</span></div>
                <div style="background:rgba(255,255,255,0.05); padding:15px; border-radius:8px; border-left:3px solid var(--primary)">${data.message}</div>
            `;
                document.getElementById('viewContent').innerHTML = html;
                document.getElementById('view_id').value = data.id;
                document.getElementById('viewModal').style.display = 'flex';
                document.getElementById('markReadForm').style.display = data.status === 'read' ? 'none' : 'inline';
            }
        }

        // New Modal Delete Logic
        function openDeleteModal(id, type) {
            document.getElementById('del_id').value = id;
            document.getElementById('del_type').value = type;
            document.getElementById('deleteModal').style.display = 'flex';
        }


        // Close Modals on Outside Click
        window.onclick = function (event) {
            if (event.target.id === 'editModal') document.getElementById('editModal').style.display = 'none';
            if (event.target.id === 'viewModal') document.getElementById('viewModal').style.display = 'none';
            if (event.target.id === 'deleteModal') document.getElementById('deleteModal').style.display = 'none';
            if (event.target.id === 'markReadModal') document.getElementById('markReadModal').style.display = 'none'; // Added this line
        }
        // Add this to your existing script block
        function openBulkDeleteModal() {
            // We reuse your existing delete logic but change the hidden fields
            document.getElementById('del_id').value = 'all'; // Special ID for bulk
            document.getElementById('del_type').value = 'feedback';

            // Update the modal text temporarily for bulk action
            const modalTitle = document.querySelector('#deleteModal h3');
            const modalPara = document.querySelector('#deleteModal p');

            modalTitle.innerText = "Delete ALL Feedback?";
            modalPara.innerText = "This will permanently erase every feedback entry in the database.";

            document.getElementById('deleteModal').style.display = 'flex';
        }

        // Update your confirmDeleteAction to handle the bulk 'action' name
        function confirmDeleteAction() {
            const id = document.getElementById('del_id').value;
            const form = document.getElementById('deleteForm');

            if (id === 'all') {
                form.querySelector('input[name="action"]').value = 'delete_all_feedback';
            }

            form.submit();
        }

        function openMarkReadModal() {
            document.getElementById('markReadModal').style.display = 'flex';
        }
    </script>
</body>

</html>
<?php $conn->close(); ?>
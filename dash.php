<?php
// --- 1. SESSION SETTINGS ---
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

// --- 2. THE LOCK ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

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

    if ($action === 'export_csv') {
        $check = $conn->query("SELECT COUNT(*) FROM newsletter")->fetch_row()[0];
        if ($check > 0) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=subscribers_' . date('Y-m-d') . '.csv');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Email', 'Date Subscribed']);
            $res = $conn->query("SELECT id, email, date_subscribed FROM newsletter ORDER BY date_subscribed DESC");
            while ($row = $res->fetch_assoc()) fputcsv($out, $row);
            fclose($out);
            exit;
        }
    } elseif ($action === 'add_thought') {
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

// --- 5. DATA FETCHING ---
$active_tab = $_GET['tab'] ?? 'thoughts';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * ITEMS_PER_PAGE;

$stats = [
    'thoughts' => $conn->query("SELECT COUNT(*) as c FROM authors_thoughts")->fetch_assoc()['c'],
    'feedback' => $conn->query("SELECT COUNT(*) as c FROM feedbacks")->fetch_assoc()['c'],
    'subs' => $conn->query("SELECT COUNT(*) as c FROM newsletter")->fetch_assoc()['c'],
    'avg_rating' => $conn->query("SELECT AVG(rating) as a FROM feedbacks")->fetch_assoc()['a'] ?? 0,
    'unread' => $conn->query("SELECT COUNT(*) as c FROM feedbacks WHERE status = 'pending'")->fetch_assoc()['c']
];

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&family=Cinzel+Decorative:wght@400;700&family=Cinzel:wght@400;700;900&display=swap" rel="stylesheet">
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
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 85px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; outline: none; }
        body { background: var(--bg-body); color: var(--text-main); font-family: 'Outfit', sans-serif; display: flex; min-height: 100vh; overflow-x: hidden; }

        /* Scrollbars */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #000; }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #a5010c; }

        /* Sidebar Logic */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            border-right: var(--border);
            display: flex;
            flex-direction: column;
            padding: 25px;
            position: fixed;
            height: 100vh;
            z-index: 1001;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), padding 0.3s;
        }

        .sidebar.collapsed { width: var(--sidebar-collapsed-width); padding: 25px 15px; }

        .sidebar-collapse-btn {
            position: absolute; right: -12px; top: 0px; width: 24px; height: 24px;
              border-radius: 50%; color: #fff;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; z-index: 10; font-size: 20px;
        }

        .brand { font-family: 'Cinzel Decorative', sans-serif; font-size: 26px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; text-decoration: none; margin: 0 auto 40px; white-space: nowrap; overflow: hidden; }
        .sidebar.collapsed .brand span { display: none; } 

        .user-profile { text-align: center; margin-bottom: 30px; transition: 0.3s; overflow: hidden; }
        .user-avatar { width: 120px; height: 120px;  border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; transition: 0.3s; }
        .sidebar.collapsed .user-avatar { width: 45px; height: 45px; font-size: 1.2rem; }
        .sidebar.collapsed .user-profile h3, .sidebar.collapsed .user-profile p { display: none; }

        .nav-links { display: flex; flex-direction: column; gap: 8px; flex: 1; overflow-y: auto; overflow-x: hidden; padding-right: 5px; }
        .nav-links::-webkit-scrollbar { width: 3px; }

        .nav-item { padding: 14px 18px; border-radius: 12px; color: var(--text-muted); text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 14px; transition: 0.2s; white-space: nowrap; }
        .nav-item:hover, .nav-item.active { background: rgba(255, 255, 255, 0.05); color: #fff; }
        .nav-item.active { border-left: 3px solid var(--primary); background: linear-gradient(90deg, rgba(201, 19, 19, 0.1), transparent); }
        .sidebar.collapsed .nav-item span { display: none; }
        .sidebar.collapsed .nav-item { justify-content: center; padding: 14px 0; }

        .sidebar-footer { margin-top: auto; border-top: var(--border); padding-top: 20px; flex-shrink: 0; }

        /* Overlay */
        .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 1000; display: none; opacity: 0; transition: 0.3s; }
        .sidebar-overlay.active { display: block; opacity: 1; }

        /* Main Content */
        .main { flex: 1; margin-left: var(--sidebar-width); padding: 30px 40px; width: calc(100% - var(--sidebar-width)); transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .main.full-width { margin-left: var(--sidebar-collapsed-width); width: calc(100% - var(--sidebar-collapsed-width)); }

        /* Cards & Stats */
        .glass-card { background: var(--bg-card); backdrop-filter: var(--glass); border: var(--border); border-radius: var(--radius); padding: 25px; margin-bottom: 25px; position: relative; overflow: hidden; }
        .glass-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 2px; background: linear-gradient(90deg, var(--primary), transparent); opacity: 0.5; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; margin-top: 30px; }
        .stat-card { background: #0f0f0f; border: var(--border); padding: 20px; border-radius: 16px; display: flex; align-items: center; gap: 20px; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); border-color: var(--primary); }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(201, 19, 19, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 20px; }

        /* Table Components */
        .table-container { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { text-align: left; color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; padding: 15px; border-bottom: var(--border); }
        td { padding: 18px 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.03); vertical-align: middle; color: #eee; }
        .text-ellipsis { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; word-break: break-word; }

        /* Buttons & Badges */
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-pending { background: rgba(255, 193, 7, 0.15); color: #ffca28; border: 1px solid rgba(255, 193, 7, 0.2); }
        .badge-read { background: rgba(46, 204, 113, 0.15); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.2); }

        .btn { padding: 12px 25px; color: #fdfdfd; border-radius: 8px; border: none; font-family: 'Cinzel', serif; font-weight: 700; font-size: 14px; letter-spacing: 0.7px; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: radial-gradient(100% 120% at 50% 0%, #df0211 0%, #500D11 100%); color: white; }
        .btn-secondary { background: rgba(255, 255, 255, 0.1); color: #fff; }
        .btn-icon { width: 32px; height: 32px; padding: 0; justify-content: center; border-radius: 50%; background: rgba(255, 255, 255, 0.05); color: #aaa; }
        .btn-icon:hover { background: #fff; color: #000; }

        /* LOCKED BUTTON STYLE */
        .btn:disabled, .btn-disabled {
            background: #222 !important;
            color: #555 !important;
            cursor: not-allowed !important;
            border: 1px solid rgba(255,255,255,0.05);
            opacity: 0.7;
            box-shadow: none !important;
        }

        /* Empty State */
        .empty-state { padding: 40px; text-align: center; color: var(--text-muted); background: rgba(0, 0, 0, 0.2); border-radius: 12px; margin: 15px; }
        .empty-state i { font-size: 30px; margin-bottom: 15px; display: block; opacity: 0.5; }

        .thought-input { width: 100%; background: #0a0a0a; border: var(--border); color: #fff; padding: 15px; border-radius: 12px; min-height: 120px; margin-bottom: 15px; }

        .toast { position: fixed; top: 20px; right: 20px; background: #151515; border-left: 4px solid var(--primary); padding: 15px 25px; border-radius: 8px; transform: translateX(120%); transition: 0.3s; z-index: 2000; display: flex; align-items: center; gap: 15px; }
        .toast.show { transform: translateX(0); }

        .mobile-toggle { display: none; background: none; border: none; color: #fff; font-size: 24px; cursor: pointer; }

        @media(max-width: 900px) {
            .sidebar { transform: translateX(-100%); width: 280px !important; }
            .sidebar.active { transform: translateX(0); }
            .sidebar-collapse-btn { display: none; }
            .main { margin-left: 0 !important; width: 100% !important; padding: 20px; }
            .mobile-toggle { display: block; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>

<body>

    <div class="sidebar-overlay" id="overlay" onclick="toggleMobileSidebar()"></div>

    <div id="toast" class="toast">
        <i class="fas fa-check-circle" style="color: var(--primary);"></i>
        <span id="toast-msg">Action Successful</span>
    </div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-collapse-btn" onclick="toggleSidebarSize()">
            <i class="fa-solid fa-bars"  id="collapse-icon"></i>
        </div>

        <a class="brand"><span>ECHOTONGUE</span></a>
        
        <div class="user-profile"> 
            
        <img class="user-avatar"src="echologo.png" width="30" alt=""> 
            <h3 style="font-family:'Cinzel'; color:#fff;">Hermona</h3>
            <p style="color:var(--primary); font-size:0.8rem; text-transform:uppercase; letter-spacing:2px;">Administrator</p>
        </div>

        <div class="nav-links">
            <a href="?tab=thoughts" class="nav-item <?php echo $active_tab === 'thoughts' ? 'active' : ''; ?>">
                <i class="fas fa-pen-nib"></i> <span>Thoughts</span>
            </a>
            <a href="?tab=feedbacks" class="nav-item <?php echo $active_tab === 'feedbacks' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> <span>Feedbacks</span>
                <?php if ($stats['unread'] > 0): ?>
                    <span style="background:var(--primary); font-size:10px; padding:2px 6px; border-radius:10px; margin-left:auto"><?php echo $stats['unread']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=newsletter" class="nav-item <?php echo $active_tab === 'newsletter' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> <span>Newsletter</span>
            </a>
            <a href="https://echotongue.com" target="_blank" class="nav-item">
                <i class="fa fa-external-link"></i> <span>Visit site</span>
            </a>
        </div>

        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item" style="color: #d10000;"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </aside>

    <main class="main" id="main-content">
        <div class="top-bar">
            <div style="display:flex; align-items:center; gap:15px;">
                <button class="mobile-toggle" onclick="toggleMobileSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h2 style="font-family: 'Cinzel Decorative', sans-serif"><?php echo ucfirst($active_tab); ?></h2>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-pen"></i></div><div class="stat-info"><h3><?php echo $stats['thoughts']; ?></h3><p>Total Thoughts</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?php echo $stats['subs']; ?></h3><p>Subscribers</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-star"></i></div><div class="stat-info"><h3><?php echo number_format($stats['avg_rating'], 1); ?></h3><p>Avg Rating</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-envelope"></i></div><div class="stat-info"><h3><?php echo $stats['unread']; ?></h3><p>Unread Feedback</p></div></div>
        </div>

        <!-- === THOUGHTS TAB === -->
        <?php if ($active_tab === 'thoughts'): ?>
            <div class="glass-card">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                    <input type="hidden" name="action" value="add_thought">
                    <h3 style="margin-bottom:15px">Share a new thought</h3>
                    <textarea name="thought_text" id="newThoughtText" class="thought-input" placeholder="What is on your mind today?" required></textarea>
                    <div style="display:flex; gap:12px; justify-content:flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('newThoughtText').value = ''"><i class="fas fa-eraser"></i> Clear</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publish thoughts</button>
                    </div>
                </form>
            </div>
            <div class="glass-card" style="padding:0">
                <?php if (empty($data)): ?>
                    <div class="empty-state"><i class="fas fa-wind"></i><p>No thoughts added yet.</p></div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead><tr><th class="hide-mobile">Date</th><th>Content</th><th style="text-align:right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td class="hide-mobile" style="color:var(--text-muted); font-size:13px;"><?php echo date('M j, Y', strtotime($row['thought_date'])); ?></td>
                                        <td><?php echo sanitize(mb_strimwidth($row['thought_text'], 0, 100, "...")); ?></td>
                                        <td style="text-align:right">
                                            <button class="btn btn-icon" onclick='openModal("edit", <?php echo json_encode($row); ?>)'><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-icon" onclick="openDeleteModal(<?php echo $row['id']; ?>, 'thought')"><i class="fas fa-trash"></i></button>
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
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
                <?php if ($stats['feedback'] > 0): ?>
                    <button type="button" class="btn btn-secondary" onclick="openMarkReadModal()"><i class="fas fa-check-double"></i> Mark All Read</button>
                    <button type="button" class="btn btn-primary" onclick="openBulkDeleteModal()"><i class="fas fa-dumpster"></i> Delete All</button>
                <?php endif; ?>
            </div>
            <div class="glass-card" style="padding:0">
                <?php if (empty($data)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No feedback received yet.</p></div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead><tr><th>Status</th><th>User</th><th class="hide-mobile">Rating</th><th>Message</th><th style="text-align:right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                                        <td><strong><?php echo sanitize($row['name']); ?></strong><br><small><?php echo sanitize($row['email']); ?></small></td>
                                        <td class="hide-mobile" style="color:#ffc107"><?php echo str_repeat('★', $row['rating']); ?></td>
                                        <td><div class="text-ellipsis"><?php echo sanitize($row['message']); ?></div></td>
                                        <td style="text-align:right">
                                            <button class="btn btn-icon" onclick='openModal("view", <?php echo json_encode($row); ?>)'><i class="fas fa-eye"></i></button>
                                            <button class="btn btn-icon" onclick="openDeleteModal(<?php echo $row['id']; ?>, 'feedback')"><i class="fas fa-trash"></i></button>
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
                    <button class="btn btn-primary" <?php echo ($stats['subs'] == 0) ? 'disabled' : ''; ?>><i class="fas fa-download"></i> Export CSV</button>
                </form>
            </div>
            <div class="glass-card" style="padding:0">
                <?php if (empty($data)): ?>
                    <div class="empty-state"><i class="fas fa-users-slash"></i><p>No subscribers yet.</p></div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead><tr><th class="hide-mobile">ID</th><th>Email</th><th>Subscribed</th><th style="text-align:right">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($data as $row): ?>
                                    <tr>
                                        <td class="hide-mobile" style="color:var(--text-muted)">#<?php echo $row['id']; ?></td>
                                        <td><?php echo sanitize($row['email']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($row['date_subscribed'])); ?></td>
                                        <td style="text-align:right">
                                            <button class="btn btn-icon" onclick="copyText('<?php echo sanitize($row['email']); ?>')"><i class="fas fa-copy"></i></button>
                                            <button class="btn btn-icon" onclick="openDeleteModal(<?php echo $row['id']; ?>, 'subscriber')"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?tab=<?php echo $active_tab; ?>&page=<?php echo $i; ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- MODALS -->
    <div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(5px);">
        <div class="glass-card" style="width:90%; max-width:500px; background:#111;">
            <h3>Edit Thought</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                <input type="hidden" name="action" value="update_thought">
                <input type="hidden" name="id" id="edit_id">
                <textarea name="edit_text" id="edit_text" class="thought-input"></textarea>
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAllModals()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="viewModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(5px);">
        <div class="glass-card" style="width:90%; max-width:600px; background:#111;">
            <h3>Feedback Details</h3>
            <div id="viewContent" style="margin:20px 0; line-height:1.6; color:#ccc;"></div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <form method="POST" id="markReadForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="id" id="view_id">
                    <button type="submit" class="btn btn-primary">Mark as Read</button>
                </form>
                <button type="button" class="btn btn-secondary" onclick="closeAllModals()">Close</button>
            </div>
        </div>
    </div>

    <div id="deleteModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:3000; align-items:center; justify-content:center;">
        <div class="glass-card" style="width:90%; max-width:400px; text-align:center;">
            <div style="font-size:40px; color: #c90202; margin-bottom:15px;"><i class="fas fa-exclamation-triangle"></i></div>
            <h3>Are you sure?</h3>
            <p style="color:#aaa; margin:15px 0 25px;">This action cannot be undone.</p>
            <div style="display:flex; justify-content:center; gap:15px;">
                <button class="btn btn-secondary" onclick="closeAllModals()">Cancel</button>
                <button class="btn btn-primary" onclick="document.getElementById('deleteForm').submit()">Yes, Delete</button>
            </div>
        </div>
    </div>

    <div id="markReadModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:3000; align-items:center; justify-content:center;">
        <div class="glass-card" style="width:90%; max-width:400px; text-align:center;">
            <h3>Mark all as read?</h3>
            <div style="display:flex; justify-content:center; gap:15px; margin-top:20px;">
                <button class="btn btn-secondary" onclick="closeAllModals()">Cancel</button>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-primary">Confirm</button>
                </form>
            </div>
        </div>
    </div>

    <form id="deleteForm" method="POST" style="display:none">
        <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
        <input type="hidden" name="action" value="delete_item" id="form_action">
        <input type="hidden" name="id" id="del_id">
        <input type="hidden" name="type" id="del_type">
        <input type="hidden" name="redirect_tab" value="<?php echo $active_tab; ?>">
    </form>

    <script>
        window.onload = function() {
            if (localStorage.getItem('sidebar-collapsed') === 'true' && window.innerWidth > 900) {
                document.getElementById('sidebar').classList.add('collapsed');
                document.getElementById('main-content').classList.add('full-width'); 
            }
            <?php if (isset($_SESSION['toast'])): ?>
                const t = document.getElementById('toast');
                document.getElementById('toast-msg').innerText = "<?php echo $_SESSION['toast']['msg']; ?>";
                t.classList.add('show');
                setTimeout(() => t.classList.remove('show'), 3000);
                <?php unset($_SESSION['toast']); ?>
            <?php endif; ?>
        };

        function toggleSidebarSize() {
            const s = document.getElementById('sidebar'), m = document.getElementById('main-content'), i = document.getElementById('collapse-icon');
            s.classList.toggle('collapsed');
            m.classList.toggle('full-width');
            const isCollapsed = s.classList.contains('collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed); 
        }

        function toggleMobileSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }

        function openModal(type, data) {
            if (type === 'edit') {
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_text').value = data.thought_text;
                document.getElementById('editModal').style.display = 'flex';
            } else if (type === 'view') {
                document.getElementById('viewContent').innerHTML = `
                    <p><strong style="color:var(--primary)">From:</strong> ${data.name} (${data.email})</p>
                    <div style="background:rgba(255,255,255,0.05); padding:15px; margin-top:15px; border-radius:8px; border-left:3px solid var(--primary)">${data.message}</div>`;
                document.getElementById('view_id').value = data.id;
                document.getElementById('markReadForm').style.display = (data.status === 'read' ? 'none' : 'block');
                document.getElementById('viewModal').style.display = 'flex';
            }
        }

        function copyText(text) {
            navigator.clipboard.writeText(text);
            const t = document.getElementById('toast');
            document.getElementById('toast-msg').innerText = "Copied to clipboard!";
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2000);
        }

        function openDeleteModal(id, type) {
            document.getElementById('del_id').value = id;
            document.getElementById('del_type').value = type;
            document.getElementById('form_action').value = 'delete_item';
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function openBulkDeleteModal() {
            document.getElementById('form_action').value = 'delete_all_feedback';
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function openMarkReadModal() { document.getElementById('markReadModal').style.display = 'flex'; }
        function closeAllModals() { document.querySelectorAll('[id*="Modal"]').forEach(m => m.style.display = 'none'); }
        window.onclick = function(e) { if (e.target.id.includes('Modal')) closeAllModals(); }
    </script>
</body>
</html>
<?php $conn->close(); ?>
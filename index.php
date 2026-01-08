<?php
session_start(); 
// ==================== SECURITY CONFIGURATION ====================
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// $csp = "default-src 'self'; ";
// $csp .= "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com/ajax/libs; ";
// $csp .= "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com/ajax/libs https://fonts.googleapis.com; ";
// $csp .= "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com/ajax/libs; ";
// $csp .= "img-src 'self' data:; ";
// $csp .= "connect-src 'self';";
// header("Content-Security-Policy: " . $csp);

if (empty($_SERVER['HTTPS']) && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

// ==================== CREDENTIALS ====================
$valid_username = 'hermona';
$valid_password = 'shakespeare'; 

// ==================== SESSION VALIDATION ====================
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $sessionFingerprint = $_SESSION['fingerprint'] ?? '';
    $currentFingerprint = hash('sha256', 
        $_SERVER['HTTP_USER_AGENT'] . 
        (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '') .
        $_SERVER['REMOTE_ADDR']
    );
    
    if (hash_equals($sessionFingerprint, $currentFingerprint)) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            session_destroy();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        $_SESSION['last_activity'] = time();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
           
            echo json_encode(['success' => true, 'redirect' => 'dash.php']);
            exit;
        }
        
        header('Location: dash.php');
        exit;
    } else {
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ==================== LOGIN HANDLING ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $sessionCsrfToken = $_SESSION['csrf_token'] ?? '';
    
    if (!hash_equals($sessionCsrfToken, $csrfToken)) {
        $response = ['success' => false, 'message' => 'Invalid security token. Please refresh the page.'];
        sendJsonResponse($response);
    }
    
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    $response = ['success' => false, 'message' => ''];
    
    if (empty($username) || empty($password)) {
        $response['message'] = "Username and password are required";
    } elseif (strlen($username) > 50 || strlen($password) > 100) {
        $response['message'] = "Input length exceeds maximum allowed";
    } elseif (!preg_match('/^[a-zA-Z0-9_\-@.]+$/', $username)) {
        $response['message'] = "Invalid username format";
    } else { 
        // Logic check: Verify credentials
        $usernameValid = hash_equals($username, $valid_username);
        $passwordValid = hash_equals($password, $valid_password);
        
        if ($usernameValid && $passwordValid) {
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = bin2hex(random_bytes(16));
            $_SESSION['username'] = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            $_SESSION['fingerprint'] = hash('sha256', 
                $_SERVER['HTTP_USER_AGENT'] . 
                (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '') .
                $_SERVER['REMOTE_ADDR']
            );
            
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            setcookie(session_name(), session_id(), [
                'expires' => time() + 1800,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            $response['success'] = true;
            $response['redirect'] = 'dash.php';
        } else {
            // Failed login - No counter, just a generic error
            $response['message'] = "Invalid credentials. Please try again.";
            
            // Keeping a small random delay is still good practice to hinder 
            // automated bots, even without a hard lockout.
            usleep(random_int(100000, 300000));
        }
    }
    
    sendJsonResponse($response);
}

// ==================== INITIALIZE NEW SESSION ====================
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

// ==================== HELPER FUNCTIONS ====================
function sendJsonResponse($response) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        exit;
    } else {
        if (!$response['success']) {
            $_SESSION['login_error'] = $response['message'];
            header('Location: ' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'));
            exit;
        } else {
            header('Location: dash.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Echotongue</title> 
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    <link rel="icon" href="echologo.png" sizes="32x32" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/echologo.png">
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
            cursor: none;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: var(--font-body);
            overflow-x: hidden;
            background-image:
                radial-gradient(white, rgba(255, 255, 255, .2) 2px, transparent 3px),
                radial-gradient(white, rgba(255, 255, 255, .15) 1px, transparent 2px),
                radial-gradient(white, rgba(255, 255, 255, .1) 2px, transparent 3px);
            background-size: 550px 550px, 350px 350px, 250px 250px;
            background-position: 0 0, 40px 60px, 130px 270px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding-top: 20px;
            padding-left: 20px;
            padding-right: 20px;
            position: relative;
        }

        /* Red particles animation overlay */
        .particles-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .particle {
            position: absolute;
            background-color: rgba(201, 19, 19, 0.93);
            border-radius: 50%;
            animation: float 6s infinite ease-in-out;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0);
            }
            33% {
                transform: translateY(-20px) translateX(10px);
            }
            66% {
                transform: translateY(10px) translateX(-10px);
            }
        }

        /* Header */
        .login-header {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
        }

        .logo {
            font-family: 'Cinzel Decorative', serif;
            font-weight: 900;
            font-size: 28px;
            color: #ffffff;
            text-transform: uppercase;
            text-decoration: none;
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i {
            color: var(--primary-red);
        }

        .back-home {
            color: #aaa;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-home:hover {
            color: var(--primary-red);
        }

        /* Login Container */
        .login-container {
            width: 100%;
            max-width: 520px;
            height: auto;
            background: rgba(15, 15, 15, 0.85);
            backdrop-filter: blur(15px) saturate(180%);
            -webkit-backdrop-filter: blur(15px) saturate(180%);
            border-radius: 20px;
            padding: 50px 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow:
                0 25px 50px rgba(0, 0, 0, 0.8),
                0 0 0 1px rgba(201, 19, 19, 0.1),
                inset 0 0 30px rgba(201, 19, 19, 0.05);
            position: relative;
            z-index: 2;
            margin: 40px 0;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Decorative elements */
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 60%, rgba(201, 19, 19, 0.05) 100%);
            border-radius: 20px;
            pointer-events: none;
            z-index: -1;
        }

        .corner-decor {
            position: absolute;
            width: 30px;
            height: 30px;
            border: 1px solid var(--primary-red);
            opacity: 0.3;
        }

        .corner-decor.tl {
            top: 15px;
            left: 15px;
            border-right: 0;
            border-bottom: 0;
        }

        .corner-decor.tr {
            top: 15px;
            right: 15px;
            border-left: 0;
            border-bottom: 0;
        }

        .corner-decor.bl {
            bottom: 15px;
            left: 15px;
            border-right: 0;
            border-top: 0;
        }

        .corner-decor.br {
            bottom: 15px;
            right: 15px;
            border-left: 0;
            border-top: 0;
        }

        /* Login Header */
        .login-header-inner {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-title {
            font-family: 'Cinzel', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-white);
            margin-bottom: 10px;
            letter-spacing: 2px;
            position: relative;
            display: inline-block;
        }

        .login-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--primary-red), transparent);
        }

        .login-subtitle {
            color: #aaa;
            font-size: 0.9rem;
            margin-top: 20px;
            font-weight: 300;
            letter-spacing: 1px;
        }

        /* Form Styles */
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .form-group {
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--primary-red);
        }

        .input-with-icon {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            background-color: rgba(30, 30, 30, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            color: var(--text-white);
            font-size: 1rem;
            font-family: var(--font-body);
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-red);
            background-color: rgba(40, 40, 40, 0.9);
            box-shadow: 0 0 0 3px rgba(201, 19, 19, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-red);
            font-size: 1.1rem;
        }

        /* Password visibility toggle */
        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #777;
            cursor: pointer;
            font-size: 1.1rem;
            transition: color 0.3s ease;
            padding: 5px;
        }

        .password-toggle:hover {
            color: var(--primary-red);
        }

        /* Submit Button */
        .submit-btn {
            padding: 16px;
            background: radial-gradient(100% 120% at 50% 0%, #df0211 0%, #500D11 100%);
            color: #fdfdfd;
            border-radius: 10px;
            border: none;
            font-family: 'Cinzel', serif;
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 0.7px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(201, 19, 19, 0.3);
        }

        .submit-btn:active {
            transform: translateY(-1px);
        }

        .submit-btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.6s ease;
            z-index: -1;
        }

        .submit-btn:hover::after {
            left: 100%;
        }

        /* Custom Cursor */
        .cursor-dot,
        .cursor-outline {
            position: fixed;
            top: 0;
            left: 0;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            z-index: 9999;
            pointer-events: none;
            transition: transform 0.1s ease-out, width 0.3s, height 0.3s, background 0.3s, box-shadow 0.3s;
        }

        .cursor-dot {
            width: 8px;
            height: 8px;
            background: radial-gradient(circle at center, #ffffff 0%, #e0e0e0 60%, #bcbcbc 100%);
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.4);
        }

        .cursor-outline {
            width: 34px;
            height: 34px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.05);
        }

        /* Error/Success Messages */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: none;
            align-items: center;
            gap: 10px;
        }

        .alert.error {
            background-color: rgba(192, 0, 0, 0.1);
            border: 1px solid rgba(192, 0, 0, 0.3);
            color: #ff6b6b;
        }

        .alert.success {
            background-color: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.3);
            color: #4CAF50;
        }

        .alert.warning {
            background-color: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: #ffc107;
        }

        .cinzel {
            font-family: 'Cinzel Decorative', serif;
            font-weight: 900;
            font-size: 28px;
            color: #ffffff;
            text-transform: uppercase;
            text-decoration: none;
            margin-left: 2%;
        }

        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 1em;
            height: 1em;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

         

        /* Responsive Design */
        @media (max-width: 768px) {
            * {
                cursor: default;
            }

            .cursor-dot,
            .cursor-outline {
                display: none;
            }

            .login-container {
                padding: 40px 25px;
                max-width: 90%;
            }

            .login-title {
                font-size: 2rem;
            }

            .login-header {
                padding: 20px 25px;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            .back-home span {
                display: none;
            }
        }

        /* Shake animation for errors */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        
    </style>
</head>

<body>
    <!-- Red particles overlay -->
    <div class="particles-overlay" id="particles"></div> 

    <!-- Header -->
    <header class="login-header">
        <a href="index.html" class="cinzel">
            Echotongue
        </a>
        <a href="echotongue.dsintertravel.com" class="back-home">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Main Site</span>
        </a>
    </header>
        
    <!-- Login Container -->
    <div class="login-container">
        <!-- Decorative corners -->
        <div class="corner-decor tl"></div>
        <div class="corner-decor tr"></div>
        <div class="corner-decor bl"></div>
        <div class="corner-decor br"></div>
        
        <!-- Login Header -->
        <div class="login-header-inner">
            <h1 class="login-title">ACCESS PORTAL</h1>
            <p class="login-subtitle">Enter your credentials to enter the Universe</p> 
        </div>

        
        <!-- Error/Success Messages -->
        <div class="alert error" id="errorAlert">
            <i class="fas fa-exclamation-circle"></i> <span id="errorText">Invalid username or password. Please try again.</span>
        </div>

        <div class="alert success" id="successAlert">
            <i class="fas fa-check-circle"></i> Login successful! Redirecting...
        </div>

        <!-- Login Form -->
        <form class="login-form" id="loginForm" autocomplete="off">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-with-icon">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" class="form-input" placeholder="Enter username" autocomplete="username" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Enter password" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">
                <i class="fas fa-sign-in-alt"></i> SIGN IN
            </button>
        </form>
    </div>

    <!-- Custom Cursor -->
    <div class="cursor-dot" id="cursor-dot"></div>
    <div class="cursor-outline" id="cursor-outline"></div>

   <script>
        // ==================== INITIALIZATION ====================
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            setupEventListeners(); 
            
            // Show security warning in development
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                document.getElementById('securityWarning').style.display = 'block';
            }
        });

        // ==================== PARTICLE EFFECT ====================
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 30;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');

                const size = Math.random() * 6 + 2;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;
                particle.style.opacity = Math.random() * 0.5 + 0.1;
                particle.style.animationDelay = `${Math.random() * 5}s`;
                particle.style.animationDuration = `${Math.random() * 5 + 3}s`;

                particlesContainer.appendChild(particle);
            }
        }

        // ==================== CUSTOM CURSOR ====================
        const cursorDot = document.getElementById('cursor-dot');
        const cursorOutline = document.getElementById('cursor-outline');

        document.addEventListener('mousemove', (e) => {
            cursorDot.style.left = `${e.clientX}px`;
            cursorDot.style.top = `${e.clientY}px`;
            cursorOutline.style.left = `${e.clientX}px`;
            cursorOutline.style.top = `${e.clientY}px`;
        });

        const interactiveElements = document.querySelectorAll('button, a, input, .password-toggle');
        interactiveElements.forEach(el => {
            el.addEventListener('mouseenter', () => {
                cursorDot.style.transform = 'translate(-50%, -50%) scale(1.5)';
                cursorOutline.style.transform = 'translate(-50%, -50%) scale(1.2)';
            });

            el.addEventListener('mouseleave', () => {
                cursorDot.style.transform = 'translate(-50%, -50%) scale(1)';
                cursorOutline.style.transform = 'translate(-50%, -50%) scale(1)';
            });
        });

        // ==================== PASSWORD VISIBILITY TOGGLE ====================
        function setupEventListeners() {
            const passwordToggle = document.getElementById('passwordToggle');
            const passwordInput = document.getElementById('password');

            passwordToggle.addEventListener('click', () => {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = passwordToggle.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
                
                passwordToggle.setAttribute('aria-label', 
                    type === 'text' ? 'Hide password' : 'Show password'
                );
            });

            document.getElementById('loginForm').addEventListener('submit', handleLoginSubmit);
        }

        // ==================== LOGIN HANDLING ====================
        async function handleLoginSubmit(e) {
            e.preventDefault();

            document.getElementById('errorAlert').style.display = 'none';
            document.getElementById('successAlert').style.display = 'none'; 

            const form = e.target;
            const formData = new FormData(form);
            
            const username = formData.get('username').trim();
            const password = formData.get('password').trim();
            
            if (!username || !password) {
                showError('Username and password are required');
                return;
            }
           

            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner"></span> SIGNING IN...';
            submitBtn.disabled = true;

            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 15000); 
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);

                if (!response.ok) {
                    showError('Server error. Please try again.');
                    return;
                }

                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    const result = await response.json();

                    if (result.success) {
                        showSuccess();
                        form.reset();
                        
                        // Redirect
                        setTimeout(() => {
                            window.location.href = result.redirect || 'dash.php';
                        }, 1000);
                    } else {
                        showError(result.message);
                        
                        // Clear password field
                        document.getElementById('password').value = '';
                        
                        // Shake animation
                        form.style.animation = 'shake 0.5s';
                        setTimeout(() => form.style.animation = '', 500);
                    }
                } else {
                    window.location.reload();
                }
            } catch (error) {
                if (error.name === 'AbortError') {
                    showError('Request timeout. Please try again.');
                } else {
                    showError('A network error occurred. Please check your connection.');
                }
                console.error('Login error:', error);
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }

        function showError(message) {
            const errorAlert = document.getElementById('errorAlert');
            const errorText = document.getElementById('errorText');
            errorText.textContent = message;
            errorAlert.style.display = 'flex';
        }

        function showSuccess() {
            document.getElementById('successAlert').style.display = 'flex';  
        }

        // ==================== KEYBOARD SHORTCUTS ====================
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'Enter') {
                document.getElementById('loginForm').requestSubmit();
            }
            
            if (e.key === 'Escape') {
                document.getElementById('loginForm').reset();
                document.getElementById('errorAlert').style.display = 'none';
                // rateLimitWarning check removed
            }
        });

        // ==================== INPUT VALIDATION ====================
        document.getElementById('username').addEventListener('input', function(e) {
            if (this.value.length > 50) {
                this.value = this.value.substring(0, 50);
            }
            if (!/^[a-zA-Z0-9_\-@.]*$/.test(this.value)) {
                this.value = this.value.slice(0, -1);
            }
        });

        document.getElementById('password').addEventListener('input', function(e) {
            if (this.value.length > 100) {
                this.value = this.value.substring(0, 100);
            }
        });
    </script>
</body> 
</html>
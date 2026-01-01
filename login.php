<?php
session_start(); 
// Hardcoded credentials (Change these to your desired login)
$valid_username = 'admin';
$valid_password = 'password123'; // Change this!

// If already logged in, redirect to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Handle AJAX request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode(['success' => true, 'redirect' => 'dash.php']);
        exit;
    }
    header('Location: dash.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    $response = ['success' => false, 'message' => ''];
    
    if (empty($username) || empty($password)) {
        $response['message'] = "Username and password required";
    } else {
        // Check against hardcoded credentials
        if ($username === $valid_username && $password === $valid_password) {
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = $username;
            $_SESSION['logged_in'] = true;
            
            $response['success'] = true;
            $response['redirect'] = 'dash.php';
        } else {
            $response['message'] = "Invalid username or password";
        }
    }
    
    // Check if it's an AJAX request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        // For non-AJAX requests
        if (!$response['success']) {
            $_SESSION['login_error'] = $response['message'];
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            header('Location: dash.php');
            exit;
        }
    }
}

// Display error from session if exists
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Echotongue</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700&display=swap" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Montserrat:wght@300;400;500;600&family=Orbitron:wght@400;500;600&display=swap"
        rel="stylesheet">
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
            background-color: rgba(201, 19, 19, 0.5);
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
            color: var(--text-dim);
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

        /* Error/Success Messages */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: none;
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

        .cinzel {
            font-family: 'Cinzel Decorative', serif;
            font-weight: 900;
            font-size: 28px;
            color: #ffffff;
            text-transform: uppercase;
            text-decoration: none;
            margin-left: 2%;
        }

        /* Debug info - remove in production */
        .debug-info {
            position: absolute;
            bottom: 10px;
            right: 10px;
            font-size: 0.7rem;
            color: #666;
            z-index: 100;
            background: rgba(0,0,0,0.5);
            padding: 5px;
            border-radius: 3px;
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
        <a href="index.html" class="back-home">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Home</span>
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
        <form class="login-form" id="loginForm">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-with-icon">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" class="form-input" placeholder="Enter admin" value="admin">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Enter password123" value="password123">
                    <button type="button" class="password-toggle" id="passwordToggle">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-sign-in-alt"></i> SIGN IN
            </button>
        </form>
    </div>

    <!-- Debug info - remove in production -->
    <div class="debug-info">
        Hardcoded Login | User: admin | Pass: password123
    </div>

    <!-- Custom Cursor -->
    <div class="cursor-dot" id="cursor-dot"></div>
    <div class="cursor-outline" id="cursor-outline"></div>

    <script>
        // Create floating particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 30;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');

                // Random size between 2-8px
                const size = Math.random() * 6 + 2;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;

                // Random position
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;

                // Random opacity
                particle.style.opacity = Math.random() * 0.5 + 0.1;

                // Random animation delay and duration
                particle.style.animationDelay = `${Math.random() * 5}s`;
                particle.style.animationDuration = `${Math.random() * 5 + 3}s`;

                particlesContainer.appendChild(particle);
            }
        }

        // Custom cursor
        const cursorDot = document.getElementById('cursor-dot');
        const cursorOutline = document.getElementById('cursor-outline');

        document.addEventListener('mousemove', (e) => {
            cursorDot.style.left = `${e.clientX}px`;
            cursorDot.style.top = `${e.clientY}px`;

            cursorOutline.style.left = `${e.clientX}px`;
            cursorOutline.style.top = `${e.clientY}px`;
        });

        // Add hover effect to interactive elements
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

        // Password visibility toggle
        const passwordToggle = document.getElementById('passwordToggle');
        const passwordInput = document.getElementById('password');

        passwordToggle.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle icon
            const icon = passwordToggle.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });

        // Form submission
        const loginForm = document.getElementById('loginForm');
        const errorAlert = document.getElementById('errorAlert');
        const successAlert = document.getElementById('successAlert');
        const errorText = document.getElementById('errorText');

        // Show PHP error if exists (from non-AJAX submission)
        <?php if (!empty($error)): ?>
            errorText.textContent = "<?php echo htmlspecialchars($error); ?>";
            errorAlert.style.display = 'block';
        <?php endif; ?>

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Hide previous alerts
            errorAlert.style.display = 'none';
            successAlert.style.display = 'none';

            // Show loading state
            const submitBtn = loginForm.querySelector('.submit-btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SIGNING IN...';
            submitBtn.disabled = true;

            // Get form data
            const formData = new FormData(loginForm);
            
            try {
                // Send POST request to the same file
                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                // Check if response is JSON
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    const result = await response.json();

                    if (result.success) {
                        successAlert.style.display = 'block';
                        
                        // Redirect after a short delay
                        setTimeout(() => {
                            window.location.href = result.redirect || 'dash.php';
                        }, 1000);
                    } else {
                        errorText.textContent = result.message || 'Invalid username or password. Please try again.';
                        errorAlert.style.display = 'block';
                        
                        // Shake animation for error
                        loginForm.style.animation = 'shake 0.5s';
                        setTimeout(() => {
                            loginForm.style.animation = '';
                        }, 500);
                    }
                } else {
                    // If not JSON, reload the page (traditional form submission fallback)
                    window.location.reload();
                }
            } catch (error) {
                errorText.textContent = "A network error occurred. Please check your connection.";
                errorAlert.style.display = 'block';
                console.error('Login error:', error);
            } finally {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });

        // Add shake animation for errors
        const style = document.createElement('style');
        style.textContent = `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                20%, 40%, 60%, 80% { transform: translateX(5px); }
            }
        `;
        document.head.appendChild(style);

        // Initialize on page load
        window.addEventListener('DOMContentLoaded', () => {
            createParticles();
        });
    </script>
</body> 
</html>
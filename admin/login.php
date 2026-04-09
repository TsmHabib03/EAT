<?php
session_start();
require_once '../config/db_config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Handle login submission
$error_message = '';
$login_attempts = isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0;
$lockout_time = isset($_SESSION['lockout_time']) ? $_SESSION['lockout_time'] : 0;
$needsInitialAdminSetup = false;

try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'admin_users'")->rowCount() > 0;
    if (!$tableExists) {
        $needsInitialAdminSetup = true;
    } else {
        $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
        $needsInitialAdminSetup = $adminCount === 0;
    }
} catch (Exception $e) {
    $needsInitialAdminSetup = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($needsInitialAdminSetup) {
        $error_message = 'No admin account exists yet. Please run initial admin setup first.';
    } else {
    // Check if account is locked
    if ($lockout_time > time()) {
        $remaining_minutes = ceil(($lockout_time - time()) / 60);
        $error_message = "Account temporarily locked. Try again in $remaining_minutes minutes.";
    } else {
        // Reset lockout if time has passed
        if ($lockout_time > 0 && $lockout_time <= time()) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['lockout_time'] = 0;
            $login_attempts = 0;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;

        if (empty($username) || empty($password)) {
            $error_message = 'Please enter both username and password.';
            $login_attempts++;
        } else {
            // Database authentication
            try {
                // First check which columns exist
                $checkColumns = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'is_active'")->rowCount();
                $hasIsActive = $checkColumns > 0;
                
                $checkLastLogin = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'last_login'")->rowCount();
                $hasLastLogin = $checkLastLogin > 0;
                
                // Build query based on available columns
                $selectFields = "id, username, password, email";
                if ($hasIsActive) {
                    $selectFields .= ", is_active";
                }
                
                $stmt = $pdo->prepare("
                    SELECT $selectFields
                    FROM admin_users 
                    WHERE username = :username OR email = :email
                    LIMIT 1
                ");
                $stmt->execute(['username' => $username, 'email' => $username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                // Check if account is active (if column exists)
                $isActive = !$hasIsActive || ($admin && (!isset($admin['is_active']) || $admin['is_active']));

                if ($admin && $isActive) {
                    // Verify password (supports both MD5 legacy and bcrypt)
                    $passwordValid = false;
                    
                    // Check if it's MD5 hash (32 characters)
                    if (strlen($admin['password']) === 32) {
                        // Legacy MD5 authentication
                        $passwordValid = (md5($password) === $admin['password']);
                    } else {
                        // Modern bcrypt authentication
                        $passwordValid = password_verify($password, $admin['password']);
                    }

                    if ($passwordValid) {
                        // Successful login
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_email'] = $admin['email'] ?? '';
                        $_SESSION['login_attempts'] = 0;
                        $_SESSION['lockout_time'] = 0;

                        // Update last login if column exists
                        if ($hasLastLogin) {
                            try {
                                $updateStmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = :id");
                                $updateStmt->execute(['id' => $admin['id']]);
                            } catch (PDOException $e) {
                                // Ignore last_login update errors
                            }
                        }

                        if ($remember) {
                            // Set cookie for 30 days
                            setcookie('remember_admin', base64_encode($admin['username']), time() + (30 * 24 * 60 * 60), '/');
                        }

                        // Return JSON for AJAX
                        if (isset($_POST['ajax'])) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
                            exit();
                        }

                        header('Location: dashboard.php');
                        exit();
                    } else {
                        $error_message = 'Invalid username or password. Please try again.';
                        $login_attempts++;
                    }
                } else {
                    $error_message = ($admin && !$isActive) ? 'Your account has been deactivated. Contact administrator.' : 'Invalid username or password. Please try again.';
                    $login_attempts++;
                }

                // Lock account after 5 failed attempts
                if ($login_attempts >= 5) {
                    $_SESSION['lockout_time'] = time() + (15 * 60); // 15 minutes
                    $error_message = 'Too many failed login attempts. Account locked for 15 minutes.';
                }

            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $error_message = 'Database error: ' . $e->getMessage();
            }
        }

        $_SESSION['login_attempts'] = $login_attempts;

        // Return JSON for AJAX
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error_message, 'locked' => ($lockout_time > time())]);
            exit();
        }
    }
    }
}

// Check for remembered user
$remembered_username = '';
if (isset($_COOKIE['remember_admin'])) {
    $remembered_username = base64_decode($_COOKIE['remember_admin']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Secure Admin Portal - Employee Attendance Management">
    <meta name="theme-color" content="#059669">
    <title>Admin Portal - Employee Attendance System</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../css/auth-glassmorphism.css?v=<?php echo time(); ?>">

    <link rel="icon" type="image/png" href="../assets/images/Logo.png">
</head>
<body class="auth-page">
    <!-- Hero Carousel Background -->
    <div class="auth-wrapper">
        <div class="auth-hero">
            <div class="carousel-track" id="carouselTrack">
                <div class="carousel-slide"><img src="../assets/image/building1.jpg" alt="Campus Building 1"></div>
                <div class="carousel-slide"><img src="../assets/image/building2.jpg" alt="Campus Building 2"></div>
                <div class="carousel-slide"><img src="../assets/image/building3.jpg" alt="Campus Building 3"></div>
                <div class="carousel-slide"><img src="../assets/images/main-campus-heritage.jpg" alt="Main Campus Heritage Building"></div>
                <div class="carousel-slide"><img src="../assets/images/HB Building.jpg" alt="HB Building"></div>
            </div>
            <div class="carousel-indicators" id="carouselIndicators">
                <button class="carousel-dot active" data-index="0" aria-label="Slide 1"></button>
                <button class="carousel-dot" data-index="1" aria-label="Slide 2"></button>
                <button class="carousel-dot" data-index="2" aria-label="Slide 3"></button>
                <button class="carousel-dot" data-index="3" aria-label="Slide 4"></button>
                <button class="carousel-dot" data-index="4" aria-label="Slide 5"></button>
            </div>
            <div class="auth-hero-content">
                <h2>Employee Attendance System</h2>
                <p>Digital Attendance Management Portal - Integrity, Service, Excellence, Empowerment</p>
            </div>
        </div>

        <div class="auth-orb auth-orb-1"></div>
        <div class="auth-orb auth-orb-2"></div>
        <div class="auth-orb auth-orb-3"></div>

        <!-- Form Panel -->
        <div class="auth-panel">
            <div class="auth-card" id="loginCard">
                <!-- Logo & Header -->
                <div class="auth-header">
                    <div class="auth-logo">
                        <img src="../assets/images/Logo.png" alt="Employee Attendance System Logo" onerror="this.style.display='none'">
                    </div>
                    <h1 class="auth-title">Admin Portal</h1>
                    <p class="auth-school-name">Employee Attendance System</p>
                    <p class="auth-subtitle">Sign in to manage attendance</p>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['access_denied'])): ?>
                <div class="auth-alert auth-alert-error" id="accessDeniedAlert">
                    <svg class="auth-alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M4.93 4.93l14.14 14.14"></path>
                    </svg>
                    <div class="auth-alert-body">
                        <strong>Access Denied</strong>
                        <p><?php echo htmlspecialchars($_SESSION['access_denied']); ?></p>
                    </div>
                    <button class="auth-alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php 
                    unset($_SESSION['access_denied']); 
                endif; 
                ?>

                <?php if (!empty($error_message)): ?>
                <div class="auth-alert auth-alert-error" id="errorAlert">
                    <svg class="auth-alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div class="auth-alert-body">
                        <strong><?php echo $lockout_time > time() ? 'Account Locked' : 'Login Failed'; ?></strong>
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                    <button class="auth-alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>

                <?php if ($needsInitialAdminSetup): ?>
                <div class="auth-alert" style="background: rgba(5, 150, 105, 0.12); border: 1px solid rgba(5, 150, 105, 0.35); color: #065f46;">
                    <div class="auth-alert-body">
                        <strong>Initial Setup Required</strong>
                        <p>No admin account exists yet. <a href="setup_admin.php" style="color:#065f46; font-weight:700; text-decoration:underline;">Create first admin account</a>.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form class="auth-form" id="loginForm" method="POST" action="" novalidate>
                    <!-- Username -->
                    <div class="auth-input-group" data-validate="required">
                        <div class="auth-input-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            id="username" 
                            name="username"
                            placeholder=" " 
                            autocomplete="username"
                            value="<?php echo htmlspecialchars($remembered_username); ?>"
                            required
                            autofocus
                        >
                        <label for="username">Username or Email</label>
                        <div class="auth-input-accent"></div>
                        <div class="auth-input-status">
                            <svg class="check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <svg class="error-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </div>
                        <div class="auth-input-error">Please enter a valid username or email</div>
                    </div>

                    <!-- Password -->
                    <div class="auth-input-group" data-validate="required">
                        <div class="auth-input-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password"
                            placeholder=" " 
                            autocomplete="current-password"
                            required
                        >
                        <label for="password">Password</label>
                        <button type="button" class="auth-toggle-pw" aria-label="Toggle password visibility">
                            <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eye-slash-icon hidden" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                        <div class="auth-input-accent"></div>
                        <div class="auth-input-status">
                            <svg class="check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <svg class="error-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </div>
                        <div class="auth-caps-warn hidden">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 15l5-5 5 5H7z"/></svg>
                            <span>Caps Lock is ON</span>
                        </div>
                        <div class="auth-input-error">Password must be at least 6 characters</div>
                    </div>

                    <!-- Remember & Forgot -->
                    <div class="auth-form-options">
                        <label class="auth-checkbox">
                            <input type="checkbox" name="remember" id="remember">
                            <span class="auth-checkmark">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </span>
                            <span>Remember me</span>
                        </label>
                        <a href="forgot_password.php" class="auth-forgot-link">Forgot password?</a>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="auth-btn auth-btn-primary" id="btnSignin">
                        <span class="btn-text">
                            <svg class="btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M13.8 12H3"/>
                            </svg>
                            Sign In
                        </span>
                        <span class="btn-loader hidden">
                            <svg class="auth-spinner" width="20" height="20" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4 31.4" transform="rotate(-90 12 12)"/>
                            </svg>
                            Signing in...
                        </span>
                    </button>
                </form>

                <!-- Footer -->
                <div class="auth-footer">
                    <p>&copy; <?php echo date('Y'); ?> Employee Attendance System.</p>
                    <p class="footer-motto">Integrity, Service, Excellence, Empowerment</p>
                </div>

                <div class="auth-security">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Protected by 256-bit encryption</span>
                </div>
                <div class="auth-security-detail">SSL Secured &bull; SOC 2 Compliant &bull; GDPR Ready</div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="auth-success-modal hidden" id="successModal">
        <div class="auth-success-content">
            <div class="auth-success-check">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <h2>Welcome back, <span id="welcomeName">Admin</span>!</h2>
            <p>Redirecting to dashboard...</p>
            <div class="auth-progress-bar">
                <div class="auth-progress-fill"></div>
            </div>
        </div>
    </div>

    <script src="../js/admin-login.js"></script>
    <script src="../js/auth-carousel.js?v=<?php echo time(); ?>"></script>
</body>
</html>


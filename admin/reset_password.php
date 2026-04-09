<?php
session_start();
require_once '../config/db_config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

// Check if token is provided
if (!isset($_GET['token']) || empty(trim($_GET['token']))) {
    $error = 'invalid_link';
    $errorMessage = 'Invalid password reset link. Please request a new one.';
} else {
    $token = trim($_GET['token']);
    
    // Hash the token to match stored hash
    $hashedToken = hash('sha256', $token);
    
    try {
        // Validate token
        $stmt = $pdo->prepare("
            SELECT id, username, email, reset_token_expires_at 
            FROM admin_users 
            WHERE reset_token = :token 
            AND reset_token_expires_at > NOW()
            LIMIT 1
        ");
        
        $stmt->execute(['token' => $hashedToken]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            $error = 'expired_link';
            $errorMessage = 'This password reset link has expired or is invalid. Please request a new one.';
        }
    } catch (PDOException $e) {
        error_log("Database error in reset password: " . $e->getMessage());
        $error = 'system_error';
        $errorMessage = 'A system error occurred. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reset Your Admin Password - Employee Attendance System">
    <meta name="theme-color" content="#059669">
    <title>Reset Password - Employee Attendance System</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../css/auth-glassmorphism.css?v=<?php echo time(); ?>">

    <link rel="icon" type="image/png" href="../assets/images/Logo.png">
</head>
<body class="auth-page">
    <div class="auth-wrapper">
        <!-- Hero Carousel -->
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
                <p>Create a new secure password for your account</p>
            </div>
        </div>

        <div class="auth-orb auth-orb-1"></div>
        <div class="auth-orb auth-orb-2"></div>
        <div class="auth-orb auth-orb-3"></div>

        <!-- Form Panel -->
        <div class="auth-panel">
            <div class="auth-card" id="resetPasswordCard">
                <?php if (isset($error)): ?>
                    <!-- Error State -->
                    <div class="auth-header">
                        <div class="auth-logo">
                            <div class="auth-logo-icon" style="background:linear-gradient(135deg,rgba(239,68,68,0.12),rgba(220,38,38,0.12));">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#ef4444;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                            </div>
                        </div>
                        <h1 class="auth-title">Link Invalid or Expired</h1>
                        <p class="auth-subtitle"><?php echo htmlspecialchars($errorMessage); ?></p>
                    </div>

                    <div class="auth-alert auth-alert-error">
                        <svg class="auth-alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <div class="auth-alert-body">
                            <strong>Reset Link Issue</strong>
                            <p>The password reset link is either invalid, has already been used, or has expired.</p>
                        </div>
                    </div>

                    <div style="text-align:center;margin-top:1.5rem;">
                        <a href="forgot_password.php" class="auth-btn auth-btn-primary" style="text-decoration:none;display:inline-flex;">
                            <span class="btn-text">
                                <svg class="btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 2L11 13"></path>
                                    <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
                                </svg>
                                Request New Reset Link
                            </span>
                        </a>
                        <a href="login.php" style="display:block;margin-top:1rem;color:var(--green-600);text-decoration:none;font-weight:500;font-size:0.875rem;">
                            Back to Login
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Valid Token - Show Reset Form -->
                    <div class="auth-header">
                        <div class="auth-logo">
                            <div class="auth-logo-icon">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            </div>
                        </div>
                        <h1 class="auth-title">Create New Password</h1>
                        <p class="auth-subtitle">Enter a strong password for your account: <strong><?php echo htmlspecialchars($admin['username']); ?></strong></p>
                    </div>

                    <!-- Alerts -->
                    <div class="auth-alert auth-alert-success hidden" id="successAlert">
                        <svg class="auth-alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <div class="auth-alert-body">
                            <strong>Password Updated!</strong>
                            <p>Your password has been successfully changed. Redirecting to login...</p>
                        </div>
                    </div>

                    <div class="auth-alert auth-alert-error hidden" id="errorAlert">
                        <svg class="auth-alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <div class="auth-alert-body">
                            <strong>Error</strong>
                            <p id="errorMessage">Something went wrong. Please try again.</p>
                        </div>
                        <button class="auth-alert-close" onclick="this.parentElement.classList.add('hidden')">&times;</button>
                    </div>

                    <!-- Reset Form -->
                    <form class="auth-form" id="resetPasswordForm" novalidate>
                        <input type="hidden" id="token" name="token" value="<?php echo htmlspecialchars($token); ?>">

                        <!-- New Password -->
                        <div class="auth-input-group" data-validate="password">
                            <div class="auth-input-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            </div>
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password"
                                placeholder=" " 
                                autocomplete="new-password"
                                required
                                autofocus
                            >
                            <label for="new_password">New Password</label>
                            <button type="button" class="auth-toggle-pw" data-target="new_password" aria-label="Toggle password visibility">
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
                            <div class="auth-input-error">Password must be at least 8 characters</div>
                        </div>

                        <!-- Password Strength -->
                        <div class="auth-pw-strength hidden" id="passwordStrength">
                            <div class="strength-label">Password Strength: <span id="strengthText">Weak</span></div>
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="auth-input-group" data-validate="password">
                            <div class="auth-input-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            </div>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password"
                                placeholder=" " 
                                autocomplete="new-password"
                                required
                            >
                            <label for="confirm_password">Confirm New Password</label>
                            <button type="button" class="auth-toggle-pw" data-target="confirm_password" aria-label="Toggle password visibility">
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
                            <div class="auth-input-error">Passwords do not match</div>
                        </div>

                        <button type="submit" class="auth-btn auth-btn-primary" id="btnSubmit">
                            <span class="btn-text">
                                <svg class="btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                Reset Password
                            </span>
                            <span class="btn-loader hidden">
                                <svg class="auth-spinner" width="20" height="20" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4 31.4" transform="rotate(-90 12 12)"/>
                                </svg>
                                Updating...
                            </span>
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Security Badge -->
                <div class="auth-security">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Your information is secure with us</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        <?php if (!isset($error)): ?>
        /**
         * Reset Password Form Handler
         */
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetPasswordForm');
            const btnSubmit = document.getElementById('btnSubmit');
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const tokenInput = document.getElementById('token');
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');
            const passwordStrength = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('strengthText');
            const strengthFill = document.getElementById('strengthFill');

            // Password toggle
            document.querySelectorAll('.auth-toggle-pw').forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    const eyeIcon = this.querySelector('.eye-icon');
                    const eyeSlashIcon = this.querySelector('.eye-slash-icon');

                    if (input.type === 'password') {
                        input.type = 'text';
                        eyeIcon.classList.add('hidden');
                        eyeSlashIcon.classList.remove('hidden');
                    } else {
                        input.type = 'password';
                        eyeIcon.classList.remove('hidden');
                        eyeSlashIcon.classList.add('hidden');
                    }
                });
            });

            // Password strength checker
            function checkPasswordStrength(password) {
                let strength = 0;
                if (password.length >= 8) strength++;
                if (password.length >= 12) strength++;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                if (/\d/.test(password)) strength++;
                if (/[^a-zA-Z\d]/.test(password)) strength++;
                return strength;
            }

            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                
                if (password.length === 0) {
                    passwordStrength.classList.add('hidden');
                    return;
                }

                passwordStrength.classList.remove('hidden');
                const strength = checkPasswordStrength(password);

                strengthFill.className = 'strength-fill';
                
                if (strength <= 2) {
                    strengthFill.classList.add('weak');
                    strengthText.textContent = 'Weak';
                    strengthText.style.color = '#ef4444';
                } else if (strength <= 4) {
                    strengthFill.classList.add('medium');
                    strengthText.textContent = 'Medium';
                    strengthText.style.color = '#f59e0b';
                } else {
                    strengthFill.classList.add('strong');
                    strengthText.textContent = 'Strong';
                    strengthText.style.color = '#10b981';
                }
            });

            function validatePasswordsMatch() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const confirmGroup = confirmPasswordInput.closest('.auth-input-group');

                if (confirmPassword.length === 0) {
                    confirmGroup.classList.remove('valid', 'invalid');
                    return false;
                }

                if (newPassword === confirmPassword) {
                    confirmGroup.classList.add('valid');
                    confirmGroup.classList.remove('invalid');
                    return true;
                } else {
                    confirmGroup.classList.add('invalid');
                    confirmGroup.classList.remove('valid');
                    return false;
                }
            }

            confirmPasswordInput.addEventListener('input', validatePasswordsMatch);
            newPasswordInput.addEventListener('input', function() {
                if (confirmPasswordInput.value.length > 0) {
                    validatePasswordsMatch();
                }
            });

            function setLoadingState(loading) {
                const btnText = btnSubmit.querySelector('.btn-text');
                const btnLoader = btnSubmit.querySelector('.btn-loader');
                
                if (loading) {
                    btnText.classList.add('hidden');
                    btnLoader.classList.remove('hidden');
                    btnSubmit.disabled = true;
                    newPasswordInput.disabled = true;
                    confirmPasswordInput.disabled = true;
                } else {
                    btnText.classList.remove('hidden');
                    btnLoader.classList.add('hidden');
                    btnSubmit.disabled = false;
                    newPasswordInput.disabled = false;
                    confirmPasswordInput.disabled = false;
                }
            }

            function showSuccess() {
                errorAlert.classList.add('hidden');
                successAlert.classList.remove('hidden');
                form.reset();
                setTimeout(() => { window.location.href = 'login.php'; }, 3000);
            }

            function showError(message) {
                successAlert.classList.add('hidden');
                errorMessage.textContent = message;
                errorAlert.classList.remove('hidden');
                errorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const newPassword = newPasswordInput.value.trim();
                const confirmPassword = confirmPasswordInput.value.trim();
                const token = tokenInput.value;

                if (!newPassword || newPassword.length < 8) {
                    showError('Password must be at least 8 characters long');
                    newPasswordInput.focus();
                    return;
                }

                if (newPassword !== confirmPassword) {
                    showError('Passwords do not match');
                    confirmPasswordInput.focus();
                    return;
                }

                successAlert.classList.add('hidden');
                errorAlert.classList.add('hidden');
                setLoadingState(true);

                try {
                    const response = await fetch('../api/update_password.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            token: token,
                            new_password: newPassword,
                            confirm_password: confirmPassword
                        })
                    });

                    const data = await response.json();
                    setLoadingState(false);

                    if (data.success) {
                        showSuccess();
                    } else {
                        showError(data.message || 'Failed to reset password. Please try again.');
                    }
                } catch (error) {
                    setLoadingState(false);
                    console.error('Error:', error);
                    showError('Network error. Please check your connection and try again.');
                }
            });
        });
        <?php endif; ?>
    </script>
    <script src="../js/auth-carousel.js?v=<?php echo time(); ?>"></script>
</body>
</html>


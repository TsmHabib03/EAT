<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reset Your Admin Password - Employee Attendance System">
    <meta name="theme-color" content="#059669">
    <title>Forgot Password - Employee Attendance System</title>

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
                <p>Password Recovery - We&rsquo;ll help you get back in</p>
            </div>
        </div>

        <div class="auth-orb auth-orb-1"></div>
        <div class="auth-orb auth-orb-2"></div>
        <div class="auth-orb auth-orb-3"></div>

        <!-- Form Panel -->
        <div class="auth-panel">
            <div class="auth-card" id="forgotPasswordCard">
                <!-- Back Link -->
                <a href="login.php" class="auth-back">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    Back to Login
                </a>

                <!-- Logo & Header -->
                <div class="auth-header">
                    <div class="auth-logo">
                        <img src="../assets/images/Logo.png" alt="EAT Logo" onerror="this.style.display='none'">
                    </div>
                    <h1 class="auth-title">Forgot Password?</h1>
                    <p class="auth-school-name">Employee Attendance System</p>
                    <p class="auth-subtitle">Enter your email and we&rsquo;ll send you a link to reset your password</p>
                </div>

                <!-- Alerts -->
                <div class="auth-alert auth-alert-success hidden" id="successAlert">
                    <svg class="auth-alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <div class="auth-alert-body">
                        <strong>Email Sent!</strong>
                        <p id="successMessage">If an account exists with this email, you will receive password reset instructions.</p>
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

                <!-- Forgot Password Form -->
                <form class="auth-form" id="forgotPasswordForm" novalidate>
                    <div class="auth-input-group" data-validate="email">
                        <div class="auth-input-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </div>
                        <input 
                            type="email" 
                            id="email" 
                            name="email"
                            placeholder=" " 
                            autocomplete="email"
                            required
                            autofocus
                        >
                        <label for="email">Email Address</label>
                        <div class="auth-input-accent"></div>
                        <div class="auth-input-status">
                            <svg class="check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <svg class="error-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </div>
                        <div class="auth-input-error">Please enter a valid email address</div>
                    </div>

                    <button type="submit" class="auth-btn auth-btn-primary" id="btnSubmit">
                        <span class="btn-text">
                            <svg class="btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 2L11 13"></path>
                                <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
                            </svg>
                            Send Reset Link
                        </span>
                        <span class="btn-loader hidden">
                            <svg class="auth-spinner" width="20" height="20" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4 31.4" transform="rotate(-90 12 12)"/>
                            </svg>
                            Sending...
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
                    <span>Your information is secure with us</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        /**
         * Forgot Password Form Handler
         */
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotPasswordForm');
            const btnSubmit = document.getElementById('btnSubmit');
            const emailInput = document.getElementById('email');
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');

            function validateEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }

            function setLoadingState(loading) {
                const btnText = btnSubmit.querySelector('.btn-text');
                const btnLoader = btnSubmit.querySelector('.btn-loader');
                
                if (loading) {
                    btnText.classList.add('hidden');
                    btnLoader.classList.remove('hidden');
                    btnSubmit.disabled = true;
                    emailInput.disabled = true;
                } else {
                    btnText.classList.remove('hidden');
                    btnLoader.classList.add('hidden');
                    btnSubmit.disabled = false;
                    emailInput.disabled = false;
                }
            }

            function showSuccess() {
                errorAlert.classList.add('hidden');
                successAlert.classList.remove('hidden');
                form.reset();
                successAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            function showError(message) {
                successAlert.classList.add('hidden');
                errorMessage.textContent = message;
                errorAlert.classList.remove('hidden');
                errorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            emailInput.addEventListener('blur', function() {
                const inputGroup = this.closest('.auth-input-group');
                
                if (this.value.trim() === '') {
                    inputGroup.classList.remove('valid', 'invalid');
                } else if (validateEmail(this.value)) {
                    inputGroup.classList.add('valid');
                    inputGroup.classList.remove('invalid');
                } else {
                    inputGroup.classList.add('invalid');
                    inputGroup.classList.remove('valid');
                }
            });

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const email = emailInput.value.trim();

                if (!email) {
                    showError('Please enter your email address');
                    emailInput.focus();
                    return;
                }

                if (!validateEmail(email)) {
                    showError('Please enter a valid email address');
                    emailInput.focus();
                    return;
                }

                successAlert.classList.add('hidden');
                errorAlert.classList.add('hidden');

                setLoadingState(true);

                try {
                    console.log('Sending request to API...');
                    const response = await fetch('../api/request_password_reset.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ email: email })
                    });

                    console.log('Response status:', response.status);
                    console.log('Response ok:', response.ok);

                    const responseText = await response.text();
                    console.log('Response text:', responseText);

                    setLoadingState(false);

                    let data;
                    try {
                        data = JSON.parse(responseText);
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        showError('Server error: Invalid response format. Response: ' + responseText.substring(0, 100));
                        return;
                    }

                    if (data.success) {
                        showSuccess();
                    } else {
                        showError(data.message || 'An error occurred. Please try again.');
                    }
                } catch (error) {
                    setLoadingState(false);
                    console.error('Fetch error:', error);
                    showError('Network error: ' + error.message + '. Please check browser console (F12) for details.');
                }
            });

            emailInput.addEventListener('input', function() {
                const inputGroup = this.closest('.auth-input-group');
                
                if (this.value.trim() !== '') {
                    if (validateEmail(this.value)) {
                        inputGroup.classList.add('valid');
                        inputGroup.classList.remove('invalid');
                    } else {
                        inputGroup.classList.add('invalid');
                        inputGroup.classList.remove('valid');
                    }
                } else {
                    inputGroup.classList.remove('valid', 'invalid');
                }
            });
        });
    </script>
    <script src="../js/auth-carousel.js?v=<?php echo time(); ?>"></script>
</body>
</html>


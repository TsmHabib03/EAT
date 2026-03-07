<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#059669">
    <title>QR Scanner - San Francisco High School</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bento Glass Design System -->
    <link rel="stylesheet" href="css/bento-glass.css">

    <!-- Admin Bento (scanner styles) -->
    <link rel="stylesheet" href="css/admin-bento.css">
    
    <!-- Scanner Glass Redesign -->
    <link rel="stylesheet" href="css/scanner-glass.css">
</head>
<body class="bg-mesh">
    <!-- Decorative Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="scanner-page">
        <!-- Top Bar -->
        <div class="scan-topbar">
            <a href="index.php" class="scan-topbar-brand">
                <img src="assets/asj-logo.png" alt="Logo" class="scan-topbar-logo" onerror="this.style.display='none'">
                <div class="scan-topbar-text">
                    <span class="scan-topbar-title">San Francisco High School</span>
                    <span class="scan-topbar-sub">QR Scanner</span>
                </div>
            </a>
            <button class="scan-topbar-menu" onclick="toggleMenu()">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Scanner Body -->
        <div class="scan-body">
            <!-- Status Bar -->
            <div class="scan-status">
                <i class="fas fa-clock"></i>
                <span id="current-time">--:--</span>
                <span>|</span>
                <span id="schedule-info">Loading...</span>
            </div>

            <!-- Camera Viewport -->
            <div class="scan-viewport" id="scan-viewport">
                <!-- Loading State -->
                <div class="scan-loading" id="scanner-loading">
                    <div class="dash-loader-spinner"></div>
                    <p>Initializing Camera...</p>
                </div>

                <!-- QR Reader -->
                <div id="qr-reader" style="width:100%;height:100%;position:absolute;inset:0;">
                    <video id="qr-video" style="width:100%;height:100%;object-fit:cover;"></video>
                </div>

                <!-- Scanner Overlay -->
                <div class="scan-frame-overlay">
                    <div class="scan-frame">
                        <div class="scan-frame-bl"></div>
                        <div class="scan-frame-br"></div>
                    </div>
                </div>

                <!-- Animated Laser -->
                <div class="scan-laser"></div>

                <!-- Hint -->
                <div class="scan-hint">
                    <i class="fas fa-mobile-screen"></i> Align QR code within the frame
                </div>
            </div>

            <!-- Controls -->
            <div class="scan-controls">
                <button id="start-scan-btn" class="scan-ctrl-btn scan-ctrl-primary">
                    <i class="fas fa-camera"></i> Start Scanning
                </button>
                <button id="stop-scan-btn" class="scan-ctrl-btn scan-ctrl-danger" style="display: none;">
                    <i class="fas fa-stop-circle"></i> Stop Scanner
                </button>
                <button class="scan-ctrl-btn scan-ctrl-secondary" onclick="openManualEntry()">
                    <i class="fas fa-keyboard"></i> Manual Entry
                </button>
            </div>
        </div>

        <!-- Success Overlay -->
        <div class="scan-result-overlay scan-result-overlay-success" id="success-overlay">
            <div class="scan-result-icon">
                <i class="fas fa-check"></i>
            </div>
            <div class="scan-result-text">
                <h2 id="success-title">Success!</h2>
                <p id="success-student">John Doe</p>
                <p id="success-time">Time In: 08:15 AM</p>
            </div>
            <button class="scan-result-btn" onclick="closeResultOverlay()">
                Continue Scanning
            </button>
        </div>

        <!-- Error Overlay -->
        <div class="scan-result-overlay scan-result-overlay-error" id="error-overlay">
            <div class="scan-result-icon">
                <i class="fas fa-times"></i>
            </div>
            <div class="scan-result-text">
                <h2>Oops!</h2>
                <p id="error-message">Student not found</p>
            </div>
            <button class="scan-result-btn" onclick="closeResultOverlay()">
                Try Again
            </button>
        </div>

        <!-- Manual Entry Modal -->
        <div class="scan-modal-backdrop" id="manual-backdrop" onclick="closeManualEntry()"></div>
        <div class="scan-modal" id="manual-modal">
            <div class="scan-modal-header">
                <h3 class="scan-modal-title">
                    <i class="fas fa-keyboard"></i> Manual Entry
                </h3>
                <button class="scan-modal-close" onclick="closeManualEntry()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="scan-modal-help">
                <i class="fas fa-info-circle"></i>
                <span>If the camera isn't working, you can manually enter the student's LRN (12-digit number).</span>
            </div>
            <form id="manual-form">
                <div class="scan-form-group">
                    <label class="scan-form-label" for="manual-lrn">
                        <i class="fas fa-id-card"></i> LRN (Learner Reference Number)
                    </label>
                    <input 
                        type="text" 
                        id="manual-lrn" 
                        name="lrn" 
                        class="scan-form-input" 
                        placeholder="Enter 12-digit LRN"
                        pattern="[0-9]{11,13}"
                        maxlength="13"
                        inputmode="numeric"
                        required
                    >
                    <div class="scan-form-error" id="lrn-error"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
                    <i class="fas fa-check-circle"></i> Mark Attendance
                </button>
            </form>
        </div>
    </div>

    <!-- Side Menu -->
    <div class="scan-sidemenu-backdrop" id="menu-backdrop" onclick="toggleMenu()"></div>
    <div class="scan-sidemenu" id="mobile-menu">
        <div class="scan-sidemenu-header">
            <img src="assets/asj-logo.png" alt="Logo" class="scan-sidemenu-logo" onerror="this.style.display='none'">
            <div class="scan-sidemenu-info">
                <h3>San Francisco High School</h3>
                <p>Attendance System</p>
            </div>
        </div>
        <a href="index.php" class="scan-sidemenu-link">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="scan_attendance.php" class="scan-sidemenu-link active">
            <i class="fas fa-qrcode"></i>
            <span>Scan Attendance</span>
        </a>
        <a href="admin/login.php" class="scan-sidemenu-link">
            <i class="fas fa-shield-halved"></i>
            <span>Admin Portal</span>
        </a>
    </div>

    <!-- ZXing QR Library -->
    <script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>

    <script>
        let codeReader = null;
        let isScanning = false;

        // Time Display
        function updateTime() {
            const now = new Date();
            const options = { 
                hour: '2-digit', 
                minute: '2-digit',
                timeZone: 'Asia/Manila'
            };
            document.getElementById('current-time').textContent = 
                now.toLocaleTimeString('en-US', options);
        }
        updateTime();
        setInterval(updateTime, 1000);

        // Initialize Scanner with ULTRA-FAST Configuration
        async function initializeScanner() {
            try {
                if (typeof ZXing === 'undefined') {
                    throw new Error('ZXing library not loaded. Please check your internet connection.');
                }
                
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Camera not supported in this browser');
                }

                const hints = new Map();
                hints.set(ZXing.DecodeHintType.TRY_HARDER, false);
                hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.QR_CODE]);
                hints.set(ZXing.DecodeHintType.CHARACTER_SET, 'UTF-8');
                hints.set(ZXing.DecodeHintType.PURE_BARCODE, false);
                
                codeReader = new ZXing.BrowserQRCodeReader(hints);
                document.getElementById('scanner-loading').style.display = 'none';
                
                setTimeout(() => {
                    startScanning();
                }, 500);
            } catch (error) {
                document.getElementById('scanner-loading').style.display = 'none';
                
                let errorMessage = 'Camera initialization failed';
                if (error.message.includes('not supported')) {
                    errorMessage = 'Your browser does not support camera access. Please use Chrome, Firefox, or Safari.';
                } else {
                    errorMessage = 'Unable to initialize camera. Please refresh the page and allow camera permissions.';
                }
                
                showError(errorMessage);
            }
        }

        // Start ULTRA-FAST Scanning
        async function startScanning() {
            if (!codeReader) await initializeScanner();
            
            try {
                isScanning = true;
                document.getElementById('start-scan-btn').style.display = 'none';
                document.getElementById('stop-scan-btn').style.display = 'flex';
                document.getElementById('scan-viewport').classList.add('scanning');

                const videoElement = document.getElementById('qr-video');

                const constraints = {
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1280, max: 1920 },
                        height: { ideal: 720, max: 1080 },
                        frameRate: { ideal: 60, min: 30 },
                        focusMode: 'continuous',
                        focusDistance: { ideal: 0 },
                        zoom: { ideal: 1 }
                    }
                };

                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                videoElement.srcObject = stream;
                
                const videoTrack = stream.getVideoTracks()[0];
                const capabilities = videoTrack.getCapabilities ? videoTrack.getCapabilities() : {};
                
                if (capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
                    await videoTrack.applyConstraints({
                        advanced: [{ focusMode: 'continuous' }]
                    });
                }
                
                await videoElement.play();

                await codeReader.decodeFromVideoDevice(undefined, videoElement, (result, err) => {
                    if (result && result.text && isScanning && !isProcessing) {
                        handleScanResult(result.text);
                    }
                });

            } catch (error) {
                let errorMessage = 'Failed to start camera';
                if (error.name === 'NotAllowedError') {
                    errorMessage = 'Camera access denied. Please allow camera permissions in your browser settings and try again.';
                } else if (error.name === 'NotFoundError') {
                    errorMessage = 'No camera found on this device. Please check your camera connection.';
                } else if (error.name === 'NotReadableError') {
                    errorMessage = 'Camera is already in use by another application. Please close other apps using the camera.';
                } else if (error.name === 'OverconstrainedError') {
                    errorMessage = 'Camera does not support the required settings. Trying fallback mode...';
                    try {
                        const simpleStream = await navigator.mediaDevices.getUserMedia({ 
                            video: { facingMode: 'environment' } 
                        });
                        const videoElement = document.getElementById('qr-video');
                        videoElement.srcObject = simpleStream;
                        await videoElement.play();
                        
                        await codeReader.decodeFromVideoDevice(undefined, videoElement, (result, err) => {
                            if (result && result.text && isScanning && !isProcessing) {
                                handleScanResult(result.text);
                            }
                        });
                        
                        return;
                    } catch (fallbackError) {
                        errorMessage = 'Failed to start camera even with basic settings.';
                    }
                } else if (error.name === 'SecurityError') {
                    errorMessage = 'Camera access blocked due to security restrictions. Please use HTTPS or localhost.';
                }
                
                showError(errorMessage);
                
                document.getElementById('start-scan-btn').style.display = 'flex';
                document.getElementById('stop-scan-btn').style.display = 'none';
                document.getElementById('scan-viewport').classList.remove('scanning');
                isScanning = false;
            }
        }

        // Stop Scanning
        function stopScanning() {
            isScanning = false;
            
            const videoElement = document.getElementById('qr-video');
            if (videoElement.srcObject) {
                const tracks = videoElement.srcObject.getTracks();
                tracks.forEach(track => track.stop());
                videoElement.srcObject = null;
            }
            
            if (codeReader) {
                codeReader.reset();
            }
            
            document.getElementById('start-scan-btn').style.display = 'flex';
            document.getElementById('stop-scan-btn').style.display = 'none';
            document.getElementById('scan-viewport').classList.remove('scanning');
        }

        // Handle Scan Result
        let isProcessing = false;
        
        async function handleScanResult(qrCode) {
            if (isProcessing) return;
            
            isProcessing = true;
            stopScanning();
            
            document.getElementById('scanner-loading').style.display = 'flex';
            document.getElementById('scanner-loading').querySelector('p').textContent = 'Processing attendance...';
            
            try {
                let lrn = qrCode.trim();
                
                if (lrn.includes('|')) {
                    lrn = lrn.split('|')[0].trim();
                }
                
                const response = await fetch('api/mark_attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `lrn=${encodeURIComponent(lrn)}`
                });

                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                }

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error('Invalid server response');
                }
                
                document.getElementById('scanner-loading').style.display = 'none';
                document.getElementById('scanner-loading').querySelector('p').textContent = 'Initializing Camera...';
                
                if (data.success) {
                    showSuccess(data);
                } else {
                    showError(data.message || 'Failed to mark attendance');
                }
            } catch (error) {
                document.getElementById('scanner-loading').style.display = 'none';
                document.getElementById('scanner-loading').querySelector('p').textContent = 'Initializing Camera...';
                
                let errorMessage = 'Network error. Please check your connection and try again.';
                if (error.message && error.message.includes('Server error')) {
                    errorMessage = 'Server error. Please contact the administrator.';
                }
                
                showError(errorMessage);
            } finally {
                setTimeout(() => {
                    isProcessing = false;
                }, 2000);
            }
        }

        // Show Success
        function showSuccess(data) {
            const titleElement = document.getElementById('success-title');
            if (data.status === 'time_in') {
                titleElement.textContent = 'Welcome! ✓';
            } else if (data.status === 'time_out') {
                titleElement.textContent = 'See You! ✓';
            } else {
                titleElement.textContent = 'Success! ✓';
            }
            
            document.getElementById('success-student').textContent = data.student_name || 'Student';
            
            const timeLabel = data.status === 'time_in' ? 'Time In' : 
                             data.status === 'time_out' ? 'Time Out' : 'Time';
            const timeValue = data.time_in || data.time_out || new Date().toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
            
            document.getElementById('success-time').textContent = `${timeLabel}: ${timeValue}`;
            document.getElementById('success-overlay').classList.add('active');
            
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBSuBzvLZiTYIHmu+7OCXQwcZaLvt559NEAxSp+PwtmMbBjiS2PTMeSwFJHfH8N2RQAoUXbPp66hVFApGn+DyvmwhBQ==');
                audio.play().catch(() => {});
            } catch (e) {}
            
            setTimeout(() => {
                closeResultOverlay();
            }, 3000);
        }

        // Show Error
        function showError(message) {
            document.getElementById('error-message').textContent = message;
            document.getElementById('error-overlay').classList.add('active');
            
            setTimeout(() => {
                closeResultOverlay();
            }, 3000);
        }

        // Close Result Overlay
        function closeResultOverlay() {
            document.getElementById('success-overlay').classList.remove('active');
            document.getElementById('error-overlay').classList.remove('active');
            startScanning();
        }

        // Manual Entry Functions
        function openManualEntry() {
            document.getElementById('manual-modal').classList.add('active');
            document.getElementById('manual-backdrop').classList.add('active');
        }

        function closeManualEntry() {
            document.getElementById('manual-modal').classList.remove('active');
            document.getElementById('manual-backdrop').classList.remove('active');
        }

        // Manual Form Submit
        document.getElementById('manual-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const lrnInput = document.getElementById('manual-lrn');
            const lrn = lrnInput.value.trim();
            const errorDiv = document.getElementById('lrn-error');
            
            if (!/^\d{11,13}$/.test(lrn)) {
                errorDiv.textContent = 'Please enter a valid 11-13 digit LRN';
                errorDiv.classList.add('active');
                lrnInput.style.borderColor = '#DC2626';
                return;
            }
            
            errorDiv.classList.remove('active');
            lrnInput.style.borderColor = '';
            
            closeManualEntry();
            await handleScanResult(lrn);
            
            lrnInput.value = '';
        });

        // Real-time validation for manual LRN input
        document.getElementById('manual-lrn').addEventListener('input', (e) => {
            const input = e.target;
            const value = input.value;
            const errorDiv = document.getElementById('lrn-error');
            
            input.value = value.replace(/[^\d]/g, '');
            
            if (input.value.length >= 11 && input.value.length <= 13) {
                errorDiv.classList.remove('active');
                input.style.borderColor = 'var(--green-500)';
            } else if (input.value.length > 0) {
                input.style.borderColor = '#DC2626';
            } else {
                input.style.borderColor = '';
                errorDiv.classList.remove('active');
            }
        });

        // Menu Toggle
        function toggleMenu() {
            document.getElementById('mobile-menu').classList.toggle('active');
            document.getElementById('menu-backdrop').classList.toggle('active');
        }

        // Event Listeners
        document.getElementById('start-scan-btn').addEventListener('click', startScanning);
        document.getElementById('stop-scan-btn').addEventListener('click', stopScanning);

        // Initialize
        window.addEventListener('load', () => {
            initializeScanner();
            document.getElementById('schedule-info').textContent = 'Ready to scan';
        });
    </script>
</body>
</html>

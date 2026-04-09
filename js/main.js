// Main JavaScript functionality for the Employee Attendance System

// Utility function to show messages
function showMessage(message, type = 'info') {
    const messageDiv = document.getElementById('message');
    if (messageDiv) {
        const alertClass = type === 'error' ? 'alert-error' : 
                          type === 'success' ? 'alert-success' : 'alert-info';
        messageDiv.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            messageDiv.innerHTML = '';
        }, 5000);
    }
}

// Load dashboard statistics
async function loadDashboardStats() {
    try {
        const response = await fetch('api/get_dashboard_stats.php');
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('totalStudents').textContent = result.stats.total_employees;
            document.getElementById('presentToday').textContent = result.stats.present_today;
            document.getElementById('attendanceRate').textContent = result.stats.attendance_rate + '%';
        }
    } catch (error) {
        console.error('Error loading dashboard stats:', error);
    }
}

// Format date for display
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Format time for display
function formatTime(timeString) {
    const time = new Date('2000-01-01 ' + timeString);
    return time.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
}

// Validate employee identifier format (alphanumeric, underscore, hyphen)
function validateEmployeeId(employeeId) {
    const pattern = /^[A-Za-z0-9_-]{3,20}$/;
    return pattern.test(employeeId);
}

// Backward-compatible alias for older callers.
function validateLRN(lrn) {
    return validateEmployeeId(lrn);
}

// Validate email format
function validateEmail(email) {
    const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return pattern.test(email);
}

// Generic AJAX function
async function makeRequest(url, method = 'GET', data = null) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        };
        
        if (data && method !== 'GET') {
            if (data instanceof FormData) {
                delete options.headers['Content-Type'];
                options.body = data;
            } else {
                options.body = new URLSearchParams(data);
            }
        }
        
        const response = await fetch(url, options);
        return await response.json();
    } catch (error) {
        console.error('Request error:', error);
        throw error;
    }
}

// Debounce function for search inputs
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Print function for QR codes and reports
function printElement(elementId, title = 'Print') {
    const element = document.getElementById(elementId);
    if (!element) {
        console.error('Element not found for printing');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>${title}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 20px; 
                    color: #333;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-top: 20px;
                }
                th, td { 
                    border: 1px solid #ddd; 
                    padding: 8px; 
                    text-align: left;
                }
                th { 
                    background-color: #f2f2f2; 
                    font-weight: bold;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .qr-code {
                    text-align: center;
                    margin: 20px 0;
                }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>${title}</h1>
                <p>Generated on: ${new Date().toLocaleString()}</p>
            </div>
            ${element.innerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Export data to CSV
function exportToCSV(data, filename) {
    if (!data || data.length === 0) {
        showMessage('No data to export', 'error');
        return;
    }
    
    const headers = Object.keys(data[0]);
    const csvContent = [
        headers.join(','),
        ...data.map(row => headers.map(header => {
            const value = row[header] || '';
            return `"${value.toString().replace(/"/g, '""')}"`;
        }).join(','))
    ].join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Loading indicator functions
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = '<div class="loading"></div> Loading...';
    }
}

function hideLoading(elementId, content = '') {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = content;
    }
}

// Form validation helper
function validateForm(formId, rules) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const formData = new FormData(form);
    
    for (const [field, rule] of Object.entries(rules)) {
        const value = formData.get(field);
        const element = form.querySelector(`[name="${field}"]`);
        
        // Remove previous error styling
        if (element) {
            element.classList.remove('error');
        }
        
        // Required field validation
        if (rule.required && (!value || value.trim() === '')) {
            if (element) {
                element.classList.add('error');
                showMessage(`${rule.label || field} is required`, 'error');
            }
            isValid = false;
            continue;
        }
        
        // Pattern validation
        if (value && rule.pattern && !rule.pattern.test(value)) {
            if (element) {
                element.classList.add('error');
                showMessage(`${rule.label || field} format is invalid`, 'error');
            }
            isValid = false;
        }
        
        // Custom validation
        if (value && rule.validator && !rule.validator(value)) {
            if (element) {
                element.classList.add('error');
                showMessage(rule.message || `${rule.label || field} is invalid`, 'error');
            }
            isValid = false;
        }
    }
    
    return isValid;
}

// Initialize common functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add loading states to forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                setTimeout(() => {
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    });
    
    // Add auto-hide to alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
});

// Error handling for fetch requests
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
    showMessage('An unexpected error occurred. Please try again.', 'error');
});

// Check if browser supports required features
function checkBrowserSupport() {
    const features = {
        fetch: typeof fetch !== 'undefined',
        formData: typeof FormData !== 'undefined',
        camera: navigator.mediaDevices && navigator.mediaDevices.getUserMedia
    };
    
    const unsupported = Object.entries(features)
        .filter(([name, supported]) => !supported)
        .map(([name]) => name);
    
    if (unsupported.length > 0) {
        showMessage(
            `Your browser doesn't support: ${unsupported.join(', ')}. Some features may not work properly.`,
            'error'
        );
    }
    
    return unsupported.length === 0;
}

// Call browser support check on load
checkBrowserSupport();

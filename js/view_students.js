// View Students JavaScript Functions

// QR Code Generation
function generateQR(lrn, studentName) {
    // Check if QRCode library is available
    if (typeof QRCode === 'undefined') {
        alert('QR Code library is not loaded. Please refresh the page and try again.');
        return;
    }
    
    const studentNameElement = document.getElementById('qr-student-name');
    const qrContainer = document.getElementById('qr-code-container');
    const qrModal = document.getElementById('qr-modal');
    
    if (!studentNameElement || !qrContainer || !qrModal) {
        alert('QR modal elements not found. Please contact support.');
        return;
    }
    
    // Set student name in modal
    studentNameElement.textContent = studentName;
    
    // Clear any existing QR code to prevent duplicates
    qrContainer.innerHTML = '';
    
    // Show the modal immediately
    qrModal.style.display = 'block';
    document.body.classList.add('modal-open');
    
    try {
        // Generate QR code using davidshimjs QRCode library
        const qrCode = new QRCode(qrContainer, {
            text: lrn,
            width: 300,
            height: 300,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
        
    } catch (error) {
        console.error('QR Generation Error:', error);
        qrContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i><br>Error generating QR code</div>';
    }
}

// Close QR Modal
function closeQRModal() {
    const qrModal = document.getElementById('qr-modal');
    const qrContainer = document.getElementById('qr-code-container');
    
    if (qrModal) {
        qrModal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
    
    if (qrContainer) {
        qrContainer.innerHTML = '';
    }
}

// Print QR Code
function printQR() {
    const qrContainer = document.getElementById('qr-code-container');
    const studentName = document.getElementById('qr-student-name').textContent;
    
    if (!qrContainer) {
        alert('QR code not found. Please generate the QR code first.');
        return;
    }
    
    // The davidshimjs library creates an img element inside the container
    const qrImage = qrContainer.querySelector('img');
    
    if (!qrImage) {
        alert('QR code image not found. Please try generating the QR code again.');
        return;
    }
    
    // Get the image source (base64 data URL)
    const imageDataUrl = qrImage.src;
    
    if (!imageDataUrl) {
        alert('QR code image source not available. Please try generating the QR code again.');
        return;
    }
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    if (!printWindow) {
        alert('Pop-up blocked. Please allow pop-ups for this site to print QR codes.');
        return;
    }
    
    // Write HTML content to the print window
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
            <head>
                <title>QR Code - ${studentName}</title>
                <style>
                    body { 
                        text-align: center; 
                        font-family: 'Poppins', Arial, sans-serif;
                        margin: 40px;
                        background: white;
                        color: #333;
                    }
                    h1 {
                        color: #1ea85b;
                        margin-bottom: 10px;
                        font-size: 2em;
                    }
                    h2 { 
                        margin: 20px 0; 
                        color: #333;
                        font-weight: 500;
                        font-size: 1.5em;
                    }
                    .qr-image { 
                        margin: 30px auto;
                        border: 2px solid #ddd;
                        border-radius: 10px;
                        display: block;
                        max-width: 300px;
                        height: auto;
                    }
                    p {
                        color: #666;
                        font-size: 14px;
                        margin: 20px 0;
                    }
                    .header {
                        border-bottom: 2px solid #1ea85b;
                        padding-bottom: 20px;
                        margin-bottom: 30px;
                    }
                    .footer {
                        margin-top: 40px;
                        font-size: 12px;
                        color: #999;
                        border-top: 1px solid #eee;
                        padding-top: 20px;
                    }
                    @media print {
                        body { margin: 20px; }
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Attendance System</h1>
                    <h2>QR Code for ${studentName}</h2>
                </div>
                
                <img src="${imageDataUrl}" alt="QR Code for ${studentName}" class="qr-image" />
                
                <p><strong>Scan this code to mark attendance</strong></p>
                <p>Student Name: <strong>${studentName}</strong></p>
                
                <div class="footer">
                    <p>Generated on ${new Date().toLocaleString()}</p>
                    <p>Attendance Management System</p>
                </div>
            </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Wait for the image to load, then print and close
    const img = printWindow.document.querySelector('.qr-image');
    
    if (img.complete) {
        // Image is already loaded
        setTimeout(() => {
            printWindow.print();
            setTimeout(() => {
                printWindow.close();
            }, 1000);
        }, 500);
    } else {
        // Wait for image to load
        img.onload = function() {
            setTimeout(() => {
                printWindow.print();
                setTimeout(() => {
                    printWindow.close();
                }, 1000);
            }, 500);
        };
        
        // Fallback in case image fails to load
        img.onerror = function() {
            alert('Failed to load QR code image for printing.');
            printWindow.close();
        };
    }
}

// View Student Attendance
function viewAttendance(lrn, studentName) {
    // Open attendance report in new tab with student filter
    const url = `attendance_reports.php?lrn=${encodeURIComponent(lrn)}&student_name=${encodeURIComponent(studentName)}`;
    window.open(url, '_blank');
}

// Delete Student Confirmation Modal
let studentToDelete = null;

function confirmDeleteStudent(studentId, studentName, lrn) {
    studentToDelete = { id: studentId, name: studentName, lrn: lrn };
    
    // Update modal content
    document.getElementById('delete-student-name').textContent = studentName;
    document.getElementById('delete-student-lrn').textContent = lrn;
    
    // Show modal
    document.getElementById('delete-confirmation-modal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('delete-confirmation-modal').style.display = 'none';
    studentToDelete = null;
}

async function deleteStudent() {
    if (!studentToDelete) return;
    
    const deleteBtn = document.getElementById('confirm-delete-btn');
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    deleteBtn.disabled = true;
    
    try {
        const response = await fetch('../api/delete_student.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                student_id: studentToDelete.id
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success message
            showAlert(`${studentToDelete.name} has been successfully deleted.`, 'success');
            
            // Remove the student row from the table
            const row = document.querySelector(`tr[data-student-id="${studentToDelete.id}"]`);
            if (row) {
                row.style.animation = 'fadeOut 0.5s ease-out';
                setTimeout(() => {
                    row.remove();
                    
                    // Update student count
                    updateStudentCount(-1);
                    
                    // Check if table is empty
                    checkEmptyTable();
                }, 500);
            }
            
            closeDeleteModal();
        } else {
            showAlert(result.message || 'Failed to delete student', 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showAlert('An error occurred while deleting the student', 'error');
    } finally {
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;
    }
}

// Utility Functions
function showAlert(message, type = 'info') {
    // Create alert element
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible`;
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
        ${message}
        <button type="button" class="close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    // Insert at top of main content
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.insertBefore(alert, mainContent.firstChild);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentElement) {
                alert.remove();
            }
        }, 5000);
    }
}

function updateStudentCount(change) {
    const badge = document.querySelector('.badge');
    if (badge) {
        const currentText = badge.textContent;
        const match = currentText.match(/Total: ([\d,]+) students/);
        if (match) {
            const currentCount = parseInt(match[1].replace(/,/g, ''));
            const newCount = Math.max(0, currentCount + change);
            badge.textContent = `Total: ${newCount.toLocaleString()} students`;
        }
    }
}

function checkEmptyTable() {
    const tbody = document.querySelector('table tbody');
    if (tbody && tbody.children.length === 0) {
        const tableContainer = document.querySelector('.table-container');
        if (tableContainer) {
            tableContainer.innerHTML = `
                <div class="alert alert-info info-alert-center">
                    <i class="fas fa-info-circle"></i>
                    No students found matching your search criteria.
                    <br><br>
                    <a href="view_students.php" class="btn btn-primary">Show All Students</a>
                </div>
            `;
        }
    }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Close modals when clicking outside
    window.onclick = function(event) {
        const qrModal = document.getElementById('qr-modal');
        const deleteModal = document.getElementById('delete-confirmation-modal');
        
        if (event.target === qrModal) {
            closeQRModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    }
    
    // Add fade out animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeOut {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(-20px); }
        }
        
        .alert-dismissible {
            position: relative;
            animation: slideInDown 0.3s ease-out;
        }
        
        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .close {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
        }
        
        .close:hover {
            opacity: 1;
        }
    `;
    document.head.appendChild(style);
});

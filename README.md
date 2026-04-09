# EAT Employee Attendance System

A comprehensive web-based attendance management system using QR code scanning and employee ID identification. Built with HTML5, CSS3, JavaScript, PHP 8+, and MySQL 8.

This repository has been migrated to a strict employee-domain model (employees, departments, employee attendance) with no legacy student/section runtime contracts.

## 🌟 Features

### Core Functionality
- **Employee ID-based Employee Management**: Uses employee IDs as unique identifiers
- **Dual QR Code Scanning Systems**: 
  - Full-screen instant scanner for quick attendance marking
  - Manual attendance page with integrated QR scanner option
- **Time In/Time Out Tracking**: Precise attendance recording with entry and exit times
- **Department-based Organization**: Manage employees by department codes and assignments
- **Real-time Dashboard**: Interactive admin dashboard with live statistics and charts
- **Philippine Timezone Support**: Accurate time recording in Asia/Manila timezone

### Attendance Management
- **Time In Recording**: Capture employee arrival time with QR scan or manual entry
- **Time Out Recording**: Log employee departure time to complete attendance record
- **Automatic Status Detection**: System determines if attendance record is complete or needs attention
- **Duplicate Prevention**: Smart detection prevents multiple scans per day per employee
- **Email Notifications**: Configurable email alerts for attendance events

### Admin Dashboard
- **Real-time Statistics**: 
  - Today's total attendance count
  - Active departments count
  - Time In records
  - Total attendance records
- **Weekly Attendance Trends**: Interactive bar chart showing 7-day Present vs Absent comparison
- **Department-wise Analysis**: Donut chart displaying today's attendance distribution by department
- **Recent Activity Feed**: Live list of latest Time In/Time Out records with employee details
- **Needs Attention List**: Quick view of incomplete attendance records (missing Time Out)
- **Responsive Charts**: Built with Chart.js 4.4.0 for smooth data visualization

### Department Management
- **Dynamic Department Creation**: Add, edit, and delete departments
- **Department Metadata**: Track active/inactive department status and employee counts
- **Employee Assignment**: Assign employees to specific departments during registration
- **Department Reports**: Generate attendance reports filtered by department

### Employee Management
- **Comprehensive Registration**: Capture first name, middle name, last name, gender, work email, department, and shift
- **QR Code Generation**: Automatic unique QR code creation for each employee
- **Bulk Operations**: View, edit, search, and delete employees with admin activity logging
- **Employee Details API**: Quick lookup by Employee ID for real-time information display
- **Data Validation**: Employee ID format validation, unique email enforcement, required field checks

### Manual Attendance Interface
- **Dual Entry Modes**: 
  - Single Entry: Mark one employee at a time with Time In/Time Out
  - Bulk Entry: Mark multiple employees simultaneously
- **Integrated QR Scanner**: Built-in camera scanner using ZXing library with continuous autofocus
- **Today's Attendance View**: Real-time table showing all attendance records for current date
- **Employee Auto-complete**: Quick Employee ID lookup with automatic name and department population
- **Action Selection**: Choose between Time In, Time Out, or both for flexible marking

### Reporting & Analytics
- **Date Range Filtering**: Generate reports for specific date periods
- **Department-based Reports**: Filter attendance by specific departments
- **CSV Export**: Download attendance data in CSV format for external analysis
- **Activity Logging**: Complete audit trail of all admin actions (login, logout, add, edit, delete)
- **Status Tracking**: Monitor Present, Absent, Time In, and Time Out records

### Security & Authentication
- **Admin Login System**: Secure authentication with session management
- **Password Reset**: Email-based password recovery with token expiration
- **Activity Logging**: Track all administrative actions with IP address and timestamps
- **Role-based Access**: Support for admin, teacher, and staff roles
- **SQL Injection Protection**: Prepared statements and parameterized queries throughout

### Modern UI/UX
- **Responsive Design**: Fully mobile-friendly interface that works on all devices
- **Modern CSS Framework**: Custom design system with CSS variables and gradient themes
- **Poppins Typography**: Clean, professional font family for enhanced readability
- **Interactive Elements**: Smooth animations, hover effects, and loading states
- **Dark Mode Ready**: Color scheme prepared for future dark theme implementation
- **Toast Notifications**: Non-intrusive feedback messages for user actions

## Employee Tracker Migration (Phase 1)

Phase 1 implementation is now included as a non-breaking, additive backend foundation. The existing employee flows continue to work while employee endpoints and schema are available for parallel testing.

### What Was Added
- New migration script: `database/migrations/2026_04_08_employee_tracker_phase1.sql`
- New employee master/data tables: `departments`, `shifts`, `employees`, `employee_attendance`, `attendance_corrections`
- New employee views: `v_employee_roster`, `v_employee_daily_summary`
- New employee stored procedures: `RegisterEmployee`, `MarkEmployeeClockIn`, `MarkEmployeeClockOut`, `GetEmployeeAttendance`

### New Employee API Endpoints
- `api/register_employee.php`
- `api/get_employees.php`
- `api/get_employee_details.php`
- `api/get_departments.php`
- `api/get_shifts.php`
- `api/mark_employee_attendance.php`
- `api/list_employees.php`

### Run Phase 1
1. Apply the migration script to your MySQL database:
   ```bash
   mysql -u your_username -p your_database_name < database/migrations/2026_04_08_employee_tracker_phase1.sql
   ```
2. Keep current employee pages and endpoints unchanged.
3. Test employee endpoints with Postman or your admin AJAX layer before frontend cutover.

### Employee Mode in Existing Pages (No Redesign)
- Public scanner supports employee mode via URL parameter:
   - `scan_attendance.php?mode=employee`
- Admin manual attendance supports employee mode via URL parameter:
   - `admin/manual_attendance.php?mode=employee`
- Optional global default mode:
   - Set environment variable `ATTENDANCE_MODE=employee`
   - Supported values: `employee` (default), `employee`

### Notes
- This phase intentionally does not redesign frontend pages.
- Existing `employees` and `attendance` tables are not removed.
- The migration includes optional seed/backfill from `employees` to `employees` for pilot validation.

## 📋 Requirements

### Server Requirements
- **Web Server**: Apache 2.4+ or Nginx (XAMPP/WAMP recommended for Windows)
- **PHP**: Version 8.0 or higher
- **MySQL**: Version 8.0 or higher
- **PHP Extensions**: PDO, PDO_MySQL, GD (for QR code generation), OpenSSL (for password hashing)

### Client Requirements
- Modern web browser with camera support (Chrome, Firefox, Safari, Edge)
- HTTPS connection (required for camera access)
- JavaScript enabled
- Minimum screen resolution: 320px width (mobile-friendly)

## 🚀 Installation

### 1. Database Setup

1. Create a MySQL database named `attendance_system`:
   ```sql
   CREATE DATABASE attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Import the database structure using the latest export:
   ```bash
   mysql -u your_username -p attendance_system < database/attendance_system.sql
   ```
   
   This will create all necessary tables:
   - `employees` - Employee records with Employee ID, names, email, department
   - `departments` - Department management with grade levels and managers
   - `attendance` - Time In/Time Out records with date tracking
   - `admin_users` - Admin authentication and roles
   - `admin_activity_log` - Complete audit trail of admin actions

### 2. Configuration

1. **Database Configuration**: Edit `config/db_config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'attendance_system');
   define('DB_USER', 'your_mysql_username');
   define('DB_PASS', 'your_mysql_password');
   ```

2. **Email Configuration** (optional): Edit `config/email_config.php` for password reset:
   ```php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_USER', 'your-email@gmail.com');
   define('SMTP_PASS', 'your-app-password');
   define('SMTP_FROM', 'attendease08@gmail.com');
   ```

3. **Timezone Setting**: Verify in `config/db_config.php`:
   ```php
   date_default_timezone_set('Asia/Manila');
   ```

### 3. File Permissions

Ensure the web server has write permissions for:
```bash
chmod 755 uploads/qrcodes/
chmod 644 logs/
```

### 4. Web Server Setup

#### For XAMPP (Windows/Mac/Linux):
1. Copy the `EAT` folder to `C:\xampp\htdocs\`
2. Start Apache and MySQL from XAMPP Control Panel
3. Access via `http://localhost/EAT/`

#### For Production (Linux):
1. Copy files to `/var/www/html/acscci-attendance-checker/`
2. Configure virtual host with SSL certificate
3. Set proper file ownership: `chown -R www-data:www-data /var/www/html/acscci-attendance-checker/`

### 5. Default Admin Account

After database import, log in with:
- **Username**: `admin`
- **Password**: `admin123456` (MD5 hash: `0192023a7bbd73250516f069df18b500`)

⚠️ **SECURITY WARNING**: Change the default password immediately after first login via the admin dashboard!

## 🏫 System Logic

### Employee ID (Learner Reference Number)
- **Primary Identifier**: All employees are identified by their official 11-13 digit Employee ID (Philippines DepEd standard)
- **Format**: Numeric only (e.g., `136514240419`)
- **Validation**: System enforces 11-13 digit format during registration
- **Unique**: Each Employee ID must be unique in the system (database constraint)
- **QR Codes**: Generated automatically using the employee's Employee ID as the primary data

### Time In/Time Out System
Employee Attendance System uses a simple, flexible attendance tracking model:

1. **Time In**: Records when a employee arrives
   - Captured via QR scan or manual entry
   - Stores exact time in `HH:MM:SS` format
   - Creates attendance record for current date
   - Prevents duplicate Time In for same employee on same day

2. **Time Out**: Records when a employee leaves
   - Captured via QR scan or manual entry
   - Updates existing attendance record with departure time
   - Status changes from `time_in` to `time_out` (complete)
   - Cannot Time Out without existing Time In record

3. **Status Logic**:
   - `time_in` - Employee has arrived but not yet departed
   - `time_out` - Employee has both arrival and departure recorded (complete)
   - `present` - Legacy status for completed attendance records
   - `absent` - Manual marking for absent employees

### Department-Based Organization
- **Department Codes**: Employees are grouped by `department_code`.
- **Active/Inactive States**: Departments can be toggled without deleting historical attendance.
- **Employee Assignment**: Each employee record is linked to a department and optional shift.

### Attendance Record Uniqueness
- **One Record Per Day**: One row per employee per date in `employee_attendance`.
- **Composite Unique Key**: `(employee_id, date)` prevents duplicates.
- **Clock In/Clock Out Model**: First mark creates/updates `time_in`; second mark sets `time_out`.
- **Cascade Delete**: Removing an employee removes related attendance rows.

## 📁 File Structure

```
EAT/
├── index.php
├── scan_attendance.php
├── view_employees.php
├── admin/
│   ├── dashboard.php
│   ├── manage_employees.php
│   ├── manage_departments.php
│   ├── manual_attendance.php
│   ├── attendance_reports_departments.php
│   └── view_employees.php
├── api/
│   ├── register_employee.php
│   ├── mark_employee_attendance.php
│   ├── get_today_employee_attendance.php
│   ├── get_attendance_report_departments.php
│   ├── export_attendance_departments_csv.php
│   ├── get_employees.php
│   ├── get_employee_details.php
│   ├── delete_employee.php
│   └── regenerate_qrcode.php
├── config/
├── includes/
├── css/
│   ├── manage-employees.css
│   ├── manage-sections-modern.css
│   ├── manual-attendance-modern.css
│   └── admin-employees-mobile.css
├── js/
│   ├── main.js
│   ├── admin-login.js
│   ├── auth-carousel.js
│   └── qrcode.min.js
└── database/
   └── employee_tracker.sql
```

## Database Schema

### Core Tables

**employees**
- `employee_id` unique identifier for attendance scanning.
- `work_email`, `department_code`, `shift_code`, `work_mode`, and profile fields.

**departments**
- `department_code` and `department_name` with active status flags.

**employee_attendance**
- Daily `time_in` / `time_out` records, shift linkage, status, and source metadata.
- **UNIQUE KEY**: `(employee_id, date)`.

**admin_users**
- `id` - Auto-increment primary key
- `username` - Unique admin username
- `password` - MD5 hashed password
- `email` - Unique email for password recovery
- `reset_token`, `reset_token_expires_at` - Password reset functionality
- `role` - ENUM: admin, teacher, staff
- `is_active` - Boolean active status
- `last_login` - Last login timestamp
- `created_at` - Account creation timestamp

**admin_activity_log**
- `id` - Auto-increment primary key
- `admin_id` - Reference to admin_users.id
- `action` - Action type (LOGIN, LOGOUT, ADD_SECTION, DELETE_STUDENT, etc.)
- `details` - Detailed description of action
- `ip_address` - IP address of admin
- `created_at` - Action timestamp

### Stored Procedures

**RegisterEmployee** - Validates and registers new employees with Employee ID format checking

**MarkEmployeeClockIn** - Records employee clock in with duplicate-day protection

**MarkEmployeeClockOut** - Records employee clock out for the same attendance day

## 🔧 Usage Guide

### For Attendance Users

#### 1. Employee Registration (Admin)
1. Login to `http://localhost/EAT/admin/login.php`
2. Open **Admin → Manage Employees**
3. Enter employee profile details (Employee ID, name, work email, department, optional shift)
4. Save the record to generate the employee QR code
5. Download or print the generated QR code for attendance scanning

#### 2. Marking Attendance via QR Scan
1. Visit `http://localhost/EAT/scan_attendance.php`
2. Allow camera access when prompted
3. Hold your QR code in front of the camera
4. System instantly records your Time In or Time Out
5. View confirmation message with your details and timestamp

### For Admins

#### 1. Admin Login
1. Navigate to `http://localhost/EAT/admin/login.php`
2. Enter username and password (default: `admin` / `admin123456`)
3. Access admin dashboard with all management features

#### 2. Dashboard Overview
- View real-time statistics: Today's attendance, active departments, Time In count
- Monitor weekly attendance trends with interactive bar chart
- Check department-wise attendance distribution with donut chart
- See recent Time In/Time Out activity feed
- Identify incomplete records needing Time Out

#### 3. Managing Departments
1. Go to **Admin → Manage Departments**
2. **Add Department**: Click "Add New Department", enter department code/name and status
3. **Edit Department**: Click edit icon, update details, save changes
4. **Delete Department**: Click delete icon (warns if employees assigned)
5. View employee count per department in real-time table

#### 4. Managing Employees
1. Go to **Admin → Manage Employees**
2. **Add Employee**: Click "Add New Employee", fill registration form with Employee ID, names, email, department
3. **View Employees**: Search, filter, sort using DataTables interface
4. **Edit Employee**: Click edit icon, update information, regenerate QR code if needed
5. **Delete Employee**: Click delete icon (removes all attendance records)
6. **Print QR Codes**: Click print icon to generate printable QR code card

#### 5. Manual Attendance Entry
1. Go to **Admin → Manual Attendance**
2. **Single Entry Mode**:
   - Enter employee ID (auto-fills name and department)
   - Select date and action (Time In, Time Out, or Both)
   - Enter specific time or use current time
   - Click "Mark Attendance"
3. **Bulk Entry Mode**:
   - Select multiple employees from list
   - Choose date and action
   - Enter time for all selected employees
   - Click "Mark Bulk Attendance"
4. **QR Scanner Mode**:
   - Click "QR Scanner" tab
   - Click "Start Scanner" button
   - Scan employee QR codes directly in the interface
   - System populates Employee ID field automatically

#### 6. Generating Reports
1. Go to **Admin → Attendance Reports**
2. **Filter Options**:
   - Select date range (From Date - To Date)
   - Choose specific department or "All Departments"
   - Filter by status (All, Present, Time In, Time Out, Absent)
3. **View Report**: Click "Generate Report" to see results table
4. **Export CSV**: Click "Export to CSV" to download data
5. **Analyze**: View summary statistics (total records, present, absent, incomplete)

#### 7. Password Management
1. **Change Password**: Go to admin profile settings (if implemented)
2. **Forgot Password**:
   - Click "Forgot Password?" on login page
   - Enter registered email address
   - Check email for reset link
   - Click link and enter new password
   - Token expires after set duration for security

## 🛠️ Customization & Extension

### Adding New Departments
```sql
INSERT INTO departments (department_code, department_name, is_active) 
VALUES ('HR', 'Human Resources', 1);
```

### Bulk Employee Import
Create a PHP script to import from CSV:
```php
// Import employees from CSV file
$csv = array_map('str_getcsv', file('employees.csv'));
foreach ($csv as $row) {
    // Insert into employees table with QR generation
}
```

### Custom Attendance Rules
Edit `api/mark_employee_attendance.php` to add custom logic:
```php
// Example: Block attendance outside school hours
$current_hour = (int)date('H');
if ($current_hour < 6 || $current_hour > 18) {
    echo json_encode(['success' => false, 'message' => 'Attendance only allowed 6 AM - 6 PM']);
    exit;
}
```

### Email Notification Customization
Modify `config/email_config.php` and attendance marking logic:
- Send email on Time In
- Send email on missing Time Out after school hours
- Daily attendance summary emails to managers
- Weekly reports to employees

### Dashboard Widgets
Add custom widgets to `admin/dashboard.php`:
```javascript
// Example: Add "Late Employees" widget
const lateEmployees = data.todayAttendance.filter(record => {
    const timeIn = new Date(`2000-01-01 ${record.time_in}`);
    return timeIn.getHours() > 8; // After 8 AM = Late
});
```

### Custom Report Types
Create new report pages in `admin/` folder:
- Monthly attendance summary by department
- Employee attendance percentage rankings
- Absent employees daily report
- Department comparison analytics

## 🔍 Testing & Verification

### Quick System Test

1. **Test Employee Registration**:
   ```
   Employee ID: 136514240419
   Name: Test Employee
   Email: test@example.com
   Department: KALACHUCHI
   ```
   - Verify QR code is generated in `uploads/qrcodes/`
   - Check employee appears in Manage Employees table

2. **Test QR Scanner**:
   - Visit `scan_attendance.php`
   - Allow camera access
   - Scan generated QR code
   - Verify Time In is recorded in database
   - Scan again → should record Time Out

3. **Test Manual Attendance**:
   - Login as admin
   - Go to Manual Attendance
   - Enter test Employee ID → auto-fills employee details
   - Mark Time In → check Today's Attendance table updates
   - Mark Time Out → verify record is complete

4. **Test Dashboard**:
   - Check statistics update in real-time
   - Verify charts display correctly (Chart.js loaded)
   - Check Recent Activity shows latest records
   - Verify Needs Attention list shows incomplete records

5. **Test Reports**:
   - Generate report for today's date
   - Filter by specific department
   - Export to CSV and verify data format
   - Check summary statistics accuracy

### Timezone & Time Verification
- All times displayed in **Asia/Manila** timezone
- PHP: `date_default_timezone_set('Asia/Manila')` in `config/db_config.php`
- Database: Stores TIME and TIMESTAMP in local timezone
- JavaScript: Uses browser's local time, adjust if needed

### Browser Compatibility Testing
- ✅ **Chrome/Edge**: Full support (recommended)
- ✅ **Firefox**: Full support
- ✅ **Safari**: Full support (iOS 11+)
- ⚠️ **Camera Access**: Requires HTTPS (except localhost)

## 🔒 Security Features

### Authentication & Authorization
- **Session-based authentication** for admin area
- **Password hashing** using MD5 (upgrade to bcrypt recommended)
- **Password reset tokens** with expiration timestamps
- **Role-based access control** (admin, teacher, staff roles)
- **Logout functionality** with session destruction
- **Login attempt tracking** via activity log

### Data Protection
- **SQL injection prevention** using PDO prepared statements throughout codebase
- **XSS protection** via htmlspecialchars() on user outputs
- **CSRF protection** recommended (add tokens to forms)
- **Input validation**:
  - Employee ID format: 11-13 digits numeric only
  - Email format: RFC 5322 validation
  - Date format: YYYY-MM-DD validation
  - Required field enforcement

### Database Security
- **Foreign key constraints** with CASCADE DELETE for data integrity
- **UNIQUE constraints** on Employee ID and email to prevent duplicates
- **Stored procedures** for complex operations (RegisterEmployee, MarkEmployeeClockIn, MarkEmployeeClockOut)
- **Indexed queries** for performance and security
- **Connection encryption** (configure SSL for MySQL in production)

### File Security
- **QR code uploads** restricted to `uploads/qrcodes/` directory
- **File type validation** for QR code generation only
- **Directory listing disabled** (configure .htaccess)
- **Write permissions** only on uploads and logs directories

### Audit & Logging
- **Complete activity log** tracking all admin actions:
  - LOGIN, LOGOUT events with IP address
  - ADD_SECTION, EDIT_SECTION, DELETE_SECTION
  - DELETE_STUDENT with cascade delete count
  - MANUAL_ATTENDANCE with Employee ID and timestamp
- **IP address logging** for accountability
- **Timestamp tracking** on all database records (created_at, updated_at)

### Recommended Security Enhancements
1. **Upgrade password hashing**: Replace MD5 with `password_hash()` and `password_verify()`
   ```php
   $hash = password_hash($password, PASSWORD_BCRYPT);
   if (password_verify($input_password, $stored_hash)) { /* success */ }
   ```

2. **Add CSRF tokens**: Implement in all forms
   ```php
   $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
   ```

3. **Rate limiting**: Prevent brute force login attempts
4. **HTTPS enforcement**: Redirect HTTP to HTTPS in production
5. **Content Security Policy**: Add CSP headers
6. **Two-factor authentication**: Add 2FA for admin accounts

## 🐛 Troubleshooting

### Common Issues & Solutions

#### 1. Camera Not Working in QR Scanner
**Symptoms**: Black screen, "Camera not found" error, or permission denied

**Solutions**:
- **HTTPS Required**: Camera API requires HTTPS (except on localhost)
  ```
  Production: Use SSL certificate
  Development: http://localhost works fine
  ```
- **Browser Permissions**: Check browser settings → Site permissions → Camera → Allow
- **Browser Compatibility**: Use Chrome/Edge (recommended), Firefox, or Safari
- **Check Camera in Use**: Close other apps using camera (Zoom, Teams, etc.)
- **Mobile**: Ensure camera permission granted in phone settings

#### 2. QR Code Not Scanning
**Symptoms**: Scanner sees QR but doesn't register, or no detection

**Solutions**:
- **Lighting**: Ensure adequate lighting, avoid glare or shadows
- **Distance**: Hold QR code 15-30cm from camera
- **Focus**: Wait 1-2 seconds for camera to autofocus
- **QR Quality**: Regenerate QR code if damaged or low quality
- **Print Quality**: Use at least 300 DPI for printed QR codes
- **Check Browser Console**: Look for JavaScript errors (F12 → Console tab)

#### 3. Database Connection Failed
**Symptoms**: "Connection failed", blank pages, or 500 errors

**Solutions**:
```php
// Check config/db_config.php
define('DB_HOST', 'localhost');      // Correct host?
define('DB_NAME', 'attendance_system'); // Database exists?
define('DB_USER', 'root');           // Correct username?
define('DB_PASS', '');               // Correct password?
```
- **Verify MySQL Running**: XAMPP Control Panel → MySQL → Start
- **Test Connection**: 
  ```bash
  mysql -u root -p
  USE attendance_system;
  SHOW TABLES;
  ```
- **Check Firewall**: Allow MySQL port 3306
- **PDO Extension**: Verify `extension=pdo_mysql` in php.ini

#### 4. Admin Login Not Working
**Symptoms**: "Invalid credentials", redirect loop, or session errors

**Solutions**:
- **Default Credentials**: Username: `admin`, Password: `admin123456`
- **Check Database**: 
  ```sql
  SELECT * FROM admin_users WHERE username='admin';
  -- Password should be MD5 hash: 0192023a7bbd73250516f069df18b500
  ```
- **Session Issues**: 
  - Clear browser cache and cookies
  - Check `session_start()` in PHP files
  - Verify `session.save_path` in php.ini is writable
- **Password Reset**: Use "Forgot Password" feature or manually update:
  ```sql
  UPDATE admin_users SET password=MD5('newpassword') WHERE username='admin';
  ```

#### 5. QR Codes Not Generating
**Symptoms**: Missing QR code images, broken image links

**Solutions**:
- **Check Directory Permissions**:
  ```bash
  chmod 755 uploads/qrcodes/
  # Windows: Right-click folder → Properties → Security → Full Control
  ```
- **Verify GD Extension**: Check `extension=gd` in php.ini is enabled
- **Check File Path**: QR codes saved to `uploads/qrcodes/employee_{ID}.png`
- **Library Present**: Ensure `libs/phpqrcode.php` exists
- **Regenerate**: Use "Regenerate QR Code" button in Manage Employees

#### 6. Charts Not Displaying on Dashboard
**Symptoms**: Empty chart areas, "Chart is not defined" error

**Solutions**:
- **Check Chart.js CDN**: Verify internet connection or download locally
  ```html
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  ```
- **Browser Console**: Check for JavaScript errors (F12 → Console)
- **Data Issues**: Verify `getDashboardData()` returns valid data
- **Clear Cache**: Hard refresh (Ctrl+Shift+R / Cmd+Shift+R)

#### 7. Email Notifications Not Sending
**Symptoms**: No password reset emails, SMTP errors

**Solutions**:
- **Configure SMTP**: Edit `config/email_config.php`
  ```php
  define('SMTP_HOST', 'smtp.gmail.com');
  define('SMTP_PORT', 587);
  define('SMTP_USER', 'your-email@gmail.com');
  define('SMTP_PASS', 'app-specific-password'); // Not regular Gmail password
  ```
- **Gmail App Password**: Enable 2FA and generate app password
- **Check PHPMailer**: Verify `libs/PHPMailer/` folder exists
- **Test Email**: Check spam/junk folder
- **SMTP Logs**: Enable debug mode in PHPMailer
  ```php
  $mail->SMTPDebug = 2; // Detailed debug output
  ```

#### 8. Timezone Showing Wrong Time
**Symptoms**: Times are off by hours, wrong date displayed

**Solutions**:
- **PHP Timezone**: Verify in `config/db_config.php`
  ```php
  date_default_timezone_set('Asia/Manila');
  ```
- **MySQL Timezone**: Check and set if needed
  ```sql
  SET GLOBAL time_zone = '+08:00';
  SELECT NOW(); -- Should show Philippine time
  ```
- **Browser Time**: Check device clock is correct
- **Server Time**: Verify server timezone matches application

#### 9. Duplicate Attendance Records
**Symptoms**: Multiple Time In records for same employee same day

**Solutions**:
- **Database Constraint**: Verify UNIQUE constraint exists
   ```sql
   ALTER TABLE employee_attendance ADD UNIQUE KEY uniq_daily_employee_attendance (employee_id, date);
   ```
- **Check API Logic**: `api/mark_employee_attendance.php` should use INSERT...ON DUPLICATE KEY UPDATE
- **Clear Duplicates**: 
   ```sql
   DELETE a1 FROM employee_attendance a1
   INNER JOIN employee_attendance a2
   WHERE a1.id > a2.id AND a1.employee_id = a2.employee_id AND a1.date = a2.date;
   ```

#### 10. Slow Performance
**Symptoms**: Pages load slowly, database queries timeout

**Solutions**:
- **Add Indexes**: Ensure indexes exist on frequently queried columns
   ```sql
   CREATE INDEX idx_employee_date ON employee_attendance(employee_id, date);
   CREATE INDEX idx_attendance_shift ON employee_attendance(shift_code);
   ```
- **Optimize Queries**: Use EXPLAIN to analyze slow queries
- **Limit Results**: Add pagination to large result sets
- **Cache Results**: Implement caching for dashboard statistics
- **MySQL Tuning**: Increase `innodb_buffer_pool_size` in my.cnf

## 🚀 Future Enhancements & Roadmap

### Phase 1: Core Improvements (Short-term)
- [ ] **Password Security Upgrade**: Replace MD5 with bcrypt/Argon2
- [ ] **CSRF Protection**: Add tokens to all forms
- [ ] **Rate Limiting**: Prevent brute force attacks on login
- [ ] **Advanced Search**: Full-text search for employees with autocomplete
- [ ] **Bulk QR Print**: Generate PDF with multiple QR codes per page
- [ ] **Data Validation**: Enhanced client-side and server-side validation

### Phase 2: Feature Extensions (Mid-term)
- [ ] **Parent Portal**: Employees can view their child's attendance history
- [ ] **SMS Notifications**: Send SMS alerts for attendance events
- [ ] **Attendance Scheduling**: Set required attendance days/times per department
- [ ] **Holiday Management**: Mark holidays and exclude from reports
- [ ] **Late Threshold**: Configurable late time per department
- [ ] **Excuse Management**: Track excused absences with reason codes
- [ ] **Multi-school Support**: Tenant isolation for multiple schools

### Phase 3: Advanced Features (Long-term)
- [ ] **Mobile Apps**: Native iOS/Android apps with offline support
- [ ] **Biometric Integration**: Fingerprint and face recognition options
- [ ] **AI Analytics**: Predictive analytics for attendance patterns
- [ ] **API Gateway**: RESTful API for third-party integrations
- [ ] **Real-time Dashboard**: WebSocket-based live updates
- [ ] **Geofencing**: Location-based attendance (on-campus verification)
- [ ] **Voice Commands**: Voice-activated attendance marking
- [ ] **Blockchain Ledger**: Immutable attendance records

### Integration Possibilities
- **Google Classroom**: Sync attendance with Google Classroom
- **Canvas LMS**: Export attendance to Canvas gradebook
- **PowerSchool**: Bidirectional sync with PowerSchool SIS
- **Microsoft Teams**: Attendance bot for online classes
- **Zapier**: Automate workflows with 3000+ apps

### Analytics & Reporting Enhancements
- **Attendance Percentage Rankings**: Leaderboard of best attendance
- **Trend Analysis**: Identify patterns (Monday absences, etc.)
- **Cohort Analysis**: Compare attendance across departments/grades
- **Export Formats**: Excel, PDF, JSON, XML options
- **Automated Reports**: Schedule daily/weekly email reports
- **Visualization**: Heat maps, trend lines, pie charts

### Performance Optimizations
- **Redis Caching**: Cache frequent queries for faster load times
- **CDN Integration**: Serve static assets from CDN
- **Database Sharding**: Horizontal scaling for large deployments
- **Load Balancing**: Distribute traffic across multiple servers
- **Progressive Web App**: Installable PWA with offline capabilities

## 🤝 Contributing

We welcome contributions to improve the Employee Attendance System! Here's how you can help:

### Bug Reports
- Check existing issues before creating new ones
- Include steps to reproduce the bug
- Provide error messages and screenshots
- Specify your environment (PHP version, browser, OS)

### Feature Requests
- Describe the feature and its benefits
- Explain use cases and expected behavior
- Consider backward compatibility

### Pull Requests
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request with detailed description

### Development Guidelines
- Follow PSR-12 coding standards for PHP
- Use meaningful variable and function names
- Comment complex logic blocks
- Test thoroughly before submitting
- Update README.md if adding new features

## 📞 Support & Contact

### Documentation
- **README**: You're reading it! Check troubleshooting department first
- **Code Comments**: Inline documentation in all major files
- **Database Schema**: See `database/attendance_system.sql` for structure

### Community
- **GitHub Issues**: Report bugs and request features
- **GitHub Discussions**: Ask questions and share ideas
- **Email**: attendease08@gmail.com (official support)

### Professional Support
For custom development, training, or deployment assistance:
- Custom features and integrations
- On-site training for staff
- Cloud deployment and maintenance
- Multi-school enterprise setup

## 📄 License

This project is developed for educational purposes and is open-source.

**Terms of Use**:
- ✅ Free to use for educational institutions
- ✅ Modify and customize for your needs
- ✅ Deploy in your school/organization
- ❌ Do not sell as a commercial product without permission
- ❌ Do not remove attribution or credits

**Attribution**: Please retain credits and link back to the original repository.

## 🙏 Acknowledgments

### Technologies Used
- **PHP 8+**: Server-side programming
- **MySQL 8**: Relational database management
- **Chart.js 4.4.0**: Interactive data visualization
- **ZXing Library**: QR code scanning (BrowserQRCodeReader)
- **PHPQRCode**: Server-side QR code generation
- **PHPMailer**: Email functionality
- **DataTables**: Advanced table features
- **Bootstrap Icons**: UI icons
- **Google Fonts (Poppins)**: Typography

### Inspiration & Credits
- Built for educational institutions in the Philippines
- Designed to comply with DepEd (Department of Education) Employee ID standards
- Inspired by modern attendance management systems
- Special thanks to the open-source community

### Project Information
- **Project Name**: Academy of St. Joseph Claveria, Cagayan Inc. Attendance Checker
- **Institution**: Academy of St. Joseph Claveria, Cagayan Inc.
- **Repository**: Academy-of-St.Joseph-Claveria-Cagayan-Inc.-Attendance-Checker
- **Owner**: TsmHabib03
- **Current Version**: 2.0 (Time In/Time Out System)
- **Last Updated**: November 7, 2025
- **License**: Educational Open Source
- **Research Purpose**: Developed as part of a research study on modernizing attendance management systems in educational institutions

---

## 📊 Quick Start Summary

```bash
# 1. Import database
mysql -u root -p < database/attendance_system.sql

# 2. Configure database
nano config/db_config.php  # Update credentials

# 3. Set permissions
chmod 755 uploads/qrcodes/

# 4. Access application
http://localhost/EAT/

# 5. Admin login
Username: admin
Password: admin123456
```

---

## 🎓 Research Context

This attendance management system was developed as a research project for the Academy of St. Joseph Claveria, Cagayan Inc. The research aims to:

- **Modernize Attendance Tracking**: Replace traditional manual attendance methods with automated QR code technology
- **Improve Accuracy**: Eliminate human error in attendance recording and reporting
- **Save Time**: Reduce time spent on attendance marking and report generation
- **Enhance Data Analysis**: Provide comprehensive analytics and insights into attendance patterns
- **Increase Security**: Ensure accurate identification through unique Employee ID-based QR codes
- **Support Decision Making**: Provide administrators with real-time data for informed decisions

### Technology Stack Overview

**Frontend Technologies:**
- HTML5, CSS3, JavaScript (ES6+)
- Chart.js 4.4.0 for data visualization
- ZXing library for QR code scanning
- Responsive design with mobile-first approach

**Backend Technologies:**
- PHP 8+ with PDO for database operations
- MySQL 8.0 with InnoDB engine
- PHPMailer for email notifications
- PHPQRCode for QR generation

**Security Features:**
- Prepared statements to prevent SQL injection
- Session-based authentication
- Password hashing and reset functionality
- Activity logging and audit trails

**Key Features for Research:**
- Real-time attendance monitoring
- Comprehensive reporting and analytics
- Department-based organization
- Time In/Time Out tracking
- Automated notifications
- Data export capabilities (CSV)

---

**📚 Academy of St. Joseph Cleveria, Cagayan Inc. - Attendance Management System**

For questions, issues, or contributions related to this research project, please visit the GitHub repository or contact support.

---
## 💡 Key Concepts & Technology Stack

### Frontend Technologies
- **HTML5**: Semantic markup, forms, media elements for structured content
- **CSS3**: Modern design system with CSS variables, gradients, and animations
- **JavaScript (ES6+)**: Async/await, fetch API, DOM manipulation for dynamic interactions
- **Chart.js 4.4.0**: Interactive bar and donut charts for attendance analytics and data visualization
- **ZXing Library**: Browser-based QR code scanning with continuous autofocus for real-time attendance marking
- **DataTables**: Advanced table features including search, sort, and pagination for managing large datasets
- **Responsive Design**: Mobile-first approach using flexbox and grid for cross-device compatibility

### Backend Technologies
- **PHP 8+**: Modern PHP with type declarations, named arguments, and improved error handling
- **PDO (PHP Data Objects)**: Database abstraction layer with prepared statements for security
- **MySQLi**: Alternative database interface for specific database operations
- **PHPMailer**: SMTP email sending library for password resets and notifications
- **PHPQRCode**: Server-side QR code image generation using GD library
- **Session Management**: Secure admin authentication with session lifecycle management

### Database Architecture
- **MySQL 8.0**: Relational database management system with InnoDB storage engine
- **Stored Procedures**: MarkEmployeeClockIn, MarkEmployeeClockOut, RegisterEmployee for business logic encapsulation
- **Foreign Keys**: CASCADE DELETE relationships for maintaining data integrity
- **Database Indexes**: Optimized queries on employee_id, date, and department columns for fast retrieval
- **UNIQUE Constraints**: Prevent duplicate attendance records using composite keys (employee_id, date)
- **ENUM Data Types**: Status fields with predefined values for data consistency
- **Triggers**: Automatic timestamp updates for data auditing

### System Architecture
- **MVC-like Structure**: Clear separation of concerns between views, business logic, and data access
- **RESTful API Design**: JSON-based APIs for frontend-backend communication
- **Database Abstraction Layer**: Centralized `Database` class for all database connections
- **Configuration Management**: Environment-specific settings in dedicated config files
- **Error Handling Strategy**: Try-catch blocks with user-friendly error messages and logging
- **Activity Logging**: Complete audit trail of all administrative actions with timestamps

### Security Implementation
1. **Input Validation**: Multi-layer validation (client-side JavaScript and server-side PHP)
2. **SQL Injection Prevention**: Exclusively using prepared statements with parameterized queries
3. **XSS Protection**: Output escaping using htmlspecialchars() on all user-generated content
4. **Session Security**: Session regeneration, timeout mechanisms, and secure cookie flags
5. **Access Control**: Role-based access control (RBAC) with admin authentication checks
6. **Password Security**: MD5 hashing (upgradeable to bcrypt/Argon2 for enhanced security)
7. **CSRF Protection**: Token-based validation for state-changing operations
8. **Audit Logging**: Comprehensive tracking of all administrative actions with IP addresses and timestamps

### Performance Optimization
- **Database Indexing**: Strategic indexes on frequently queried columns
- **Query Optimization**: Efficient JOIN operations and subquery usage
- **Caching Strategy**: Session-based caching for frequently accessed data
- **Asset Optimization**: Minified CSS and JavaScript files
- **CDN Integration**: External libraries loaded from CDNs for faster delivery

## 📚 Research and Learning Outcomes

### Academic Learning Objectives

This attendance management system serves as a comprehensive case study for understanding modern web application development and its application in educational technology. Researchers and employees can gain insights into:

**Database Design & Management**:
- Relational database schema design and entity-relationship modeling
- Foreign key relationships and referential integrity with CASCADE operations
- Indexing strategies for query performance optimization
- Stored procedures for encapsulating business logic
- Data normalization principles (1NF, 2NF, 3NF)
- Database constraints and data integrity enforcement

**Backend Development with PHP**:
- PDO (PHP Data Objects) and prepared statements for secure database access
- Session management and authentication mechanisms
- File system operations and image generation
- SMTP email sending for automated notifications
- Comprehensive error handling and logging strategies
- RESTful API endpoint development and best practices

**Frontend Development**:
- HTML5 Camera API and media stream manipulation
- QR code scanning implementation using ZXing library
- Asynchronous JavaScript with Fetch API and Promises
- Data visualization with Chart.js for attendance analytics
- DOM manipulation and event-driven programming
- Client-side form validation and user experience design

**Web Application Security**:
- SQL injection prevention through parameterized queries
- Cross-site scripting (XSS) mitigation techniques
- Authentication and authorization implementation
- Password hashing algorithms (MD5, bcrypt, Argon2)
- CSRF (Cross-Site Request Forgery) protection concepts
- Secure session management and cookie handling
- Input validation and sanitization strategies

**Full-Stack Integration**:
- Frontend-backend communication patterns
- RESTful API design and JSON data interchange
- File system operations and upload handling
- Database transactions and ACID properties
- Real-time data updates and dynamic content rendering
- Responsive web design principles and mobile-first approach

**Software Engineering Practices**:
- Code organization and modular architecture
- Version control with Git and GitHub
- Documentation and code commenting standards
- Testing strategies and quality assurance
- Deployment procedures and server configuration
- Maintenance and troubleshooting methodologies

### Research Applications

**Educational Technology Research**:
- Effectiveness of QR code-based attendance systems
- Time efficiency comparison: manual vs. automated attendance
- Accuracy and reliability of attendance data collection
- User acceptance and adoption of digital attendance solutions
- Impact on administrative workload reduction

**Data Analytics Research**:
- Attendance pattern analysis and trend identification
- Predictive modeling for employee attendance behavior
- Correlation studies between attendance and academic performance
- Visualization techniques for educational data
- Report generation and data export methodologies

**System Performance Studies**:
- Database query optimization and response time analysis
- Scalability testing for large employee populations
- Network latency and QR code scanning speed evaluation
- Cross-browser and cross-device compatibility assessment
- Security vulnerability testing and penetration analysis

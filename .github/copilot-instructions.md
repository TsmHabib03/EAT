# San Francisco High School — Copilot Instructions

## Architecture

XAMPP-hosted PHP 8+ / MySQL 8 attendance system with no framework — procedural PHP, vanilla JS, PDO for database access. Runs on `Asia/Manila` timezone (set in PHP and MySQL `+08:00`).

**Directory layout:**
- `api/` — JSON endpoints (stateless, one file per action)
- `admin/` — admin pages (hybrid: same file handles AJAX POST → JSON and GET → HTML)
- `admin/includes/` — admin layout (`header_modern.php` sidebar/topbar, `footer_modern.php`)
- `includes/` — shared PHP (`database.php` Database class, `navigation.php`, `qrcode_helper.php`)
- `config/` — `db_config.php` (DB creds + PDO), `email_config.php` (PHPMailer/Gmail SMTP)
- `js/`, `css/` — frontend assets at project root
- `database/attendeasev2.sql` — full schema with stored procedures and views

## Database

**Key tables:** `students` (LRN as unique ID), `attendance` (unique on `lrn+date`), `sections`, `admin_users`, `admin_activity_log`.
- `students.lrn` → `attendance.lrn` (CASCADE). Students link to sections via text `class`/`section` columns, not FK.
- Stored procs: `MarkTimeIn()`, `MarkTimeOut()`, `GetStudentAttendance()`, `RegisterStudent()`.
- Views: `v_daily_attendance_summary`, `v_student_roster`.

## API Pattern

Every `api/*.php` file returns `Content-Type: application/json` with `{"success": true/false, "message": "..."}`. Follow this exact pattern:

```php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0); ini_set('display_errors', 0);
ob_start(); // prevent stray output corrupting JSON
require_once '../includes/database.php';
$database = new Database();
$db = $database->getConnection();
// ... logic with prepared statements ...
ob_end_clean();
echo json_encode(['success' => true, 'message' => '...']);
```

Use dual catch: `PDOException` then `Exception`. Always use `ob_start()`/`ob_end_clean()` before `echo json_encode()`.

## Admin Page Pattern

Every `admin/*.php` page follows this boilerplate:

```php
require_once 'config.php';        // session, DB, helpers
requireAdmin();                    // auth check + 1hr timeout
$currentAdmin = getCurrentAdmin();
$pageTitle = 'Page Name';
$pageIcon = 'fa-icon-name';
// optional: $additionalCSS = ['../css/page-styles.css?v=' . time()];
include 'includes/header_modern.php';
// ... HTML content ...
include 'includes/footer_modern.php';
```

AJAX requests within admin pages are detected via `$_SERVER['HTTP_X_REQUESTED_WITH'] === 'xmlhttprequest'` — same PHP file returns JSON for AJAX, HTML for normal requests.

## Frontend JS

- Vanilla JS only (no jQuery). Use `fetch()` with `async/await`.
- `js/main.js` provides `makeRequest(url, method, data)` helper — sends `application/x-www-form-urlencoded` by default, auto-switches for `FormData`.
- DOM manipulation via `getElementById` / `querySelector` and template literals for HTML injection.
- Notifications via `showNotification()` / `showMessage()` functions.
- Chart.js 4.4.0 for dashboard charts. QRCode.js for client-side QR rendering.

## CSS Theming

Two variable systems — both use CSS custom properties, no preprocessor:
- `css/asj-theme.css` — public pages, green/gold palette (`--asj-green-primary: #4CAF50`)
- `css/modern-design.css` — admin/scan pages, numeric scale (`--primary-50` through `--primary-900`)
- Admin header re-declares variables inline to enforce green branding over blue defaults.
- Font: **Poppins** (Google Fonts). Icons: **Font Awesome 6.4**.
- Per-page CSS loaded via `$additionalCSS` array, cache-busted with `?v=' . time()`.

## QR Code Flow

- QR codes encode the student **LRN as plain text**.
- Server-side generation: `includes/qrcode_helper.php` calls `api.qrserver.com`, falls back to GD placeholder. Saved to `uploads/qrcodes/`.
- Client-side rendering: `QRCode.toCanvas()` or `new QRCode()` depending on page.
- Scanning: `scan_attendance.php` uses HTML5 camera → extracts LRN → POSTs to `api/mark_attendance.php`.
- Attendance logic: 1st scan/day = Time In, 2nd = Time Out, 3rd+ = rejected. Uses `SELECT ... FOR UPDATE` for concurrency.

## Naming Conventions

- PHP/JS files: `snake_case` (e.g., `mark_attendance.php`, `view_students.js`)
- CSS files: `kebab-case` (e.g., `manage-students.css`, `modern-design.css`)
- DB columns: `snake_case`. PDO bindings: mix of named (`:param`) and positional (`?`) — prefer named for new code.

## Security Notes

- Auth: session-based (`$_SESSION['admin_logged_in']`). APIs requiring auth must check session manually.
- CSRF: `generateCSRFToken()` / `verifyCSRFToken()` available in `admin/config.php` — use for state-changing forms.
- Passwords: currently MD5 (legacy). Use `password_hash()`/`password_verify()` for any new auth code.
- All DB queries must use PDO prepared statements. Use `sanitizeOutput()` for HTML output.

<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'hayaandb10');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ========== 1. CREATE ALL TABLES ==========
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0.00,
    lessons INT DEFAULT 0,
    duration VARCHAR(50),
    level VARCHAR(100),
    instructor VARCHAR(255),
    rating DECIMAL(3,2),
    students INT DEFAULT 0,
    image VARCHAR(500),
    category VARCHAR(100)
)");
$conn->query("CREATE TABLE IF NOT EXISTS payment_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NULL,
    course_ids TEXT NULL,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
)");
$check = $conn->query("SHOW COLUMNS FROM payment_requests LIKE 'course_ids'");
if ($check && $check->num_rows == 0) $conn->query("ALTER TABLE payment_requests ADD COLUMN course_ids TEXT NULL AFTER course_id");
$conn->query("ALTER TABLE payment_requests MODIFY course_id INT NULL");

$conn->query("CREATE TABLE IF NOT EXISTS admin_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    admin_reply TEXT DEFAULT NULL,
    status ENUM('unread','read','replied') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
$conn->query("CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open','pending','resolved','closed') DEFAULT 'open',
    admin_reply TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
$conn->query("CREATE TABLE IF NOT EXISTS course_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    embed_url VARCHAR(500) NOT NULL,
    video_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
)");
$conn->query("CREATE TABLE IF NOT EXISTS video_pdfs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES course_videos(id) ON DELETE CASCADE
)");
$conn->query("CREATE TABLE IF NOT EXISTS video_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    reaction_type ENUM('like','love','none') DEFAULT 'none',
    admin_reply TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES course_videos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// ========== 2. DEFAULT COURSES (if empty) ==========
$check_courses = $conn->query("SELECT COUNT(*) as cnt FROM courses")->fetch_assoc()['cnt'];
if ($check_courses == 0) {
    $default_courses = [
        [1,'Web Development Bootcamp 2024','Learn HTML, CSS, JavaScript, React and Node.js.',49.99,12,'20 Hours','Beginner','Ahmed Mohamed',4.8,15420,'https://images.unsplash.com/photo-1627398242454-45a1465c2479?w=400','Web Development'],
        [2,'Complete Python Masterclass','Master Python for data science, automation, and web.',39.99,10,'15 Hours','All Levels','Qualified Expert',4.9,23450,'https://images.unsplash.com/photo-1555949963-aa79dcee981c?w=400','Data Science'],
        [3,'Database Design & SQL','Learn SQL, MySQL, and database design.',34.99,8,'12 Hours','Intermediate','Omar Hassan',4.7,12340,'https://images.unsplash.com/photo-1542744095-fcf48d80b0fd?w=400','Database'],
        [4,'Business Fundamentals','Essential business skills for entrepreneurs.',29.99,6,'10 Hours','Beginner','Hawa Ahmed',4.5,8760,'https://images.unsplash.com/photo-1556761175-b413da4baf72?w=400','Business'],
        [5,'Digital Marketing Pro','Master SEO, social media, email marketing.',24.99,5,'8 Hours','All Levels','Khadar Yusuf',4.6,10980,'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=400','Digital Marketing'],
        [6,'Graphic Design Masterclass','Learn design principles, Photoshop, Illustrator.',44.99,7,'14 Hours','Beginner to Advanced','Naima Abdi',4.8,15430,'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=400','Graphic Design']
    ];
    $stmt = $conn->prepare("INSERT INTO courses (id, name, description, price, lessons, duration, level, instructor, rating, students, image, category) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($default_courses as $c) {
        $stmt->bind_param("issdssssdiss", $c[0],$c[1],$c[2],$c[3],$c[4],$c[5],$c[6],$c[7],$c[8],$c[9],$c[10],$c[11]);
        $stmt->execute();
    }
}

// ========== 3. FETCH COURSES FROM DATABASE ==========
$course_list = [];
$res_courses = $conn->query("SELECT id, name, price, category, image FROM courses");
while($row = $res_courses->fetch_assoc()) $course_list[$row['id']] = $row;

// Fetch courses with first video URL for display in courses section
$courses_with_video_link = [];
$all_courses_data = $conn->query("SELECT c.*, (SELECT embed_url FROM course_videos WHERE course_id = c.id ORDER BY video_order LIMIT 1) as first_video_url FROM courses c ORDER BY c.id");
while($crs = $all_courses_data->fetch_assoc()) {
    $courses_with_video_link[] = $crs;
}

function getCourseNames($row, $course_list) {
    if (!empty($row['course_ids'])) {
        $ids = explode(',', $row['course_ids']);
        $names = [];
        foreach ($ids as $id) {
            if (isset($course_list[$id])) $names[] = $course_list[$id]['name'];
            else $names[] = "Course ID $id";
        }
        return implode(', ', $names);
    } elseif (!empty($row['course_id']) && isset($course_list[$row['course_id']])) {
        return $course_list[$row['course_id']]['name'];
    }
    return "Unknown Course";
}

// ========== 4. LOGIN (slideshow) ==========
$admin_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!$admin_logged_in && isset($_POST['admin_login'])) {
    if ($_POST['admin_user'] === 'admin' && $_POST['admin_pass'] === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $admin_logged_in = true;
    } else $login_error = "Invalid credentials";
}
if (!$admin_logged_in) {
    ?><!DOCTYPE html><html><head><title>Admin Login - Salaam Online</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;min-height:100vh;position:relative;overflow:hidden}.slideshow{position:fixed;top:0;left:0;width:100%;height:100%;z-index:-1}.slideshow img{position:absolute;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity 1.5s ease-in-out}.slideshow img.active{opacity:1}.overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:0}.login-container{position:relative;z-index:2;background:rgba(255,255,255,0.95);backdrop-filter:blur(12px);border-radius:2rem;padding:2.5rem;width:420px;box-shadow:0 25px 45px rgba(0,0,0,0.3);margin:10vh auto;text-align:center}.login-header i{font-size:3rem;color:#2563eb;background:white;padding:1rem;border-radius:50%}.input-group{position:relative;margin-bottom:1.5rem}.input-group i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#94a3b8}.input-group input{width:100%;padding:0.9rem 1rem 0.9rem 2.8rem;border:1px solid #e2e8f0;border-radius:1rem;font-size:1rem}.btn{width:100%;padding:0.9rem;background:linear-gradient(135deg,#2563eb,#06b6d4);color:white;border:none;border-radius:1rem;font-weight:600;cursor:pointer}.error{background:#fee2e2;color:#dc2626;padding:0.75rem;border-radius:0.75rem;margin-bottom:1rem}</style></head><body><div class="slideshow"><img src="https://images.unsplash.com/photo-1498050108023-c5249f4df085?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" class="active"><img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80"><img src="https://images.unsplash.com/photo-1519389950473-47ba0277781c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80"></div><div class="overlay"></div><div class="login-container"><div class="login-header"><i class="fas fa-user-shield"></i><h2>Salaam Online Admin</h2><p>Admin Portal</p></div><?php if(isset($login_error)) echo "<div class='error'>$login_error</div>"; ?><form method="POST"><div class="input-group"><i class="fas fa-user"></i><input type="text" name="admin_user" placeholder="Username" required></div><div class="input-group"><i class="fas fa-lock"></i><input type="password" name="admin_pass" placeholder="Password" required></div><button type="submit" name="admin_login" class="btn">Login</button></form><p style="margin-top:1.5rem;">admin / admin123</p></div><script>let slides=document.querySelectorAll('.slideshow img'),current=0;setInterval(()=>{slides[current].classList.remove('active');current=(current+1)%slides.length;slides[current].classList.add('active');},4000);</script></body></html><?php exit;
}

// ========== 5. HANDLE POST ACTIONS + AJAX for user details ==========
$auto_approve_msg = "✅ Lacagta waa la aqbalay. Course-ka waa la furay, hadda waxaad ka heli kartaa qaybta 'My Courses'. Mahadsanid!";
$auto_reject_msg = "❌ Waan ka xunahay, macluumaadka transaction-ka ma saxna ama wax ka maqan. Fadlan dib u soo gudbi macluumaad sax ah.";

// AJAX handler for user details (used in Reports)
if (isset($_GET['action']) && $_GET['action'] === 'get_user_details' && isset($_GET['user_id'])) {
    header('Content-Type: application/json');
    $user_id = intval($_GET['user_id']);
    $user = $conn->query("SELECT id, username, full_name, email, created_at FROM users WHERE id=$user_id")->fetch_assoc();
    if (!$user) { echo json_encode(['error' => 'User not found']); exit; }
    // Get all approved payments for this user
    $payments = $conn->query("SELECT * FROM payment_requests WHERE user_id=$user_id AND status='approved' ORDER BY created_at DESC");
    $payment_list = [];
    while ($p = $payments->fetch_assoc()) {
        $courses = getCourseNames($p, $course_list);
        $payment_list[] = [
            'id' => $p['id'],
            'transaction_id' => $p['transaction_id'],
            'amount' => $p['amount'],
            'courses' => $courses,
            'date' => $p['created_at']
        ];
    }
    $user['payments'] = $payment_list;
    echo json_encode($user);
    exit;
}

// ========== PDF EXPORT HANDLERS (nashqadeysan) ==========
if (isset($_POST['export_users_pdf'])) {
    $users = $conn->query("SELECT id, username, full_name, email, created_at FROM users ORDER BY created_at DESC");
    $html = '<!DOCTYPE html>
<html>
<head>
    <title>Users Report - Salaam Online</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f0f7ff; padding: 30px; color: #1e293b; }
        .report-wrapper { max-width: 1100px; margin: 0 auto; background: #ffffff; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.08); padding: 30px 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #2563eb; padding-bottom: 20px; margin-bottom: 25px; }
        .header h1 { font-size: 28px; font-weight: 700; background: linear-gradient(135deg, #020024, #090979, #00D4FF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header .brand { display: flex; align-items: center; gap: 12px; }
        .header .brand i { font-size: 36px; color: #2563eb; background: #e0f2fe; padding: 12px; border-radius: 50%; }
        .header .date { color: #64748b; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        th { background: linear-gradient(135deg, #2563eb, #06b6d4); color: white; padding: 14px 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        tr:hover { background: #e0f2fe; }
        .footer { margin-top: 30px; text-align: center; font-size: 13px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; }
        .footer span { color: #2563eb; font-weight: 600; }
        .close-btn { display: inline-block; background: #ef4444; color: #fff; padding: 8px 24px; border-radius: 30px; text-decoration: none; font-weight: 600; margin-bottom: 20px; border: none; cursor: pointer; font-size: 14px; }
        .close-btn:hover { background: #dc2626; }
        .actions { text-align: right; }
        @media print { .close-btn { display: none; } }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="report-wrapper">
    <div class="actions"><button class="close-btn" onclick="window.close();"><i class="fas fa-times-circle"></i> Close / Cancel</button></div>
    <div class="header">
        <div class="brand">
            <i class="fas fa-graduation-cap"></i>
            <h1>Salaam Online</h1>
        </div>
        <div class="date">Generated: ' . date('Y-m-d H:i:s') . '</div>
    </div>
    <h2 style="margin-bottom: 15px; color: #090979;"><i class="fas fa-users"></i> Users Report</h2>
    <table>
        <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Registered</th></tr></thead>
        <tbody>';
    while ($u = $users->fetch_assoc()) {
        $html .= '<tr><td>' . $u['id'] . '</td><td>' . htmlspecialchars($u['username']) . '</td><td>' . htmlspecialchars($u['full_name']) . '</td><td>' . htmlspecialchars($u['email']) . '</td><td>' . $u['created_at'] . '</td></tr>';
    }
    $html .= '</tbody></table>
    <div class="footer">Report generated on ' . date('Y-m-d H:i:s') . ' &bull; <span>Salaam Online</span></div>
</div>
<script>window.onload=function(){ if(window.print) window.print(); };</script>
</body></html>';
    echo $html;
    exit;
}
if (isset($_POST['export_payments_pdf'])) {
    $payments = $conn->query("SELECT pr.*, u.username FROM payment_requests pr JOIN users u ON pr.user_id=u.id WHERE pr.status='approved' ORDER BY pr.created_at DESC");
    $html = '<!DOCTYPE html>
<html>
<head>
    <title>Payments Report - Salaam Online</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f0f7ff; padding: 30px; color: #1e293b; }
        .report-wrapper { max-width: 1100px; margin: 0 auto; background: #ffffff; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.08); padding: 30px 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #2563eb; padding-bottom: 20px; margin-bottom: 25px; }
        .header h1 { font-size: 28px; font-weight: 700; background: linear-gradient(135deg, #020024, #090979, #00D4FF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header .brand { display: flex; align-items: center; gap: 12px; }
        .header .brand i { font-size: 36px; color: #2563eb; background: #e0f2fe; padding: 12px; border-radius: 50%; }
        .header .date { color: #64748b; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        th { background: linear-gradient(135deg, #2563eb, #06b6d4); color: white; padding: 14px 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        tr:hover { background: #e0f2fe; }
        .footer { margin-top: 30px; text-align: center; font-size: 13px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; }
        .footer span { color: #2563eb; font-weight: 600; }
        .close-btn { display: inline-block; background: #ef4444; color: #fff; padding: 8px 24px; border-radius: 30px; text-decoration: none; font-weight: 600; margin-bottom: 20px; border: none; cursor: pointer; font-size: 14px; }
        .close-btn:hover { background: #dc2626; }
        .actions { text-align: right; }
        @media print { .close-btn { display: none; } }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="report-wrapper">
    <div class="actions"><button class="close-btn" onclick="window.close();"><i class="fas fa-times-circle"></i> Close / Cancel</button></div>
    <div class="header">
        <div class="brand">
            <i class="fas fa-graduation-cap"></i>
            <h1>Salaam Online</h1>
        </div>
        <div class="date">Generated: ' . date('Y-m-d H:i:s') . '</div>
    </div>
    <h2 style="margin-bottom: 15px; color: #090979;"><i class="fas fa-credit-card"></i> Approved Payments Report</h2>
    <table>
        <thead><tr><th>ID</th><th>User</th><th>Course(s)</th><th>Amount</th><th>Transaction ID</th><th>Date</th></tr></thead>
        <tbody>';
    while ($p = $payments->fetch_assoc()) {
        $courses = getCourseNames($p, $course_list);
        $html .= '<tr><td>' . $p['id'] . '</td><td>' . htmlspecialchars($p['username']) . '</td><td>' . htmlspecialchars($courses) . '</td><td>$' . number_format($p['amount'],2) . '</td><td>' . htmlspecialchars($p['transaction_id']) . '</td><td>' . $p['created_at'] . '</td></tr>';
    }
    $html .= '</tbody></table>
    <div class="footer">Report generated on ' . date('Y-m-d H:i:s') . ' &bull; <span>Salaam Online</span></div>
</div>
<script>window.onload=function(){ if(window.print) window.print(); };</script>
</body></html>';
    echo $html;
    exit;
}
if (isset($_POST['export_tickets_pdf'])) {
    $tickets = $conn->query("SELECT t.*, u.username FROM support_tickets t JOIN users u ON t.user_id=u.id ORDER BY t.created_at DESC");
    $html = '<!DOCTYPE html>
<html>
<head>
    <title>Tickets Report - Salaam Online</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f0f7ff; padding: 30px; color: #1e293b; }
        .report-wrapper { max-width: 1100px; margin: 0 auto; background: #ffffff; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.08); padding: 30px 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #2563eb; padding-bottom: 20px; margin-bottom: 25px; }
        .header h1 { font-size: 28px; font-weight: 700; background: linear-gradient(135deg, #020024, #090979, #00D4FF); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header .brand { display: flex; align-items: center; gap: 12px; }
        .header .brand i { font-size: 36px; color: #2563eb; background: #e0f2fe; padding: 12px; border-radius: 50%; }
        .header .date { color: #64748b; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        th { background: linear-gradient(135deg, #2563eb, #06b6d4); color: white; padding: 14px 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        tr:hover { background: #e0f2fe; }
        .footer { margin-top: 30px; text-align: center; font-size: 13px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; }
        .footer span { color: #2563eb; font-weight: 600; }
        .close-btn { display: inline-block; background: #ef4444; color: #fff; padding: 8px 24px; border-radius: 30px; text-decoration: none; font-weight: 600; margin-bottom: 20px; border: none; cursor: pointer; font-size: 14px; }
        .close-btn:hover { background: #dc2626; }
        .actions { text-align: right; }
        @media print { .close-btn { display: none; } }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="report-wrapper">
    <div class="actions"><button class="close-btn" onclick="window.close();"><i class="fas fa-times-circle"></i> Close / Cancel</button></div>
    <div class="header">
        <div class="brand">
            <i class="fas fa-graduation-cap"></i>
            <h1>Salaam Online</h1>
        </div>
        <div class="date">Generated: ' . date('Y-m-d H:i:s') . '</div>
    </div>
    <h2 style="margin-bottom: 15px; color: #090979;"><i class="fas fa-ticket-alt"></i> Support Tickets Report</h2>
    <table>
        <thead><tr><th>ID</th><th>User</th><th>Subject</th><th>Message</th><th>Admin Reply</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>';
    while ($t = $tickets->fetch_assoc()) {
        $html .= '<tr><td>' . $t['id'] . '</td><td>' . htmlspecialchars($t['username']) . '</td><td>' . htmlspecialchars($t['subject']) . '</td><td>' . nl2br(htmlspecialchars($t['message'])) . '</td><td>' . nl2br(htmlspecialchars($t['admin_reply'])) . '</td><td>' . $t['status'] . '</td><td>' . $t['created_at'] . '</td></tr>';
    }
    $html .= '</tbody></table>
    <div class="footer">Report generated on ' . date('Y-m-d H:i:s') . ' &bull; <span>Salaam Online</span></div>
</div>
<script>window.onload=function(){ if(window.print) window.print(); };</script>
</body></html>';
    echo $html;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Single payment action
    if (isset($_POST['single_action'])) {
        $id = intval($_POST['request_id']);
        $action = $_POST['single_action'];
        $custom_msg = trim($_POST['admin_message']);
        $final_msg = ($action == 'approve') ? ($custom_msg ?: $auto_approve_msg) : ($custom_msg ?: $auto_reject_msg);
        $final_msg = $conn->real_escape_string($final_msg);
        $status = ($action == 'approve') ? 'approved' : 'rejected';
        $conn->query("UPDATE payment_requests SET status='$status', admin_message='$final_msg' WHERE id=$id");
        $get_user = $conn->query("SELECT user_id FROM payment_requests WHERE id=$id")->fetch_assoc();
        if ($get_user) {
            $user_id = $get_user['user_id'];
            $conn->query("INSERT INTO admin_messages (user_id, message, admin_reply, status) VALUES ($user_id, '$final_msg', NULL, 'unread')");
        }
    }
    // Bulk action
    elseif (isset($_POST['bulk_action'])) {
        $ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : [];
        $action = $_POST['bulk_action'];
        $custom_msg = trim($_POST['admin_message']);
        $final_msg = ($action == 'approve_selected') ? ($custom_msg ?: $auto_approve_msg) : ($custom_msg ?: $auto_reject_msg);
        $final_msg = $conn->real_escape_string($final_msg);
        if (!empty($ids)) {
            $ids_list = implode(',', array_map('intval', $ids));
            $status = ($action == 'approve_selected') ? 'approved' : 'rejected';
            $conn->query("UPDATE payment_requests SET status='$status', admin_message='$final_msg' WHERE id IN ($ids_list)");
            $users_res = $conn->query("SELECT DISTINCT user_id FROM payment_requests WHERE id IN ($ids_list)");
            while ($u = $users_res->fetch_assoc()) {
                $conn->query("INSERT INTO admin_messages (user_id, message, admin_reply, status) VALUES ({$u['user_id']}, '$final_msg', NULL, 'unread')");
            }
        }
    }
    // Reply to admin message
    elseif (isset($_POST['reply_message'])) {
        $msg_id = intval($_POST['msg_id']);
        $reply = $conn->real_escape_string(trim($_POST['admin_reply']));
        if ($reply) $conn->query("UPDATE admin_messages SET admin_reply='$reply', status='replied' WHERE id=$msg_id");
    }
    // Reply to support ticket
    elseif (isset($_POST['reply_ticket'])) {
        $ticket_id = intval($_POST['ticket_id']);
        $reply = $conn->real_escape_string(trim($_POST['admin_reply']));
        if ($reply) {
            $conn->query("UPDATE support_tickets SET admin_reply='$reply', status='resolved' WHERE id=$ticket_id");
            $ticket = $conn->query("SELECT user_id, subject FROM support_tickets WHERE id=$ticket_id")->fetch_assoc();
            if ($ticket) {
                $msg = "Jawaabta ticket-ka '{$ticket['subject']}': " . $reply;
                $conn->query("INSERT INTO admin_messages (user_id, message, admin_reply, status) VALUES ({$ticket['user_id']}, '$msg', NULL, 'unread')");
            }
        }
    }
    // Send direct message to user from ticket
    elseif (isset($_POST['send_direct_message'])) {
        $user_id = intval($_POST['user_id']);
        $message = $conn->real_escape_string(trim($_POST['direct_message']));
        if ($user_id && $message) {
            $conn->query("INSERT INTO admin_messages (user_id, message, status) VALUES ($user_id, '$message', 'unread')");
        }
    }
    // Reply to video comment
    elseif (isset($_POST['reply_video_comment'])) {
        $comment_id = intval($_POST['comment_id']);
        $reply = $conn->real_escape_string(trim($_POST['admin_reply']));
        if ($reply) $conn->query("UPDATE video_comments SET admin_reply='$reply' WHERE id=$comment_id");
    }
    // Send new message to user
    elseif (isset($_POST['send_message_to_user'])) {
        $user_id = intval($_POST['user_id']);
        $message = $conn->real_escape_string(trim($_POST['admin_message_text']));
        if ($user_id && $message) $conn->query("INSERT INTO admin_messages (user_id, message, status) VALUES ($user_id, '$message', 'unread')");
    }
    // Add video
    elseif (isset($_POST['add_video'])) {
        $course_id = intval($_POST['course_id']);
        $title = $conn->real_escape_string($_POST['title']);
        $embed_url = $conn->real_escape_string($_POST['embed_url']);
        $order = intval($_POST['video_order']);
        $conn->query("INSERT INTO course_videos (course_id, title, embed_url, video_order) VALUES ($course_id, '$title', '$embed_url', $order)");
    }
    // Delete video
    elseif (isset($_POST['delete_video'])) {
        $video_id = intval($_POST['video_id']);
        $conn->query("DELETE FROM course_videos WHERE id=$video_id");
    }
    // Add PDF
    elseif (isset($_POST['add_pdf'])) {
        $video_id = intval($_POST['video_id']);
        $file_name = $conn->real_escape_string($_POST['file_name']);
        $file_path = $conn->real_escape_string($_POST['file_path']);
        $conn->query("INSERT INTO video_pdfs (video_id, file_name, file_path) VALUES ($video_id, '$file_name', '$file_path')");
    }
    // Delete PDF
    elseif (isset($_POST['delete_pdf'])) {
        $pdf_id = intval($_POST['pdf_id']);
        $conn->query("DELETE FROM video_pdfs WHERE id=$pdf_id");
    }
    // Update course (simple version from old UI) - keep for compatibility but not used in new UI
    elseif (isset($_POST['update_course'])) {
        $course_id = intval($_POST['course_id']);
        $name = $conn->real_escape_string($_POST['course_name']);
        $price = floatval($_POST['course_price']);
        $category = $conn->real_escape_string($_POST['course_category']);
        $image = $conn->real_escape_string($_POST['course_image']);
        $conn->query("UPDATE courses SET name='$name', price=$price, category='$category', image='$image' WHERE id=$course_id");
        // Refresh course list
        $course_list = [];
        $res_courses = $conn->query("SELECT id, name, price, category, image FROM courses");
        while($row = $res_courses->fetch_assoc()) $course_list[$row['id']] = $row;
    }
    // NEW: Delete course with proper handling
    elseif (isset($_POST['delete_course'])) {
        $course_id = intval($_POST['course_id']);
        // First, set course_id to NULL in payment_requests to avoid losing payment records
        $conn->query("UPDATE payment_requests SET course_id = NULL WHERE course_id = $course_id");
        // Also remove course ID from course_ids text field (optional)
        $conn->query("UPDATE payment_requests SET course_ids = NULL WHERE course_ids = '$course_id' OR course_ids LIKE '$course_id,%' OR course_ids LIKE '%,$course_id' OR course_ids LIKE '%,$course_id,%'");
        // Now delete the course (cascade will delete videos, pdfs, comments)
        $conn->query("DELETE FROM courses WHERE id = $course_id");
        // Redirect to courses section
        header("Location: admin.php?section=courses");
        exit;
    }
    // NEW: Full update for course (name, image, duration, description, video_url)
    elseif (isset($_POST['update_course_full'])) {
        $course_id = intval($_POST['course_id']);
        $name = $conn->real_escape_string(trim($_POST['course_name']));
        $image = $conn->real_escape_string(trim($_POST['course_image']));
        $duration = $conn->real_escape_string(trim($_POST['course_duration']));
        $description = $conn->real_escape_string(trim($_POST['course_description']));
        $video_url = trim($_POST['course_video_url']);
        
        // Update course basic info
        $conn->query("UPDATE courses SET name='$name', image='$image', duration='$duration', description='$description' WHERE id=$course_id");
        
        // Handle video URL: update first video or create new one
        if (!empty($video_url)) {
            $check_video = $conn->query("SELECT id FROM course_videos WHERE course_id = $course_id ORDER BY video_order LIMIT 1");
            if ($check_video->num_rows > 0) {
                $vid = $check_video->fetch_assoc();
                $conn->query("UPDATE course_videos SET embed_url='$video_url' WHERE id={$vid['id']}");
            } else {
                // Create new video entry
                $conn->query("INSERT INTO course_videos (course_id, title, embed_url, video_order) VALUES ($course_id, 'Main Video', '$video_url', 0)");
            }
        }
        // Refresh courses list
        $all_courses_data = $conn->query("SELECT c.*, (SELECT embed_url FROM course_videos WHERE course_id = c.id ORDER BY video_order LIMIT 1) as first_video_url FROM courses c ORDER BY c.id");
        $courses_with_video_link = [];
        while($crs = $all_courses_data->fetch_assoc()) {
            $courses_with_video_link[] = $crs;
        }
        header("Location: admin.php?section=courses");
        exit;
    }
    // CSV Exports
    elseif (isset($_POST['export_users'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=users_export.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Username', 'Full Name', 'Email', 'Registered Date']);
        $users = $conn->query("SELECT id, username, full_name, email, created_at FROM users ORDER BY id");
        while ($row = $users->fetch_assoc()) fputcsv($output, $row);
        fclose($output);
        exit;
    }
    elseif (isset($_POST['export_payments'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=payments_export.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'User ID', 'Username', 'Courses', 'Amount', 'Status', 'Transaction ID', 'Date']);
        $payments = $conn->query("SELECT pr.*, u.username FROM payment_requests pr JOIN users u ON pr.user_id=u.id WHERE pr.status='approved' ORDER BY pr.created_at DESC");
        while ($p = $payments->fetch_assoc()) {
            $courses = getCourseNames($p, $course_list);
            fputcsv($output, [$p['id'], $p['user_id'], $p['username'], $courses, $p['amount'], $p['status'], $p['transaction_id'], $p['created_at']]);
        }
        fclose($output);
        exit;
    }
    elseif (isset($_POST['export_tickets'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=tickets_export.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'User', 'Subject', 'Message', 'Admin Reply', 'Status', 'Created']);
        $tickets = $conn->query("SELECT t.*, u.username FROM support_tickets t JOIN users u ON t.user_id=u.id ORDER BY t.created_at DESC");
        while ($t = $tickets->fetch_assoc()) {
            fputcsv($output, [$t['id'], $t['username'], $t['subject'], $t['message'], $t['admin_reply'], $t['status'], $t['created_at']]);
        }
        fclose($output);
        exit;
    }
    // Default redirect if no specific redirect after certain actions
    if (!isset($_POST['delete_course']) && !isset($_POST['update_course_full'])) {
        header("Location: admin.php?status=" . ($_GET['status'] ?? 'all'));
        exit;
    }
}

// ========== 6. FETCH DATA ==========
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'] ?? 0;
$stats = [
    'pending' => $conn->query("SELECT COUNT(*) as c FROM payment_requests WHERE status='pending'")->fetch_assoc()['c'] ?? 0,
    'approved' => $conn->query("SELECT COUNT(*) as c FROM payment_requests WHERE status='approved'")->fetch_assoc()['c'] ?? 0,
    'rejected' => $conn->query("SELECT COUNT(*) as c FROM payment_requests WHERE status='rejected'")->fetch_assoc()['c'] ?? 0
];
$total_income = $conn->query("SELECT SUM(amount) as total FROM payment_requests WHERE status='approved'")->fetch_assoc()['total'] ?? 0;

// ========== FIX: Only count unreplied tickets and comments ==========
$total_tickets = $conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE admin_reply IS NULL")->fetch_assoc()['cnt'] ?? 0;
$total_comments = $conn->query("SELECT COUNT(*) as cnt FROM video_comments WHERE admin_reply IS NULL")->fetch_assoc()['cnt'] ?? 0;

$messages = $conn->query("SELECT m.*, u.username, u.email FROM admin_messages m JOIN users u ON m.user_id=u.id ORDER BY m.created_at DESC");
$video_comments = $conn->query("SELECT c.*, u.username, u.email, v.title as video_title FROM video_comments c JOIN users u ON c.user_id=u.id LEFT JOIN course_videos v ON c.video_id=v.id ORDER BY c.created_at DESC");
$support_tickets = $conn->query("SELECT t.*, u.username, u.email FROM support_tickets t JOIN users u ON t.user_id=u.id ORDER BY t.created_at DESC");

// ========== ADD SEQUENCE NUMBERS FOR SUPPORT TICKETS (per user) ==========
$ticket_seq = [];
$seqQuery = $conn->query("SELECT id, user_id, created_at FROM support_tickets ORDER BY user_id, created_at ASC");
$user_counter = [];
while ($row = $seqQuery->fetch_assoc()) {
    $uid = $row['user_id'];
    if (!isset($user_counter[$uid])) $user_counter[$uid] = 0;
    $user_counter[$uid]++;
    $ticket_seq[$row['id']] = $user_counter[$uid];
}

// ========== ADD SEQUENCE NUMBERS FOR VIDEO COMMENTS (per user) ==========
$comment_seq = [];
$seqCommentQuery = $conn->query("SELECT id, user_id, created_at FROM video_comments ORDER BY user_id, created_at ASC");
$user_comment_counter = [];
while ($rowc = $seqCommentQuery->fetch_assoc()) {
    $uid = $rowc['user_id'];
    if (!isset($user_comment_counter[$uid])) $user_comment_counter[$uid] = 0;
    $user_comment_counter[$uid]++;
    $comment_seq[$rowc['id']] = $user_comment_counter[$uid];
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_query = "SELECT pr.*, u.username FROM payment_requests pr JOIN users u ON pr.user_id=u.id";
if ($status_filter != 'all') $filter_query .= " WHERE pr.status = '$status_filter'";
$filter_query .= " ORDER BY pr.created_at DESC";
$filtered_requests = $conn->query($filter_query);

// Enrollments: both approved and rejected
$enrollments = $conn->query("SELECT pr.*, u.username, u.email FROM payment_requests pr JOIN users u ON pr.user_id=u.id WHERE pr.status IN ('approved','rejected') ORDER BY pr.created_at DESC");

$courses_with_videos = [];
$all_courses = $conn->query("SELECT * FROM courses");
while ($c = $all_courses->fetch_assoc()) {
    $videos = $conn->query("SELECT * FROM course_videos WHERE course_id=" . $c['id'] . " ORDER BY video_order")->fetch_all(MYSQLI_ASSOC);
    $courses_with_videos[$c['id']] = ['info' => $c, 'videos' => $videos];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salaam Online | Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            background-attachment: fixed;
            color: #1e293b;
            position: relative;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.6);
            z-index: -1;
        }
        .admin-wrapper { display: flex; min-height: 100vh; }
        /* Sidebar styling - Salaam Online gradient (blue/cyan) */
        .sidebar { width: 280px; background: linear-gradient(180deg, #020024 0%, #090979 70%, #00D4FF 100%); color: #fef2f2; position: fixed; height: 100vh; transition: 0.3s; z-index: 100; box-shadow: 4px 0 20px rgba(0,0,0,0.2); overflow-y: auto; }
        .sidebar-header { padding: 2rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar-header h3 { font-size: 1.5rem; background: linear-gradient(135deg, #ffffff, #c7d2fe); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .sidebar-nav { padding: 2rem 0; }
        .nav-item { padding: 0.8rem 1.5rem; margin: 0.2rem 0; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: 0.2s; border-left: 3px solid transparent; }
        .nav-item i { width: 24px; font-size: 1.2rem; }
        .nav-item:hover, .nav-item.active { background: rgba(0,212,255,0.2); border-left-color: #00D4FF; color: white; }
        .badge-pending { background: #fbbf24; color: #1e293b; border-radius: 20px; padding: 0.2rem 0.6rem; font-size: 0.7rem; font-weight: bold; margin-left: auto; }
        .main-content { flex: 1; margin-left: 280px; padding: 1.8rem 2.2rem; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); padding: 1rem 2rem; border-radius: 1.5rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid rgba(0,212,255,0.3); }
        .page-title { font-size: 1.6rem; font-weight: 700; background: linear-gradient(135deg, #020024, #090979, #00D4FF); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logout-btn { background: #dc2626; color: white; padding: 0.5rem 1.5rem; border-radius: 2rem; text-decoration: none; font-weight: 600; transition: 0.2s; }
        .logout-btn:hover { background: #b91c1c; transform: translateY(-2px); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(4px); border-radius: 1.5rem; padding: 1.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); transition: 0.2s; cursor: pointer; border: 1px solid rgba(0,212,255,0.3); position: relative; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border-color: #00D4FF; }
        .stat-card h3 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: #4b5563; }
        .stat-number { font-size: 2.5rem; font-weight: 800; color: #090979; }
        .stat-icon { position: absolute; right: 1.5rem; top: 1.5rem; font-size: 2.5rem; opacity: 0.15; }
        .charts-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .chart-box { background: rgba(255,255,255,0.95); backdrop-filter: blur(4px); border-radius: 1.5rem; padding: 1.2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .section-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(4px); border-radius: 1.5rem; padding: 1.8rem; margin-bottom: 2rem; overflow-x: auto; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid rgba(0,212,255,0.2); }
        .section-title { font-size: 1.4rem; font-weight: 700; margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.5rem; color: #090979; border-left: 5px solid #00D4FF; padding-left: 1rem; }
        /* Table row colors */
        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; border-radius: 1rem; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); background: rgba(255,255,255,0.9); }
        .modern-table th, .modern-table td { padding: 1rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .modern-table th { background: #e0f2fe; font-weight: 600; color: #090979; }
        .modern-table tr:hover { background: #f0f9ff; }
        .approved-row { background-color: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; }
        .rejected-row { background-color: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; }
        .pending-row { background-color: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b; }
        .status-badge { padding: 0.25rem 0.85rem; border-radius: 2rem; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-approved { background: #d1fae5; color: #10b981; }
        .status-rejected { background: #fee2e2; color: #ef4444; }
        .filter-bar a { padding: 0.4rem 1.2rem; border-radius: 2rem; background: #f1f5f9; text-decoration: none; color: #334155; font-weight: 500; transition: 0.2s; }
        .filter-bar a.active, .filter-bar a:hover { background: #2563eb; color: white; }
        button, .btn-sm { padding: 0.4rem 1rem; border-radius: 0.6rem; border: none; font-weight: 600; cursor: pointer; font-size: 0.8rem; transition: 0.2s; }
        .btn-approve { background: #10b981; color: white; }
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: #ef4444; color: white; }
        .btn-reject:hover { background: #dc2626; }
        .btn-reply { background: #2563eb; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-print { background: #4b5563; color: white; }
        .message-bubble { background: #fff7ed; border-radius: 1rem; padding: 0.8rem; margin-bottom: 0.5rem; border-left: 4px solid #2563eb; }
        .message-bubble.unread { background: #fef3c7; border-left-color: #eab308; }
        .ticket-card { background: rgba(255,255,255,0.9); border-radius: 1rem; padding: 1rem; margin-bottom: 1rem; border: 1px solid #bae6fd; }
        .videos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .video-card { background: #f0f9ff; border-radius: 1rem; padding: 1rem; border: 1px solid #bae6fd; transition: 0.2s; }
        .video-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .report-card { border: 1px solid #bae6fd; border-radius: 1rem; padding: 1.5rem; margin-bottom: 1rem; background: #fff; }
        .report-header { border-bottom: 2px solid #bae6fd; padding-bottom: 0.75rem; margin-bottom: 1rem; background: #f0f9ff; padding: 1rem; border-radius: 1rem; }
        .search-input { margin-bottom: 1rem; padding: 0.5rem 1rem; border-radius: 2rem; border: 1px solid #bae6fd; width: 300px; font-family: inherit; background: white; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; border-radius: 1rem; padding: 1.5rem; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; }
        .edit-row input, .edit-row textarea { width: 100%; padding: 6px; margin: 4px 0; border: 1px solid #ccc; border-radius: 6px; }
        .edit-row button { margin-top: 5px; }
        @media print {
            .modal-content { box-shadow: none; margin: 0; padding: 0; width: 100%; }
            .modal .btn-print, .modal button { display: none; }
            .sidebar, .top-bar, .logout-btn, .report-actions, .filter-bar, .nav-item, .charts-row { display: none; }
            .main-content { margin-left: 0; padding: 0; }
            body::before { display: none; }
        }

        /* ========== QURXINTA REPORTS SECTION ========== */
        #reportsSection .section-title {
            font-size: 1.6rem;
            background: linear-gradient(135deg, #020024, #090979, #00D4FF);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            border-left: 6px solid #00D4FF;
            padding-left: 1.2rem;
        }

        #reportsSection .section-card {
            background: rgba(255,255,255,0.97);
            border-radius: 1.8rem;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,212,255,0.25);
        }

        #reportsSection .report-header-banner {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            padding: 1.2rem 1.8rem;
            border-radius: 1.5rem;
            margin-bottom: 1.8rem;
            border: 1px solid rgba(0,212,255,0.2);
        }
        #reportsSection .report-header-banner i {
            font-size: 2.8rem;
            color: #2563eb;
            background: white;
            padding: 0.8rem;
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(37,99,235,0.15);
        }
        #reportsSection .report-header-banner h2 {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #020024, #090979, #00D4FF);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }
        #reportsSection .report-header-banner p {
            color: #475569;
            font-size: 0.9rem;
            margin: 0;
        }

        /* Buttons-ka Reports */
        #reportsSection .report-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 1.5rem;
        }
        #reportsSection .report-actions form button {
            padding: 0.6rem 1.4rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        #reportsSection .report-actions form button.btn-approve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        #reportsSection .report-actions form button.btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16,185,129,0.35);
        }
        #reportsSection .report-actions form button.btn-primary {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
        }
        #reportsSection .report-actions form button.btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37,99,235,0.35);
        }

        /* Tables-ka Reports - qurux badan */
        #reportsSection .modern-table {
            border-radius: 1.2rem;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            background: white;
        }
        #reportsSection .modern-table thead th {
            background: linear-gradient(135deg, #020024, #090979, #1e40af);
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem 1.2rem;
            border: none;
        }
        #reportsSection .modern-table tbody td {
            padding: 0.9rem 1.2rem;
            border-bottom: 1px solid #eef2f6;
            color: #1e293b;
            font-size: 0.9rem;
        }
        #reportsSection .modern-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        #reportsSection .modern-table tbody tr:hover {
            background: #e0f2fe;
            transition: 0.2s;
        }
        #reportsSection .modern-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Buttons gudaha table */
        #reportsSection .modern-table .btn-sm {
            padding: 0.35rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: 0.2s;
        }
        #reportsSection .modern-table .btn-sm.btn-primary {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
        }
        #reportsSection .modern-table .btn-sm.btn-primary:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }

        /* Search input */
        #reportsSection .search-input {
            border-radius: 2rem;
            border: 1px solid #d1d5db;
            padding: 0.6rem 1.2rem;
            width: 280px;
            font-family: 'Inter', sans-serif;
            transition: 0.2s;
            background: white;
        }
        #reportsSection .search-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
            outline: none;
        }

        /* Headings in Reports */
        #reportsSection h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #090979;
            margin: 1.5rem 0 0.8rem 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        #reportsSection h3 i {
            color: #00D4FF;
            font-size: 1.4rem;
        }
        #reportsSection hr {
            border: none;
            height: 2px;
            background: linear-gradient(to right, #2563eb, #00D4FF, transparent);
            margin: 1.5rem 0;
            border-radius: 2px;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <div class="sidebar">
        <div class="sidebar-header"><h3><i class="fas fa-graduation-cap"></i> Salaam Online</h3></div>
        <div class="sidebar-nav">
            <div class="nav-item" onclick="showSection('dashboard')"><i class="fas fa-tachometer-alt"></i> Dashboard</div>
            <div class="nav-item" onclick="showSection('users')"><i class="fas fa-users"></i> Users</div>
            <div class="nav-item" onclick="showSection('payments')"><i class="fas fa-credit-card"></i> Payment Requests <?php if($stats['pending']>0): ?><span class="badge-pending"><?php echo $stats['pending']; ?></span><?php endif; ?></div>
            <div class="nav-item" onclick="showSection('enrollments')"><i class="fas fa-book-open"></i> Enrollments (Approved/Rejected)</div>
            <div class="nav-item" onclick="showSection('messages')"><i class="fas fa-envelope"></i> Direct Messages</div>
            <div class="nav-item" onclick="showSection('tickets')">
                <i class="fas fa-ticket-alt"></i> Support Tickets
                <?php if ($total_tickets > 0): ?>
                    <span class="badge-pending"><?php echo $total_tickets; ?></span>
                <?php endif; ?>
            </div>
            <div class="nav-item" onclick="showSection('videocomments')">
                <i class="fas fa-comment-dots"></i> Video Comments
                <?php if ($total_comments > 0): ?>
                    <span class="badge-pending"><?php echo $total_comments; ?></span>
                <?php endif; ?>
            </div>
            <div class="nav-item" onclick="showSection('courses')"><i class="fas fa-book"></i> Courses & Videos</div>
            <div class="nav-item" onclick="showSection('reports')"><i class="fas fa-chart-pie"></i> Reports</div>
        </div>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <span class="page-title" id="mainTitle">Dashboard</span>
            <a href="?logout=1" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <!-- Dashboard -->
        <div id="dashboardSection">
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location.href='?status=pending'"><h3><i class="fas fa-clock"></i> Pending</h3><div class="stat-number"><?php echo $stats['pending']; ?></div><div class="stat-icon"><i class="fas fa-hourglass-half"></i></div></div>
                <div class="stat-card" onclick="window.location.href='?status=approved'"><h3><i class="fas fa-check-circle"></i> Approved</h3><div class="stat-number"><?php echo $stats['approved']; ?></div><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
                <div class="stat-card" onclick="window.location.href='?status=rejected'"><h3><i class="fas fa-times-circle"></i> Rejected</h3><div class="stat-number"><?php echo $stats['rejected']; ?></div><div class="stat-icon"><i class="fas fa-ban"></i></div></div>
                <div class="stat-card"><h3><i class="fas fa-dollar-sign"></i> Total Income</h3><div class="stat-number">$<?php echo number_format($total_income, 2); ?></div><div class="stat-icon"><i class="fas fa-chart-line"></i></div></div>
                <div class="stat-card" onclick="showSection('tickets')"><h3><i class="fas fa-ticket-alt"></i> Support Tickets</h3><div class="stat-number"><?php echo $total_tickets; ?></div><div class="stat-icon"><i class="fas fa-headset"></i></div></div>
                <div class="stat-card" onclick="showSection('videocomments')"><h3><i class="fas fa-comment-dots"></i> Video Comments</h3><div class="stat-number"><?php echo $total_comments; ?></div><div class="stat-icon"><i class="fas fa-comments"></i></div></div>
            </div>
            <div class="charts-row">
                <div class="chart-box"><canvas id="statusChart" width="400" height="200"></canvas></div>
                <div class="chart-box"><canvas id="incomeChart" width="400" height="200"></canvas></div>
            </div>
        </div>

        <!-- Users -->
        <div id="usersSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-users"></i> Registered Users (<?php echo $total_users; ?>)</div>
                <table class="modern-table">
                    <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Registered</th></tr></thead>
                    <tbody><?php $users = $conn->query("SELECT id, username, full_name, email, created_at FROM users ORDER BY created_at DESC"); while($u = $users->fetch_assoc()): ?>
                        <tr><td><?php echo $u['id']; ?></td><td><?php echo htmlspecialchars($u['username']); ?></td><td><?php echo htmlspecialchars($u['full_name']); ?></td><td><?php echo htmlspecialchars($u['email']); ?></td><td><?php echo $u['created_at']; ?></td></tr>
                    <?php endwhile; ?></tbody>
                </table>
            </div>
        </div>

        <!-- Payment Requests -->
        <div id="paymentsSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-credit-card"></i> Payment Requests</div>
                <div class="filter-bar">
                    <a href="?status=all" class="<?php echo $status_filter=='all'?'active':''; ?>">All</a>
                    <a href="?status=pending" class="<?php echo $status_filter=='pending'?'active':''; ?>">Pending</a>
                    <a href="?status=approved" class="<?php echo $status_filter=='approved'?'active':''; ?>">Approved</a>
                    <a href="?status=rejected" class="<?php echo $status_filter=='rejected'?'active':''; ?>">Rejected</a>
                </div>
                <form method="POST" id="bulkForm">
                    <div style="margin-bottom:1rem; background:#f0f9ff; padding:1rem; border-radius:1rem;">
                        <label><i class="fas fa-comment-dots"></i> Bulk Action Message (optional):</label>
                        <textarea name="admin_message" rows="2" placeholder="Optional: Custom message for all selected requests..."></textarea>
                        <div style="margin-top:0.8rem; display:flex; gap:1rem;">
                            <button type="submit" name="bulk_action" value="approve_selected" class="btn-approve" onclick="return confirmBulk('approve')"><i class="fas fa-check-circle"></i> Approve Selected</button>
                            <button type="submit" name="bulk_action" value="reject_selected" class="btn-reject" onclick="return confirmBulk('reject')"><i class="fas fa-ban"></i> Reject Selected</button>
                        </div>
                    </div>
                    <table class="modern-table">
                        <thead><tr><th><input type="checkbox" id="selectAll"></th><th>ID</th><th>User</th><th>Course(s)</th><th>Fullname</th><th>Transaction</th><th>Amount</th><th>Status</th><th>Admin Message</th><th>Action</th></tr></thead>
                        <tbody><?php while($req = $filtered_requests->fetch_assoc()): $course_names = getCourseNames($req, $course_list); ?>
                            <tr class="<?php echo $req['status']=='pending' ? 'pending-row' : ($req['status']=='approved' ? 'approved-row' : 'rejected-row'); ?>">
                                <td><input type="checkbox" name="selected_ids[]" value="<?php echo $req['id']; ?>"></td>
                                <td><?php echo $req['id']; ?></td>
                                <td><?php echo htmlspecialchars($req['username']); ?></td>
                                <td><?php echo htmlspecialchars($course_names); ?></td>
                                <td><?php echo htmlspecialchars($req['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($req['transaction_id']); ?></td>
                                <td>$<?php echo $req['amount']; ?></td>
                                <td><span class="status-badge status-<?php echo $req['status']; ?>"><?php echo ucfirst($req['status']); ?></span></td>
                                <td style="max-width:200px;"><?php echo nl2br(htmlspecialchars($req['admin_message'])); ?></td>
                                <td><?php if($req['status'] == 'pending'): ?>
                                    <form method="POST" style="display:inline-block; width:100%;">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <textarea name="admin_message" rows="1" placeholder="Custom message" style="font-size:0.7rem;"></textarea>
                                        <button type="submit" name="single_action" value="approve" class="btn-sm btn-approve"><i class="fas fa-check"></i> Approve</button>
                                        <button type="submit" name="single_action" value="reject" class="btn-sm btn-reject"><i class="fas fa-times"></i> Reject</button>
                                    </form>
                                <?php else: ?>—<?php endif; ?></td>
                            </tr>
                        <?php endwhile; ?></tbody>
                    </table>
                </form>
            </div>
        </div>

        <!-- Enrollments (Approved/Rejected) -->
        <div id="enrollmentsSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-book-open"></i> Enrollments (Approved / Rejected)</div>
                <table class="modern-table">
                    <thead><tr><th>User ID</th><th>Username</th><th>Email</th><th>Course(s)</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php while($enr = $enrollments->fetch_assoc()): $course_names = getCourseNames($enr, $course_list); ?>
                        <tr class="<?php echo $enr['status'] == 'approved' ? 'approved-row' : 'rejected-row'; ?>">
                            <td><?php echo $enr['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($enr['username']); ?></td>
                            <td><?php echo htmlspecialchars($enr['email']); ?></td>
                            <td><?php echo htmlspecialchars($course_names); ?></td>
                            <td>$<?php echo number_format($enr['amount'],2); ?></td>
                            <td><span class="status-badge status-<?php echo $enr['status']; ?>"><?php echo ucfirst($enr['status']); ?></span></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($enr['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Direct Messages -->
        <div id="messagesSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-comments"></i> Direct Messages</div>
                <div style="margin-bottom:1.5rem; background:#f0f9ff; padding:1rem; border-radius:1rem;">
                    <h4><i class="fas fa-paper-plane"></i> Send New Message to User</h4>
                    <form method="POST">
                        <select name="user_id" required><option value="">-- Select User --</option>
                        <?php $all_users = $conn->query("SELECT id, username, email FROM users ORDER BY username"); while($usr = $all_users->fetch_assoc()): ?>
                            <option value="<?php echo $usr['id']; ?>"><?php echo htmlspecialchars($usr['username'] . ' (' . $usr['email'] . ')'); ?></option>
                        <?php endwhile; ?></select>
                        <textarea name="admin_message_text" rows="2" placeholder="Type your message..." required></textarea>
                        <button type="submit" name="send_message_to_user" class="btn-approve"><i class="fas fa-paper-plane"></i> Send</button>
                    </form>
                </div>
                <?php $messages->data_seek(0); while($msg = $messages->fetch_assoc()): ?>
                <div class="message-bubble <?php echo $msg['status']=='unread' ? 'unread' : ''; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($msg['username']); ?> (<?php echo htmlspecialchars($msg['email']); ?>)</strong>
                        <span class="status-badge status-<?php echo $msg['status']; ?>"><?php echo $msg['status']; ?></span>
                    </div>
                    <div style="margin: 0.5rem 0;"><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                    <?php if($msg['admin_reply']): ?>
                        <div style="background: #dcfce7; padding: 0.5rem; border-radius: 0.8rem; margin-top: 0.5rem;"><strong>Admin Reply:</strong> <?php echo nl2br(htmlspecialchars($msg['admin_reply'])); ?></div>
                    <?php else: ?>
                        <form method="POST" style="margin-top:0.5rem;">
                            <input type="hidden" name="msg_id" value="<?php echo $msg['id']; ?>">
                            <textarea name="admin_reply" rows="2" placeholder="Write your reply..." style="width:100%;"></textarea>
                            <button type="submit" name="reply_message" class="btn-sm btn-reply">Reply</button>
                        </form>
                    <?php endif; ?>
                    <div class="text-muted" style="font-size:0.7rem; margin-top:0.3rem;"><?php echo $msg['created_at']; ?></div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Support Tickets (with per-user sequence number, hidden when 0) -->
        <div id="ticketsSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-ticket-alt"></i> Support Tickets (Help Center)</div>
                <?php $support_tickets->data_seek(0); while($t = $support_tickets->fetch_assoc()): 
                    $seqNumber = isset($ticket_seq[$t['id']]) ? $ticket_seq[$t['id']] : 0;
                ?>
                <div class="ticket-card">
                    <div class="ticket-header">
                        <div><strong>#<?php echo $t['id']; ?></strong> - <?php echo htmlspecialchars($t['subject']); ?> 
                            <span class="status-badge status-<?php echo $t['status']; ?>"><?php echo $t['status']; ?></span>
                            <?php if ($seqNumber > 0): ?>
                            <span style="background:#dbeafe; padding:2px 8px; border-radius:20px; margin-left:10px; font-size:0.75rem;">
                                <i class="fas fa-sort-numeric-up"></i> User Request #<?php echo $seqNumber; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div><?php echo $t['created_at']; ?></div>
                    </div>
                    <div><strong>User:</strong> <?php echo htmlspecialchars($t['username']); ?> (<?php echo htmlspecialchars($t['email']); ?>)</div>
                    <div style="margin: 0.5rem 0; background:#f8fafc; padding:0.5rem; border-radius:0.5rem;"><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($t['message'])); ?></div>
                    <?php if($t['admin_reply']): ?>
                        <div style="background:#dcfce7; padding:0.5rem; border-radius:0.5rem;"><strong>Admin Reply:</strong> <?php echo nl2br(htmlspecialchars($t['admin_reply'])); ?></div>
                    <?php else: ?>
                        <form method="POST" style="margin-top:0.5rem;">
                            <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                            <textarea name="admin_reply" rows="2" placeholder="Reply to this ticket..." style="width:100%;"></textarea>
                            <button type="submit" name="reply_ticket" class="btn-sm btn-reply">Reply & Resolve</button>
                        </form>
                    <?php endif; ?>
                    <div style="margin-top:0.5rem; text-align:right;">
                        <button class="btn-sm btn-primary" onclick="openMessageModal(<?php echo $t['user_id']; ?>, '<?php echo htmlspecialchars($t['username']); ?>')"><i class="fas fa-paper-plane"></i> Direct Message</button>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Video Comments (with per-user sequence number, hidden when 0) -->
        <div id="videocommentsSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-comment-dots"></i> Video Comments</div>
                <table class="modern-table">
                    <thead><tr><th>ID</th><th>User</th><th>Video</th><th>Comment</th><th>User Comment #</th><th>Admin Reply</th><th>Created</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php $video_comments->data_seek(0); while($cmt = $video_comments->fetch_assoc()): 
                        $seqNum = isset($comment_seq[$cmt['id']]) ? $comment_seq[$cmt['id']] : 0;
                    ?>
                    <tr>
                        <td><?php echo $cmt['id']; ?></td>
                        <td><?php echo htmlspecialchars($cmt['username']); ?> (<?php echo htmlspecialchars($cmt['email']); ?>)</td>
                        <td><?php echo htmlspecialchars($cmt['video_title'] ?? 'Unknown'); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($cmt['comment'])); ?></td>
                        <td><?php if ($seqNum > 0): ?><span class="status-badge" style="background:#e0e7ff; color:#1e40af;">#<?php echo $seqNum; ?></span><?php else: ?>—<?php endif; ?></td>
                        <td><?php echo nl2br(htmlspecialchars($cmt['admin_reply'])); ?></td>
                        <td><?php echo $cmt['created_at']; ?></td>
                        <td><?php if(empty($cmt['admin_reply'])): ?><form method="POST"><input type="hidden" name="comment_id" value="<?php echo $cmt['id']; ?>"><textarea name="admin_reply" rows="2" placeholder="Reply..." style="width:150px;"></textarea><button type="submit" name="reply_video_comment" class="btn-sm btn-reply">Reply</button></form><?php else: ?>—<?php endif; ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Courses Section (unchanged) -->
        <div id="coursesSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-book"></i> Courses Management</div>
                <div style="overflow-x:auto;">
                    <table class="modern-table" id="coursesTable">
                        <thead>
                            <tr>
                                <th>Course Name</th>
                                <th>Image Link</th>
                                <th>Duration</th>
                                <th>Description</th>
                                <th>Video Link</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($courses_with_video_link as $course): ?>
                            <tr id="course-row-<?php echo $course['id']; ?>" class="course-row">
                                <td class="course-name" data-field="name"><?php echo htmlspecialchars($course['name']); ?></td>
                                <td class="course-image" data-field="image"><?php echo htmlspecialchars($course['image']); ?></td>
                                <td class="course-duration" data-field="duration"><?php echo htmlspecialchars($course['duration']); ?></td>
                                <td class="course-description" data-field="description"><?php echo htmlspecialchars($course['description']); ?></td>
                                <td class="course-video" data-field="video"><?php echo htmlspecialchars($course['first_video_url'] ?? 'No video'); ?></td>
                                <td>
                                    <button class="btn-sm btn-primary edit-course-btn" data-id="<?php echo $course['id']; ?>">Edit</button>
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this course? All associated videos and data will be removed.');">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" name="delete_course" class="btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <tr id="edit-row-<?php echo $course['id']; ?>" class="edit-row" style="display:none;">
                                <td colspan="6" style="padding: 15px; background: #f9f9ff;">
                                    <form method="POST" action="" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <div style="flex:1; min-width:140px;">
                                            <label>Course Name</label>
                                            <input type="text" name="course_name" value="<?php echo htmlspecialchars($course['name']); ?>" required>
                                        </div>
                                        <div style="flex:1; min-width:140px;">
                                            <label>Image URL</label>
                                            <input type="text" name="course_image" value="<?php echo htmlspecialchars($course['image']); ?>">
                                        </div>
                                        <div style="flex:1; min-width:120px;">
                                            <label>Duration</label>
                                            <input type="text" name="course_duration" value="<?php echo htmlspecialchars($course['duration']); ?>">
                                        </div>
                                        <div style="flex:2; min-width:180px;">
                                            <label>Description</label>
                                            <textarea name="course_description" rows="2" style="width:100%;"><?php echo htmlspecialchars($course['description']); ?></textarea>
                                        </div>
                                        <div style="flex:1; min-width:140px;">
                                            <label>Video URL</label>
                                            <input type="text" name="course_video_url" value="<?php echo htmlspecialchars($course['first_video_url'] ?? ''); ?>" placeholder="YouTube embed URL">
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <button type="submit" name="update_course_full" class="btn-approve">Save Update</button>
                                            <button type="button" class="btn-reject cancel-edit" data-id="<?php echo $course['id']; ?>">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="margin-top: 15px; font-size:0.85rem; color:#4b5563;"><i class="fas fa-info-circle"></i> Note: Video link shows the first associated video. Use "Edit" to update course details including video URL.</p>
            </div>
        </div>

        <!-- ======== REPORTS SECTION – QURXIN CUSUB ======== -->
        <div id="reportsSection" style="display:none;">
            <div class="section-card">
                <!-- Banner Sare -->
                <div class="report-header-banner">
                    <i class="fas fa-chart-pie"></i>
                    <div>
                        <h2>Salaam Online – Reports & Analytics</h2>
                        <p>Warbixinaha isticmaalaha, lacagaha, iyo taageerada</p>
                    </div>
                </div>

                <div class="report-actions">
                    <form method="POST" target="_blank"><button type="submit" name="export_users" class="btn-approve"><i class="fas fa-file-csv"></i> Export Users (CSV)</button></form>
                    <form method="POST" target="_blank"><button type="submit" name="export_payments" class="btn-approve"><i class="fas fa-file-csv"></i> Export Payments (CSV)</button></form>
                    <form method="POST" target="_blank"><button type="submit" name="export_tickets" class="btn-approve"><i class="fas fa-file-csv"></i> Export Tickets (CSV)</button></form>
                    <form method="POST" target="_blank"><button type="submit" name="export_users_pdf" class="btn-primary"><i class="fas fa-file-pdf"></i> Export Users (PDF)</button></form>
                    <form method="POST" target="_blank"><button type="submit" name="export_payments_pdf" class="btn-primary"><i class="fas fa-file-pdf"></i> Export Payments (PDF)</button></form>
                    <form method="POST" target="_blank"><button type="submit" name="export_tickets_pdf" class="btn-primary"><i class="fas fa-file-pdf"></i> Export Tickets (PDF)</button></form>
                </div>

                <hr>

                <!-- User Report -->
                <h3><i class="fas fa-users"></i> User Report</h3>
                <input type="text" id="userSearch" class="search-input" placeholder="Search by username, email, or course...">
                <table class="modern-table" id="userTable">
                    <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Total Paid ($)</th><th>Courses Purchased</th><th>Registered</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php 
                        $users_report = $conn->query("SELECT u.id, u.username, u.full_name, u.email, u.created_at, COALESCE(SUM(pr.amount),0) as total_paid, GROUP_CONCAT(DISTINCT CASE WHEN pr.status='approved' THEN IF(pr.course_ids IS NOT NULL, pr.course_ids, pr.course_id) END) as courses_ids FROM users u LEFT JOIN payment_requests pr ON u.id = pr.user_id AND pr.status='approved' GROUP BY u.id ORDER BY u.id");
                        while($ur = $users_report->fetch_assoc()):
                            $course_names = [];
                            if($ur['courses_ids']) {
                                $ids = explode(',', $ur['courses_ids']);
                                foreach($ids as $id) if(is_numeric($id) && isset($course_list[$id])) $course_names[] = $course_list[$id]['name'];
                            }
                            $search_data = strtolower($ur['username'].' '.$ur['email'].' '.implode(' ', $course_names));
                        ?>
                        <tr data-search="<?php echo $search_data; ?>">
                            <td><?php echo $ur['id']; ?></td>
                            <td><?php echo htmlspecialchars($ur['username']); ?></td>
                            <td><?php echo htmlspecialchars($ur['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($ur['email']); ?></td>
                            <td>$<?php echo number_format($ur['total_paid'],2); ?></td>
                            <td><?php echo htmlspecialchars(implode(', ', $course_names)); ?></td>
                            <td><?php echo $ur['created_at']; ?></td>
                            <td><button class="btn-sm btn-primary" onclick="viewUserDetails(<?php echo $ur['id']; ?>)"><i class="fas fa-file-invoice"></i> Full Report</button></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <hr>

                <!-- Payment Report -->
                <h3><i class="fas fa-dollar-sign"></i> Payment Report</h3>
                <input type="text" id="paymentSearch" class="search-input" placeholder="Search by username, course, or transaction...">
                <table class="modern-table" id="paymentTable">
                    <thead><tr><th>ID</th><th>User</th><th>Course(s)</th><th>Amount</th><th>Transaction ID</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php $payments_report = $conn->query("SELECT pr.*, u.username FROM payment_requests pr JOIN users u ON pr.user_id=u.id WHERE pr.status='approved' ORDER BY pr.created_at DESC");
                        while($pr = $payments_report->fetch_assoc()): $course_names = getCourseNames($pr, $course_list); ?>
                        <tr data-search="<?php echo strtolower($pr['username'].' '.$course_names.' '.$pr['transaction_id']); ?>">
                            <td><?php echo $pr['id']; ?></td>
                            <td><?php echo htmlspecialchars($pr['username']); ?></td>
                            <td><?php echo htmlspecialchars($course_names); ?></td>
                            <td>$<?php echo number_format($pr['amount'],2); ?></td>
                            <td><?php echo htmlspecialchars($pr['transaction_id']); ?></td>
                            <td><?php echo $pr['created_at']; ?></td>
                            <td><button class="btn-sm btn-primary" onclick="viewPaymentDetails(<?php echo $pr['id']; ?>, '<?php echo htmlspecialchars($pr['username']); ?>', '<?php echo addslashes($course_names); ?>', <?php echo $pr['amount']; ?>, '<?php echo $pr['transaction_id']; ?>', '<?php echo $pr['created_at']; ?>')"><i class="fas fa-receipt"></i> Receipt</button></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modals (unchanged) -->
<div id="userDetailModal" class="modal">
    <div class="modal-content" id="userDetailContent">
        <div style="text-align:right;"><button class="btn-sm btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button> <button class="btn-sm btn-danger" onclick="closeUserModal()">Close</button></div>
        <div id="userDetailHtml"></div>
    </div>
</div>
<div id="paymentDetailModal" class="modal">
    <div class="modal-content" id="paymentDetailContent">
        <div style="text-align:right;"><button class="btn-sm btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button> <button class="btn-sm btn-danger" onclick="closePaymentModal()">Close</button></div>
        <div id="paymentDetailHtml"></div>
    </div>
</div>
<div id="messageModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-paper-plane"></i> Send Message to User</h3>
        <form method="POST">
            <input type="hidden" name="user_id" id="modal_user_id">
            <p>Sending to: <strong id="modal_username"></strong></p>
            <textarea name="direct_message" rows="4" placeholder="Type your message here..." required></textarea>
            <div style="display:flex; gap:1rem; margin-top:1rem;">
                <button type="submit" name="send_direct_message" class="btn-approve">Send Message</button>
                <button type="button" class="btn-reject" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showSection(section) {
        const sections = ['dashboardSection','usersSection','paymentsSection','enrollmentsSection','messagesSection','ticketsSection','videocommentsSection','coursesSection','reportsSection'];
        sections.forEach(s => document.getElementById(s).style.display = 'none');
        document.getElementById(section + 'Section').style.display = 'block';
        let title = section.charAt(0).toUpperCase() + section.slice(1);
        if(section === 'payments') title = 'Payment Requests';
        if(section === 'videocomments') title = 'Video Comments';
        if(section === 'enrollments') title = 'Enrollments (Approved/Rejected)';
        if(section === 'courses') title = 'Courses & Videos';
        document.getElementById('mainTitle').innerText = title;
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        document.querySelector(`.nav-item[onclick="showSection('${section}')"]`).classList.add('active');
    }

    document.querySelectorAll('.edit-course-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            let courseId = this.getAttribute('data-id');
            let normalRow = document.getElementById('course-row-' + courseId);
            let editRow = document.getElementById('edit-row-' + courseId);
            if (normalRow) normalRow.style.display = 'none';
            if (editRow) editRow.style.display = 'table-row';
        });
    });
    document.querySelectorAll('.cancel-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            let courseId = this.getAttribute('data-id');
            let normalRow = document.getElementById('course-row-' + courseId);
            let editRow = document.getElementById('edit-row-' + courseId);
            if (normalRow) normalRow.style.display = '';
            if (editRow) editRow.style.display = 'none';
        });
    });

    const selectAll = document.getElementById('selectAll');
    if(selectAll) selectAll.addEventListener('change', function(e) {
        document.querySelectorAll('input[name="selected_ids[]"]').forEach(cb => cb.checked = e.target.checked);
    });
    function confirmBulk(action) {
        let selected = document.querySelectorAll('input[name="selected_ids[]"]:checked').length;
        if(selected === 0) { alert('No requests selected'); return false; }
        return confirm(`Are you sure you want to ${action} ${selected} request(s)?`);
    }
    function openMessageModal(userId, username) {
        document.getElementById('modal_user_id').value = userId;
        document.getElementById('modal_username').innerText = username;
        document.getElementById('messageModal').style.display = 'flex';
    }
    function closeModal() { document.getElementById('messageModal').style.display = 'none'; }
    function viewUserDetails(userId) {
        fetch('?action=get_user_details&user_id=' + userId)
            .then(res => res.json())
            .then(data => {
                if (data.error) { alert(data.error); return; }
                let html = `<div class="report-card">
                    <div class="report-header"><h2><i class="fas fa-user-circle"></i> User Full Report</h2>
                    <p><strong>ID:</strong> ${data.id} | <strong>Username:</strong> ${data.username} | <strong>Full Name:</strong> ${data.full_name}</p>
                    <p><strong>Email:</strong> ${data.email} | <strong>Registered:</strong> ${data.created_at}</p></div>
                    <h3>Purchase History (Approved Payments)</h3>`;
                if (data.payments.length === 0) html += '<p>No approved payments found.</p>';
                else {
                    html += '<table class="modern-table"><thead><tr><th>Transaction ID</th><th>Course(s)</th><th>Amount</th><th>Date</th></tr></thead><tbody>';
                    data.payments.forEach(p => {
                        html += `<tr><td>${p.transaction_id}</td><td>${p.courses}</td><td>$${p.amount}</td><td>${p.date}</td></tr>`;
                    });
                    html += '</tbody></table>';
                }
                html += `<div class="report-footer"><p>Generated on ${new Date().toLocaleString()} | Salaam Online Admin</p></div></div>`;
                document.getElementById('userDetailHtml').innerHTML = html;
                document.getElementById('userDetailModal').style.display = 'flex';
            })
            .catch(err => alert('Error loading user details'));
    }
    function closeUserModal() { document.getElementById('userDetailModal').style.display = 'none'; }
    function viewPaymentDetails(id, username, courseNames, amount, transactionId, date) {
        let html = `<div class="report-card">
            <div class="report-header"><h2><i class="fas fa-receipt"></i> Payment Receipt</h2>
            <p><strong>Receipt #:</strong> ${id}</p>
            <p><strong>User:</strong> ${username}</p>
            <p><strong>Course(s):</strong> ${courseNames}</p>
            <p><strong>Amount Paid:</strong> $${amount}</p>
            <p><strong>Transaction ID:</strong> ${transactionId}</p>
            <p><strong>Payment Date:</strong> ${date}</p></div>
            <div class="report-footer"><p>Thank you for your purchase! | Salaam Online</p></div></div>`;
        document.getElementById('paymentDetailHtml').innerHTML = html;
        document.getElementById('paymentDetailModal').style.display = 'flex';
    }
    function closePaymentModal() { document.getElementById('paymentDetailModal').style.display = 'none'; }
    document.getElementById('userSearch')?.addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#userTable tbody tr');
        rows.forEach(row => {
            let text = row.getAttribute('data-search') || '';
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
    document.getElementById('paymentSearch')?.addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#paymentTable tbody tr');
        rows.forEach(row => {
            let text = row.getAttribute('data-search') || '';
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
    new Chart(document.getElementById('statusChart').getContext('2d'), {
        type: 'doughnut',
        data: { labels: ['Pending','Approved','Rejected'], datasets: [{ data: [<?php echo $stats['pending']; ?>,<?php echo $stats['approved']; ?>,<?php echo $stats['rejected']; ?>], backgroundColor: ['#f59e0b','#10b981','#ef4444'], borderWidth: 0 }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
    new Chart(document.getElementById('incomeChart').getContext('2d'), {
        type: 'bar',
        data: { labels: ['Total Approved Payments'], datasets: [{ label: 'Income ($)', data: [<?php echo $total_income; ?>], backgroundColor: '#2563eb', borderRadius: 10 }] },
        options: { responsive: true, scales: { y: { beginAtZero: true, title: { display: true, text: 'USD' } } } }
    });
    const urlParams = new URLSearchParams(window.location.search);
    const sectionParam = urlParams.get('section');
    if (sectionParam && ['dashboard','users','payments','enrollments','messages','tickets','videocomments','courses','reports'].includes(sectionParam)) {
        showSection(sectionParam);
    } else {
        showSection('dashboard');
    }
</script>
<?php if(isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; } ?>
</body>
</html>
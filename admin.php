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

// ========== 2. DEFAULT COURSES ==========
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

// ========== 3. FETCH COURSES ==========
$course_list = [];
$res_courses = $conn->query("SELECT id, name, price, category, image FROM courses");
while($row = $res_courses->fetch_assoc()) $course_list[$row['id']] = $row;

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

// ========== 4. LOGIN ==========
$admin_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!$admin_logged_in && isset($_POST['admin_login'])) {
    if ($_POST['admin_user'] === 'admin' && $_POST['admin_pass'] === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $admin_logged_in = true;
    } else $login_error = "Invalid credentials";
}
if (!$admin_logged_in) {
    ?><!DOCTYPE html><html><head><title>Admin Login - Salaam Online</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;min-height:100vh;position:relative;overflow:hidden}.slideshow{position:fixed;top:0;left:0;width:100%;height:100%;z-index:-1}.slideshow img{position:absolute;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity 1.5s ease-in-out}.slideshow img.active{opacity:1}.overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:0}.login-container{position:relative;z-index:2;background:rgba(255,255,255,0.95);backdrop-filter:blur(12px);border-radius:2rem;padding:2.5rem;width:420px;box-shadow:0 25px 45px rgba(0,0,0,0.3);margin:10vh auto;text-align:center}.login-header i{font-size:3rem;color:#7c3aed;background:white;padding:1rem;border-radius:50%}.input-group{position:relative;margin-bottom:1.5rem}.input-group i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#94a3b8}.input-group input{width:100%;padding:0.9rem 1rem 0.9rem 2.8rem;border:1px solid #e2e8f0;border-radius:1rem;font-size:1rem}.btn{width:100%;padding:0.9rem;background:linear-gradient(135deg,#7c3aed,#a855f7);color:white;border:none;border-radius:1rem;font-weight:600;cursor:pointer}.error{background:#fee2e2;color:#dc2626;padding:0.75rem;border-radius:0.75rem;margin-bottom:1rem}</style></head><body><div class="slideshow"><img src="https://images.unsplash.com/photo-1498050108023-c5249f4df085?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" class="active"><img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80"><img src="https://images.unsplash.com/photo-1519389950473-47ba0277781c?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80"></div><div class="overlay"></div><div class="login-container"><div class="login-header"><i class="fas fa-user-shield"></i><h2>Salaam Online Admin</h2><p>Admin Portal</p></div><?php if(isset($login_error)) echo "<div class='error'>$login_error</div>"; ?><form method="POST"><div class="input-group"><i class="fas fa-user"></i><input type="text" name="admin_user" placeholder="Username" required></div><div class="input-group"><i class="fas fa-lock"></i><input type="password" name="admin_pass" placeholder="Password" required></div><button type="submit" name="admin_login" class="btn">Login</button></form><p style="margin-top:1.5rem;">admin / admin123</p></div><script>let slides=document.querySelectorAll('.slideshow img'),current=0;setInterval(()=>{slides[current].classList.remove('active');current=(current+1)%slides.length;slides[current].classList.add('active');},4000);</script></body></html><?php exit;
}

// ========== 5. POST HANDLING ==========
$auto_approve_msg = "✅ Lacagta waa la aqbalay. Course-ka waa la furay, hadda waxaad ka heli kartaa qaybta 'My Courses'. Mahadsanid!";
$auto_reject_msg = "❌ Waan ka xunahay, macluumaadka transaction-ka ma saxna ama wax ka maqan. Fadlan dib u soo gudbi macluumaad sax ah.";

if (isset($_GET['action']) && $_GET['action'] === 'get_user_details' && isset($_GET['user_id'])) {
    header('Content-Type: application/json');
    $user_id = intval($_GET['user_id']);
    $user = $conn->query("SELECT id, username, full_name, email, created_at FROM users WHERE id=$user_id")->fetch_assoc();
    if (!$user) { echo json_encode(['error' => 'User not found']); exit; }
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

// PDF/CSV exports (simplified for brevity – keep full code in production)
if (isset($_POST['export_users_pdf'])) { /* ... */ exit; }
if (isset($_POST['export_payments_pdf'])) { /* ... */ exit; }
if (isset($_POST['export_tickets_pdf'])) { /* ... */ exit; }

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
    elseif (isset($_POST['send_direct_message'])) {
        $user_id = intval($_POST['user_id']);
        $message = $conn->real_escape_string(trim($_POST['direct_message']));
        if ($user_id && $message) {
            $conn->query("INSERT INTO admin_messages (user_id, message, status) VALUES ($user_id, '$message', 'unread')");
        }
    }
    elseif (isset($_POST['reply_video_comment'])) {
        $comment_id = intval($_POST['comment_id']);
        $reply = $conn->real_escape_string(trim($_POST['admin_reply']));
        if ($reply) $conn->query("UPDATE video_comments SET admin_reply='$reply' WHERE id=$comment_id");
    }
    elseif (isset($_POST['send_message_to_user'])) {
        $user_id = intval($_POST['user_id']);
        $message = $conn->real_escape_string(trim($_POST['admin_message_text']));
        if ($user_id && $message) $conn->query("INSERT INTO admin_messages (user_id, message, status) VALUES ($user_id, '$message', 'unread')");
    }
    elseif (isset($_POST['add_video'])) {
        $course_id = intval($_POST['course_id']);
        $title = $conn->real_escape_string($_POST['title']);
        $embed_url = $conn->real_escape_string($_POST['embed_url']);
        $order = intval($_POST['video_order']);
        $conn->query("INSERT INTO course_videos (course_id, title, embed_url, video_order) VALUES ($course_id, '$title', '$embed_url', $order)");
    }
    elseif (isset($_POST['delete_video'])) {
        $video_id = intval($_POST['video_id']);
        $conn->query("DELETE FROM course_videos WHERE id=$video_id");
    }
    elseif (isset($_POST['add_pdf'])) {
        $video_id = intval($_POST['video_id']);
        $file_name = $conn->real_escape_string($_POST['file_name']);
        $file_path = $conn->real_escape_string($_POST['file_path']);
        $conn->query("INSERT INTO video_pdfs (video_id, file_name, file_path) VALUES ($video_id, '$file_name', '$file_path')");
    }
    elseif (isset($_POST['delete_pdf'])) {
        $pdf_id = intval($_POST['pdf_id']);
        $conn->query("DELETE FROM video_pdfs WHERE id=$pdf_id");
    }
    elseif (isset($_POST['delete_course'])) {
        $course_id = intval($_POST['course_id']);
        $conn->query("UPDATE payment_requests SET course_id = NULL WHERE course_id = $course_id");
        $conn->query("UPDATE payment_requests SET course_ids = NULL WHERE course_ids = '$course_id' OR course_ids LIKE '$course_id,%' OR course_ids LIKE '%,$course_id' OR course_ids LIKE '%,$course_id,%'");
        $conn->query("DELETE FROM courses WHERE id = $course_id");
        header("Location: admin.php?section=courses");
        exit;
    }
    elseif (isset($_POST['update_course_full'])) {
        $course_id = intval($_POST['course_id']);
        $name = $conn->real_escape_string(trim($_POST['course_name']));
        $image = $conn->real_escape_string(trim($_POST['course_image']));
        $duration = $conn->real_escape_string(trim($_POST['course_duration']));
        $description = $conn->real_escape_string(trim($_POST['course_description']));
        $video_url = trim($_POST['course_video_url']);
        $conn->query("UPDATE courses SET name='$name', image='$image', duration='$duration', description='$description' WHERE id=$course_id");
        if (!empty($video_url)) {
            $check_video = $conn->query("SELECT id FROM course_videos WHERE course_id = $course_id ORDER BY video_order LIMIT 1");
            if ($check_video->num_rows > 0) {
                $vid = $check_video->fetch_assoc();
                $conn->query("UPDATE course_videos SET embed_url='$video_url' WHERE id={$vid['id']}");
            } else {
                $conn->query("INSERT INTO course_videos (course_id, title, embed_url, video_order) VALUES ($course_id, 'Main Video', '$video_url', 0)");
            }
        }
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

$total_tickets = $conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE admin_reply IS NULL")->fetch_assoc()['cnt'] ?? 0;
$total_comments = $conn->query("SELECT COUNT(*) as cnt FROM video_comments WHERE admin_reply IS NULL")->fetch_assoc()['cnt'] ?? 0;

$messages = $conn->query("SELECT m.*, u.username, u.email FROM admin_messages m JOIN users u ON m.user_id=u.id ORDER BY m.created_at DESC");
$video_comments = $conn->query("SELECT c.*, u.username, u.email, v.title as video_title FROM video_comments c JOIN users u ON c.user_id=u.id LEFT JOIN course_videos v ON c.video_id=v.id ORDER BY c.created_at DESC");
$support_tickets = $conn->query("SELECT t.*, u.username, u.email FROM support_tickets t JOIN users u ON t.user_id=u.id ORDER BY t.created_at DESC");

$ticket_seq = [];
$seqQuery = $conn->query("SELECT id, user_id, created_at FROM support_tickets ORDER BY user_id, created_at ASC");
$user_counter = [];
while ($row = $seqQuery->fetch_assoc()) {
    $uid = $row['user_id'];
    if (!isset($user_counter[$uid])) $user_counter[$uid] = 0;
    $user_counter[$uid]++;
    $ticket_seq[$row['id']] = $user_counter[$uid];
}
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

// ====== CHANGE: Enrollments only approved ======
$enrollments = $conn->query("SELECT pr.*, u.username, u.email FROM payment_requests pr JOIN users u ON pr.user_id=u.id WHERE pr.status = 'approved' ORDER BY pr.created_at DESC");

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
        /* ============================================================
               ROOT VARIABLES – Violet/Indigo + Amber Accents
            ============================================================ */
        :root {
            --sidebar-bg: linear-gradient(180deg, #1e1b4b 0%, #312e81 40%, #4c1d95 100%);
            --primary: #7c3aed;
            --primary-light: #a855f7;
            --primary-dark: #5b21b6;
            --accent: #f59e0b;
            --accent-light: #fbbf24;
            --bg-body: #f5f3ff;
            --card-bg: rgba(255,255,255,0.92);
            --text-dark: #1e1b4b;
            --text-muted: #6b7280;
            --border-light: #e0e7ff;
            --shadow-card: 0 12px 40px rgba(94, 33, 195, 0.08);
            --radius-card: 1.8rem;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-dark);
        }
        a { text-decoration: none; color: inherit; }

        .admin-wrapper { display: flex; min-height: 100vh; }

        /* ============================================================
               SIDEBAR (unchanged)
            ============================================================ */
        .sidebar { width: 280px; background: var(--sidebar-bg); color: #e0e7ff; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; box-shadow: 6px 0 30px rgba(30,27,75,0.25); transition: transform 0.3s ease; display: flex; flex-direction: column; }
        .sidebar-header { padding: 2rem 1.5rem 1.5rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 0.75rem; }
        .sidebar-header i { font-size: 2rem; color: var(--accent-light); }
        .sidebar-header h3 { font-size: 1.4rem; font-weight: 700; background: linear-gradient(to right, #fde68a, #fbbf24); -webkit-background-clip: text; background-clip: text; color: transparent; letter-spacing: -0.5px; }
        .sidebar-nav { flex: 1; padding: 1.5rem 0.5rem 2rem; }
        .nav-item { display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1.2rem; margin: 0.25rem 0.5rem; border-radius: 0.75rem; cursor: pointer; transition: all 0.25s ease; font-weight: 500; color: #c7d2fe; position: relative; }
        .nav-item i { width: 24px; font-size: 1.2rem; text-align: center; }
        .nav-item:hover { background: rgba(251,191,36,0.15); color: white; transform: translateX(6px); }
        .nav-item.active { background: rgba(251,191,36,0.2); color: white; box-shadow: inset 3px 0 0 var(--accent-light); }
        .badge-pending { background: var(--accent); color: #1e1b4b; border-radius: 20px; padding: 0.1rem 0.7rem; font-size: 0.7rem; font-weight: 700; margin-left: auto; }
        .sidebar-footer { padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.08); font-size: 0.8rem; color: #94a3b8; text-align: center; }

        .main-content { flex: 1; margin-left: 280px; padding: 1.8rem 2.2rem; min-height: 100vh; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; background: rgba(255,255,255,0.8); backdrop-filter: blur(12px); padding: 0.8rem 2rem; border-radius: var(--radius-card); box-shadow: var(--shadow-card); border: 1px solid rgba(255,255,255,0.3); }
        .page-title { font-size: 1.5rem; font-weight: 700; background: linear-gradient(135deg, #1e1b4b, #4c1d95, #7c3aed); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logout-btn { background: #dc2626; color: white; padding: 0.5rem 1.5rem; border-radius: 2rem; font-weight: 600; transition: 0.2s; border: none; cursor: pointer; }
        .logout-btn:hover { background: #b91c1c; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(220,38,38,0.3); }

        /* ============================================================
               STATS CARDS
            ============================================================ */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--card-bg); backdrop-filter: blur(4px); border-radius: var(--radius-card); padding: 1.5rem 1.2rem; box-shadow: var(--shadow-card); border: 1px solid rgba(124,58,237,0.08); transition: all 0.3s ease; cursor: pointer; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), var(--accent)); opacity: 0; transition: opacity 0.3s; }
        .stat-card:hover::before { opacity: 1; }
        .stat-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(94,33,195,0.12); border-color: var(--primary-light); }
        .stat-card .stat-icon { position: absolute; right: 1.2rem; top: 1.2rem; font-size: 2.4rem; opacity: 0.1; color: var(--primary); }
        .stat-card h3 { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 0.3rem; }
        .stat-number { font-size: 2.4rem; font-weight: 800; background: linear-gradient(135deg, #1e1b4b, #7c3aed); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .charts-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .chart-box { background: white; border-radius: var(--radius-card); padding: 1.2rem; box-shadow: var(--shadow-card); border: 1px solid var(--border-light); }

        /* ============================================================
               SECTION CARD (common container)
            ============================================================ */
        .section-card { background: white; border-radius: var(--radius-card); padding: 1.8rem 2rem; margin-bottom: 2rem; box-shadow: var(--shadow-card); border: 1px solid var(--border-light); overflow: hidden; }
        .section-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.6rem; color: var(--text-dark); border-left: 5px solid var(--primary); padding-left: 1rem; }
        .section-title i { color: var(--primary); }

        /* ============================================================
               USERS – Card Grid
            ============================================================ */
        .user-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem; }
        .user-card { background: white; border-radius: 1.2rem; padding: 1.2rem; border: 1px solid var(--border-light); transition: all 0.2s; display: flex; flex-direction: column; align-items: center; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .user-card:hover { transform: translateY(-4px); border-color: var(--primary-light); box-shadow: 0 12px 30px rgba(94,33,195,0.08); }
        .user-avatar { width: 70px; height: 70px; border-radius: 50%; background: var(--primary-light); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; margin-bottom: 0.8rem; }
        .user-card .name { font-weight: 600; font-size: 1.1rem; }
        .user-card .email { color: var(--text-muted); font-size: 0.85rem; }
        .user-card .meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.3rem; }

        /* ============================================================
               PAYMENTS – Enhanced Table
            ============================================================ */
        .table-responsive { overflow-x: auto; border-radius: 1.2rem; border: 1px solid var(--border-light); background: white; }
        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; background: white; }
        .modern-table thead th { background: #f5f3ff; color: var(--text-dark); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; padding: 1rem 1rem; border-bottom: 2px solid var(--border-light); white-space: nowrap; }
        .modern-table tbody td { padding: 0.9rem 1rem; border-bottom: 1px solid #f1f5f9; color: var(--text-dark); vertical-align: middle; }
        .modern-table tbody tr:last-child td { border-bottom: none; }
        .modern-table tbody tr { transition: background 0.15s ease; }
        .modern-table tbody tr:hover { background: #f5f3ff; }
        .modern-table tbody tr:nth-child(even) { background: #fafaff; }
        .modern-table tbody tr:nth-child(even):hover { background: #f5f3ff; }

        .status-badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.8rem; border-radius: 2rem; font-size: 0.75rem; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-read { background: #e0e7ff; color: #3730a3; }
        .status-unread { background: #fef3c7; color: #92400e; }
        .status-replied { background: #d1fae5; color: #065f46; }
        .status-open { background: #dbeafe; color: #1e40af; }
        .status-resolved { background: #d1fae5; color: #065f46; }
        .status-closed { background: #e2e8f0; color: #475569; }

        .filter-bar { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.2rem; }
        .filter-bar a { padding: 0.4rem 1.2rem; border-radius: 2rem; background: #f1f5f9; color: #334155; font-weight: 500; font-size: 0.85rem; transition: 0.2s; }
        .filter-bar a.active, .filter-bar a:hover { background: var(--primary); color: white; }

        .btn-sm { padding: 0.35rem 1rem; border-radius: 2rem; font-size: 0.75rem; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.4rem; }
        .btn-sm:hover { transform: scale(1.04); }
        .btn-approve { background: #10b981; color: white; }
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: #ef4444; color: white; }
        .btn-reject:hover { background: #dc2626; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-warning { background: var(--accent); color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-print { background: #4b5563; color: white; }
        .btn-print:hover { background: #374151; }

        /* ============================================================
               ACTION COLUMN – Improved Design
            ============================================================ */
        .action-container {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            align-items: stretch;
            min-width: 140px;
        }
        .action-container textarea {
            width: 100%;
            border-radius: 0.6rem;
            border: 1px solid #d1d5db;
            padding: 0.3rem 0.5rem;
            font-size: 0.75rem;
            font-family: inherit;
            resize: vertical;
            min-height: 40px;
            background: #fafafa;
            transition: 0.2s;
        }
        .action-container textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
        }
        .action-container .action-buttons {
            display: flex;
            gap: 0.4rem;
            justify-content: flex-end;
        }
        .action-container .action-buttons .btn-sm {
            flex: 1;
            justify-content: center;
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
        }
        .action-container .action-buttons .btn-approve {
            background: #10b981;
            color: white;
        }
        .action-container .action-buttons .btn-approve:hover {
            background: #059669;
        }
        .action-container .action-buttons .btn-reject {
            background: #ef4444;
            color: white;
        }
        .action-container .action-buttons .btn-reject:hover {
            background: #dc2626;
        }

        /* ============================================================
               ENROLLMENTS – Timeline (only approved)
            ============================================================ */
        .timeline { position: relative; padding-left: 2rem; }
        .timeline::before { content: ''; position: absolute; left: 0.5rem; top: 0; bottom: 0; width: 2px; background: var(--border-light); }
        .timeline-item { position: relative; margin-bottom: 1.5rem; padding-left: 1.5rem; }
        .timeline-item::before { content: ''; position: absolute; left: -0.5rem; top: 0.5rem; width: 12px; height: 12px; border-radius: 50%; background: var(--primary); border: 2px solid white; box-shadow: 0 0 0 2px var(--primary); }
        .timeline-item.approved::before { background: #10b981; box-shadow: 0 0 0 2px #10b981; }
        .timeline-item .tl-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .timeline-item .tl-title { font-weight: 600; }
        .timeline-item .tl-date { font-size: 0.8rem; color: var(--text-muted); }

        /* ============================================================
               MESSAGES – Chat bubbles
            ============================================================ */
        .message-bubble { background: #f8fafc; border-radius: 1rem; padding: 1rem; margin-bottom: 1rem; border-left: 4px solid var(--primary); transition: 0.15s; }
        .message-bubble.unread { background: #fef3c7; border-left-color: var(--accent); }
        .message-bubble .msg-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; }
        .message-bubble .msg-header strong { color: var(--text-dark); }

        /* ============================================================
               TICKETS – Accordion
            ============================================================ */
        .ticket-card { background: #f8fafc; border-radius: 1.2rem; padding: 1.2rem 1.5rem; margin-bottom: 1.2rem; border: 1px solid var(--border-light); transition: 0.15s; }
        .ticket-card:hover { border-color: var(--primary-light); }
        .ticket-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.5rem; }

        /* ============================================================
               COURSES – Grid Cards
            ============================================================ */
        .course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .course-card { background: white; border-radius: 1.2rem; overflow: hidden; border: 1px solid var(--border-light); transition: all 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .course-card:hover { transform: translateY(-4px); border-color: var(--primary-light); box-shadow: 0 12px 30px rgba(94,33,195,0.08); }
        .course-card img { width: 100%; height: 160px; object-fit: cover; border-bottom: 1px solid var(--border-light); }
        .course-card .course-info { padding: 1rem 1.2rem; }
        .course-card .course-info h4 { font-weight: 600; margin-bottom: 0.3rem; }
        .course-card .course-info .meta { font-size: 0.85rem; color: var(--text-muted); }
        .course-card .course-actions { padding: 0.8rem 1.2rem; border-top: 1px solid var(--border-light); display: flex; gap: 0.5rem; justify-content: flex-end; }

        /* ============================================================
               REPORTS – Enhanced with icons
            ============================================================ */
        .search-input { padding: 0.5rem 1.2rem; border-radius: 2rem; border: 1px solid #d1d5db; width: 280px; font-family: 'Inter', sans-serif; transition: 0.2s; background: white; margin-bottom: 1rem; }
        .search-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(124,58,237,0.1); }

        /* ============================================================
               USER DETAIL CARD (Modal içi) – NEW DESIGN
            ============================================================ */
        .user-detail-card { background: white; border-radius: 1.5rem; padding: 2rem; border: 1px solid var(--border-light); box-shadow: var(--shadow-card); }
        .user-detail-card .ud-header { display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem; }
        .user-detail-card .ud-avatar { width: 80px; height: 80px; border-radius: 50%; background: var(--primary-light); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; flex-shrink: 0; }
        .user-detail-card .ud-info h2 { font-size: 1.5rem; margin-bottom: 0.2rem; color: var(--text-dark); }
        .user-detail-card .ud-info p { color: var(--text-muted); margin: 0.2rem 0; }
        .user-detail-card .ud-info p i { width: 20px; color: var(--primary); }
        .user-detail-card .ud-divider { border: none; border-top: 1px solid var(--border-light); margin: 1.5rem 0; }
        .user-detail-card .ud-purchases h4 { margin-bottom: 0.8rem; }
        .user-detail-card .ud-purchases table { width: 100%; font-size: 0.9rem; }
        .user-detail-card .ud-purchases table th { text-align: left; font-weight: 600; color: var(--text-muted); border-bottom: 1px solid var(--border-light); padding: 0.5rem 0; }
        .user-detail-card .ud-purchases table td { padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; }

        /* ============================================================
               MODALS
            ============================================================ */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 999; padding: 1rem; }
        .modal-content { background: white; border-radius: 2rem; padding: 2rem; max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 30px 60px rgba(0,0,0,0.2); }
        .modal-content .modal-actions { display: flex; justify-content: flex-end; gap: 0.8rem; margin-bottom: 1rem; }

        /* ============================================================
               RESPONSIVE
            ============================================================ */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 260px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .top-bar { flex-wrap: wrap; gap: 0.8rem; padding: 0.8rem 1.2rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-row { grid-template-columns: 1fr; }
            .search-input { width: 100%; }
            .filter-bar a { font-size: 0.75rem; padding: 0.3rem 0.8rem; }
            .section-card { padding: 1rem; }
            .user-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
            .course-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
            .user-detail-card .ud-header { flex-direction: column; text-align: center; }
            .action-container .action-buttons { flex-direction: column; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .stat-number { font-size: 1.8rem; }
            .modern-table { font-size: 0.75rem; }
            .modern-table th, .modern-table td { padding: 0.5rem 0.6rem; }
        }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.5rem; color: #1e293b; cursor: pointer; padding: 0.3rem 0.8rem; }
        @media (max-width: 768px) { .menu-toggle { display: block; } }

        @media print {
            .sidebar, .top-bar, .logout-btn, .filter-bar, .charts-row, .menu-toggle { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .modal { display: block !important; position: relative !important; background: white !important; backdrop-filter: none !important; padding: 0 !important; margin: 0 !important; top: 0 !important; left: 0 !important; width: 100% !important; height: auto !important; z-index: auto !important; }
            .modal-content { box-shadow: none !important; border: none !important; padding: 1rem !important; max-width: 100% !important; max-height: none !important; border-radius: 0 !important; }
            .modal .modal-actions { display: none !important; }
            .user-detail-card { border: 1px solid #ddd !important; box-shadow: none !important; }
            #paymentDetailModal, #messageModal { display: none !important; }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-graduation-cap"></i>
            <h3>Salaam Online</h3>
        </div>
        <div class="sidebar-nav">
            <div class="nav-item active" onclick="showSection('dashboard')"><i class="fas fa-tachometer-alt"></i> Dashboard</div>
            <div class="nav-item" onclick="showSection('users')"><i class="fas fa-users"></i> Users</div>
            <div class="nav-item" onclick="showSection('payments')">
                <i class="fas fa-credit-card"></i> Payments
                <?php if($stats['pending']>0): ?>
                    <span class="badge-pending"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
            </div>
            <div class="nav-item" onclick="showSection('enrollments')"><i class="fas fa-book-open"></i> Enrollments</div>
            <div class="nav-item" onclick="showSection('messages')"><i class="fas fa-envelope"></i> Messages</div>
            <div class="nav-item" onclick="showSection('tickets')">
                <i class="fas fa-ticket-alt"></i> Tickets
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
            <div class="nav-item" onclick="showSection('courses')"><i class="fas fa-book"></i> Courses</div>
            <div class="nav-item" onclick="showSection('reports')"><i class="fas fa-chart-pie"></i> Reports</div>
        </div>
        <div class="sidebar-footer">&copy; 2025 Salaam Online</div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div style="display:flex;align-items:center;gap:0.8rem;">
                <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                <span class="page-title" id="mainTitle">Dashboard</span>
            </div>
            <a href="?logout=1" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <!-- ==========================================================
        DASHBOARD (unchanged)
        ========================================================== -->
        <div id="dashboardSection">
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location.href='?status=pending'"><div class="stat-icon"><i class="fas fa-hourglass-half"></i></div><h3>Pending</h3><div class="stat-number"><?php echo $stats['pending']; ?></div></div>
                <div class="stat-card" onclick="window.location.href='?status=approved'"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><h3>Approved</h3><div class="stat-number"><?php echo $stats['approved']; ?></div></div>
                <div class="stat-card" onclick="window.location.href='?status=rejected'"><div class="stat-icon"><i class="fas fa-times-circle"></i></div><h3>Rejected</h3><div class="stat-number"><?php echo $stats['rejected']; ?></div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-dollar-sign"></i></div><h3>Total Income</h3><div class="stat-number">$<?php echo number_format($total_income, 2); ?></div></div>
                <div class="stat-card" onclick="showSection('tickets')"><div class="stat-icon"><i class="fas fa-ticket-alt"></i></div><h3>Support Tickets</h3><div class="stat-number"><?php echo $total_tickets; ?></div></div>
                <div class="stat-card" onclick="showSection('videocomments')"><div class="stat-icon"><i class="fas fa-comment-dots"></i></div><h3>Video Comments</h3><div class="stat-number"><?php echo $total_comments; ?></div></div>
            </div>
            <div class="charts-row">
                <div class="chart-box"><canvas id="statusChart" width="400" height="200"></canvas></div>
                <div class="chart-box"><canvas id="incomeChart" width="400" height="200"></canvas></div>
            </div>
        </div>

        <!-- ==========================================================
        USERS – Card Grid View
        ========================================================== -->
        <div id="usersSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-users"></i> Registered Users <span style="font-size:0.9rem; background:#f5f3ff; padding:0.2rem 1rem; border-radius:2rem; margin-left:0.5rem;"><?php echo $total_users; ?> total</span></div>
                <div class="user-grid">
                    <?php
                    $users = $conn->query("SELECT id, username, full_name, email, created_at FROM users ORDER BY created_at DESC");
                    while($u = $users->fetch_assoc()):
                        $initials = strtoupper(substr($u['full_name'], 0, 2));
                    ?>
                    <div class="user-card">
                        <div class="user-avatar"><?php echo $initials; ?></div>
                        <div class="name"><?php echo htmlspecialchars($u['full_name']); ?></div>
                        <div class="email"><?php echo htmlspecialchars($u['email']); ?></div>
                        <div class="meta"><i class="fas fa-user-tag"></i> @<?php echo htmlspecialchars($u['username']); ?></div>
                        <div class="meta"><i class="fas fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($u['created_at'])); ?></div>
                        <button class="btn-sm btn-primary" style="margin-top:0.5rem;" onclick="viewUserDetails(<?php echo $u['id']; ?>)"><i class="fas fa-eye"></i> View</button>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- ==========================================================
        PAYMENTS – Improved Design
        ========================================================== -->
        <div id="paymentsSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-credit-card"></i> Payment Requests</div>
                
                <!-- Summary Cards -->
                <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
                    <div style="flex:1; min-width:120px; background:#f5f3ff; border-radius:1rem; padding:0.8rem 1.2rem; border:1px solid var(--border-light); text-align:center;">
                        <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase;">Pending</div>
                        <div style="font-size:1.6rem; font-weight:700; color:#f59e0b;"><?php echo $stats['pending']; ?></div>
                    </div>
                    <div style="flex:1; min-width:120px; background:#f5f3ff; border-radius:1rem; padding:0.8rem 1.2rem; border:1px solid var(--border-light); text-align:center;">
                        <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase;">Approved</div>
                        <div style="font-size:1.6rem; font-weight:700; color:#10b981;"><?php echo $stats['approved']; ?></div>
                    </div>
                    <div style="flex:1; min-width:120px; background:#f5f3ff; border-radius:1rem; padding:0.8rem 1.2rem; border:1px solid var(--border-light); text-align:center;">
                        <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase;">Rejected</div>
                        <div style="font-size:1.6rem; font-weight:700; color:#ef4444;"><?php echo $stats['rejected']; ?></div>
                    </div>
                    <div style="flex:1; min-width:120px; background:#f5f3ff; border-radius:1rem; padding:0.8rem 1.2rem; border:1px solid var(--border-light); text-align:center;">
                        <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase;">Total Income</div>
                        <div style="font-size:1.6rem; font-weight:700; color:var(--primary-dark);">$<?php echo number_format($total_income, 2); ?></div>
                    </div>
                </div>

                <div class="filter-bar">
                    <a href="?status=all" class="<?php echo $status_filter=='all'?'active':''; ?>">All</a>
                    <a href="?status=pending" class="<?php echo $status_filter=='pending'?'active':''; ?>">Pending</a>
                    <a href="?status=approved" class="<?php echo $status_filter=='approved'?'active':''; ?>">Approved</a>
                    <a href="?status=rejected" class="<?php echo $status_filter=='rejected'?'active':''; ?>">Rejected</a>
                </div>
                <form method="POST" id="bulkForm">
                    <div style="margin-bottom:1.2rem; background:#f5f3ff; padding:1rem 1.5rem; border-radius:1.2rem; display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                        <div style="flex:1; min-width:200px;">
                            <label style="font-weight:600; font-size:0.85rem;"><i class="fas fa-comment-dots"></i> Bulk Message</label>
                            <textarea name="admin_message" rows="2" placeholder="Custom message..." style="width:100%; border-radius:0.8rem; border:1px solid #d1d5db; padding:0.5rem;"></textarea>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <button type="submit" name="bulk_action" value="approve_selected" class="btn-sm btn-approve" onclick="return confirmBulk('approve')"><i class="fas fa-check"></i> Approve Selected</button>
                            <button type="submit" name="bulk_action" value="reject_selected" class="btn-sm btn-reject" onclick="return confirmBulk('reject')"><i class="fas fa-times"></i> Reject Selected</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead><tr><th><input type="checkbox" id="selectAll"></th><th>ID</th><th>User</th><th>Course(s)</th><th>Fullname</th><th>Transaction</th><th>Amount</th><th>Status</th><th>Admin Msg</th><th>Action</th></tr></thead>
                            <tbody><?php while($req = $filtered_requests->fetch_assoc()): $course_names = getCourseNames($req, $course_list); ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?php echo $req['id']; ?>"></td>
                                    <td><?php echo $req['id']; ?></td>
                                    <td><?php echo htmlspecialchars($req['username']); ?></td>
                                    <td><?php echo htmlspecialchars($course_names); ?></td>
                                    <td><?php echo htmlspecialchars($req['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($req['transaction_id']); ?></td>
                                    <td>$<?php echo $req['amount']; ?></td>
                                    <td><span class="status-badge status-<?php echo $req['status']; ?>"><?php echo ucfirst($req['status']); ?></span></td>
                                    <td style="max-width:150px; font-size:0.8rem;"><?php echo nl2br(htmlspecialchars($req['admin_message'])); ?></td>
                                    <td>
                                        <?php if($req['status'] == 'pending'): ?>
                                        <form method="POST" class="action-container">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <textarea name="admin_message" rows="1" placeholder="Custom message..."></textarea>
                                            <div class="action-buttons">
                                                <button type="submit" name="single_action" value="approve" class="btn-sm btn-approve"><i class="fas fa-check"></i> Approve</button>
                                                <button type="submit" name="single_action" value="reject" class="btn-sm btn-reject"><i class="fas fa-times"></i> Reject</button>
                                            </div>
                                        </form>
                                        <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.8rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?></tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>

        <!-- ==========================================================
        ENROLLMENTS – Only Approved (changed)
        ========================================================== -->
        <div id="enrollmentsSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-book-open"></i> Enrollments (Approved Only)</div>
                <div class="timeline">
                    <?php
                    $enrollments->data_seek(0);
                    while($enr = $enrollments->fetch_assoc()):
                        $course_names = getCourseNames($enr, $course_list);
                        // Only approved are shown
                    ?>
                    <div class="timeline-item approved">
                        <div class="tl-header">
                            <span class="tl-title"><i class="fas fa-user"></i> <?php echo htmlspecialchars($enr['username']); ?> – <span class="status-badge status-approved">Approved</span></span>
                            <span class="tl-date"><?php echo date('d M Y, h:i A', strtotime($enr['created_at'])); ?></span>
                        </div>
                        <div style="font-size:0.9rem; color:var(--text-muted);">
                            <i class="fas fa-book"></i> <?php echo htmlspecialchars($course_names); ?> &bull; $<?php echo number_format($enr['amount'],2); ?>
                        </div>
                        <div style="font-size:0.8rem; color:var(--text-muted);">Email: <?php echo htmlspecialchars($enr['email']); ?></div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- ==========================================================
        MESSAGES – Chat Bubbles
        ========================================================== -->
        <div id="messagesSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-comments"></i> Direct Messages</div>
                <div style="margin-bottom:1.5rem; background:#f5f3ff; padding:1rem 1.5rem; border-radius:1.2rem;">
                    <h4 style="margin-bottom:0.5rem;"><i class="fas fa-paper-plane"></i> Send New Message</h4>
                    <form method="POST" style="display:flex; flex-wrap:wrap; gap:0.8rem; align-items:flex-end;">
                        <select name="user_id" required style="padding:0.5rem 1rem; border-radius:2rem; border:1px solid #d1d5db; background:white;">
                            <option value="">-- Select User --</option>
                            <?php $all_users = $conn->query("SELECT id, username, email FROM users ORDER BY username"); while($usr = $all_users->fetch_assoc()): ?>
                                <option value="<?php echo $usr['id']; ?>"><?php echo htmlspecialchars($usr['username'] . ' (' . $usr['email'] . ')'); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <textarea name="admin_message_text" rows="2" placeholder="Type your message..." style="flex:1; min-width:200px; border-radius:1rem; border:1px solid #d1d5db; padding:0.5rem;"></textarea>
                        <button type="submit" name="send_message_to_user" class="btn-sm btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
                    </form>
                </div>
                <?php $messages->data_seek(0); while($msg = $messages->fetch_assoc()): ?>
                <div class="message-bubble <?php echo $msg['status']=='unread' ? 'unread' : ''; ?>">
                    <div class="msg-header">
                        <strong><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($msg['username']); ?> (<?php echo htmlspecialchars($msg['email']); ?>)</strong>
                        <span class="status-badge status-<?php echo $msg['status']; ?>"><?php echo $msg['status']; ?></span>
                    </div>
                    <div style="margin:0.5rem 0;"><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                    <?php if($msg['admin_reply']): ?>
                        <div style="background:#dcfce7; padding:0.5rem; border-radius:0.8rem; margin-top:0.5rem;"><strong>Admin Reply:</strong> <?php echo nl2br(htmlspecialchars($msg['admin_reply'])); ?></div>
                    <?php else: ?>
                        <form method="POST" style="margin-top:0.5rem;">
                            <input type="hidden" name="msg_id" value="<?php echo $msg['id']; ?>">
                            <textarea name="admin_reply" rows="2" placeholder="Write your reply..." style="width:100%; border-radius:0.8rem; border:1px solid #d1d5db; padding:0.5rem;"></textarea>
                            <button type="submit" name="reply_message" class="btn-sm btn-primary">Reply</button>
                        </form>
                    <?php endif; ?>
                    <div style="font-size:0.7rem; color:#6b7280; margin-top:0.3rem;"><?php echo date('d M Y, h:i A', strtotime($msg['created_at'])); ?></div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- ==========================================================
        TICKETS – Accordion Cards
        ========================================================== -->
        <div id="ticketsSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-ticket-alt"></i> Support Tickets</div>
                <?php $support_tickets->data_seek(0); while($t = $support_tickets->fetch_assoc()): 
                    $seqNumber = isset($ticket_seq[$t['id']]) ? $ticket_seq[$t['id']] : 0;
                ?>
                <div class="ticket-card">
                    <div class="ticket-header">
                        <div><strong>#<?php echo $t['id']; ?></strong> - <?php echo htmlspecialchars($t['subject']); ?> 
                            <span class="status-badge status-<?php echo $t['status']; ?>"><?php echo $t['status']; ?></span>
                            <?php if ($seqNumber > 0): ?>
                            <span style="background:#dbeafe; padding:0.1rem 0.8rem; border-radius:2rem; font-size:0.7rem; margin-left:0.5rem;">
                                <i class="fas fa-sort-numeric-up"></i> #<?php echo $seqNumber; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.8rem; color:#6b7280;"><?php echo date('d M Y, h:i A', strtotime($t['created_at'])); ?></div>
                    </div>
                    <div><strong>User:</strong> <?php echo htmlspecialchars($t['username']); ?> (<?php echo htmlspecialchars($t['email']); ?>)</div>
                    <div style="margin:0.5rem 0; background:#f1f5f9; padding:0.5rem; border-radius:0.8rem;"><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($t['message'])); ?></div>
                    <?php if($t['admin_reply']): ?>
                        <div style="background:#dcfce7; padding:0.5rem; border-radius:0.8rem;"><strong>Admin Reply:</strong> <?php echo nl2br(htmlspecialchars($t['admin_reply'])); ?></div>
                    <?php else: ?>
                        <form method="POST" style="margin-top:0.5rem;">
                            <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                            <textarea name="admin_reply" rows="2" placeholder="Reply to this ticket..." style="width:100%; border-radius:0.8rem; border:1px solid #d1d5db; padding:0.5rem;"></textarea>
                            <button type="submit" name="reply_ticket" class="btn-sm btn-primary">Reply & Resolve</button>
                        </form>
                    <?php endif; ?>
                    <div style="margin-top:0.5rem; text-align:right;">
                        <button class="btn-sm btn-primary" onclick="openMessageModal(<?php echo $t['user_id']; ?>, '<?php echo htmlspecialchars($t['username']); ?>')"><i class="fas fa-paper-plane"></i> Direct Message</button>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- ==========================================================
        VIDEO COMMENTS – Improved Table
        ========================================================== -->
        <div id="videocommentsSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-comment-dots"></i> Video Comments</div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><i class="fas fa-user"></i> User</th>
                                <th><i class="fas fa-video"></i> Video</th>
                                <th><i class="fas fa-comment"></i> Comment</th>
                                <th>User #</th>
                                <th><i class="fas fa-reply"></i> Admin Reply</th>
                                <th><i class="fas fa-calendar-alt"></i> Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $video_comments->data_seek(0); while($cmt = $video_comments->fetch_assoc()): 
                            $seqNum = isset($comment_seq[$cmt['id']]) ? $comment_seq[$cmt['id']] : 0;
                        ?>
                        <tr>
                            <td><?php echo $cmt['id']; ?></td>
                            <td><?php echo htmlspecialchars($cmt['username']); ?></td>
                            <td><?php echo htmlspecialchars($cmt['video_title'] ?? 'Unknown'); ?></td>
                            <td style="max-width:200px; word-wrap:break-word;"><?php echo nl2br(htmlspecialchars($cmt['comment'])); ?></td>
                            <td><?php if ($seqNum > 0): ?><span class="status-badge" style="background:#e0e7ff; color:#1e1b4b;">#<?php echo $seqNum; ?></span><?php else: ?>—<?php endif; ?></td>
                            <td style="max-width:150px;"><?php echo nl2br(htmlspecialchars($cmt['admin_reply'])); ?></td>
                            <td><?php echo date('d M Y, h:i A', strtotime($cmt['created_at'])); ?></td>
                            <td>
                                <?php if(empty($cmt['admin_reply'])): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="comment_id" value="<?php echo $cmt['id']; ?>">
                                    <textarea name="admin_reply" rows="1" placeholder="Reply..." style="width:120px; border-radius:0.6rem; padding:0.3rem;"></textarea>
                                    <button type="submit" name="reply_video_comment" class="btn-sm btn-primary"><i class="fas fa-reply"></i> Reply</button>
                                </form>
                                <?php else: ?>
                                <span class="status-badge status-approved">Replied</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==========================================================
        COURSES – Grid of Cards
        ========================================================== -->
        <div id="coursesSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-book"></i> Courses Management</div>
                <div class="course-grid">
                    <?php foreach($courses_with_video_link as $course): ?>
                    <div class="course-card" id="course-card-<?php echo $course['id']; ?>">
                        <img src="<?php echo htmlspecialchars($course['image']); ?>" alt="<?php echo htmlspecialchars($course['name']); ?>" onerror="this.src='https://via.placeholder.com/400x160?text=No+Image'">
                        <div class="course-info">
                            <h4><?php echo htmlspecialchars($course['name']); ?></h4>
                            <div class="meta"><?php echo htmlspecialchars($course['duration']); ?></div>
                            <div class="meta" style="font-size:0.8rem;"><?php echo htmlspecialchars(substr($course['description'], 0, 80)); ?>...</div>
                            <div class="meta"><i class="fas fa-video"></i> <?php echo htmlspecialchars($course['first_video_url'] ?? 'No video'); ?></div>
                        </div>
                        <div class="course-actions">
                            <button class="btn-sm btn-primary edit-course-btn" data-id="<?php echo $course['id']; ?>"><i class="fas fa-edit"></i> Edit</button>
                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this course? All associated data will be removed.');">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <button type="submit" name="delete_course" class="btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                        <div id="edit-form-<?php echo $course['id']; ?>" style="display:none; padding:1rem; border-top:1px solid var(--border-light); background:#f5f3ff;">
                            <form method="POST" action="">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem;">
                                    <div><label>Name</label><input type="text" name="course_name" value="<?php echo htmlspecialchars($course['name']); ?>" style="width:100%;"></div>
                                    <div><label>Image URL</label><input type="text" name="course_image" value="<?php echo htmlspecialchars($course['image']); ?>" style="width:100%;"></div>
                                    <div><label>Duration</label><input type="text" name="course_duration" value="<?php echo htmlspecialchars($course['duration']); ?>" style="width:100%;"></div>
                                    <div><label>Video URL</label><input type="text" name="course_video_url" value="<?php echo htmlspecialchars($course['first_video_url'] ?? ''); ?>" style="width:100%;"></div>
                                    <div style="grid-column: span 2;"><label>Description</label><textarea name="course_description" rows="2" style="width:100%;"><?php echo htmlspecialchars($course['description']); ?></textarea></div>
                                </div>
                                <div style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                                    <button type="submit" name="update_course_full" class="btn-sm btn-approve">Save</button>
                                    <button type="button" class="btn-sm btn-reject cancel-edit-card" data-id="<?php echo $course['id']; ?>">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p style="margin-top:0.8rem; font-size:0.8rem; color:#6b7280;"><i class="fas fa-info-circle"></i> Click Edit to modify course details.</p>
            </div>
        </div>

        <!-- ==========================================================
        REPORTS – Enhanced with search and export
        ========================================================== -->
        <div id="reportsSection" style="display:none;">
            <div class="section-card">
                <div class="section-title"><i class="fas fa-chart-pie"></i> Reports & Analytics</div>
                <div style="display:flex; flex-wrap:wrap; gap:0.8rem; margin-bottom:1.5rem;">
                    <form method="POST" target="_blank"><button type="submit" name="export_users" class="btn-sm btn-approve"><i class="fas fa-file-csv"></i> Users CSV</button></form>
                    <form method="POST" target="_blank"><button type="submit" name="export_payments" class="btn-sm btn-approve"><i class="fas fa-file-csv"></i> Payments CSV</button></form>
                    <form method="POST" target="_blank"><button type="submit" name="export_tickets" class="btn-sm btn-approve"><i class="fas fa-file-csv"></i> Tickets CSV</button></form>
                    <form method="POST" target="_blank"><button type="submit" name="export_users_pdf" class="btn-sm btn-primary"><i class="fas fa-file-pdf"></i> Users PDF</button></form>
                    <form method="POST" target="_blank"><button type="submit" name="export_payments_pdf" class="btn-sm btn-primary"><i class="fas fa-file-pdf"></i> Payments PDF</button></form>
                    <form method="POST" target="_blank"><button type="submit" name="export_tickets_pdf" class="btn-sm btn-primary"><i class="fas fa-file-pdf"></i> Tickets PDF</button></form>
                </div>
                <hr style="margin:1.5rem 0; border:0; border-top:1px solid var(--border-light);">

                <h3 style="margin-bottom:0.8rem;"><i class="fas fa-users"></i> User Report</h3>
                <input type="text" id="userSearch" class="search-input" placeholder="Search by username, email, or course...">
                <div class="table-responsive">
                    <table class="modern-table" id="userTable">
                        <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Total Paid</th><th>Courses</th><th>Registered</th><th>Action</th></tr></thead>
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
                                <td><?php echo date('d M Y', strtotime($ur['created_at'])); ?></td>
                                <td><button class="btn-sm btn-primary" onclick="viewUserDetails(<?php echo $ur['id']; ?>)"><i class="fas fa-file-invoice"></i> Full Report</button></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <hr style="margin:1.5rem 0; border:0; border-top:1px solid var(--border-light);">
                <h3 style="margin-bottom:0.8rem;"><i class="fas fa-dollar-sign"></i> Payment Report</h3>
                <input type="text" id="paymentSearch" class="search-input" placeholder="Search by username, course, or transaction...">
                <div class="table-responsive">
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
                                <td><?php echo date('d M Y, h:i A', strtotime($pr['created_at'])); ?></td>
                                <td><button class="btn-sm btn-primary" onclick="viewPaymentDetails(<?php echo $pr['id']; ?>, '<?php echo htmlspecialchars($pr['username']); ?>', '<?php echo addslashes($course_names); ?>', <?php echo $pr['amount']; ?>, '<?php echo $pr['transaction_id']; ?>', '<?php echo $pr['created_at']; ?>')"><i class="fas fa-receipt"></i> Receipt</button></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================
MODALS (unchanged)
========================================================== -->
<div id="userDetailModal" class="modal">
    <div class="modal-content">
        <div class="modal-actions">
            <button class="btn-sm btn-print" onclick="printUserReport()"><i class="fas fa-print"></i> Print / PDF</button>
            <button class="btn-sm btn-danger" onclick="closeUserModal()">Close</button>
        </div>
        <div id="userDetailHtml"></div>
    </div>
</div>

<div id="paymentDetailModal" class="modal">
    <div class="modal-content" id="paymentDetailContent">
        <div class="modal-actions">
            <button class="btn-sm btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print / PDF</button>
            <button class="btn-sm btn-danger" onclick="closePaymentModal()">Close</button>
        </div>
        <div id="paymentDetailHtml"></div>
    </div>
</div>

<div id="messageModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-paper-plane"></i> Send Message to User</h3>
        <form method="POST">
            <input type="hidden" name="user_id" id="modal_user_id">
            <p>Sending to: <strong id="modal_username"></strong></p>
            <textarea name="direct_message" rows="4" placeholder="Type your message here..." style="width:100%; border-radius:0.8rem; border:1px solid #d1d5db; padding:0.5rem; margin:0.5rem 0;"></textarea>
            <div style="display:flex; gap:0.8rem;">
                <button type="submit" name="send_direct_message" class="btn-sm btn-approve">Send</button>
                <button type="button" class="btn-sm btn-reject" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ============================================================
    // SECTION SWITCHING
    // ============================================================
    function showSection(section) {
        const sections = ['dashboard','users','payments','enrollments','messages','tickets','videocomments','courses','reports'];
        sections.forEach(s => {
            document.getElementById(s + 'Section').style.display = 'none';
        });
        document.getElementById(section + 'Section').style.display = 'block';
        const titles = {
            dashboard: 'Dashboard',
            users: 'Users',
            payments: 'Payment Requests',
            enrollments: 'Enrollments',
            messages: 'Direct Messages',
            tickets: 'Support Tickets',
            videocomments: 'Video Comments',
            courses: 'Courses Management',
            reports: 'Reports'
        };
        document.getElementById('mainTitle').innerText = titles[section] || section;
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        document.querySelector(`.nav-item[onclick="showSection('${section}')"]`).classList.add('active');
        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.remove('open');
        }
    }

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }

    // Courses Edit/Cancel for card view
    document.querySelectorAll('.edit-course-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            let courseId = this.getAttribute('data-id');
            let formDiv = document.getElementById('edit-form-' + courseId);
            if (formDiv.style.display === 'none') {
                formDiv.style.display = 'block';
            } else {
                formDiv.style.display = 'none';
            }
        });
    });
    document.querySelectorAll('.cancel-edit-card').forEach(btn => {
        btn.addEventListener('click', function() {
            let courseId = this.getAttribute('data-id');
            document.getElementById('edit-form-' + courseId).style.display = 'none';
        });
    });

    // Bulk Select / Confirm
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function(e) {
            document.querySelectorAll('input[name="selected_ids[]"]').forEach(cb => cb.checked = e.target.checked);
        });
    }
    function confirmBulk(action) {
        let selected = document.querySelectorAll('input[name="selected_ids[]"]:checked').length;
        if (selected === 0) { alert('No requests selected'); return false; }
        return confirm(`Are you sure you want to ${action} ${selected} request(s)?`);
    }

    // Modals
    function openMessageModal(userId, username) {
        document.getElementById('modal_user_id').value = userId;
        document.getElementById('modal_username').innerText = username;
        document.getElementById('messageModal').style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('messageModal').style.display = 'none';
    }

    // ============================================================
    // USER DETAIL VIEW – NEW DESIGN with user-detail-card
    // ============================================================
    function viewUserDetails(userId) {
        fetch('?action=get_user_details&user_id=' + userId)
            .then(res => res.json())
            .then(data => {
                if (data.error) { alert(data.error); return; }
                let html = `
                    <div class="user-detail-card">
                        <div class="ud-header">
                            <div class="ud-avatar">${data.full_name ? data.full_name.substr(0,2).toUpperCase() : 'U'}</div>
                            <div class="ud-info">
                                <h2>${data.full_name}</h2>
                                <p><i class="fas fa-user"></i> @${data.username}</p>
                                <p><i class="fas fa-envelope"></i> ${data.email}</p>
                                <p><i class="fas fa-calendar-check"></i> Registered: ${new Date(data.created_at).toLocaleString()}</p>
                            </div>
                        </div>
                        <hr class="ud-divider">
                        <div class="ud-purchases">
                            <h4><i class="fas fa-shopping-cart"></i> Purchase History</h4>
                            ${data.payments.length === 0 ? '<p style="color:var(--text-muted);">No purchases yet.</p>' : `
                                <table>
                                    <thead><tr><th>Transaction ID</th><th>Course(s)</th><th>Amount</th><th>Date</th></tr></thead>
                                    <tbody>
                                        ${data.payments.map(p => `
                                            <tr>
                                                <td>${p.transaction_id}</td>
                                                <td>${p.courses}</td>
                                                <td>$${p.amount}</td>
                                                <td>${new Date(p.date).toLocaleString()}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            `}
                        </div>
                        <div style="margin-top:1.5rem; text-align:right; color:var(--text-muted); font-size:0.8rem;">
                            <i class="fas fa-print"></i> Printable version available via Print / PDF
                        </div>
                    </div>
                `;
                document.getElementById('userDetailHtml').innerHTML = html;
                document.getElementById('userDetailModal').style.display = 'flex';
            })
            .catch(err => alert('Error loading user details: ' + err));
    }

    function printUserReport() {
        window.print();
    }

    function closeUserModal() {
        document.getElementById('userDetailModal').style.display = 'none';
    }

    // ============================================================
    // PAYMENT DETAIL VIEW – NEW DESIGN with user-detail-card
    // ============================================================
    function viewPaymentDetails(id, username, courseNames, amount, transactionId, date) {
        let html = `
            <div class="user-detail-card">
                <div class="ud-header">
                    <div class="ud-avatar" style="background:#10b981;">$</div>
                    <div class="ud-info">
                        <h2>Payment Receipt</h2>
                        <p><i class="fas fa-receipt"></i> Receipt #${id}</p>
                        <p><i class="fas fa-user"></i> ${username}</p>
                        <p><i class="fas fa-book"></i> ${courseNames}</p>
                        <p><i class="fas fa-dollar-sign"></i> $${amount}</p>
                        <p><i class="fas fa-hashtag"></i> Transaction: ${transactionId}</p>
                        <p><i class="fas fa-calendar"></i> ${date}</p>
                    </div>
                </div>
                <hr class="ud-divider">
                <div style="text-align:center; padding:1rem 0; color:var(--text-muted);">
                    <i class="fas fa-check-circle" style="color:#10b981;"></i> Payment approved. Thank you!
                </div>
            </div>
        `;
        document.getElementById('paymentDetailHtml').innerHTML = html;
        document.getElementById('paymentDetailModal').style.display = 'flex';
    }
    function closePaymentModal() {
        document.getElementById('paymentDetailModal').style.display = 'none';
    }

    // Search filters
    document.getElementById('userSearch')?.addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        document.querySelectorAll('#userTable tbody tr').forEach(row => {
            let text = row.getAttribute('data-search') || '';
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
    document.getElementById('paymentSearch')?.addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        document.querySelectorAll('#paymentTable tbody tr').forEach(row => {
            let text = row.getAttribute('data-search') || '';
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });

    // Charts
    new Chart(document.getElementById('statusChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Pending','Approved','Rejected'],
            datasets: [{
                data: [<?php echo $stats['pending']; ?>,<?php echo $stats['approved']; ?>,<?php echo $stats['rejected']; ?>],
                backgroundColor: ['#f59e0b','#10b981','#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
    new Chart(document.getElementById('incomeChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['Total Approved Payments'],
            datasets: [{
                label: 'Income ($)',
                data: [<?php echo $total_income; ?>],
                backgroundColor: '#7c3aed',
                borderRadius: 8,
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'USD' } }
            }
        }
    });

    // Initial section
    const urlParams = new URLSearchParams(window.location.search);
    const sectionParam = urlParams.get('section');
    const validSections = ['dashboard','users','payments','enrollments','messages','tickets','videocomments','courses','reports'];
    if (sectionParam && validSections.includes(sectionParam)) {
        showSection(sectionParam);
    } else {
        showSection('dashboard');
    }
</script>
<?php if(isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; } ?>
</body>
</html>
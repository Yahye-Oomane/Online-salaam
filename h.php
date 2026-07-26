<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'hayaandb10');
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);

// ===== CREATE / ALTER TABLES =====
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
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_number VARCHAR(50) DEFAULT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
)");
$check = $conn->query("SHOW COLUMNS FROM payment_requests LIKE 'course_ids'");
if ($check && $check->num_rows == 0) {
    $conn->query("ALTER TABLE payment_requests ADD COLUMN course_ids TEXT NULL AFTER course_id");
}
$check2 = $conn->query("SHOW COLUMNS FROM payment_requests LIKE 'payment_method'");
if ($check2 && $check2->num_rows == 0) {
    $conn->query("ALTER TABLE payment_requests ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL, ADD COLUMN payment_number VARCHAR(50) DEFAULT NULL");
}
$conn->query("ALTER TABLE payment_requests MODIFY course_id INT NULL");

$conn->query("CREATE TABLE IF NOT EXISTS admin_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    admin_reply TEXT DEFAULT NULL,
    status ENUM('unread','read','replied') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_course (user_id, course_id)
)");
$conn->query("CREATE TABLE IF NOT EXISTS course_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    embed_url VARCHAR(500) NOT NULL,
    video_order INT DEFAULT 0,
    pdf_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
)");
$colCheck = $conn->query("SHOW COLUMNS FROM course_videos LIKE 'pdf_url'");
if ($colCheck && $colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE course_videos ADD COLUMN pdf_url VARCHAR(500) DEFAULT NULL AFTER embed_url");
}

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
$conn->query("CREATE TABLE IF NOT EXISTS user_video_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    progress_percent INT DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY user_video (user_id, video_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES course_videos(id) ON DELETE CASCADE
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
$conn->query("CREATE TABLE IF NOT EXISTS knowledge_base (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS reminders_sent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    reminder_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES course_videos(id) ON DELETE CASCADE
)");
$conn->query("CREATE TABLE IF NOT EXISTS broadcast_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL DEFAULT 'Announcement',
    message TEXT NOT NULL,
    created_by VARCHAR(100) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    message_type ENUM('info','warning','success','danger') DEFAULT 'info'
)");
$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(50) DEFAULT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$admin_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$conn->query("INSERT IGNORE INTO users (id, username, email, password, full_name, is_admin) VALUES (1, 'admin', 'admin@hayaan.com', '$admin_hash', 'System Admin', 1)");

$youtube_embed = "https://www.youtube.com/embed/CIRGjwYgdT4";

// ===== 12 COURSES WITH 8 VIDEOS EACH =====
$course_list = array(
    1 => array(
        'id' => 1,
        'name' => 'Web Development Bootcamp 2024',
        'description' => 'Learn HTML, CSS, JavaScript, React and Node.js from scratch. Build 10+ real-world projects.',
        'price' => 49.99,
        'lessons' => 8,
        'duration' => '20 Hours',
        'level' => 'Beginner',
        'instructor' => 'Ahmed Mohamed',
        'rating' => 4.8,
        'students' => 15420,
        'image' => 'https://images.unsplash.com/photo-1627398242454-45a1465c2479?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'category' => 'Web Development',
        'videos' => array(
            array('title' => '1 HTML5 Basics', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '2 CSS3 Styling & Animations', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '3 JavaScript Fundamentals', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '4 Building Modern Websites', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '5 React Introduction', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '6 Node.js Basics', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '7 Express & REST APIs', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '8 Full Stack Project', 'src' => $youtube_embed, 'pdf' => '')
        )
    ),
    2 => array(
        'id' => 2,
        'name' => 'Complete Python Masterclass',
        'description' => 'Master Python for data science, automation, and web development.',
        'price' => 39.99,
        'lessons' => 8,
        'duration' => '15 Hours',
        'level' => 'All Levels',
        'instructor' => 'Qualified Expert',
        'rating' => 4.9,
        'students' => 23450,
        'image' => 'https://images.unsplash.com/photo-1555949963-aa79dcee981c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'category' => 'Data Science',
        'videos' => array(
            array('title' => '1 Python Setup & Basics', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '2 Variables & Data Types', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '3 Functions & Loops', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '4 Projects & Applications', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '5 Data Structures', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '6 File Handling & Modules', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '7 OOP in Python', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '8 Final Project', 'src' => $youtube_embed, 'pdf' => '')
        )
    ),
    3 => array(
        'id' => 3,
        'name' => 'Database Design & SQL',
        'description' => 'Learn SQL, MySQL, and database design principles.',
        'price' => 34.99,
        'lessons' => 8,
        'duration' => '12 Hours',
        'level' => 'Intermediate',
        'instructor' => 'Omar Hassan',
        'rating' => 4.7,
        'students' => 12340,
        'image' => 'https://images.unsplash.com/photo-1542744095-fcf48d80b0fd?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'category' => 'Database',
        'videos' => array(
            array('title' => '1 Database Introduction', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '2 SQL Queries', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '3 Database Design', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '4 Advanced Topics', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '5 Joins & Subqueries', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '6 Indexing & Optimization', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '7 Stored Procedures', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '8 Database Administration', 'src' => $youtube_embed, 'pdf' => '')
        )
    ),
    4 => array(
        'id' => 4,
        'name' => 'Business Fundamentals',
        'description' => 'Essential business skills for entrepreneurs and managers.',
        'price' => 29.99,
        'lessons' => 8,
        'duration' => '10 Hours',
        'level' => 'Beginner',
        'instructor' => 'Hawa Ahmed',
        'rating' => 4.5,
        'students' => 8760,
        'image' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'category' => 'Business',
        'videos' => array(
            array('title' => '1 Business Planning', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '2 Marketing Strategies', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '3 Financial Basics', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '4 Management Skills', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '5 Operations Management', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '6 Human Resources', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '7 Business Ethics', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '8 Entrepreneurship', 'src' => $youtube_embed, 'pdf' => '')
        )
    ),
    5 => array(
        'id' => 5,
        'name' => 'Digital Marketing Pro',
        'description' => 'Master SEO, social media, email marketing, and online advertising.',
        'price' => 24.99,
        'lessons' => 8,
        'duration' => '8 Hours',
        'level' => 'All Levels',
        'instructor' => 'Khadar Yusuf',
        'rating' => 4.6,
        'students' => 10980,
        'image' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'category' => 'Digital Marketing',
        'videos' => array(
            array('title' => '1 SEO Basics', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '2 Social Media Marketing', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '3 Email Marketing', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '4 Online Advertising', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '5 Content Marketing', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '6 Analytics & Reporting', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '7 Marketing Automation', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '8 Advanced Strategies', 'src' => $youtube_embed, 'pdf' => '')
        )
    ),
    6 => array(
        'id' => 6,
        'name' => 'Graphic Design Masterclass',
        'description' => 'Learn graphic design principles, Photoshop, Illustrator, and create stunning visuals.',
        'price' => 44.99,
        'lessons' => 8,
        'duration' => '14 Hours',
        'level' => 'Beginner to Advanced',
        'instructor' => 'Naima Abdi',
        'rating' => 4.8,
        'students' => 15430,
        'image' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'category' => 'Graphic Design',
        'videos' => array(
            array('title' => '1 Design Principles', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '2 Photoshop Basics', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '3 Illustrator Tutorial', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '4 Project Design', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '5 Typography', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '6 Color Theory', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '7 Advanced Photoshop', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '8 Portfolio Building', 'src' => $youtube_embed, 'pdf' => '')
        )
    ),
    7 => array(
        'id' => 7,
        'name' => 'Mobile App Development with Flutter',
        'description' => 'Build cross-platform mobile apps using Flutter and Dart. Learn from basics to advanced topics.',
        'price' => 54.99,
        'lessons' => 8,
        'duration' => '22 Hours',
        'level' => 'Intermediate',
        'instructor' => 'Mohamed Ali',
        'rating' => 4.9,
        'students' => 8765,
        'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'category' => 'Mobile Development',
        'videos' => array(
            array('title' => '1 Flutter Setup & First App', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '2 Dart Basics', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '3 Widgets & Layouts', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '4 State Management', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '5 Navigation & Routing', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '6 API Integration', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '7 Firebase Integration', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '8 App Deployment', 'src' => $youtube_embed, 'pdf' => '')
        )
    ),
    8 => array(
        'id' => 8,
        'name' => 'Data Science & Machine Learning',
        'description' => 'Master data science, machine learning, and AI with Python. Includes projects and real-world datasets.',
        'price' => 59.99,
        'lessons' => 8,
        'duration' => '25 Hours',
        'level' => 'Advanced',
        'instructor' => 'Dr. Fatima Hassan',
        'rating' => 4.9,
        'students' => 12345,
        'image' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'category' => 'Data Science',
        'videos' => array(
            array('title' => '1 Data Science Overview', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '2 Python for Data Science', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '3 Data Analysis with Pandas', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '4 Data Visualization', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '5 Machine Learning Basics', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '6 Supervised Learning', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '7 Unsupervised Learning', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '8 Deep Learning & AI', 'src' => $youtube_embed, 'pdf' => '')
        )
    ),
    9 => array(
        'id' => 9,
        'name' => 'Cybersecurity Essentials',
        'description' => 'Learn cybersecurity fundamentals, ethical hacking, network security, and risk management.',
        'price' => 49.99,
        'lessons' => 8,
        'duration' => '18 Hours',
        'level' => 'Intermediate',
        'instructor' => 'Ahmed Warsame',
        'rating' => 4.7,
        'students' => 9876,
        'image' => 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'category' => 'Cybersecurity',
        'videos' => array(
            array('title' => '1 Cybersecurity Fundamentals', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '2 Network Security', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '3 Ethical Hacking Intro', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '4 Penetration Testing', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '5 Cryptography', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '6 Security Policies', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '7 Incident Response', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '8 Risk Management', 'src' => $youtube_embed, 'pdf' => '')
        )
    ),
    10 => array(
        'id' => 10,
        'name' => 'UI/UX Design Masterclass',
        'description' => 'Learn user interface and user experience design principles. Master Figma, Adobe XD, and user research.',
        'price' => 39.99,
        'lessons' => 8,
        'duration' => '16 Hours',
        'level' => 'Beginner to Advanced',
        'instructor' => 'Safia Osman',
        'rating' => 4.8,
        'students' => 11234,
        'image' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'category' => 'Design',
        'videos' => array(
            array('title' => '1 UX Design Principles', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '2 User Research', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '3 Wireframing & Prototyping', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '4 UI Design Basics', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '5 Figma Mastery', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '6 Design Systems', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '7 Usability Testing', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '8 Final Project', 'src' => $youtube_embed, 'pdf' => '')
        )
    ),
    11 => array(
        'id' => 11,
        'name' => 'Cloud Computing with AWS',
        'description' => 'Master Amazon Web Services, cloud architecture, and deployment. Prepare for AWS certifications.',
        'price' => 64.99,
        'lessons' => 8,
        'duration' => '24 Hours',
        'level' => 'Intermediate',
        'instructor' => 'Abdirahman Yusuf',
        'rating' => 4.9,
        'students' => 7654,
        'image' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'category' => 'Cloud Computing',
        'videos' => array(
            array('title' => '1 Cloud Computing Overview', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '2 AWS Account Setup', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '3 EC2 & Compute Services', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '4 S3 & Storage', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '5 Networking (VPC)', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '6 RDS & Databases', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '7 Lambda & Serverless', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '8 AWS Security & IAM', 'src' => $youtube_embed, 'pdf' => '')
        )
    ),
    12 => array(
        'id' => 12,
        'name' => 'Project Management Professional',
        'description' => 'Master project management with Agile, Scrum, and traditional methodologies. Prepare for PMP certification.',
        'price' => 44.99,
        'lessons' => 8,
        'duration' => '20 Hours',
        'level' => 'All Levels',
        'instructor' => 'Hassan Ahmed',
        'rating' => 4.6,
        'students' => 6543,
        'image' => 'https://images.unsplash.com/photo-1557804506-669a67965ba0?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80',
        'category' => 'Business',
        'videos' => array(
            array('title' => '1 Project Management Intro', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '2 Project Lifecycle', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '3 Agile & Scrum', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '4 Project Planning', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '5 Risk Management', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '6 Quality Management', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '7 Budgeting & Cost', 'src' => $youtube_embed, 'pdf' => ''),
            array('title' => '8 Project Closure', 'src' => $youtube_embed, 'pdf' => '')
        )
    )
);

function syncCoursesAndVideos($conn, $course_list) {
    foreach ($course_list as $course_id => $course) {
        $name = $conn->real_escape_string($course['name']);
        $desc = $conn->real_escape_string($course['description']);
        $price = floatval($course['price']);
        $lessons = intval($course['lessons']);
        $duration = $conn->real_escape_string($course['duration']);
        $level = $conn->real_escape_string($course['level']);
        $instructor = $conn->real_escape_string($course['instructor']);
        $rating = floatval($course['rating']);
        $students = intval($course['students']);
        $image = $conn->real_escape_string($course['image']);
        $category = $conn->real_escape_string($course['category']);
        
        $sql = "INSERT IGNORE INTO courses (id, name, description, price, lessons, duration, level, instructor, rating, students, image, category) 
                VALUES ($course_id, '$name', '$desc', $price, $lessons, '$duration', '$level', '$instructor', $rating, $students, '$image', '$category')";
        $conn->query($sql);
    }
    foreach ($course_list as $course_id => $course) {
        $order = 1;
        foreach ($course['videos'] as $video) {
            $title = $conn->real_escape_string($video['title']);
            $embed = $conn->real_escape_string($video['src']);
            $pdf = isset($video['pdf']) ? $conn->real_escape_string($video['pdf']) : '';
            $check = $conn->query("SELECT id FROM course_videos WHERE course_id = $course_id AND title = '$title' LIMIT 1");
            if ($check->num_rows == 0) {
                $conn->query("INSERT INTO course_videos (course_id, title, embed_url, pdf_url, video_order) 
                              VALUES ($course_id, '$title', '$embed', '$pdf', $order)");
            }
            $order++;
        }
    }
}
syncCoursesAndVideos($conn, $course_list);

// ========== FUNCTIONS (unchanged) ==========
function registerUser($conn, $data) {
    $username = $conn->real_escape_string($data['username']);
    $email = $conn->real_escape_string($data['email']);
    $full_name = $conn->real_escape_string($data['full_name']);
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, full_name) VALUES ('$username','$email','$password','$full_name')";
    return $conn->query($sql) ? true : $conn->error;
}
function loginUser($conn, $email, $password) {
    $email = $conn->real_escape_string($email);
    $result = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($result && $result->num_rows) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            return true;
        }
    }
    return false;
}
function updateUser($conn, $user_id, $data) {
    $full_name = $conn->real_escape_string($data['full_name']);
    $username = $conn->real_escape_string($data['username']);
    $email = $conn->real_escape_string($data['email']);
    $updates = "full_name='$full_name', username='$username', email='$email'";
    if (!empty($data['password'])) {
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $updates .= ", password='$password'";
    }
    $sql = "UPDATE users SET $updates WHERE id=$user_id";
    if ($conn->query($sql)) {
        $res = $conn->query("SELECT * FROM users WHERE id=$user_id");
        $_SESSION['user'] = $res->fetch_assoc();
        return true;
    }
    return false;
}
function hasAccess($conn, $user_id, $course_id) {
    $res = $conn->query("SELECT id FROM payment_requests WHERE user_id=$user_id AND status='approved' AND (course_id=$course_id OR FIND_IN_SET($course_id, course_ids))");
    return $res->num_rows > 0;
}
function hasPendingRequest($conn, $user_id, $course_id) {
    $res = $conn->query("SELECT id FROM payment_requests WHERE user_id=$user_id AND status='pending' AND (course_id=$course_id OR FIND_IN_SET($course_id, course_ids))");
    return $res->num_rows > 0;
}
function getUserPaymentRequests($conn, $user_id) {
    global $course_list;
    $res = $conn->query("SELECT * FROM payment_requests WHERE user_id=$user_id ORDER BY created_at DESC");
    $list = array();
    while($row = $res->fetch_assoc()) {
        if ($row['course_ids']) {
            $ids = explode(',', $row['course_ids']);
            $names = array();
            foreach ($ids as $id) {
                if (isset($course_list[$id])) $names[] = $course_list[$id]['name'];
            }
            $row['course_name'] = implode(', ', $names);
        } elseif ($row['course_id'] && isset($course_list[$row['course_id']])) {
            $row['course_name'] = $course_list[$row['course_id']]['name'];
        } else {
            $row['course_name'] = 'Unknown Course';
        }
        $list[] = $row;
    }
    return $list;
}
function createPaymentRequest($conn, $user_id, $course_ids, $fullname, $email, $txn_id, $amount, $payment_method, $payment_number) {
    $fullname = $conn->real_escape_string($fullname);
    $email = $conn->real_escape_string($email);
    $txn_id = $conn->real_escape_string($txn_id);
    $payment_method = $conn->real_escape_string($payment_method);
    $payment_number = $conn->real_escape_string($payment_number);
    $amount = floatval($amount);
    
    if (is_array($course_ids)) {
        $ids_str = implode(',', $course_ids);
        $sql = "INSERT INTO payment_requests (user_id, course_ids, course_id, fullname, email, transaction_id, amount, payment_method, payment_number, status) 
                VALUES ($user_id, '$ids_str', NULL, '$fullname', '$email', '$txn_id', $amount, '$payment_method', '$payment_number', 'pending')";
    } else {
        $sql = "INSERT INTO payment_requests (user_id, course_id, fullname, email, transaction_id, amount, payment_method, payment_number, status) 
                VALUES ($user_id, $course_ids, '$fullname', '$email', '$txn_id', $amount, '$payment_method', '$payment_number', 'pending')";
    }
    return $conn->query($sql);
}
function sendAdminMessage($conn, $user_id, $message) {
    $message = $conn->real_escape_string($message);
    $sql = "INSERT INTO admin_messages (user_id, message) VALUES ($user_id, '$message')";
    return $conn->query($sql);
}
function getUserMessages($conn, $user_id) {
    $res = $conn->query("SELECT * FROM admin_messages WHERE user_id=$user_id ORDER BY created_at DESC");
    $msgs = array();
    while($row = $res->fetch_assoc()) $msgs[] = $row;
    return $msgs;
}
function getUnreadAdminRepliesCount($conn, $user_id) {
    $res = $conn->query("SELECT COUNT(*) as cnt FROM admin_messages WHERE user_id=$user_id AND admin_reply IS NOT NULL AND status='unread'");
    $row = $res->fetch_assoc();
    $count = $row['cnt'];
    $res2 = $conn->query("SELECT COUNT(*) as cnt2 FROM payment_requests WHERE user_id=$user_id AND admin_message IS NOT NULL AND updated_at > (SELECT IFNULL(MAX(created_at), '1970-01-01') FROM admin_messages WHERE user_id=$user_id AND admin_reply IS NOT NULL)");
    $row2 = $res2->fetch_assoc();
    return $count + $row2['cnt2'];
}
function markAdminRepliesAsRead($conn, $user_id) {
    $conn->query("UPDATE admin_messages SET status='read' WHERE user_id=$user_id AND admin_reply IS NOT NULL AND status='unread'");
}
function addToCart($conn, $user_id, $course_id) {
    $course_id = intval($course_id);
    $conn->query("INSERT IGNORE INTO cart (user_id, course_id) VALUES ($user_id, $course_id)");
}
function removeFromCart($conn, $user_id, $course_id) {
    $course_id = intval($course_id);
    $conn->query("DELETE FROM cart WHERE user_id=$user_id AND course_id=$course_id");
}
function getCartItems($conn, $user_id) {
    $res = $conn->query("SELECT course_id FROM cart WHERE user_id=$user_id");
    $items = array();
    while ($row = $res->fetch_assoc()) $items[] = $row['course_id'];
    return $items;
}
function clearCart($conn, $user_id) {
    $conn->query("DELETE FROM cart WHERE user_id=$user_id");
}
function getVideosForCourse($conn, $course_id) {
    $res = $conn->query("SELECT * FROM course_videos WHERE course_id=$course_id ORDER BY video_order LIMIT 8");
    return $res->fetch_all(MYSQLI_ASSOC);
}
function getVideoById($conn, $video_id) {
    $res = $conn->query("SELECT * FROM course_videos WHERE id=$video_id");
    return $res->fetch_assoc();
}
function getUserProgress($conn, $user_id, $video_id) {
    $res = $conn->query("SELECT progress_percent, completed FROM user_video_progress WHERE user_id=$user_id AND video_id=$video_id");
    if($res->num_rows) return $res->fetch_assoc();
    return array('progress_percent'=>0, 'completed'=>false);
}
function updateVideoProgress($conn, $user_id, $video_id, $percent, $completed=false) {
    $completed_int = $completed ? 1 : 0;
    $conn->query("INSERT INTO user_video_progress (user_id, video_id, progress_percent, completed) VALUES ($user_id, $video_id, $percent, $completed_int)
                  ON DUPLICATE KEY UPDATE progress_percent=$percent, completed=$completed_int, last_accessed=NOW()");
}
function getCourseProgress($conn, $user_id, $course_id) {
    $videos = getVideosForCourse($conn, $course_id);
    if(count($videos)==0) return 0;
    $completed=0;
    foreach($videos as $v){
        $prog = getUserProgress($conn, $user_id, $v['id']);
        if($prog['completed']) $completed++;
    }
    return round(($completed/count($videos))*100);
}
function addVideoComment($conn, $video_id, $user_id, $comment) {
    $comment = $conn->real_escape_string($comment);
    return $conn->query("INSERT INTO video_comments (video_id, user_id, comment) VALUES ($video_id, $user_id, '$comment')");
}
function getVideoComments($conn, $video_id) {
    $res = $conn->query("SELECT c.*, u.username, u.full_name FROM video_comments c JOIN users u ON c.user_id=u.id WHERE c.video_id=$video_id ORDER BY c.created_at DESC");
    return $res->fetch_all(MYSQLI_ASSOC);
}
function reactToComment($conn, $comment_id, $reaction) {
    $allowed = array('like','love');
    if(in_array($reaction, $allowed))
        $conn->query("UPDATE video_comments SET reaction_type='$reaction' WHERE id=$comment_id");
}
function replyToComment($conn, $comment_id, $reply) {
    $reply = $conn->real_escape_string($reply);
    $conn->query("UPDATE video_comments SET admin_reply='$reply' WHERE id=$comment_id");
}
function createTicket($conn, $user_id, $subject, $message) {
    $subject = $conn->real_escape_string($subject);
    $message = $conn->real_escape_string($message);
    return $conn->query("INSERT INTO support_tickets (user_id, subject, message) VALUES ($user_id, '$subject', '$message')");
}
function getUserTickets($conn, $user_id) {
    $res = $conn->query("SELECT * FROM support_tickets WHERE user_id=$user_id ORDER BY created_at DESC");
    return $res->fetch_all(MYSQLI_ASSOC);
}
function sendReminderIfNeeded($conn, $user_id) {
    $sql = "SELECT p.video_id, v.title, p.last_accessed 
            FROM user_video_progress p 
            JOIN course_videos v ON p.video_id=v.id 
            WHERE p.user_id=$user_id AND p.completed=0 AND p.last_accessed < DATE_SUB(NOW(), INTERVAL 3 DAY)
            AND NOT EXISTS (SELECT 1 FROM reminders_sent r WHERE r.user_id=$user_id AND r.video_id=p.video_id AND r.reminder_date=CURDATE())";
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()){
        $msg = "Xusuusin: Video '{$row['title']}' aad ma dhammaysan. Fadlan sii wad barashada.";
        $escaped_msg = $conn->real_escape_string($msg);
        $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ($user_id, '$escaped_msg')");
        $conn->query("INSERT INTO reminders_sent (user_id, video_id, reminder_date) VALUES ($user_id, {$row['video_id']}, CURDATE())");
    }
}
function getActiveBroadcast($conn) {
    $res = $conn->query("SELECT * FROM broadcast_messages WHERE is_active=1 ORDER BY created_at DESC LIMIT 1");
    return $res->fetch_assoc();
}
function isAdmin($user) {
    return $user && isset($user['is_admin']) && $user['is_admin'] == 1;
}
function isValidYouTubeEmbed($url) {
    return (strpos($url, 'youtube.com/embed/') !== false) || (strpos($url, 'youtu.be/') !== false);
}

$contact_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_contact'])) {
    $name = $conn->real_escape_string(trim($_POST['contact_name']));
    $email = $conn->real_escape_string(trim($_POST['contact_email']));
    $whatsapp = $conn->real_escape_string(trim($_POST['contact_whatsapp']));
    $msg = $conn->real_escape_string(trim($_POST['contact_message']));
    if (!empty($name) && !empty($email) && !empty($msg)) {
        $sql = "INSERT INTO contact_messages (name, email, whatsapp, message) VALUES ('$name', '$email', '$whatsapp', '$msg')";
        if ($conn->query($sql)) {
            $contact_message = "Fariintaada waa la soo diray. Waxaan kula soo xidhiidhi doonnaa goor dhow.";
        } else {
            $contact_message = "Khalad ayaa dhacay, fadlan hadana isku day.";
        }
    } else {
        $contact_message = "Fadlan buuxi dhammaan goobaha loo baahan yahay (Magaca, Emailka, Fariinta).";
    }
}

$message = '';
$receiptData = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['register'])) {
        $res = registerUser($conn, $_POST);
        if ($res === true) {
            loginUser($conn, $_POST['email'], $_POST['password']);
            $message = "Account created! You are now logged in.";
            header("Location: new.php?registered=1");
            exit;
        } else $message = "Error: $res";
    }
    elseif (isset($_POST['login'])) {
        if (loginUser($conn, $_POST['email'], $_POST['password'])) {
            $message = "Login successful!";
            header("Location: new.php");
            exit;
        } else $message = "Wrong email or password!";
    }
    elseif (isset($_POST['submit_payment_request']) && isset($_SESSION['user'])) {
        $uid = $_SESSION['user']['id'];
        $full = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $txn = trim($_POST['transaction_id']);
        $amt = floatval($_POST['amount']);
        $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
        $payment_number = isset($_POST['payment_number']) ? trim($_POST['payment_number']) : '';
        
        if (isset($_POST['cart_checkout']) && $_POST['cart_checkout'] == 1) {
            $cart_items = getCartItems($conn, $uid);
            if (count($cart_items) == 0) $message = "Cart is empty.";
            else {
                $has_pending = false;
                foreach ($cart_items as $cid) if (hasPendingRequest($conn, $uid, $cid)) $has_pending = true;
                if ($has_pending) $message = "You have a pending request for one or more courses.";
                else {
                    if (createPaymentRequest($conn, $uid, $cart_items, $full, $email, $txn, $amt, $payment_method, $payment_number)) {
                        clearCart($conn, $uid);
                        $courseNames = [];
                        foreach ($cart_items as $cid) {
                            if (isset($course_list[$cid])) $courseNames[] = $course_list[$cid]['name'];
                        }
                        $receiptInfo = [
                            'payment_method' => $payment_method,
                            'payment_number' => $payment_number,
                            'amount' => $amt,
                            'transaction_id' => $txn,
                            'course_name' => implode(', ', $courseNames)
                        ];
                        $_SESSION['payment_receipt'] = $receiptInfo;
                        $message = "Payment request sent for " . count($cart_items) . " courses! Admin will review.";
                    } else $message = "Error sending request.";
                }
            }
        } else {
            $cid = intval($_POST['course_id']);
            if (hasPendingRequest($conn, $uid, $cid)) $message = "You already have a pending request for this course.";
            else {
                if (createPaymentRequest($conn, $uid, $cid, $full, $email, $txn, $amt, $payment_method, $payment_number)) {
                    $receiptInfo = [
                        'payment_method' => $payment_method,
                        'payment_number' => $payment_number,
                        'amount' => $amt,
                        'transaction_id' => $txn,
                        'course_name' => isset($course_list[$cid]) ? $course_list[$cid]['name'] : 'Course'
                    ];
                    $_SESSION['payment_receipt'] = $receiptInfo;
                    $message = "Request sent! Admin will review it.";
                } else $message = "Error sending request.";
            }
        }
    }
    elseif (isset($_POST['send_message']) && isset($_SESSION['user'])) {
        $msg = trim($_POST['message']);
        if (!empty($msg)) {
            if (sendAdminMessage($conn, $_SESSION['user']['id'], $msg)) $message = "Message sent to admin.";
            else $message = "Failed to send message.";
        } else $message = "Please write a message.";
    }
    elseif (isset($_POST['update_profile']) && isset($_SESSION['user'])) {
        $update_data = array('full_name'=>$_POST['full_name'], 'username'=>$_POST['username'], 'email'=>$_POST['email'], 'password'=>$_POST['password']);
        if (updateUser($conn, $_SESSION['user']['id'], $update_data)) $message = "Profile updated!";
        else $message = "Update failed.";
    }
    elseif (isset($_POST['post_comment']) && isset($_SESSION['user'])) {
        $video_id = intval($_POST['video_id']);
        $comment = trim($_POST['comment']);
        if(!empty($comment)) {
            addVideoComment($conn, $video_id, $_SESSION['user']['id'], $comment);
            $message = "Comment added.";
        } else $message = "Comment cannot be empty.";
    }
    elseif (isset($_POST['react_comment']) && isset($_SESSION['user'])) {
        $comment_id = intval($_POST['comment_id']);
        $reaction = $_POST['reaction'];
        reactToComment($conn, $comment_id, $reaction);
        $message = "Reaction saved.";
    }
    elseif (isset($_POST['submit_ticket']) && isset($_SESSION['user'])) {
        $subject = trim($_POST['subject']);
        $msg = trim($_POST['ticket_message']);
        if(!empty($subject) && !empty($msg)) {
            createTicket($conn, $_SESSION['user']['id'], $subject, $msg);
            $message = "Ticket submitted. Admin will review it.";
        } else $message = "Please fill all fields.";
    }
    if(isset($_SESSION['user']) && isAdmin($_SESSION['user'])){
        if(isset($_POST['add_video'])){
            $course_id = intval($_POST['course_id']);
            $countRes = $conn->query("SELECT COUNT(*) as cnt FROM course_videos WHERE course_id=$course_id");
            $cnt = $countRes->fetch_assoc()['cnt'];
            if($cnt >= 8) {
                $message = "Cannot add more than 8 videos for this course!";
            } else {
                $title = $conn->real_escape_string($_POST['title']);
                $embed_url = $conn->real_escape_string($_POST['embed_url']);
                $pdf_url = $conn->real_escape_string($_POST['pdf_url']);
                if(!isValidYouTubeEmbed($embed_url)) {
                    $message = "Only YouTube embed URLs are allowed! Example: https://www.youtube.com/embed/...";
                } else {
                    $order = intval($_POST['video_order']);
                    $conn->query("INSERT INTO course_videos (course_id, title, embed_url, pdf_url, video_order) VALUES ($course_id, '$title', '$embed_url', '$pdf_url', $order)");
                    $message = "Video added successfully.";
                }
            }
        }
        if(isset($_POST['reply_comment'])){
            $comment_id = intval($_POST['comment_id']);
            $reply = $conn->real_escape_string($_POST['admin_reply']);
            replyToComment($conn, $comment_id, $reply);
            $message = "Reply sent.";
        }
        if(isset($_POST['reply_ticket'])){
            $ticket_id = intval($_POST['ticket_id']);
            $reply = $conn->real_escape_string($_POST['admin_reply']);
            $conn->query("UPDATE support_tickets SET admin_reply='$reply', status='resolved' WHERE id=$ticket_id");
            $message = "Ticket replied.";
        }
        if(isset($_POST['add_kb'])){
            $title = $conn->real_escape_string($_POST['kb_title']);
            $content = $conn->real_escape_string($_POST['kb_content']);
            $category = $conn->real_escape_string($_POST['kb_category']);
            $conn->query("INSERT INTO knowledge_base (title, content, category) VALUES ('$title', '$content', '$category')");
            $message = "Article added.";
        }
        if(isset($_POST['update_payment_status'])){
            $req_id = intval($_POST['request_id']);
            $status = $conn->real_escape_string($_POST['status']);
            $admin_msg = $conn->real_escape_string($_POST['admin_message']);
            $conn->query("UPDATE payment_requests SET status='$status', admin_message='$admin_msg' WHERE id=$req_id");
            $message = "Payment request updated.";
        }
        if(isset($_POST['broadcast_msg'])){
            $title = $conn->real_escape_string($_POST['broadcast_title']);
            $msg = $conn->real_escape_string($_POST['broadcast_message']);
            $type = $conn->real_escape_string($_POST['broadcast_type']);
            $conn->query("INSERT INTO broadcast_messages (title, message, message_type, is_active) VALUES ('$title', '$msg', '$type', 1)");
            $message = "Broadcast sent.";
        }
    }
}

if (isset($_SESSION['payment_receipt'])) {
    $receiptData = $_SESSION['payment_receipt'];
    unset($_SESSION['payment_receipt']);
}

if (isset($_GET['delete_notification']) && isset($_SESSION['user'])) {
    $type = $_GET['delete_notification'];
    $id = intval($_GET['id']);
    if ($type == 'admin_message') {
        $conn->query("DELETE FROM admin_messages WHERE id = $id AND user_id = {$_SESSION['user']['id']}");
        $message = "Notification deleted.";
        header("Location: new.php");
        exit;
    }
}

$user = (isset($_SESSION['user'])) ? $_SESSION['user'] : null;
$view_course = isset($_GET['course']) ? intval($_GET['course']) : null;
$show_cart = isset($_GET['cart']) && $_GET['cart'] == 1 && $user;
$register_page = isset($_GET['register']) && $_GET['register'] == 1;
$selected_category = isset($_GET['category']) ? $_GET['category'] : null;
$cart_added = isset($_GET['cart_added']) ? true : false;
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$video_id = isset($_GET['video_id']) ? intval($_GET['video_id']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$profile_dashboard = isset($_GET['profile']) && $_GET['profile'] == 'dashboard' && $user;
$notif_type = isset($_GET['notif_type']) ? $_GET['notif_type'] : '';
$notif_id = isset($_GET['notif_id']) ? intval($_GET['notif_id']) : 0;

if (isset($_GET['add_to_cart']) && $user) { addToCart($conn, $user['id'], intval($_GET['add_to_cart'])); header("Location: new.php?cart_added=1"); exit; }
if (isset($_GET['remove_from_cart']) && $user) { removeFromCart($conn, $user['id'], intval($_GET['remove_from_cart'])); header("Location: ?cart=1"); exit; }
if (isset($_GET['clear_cart']) && $user) { clearCart($conn, $user['id']); header("Location: ?cart=1"); exit; }
if (isset($_GET['logout'])) { session_destroy(); header("Location: new.php"); exit; }
if (isset($_GET['mark_notifications_read']) && $user) { markAdminRepliesAsRead($conn, $user['id']); header("Location: new.php"); exit; }
if (isset($_GET['update_progress']) && $user) {
    $video_id = intval($_GET['video_id']);
    $percent = intval($_GET['percent']);
    $completed = isset($_GET['completed']) && $_GET['completed'] == 'true';
    updateVideoProgress($conn, $user['id'], $video_id, $percent, $completed);
    exit;
}

$current_course = null;
if ($view_course && isset($course_list[$view_course])) $current_course = $course_list[$view_course];

$enrolled_courses = array();
$user_payment_requests = array();
$user_messages = array();
$unread_count = 0;
$cart_items = array();
$cart_total = 0;
if ($user) {
    $uid = $user['id'];
    $res = $conn->query("SELECT course_id FROM payment_requests WHERE user_id=$uid AND status='approved'");
    while ($row = $res->fetch_assoc()) $enrolled_courses[$row['course_id']] = true;
    $bulk = $conn->query("SELECT course_ids FROM payment_requests WHERE user_id=$uid AND status='approved' AND course_ids IS NOT NULL");
    while ($row = $bulk->fetch_assoc()) foreach(explode(',', $row['course_ids']) as $id) $enrolled_courses[$id] = true;
    $user_payment_requests = getUserPaymentRequests($conn, $uid);
    $user_messages = getUserMessages($conn, $uid);
    $unread_count = getUnreadAdminRepliesCount($conn, $uid);
    $cart_items = getCartItems($conn, $uid);
    foreach ($cart_items as $cid) if (isset($course_list[$cid])) $cart_total += $course_list[$cid]['price'];
    sendReminderIfNeeded($conn, $uid);
}

$all_notifications = array();
if ($user) {
    foreach ($user_messages as $msg) {
        if (!empty($msg['admin_reply'])) {
            $all_notifications[] = array(
                'type' => 'admin_message',
                'id' => $msg['id'],
                'text' => $msg['admin_reply'],
                'time' => $msg['updated_at'],
                'title' => 'Admin Reply',
                'original_message' => $msg['message'],
                'created_at' => $msg['created_at']
            );
        }
    }
    foreach ($user_payment_requests as $req) {
        if (!empty($req['admin_message'])) {
            $all_notifications[] = array(
                'type' => 'payment_request',
                'id' => $req['id'],
                'text' => "Your payment for {$req['course_name']} has been {$req['status']}. Message: " . $req['admin_message'],
                'time' => $req['updated_at'],
                'title' => 'Payment Update',
                'status' => $req['status'],
                'amount' => $req['amount'],
                'course_name' => $req['course_name'],
                'full_message' => $req['admin_message']
            );
        }
    }
    usort($all_notifications, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
}
$broadcast = getActiveBroadcast($conn);

$filtered_courses = $course_list;
if (!empty($search_query)) {
    $filtered_courses = array_filter($course_list, function($course) use ($search_query) {
        return stripos($course['name'], $search_query) !== false || 
               stripos($course['description'], $search_query) !== false ||
               stripos($course['category'], $search_query) !== false;
    });
}
if ($selected_category) {
    $filtered_courses = array_filter($filtered_courses, function($course) use ($selected_category) {
        return $course['category'] === $selected_category;
    });
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salaam Online | Excellence in Learning</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "surface-container-lowest": "#ffffff",
                        "outline-variant": "#c4c6cf",
                        "on-secondary-fixed": "#1a1c1c",
                        "surface-tint": "#495f84",
                        "inverse-primary": "#b1c7f2",
                        "secondary-fixed-dim": "#c6c6c7",
                        "on-tertiary": "#ffffff",
                        "error-container": "#ffdad6",
                        "tertiary-fixed": "#ffe088",
                        "error": "#ba1a1a",
                        "on-secondary-fixed-variant": "#454747",
                        "primary-fixed": "#d6e3ff",
                        "on-primary": "#ffffff",
                        "on-primary-fixed-variant": "#31476b",
                        "primary-fixed-dim": "#b1c7f2",
                        "tertiary-fixed-dim": "#e9c349",
                        "on-tertiary-container": "#4f3e00",
                        "on-surface-variant": "#44474e",
                        "surface-container-highest": "#d5e3fd",
                        "on-surface": "#0d1c2f",
                        "primary-container": "#001b3d",
                        "tertiary-container": "#cca730",
                        "on-secondary-container": "#616363",
                        "surface": "#f8f9ff",
                        "secondary": "#5d5f5f",
                        "on-tertiary-fixed": "#241a00",
                        "on-error-container": "#93000a",
                        "surface-variant": "#d5e3fd",
                        "on-secondary": "#ffffff",
                        "inverse-on-surface": "#ebf1ff",
                        "on-primary-fixed": "#001b3d",
                        "surface-container-high": "#dde9ff",
                        "surface-container": "#e6eeff",
                        "background": "#f8f9ff",
                        "secondary-fixed": "#e2e2e2",
                        "surface-bright": "#f8f9ff",
                        "inverse-surface": "#233144",
                        "on-tertiary-fixed-variant": "#574500",
                        "tertiary": "#735c00",
                        "on-error": "#ffffff",
                        "on-background": "#0d1c2f",
                        "primary": "#000000",
                        "on-primary-container": "#6f84ac",
                        "surface-container-low": "#eff4ff",
                        "secondary-container": "#dfe0e0",
                        "surface-dim": "#ccdbf4",
                        "outline": "#74777f"
                    },
                    borderRadius: {
                        DEFAULT: "0.25rem",
                        lg: "0.5rem",
                        xl: "0.75rem",
                        full: "9999px"
                    },
                    spacing: {
                        "container-max": "1280px",
                        "margin-desktop": "40px",
                        "margin-mobile": "16px",
                        base: "8px",
                        gutter: "24px"
                    },
                    fontFamily: {
                        "body-md": ["Inter"],
                        "headline-lg-mobile": ["Inter"],
                        "label-md": ["Inter"],
                        "label-sm": ["Inter"],
                        "headline-lg": ["Inter"],
                        "display-lg-mobile": ["Inter"],
                        "display-lg": ["Inter"],
                        "headline-md": ["Inter"],
                        "body-lg": ["Inter"]
                    },
                    fontSize: {
                        "body-md": ["16px", { lineHeight: "24px", fontWeight: "400" }],
                        "headline-lg-mobile": ["24px", { lineHeight: "32px", fontWeight: "600" }],
                        "label-md": ["14px", { lineHeight: "20px", letterSpacing: "0.05em", fontWeight: "500" }],
                        "label-sm": ["12px", { lineHeight: "16px", fontWeight: "600" }],
                        "headline-lg": ["32px", { lineHeight: "40px", fontWeight: "600" }],
                        "display-lg-mobile": ["36px", { lineHeight: "44px", letterSpacing: "-0.02em", fontWeight: "700" }],
                        "display-lg": ["48px", { lineHeight: "56px", letterSpacing: "-0.02em", fontWeight: "700" }],
                        "headline-md": ["24px", { lineHeight: "32px", fontWeight: "600" }],
                        "body-lg": ["18px", { lineHeight: "28px", fontWeight: "400" }]
                    }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .bento-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .bento-card:hover {
            transform: translateY(-4px);
            box-shadow: 0px 12px 24px rgba(0, 27, 61, 0.1);
        }
        .notification-dropdown {
            position: absolute;
            right: 0;
            top: 40px;
            width: 360px;
            max-height: 450px;
            overflow-y: auto;
            background: linear-gradient(135deg, #020024, #090979);
            border-radius: 0.5rem;
            border: 1px solid rgba(255,255,255,0.2);
            z-index: 1000;
            display: none;
        }
        .notification-dropdown.show {
            display: block;
        }
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f8f9ff;
        }
        ::-webkit-scrollbar-thumb {
            background: #c4c6cf;
            border-radius: 10px;
        }
        .payment-method-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .payment-method-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        .cart-header-image {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .profile-card, .notifications-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .tab-active {
            background-color: #4f46e5;
            color: white;
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1);
        }
        
        /* Receipt Modal Styles */
        .receipt-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            align-items: center;
            justify-content: center;
            z-index: 10000;
            backdrop-filter: blur(3px);
        }
        .receipt-card {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            border-radius: 1rem;
            padding: 2rem;
            max-width: 450px;
            width: 90%;
            text-align: center;
            color: white;
            box-shadow: 0 20px 35px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
            animation: fadeInUp 0.3s ease-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .receipt-card .service-badge {
            background: #ffb347;
            display: inline-block;
            padding: 6px 16px;
            border-radius: 40px;
            font-weight: bold;
            color: #1e2a3a;
            margin-bottom: 1rem;
        }
        .receipt-card .txn-id {
            background: rgba(255,255,255,0.2);
            padding: 8px;
            border-radius: 12px;
            font-family: monospace;
            font-size: 1.2rem;
            letter-spacing: 1px;
        }
        .receipt-card .btn-close {
            background: #ffb347;
            border: none;
            color: #1e2a3a;
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 40px;
            cursor: pointer;
            margin-top: 1rem;
            transition: 0.2s;
        }
        .receipt-card .btn-close:hover {
            background: #ff9f1a;
        }

        /* Confirmation Modal Styles */
        .confirmation-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            align-items: center;
            justify-content: center;
            z-index: 10001;
            backdrop-filter: blur(5px);
        }
        .confirmation-card {
            background: white;
            border-radius: 1.5rem;
            max-width: 500px;
            width: 90%;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            border: 1px solid rgba(0,0,0,0.05);
            animation: fadeInScale 0.2s ease-out;
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .confirmation-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1f2937;
            border-left: 4px solid #f59e0b;
            padding-left: 1rem;
        }
        .confirmation-details {
            background: #f9fafb;
            border-radius: 1rem;
            padding: 1rem;
            margin: 1rem 0;
            font-size: 0.95rem;
        }
        .confirmation-details p {
            margin: 0.5rem 0;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #e5e7eb;
            padding-bottom: 0.3rem;
        }
        .confirmation-details strong {
            color: #374151;
        }
        .confirmation-details span {
            color: #111827;
            font-weight: 500;
        }
        .btn-cancel {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 0.6rem 1.5rem;
            border-radius: 2rem;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-cancel:hover {
            background: #e5e7eb;
        }
        .btn-confirm {
            background: #f59e0b;
            color: white;
            border: none;
            padding: 0.6rem 1.8rem;
            border-radius: 2rem;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: 0.2s;
        }
        .btn-confirm:hover {
            background: #d97706;
            transform: translateY(-1px);
        }

        /* Glassmorphism category cards */
        .category-card {
            position: relative;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 1rem;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            transition: all 0.3s ease;
            overflow: hidden;
            min-height: 140px;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        .category-card .bg-image {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            z-index: -1;
            transition: transform 0.3s ease;
        }
        .category-card:hover .bg-image {
            transform: scale(1.05);
        }
        .category-card .overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.35);
            z-index: 0;
        }
        .category-card .content {
            position: relative;
            z-index: 1;
        }
        .category-card .icon-circle {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.4);
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
            transition: background 0.3s;
        }
        .category-card:hover .icon-circle {
            background: rgba(255, 255, 255, 0.4);
        }
        .category-card h3 {
            font-weight: 700;
            font-size: 1.125rem;
            line-height: 1.5;
        }
    </style>
</head>
<body class="bg-background text-on-background font-body-md overflow-x-hidden">

<?php
if ($broadcast) {
    $bgClass = '';
    if ($broadcast['message_type'] == 'danger') {
        $bgClass = 'bg-red-600';
    } elseif ($broadcast['message_type'] == 'warning') {
        $bgClass = 'bg-yellow-500';
    } else {
        $bgClass = 'bg-green-500';
    }
    echo '<div class="' . $bgClass . ' text-white text-center py-2 text-sm">' . htmlspecialchars($broadcast['title']) . ': ' . htmlspecialchars($broadcast['message']) . '</div>';
}
?>

<header class="fixed top-0 w-full z-50 bg-primary shadow-md">
    <div class="flex justify-between items-center px-margin-mobile md:px-margin-desktop h-16 max-w-container-max mx-auto">
        <div class="flex items-center gap-8">
            <a href="new.php" class="font-headline-md text-headline-md font-bold text-on-primary">Salaam Online</a>
            <nav class="hidden md:flex gap-6">
                <a href="new.php#courses" class="text-on-primary/80 font-medium hover:text-tertiary-fixed transition-colors duration-200 font-label-md text-label-md">Courses</a>
                <a href="new.php#categories" class="text-on-primary/80 font-medium hover:text-tertiary-fixed transition-colors duration-200 font-label-md text-label-md">Categories</a>
                <a href="?page=about" class="text-on-primary/80 font-medium hover:text-tertiary-fixed transition-colors duration-200 font-label-md text-label-md">About</a>
                <?php if($user): ?>
                <a href="?page=help" class="text-on-primary/80 font-medium hover:text-tertiary-fixed transition-colors duration-200 font-label-md text-label-md">Help</a>
                <?php endif; ?>
                <a href="?page=contact" class="text-on-primary/80 font-medium hover:text-tertiary-fixed transition-colors duration-200 font-label-md text-label-md">Contact</a>
                <?php if(isAdmin($user)): ?>
                    <a href="?page=admin" class="text-on-primary/80 font-medium hover:text-tertiary-fixed transition-colors duration-200 font-label-md text-label-md">Admin</a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="flex items-center gap-4">
            <form method="GET" action="new.php" class="relative hidden sm:block">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-primary/50 text-sm">search</span>
                <input type="text" name="search" placeholder="Search courses..." value="<?php echo htmlspecialchars($search_query); ?>" class="bg-white/10 border-none rounded-full py-1.5 pl-10 pr-4 text-on-primary placeholder:text-on-primary/50 text-sm focus:ring-2 focus:ring-tertiary-fixed w-48 lg:w-64">
            </form>
            <a href="?cart=1" class="scale-95 active:scale-90 transition-transform text-on-primary p-2 relative">
                <span class="material-symbols-outlined">shopping_cart</span>
                <?php if($user && count($cart_items) > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?php echo count($cart_items); ?></span>
                <?php endif; ?>
            </a>
            <?php if($user): ?>
                <div class="relative">
                    <button onclick="toggleNotificationDropdown()" class="text-on-primary p-2 relative">
                        <span class="material-symbols-outlined">notifications</span>
                        <?php if($unread_count > 0): ?>
                            <span class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full"></span>
                        <?php endif; ?>
                    </button>
                    <div id="notificationDropdown" class="notification-dropdown">
                        <?php if(count($all_notifications) > 0): ?>
                            <?php foreach($all_notifications as $notif): ?>
                                <div class="border-b border-white/10 p-3 hover:bg-white/5">
                                    <a href="?page=notifications" class="block text-white">
                                        <strong class="block text-sm"><?php echo htmlspecialchars($notif['title']); ?></strong>
                                        <p class="text-xs text-white/70 truncate"><?php echo htmlspecialchars(substr($notif['text'],0,80)); ?></p>
                                        <span class="text-xs text-white/50"><?php echo date('M d, H:i', strtotime($notif['time'])); ?></span>
                                    </a>
                                    <?php if($notif['type'] == 'admin_message'): ?>
                                        <a href="?delete_notification=admin_message&id=<?php echo $notif['id']; ?>" class="text-xs text-red-400 hover:text-red-300 mt-1 inline-block" onclick="return confirm('Delete?')">Delete</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if($unread_count > 0): ?>
                                <div class="p-2 text-center"><a href="?mark_notifications_read=1" class="text-xs text-yellow-300">Mark all as read</a></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-white/70 text-sm">No notifications</div>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="?profile=dashboard" class="scale-95 active:scale-90 transition-transform text-on-primary p-2">
                    <span class="material-symbols-outlined">account_circle</span>
                </a>
                <a href="?logout" class="text-on-primary text-sm px-3 py-1 rounded-full bg-white/10 hover:bg-white/20 transition" onclick="return confirm('Logout?')">Logout</a>
            <?php else: ?>
                <button onclick="openModal('loginModal')" class="px-4 py-2 bg-yellow-400 text-black rounded-lg font-bold text-sm">Login</button>
                <button onclick="openRegisterTab()" class="px-4 py-2 bg-transparent border border-white text-white rounded-lg font-bold text-sm">Sign Up</button>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="mt-16">
<?php
// ========== PAGE RENDERING ==========
if ($page == 'settings' && $user):
?>
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-8">
        <a href="new.php" class="inline-flex items-center gap-2 text-primary font-bold mb-6"><span class="material-symbols-outlined">arrow_back</span> Back to Home</a>
        <div class="bg-white border border-gray-200 rounded-xl p-8 shadow-sm">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Account Settings</h2>
            <form method="POST" class="max-w-xl">
                <div class="mb-4"><label class="block font-medium mb-2">Full Name</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" class="w-full border border-gray-300 rounded-lg p-3 bg-gray-50" required></div>
                <div class="mb-4"><label class="block font-medium mb-2">Username</label><input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="w-full border border-gray-300 rounded-lg p-3 bg-gray-50" required></div>
                <div class="mb-4"><label class="block font-medium mb-2">Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full border border-gray-300 rounded-lg p-3 bg-gray-50" required></div>
                <div class="mb-4"><label class="block font-medium mb-2">New Password (leave blank to keep)</label><input type="password" name="password" class="w-full border border-gray-300 rounded-lg p-3 bg-gray-50"></div>
                <button type="submit" name="update_profile" class="bg-black text-white px-6 py-3 rounded-lg font-bold">Save Changes</button>
            </form>
        </div>
    </div>
<?php
elseif ($page == 'notifications' && $user):
    $payment_notifications = array();
    foreach ($user_payment_requests as $req) {
        if (!empty($req['admin_message'])) {
            $payment_notifications[] = $req;
        }
    }
    $message_notifications = array();
    foreach ($user_messages as $msg) {
        if (!empty($msg['admin_reply'])) {
            $message_notifications[] = $msg;
        }
    }
?>
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-12">
        <div class="notifications-card rounded-2xl p-8 text-white shadow-xl mb-8">
            <div class="flex items-center gap-6 flex-wrap">
                <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center shadow-lg">
                    <span class="material-symbols-outlined text-6xl text-indigo-600">notifications_active</span>
                </div>
                <div>
                    <h1 class="text-3xl font-bold">Notifications Center</h1>
                    <p class="text-white/80">View all your payment updates and messages from admin</p>
                    <div class="flex gap-3 mt-2">
                        <span class="bg-white/20 rounded-full px-3 py-1 text-sm"><?php echo count($payment_notifications); ?> Payment updates</span>
                        <span class="bg-white/20 rounded-full px-3 py-1 text-sm"><?php echo count($message_notifications); ?> Messages</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="flex flex-wrap gap-2 border-b p-4 bg-gray-50">
                <button class="notification-tab active px-5 py-2 rounded-full bg-indigo-600 text-white font-medium shadow" onclick="showNotificationTab('payment')">💳 Payment Updates</button>
                <button class="notification-tab px-5 py-2 rounded-full bg-gray-200 text-gray-700 font-medium hover:bg-gray-300 transition" onclick="showNotificationTab('message')">💬 Admin Messages</button>
            </div>
            <div class="p-6">
                <div id="payment-tab" class="notification-tab-content">
                    <h2 class="text-xl font-bold mb-4 text-gray-800 flex items-center gap-2"><span class="material-symbols-outlined text-amber-600">payments</span> Payment Updates</h2>
                    <?php if(count($payment_notifications) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach($payment_notifications as $notif): ?>
                                <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-xl p-5 shadow-sm hover:shadow-md transition">
                                    <div class="flex flex-wrap justify-between items-start">
                                        <div>
                                            <p class="font-bold text-lg"><?php echo htmlspecialchars($notif['course_name']); ?></p>
                                            <p class="text-sm text-gray-600">Amount: <span class="font-bold text-amber-600">$<?php echo number_format($notif['amount'],2); ?></span></p>
                                            <p class="text-sm">Status: 
                                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold <?php echo $notif['status']=='approved' ? 'bg-green-200 text-green-800' : ($notif['status']=='pending' ? 'bg-yellow-200 text-yellow-800' : 'bg-red-200 text-red-800'); ?>">
                                                    <?php echo strtoupper($notif['status']); ?>
                                                </span>
                                            </p>
                                            <p class="text-sm text-gray-500 mt-1">Transaction: <?php echo htmlspecialchars($notif['transaction_id']); ?></p>
                                            <p class="text-xs text-gray-400"><?php echo date('F d, Y h:i A', strtotime($notif['created_at'])); ?></p>
                                        </div>
                                        <div class="mt-2 sm:mt-0">
                                            <span class="material-symbols-outlined text-amber-500 text-4xl">receipt</span>
                                        </div>
                                    </div>
                                    <?php if($notif['admin_message']): ?>
                                        <div class="mt-3 bg-white/70 rounded-lg p-3 border-l-4 border-amber-500">
                                            <p class="text-xs font-semibold text-amber-700">Admin note:</p>
                                            <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($notif['admin_message'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 text-gray-500">No payment notifications yet.</div>
                    <?php endif; ?>
                </div>
                <div id="message-tab" class="notification-tab-content" style="display:none;">
                    <h2 class="text-xl font-bold mb-4 text-gray-800 flex items-center gap-2"><span class="material-symbols-outlined text-blue-600">chat</span> Admin Messages</h2>
                    <?php if(count($message_notifications) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach($message_notifications as $msg): ?>
                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-5 shadow-sm hover:shadow-md transition">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="bg-white rounded-lg p-3 mb-3">
                                                <p class="text-xs text-gray-500">Your message (<?php echo date('M d, H:i', strtotime($msg['created_at'])); ?>)</p>
                                                <p class="text-gray-800"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                            </div>
                                            <?php if($msg['admin_reply']): ?>
                                                <div class="bg-blue-100 rounded-lg p-3 border-l-4 border-blue-500">
                                                    <p class="text-xs font-semibold text-blue-700">Admin replied (<?php echo date('M d, H:i', strtotime($msg['updated_at'])); ?>)</p>
                                                    <p class="text-gray-800"><?php echo nl2br(htmlspecialchars($msg['admin_reply'])); ?></p>
                                                </div>
                                            <?php else: ?>
                                                <div class="bg-gray-100 rounded-lg p-3 text-center text-gray-500 text-sm">No reply yet</div>
                                            <?php endif; ?>
                                        </div>
                                        <a href="?delete_notification=admin_message&id=<?php echo $msg['id']; ?>" class="text-red-500 hover:text-red-700 ml-3" onclick="return confirm('Delete this message?')"><span class="material-symbols-outlined">delete</span></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 text-gray-500">No admin messages yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        function showNotificationTab(tabName) {
            var tabs = document.querySelectorAll('.notification-tab-content');
            for(var i=0;i<tabs.length;i++) tabs[i].style.display = 'none';
            document.getElementById(tabName + '-tab').style.display = 'block';
            var btns = document.querySelectorAll('.notification-tab');
            for(var i=0;i<btns.length;i++) {
                btns[i].classList.remove('bg-indigo-600', 'text-white');
                btns[i].classList.add('bg-gray-200', 'text-gray-700');
            }
            if(tabName === 'payment') {
                btns[0].classList.add('bg-indigo-600', 'text-white');
                btns[0].classList.remove('bg-gray-200', 'text-gray-700');
            } else {
                btns[1].classList.add('bg-indigo-600', 'text-white');
                btns[1].classList.remove('bg-gray-200', 'text-gray-700');
            }
        }
    </script>
<?php
elseif ($page == 'view_notification' && $user && $notif_type && $notif_id):
    header("Location: ?page=notifications");
    exit;
elseif ($page == 'about'):
?>
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-12">
        <div class="bg-blue-900 rounded-xl p-12 text-center text-white mb-12">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">About Salaam Online</h1>
            <p class="text-lg max-w-2xl mx-auto">We are a premier online learning platform dedicated to providing high-quality, accessible education for professionals worldwide.</p>
        </div>
        <div class="grid md:grid-cols-2 gap-12 items-center mb-12">
            <div>
                <h2 class="text-3xl font-bold mb-4">Our Mission</h2>
                <p class="text-gray-600 mb-4">Empower learners with the skills they need to succeed in today's competitive landscape. We combine expert instruction with modern technology to create an unmatched learning experience.</p>
                <p class="text-gray-600">Join thousands of students who have advanced their careers through our courses.</p>
            </div>
            <div class="rounded-xl overflow-hidden shadow-lg"><img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f" class="w-full h-auto" alt="Team"></div>
        </div>
        <div class="grid md:grid-cols-3 gap-8 text-center">
            <div class="bg-white border border-gray-200 rounded-xl p-6"><span class="material-symbols-outlined text-5xl text-amber-600">school</span><h3 class="text-xl font-bold mt-4">Expert Instructors</h3><p>Learn from industry leaders</p></div>
            <div class="bg-white border border-gray-200 rounded-xl p-6"><span class="material-symbols-outlined text-5xl text-amber-600">verified</span><h3 class="text-xl font-bold mt-4">Certified Courses</h3><p>Earn recognised certificates</p></div>
            <div class="bg-white border border-gray-200 rounded-xl p-6"><span class="material-symbols-outlined text-5xl text-amber-600">support_agent</span><h3 class="text-xl font-bold mt-4">24/7 Support</h3><p>We are here to help</p></div>
        </div>
    </div>
<?php
elseif ($page == 'help'):
    if (!$user) {
        // Not logged in: show login prompt
        echo '<div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-20 text-center">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Please Login to Access Help Center</h2>
                <p class="text-gray-600 mb-6">You need to be logged in to view help resources and submit tickets.</p>
                <button onclick="openModal(\'loginModal\')" class="bg-amber-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-amber-700 transition">Login</button>
                <span class="mx-2">or</span>
                <a href="?register=1" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-blue-700 transition">Sign Up</a>
              </div>';
    } else {
        // Logged in: show help content
        $tickets = getUserTickets($conn, $user['id']);
?>
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-12">
        <div class="bg-blue-900 rounded-xl p-12 text-center text-white mb-12">
            <h1 class="text-4xl md:text-5xl font-bold mb-2">Help Center</h1>
            <p class="text-lg">How can we assist you today?</p>
        </div>
        <div class="grid lg:grid-cols-2 gap-8">
            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <h2 class="text-2xl font-bold mb-4">Submit a Ticket</h2>
                <form method="POST">
                    <div class="mb-4"><input type="text" name="subject" placeholder="Subject" class="w-full border border-gray-300 rounded-lg p-3" required></div>
                    <div class="mb-4"><textarea name="ticket_message" rows="4" placeholder="Describe your issue..." class="w-full border border-gray-300 rounded-lg p-3" required></textarea></div>
                    <button type="submit" name="submit_ticket" class="bg-amber-600 text-white px-6 py-2 rounded-lg font-bold">Submit Ticket</button>
                </form>
                <?php if(count($tickets) > 0): ?>
                    <h3 class="text-xl font-bold mt-8 mb-4">My Tickets</h3>
                    <?php foreach($tickets as $t): ?>
                        <div class="border-b border-gray-200 py-4">
                            <div class="flex justify-between"><strong><?php echo htmlspecialchars($t['subject']); ?></strong> <span class="text-xs bg-gray-100 px-2 py-1 rounded-full"><?php echo $t['status']; ?></span></div>
                            <p class="text-sm text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($t['message'])); ?></p>
                            <?php if($t['admin_reply']): ?>
                                <div class="mt-2 p-2 bg-blue-50 rounded text-sm"><strong>Admin reply:</strong> <?php echo nl2br(htmlspecialchars($t['admin_reply'])); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <h2 class="text-2xl font-bold mb-4">Payment Information</h2>
                <div class="bg-blue-50 rounded-lg p-4 mb-6 text-center">
                    <p class="font-bold">Zaad Number: <span class="text-amber-600">063462122</span></p>
                    <p class="text-sm mt-2">Send exact amount and use your Transaction ID in the form.</p>
                </div>
                <ul class="list-disc list-inside space-y-2 text-gray-600">
                    <li>Step 1: Send payment via Zaad to 063462122 / EDAHAB 065462122 / SOLTELCO 0672357971</li>
                    <li>Step 2: Fill payment request with Transaction ID</li>
                    <li>Step 3: Admin approves within 24h</li>
                    <li>Step 4: Access your course immediately</li>
                </ul>
                <p class="mt-6 text-sm text-center text-gray-500">For urgent help, use the ticket system above.</p>
            </div>
        </div>
    </div>
<?php
    }
elseif ($page == 'contact'):
?>
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-12">
        <div class="bg-gradient-to-r from-blue-900 to-blue-700 rounded-xl p-8 md:p-12 text-center text-white mb-12 shadow-xl border-b-4 border-amber-500">
            <div class="flex flex-col md:flex-row items-center justify-between gap-8">
                <div class="flex-1 text-left">
                    <span class="text-amber-300 text-sm font-semibold tracking-wider">📞 NALA SOO XIDHIIDH</span>
                    <h1 class="text-4xl md:text-5xl font-bold mt-2 mb-4">Gacmo furan ayaan kugu soo dhaweynayaa!</h1>
                    <p class="text-blue-100 text-lg max-w-lg">Haddii aad qabto su'aal, talo, ama caawimaad, nagala soo xiriir adoo isticmaalaya macluumaadka hoose.</p>
                    <div class="mt-6 flex flex-wrap gap-4">
                        <a href="#contact-info" class="bg-amber-500 hover:bg-amber-600 text-black font-bold py-2 px-6 rounded-full transition flex items-center gap-2"><span class="material-symbols-outlined">phone_in_talk</span> Nala soo xiriir</a>
                        <button onclick="window.open('https://maps.google.com/?q=Hargeisa+Somaliland', '_blank')" class="bg-white/20 hover:bg-white/30 text-white font-bold py-2 px-6 rounded-full transition flex items-center gap-2"><span class="material-symbols-outlined">location_on</span> Location: Hargeisa</button>
                    </div>
                </div>
                <div class="flex-1">
                    <img src="https://images.unsplash.com/photo-1587560699334-bea93391dcef?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Customer support agent on phone" class="rounded-lg shadow-2xl max-h-64 mx-auto border-4 border-amber-400">
                    <p class="text-xs text-blue-200 mt-2">✆ Khasnado kasta oo aad waydiiso, waan kaa caawinaynaa</p>
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-6 mb-12" id="contact-info">
            <div class="bg-white border border-gray-200 rounded-xl p-6 text-center shadow-md hover:shadow-xl transition-all duration-300 hover:border-amber-500">
                <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4"><span class="material-symbols-outlined text-blue-600 text-2xl">location_on</span></div>
                <h3 class="font-bold text-xl">Hargeisa, Somaliland</h3>
                <p class="text-gray-500 mt-2">Madaxtooyada, agagaarka wasaaradda</p>
                <button onclick="window.open('https://maps.google.com/?q=Hargeisa+Somaliland', '_blank')" class="mt-4 text-amber-600 hover:underline text-sm">Fur Google Maps</button>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-6 text-center shadow-md hover:shadow-xl transition-all duration-300 hover:border-amber-500">
                <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4"><span class="material-symbols-outlined text-blue-600 text-2xl">email</span></div>
                <h3 class="font-bold text-xl">Email</h3>
                <p class="text-gray-500 mt-2">salaamonlin100@gmail.com</p>
                <a href="mailto:salaamonlin100@gmail.com" class="mt-4 inline-block text-amber-600 hover:underline text-sm">Soo dir email</a>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-6 text-center shadow-md hover:shadow-xl transition-all duration-300 hover:border-amber-500">
                <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4"><span class="material-symbols-outlined text-blue-600 text-2xl">call</span></div>
                <h3 class="font-bold text-xl">WhatsApp / Phone</h3>
                <p class="text-gray-500 mt-2">0634621822</p>
                <a href="https://wa.me/252634621822" target="_blank" class="mt-4 inline-block text-green-600 hover:underline text-sm">WhatsApp</a>
            </div>
        </div>
    </div>
<?php
elseif ($page == 'admin' && isAdmin($user)):
?>
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-12">
        <h1 class="text-4xl font-bold mb-8">Admin Dashboard</h1>
        <div class="grid md:grid-cols-2 gap-8">
            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <h2 class="text-xl font-bold mb-4">Add Video</h2>
                <form method="POST">
                    <select name="course_id" class="w-full border rounded-lg p-2 mb-3">
                        <?php foreach($course_list as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="title" placeholder="Video title" class="w-full border rounded-lg p-2 mb-3" required>
                    <input type="text" name="embed_url" placeholder="YouTube embed URL" class="w-full border rounded-lg p-2 mb-3" required>
                    <input type="text" name="pdf_url" placeholder="PDF URL (Google Drive link)" class="w-full border rounded-lg p-2 mb-3">
                    <input type="number" name="video_order" placeholder="Order" class="w-full border rounded-lg p-2 mb-3">
                    <button type="submit" name="add_video" class="bg-black text-white px-4 py-2 rounded-lg">Add</button>
                </form>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <h2 class="text-xl font-bold mb-4">Reply to Comment</h2>
                <form method="POST">
                    <select name="comment_id" class="w-full border rounded-lg p-2 mb-3">
                        <?php $coms = $conn->query("SELECT c.id, u.username, c.comment FROM video_comments c JOIN users u ON c.user_id=u.id"); while($cm = $coms->fetch_assoc()): ?>
                            <option value="<?php echo $cm['id']; ?>"><?php echo htmlspecialchars($cm['username']); ?>: <?php echo substr($cm['comment'],0,30); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <textarea name="admin_reply" rows="3" class="w-full border rounded-lg p-2 mb-3" placeholder="Admin reply"></textarea>
                    <button type="submit" name="reply_comment" class="bg-black text-white px-4 py-2 rounded-lg">Reply</button>
                </form>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <h2 class="text-xl font-bold mb-4">Reply to Ticket</h2>
                <form method="POST">
                    <select name="ticket_id" class="w-full border rounded-lg p-2 mb-3">
                        <?php $tics = $conn->query("SELECT * FROM support_tickets WHERE admin_reply IS NULL"); while($tk = $tics->fetch_assoc()): ?>
                            <option value="<?php echo $tk['id']; ?>"><?php echo htmlspecialchars($tk['subject']); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <textarea name="admin_reply" rows="3" class="w-full border rounded-lg p-2 mb-3" placeholder="Admin reply"></textarea>
                    <button type="submit" name="reply_ticket" class="bg-black text-white px-4 py-2 rounded-lg">Reply & Resolve</button>
                </form>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <h2 class="text-xl font-bold mb-4">Payment Requests</h2>
                <?php
                $reqs = $conn->query("SELECT * FROM payment_requests ORDER BY created_at DESC");
                while($r = $reqs->fetch_assoc()):
                    $cnames = array();
                    if($r['course_ids']){
                        foreach(explode(',', $r['course_ids']) as $id) if(isset($course_list[$id])) $cnames[] = $course_list[$id]['name'];
                    } elseif($r['course_id'] && isset($course_list[$r['course_id']])) $cnames[] = $course_list[$r['course_id']]['name'];
                    $courses_disp = implode(', ', $cnames);
                ?>
                    <div class="border-b py-3">
                        <strong><?php echo htmlspecialchars($r['fullname']); ?></strong><br>
                        Courses: <?php echo htmlspecialchars($courses_disp); ?><br>
                        Amount: $<?php echo $r['amount']; ?> | Status: <?php echo $r['status']; ?><br>
                        Method: <?php echo htmlspecialchars($r['payment_method']); ?> | Number: <?php echo htmlspecialchars($r['payment_number']); ?>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                            <select name="status" class="border rounded p-1 text-sm"><option <?php echo $r['status']=='approved' ? 'selected' : ''; ?>>approved</option><option <?php echo $r['status']=='rejected' ? 'selected' : ''; ?>>rejected</option></select>
                            <input type="text" name="admin_message" placeholder="Message" class="border rounded p-1 text-sm w-full mt-1">
                            <button type="submit" name="update_payment_status" class="bg-black text-white text-sm px-3 py-1 rounded mt-1">Update</button>
                        </form>
                    </div>
                <?php endwhile; ?>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                <h2 class="text-xl font-bold mb-4">Broadcast Message</h2>
                <form method="POST">
                    <input type="text" name="broadcast_title" placeholder="Title" class="w-full border rounded-lg p-2 mb-3">
                    <textarea name="broadcast_message" rows="3" placeholder="Message" class="w-full border rounded-lg p-2 mb-3"></textarea>
                    <select name="broadcast_type" class="w-full border rounded-lg p-2 mb-3"><option>info</option><option>warning</option><option>success</option><option>danger</option></select>
                    <button type="submit" name="broadcast_msg" class="bg-black text-white px-4 py-2 rounded-lg">Send</button>
                </form>
            </div>
        </div>
    </div>
<?php
elseif ($page == 'watch' && $user && $video_id):
    $video = getVideoById($conn, $video_id);
    if(!$video):
        echo '<div class="container py-20 text-center">Video not found.</div>';
    else:
        $course_id = $video['course_id'];
        if(!hasAccess($conn, $user['id'], $course_id)):
            echo '<div class="container py-20 text-center">You do not have access to this course.</div>';
        else:
            $progress = getUserProgress($conn, $user['id'], $video_id);
            $comments = getVideoComments($conn, $video_id);
            $video_embed = $video['embed_url'];
            preg_match('/\/embed\/([a-zA-Z0-9_-]+)/', $video_embed, $matches);
            $youtube_id = isset($matches[1]) ? $matches[1] : '';
?>
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-8">
        <a href="?course=<?php echo $course_id; ?>" class="inline-flex items-center gap-2 text-primary font-bold mb-6"><span class="material-symbols-outlined">arrow_back</span> Back to Course</a>
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <div class="aspect-video">
                <div id="youtube-player"></div>
            </div>
            <div class="p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($video['title']); ?></h1>
                    </div>
                    <?php if(!empty($video['pdf_url'])): ?>
                        <a href="<?php echo htmlspecialchars($video['pdf_url']); ?>" target="_blank" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                            <span class="material-symbols-outlined">picture_as_pdf</span> Download PDF
                        </a>
                    <?php endif; ?>
                </div>
                <div class="w-full bg-gray-200 h-2 rounded-full mt-4">
                    <div id="progress-bar" class="bg-amber-500 h-2 rounded-full" style="width: <?php echo $progress['progress_percent']; ?>%"></div>
                </div>
                <div class="mt-2 text-sm text-gray-500">Progress: <span id="progress-percent"><?php echo $progress['progress_percent']; ?></span>%</div>
                
                <h2 class="text-xl font-bold mt-8 mb-4">Comments</h2>
                <form method="POST" class="mb-6">
                    <input type="hidden" name="video_id" value="<?php echo $video_id; ?>">
                    <textarea name="comment" rows="2" class="w-full border border-gray-300 rounded-lg p-3" placeholder="Write a comment..."></textarea>
                    <button type="submit" name="post_comment" class="mt-2 bg-black text-white px-4 py-2 rounded-lg">Post Comment</button>
                </form>
                <?php foreach($comments as $cmt): ?>
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <div class="flex justify-between"><strong><?php echo htmlspecialchars($cmt['username']); ?></strong> <span><?php echo date('M d, H:i', strtotime($cmt['created_at'])); ?></span></div>
                        <p class="mt-1"><?php echo nl2br(htmlspecialchars($cmt['comment'])); ?></p>
                        <div class="flex gap-3 mt-2">
                            <form method="POST" class="inline"><input type="hidden" name="comment_id" value="<?php echo $cmt['id']; ?>"><input type="hidden" name="reaction" value="like"><button type="submit" name="react_comment" class="text-sm flex items-center gap-1"><span class="material-symbols-outlined text-sm">thumb_up</span> Like <?php echo $cmt['reaction_type']=='like' ? '✓' : ''; ?></button></form>
                            <form method="POST" class="inline"><input type="hidden" name="comment_id" value="<?php echo $cmt['id']; ?>"><input type="hidden" name="reaction" value="love"><button type="submit" name="react_comment" class="text-sm flex items-center gap-1"><span class="material-symbols-outlined text-sm">favorite</span> Love <?php echo $cmt['reaction_type']=='love' ? '✓' : ''; ?></button></form>
                        </div>
                        <?php if($cmt['admin_reply']): ?>
                            <div class="mt-2 p-2 bg-blue-50 rounded"><strong>Admin:</strong> <?php echo nl2br(htmlspecialchars($cmt['admin_reply'])); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script src="https://www.youtube.com/iframe_api"></script>
    <script>
        var player;
        var videoId = '<?php echo $youtube_id; ?>';
        var video_id = <?php echo $video_id; ?>;
        var lastPercent = <?php echo $progress['progress_percent']; ?>;
        var interval;
        
        function onYouTubeIframeAPIReady() {
            if(videoId) {
                player = new YT.Player('youtube-player', {
                    height: '100%',
                    width: '100%',
                    videoId: videoId,
                    events: {
                        'onStateChange': onPlayerStateChange,
                        'onReady': onPlayerReady
                    }
                });
            } else {
                document.getElementById('youtube-player').innerHTML = '<div class="bg-gray-200 p-8 text-center">Invalid video URL</div>';
            }
        }
        
        function onPlayerReady(event) {
            interval = setInterval(function() {
                if(player && typeof player.getCurrentTime === 'function') {
                    var duration = player.getDuration();
                    var current = player.getCurrentTime();
                    if(duration > 0) {
                        var percent = Math.floor((current / duration) * 100);
                        if(percent > 100) percent = 100;
                        if(percent > lastPercent) {
                            lastPercent = percent;
                            var completed = (percent >= 100);
                            fetch(`?update_progress=1&video_id=${video_id}&percent=${percent}&completed=${completed}`);
                            document.getElementById('progress-bar').style.width = percent + '%';
                            document.getElementById('progress-percent').innerText = percent;
                        }
                    }
                }
            }, 2000);
        }
        
        function onPlayerStateChange(event) {
            if(event.data == YT.PlayerState.ENDED) {
                fetch(`?update_progress=1&video_id=${video_id}&percent=100&completed=true`);
                document.getElementById('progress-bar').style.width = '100%';
                document.getElementById('progress-percent').innerText = '100';
                if(interval) clearInterval(interval);
            }
        }
        
        window.addEventListener('beforeunload', function() {
            if(interval) clearInterval(interval);
        });
    </script>
<?php
        endif;
    endif;
elseif ($show_cart):
?>
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-12">
        <a href="new.php" class="inline-flex items-center gap-2 text-primary font-bold mb-6"><span class="material-symbols-outlined">arrow_back</span> Continue Shopping</a>
        
        <div class="cart-header-image rounded-2xl p-6 mb-8 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold flex items-center gap-3"><span class="material-symbols-outlined text-5xl">shopping_cart</span> Your Cart</h1>
                    <p class="text-white/80 mt-2">You have <span class="font-bold text-yellow-300"><?php echo count($cart_items); ?> items</span> in your cart</p>
                </div>
                <img src="https://cdn-icons-png.flaticon.com/512/263/263142.png" class="h-20 w-20 object-contain hidden md:block" alt="Cart">
            </div>
        </div>
        
        <div class="grid lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-lg hover:shadow-xl transition">
                    <h2 class="text-2xl font-bold mb-4 flex items-center gap-2"><span class="material-symbols-outlined text-amber-600">shopping_bag</span> Cart Items</h2>
                    <?php if(count($cart_items) > 0): ?>
                        <?php foreach($cart_items as $cid): $c = $course_list[$cid]; ?>
                            <div class="flex justify-between items-center border-b py-4 hover:bg-gray-50 px-2 rounded transition">
                                <div class="flex items-center gap-3">
                                    <img src="<?php echo $c['image']; ?>" class="w-12 h-12 rounded object-cover">
                                    <div><h3 class="font-bold"><?php echo htmlspecialchars($c['name']); ?></h3><p class="text-gray-600 text-sm">$<?php echo $c['price']; ?></p></div>
                                </div>
                                <a href="?remove_from_cart=<?php echo $cid; ?>" class="text-red-600 hover:text-red-800"><span class="material-symbols-outlined">delete</span></a>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-right mt-6 p-3 bg-gray-50 rounded-lg">
                            <strong class="text-xl">Total: $<?php echo number_format($cart_total,2); ?></strong>
                        </div>
                        <div class="mt-6 flex gap-3">
                            <button onclick="openSelectPaymentMethod('cart')" class="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3 rounded-lg font-bold hover:shadow-lg transition">Proceed to Checkout</button>
                            <a href="?clear_cart=1" class="border border-red-600 text-red-600 px-6 py-3 rounded-lg font-bold hover:bg-red-50 transition">Clear Cart</a>
                        </div>
                    <?php else: ?>
                        <p class="text-center py-8 text-gray-500">Your cart is empty. <a href="new.php" class="text-amber-600 font-bold">Browse courses</a></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 shadow-lg border border-blue-200 h-fit">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2"><span class="material-symbols-outlined text-blue-600">info</span> Payment Info</h3>
                <div class="space-y-3">
                    <p class="text-sm text-gray-700 flex items-center gap-2"><span class="material-symbols-outlined text-green-600">payments</span> Choose your payment method:</p>
                    <ul class="list-disc list-inside text-sm text-gray-700 space-y-1 ml-2">
                        <li><span class="font-semibold">ZAAD:</span> 063462122</li>
                        <li><span class="font-semibold">EDAHAB:</span> 065462122</li>
                        <li><span class="font-semibold">SOLTELCO:</span> 0672357971</li>
                    </ul>
                    <div class="bg-yellow-100 p-3 rounded-lg mt-4 text-sm">
                        <span class="material-symbols-outlined text-yellow-600 align-middle">lightbulb</span> After payment, click "Proceed to Checkout" and submit your Transaction ID.
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="cartPaymentModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50" onclick="if(event.target===this)closeModal('cartPaymentModal')">
        <div class="bg-white rounded-xl max-w-md w-full p-6">
            <div class="flex justify-between"><h3 class="text-xl font-bold">Complete Payment</h3><button onclick="closeModal('cartPaymentModal')" class="text-2xl">&times;</button></div>
            <div class="mt-4 p-3 bg-blue-50 rounded text-center"><strong>Send payment to: <span id="cartPaymentNumberDisplay">063462122</span></strong><br><span id="cartPaymentMethodDisplay" class="text-sm font-bold">ZAAD</span></div>
            <form method="POST" class="mt-4" id="cartPaymentForm">
                <input type="hidden" name="submit_payment_request" value="1">
                <input type="hidden" name="cart_checkout" value="1">
                <input type="hidden" name="payment_method" id="cartPaymentMethodInput" value="ZAAD">
                <input type="hidden" name="payment_number" id="cartPaymentNumberInput" value="063462122">
                <div class="mb-3"><input type="text" name="fullname" value="<?php echo htmlspecialchars($user['full_name']); ?>" placeholder="Full Name" class="w-full border rounded-lg p-2" required></div>
                <div class="mb-3"><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="Email" class="w-full border rounded-lg p-2" required></div>
                <div class="mb-3"><input type="text" name="transaction_id" id="transactionIdCart" placeholder="Transaction ID" class="w-full border rounded-lg p-2" required></div>
                <div class="mb-3"><input type="text" name="amount" value="<?php echo $cart_total; ?>" class="w-full border rounded-lg p-2 bg-gray-100" readonly></div>
                <button type="button" id="cartSubmitBtn" class="w-full bg-amber-600 text-white py-2 rounded-lg font-bold">Submit Request</button>
            </form>
        </div>
    </div>
<?php
elseif ($register_page):
?>
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-12">
        <div class="max-w-md mx-auto bg-white border border-gray-200 rounded-xl p-8 shadow-sm">
            <h2 class="text-2xl font-bold mb-6 text-center">Create Account</h2>
            <form method="POST" onsubmit="return validateRegistrationForm()">
                <div class="mb-4"><input type="text" name="full_name" id="full_name" placeholder="Full Name" class="w-full border rounded-lg p-3" required></div>
                <div class="mb-4"><input type="text" name="username" id="username" placeholder="Username" class="w-full border rounded-lg p-3" required></div>
                <div class="mb-4"><input type="email" name="email" id="email" placeholder="Email (Gmail only)" class="w-full border rounded-lg p-3" required></div>
                <div class="mb-4"><input type="password" name="password" id="password" placeholder="Password" class="w-full border rounded-lg p-3" required></div>
                <div class="mb-4"><input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" class="w-full border rounded-lg p-3" required></div>
                <button type="submit" name="register" class="w-full bg-black text-white py-3 rounded-lg font-bold">Sign Up</button>
            </form>
            <p class="text-center mt-4">Already have an account? <a href="#" onclick="openModal('loginModal')" class="text-amber-600">Login</a></p>
        </div>
    </div>
<?php
elseif ($profile_dashboard):
    $purchase_history = array();
    $purchases_res = $conn->query("SELECT * FROM payment_requests WHERE user_id={$user['id']} AND status='approved' ORDER BY created_at DESC");
    while($pr = $purchases_res->fetch_assoc()) {
        $courses_list_purchased = array();
        if($pr['course_ids']) {
            $ids = explode(',', $pr['course_ids']);
            foreach($ids as $id) if(isset($course_list[$id])) $courses_list_purchased[] = $course_list[$id];
        } elseif($pr['course_id'] && isset($course_list[$pr['course_id']])) {
            $courses_list_purchased[] = $course_list[$pr['course_id']];
        }
        $purchase_history[] = array(
            'date' => $pr['created_at'],
            'amount' => $pr['amount'],
            'transaction_id' => $pr['transaction_id'],
            'courses' => $courses_list_purchased,
            'payment_method' => $pr['payment_method'],
            'payment_number' => $pr['payment_number']
        );
    }
?>
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-12">
        <div class="profile-card rounded-2xl p-8 text-white shadow-xl mb-8">
            <div class="flex items-center gap-6 flex-wrap">
                <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center shadow-lg">
                    <span class="material-symbols-outlined text-6xl text-indigo-600">account_circle</span>
                </div>
                <div>
                    <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <p class="text-white/80">@<?php echo htmlspecialchars($user['username']); ?></p>
                    <p class="text-white/70 text-sm">Member since <?php echo isset($user['created_at']) ? date('F Y', strtotime($user['created_at'])) : 'Recently'; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="flex flex-wrap gap-2 border-b p-4 bg-gray-50">
                <button class="profile-tab active px-5 py-2 rounded-full bg-indigo-600 text-white font-medium shadow transition" onclick="showProfileTab('personal')">📋 Personal Info</button>
                <button class="profile-tab px-5 py-2 rounded-full bg-gray-200 text-gray-700 font-medium hover:bg-gray-300 transition" onclick="showProfileTab('purchases')">🛒 My Purchases</button>
                <button class="profile-tab px-5 py-2 rounded-full bg-gray-200 text-gray-700 font-medium hover:bg-gray-300 transition" onclick="showProfileTab('update')">✏️ Update Profile</button>
                <button class="profile-tab px-5 py-2 rounded-full bg-gray-200 text-gray-700 font-medium hover:bg-gray-300 transition" onclick="showProfileTab('logout')">🚪 Logout</button>
            </div>
            <div class="p-6">
                <div id="personal-tab" class="tab-content">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800 flex items-center gap-2"><span class="material-symbols-outlined text-indigo-500">person</span> Personal Information</h2>
                    <div class="overflow-x-auto shadow-md rounded-xl">
                        <table class="min-w-full bg-white border border-gray-200 rounded-xl">
                            <thead class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                                <tr><th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Field</th><th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Details</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr class="hover:bg-gray-50 transition duration-200"><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">👤 Username</td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($user['username']); ?></td></tr>
                                <tr class="hover:bg-gray-50 transition duration-200"><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">📧 Email</td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($user['email']); ?></td></tr>
                                <tr class="hover:bg-gray-50 transition duration-200"><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">📛 Full Name</td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($user['full_name']); ?></td></tr>
                                <tr class="hover:bg-gray-50 transition duration-200"><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">📅 Member Since</td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo isset($user['created_at']) ? date('F d, Y', strtotime($user['created_at'])) : 'Recently'; ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="purchases-tab" class="tab-content" style="display:none;">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800 flex items-center gap-2"><span class="material-symbols-outlined text-amber-500">shopping_bag</span> Purchase History</h2>
                    <?php if(count($purchase_history) > 0): ?>
                        <div class="overflow-x-auto shadow-md rounded-xl">
                            <table class="min-w-full bg-white border border-gray-200 rounded-xl">
                                <thead class="bg-gradient-to-r from-amber-500 to-orange-500 text-white">
                                    <tr><th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Date</th><th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Course(s)</th><th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Amount</th><th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Transaction ID</th><th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Payment Method</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($purchase_history as $p): ?>
                                        <tr class="hover:bg-gray-50 transition duration-200">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo date('M d, Y', strtotime($p['date'])); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-700"><?php foreach($p['courses'] as $co): ?><a href="?course=<?php echo $co['id']; ?>" class="text-indigo-600 hover:underline block"><?php echo htmlspecialchars($co['name']); ?></a><?php endforeach; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-amber-600">$<?php echo number_format($p['amount'],2); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600"><?php echo htmlspecialchars($p['transaction_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-blue-100 text-blue-800 text-xs"><?php echo htmlspecialchars($p['payment_method']); ?> (<?php echo htmlspecialchars($p['payment_number']); ?>)</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="bg-gray-50 rounded-xl p-8 text-center text-gray-500 shadow-inner"><span class="material-symbols-outlined text-5xl text-gray-400">receipt</span><p class="mt-2">No purchases yet. Start learning today!</p><a href="new.php#courses" class="mt-4 inline-block bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition">Browse Courses</a></div>
                    <?php endif; ?>
                </div>
                <div id="update-tab" class="tab-content" style="display:none;">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800 flex items-center gap-2"><span class="material-symbols-outlined text-green-500">edit_note</span> Update Your Profile</h2>
                    <div class="max-w-2xl mx-auto bg-gray-50 rounded-xl p-6 shadow-md">
                        <form method="POST" class="space-y-5">
                            <div><label class="block text-sm font-semibold text-gray-700 mb-1">Full Name</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition" required></div>
                            <div><label class="block text-sm font-semibold text-gray-700 mb-1">Username</label><input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition" required></div>
                            <div><label class="block text-sm font-semibold text-gray-700 mb-1">Email Address</label><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition" required></div>
                            <div><label class="block text-sm font-semibold text-gray-700 mb-1">New Password <span class="text-xs text-gray-500">(leave blank to keep current)</span></label><input type="password" name="password" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition" placeholder="••••••••"></div>
                            <div class="pt-2"><button type="submit" name="update_profile" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold py-3 rounded-lg shadow-md hover:shadow-lg transition transform hover:scale-[1.02]">Save Changes</button></div>
                        </form>
                    </div>
                </div>
                <div id="logout-tab" class="tab-content" style="display:none;">
                    <div class="text-center py-8"><span class="material-symbols-outlined text-6xl text-red-400">logout</span><h2 class="text-2xl font-bold mt-4 text-gray-800">Ready to leave?</h2><p class="text-gray-600 mt-2">You will be redirected to the home page after logging out.</p><button onclick="confirmLogout()" class="mt-6 bg-red-600 text-white px-8 py-3 rounded-lg font-bold hover:bg-red-700 transition shadow-md">Yes, Logout</button></div>
                </div>
            </div>
        </div>
    </div>
<?php
elseif ($view_course && $current_course):
    $has_access = $user ? hasAccess($conn, $user['id'], $view_course) : false;
    $is_pending = $user ? hasPendingRequest($conn, $user['id'], $view_course) : false;
    $videos_to_show = getVideosForCourse($conn, $view_course);
?>
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-12" id="courseDetail">
        <a href="new.php" class="inline-flex items-center gap-2 text-primary font-bold mb-6"><span class="material-symbols-outlined">arrow_back</span> Back to Home</a>
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <img src="<?php echo $current_course['image']; ?>" class="w-full h-64 object-cover" alt="Course">
            <div class="p-8">
                <h1 class="text-3xl md:text-4xl font-bold mb-4"><?php echo $current_course['name']; ?></h1>
                <p class="text-gray-600 mb-6"><?php echo $current_course['description']; ?></p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-gray-50 p-3 rounded-lg text-center"><span class="material-symbols-outlined">schedule</span><div class="font-bold"><?php echo $current_course['duration']; ?></div></div>
                    <div class="bg-gray-50 p-3 rounded-lg text-center"><span class="material-symbols-outlined">signal_cellular_alt</span><div class="font-bold"><?php echo $current_course['level']; ?></div></div>
                    <div class="bg-gray-50 p-3 rounded-lg text-center"><span class="material-symbols-outlined">person</span><div class="font-bold"><?php echo $current_course['instructor']; ?></div></div>
                    <div class="bg-gray-50 p-3 rounded-lg text-center"><span class="material-symbols-outlined">star</span><div class="font-bold"><?php echo $current_course['rating']; ?> (<?php echo number_format($current_course['students']); ?>)</div></div>
                </div>
                <?php if(!$user || (!$has_access && !$is_pending)): ?>
                    <div class="text-center p-8 bg-gray-100 rounded-xl">
                        <span class="material-symbols-outlined text-5xl text-gray-500">lock</span>
                        <h2 class="text-xl font-bold mt-2">Course Locked</h2>
                        <p class="mb-4">Purchase this course to unlock all videos.</p>
                        <div class="text-3xl font-bold mb-4">$<?php echo $current_course['price']; ?></div>
                        <?php if(!$user): ?>
                            <button onclick="openRegisterTab()" class="bg-amber-600 text-white px-6 py-3 rounded-lg font-bold">Sign Up to Enroll</button>
                        <?php else: ?>
                            <button onclick="openSelectPaymentMethod('single', <?php echo $current_course['id']; ?>, '<?php echo addslashes($current_course['name']); ?>', <?php echo $current_course['price']; ?>)" class="bg-amber-600 text-white px-6 py-3 rounded-lg font-bold">Enroll Now - $<?php echo $current_course['price']; ?></button>
                        <?php endif; ?>
                    </div>
                <?php elseif($is_pending && !$has_access): ?>
                    <div class="bg-yellow-100 text-yellow-800 p-4 rounded-xl text-center">Your payment request is pending approval.</div>
                <?php elseif($has_access): ?>
                    <div>
                        <h2 class="text-2xl font-bold mb-4">Course Videos</h2>
                        <div class="grid md:grid-cols-2 gap-6">
                            <?php foreach($videos_to_show as $video):
                                $vid_progress = getUserProgress($conn, $user['id'], $video['id']);
                            ?>
                                <a href="?page=watch&video_id=<?php echo $video['id']; ?>" class="bg-gray-50 border border-gray-200 rounded-xl p-4 flex justify-between items-center hover:shadow-md transition">
                                    <div><span class="material-symbols-outlined">play_circle</span> <?php echo htmlspecialchars($video['title']); ?></div>
                                    <div class="text-sm bg-blue-100 px-2 py-1 rounded-full"><?php echo $vid_progress['progress_percent']; ?>%</div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
else: // HOME PAGE
?>
    <section class="relative min-h-[600px] flex items-center overflow-hidden bg-blue-900">
        <div class="absolute inset-0 z-0 opacity-30">
            <img class="w-full h-full object-cover" src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f" alt="Hero bg">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-900 via-blue-900/80 to-transparent"></div>
        </div>
        <div class="relative z-10 w-full max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-20">
            <div class="max-w-2xl">
                <span class="inline-block px-4 py-1.5 mb-6 rounded-full bg-amber-600 text-white text-sm">Global Learning Hub</span>
                <h1 class="text-4xl md:text-6xl text-white font-bold mb-6">Excellence in <span class="text-yellow-400">Learning</span></h1>
                <p class="text-lg text-white/80 mb-10">Master the skills of the future with industry-leading courses. Join a community of over 50,000 learners.</p>
                <div class="flex flex-wrap gap-4">
                    <a href="#courses" class="px-8 py-4 bg-yellow-400 text-black font-bold rounded-lg shadow-lg hover:shadow-xl transition">Explore Courses</a>
                    <a href="?page=about" class="px-8 py-4 bg-transparent border-2 border-white text-white font-bold rounded-lg hover:bg-white/10 transition">Learn More</a>
                </div>
            </div>
        </div>
    </section>
    <section class="py-20 bg-gray-50" id="categories">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop">
            <div class="flex justify-between items-end mb-12">
                <div><h2 class="text-3xl font-bold text-gray-900 mb-2">Popular Categories</h2><p class="text-gray-600">Choose your path from our diverse library.</p></div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php
                // Category data: name, icon, image (unsplash)
                $categories = [
                    'Web Development' => ['icon' => 'code', 'image' => 'https://images.unsplash.com/photo-1627398242454-45a1465c2479?auto=format&fit=crop&w=800&q=80'],
                    'Data Science' => ['icon' => 'analytics', 'image' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=800&q=80'],
                    'Database' => ['icon' => 'database', 'image' => 'https://images.unsplash.com/photo-1542744095-fcf48d80b0fd?auto=format&fit=crop&w=800&q=80'],
                    'Business' => ['icon' => 'business_center', 'image' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=800&q=80'],
                    'Digital Marketing' => ['icon' => 'campaign', 'image' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=80'],
                    'Graphic Design' => ['icon' => 'palette', 'image' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5?auto=format&fit=crop&w=800&q=80'],
                    'Mobile Development' => ['icon' => 'phone_android', 'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?auto=format&fit=crop&w=800&q=80'],
                    'Cybersecurity' => ['icon' => 'security', 'image' => 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?auto=format&fit=crop&w=800&q=80'],
                    'Design' => ['icon' => 'design_services', 'image' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5?auto=format&fit=crop&w=800&q=80'],
                    'Cloud Computing' => ['icon' => 'cloud', 'image' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=800&q=80'],
                ];
                foreach($categories as $cat=>$data):
                ?>
                <a href="?category=<?php echo urlencode($cat); ?>" class="category-card group">
                    <div class="bg-image" style="background-image: url('<?php echo $data['image']; ?>');"></div>
                    <div class="overlay"></div>
                    <div class="content">
                        <div class="icon-circle">
                            <span class="material-symbols-outlined text-white text-3xl"><?php echo $data['icon']; ?></span>
                        </div>
                        <h3><?php echo $cat; ?></h3>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <section class="py-20 bg-white" id="courses">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop">
            <div class="flex justify-between items-end mb-12">
                <h2 class="text-3xl font-bold text-gray-900"><?php echo $selected_category ? htmlspecialchars($selected_category).' Courses' : 'Featured Courses'; ?></h2>
                <?php if($selected_category || $search_query): ?><a href="new.php" class="text-amber-600 font-bold">Clear filters</a><?php endif; ?>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if(count($filtered_courses) > 0): foreach($filtered_courses as $course):
                    $is_enrolled = isset($enrolled_courses[$course['id']]);
                    $is_pending = $user ? hasPendingRequest($conn, $user['id'], $course['id']) : false;
                    $progress = ($user && $is_enrolled) ? getCourseProgress($conn, $user['id'], $course['id']) : 0;
                ?>
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition group">
                    <div class="relative h-48 overflow-hidden"><img src="<?php echo $course['image']; ?>" class="w-full h-full object-cover group-hover:scale-105 transition"><div class="absolute top-4 left-4 bg-amber-600 text-white px-3 py-1 rounded-full text-xs font-bold"><?php echo $course['category']; ?></div></div>
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-3"><div class="flex text-yellow-500"><?php for($i=1;$i<=5;$i++) echo '<span class="material-symbols-outlined text-sm" style="font-variation-settings:\'FILL\' '.($i<=floor($course['rating'])?'1':'0').'">star</span>'; ?></div><span class="text-gray-600 text-xs">(<?php echo number_format($course['students']); ?> reviews)</span></div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2"><?php echo $course['name']; ?></h3>
                        <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo substr($course['description'],0,100); ?>...</p>
                        <div class="flex justify-between items-center">
                            <?php if($is_enrolled): ?>
                                <a href="?course=<?php echo $course['id']; ?>" class="px-4 py-2 bg-green-600 text-white rounded-lg font-bold text-sm">Continue (<?php echo $progress; ?>%)</a>
                            <?php else: ?>
                                <span class="text-2xl font-bold">$<?php echo $course['price']; ?></span>
                                <?php if($user): ?>
                                    <?php if($is_pending): ?>
                                        <span class="px-3 py-1 bg-yellow-200 text-yellow-800 rounded-full text-xs">Pending</span>
                                    <?php else: ?>
                                        <div class="flex gap-2"><a href="?add_to_cart=<?php echo $course['id']; ?>" class="px-3 py-2 bg-gray-100 rounded-lg"><span class="material-symbols-outlined text-sm">add_shopping_cart</span></a><button onclick="openSelectPaymentMethod('single', <?php echo $course['id']; ?>, '<?php echo addslashes($course['name']); ?>', <?php echo $course['price']; ?>)" class="px-4 py-2 bg-blue-900 text-white rounded-lg font-bold text-sm">Buy Now</button></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button onclick="openRegisterTab()" class="px-4 py-2 bg-blue-900 text-white rounded-lg font-bold text-sm">Enroll</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; else: ?>
                <div class="col-span-full text-center py-12">No courses found matching your criteria.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <section class="py-20 bg-blue-900 text-white">
        <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop text-center">
            <h2 class="text-3xl font-bold mb-16">Why Professionals Choose Salaam Online</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                <div><div class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-6"><span class="material-symbols-outlined text-4xl text-yellow-400">verified_user</span></div><h3 class="text-xl font-bold mb-4 text-yellow-400">Secure & Verified</h3><p>Your data and certifications are secured with industry-leading encryption.</p></div>
                <div><div class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-6"><span class="material-symbols-outlined text-4xl text-yellow-400">psychology</span></div><h3 class="text-xl font-bold mb-4 text-yellow-400">Expert Tutors</h3><p>Learn from professionals currently working at top companies.</p></div>
                <div><div class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-6"><span class="material-symbols-outlined text-4xl text-yellow-400">update</span></div><h3 class="text-xl font-bold mb-4 text-yellow-400">Flexible Learning</h3><p>Study at your own pace with mobile-friendly content.</p></div>
            </div>
        </div>
    </section>
<?php endif; ?>
</main>

<footer class="bg-white border-t border-gray-200">
    <div class="max-w-container-max mx-auto px-margin-mobile md:px-margin-desktop py-12 grid grid-cols-1 md:grid-cols-4 gap-8">
        <div><div class="text-xl font-bold text-gray-900 mb-4">Salaam Online</div><p class="text-gray-600">Empowering global learners through precision education.</p></div>
        <div><h4 class="text-sm font-bold text-gray-900 mb-4 uppercase">Platform</h4><ul class="space-y-2"><li><a href="new.php" class="text-gray-600 hover:text-amber-600">Home</a></li><li><a href="?page=about" class="text-gray-600 hover:text-amber-600">About</a></li><?php if($user): ?><li><a href="?page=help" class="text-gray-600 hover:text-amber-600">Help Center</a></li><?php endif; ?><li><a href="?page=contact" class="text-gray-600 hover:text-amber-600">Contact</a></li></ul></div>
        <div><h4 class="text-sm font-bold text-gray-900 mb-4 uppercase">Support</h4><ul class="space-y-2"><li><a href="?page=help" class="text-gray-600 hover:text-amber-600">Submit Ticket</a></li><li><a href="#" class="text-gray-600 hover:text-amber-600">Privacy Policy</a></li><li><a href="#" class="text-gray-600 hover:text-amber-600">Terms of Service</a></li></ul></div>
        <div><h4 class="text-sm font-bold text-gray-900 mb-4 uppercase">Contact</h4><p class="text-gray-600">salaamonlin100@gmail.com</p><p class="text-gray-600">+252 63 4621822</p></div>
    </div>
    <div class="border-t border-gray-200 py-6 text-center text-gray-500 text-sm">© <?php echo date('Y'); ?> Salaam Online. Excellence in Learning.</div>
</footer>

<!-- Modal for selecting payment method (3 cards) -->
<div id="selectPaymentModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50" onclick="if(event.target===this)closeModal('selectPaymentModal')">
    <div class="bg-white rounded-xl max-w-2xl w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold">Choose Payment Method</h3>
            <button onclick="closeModal('selectPaymentModal')" class="text-2xl">&times;</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div onclick="selectPaymentMethod('ZAAD', '063462122')" class="payment-method-card bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-4 text-white text-center">
                <span class="material-symbols-outlined text-5xl">account_balance_wallet</span>
                <h3 class="text-xl font-bold mt-2">ZAAD</h3>
                <p class="text-sm">063462122</p>
                <p class="text-xs mt-2">Click to select</p>
            </div>
            <div onclick="selectPaymentMethod('EDAHAB', '065462122')" class="payment-method-card bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-4 text-white text-center">
                <span class="material-symbols-outlined text-5xl">payments</span>
                <h3 class="text-xl font-bold mt-2">EDAHAB</h3>
                <p class="text-sm">065462122</p>
                <p class="text-xs mt-2">Click to select</p>
            </div>
            <div onclick="selectPaymentMethod('SOLTELCO', '0672357971')" class="payment-method-card bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-4 text-white text-center">
                <span class="material-symbols-outlined text-5xl">smartphone</span>
                <h3 class="text-xl font-bold mt-2">SOLTELCO</h3>
                <p class="text-sm">0672357971</p>
                <p class="text-xs mt-2">Click to select</p>
            </div>
        </div>
    </div>
</div>

<div id="loginModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50" onclick="if(event.target===this)closeModal('loginModal')">
    <div class="bg-white rounded-xl max-w-md w-full p-6">
        <div class="flex justify-between"><h3 class="text-xl font-bold">Login</h3><button onclick="closeModal('loginModal')" class="text-2xl">&times;</button></div>
        <form method="POST" class="mt-4">
            <div class="mb-4"><input type="email" name="email" placeholder="Email" class="w-full border rounded-lg p-3" required></div>
            <div class="mb-4"><input type="password" name="password" placeholder="Password" class="w-full border rounded-lg p-3" required></div>
            <button type="submit" name="login" class="w-full bg-black text-white py-3 rounded-lg font-bold">Login</button>
        </form>
        <p class="text-center mt-4">Don't have an account? <a href="#" onclick="openRegisterTab();closeModal('loginModal')" class="text-amber-600">Sign Up</a></p>
    </div>
</div>

<div id="paymentModal" class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50" onclick="if(event.target===this)closeModal('paymentModal')">
    <div class="bg-white rounded-xl max-w-md w-full p-6">
        <div class="flex justify-between"><h3 class="text-xl font-bold">Complete Payment</h3><button onclick="closeModal('paymentModal')" class="text-2xl">&times;</button></div>
        <div class="mt-4 p-3 bg-blue-50 rounded text-center"><strong>Send payment to: <span id="paymentNumberDisplay">063462122</span></strong><br><span id="paymentMethodDisplay" class="text-sm font-bold">ZAAD</span></div>
        <form method="POST" class="mt-4" id="singlePaymentForm">
            <input type="hidden" name="submit_payment_request" value="1">
            <input type="hidden" name="course_id" id="paymentCourseId">
            <input type="hidden" name="amount" id="paymentAmountValue">
            <input type="hidden" name="payment_method" id="paymentMethodInput" value="ZAAD">
            <input type="hidden" name="payment_number" id="paymentNumberInput" value="063462122">
            <div class="mb-3"><input type="text" id="paymentCourseName" class="w-full border rounded-lg p-2 bg-gray-100" readonly></div>
            <div class="mb-3"><input type="text" name="fullname" value="<?php echo $user ? htmlspecialchars($user['full_name']) : ''; ?>" placeholder="Full Name" class="w-full border rounded-lg p-2" required></div>
            <div class="mb-3"><input type="email" name="email" value="<?php echo $user ? htmlspecialchars($user['email']) : ''; ?>" placeholder="Email" class="w-full border rounded-lg p-2" required></div>
            <div class="mb-3"><input type="text" name="transaction_id" id="transactionIdSingle" placeholder="Transaction ID" class="w-full border rounded-lg p-2" required></div>
            <div class="mb-3"><input type="text" id="paymentAmountDisplay" class="w-full border rounded-lg p-2 bg-gray-100" readonly></div>
            <button type="button" id="singleSubmitBtn" class="w-full bg-amber-600 text-white py-2 rounded-lg font-bold">Submit Request</button>
        </form>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="confirmation-modal">
    <div class="confirmation-card">
        <h3>🔔 Xaqiiji Lacag Bixinta</h3>
        <div class="confirmation-details" id="confirmDetails">
            <!-- dynamic content -->
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button id="cancelConfirmBtn" class="btn-cancel">Cancel</button>
            <button id="okConfirmBtn" class="btn-confirm">OK</button>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div id="receiptModal" class="receipt-modal-overlay">
    <div class="receipt-card">
        <?php if ($receiptData): 
            $method = $receiptData['payment_method'];
            $number = $receiptData['payment_number'];
            $amount = $receiptData['amount'];
            $txn_id = $receiptData['transaction_id'];
            $course = $receiptData['course_name'];
        ?>
            <div class="service-badge">[<?php echo htmlspecialchars($method); ?> SERVICES-]</div>
            <h2 style="font-size: 1.8rem; font-weight: bold; margin-bottom: 0.5rem;">KUSOO DHAWOOW SALAAM ONLINE</h2>
            <p style="font-size: 1.2rem; margin-bottom: 0.5rem;">TIX: <strong class="txn-id"><?php echo htmlspecialchars($txn_id); ?></strong></p>
            <p style="font-size: 1.5rem; font-weight: bold; margin: 1rem 0;">$<?php echo number_format($amount,2); ?> AYAAD UDIRTAY (<?php echo htmlspecialchars($number); ?>)</p>
            <p style="margin-bottom: 1rem;">Kooriska: <?php echo htmlspecialchars($course); ?></p>
            <div style="margin: 1rem 0; padding: 0.5rem; background: rgba(255,255,255,0.15); border-radius: 12px;">
                <p style="font-size: 0.9rem;">HALKAN KA SOO MUUJI TRANSACTION ID-GA:</p>
                <p style="font-family: monospace; font-size: 1.1rem; letter-spacing: 1px;"><?php echo htmlspecialchars($txn_id); ?></p>
            </div>
            <button class="btn-close" onclick="closeReceiptModal()">MAHADSANID MACMIIL</button>
            <p style="font-size: 0.7rem; margin-top: 1rem; opacity: 0.8;">Codsi kaaga waa la diray, waxaa laguu jawaabi doonaa 24 saac gudahood.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    let pendingPaymentData = null;
    let pendingCartData = false;
    let currentConfirmForm = null;

    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    function openRegisterTab() { window.open('?register=1', '_blank'); }

    function openSelectPaymentMethod(type, courseId = null, courseName = null, price = null) {
        pendingPaymentData = { type, courseId, courseName, price };
        openModal('selectPaymentModal');
    }

    function selectPaymentMethod(method, number) {
        closeModal('selectPaymentModal');
        if (pendingPaymentData && pendingPaymentData.type === 'single') {
            document.getElementById('paymentCourseId').value = pendingPaymentData.courseId;
            document.getElementById('paymentCourseName').value = pendingPaymentData.courseName;
            document.getElementById('paymentAmountValue').value = pendingPaymentData.price;
            document.getElementById('paymentAmountDisplay').value = '$' + pendingPaymentData.price;
            document.getElementById('paymentMethodInput').value = method;
            document.getElementById('paymentNumberInput').value = number;
            document.getElementById('paymentMethodDisplay').innerText = method;
            document.getElementById('paymentNumberDisplay').innerText = number;
            openModal('paymentModal');
        } else if (pendingPaymentData && pendingPaymentData.type === 'cart') {
            document.getElementById('cartPaymentMethodInput').value = method;
            document.getElementById('cartPaymentNumberInput').value = number;
            document.getElementById('cartPaymentMethodDisplay').innerText = method;
            document.getElementById('cartPaymentNumberDisplay').innerText = number;
            openModal('cartPaymentModal');
        }
        pendingPaymentData = null;
    }

    function openCartPaymentModal() {
        openSelectPaymentMethod('cart');
    }

    function toggleNotificationDropdown() {
        var dropdown = document.getElementById('notificationDropdown');
        if(dropdown.classList) dropdown.classList.toggle('show');
    }
    document.addEventListener('click', function(e) {
        var bell = document.querySelector('.notification-bell');
        var dropdown = document.getElementById('notificationDropdown');
        if(bell && !bell.contains(e.target) && dropdown && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
    function validateRegistrationForm() {
        var email = document.getElementById('email').value;
        if (!email.endsWith('@gmail.com')) { alert('Email must be a Gmail address (@gmail.com)'); return false; }
        var pwd = document.getElementById('password').value;
        if (pwd.length < 8 || !/[a-zA-Z]/.test(pwd) || !/[0-9]/.test(pwd) || !/[!@#$%^&*(),.?":{}|<>]/.test(pwd)) {
            alert('Password must be at least 8 chars, include letters, numbers and special characters.');
            return false;
        }
        if (pwd !== document.getElementById('confirm_password').value) { alert('Passwords do not match'); return false; }
        return true;
    }
    function showProfileTab(tabName) {
        var tabs = document.querySelectorAll('.tab-content');
        for(var i=0;i<tabs.length;i++) tabs[i].style.display = 'none';
        document.getElementById(tabName + '-tab').style.display = 'block';
        var btns = document.querySelectorAll('.profile-tab');
        for(var i=0;i<btns.length;i++) {
            btns[i].classList.remove('active','bg-indigo-600','text-white');
            btns[i].classList.add('bg-gray-200','text-gray-700');
        }
        if(tabName === 'personal') btns[0].classList.add('bg-indigo-600','text-white');
        else if(tabName === 'purchases') btns[1].classList.add('bg-indigo-600','text-white');
        else if(tabName === 'update') btns[2].classList.add('bg-indigo-600','text-white');
        else if(tabName === 'logout') btns[3].classList.add('bg-indigo-600','text-white');
    }
    function confirmLogout() { if(confirm('Logout?')) window.location.href = '?logout=1'; }
    
    function closeReceiptModal() {
        document.getElementById('receiptModal').style.display = 'none';
    }
    <?php if ($receiptData): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('receiptModal').style.display = 'flex';
    });
    <?php endif; ?>

    function showConfirmation(formType) {
        let details = {};
        if (formType === 'single') {
            let form = document.getElementById('singlePaymentForm');
            details.courseName = form.querySelector('#paymentCourseName').value;
            details.amount = form.querySelector('#paymentAmountDisplay').value;
            details.fullname = form.querySelector('input[name="fullname"]').value;
            details.email = form.querySelector('input[name="email"]').value;
            details.transactionId = form.querySelector('#transactionIdSingle').value;
            details.paymentMethod = document.getElementById('paymentMethodDisplay').innerText;
            details.paymentNumber = document.getElementById('paymentNumberDisplay').innerText;
            if (!details.transactionId.trim()) {
                alert("Fadlan geli Transaction ID");
                return false;
            }
            currentConfirmForm = form;
        } else if (formType === 'cart') {
            let form = document.getElementById('cartPaymentForm');
            details.courseName = "Cart items (multiple courses)";
            details.amount = form.querySelector('input[name="amount"]').value;
            details.fullname = form.querySelector('input[name="fullname"]').value;
            details.email = form.querySelector('input[name="email"]').value;
            details.transactionId = form.querySelector('#transactionIdCart').value;
            details.paymentMethod = document.getElementById('cartPaymentMethodDisplay').innerText;
            details.paymentNumber = document.getElementById('cartPaymentNumberDisplay').innerText;
            if (!details.transactionId.trim()) {
                alert("Fadlan geli Transaction ID");
                return false;
            }
            currentConfirmForm = form;
        } else {
            return false;
        }

        let html = `
            <p><strong>Habka Lacag bixinta:</strong> <span>${details.paymentMethod} (${details.paymentNumber})</span></p>
            <p><strong>Transaction ID:</strong> <span>${details.transactionId}</span></p>
            <p><strong>Lacagta:</strong> <span>${details.amount}</span></p>
            <p><strong>Kooriska:</strong> <span>${details.courseName}</span></p>
            <p><strong>Magaca:</strong> <span>${details.fullname}</span></p>
            <p><strong>Email:</strong> <span>${details.email}</span></p>
        `;
        document.getElementById('confirmDetails').innerHTML = html;
        openModal('confirmModal');
        return true;
    }

    document.getElementById('okConfirmBtn').onclick = function() {
        if (currentConfirmForm) {
            currentConfirmForm.submit();
        }
        closeModal('confirmModal');
    };

    document.getElementById('cancelConfirmBtn').onclick = function() {
        closeModal('confirmModal');
        currentConfirmForm = null;
    };

    document.getElementById('singleSubmitBtn').onclick = function(e) {
        e.preventDefault();
        showConfirmation('single');
    };
    document.getElementById('cartSubmitBtn').onclick = function(e) {
        e.preventDefault();
        showConfirmation('cart');
    };

    <?php if($message): ?>
    setTimeout(function(){
        var div = document.createElement('div');
        div.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        div.innerHTML = '<?php echo addslashes($message); ?><button onclick="this.parentElement.remove()" class="ml-4">&times;</button>';
        document.body.appendChild(div);
        setTimeout(function(){ div.remove(); }, 4000);
    }, 100);
    <?php endif; ?>
    <?php if($contact_message): ?>
    setTimeout(function(){
        var div = document.createElement('div');
        div.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        div.innerHTML = '<?php echo addslashes($contact_message); ?><button onclick="this.parentElement.remove()" class="ml-4">&times;</button>';
        document.body.appendChild(div);
        setTimeout(function(){ div.remove(); }, 4000);
    }, 100);
    <?php endif; ?>
    <?php if($cart_added): ?>
    setTimeout(function(){
        var div = document.createElement('div');
        div.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
        div.innerHTML = 'Course added to cart!<button onclick="this.parentElement.remove()" class="ml-4">&times;</button>';
        document.body.appendChild(div);
        setTimeout(function(){ div.remove(); }, 3000);
    }, 100);
    <?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>
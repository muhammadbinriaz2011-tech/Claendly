<?php
// Calendly-like Scheduling Platform - Single File Version
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbmiyvszhhnffq');
define('DB_USER', 'ukrfhh29eellf');
define('DB_PASS', 'jua2ursxz7gb');

class Database {
    private $connection;
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database instance
$db = new Database();
$pdo = $db->getConnection();

// Helper functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserById($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function formatDateTime($datetime) {
    return date('M j, Y \a\t g:i A', strtotime($datetime));
}

function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

function formatTime($time) {
    return date('g:i A', strtotime($time));
}

function sendEmail($to, $subject, $message) {
    $headers = "From: noreply@calendly.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    // For testing purposes, also log the email content
    $log_message = "Email to: $to\nSubject: $subject\nMessage: $message\n\n";
    error_log($log_message, 3, "email_log.txt");
    
    return mail($to, $subject, $message, $headers);
}

function generateBookingLink($userId) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    return $protocol . "://" . $host . $script_name . "?page=book&user=" . $userId;
}

function getAvailableSlots($userId, $date) {
    global $pdo;
    
    $dayOfWeek = date('w', strtotime($date));
    
    $stmt = $pdo->prepare("
        SELECT start_time, end_time 
        FROM availability 
        WHERE user_id = ? AND day_of_week = ? AND is_active = 1
    ");
    $stmt->execute([$userId, $dayOfWeek]);
    $availability = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT start_time, end_time 
        FROM appointments 
        WHERE user_id = ? AND DATE(start_time) = ? AND status != 'cancelled'
    ");
    $stmt->execute([$userId, $date]);
    $bookings = $stmt->fetchAll();
    
    $availableSlots = [];
    
    foreach ($availability as $slot) {
        $start = strtotime($slot['start_time']);
        $end = strtotime($slot['end_time']);
        
        $current = $start;
        while ($current < $end) {
            $slotStart = date('H:i:s', $current);
            $slotEnd = date('H:i:s', $current + 1800);
            
            $isBooked = false;
            foreach ($bookings as $booking) {
                $bookingStart = date('H:i:s', strtotime($booking['start_time']));
                $bookingEnd = date('H:i:s', strtotime($booking['end_time']));
                
                if (($slotStart >= $bookingStart && $slotStart < $bookingEnd) ||
                    ($slotEnd > $bookingStart && $slotEnd <= $bookingEnd) ||
                    ($slotStart <= $bookingStart && $slotEnd >= $bookingEnd)) {
                    $isBooked = true;
                    break;
                }
            }
            
            if (!$isBooked && $current + 1800 <= $end) {
                $availableSlots[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                    'display' => date('g:i A', $current) . ' - ' . date('g:i A', $current + 1800)
                ];
            }
            
            $current += 1800;
        }
    }
    
    return $availableSlots;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: calendly_single.php');
    exit;
}

// Get current page
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($page); ?> - Calendly</title>
    <style>
        /* Calendly-like Scheduling Platform Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .header {
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #0066cc;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #0066cc;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #0066cc;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            background: #0052a3;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
            text-align: center;
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        /* Forms */
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 400px;
            margin: 2rem auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
        }

        /* Calendar */
        .calendar-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin: 2rem 0;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
        }

        .calendar-day {
            background: white;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.3s;
            min-height: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .calendar-day:hover {
            background: #f8f9fa;
        }

        .calendar-day.selected {
            background: #0066cc;
            color: white;
        }

        .calendar-day.has-slots {
            background: #d4edda;
            color: #155724;
        }

        /* Time Slots */
        .time-slots {
            margin-top: 2rem;
        }

        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .slot {
            background: white;
            border: 2px solid #e9ecef;
            padding: 1rem;
            text-align: center;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }

        .slot:hover {
            border-color: #0066cc;
            background: #f8f9fa;
        }

        .slot.selected {
            border-color: #0066cc;
            background: #e7f3ff;
        }

        /* Dashboard */
        .dashboard {
            padding: 2rem 0;
        }

        .dashboard-header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #0066cc;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        /* Appointments List */
        .appointments-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .appointment-item {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .appointment-item:last-child {
            border-bottom: none;
        }

        .appointment-info h4 {
            margin-bottom: 0.5rem;
            color: #333;
        }

        .appointment-info p {
            color: #666;
            font-size: 0.9rem;
        }

        .appointment-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Availability Settings */
        .availability-settings {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 2rem 0;
        }

        .day-schedule {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .day-name {
            width: 100px;
            font-weight: 500;
        }

        .time-inputs {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #17a2b8;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                gap: 1rem;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .calendar-grid {
                font-size: 0.9rem;
            }
            
            .calendar-day {
                min-height: 50px;
                padding: 0.5rem;
            }
            
            .slots-grid {
                grid-template-columns: 1fr;
            }
            
            .appointment-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .appointment-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .time-inputs {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 10px;
            }
            
            .form-container {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .calendar-container {
                padding: 1rem;
            }
            
            .availability-settings {
                padding: 1rem;
            }
            
            .day-schedule {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .day-name {
                width: auto;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav container">
            <a href="calendly_single.php" class="logo">📅 Calendly</a>
            <ul class="nav-links">
                <?php if (isLoggedIn()): ?>
                    <li><a href="calendly_single.php?page=dashboard">Dashboard</a></li>
                    <li><a href="calendly_single.php?page=availability">Availability</a></li>
                    <li><a href="calendly_single.php?logout=1">Logout</a></li>
                <?php else: ?>
                    <li><a href="calendly_single.php?page=login">Login</a></li>
                    <li><a href="calendly_single.php?page=register" class="btn">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <?php
        // Homepage
        if ($page == 'home'): 
            $success_message = '';
            $error_message = '';
            
            // Handle time slot submission for logged in users
            if (isLoggedIn() && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_time_slot'])) {
                $selected_date = $_POST['selected_date'];
                $selected_time = $_POST['selected_time'];
                $visitor_name = sanitizeInput($_POST['visitor_name']);
                $visitor_email = sanitizeInput($_POST['visitor_email']);
                $visitor_phone = sanitizeInput($_POST['visitor_phone']);
                $notes = sanitizeInput($_POST['notes']);
                
                if (empty($selected_date) || empty($selected_time) || empty($visitor_name) || empty($visitor_email)) {
                    $error_message = 'Please fill in all required fields.';
                } elseif (!filter_var($visitor_email, FILTER_VALIDATE_EMAIL)) {
                    $error_message = 'Please enter a valid email address.';
                } else {
                    try {
                        $user_id = $_SESSION['user_id'];
                        $time_parts = explode(' - ', $selected_time);
                        $start_time = $selected_date . ' ' . $time_parts[0] . ':00';
                        $end_time = $selected_date . ' ' . $time_parts[1] . ':00';
                        
                        // Check for conflicts
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as count 
                            FROM appointments 
                            WHERE user_id = ? AND start_time <= ? AND end_time > ? AND status != 'cancelled'
                        ");
                        $stmt->execute([$user_id, $start_time, $start_time]);
                        $conflict = $stmt->fetch()['count'];
                        
                        if ($conflict > 0) {
                            $error_message = 'This time slot is already booked. Please select another time.';
                        } else {
                        // Validate date and time
                        if (strtotime($start_time) <= time()) {
                            throw new Exception("Cannot book appointments in the past.");
                        }
                        
                        // Create appointment
                        $stmt = $pdo->prepare("
                            INSERT INTO appointments (user_id, visitor_name, visitor_email, visitor_phone, start_time, end_time, notes) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        if (!$stmt) {
                            throw new Exception("Database prepare statement failed.");
                        }
                        
                        $result = $stmt->execute([$user_id, $visitor_name, $visitor_email, $visitor_phone, $start_time, $end_time, $notes]);
                        
                        if (!$result) {
                            throw new Exception("Failed to insert appointment into database.");
                        }
                            
                            $user = getUserById($user_id);
                            
                            // Send confirmation emails
                            $subject = "Appointment Confirmed with " . $user['name'];
                            $visitor_message = "
                                <h2>Appointment Confirmed</h2>
                                <p>Hello " . htmlspecialchars($visitor_name) . ",</p>
                                <p>Your appointment with " . htmlspecialchars($user['name']) . " has been confirmed.</p>
                                <p><strong>Date:</strong> " . formatDate($start_time) . "</p>
                                <p><strong>Time:</strong> " . formatTime($start_time) . " - " . formatTime($end_time) . "</p>
                                <p><strong>Host:</strong> " . htmlspecialchars($user['name']) . "</p>
                                <p>Thank you for scheduling with us!</p>
                            ";
                            
                            $host_subject = "New Appointment Booked";
                            $host_message = "
                                <h2>New Appointment Booked</h2>
                                <p>You have a new appointment scheduled:</p>
                                <p><strong>Visitor:</strong> " . htmlspecialchars($visitor_name) . "</p>
                                <p><strong>Email:</strong> " . htmlspecialchars($visitor_email) . "</p>
                                <p><strong>Phone:</strong> " . htmlspecialchars($visitor_phone) . "</p>
                                <p><strong>Date:</strong> " . formatDate($start_time) . "</p>
                                <p><strong>Time:</strong> " . formatTime($start_time) . " - " . formatTime($end_time) . "</p>
                                <p><strong>Notes:</strong> " . htmlspecialchars($notes) . "</p>
                            ";
                            
                            $email_sent_visitor = sendEmail($visitor_email, $subject, $visitor_message);
                            $email_sent_host = sendEmail($user['email'], $host_subject, $host_message);
                            
                            if ($email_sent_visitor && $email_sent_host) {
                                $success_message = '✅ Appointment scheduled successfully! Confirmation emails have been sent to both parties. Check your inbox!';
                            } else {
                                $success_message = '⚠️ Appointment scheduled successfully! However, there might be an issue with email delivery. Please check your spam folder.';
                            }
                        }
                    } catch (Exception $e) {
                        // Log the detailed error for debugging
                        error_log("Booking Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine(), 3, "error_log.txt");
                        $error_message = 'An error occurred while scheduling the appointment. Error: ' . $e->getMessage() . '. Please try again.';
                    }
                }
            }
        ?>
            <?php if (isLoggedIn()): ?>
                <!-- Logged In User Homepage -->
                <div class="container" style="padding: 2rem 0;">
                    <div style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                        <h1 style="color: #333; margin-bottom: 1rem;">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
                        <p style="color: #666; margin-bottom: 2rem;">Schedule your appointments easily. Select a date and time that works for you.</p>
                        
                        <!-- Booking Link for Visitors -->
                        <div style="background: #e7f3ff; padding: 1.5rem; border-radius: 6px; border-left: 4px solid #0066cc; margin-bottom: 2rem;">
                            <h3 style="margin-bottom: 1rem; color: #0066cc;">📋 Your Booking Link for Others</h3>
                            <p style="color: #666; margin-bottom: 1rem;">Share this link with anyone who wants to schedule a meeting with you:</p>
                            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                                <input type="text" value="<?php echo generateBookingLink($_SESSION['user_id']); ?>" readonly 
                                       style="flex: 1; min-width: 300px; padding: 10px; border: 2px solid #0066cc; border-radius: 4px; background: white;">
                                <button onclick="copyToClipboard('<?php echo generateBookingLink($_SESSION['user_id']); ?>')" class="btn">Copy Link</button>
                            </div>
                            <p style="margin-top: 0.5rem; color: #666; font-size: 0.9rem;">
                                ✅ Anyone can click this link to book appointments with you
                            </p>
                            <div style="margin-top: 1rem;">
                                <a href="<?php echo generateBookingLink($_SESSION['user_id']); ?>" target="_blank" class="btn btn-secondary" style="margin-right: 1rem;">
                                    🧪 Test Your Booking Link
                                </a>
                                <small style="color: #666;">Click to see how others will see your booking page</small>
                            </div>
                        </div>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-error"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <!-- Quick Schedule Form -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
                            <div>
                                <h3 style="margin-bottom: 1rem; color: #333;">Quick Schedule</h3>
                                <form method="POST" action="">
                                    <input type="hidden" name="submit_time_slot" value="1">
                                    
                                    <div class="form-group">
                                        <label for="selected_date">Select Date</label>
                                        <input type="date" id="selected_date" name="selected_date" class="form-control" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="selected_time">Select Time Slot</label>
                                        <select id="selected_time" name="selected_time" class="form-control" required>
                                            <option value="">Choose a time slot</option>
                                            <option value="09:00 AM - 09:30 AM">09:00 AM - 09:30 AM</option>
                                            <option value="09:30 AM - 10:00 AM">09:30 AM - 10:00 AM</option>
                                            <option value="10:00 AM - 10:30 AM">10:00 AM - 10:30 AM</option>
                                            <option value="10:30 AM - 11:00 AM">10:30 AM - 11:00 AM</option>
                                            <option value="11:00 AM - 11:30 AM">11:00 AM - 11:30 AM</option>
                                            <option value="11:30 AM - 12:00 PM">11:30 AM - 12:00 PM</option>
                                            <option value="12:00 PM - 12:30 PM">12:00 PM - 12:30 PM</option>
                                            <option value="12:30 PM - 01:00 PM">12:30 PM - 01:00 PM</option>
                                            <option value="01:00 PM - 01:30 PM">01:00 PM - 01:30 PM</option>
                                            <option value="01:30 PM - 02:00 PM">01:30 PM - 02:00 PM</option>
                                            <option value="02:00 PM - 02:30 PM">02:00 PM - 02:30 PM</option>
                                            <option value="02:30 PM - 03:00 PM">02:30 PM - 03:00 PM</option>
                                            <option value="03:00 PM - 03:30 PM">03:00 PM - 03:30 PM</option>
                                            <option value="03:30 PM - 04:00 PM">03:30 PM - 04:00 PM</option>
                                            <option value="04:00 PM - 04:30 PM">04:00 PM - 04:30 PM</option>
                                            <option value="04:30 PM - 05:00 PM">04:30 PM - 05:00 PM</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="visitor_name">Your Name</label>
                                        <input type="text" id="visitor_name" name="visitor_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="visitor_email">Your Email</label>
                                        <input type="email" id="visitor_email" name="visitor_email" class="form-control" 
                                               value="<?php echo htmlspecialchars($_SESSION['user_email']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="visitor_phone">Phone Number (Optional)</label>
                                        <input type="tel" id="visitor_phone" name="visitor_phone" class="form-control">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="notes">Additional Notes</label>
                                        <textarea id="notes" name="notes" class="form-control" rows="3" 
                                                  placeholder="Any additional information..."></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn" style="width: 100%;" onclick="this.innerHTML='Sending Email...'; this.disabled=true; setTimeout(function(){this.innerHTML='Schedule Appointment'; this.disabled=false;}.bind(this), 3000);">
                                        📧 Schedule Appointment & Send Email
                                    </button>
                                </form>
                            </div>
                            
                            <div>
                                <h3 style="margin-bottom: 1rem; color: #333;">Your Recent Appointments</h3>
                                <?php
                                $user_id = $_SESSION['user_id'];
                                $stmt = $pdo->prepare("
                                    SELECT * FROM appointments 
                                    WHERE user_id = ? 
                                    ORDER BY start_time DESC 
                                    LIMIT 5
                                ");
                                $stmt->execute([$user_id]);
                                $recent_appointments = $stmt->fetchAll();
                                
                                if (empty($recent_appointments)): ?>
                                    <div style="padding: 2rem; text-align: center; color: #666; background: #f8f9fa; border-radius: 6px;">
                                        <p>No appointments scheduled yet.</p>
                                        <p>Use the form to schedule your first appointment!</p>
                                    </div>
                                <?php else: ?>
                                    <div style="max-height: 400px; overflow-y: auto;">
                                        <?php foreach ($recent_appointments as $appointment): ?>
                                            <div style="background: #f8f9fa; padding: 1rem; margin-bottom: 1rem; border-radius: 6px; border-left: 4px solid #0066cc;">
                                                <h4 style="margin-bottom: 0.5rem; color: #333;"><?php echo htmlspecialchars($appointment['visitor_name']); ?></h4>
                                                <p style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">
                                                    <?php echo formatDateTime($appointment['start_time']); ?>
                                                </p>
                                                <p style="color: #0066cc; font-size: 0.9rem; margin-bottom: 0.5rem;">
                                                    <?php echo htmlspecialchars($appointment['visitor_email']); ?>
                                                </p>
                                                <span style="background: <?php echo $appointment['status'] == 'scheduled' ? '#d4edda' : ($appointment['status'] == 'completed' ? '#cce5ff' : '#f8d7da'); ?>; color: <?php echo $appointment['status'] == 'scheduled' ? '#155724' : ($appointment['status'] == 'completed' ? '#004085' : '#721c24'); ?>; padding: 4px 8px; border-radius: 4px; font-size: 12px; text-transform: uppercase;">
                                                    <?php echo $appointment['status']; ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 2rem;">
                                    <a href="calendly_single.php?page=dashboard" class="btn btn-secondary" style="width: 100%;">View Full Dashboard</a>
                                    <a href="calendly_single.php?page=availability" class="btn" style="width: 100%; margin-top: 0.5rem;">Manage Availability</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Public Homepage -->
                <section class="hero">
                    <div class="container">
                        <h1>Schedule Meetings the Easy Way</h1>
                        <p>Let others book time with you without the back-and-forth emails. Set your availability and share your booking link.</p>
                        <a href="calendly_single.php?page=register" class="btn">Get Started Free</a>
                        <a href="calendly_single.php?page=login" class="btn btn-secondary" style="margin-left: 1rem;">Login</a>
                    </div>
                </section>
            <?php endif; ?>

            <section class="container" style="padding: 4rem 0;">
                <div style="text-align: center; margin-bottom: 3rem;">
                    <h2 style="font-size: 2.5rem; margin-bottom: 1rem; color: #333;">How It Works</h2>
                    <p style="font-size: 1.1rem; color: #666; max-width: 600px; margin: 0 auto;">Simple, efficient, and professional scheduling for everyone.</p>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem; margin-top: 3rem;">
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                        <h3 style="margin-bottom: 1rem; color: #333;">Set Your Availability</h3>
                        <p style="color: #666;">Choose your available days and time slots. Set different schedules for different days of the week.</p>
                    </div>

                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🔗</div>
                        <h3 style="margin-bottom: 1rem; color: #333;">Share Your Link</h3>
                        <p style="color: #666;">Get a personalized booking link to share with clients, colleagues, or anyone who needs to schedule with you.</p>
                    </div>

                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                        <h3 style="margin-bottom: 1rem; color: #333;">Automatic Booking</h3>
                        <p style="color: #666;">Visitors can see your availability and book appointments instantly. You'll receive email notifications for all bookings.</p>
                    </div>
                </div>
            </section>

            <section style="background: white; padding: 4rem 0;">
                <div class="container">
                    <div style="text-align: center; margin-bottom: 3rem;">
                        <h2 style="font-size: 2.5rem; margin-bottom: 1rem; color: #333;">Ready to Get Started?</h2>
                        <p style="font-size: 1.1rem; color: #666;">Join thousands of professionals who save time with smart scheduling.</p>
                    </div>

                    <div style="text-align: center;">
                        <?php if (!isLoggedIn()): ?>
                            <a href="calendly_single.php?page=register" class="btn" style="font-size: 1.1rem; padding: 15px 30px;">Create Free Account</a>
                            <p style="margin-top: 1rem; color: #666;">No credit card required • Free forever</p>
                        <?php else: ?>
                            <a href="calendly_single.php?page=dashboard" class="btn" style="font-size: 1.1rem; padding: 15px 30px;">Manage Your Bookings</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section style="background: #f8f9fa; padding: 4rem 0;">
                <div class="container">
                    <div style="text-align: center;">
                        <h2 style="font-size: 2.5rem; margin-bottom: 1rem; color: #333;">Features</h2>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-top: 3rem;">
                            <div style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <h4 style="margin-bottom: 1rem; color: #333;">📱 Mobile Responsive</h4>
                                <p style="color: #666;">Works perfectly on all devices - desktop, tablet, and mobile.</p>
                            </div>
                            <div style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <h4 style="margin-bottom: 1rem; color: #333;">📧 Email Notifications</h4>
                                <p style="color: #666;">Automatic email confirmations for you and your visitors.</p>
                            </div>
                            <div style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <h4 style="margin-bottom: 1rem; color: #333;">⏰ Time Zone Support</h4>
                                <p style="color: #666;">Automatic time zone detection and conversion.</p>
                            </div>
                            <div style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <h4 style="margin-bottom: 1rem; color: #333;">🔄 Easy Management</h4>
                                <p style="color: #666;">Cancel, reschedule, and manage all your appointments from one dashboard.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        <?php
        // Registration
        elseif ($page == 'register'):
            $error = '';
            $success = '';

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $name = sanitizeInput($_POST['name']);
                $email = sanitizeInput($_POST['email']);
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($name) || empty($email) || empty($password)) {
                    $error = 'All fields are required.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } elseif (strlen($password) < 6) {
                    $error = 'Password must be at least 6 characters long.';
                } elseif ($password !== $confirm_password) {
                    $error = 'Passwords do not match.';
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        
                        if ($stmt->fetch()) {
                            $error = 'Email already exists. Please use a different email.';
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                            $stmt->execute([$name, $email, $hashed_password]);
                            
                            $user_id = $pdo->lastInsertId();
                            for ($day = 1; $day <= 5; $day++) {
                                $stmt = $pdo->prepare("INSERT INTO availability (user_id, day_of_week, start_time, end_time) VALUES (?, ?, '09:00:00', '17:00:00')");
                                $stmt->execute([$user_id, $day]);
                            }
                            
                            header('Location: calendly_single.php?page=login&success=1');
                            exit;
                        }
                    } catch (Exception $e) {
                        $error = 'An error occurred. Please try again.';
                    }
                }
            }
        ?>
            <div class="container" style="padding: 2rem 0;">
                <div class="form-container">
                    <h2 style="text-align: center; margin-bottom: 2rem; color: #333;">Create Your Account</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>

                        <button type="submit" class="btn" style="width: 100%;">Create Account</button>
                    </form>

                    <p style="text-align: center; margin-top: 2rem; color: #666;">
                        Already have an account? <a href="calendly_single.php?page=login" style="color: #0066cc;">Login here</a>
                    </p>
                </div>
            </div>

        <?php
        // Login
        elseif ($page == 'login'):
            if (isLoggedIn()) {
                header('Location: calendly_single.php');
                exit;
            }

            $error = '';

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $email = sanitizeInput($_POST['email']);
                $password = $_POST['password'];
                
                if (empty($email) || empty($password)) {
                    $error = 'Please enter both email and password.';
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        $user = $stmt->fetch();
                        
                        if ($user && password_verify($password, $user['password'])) {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_name'] = $user['name'];
                            $_SESSION['user_email'] = $user['email'];
                            
                            header('Location: calendly_single.php');
                            exit;
                        } else {
                            $error = 'Invalid email or password.';
                        }
                    } catch (Exception $e) {
                        $error = 'An error occurred. Please try again.';
                    }
                }
            }
        ?>
            <div class="container" style="padding: 2rem 0;">
                <div class="form-container">
                    <h2 style="text-align: center; margin-bottom: 2rem; color: #333;">Welcome Back</h2>
                    
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success">Account created successfully! Please login to continue.</div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>

                        <button type="submit" class="btn" style="width: 100%;">Login</button>
                    </form>

                    <p style="text-align: center; margin-top: 2rem; color: #666;">
                        Don't have an account? <a href="calendly_single.php?page=register" style="color: #0066cc;">Sign up here</a>
                    </p>
                </div>
            </div>

        <?php
        // Dashboard
        elseif ($page == 'dashboard'):
            if (!isLoggedIn()) {
                header('Location: calendly_single.php?page=login');
                exit;
            }

            $user_id = $_SESSION['user_id'];
            $user = getUserById($user_id);

            if (isset($_POST['cancel_appointment'])) {
                $appointment_id = (int)$_POST['appointment_id'];
                $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ?");
                $stmt->execute([$appointment_id, $user_id]);
                header('Location: calendly_single.php?page=dashboard&cancelled=1');
                exit;
            }

            $stats = [];

            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['total'] = $stmt->fetch()['count'];

            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND start_time > NOW() AND status = 'scheduled'");
            $stmt->execute([$user_id]);
            $stats['upcoming'] = $stmt->fetch()['count'];

            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND status = 'scheduled'");
            $stmt->execute([$user_id]);
            $stats['this_week'] = $stmt->fetch()['count'];

            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND status = 'completed'");
            $stmt->execute([$user_id]);
            $stats['completed'] = $stmt->fetch()['count'];

            $stmt = $pdo->prepare("
                SELECT * FROM appointments 
                WHERE user_id = ? AND start_time > NOW() AND status = 'scheduled'
                ORDER BY start_time ASC 
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
            $upcoming_appointments = $stmt->fetchAll();

            $stmt = $pdo->prepare("
                SELECT * FROM appointments 
                WHERE user_id = ? AND start_time <= NOW()
                ORDER BY start_time DESC 
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
            $recent_appointments = $stmt->fetchAll();

            $booking_link = generateBookingLink($user_id);
        ?>
            <div class="dashboard">
                <div class="container">
                    <?php if (isset($_GET['cancelled'])): ?>
                        <div class="alert alert-success">Appointment cancelled successfully.</div>
                    <?php endif; ?>

                    <div class="dashboard-header">
                        <h1>Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                        <p>Here's an overview of your scheduling activity.</p>
                        
                        <div style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 6px;">
                            <h3 style="margin-bottom: 1rem;">Your Booking Link</h3>
                            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                                <input type="text" value="<?php echo $booking_link; ?>" readonly style="flex: 1; min-width: 300px; padding: 10px; border: 2px solid #e9ecef; border-radius: 4px;">
                                <button onclick="copyToClipboard('<?php echo $booking_link; ?>')" class="btn">Copy Link</button>
                            </div>
                            <p style="margin-top: 0.5rem; color: #666; font-size: 0.9rem;">Share this link with others so they can book appointments with you.</p>
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['total']; ?></div>
                            <div class="stat-label">Total Appointments</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['upcoming']; ?></div>
                            <div class="stat-label">Upcoming</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['this_week']; ?></div>
                            <div class="stat-label">This Week</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['completed']; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-top: 2rem;">
                        <div class="appointments-list">
                            <div style="padding: 1.5rem; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">
                                <h3 style="margin: 0; color: #333;">Upcoming Appointments</h3>
                            </div>
                            <?php if (empty($upcoming_appointments)): ?>
                                <div style="padding: 2rem; text-align: center; color: #666;">
                                    <p>No upcoming appointments scheduled.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <div class="appointment-item">
                                        <div class="appointment-info">
                                            <h4><?php echo htmlspecialchars($appointment['visitor_name']); ?></h4>
                                            <p><?php echo formatDateTime($appointment['start_time']); ?></p>
                                            <p style="color: #0066cc;"><?php echo htmlspecialchars($appointment['visitor_email']); ?></p>
                                            <?php if ($appointment['visitor_phone']): ?>
                                                <p style="color: #666;"><?php echo htmlspecialchars($appointment['visitor_phone']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="appointment-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <button type="submit" name="cancel_appointment" class="btn btn-danger" style="padding: 8px 16px; font-size: 12px;" onclick="return confirm('Are you sure you want to cancel this appointment?')">Cancel</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="appointments-list">
                            <div style="padding: 1.5rem; background: #f8f9fa; border-bottom: 1px solid #e9ecef;">
                                <h3 style="margin: 0; color: #333;">Recent Appointments</h3>
                            </div>
                            <?php if (empty($recent_appointments)): ?>
                                <div style="padding: 2rem; text-align: center; color: #666;">
                                    <p>No recent appointments.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <div class="appointment-item">
                                        <div class="appointment-info">
                                            <h4><?php echo htmlspecialchars($appointment['visitor_name']); ?></h4>
                                            <p><?php echo formatDateTime($appointment['start_time']); ?></p>
                                            <p style="color: #0066cc;"><?php echo htmlspecialchars($appointment['visitor_email']); ?></p>
                                            <span style="background: <?php echo $appointment['status'] == 'completed' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $appointment['status'] == 'completed' ? '#155724' : '#721c24'; ?>; padding: 4px 8px; border-radius: 4px; font-size: 12px; text-transform: uppercase;">
                                                <?php echo $appointment['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php
        // Availability Settings
        elseif ($page == 'availability'):
            if (!isLoggedIn()) {
                header('Location: calendly_single.php?page=login');
                exit;
            }

            $user_id = $_SESSION['user_id'];
            $success = '';
            $error = '';

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                try {
                    $stmt = $pdo->prepare("DELETE FROM availability WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                    
                    foreach ($days as $index => $day) {
                        if (isset($_POST[$day . '_enabled']) && $_POST[$day . '_enabled']) {
                            $start_time = $_POST[$day . '_start'] . ':00';
                            $end_time = $_POST[$day . '_end'] . ':00';
                            
                            $stmt = $pdo->prepare("INSERT INTO availability (user_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$user_id, $index, $start_time, $end_time]);
                        }
                    }
                    
                    $success = 'Availability updated successfully!';
                } catch (Exception $e) {
                    $error = 'An error occurred while updating availability.';
                }
            }

            $stmt = $pdo->prepare("SELECT * FROM availability WHERE user_id = ? ORDER BY day_of_week");
            $stmt->execute([$user_id]);
            $availability = $stmt->fetchAll();

            $current_availability = [];
            foreach ($availability as $slot) {
                $current_availability[$slot['day_of_week']] = [
                    'start_time' => date('H:i', strtotime($slot['start_time'])),
                    'end_time' => date('H:i', strtotime($slot['end_time']))
                ];
            }

            $days = [
                'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'
            ];
        ?>
            <div class="container" style="padding: 2rem 0;">
                <div class="availability-settings">
                    <h1 style="margin-bottom: 2rem; color: #333;">Set Your Availability</h1>
                    <p style="margin-bottom: 2rem; color: #666;">Choose the days and times when you're available for appointments. Visitors will only be able to book during these times.</p>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?php foreach ($days as $index => $day): ?>
                            <div class="day-schedule">
                                <div class="day-name">
                                    <label>
                                        <input type="checkbox" name="<?php echo strtolower($day); ?>_enabled" 
                                               <?php echo isset($current_availability[$index]) ? 'checked' : ''; ?>>
                                        <?php echo $day; ?>
                                    </label>
                                </div>
                                <div class="time-inputs">
                                    <div>
                                        <label for="<?php echo strtolower($day); ?>_start">From:</label>
                                        <input type="time" id="<?php echo strtolower($day); ?>_start" 
                                               name="<?php echo strtolower($day); ?>_start" 
                                               value="<?php echo isset($current_availability[$index]) ? $current_availability[$index]['start_time'] : '09:00'; ?>">
                                    </div>
                                    <div>
                                        <label for="<?php echo strtolower($day); ?>_end">To:</label>
                                        <input type="time" id="<?php echo strtolower($day); ?>_end" 
                                               name="<?php echo strtolower($day); ?>_end" 
                                               value="<?php echo isset($current_availability[$index]) ? $current_availability[$index]['end_time'] : '17:00'; ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div style="text-align: center; margin-top: 2rem;">
                            <button type="submit" class="btn">Save Availability</button>
                            <a href="calendly_single.php?page=dashboard" class="btn btn-secondary" style="margin-left: 1rem;">Back to Dashboard</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php
        // Booking System
        elseif ($page == 'book'):
            $user_id = isset($_GET['user']) ? (int)$_GET['user'] : 0;

            if (!$user_id) {
                echo "<div class='container' style='padding: 2rem 0; text-align: center;'>";
                echo "<h1 style='color: #dc3545;'>❌ Invalid Booking Link</h1>";
                echo "<p>This booking link is not valid. Please contact the person who shared this link with you.</p>";
                echo "<a href='calendly_single.php' class='btn'>Go to Homepage</a>";
                echo "</div>";
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user) {
                echo "<div class='container' style='padding: 2rem 0; text-align: center;'>";
                echo "<h1 style='color: #dc3545;'>❌ User Not Found</h1>";
                echo "<p>The person you're trying to book with doesn't exist in our system.</p>";
                echo "<a href='calendly_single.php' class='btn'>Go to Homepage</a>";
                echo "</div>";
                exit;
            }

            $selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
            $selected_time = isset($_GET['time']) ? $_GET['time'] : '';

            $available_slots = getAvailableSlots($user_id, $selected_date);

            $error = '';
            $success = '';

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // Debug: Log the POST data
                error_log("Visitor Booking POST Data: " . print_r($_POST, true), 3, "debug_log.txt");
                
                $visitor_name = sanitizeInput($_POST['visitor_name']);
                $visitor_email = sanitizeInput($_POST['visitor_email']);
                $visitor_phone = sanitizeInput($_POST['visitor_phone']);
                $selected_date = $_POST['selected_date'];
                $selected_time = $_POST['selected_time'];
                $notes = sanitizeInput($_POST['notes']);
                
                if (empty($visitor_name) || empty($visitor_email) || empty($selected_date) || empty($selected_time)) {
                    $error = 'Please fill in all required fields.';
                } elseif (!filter_var($visitor_email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    try {
                        $time_parts = explode(' - ', $selected_time);
                        $start_time = $selected_date . ' ' . $time_parts[0] . ':00';
                        $end_time = $selected_date . ' ' . $time_parts[1] . ':00';
                        
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as count 
                            FROM appointments 
                            WHERE user_id = ? AND start_time <= ? AND end_time > ? AND status != 'cancelled'
                        ");
                        $stmt->execute([$user_id, $start_time, $start_time]);
                        $conflict = $stmt->fetch()['count'];
                        
                        if ($conflict > 0) {
                            $error = 'This time slot is no longer available. Please select another time.';
                        } else {
                            // Validate date and time
                            if (strtotime($start_time) <= time()) {
                                throw new Exception("Cannot book appointments in the past.");
                            }
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO appointments (user_id, visitor_name, visitor_email, visitor_phone, start_time, end_time, notes) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            if (!$stmt) {
                                throw new Exception("Database prepare statement failed.");
                            }
                            
                            $result = $stmt->execute([$user_id, $visitor_name, $visitor_email, $visitor_phone, $start_time, $end_time, $notes]);
                            
                            if (!$result) {
                                throw new Exception("Failed to insert appointment into database.");
                            }
                            
                            $appointment_id = $pdo->lastInsertId();
                            
                            // Debug: Log successful appointment creation
                            error_log("Appointment created successfully. ID: $appointment_id, User: $user_id, Visitor: $visitor_name", 3, "debug_log.txt");
                            
                            $subject = "Appointment Confirmed with " . $user['name'];
                            $message = "
                                <h2>Appointment Confirmed</h2>
                                <p>Hello " . htmlspecialchars($visitor_name) . ",</p>
                                <p>Your appointment with " . htmlspecialchars($user['name']) . " has been confirmed.</p>
                                <p><strong>Date:</strong> " . formatDate($start_time) . "</p>
                                <p><strong>Time:</strong> " . formatTime($start_time) . " - " . formatTime($end_time) . "</p>
                                <p><strong>Host:</strong> " . htmlspecialchars($user['name']) . "</p>
                                <p>If you need to reschedule or cancel, please contact " . htmlspecialchars($user['name']) . " directly.</p>
                            ";
                            
                            sendEmail($visitor_email, $subject, $message);
                            
                            $host_subject = "New Appointment Booked";
                            $host_message = "
                                <h2>New Appointment Booked</h2>
                                <p>You have a new appointment scheduled:</p>
                                <p><strong>Visitor:</strong> " . htmlspecialchars($visitor_name) . "</p>
                                <p><strong>Email:</strong> " . htmlspecialchars($visitor_email) . "</p>
                                <p><strong>Phone:</strong> " . htmlspecialchars($visitor_phone) . "</p>
                                <p><strong>Date:</strong> " . formatDate($start_time) . "</p>
                                <p><strong>Time:</strong> " . formatTime($start_time) . " - " . formatTime($end_time) . "</p>
                                <p><strong>Notes:</strong> " . htmlspecialchars($notes) . "</p>
                            ";
                            
                            sendEmail($user['email'], $host_subject, $host_message);
                            
                            $success = 'Appointment booked successfully! Confirmation emails have been sent.';
                        }
                    } catch (Exception $e) {
                        // Log the detailed error for debugging
                        error_log("Visitor Booking Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine(), 3, "error_log.txt");
                        $error = 'An error occurred while booking the appointment. Error: ' . $e->getMessage() . '. Please try again.';
                    }
                }
            }

            $calendar_dates = [];
            for ($i = 0; $i < 30; $i++) {
                $date = date('Y-m-d', strtotime("+$i days"));
                $day_name = date('l', strtotime($date));
                $day_of_week = date('w', strtotime($date));
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM availability WHERE user_id = ? AND day_of_week = ? AND is_active = 1");
                $stmt->execute([$user_id, $day_of_week]);
                $has_availability = $stmt->fetch()['count'] > 0;
                
                $slots = getAvailableSlots($user_id, $date);
                $has_slots = !empty($slots);
                
                $calendar_dates[] = [
                    'date' => $date,
                    'day_name' => $day_name,
                    'day_number' => date('j', strtotime($date)),
                    'has_slots' => $has_slots,
                    'is_today' => $date == date('Y-m-d'),
                    'is_selected' => $date == $selected_date
                ];
            }
        ?>
            <div class="container" style="padding: 2rem 0;">
                <div style="text-align: center; margin-bottom: 3rem;">
                    <h1 style="color: #333; margin-bottom: 1rem;">📅 Book a meeting with <?php echo htmlspecialchars($user['name']); ?></h1>
                    <p style="color: #666; margin-bottom: 1rem;">Select a date and time that works for you.</p>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px; display: inline-block; margin-bottom: 1rem;">
                        <p style="margin: 0; color: #666; font-size: 0.9rem;">
                            ✅ You're booking with: <strong><?php echo htmlspecialchars($user['name']); ?></strong> 
                            (<?php echo htmlspecialchars($user['email']); ?>)
                        </p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="max-width: 600px; margin: 0 auto 2rem;">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="max-width: 600px; margin: 0 auto 2rem;">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$success): ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; max-width: 1000px; margin: 0 auto;">
                        <div class="calendar-container">
                            <h3 style="margin-bottom: 1rem;">Select a Date</h3>
                            <div class="calendar-grid" style="grid-template-columns: repeat(7, 1fr); gap: 1px; background: #e9ecef; border-radius: 6px; overflow: hidden;">
                                <?php foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $day): ?>
                                    <div style="background: #f8f9fa; padding: 0.5rem; text-align: center; font-weight: bold; color: #666;"><?php echo $day; ?></div>
                                <?php endforeach; ?>
                                
                                <?php foreach ($calendar_dates as $date_info): ?>
                                    <a href="calendly_single.php?page=book&user=<?php echo $user_id; ?>&date=<?php echo $date_info['date']; ?>" 
                                       class="calendar-day <?php echo $date_info['has_slots'] ? 'has-slots' : ''; ?> <?php echo $date_info['is_selected'] ? 'selected' : ''; ?>"
                                       style="text-decoration: none; color: inherit;">
                                        <div style="font-weight: <?php echo $date_info['is_today'] ? 'bold' : 'normal'; ?>;">
                                            <?php echo $date_info['day_number']; ?>
                                        </div>
                                        <?php if ($date_info['has_slots']): ?>
                                            <div style="font-size: 0.7rem; color: #28a745;">Available</div>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="calendar-container">
                            <h3 style="margin-bottom: 1rem;">Available Times for <?php echo formatDate($selected_date); ?></h3>
                            
                            <?php if (empty($available_slots)): ?>
                                <div style="text-align: center; padding: 2rem; color: #666; background: #f8f9fa; border-radius: 6px;">
                                    <h4 style="color: #dc3545; margin-bottom: 1rem;">⏰ No Available Slots</h4>
                                    <p>No available time slots for this date.</p>
                                    <p>Please select a different date from the calendar.</p>
                                    <p style="font-size: 0.9rem; color: #999; margin-top: 1rem;">
                                        💡 Tip: Try selecting dates that show "Available" in green
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="slots-grid">
                                    <?php foreach ($available_slots as $slot): ?>
                                        <a href="calendly_single.php?page=book&user=<?php echo $user_id; ?>&date=<?php echo $selected_date; ?>&time=<?php echo urlencode($slot['display']); ?>" 
                                           class="slot <?php echo $slot['display'] == $selected_time ? 'selected' : ''; ?>"
                                           style="text-decoration: none; color: inherit;">
                                            <?php echo $slot['display']; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($selected_date && $selected_time && !empty($available_slots)): ?>
                        <div class="calendar-container" style="max-width: 600px; margin: 2rem auto;">
                            <h3 style="margin-bottom: 1rem;">Book Your Appointment</h3>
                            <p style="margin-bottom: 2rem; color: #666;">
                                <strong>Date:</strong> <?php echo formatDate($selected_date); ?><br>
                                <strong>Time:</strong> <?php echo htmlspecialchars($selected_time); ?>
                            </p>

                            <form method="POST" action="?page=book&user=<?php echo $user_id; ?><?php echo $selected_date ? '&date=' . $selected_date : ''; ?><?php echo $selected_time ? '&time=' . urlencode($selected_time) : ''; ?>">
                                <input type="hidden" name="selected_date" value="<?php echo $selected_date; ?>">
                                <input type="hidden" name="selected_time" value="<?php echo htmlspecialchars($selected_time); ?>">

                                <div class="form-group">
                                    <label for="visitor_name">Your Name *</label>
                                    <input type="text" id="visitor_name" name="visitor_name" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="visitor_email">Email Address *</label>
                                    <input type="email" id="visitor_email" name="visitor_email" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="visitor_phone">Phone Number</label>
                                    <input type="tel" id="visitor_phone" name="visitor_phone" class="form-control">
                                </div>

                                <div class="form-group">
                                    <label for="notes">Additional Notes</label>
                                    <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Any additional information you'd like to share..."></textarea>
                                </div>

                                <button type="submit" class="btn" style="width: 100%;" onclick="this.innerHTML='Booking...'; this.disabled=true; setTimeout(function(){this.innerHTML='Book Appointment'; this.disabled=false;}.bind(this), 5000);">
                                    📅 Book Appointment
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="container" style="padding: 2rem 0; text-align: center;">
                <h1>Page Not Found</h1>
                <p>The page you're looking for doesn't exist.</p>
                <a href="calendly_single.php" class="btn">Go Home</a>
            </div>
        <?php endif; ?>
    </main>

    <footer style="background: #333; color: white; text-align: center; padding: 2rem 0;">
        <div class="container">
            <p>&copy; 2024 Calendly Clone. Built with PHP and MySQL.</p>
        </div>
    </footer>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Booking link copied to clipboard!');
            }, function(err) {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    alert('Booking link copied to clipboard!');
                } catch (err) {
                    alert('Failed to copy link. Please copy manually.');
                }
                document.body.removeChild(textArea);
            });
        }

        // Enable/disable time inputs based on checkbox
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(function(checkbox) {
                const dayName = checkbox.name.replace('_enabled', '');
                const startInput = document.querySelector('input[name="' + dayName + '_start"]');
                const endInput = document.querySelector('input[name="' + dayName + '_end"]');
                
                if (startInput && endInput) {
                    function toggleInputs() {
                        startInput.disabled = !checkbox.checked;
                        endInput.disabled = !checkbox.checked;
                    }
                    
                    toggleInputs();
                    checkbox.addEventListener('change', toggleInputs);
                }
            });
        });
    </script>
</body>
</html>


<?php
// Debug page for Calendly system
require_once 'calendly_single.php';

echo "<h1>Calendly System Debug</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .debug-section{background:#f8f9fa;padding:15px;margin:10px 0;border-radius:5px;}</style>";

// Test 1: Database Connection
echo "<div class='debug-section'>";
echo "<h2>1. Database Connection Test</h2>";
try {
    $db = new Database();
    $pdo = $db->getConnection();
    echo "<p style='color:green;'>✅ Database connection successful</p>";
    
    // Test tables
    $tables = ['users', 'availability', 'appointments'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "<p>Table '$table': $count records</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Database error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 2: Session
echo "<div class='debug-section'>";
echo "<h2>2. Session Test</h2>";
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session data: " . print_r($_SESSION, true) . "</p>";
echo "<p>Is logged in: " . (isLoggedIn() ? 'Yes' : 'No') . "</p>";
echo "</div>";

// Test 3: Email Function
echo "<div class='debug-section'>";
echo "<h2>3. Email Function Test</h2>";
if (function_exists('mail')) {
    echo "<p style='color:green;'>✅ PHP mail() function is available</p>";
} else {
    echo "<p style='color:red;'>❌ PHP mail() function is not available</p>";
}

// Test sendEmail function
try {
    $test_result = sendEmail('test@example.com', 'Test Email', 'This is a test email');
    echo "<p>Email test result: " . ($test_result ? 'Success' : 'Failed') . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Email test error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 4: File Permissions
echo "<div class='debug-section'>";
echo "<h2>4. File Permissions Test</h2>";
$files_to_check = ['email_log.txt', 'error_log.txt'];
foreach ($files_to_check as $file) {
    if (is_writable($file) || is_writable('.')) {
        echo "<p style='color:green;'>✅ Can write to $file</p>";
    } else {
        echo "<p style='color:orange;'>⚠️ Cannot write to $file (may create new file)</p>";
    }
}
echo "</div>";

// Test 5: User Data
echo "<div class='debug-section'>";
echo "<h2>5. User Data Test</h2>";
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    echo "<p>Logged in user ID: $user_id</p>";
    
    $user = getUserById($user_id);
    if ($user) {
        echo "<p>User name: " . htmlspecialchars($user['name']) . "</p>";
        echo "<p>User email: " . htmlspecialchars($user['email']) . "</p>";
        
        // Test availability
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM availability WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $availability_count = $stmt->fetchColumn();
        echo "<p>Availability records: $availability_count</p>";
        
        // Test appointments
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $appointment_count = $stmt->fetchColumn();
        echo "<p>Appointment records: $appointment_count</p>";
        
        // Test booking link
        $booking_link = generateBookingLink($user_id);
        echo "<p>Booking link: <a href='$booking_link' target='_blank'>$booking_link</a></p>";
    } else {
        echo "<p style='color:red;'>❌ Could not fetch user data</p>";
    }
} else {
    echo "<p>Not logged in. <a href='calendly_single.php?page=login'>Login here</a></p>";
}
echo "</div>";

// Test 6: Recent Errors
echo "<div class='debug-section'>";
echo "<h2>6. Recent Errors</h2>";
if (file_exists('error_log.txt')) {
    $error_content = file_get_contents('error_log.txt');
    if ($error_content) {
        echo "<pre style='background:white;padding:10px;border:1px solid #ccc;max-height:200px;overflow:auto;'>";
        echo htmlspecialchars(substr($error_content, -1000)); // Last 1000 characters
        echo "</pre>";
    } else {
        echo "<p>No errors logged yet.</p>";
    }
} else {
    echo "<p>No error log file found.</p>";
}
echo "</div>";

// Test 7: Email Log
echo "<div class='debug-section'>";
echo "<h2>7. Email Log</h2>";
if (file_exists('email_log.txt')) {
    $email_content = file_get_contents('email_log.txt');
    if ($email_content) {
        echo "<pre style='background:white;padding:10px;border:1px solid #ccc;max-height:200px;overflow:auto;'>";
        echo htmlspecialchars(substr($email_content, -1000)); // Last 1000 characters
        echo "</pre>";
    } else {
        echo "<p>No emails logged yet.</p>";
    }
} else {
    echo "<p>No email log file found.</p>";
}
echo "</div>";

echo "<div class='debug-section'>";
echo "<h2>Quick Actions</h2>";
echo "<p><a href='calendly_single.php'>Go to Homepage</a></p>";
echo "<p><a href='calendly_single.php?page=register'>Register New User</a></p>";
echo "<p><a href='setup.php'>Setup Database</a></p>";
echo "</div>";
?>

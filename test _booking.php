<?php
// Simple test page to check booking functionality
require_once 'calendly_single.php';

echo "<h1>Booking Test Page</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .test-section{background:#f8f9fa;padding:15px;margin:10px 0;border-radius:5px;}</style>";

// Test 1: Check if we can get users
echo "<div class='test-section'>";
echo "<h2>1. Available Users</h2>";
try {
    $stmt = $pdo->query("SELECT id, name, email FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<p style='color:red;'>❌ No users found. Please register a user first.</p>";
    } else {
        echo "<p style='color:green;'>✅ Found " . count($users) . " users:</p>";
        foreach ($users as $user) {
            echo "<p>ID: {$user['id']} - Name: {$user['name']} - Email: {$user['email']}</p>";
            echo "<p>Booking Link: <a href='calendly_single.php?page=book&user={$user['id']}' target='_blank'>Test Booking</a></p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 2: Check appointments table
echo "<div class='test-section'>";
echo "<h2>2. Recent Appointments</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM appointments ORDER BY created_at DESC LIMIT 10");
    $appointments = $stmt->fetchAll();
    
    if (empty($appointments)) {
        echo "<p style='color:orange;'>⚠️ No appointments found yet.</p>";
    } else {
        echo "<p style='color:green;'>✅ Found " . count($appointments) . " recent appointments:</p>";
        foreach ($appointments as $apt) {
            echo "<p>";
            echo "ID: {$apt['id']} | ";
            echo "User: {$apt['user_id']} | ";
            echo "Visitor: {$apt['visitor_name']} | ";
            echo "Email: {$apt['visitor_email']} | ";
            echo "Date: {$apt['start_time']} | ";
            echo "Status: {$apt['status']}";
            echo "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 3: Check availability
echo "<div class='test-section'>";
echo "<h2>3. User Availability</h2>";
try {
    $stmt = $pdo->query("SELECT u.id, u.name, COUNT(a.id) as availability_count FROM users u LEFT JOIN availability a ON u.id = a.user_id GROUP BY u.id");
    $availability = $stmt->fetchAll();
    
    foreach ($availability as $avail) {
        echo "<p>User: {$avail['name']} (ID: {$avail['id']}) - Availability records: {$avail['availability_count']}</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 4: Debug logs
echo "<div class='test-section'>";
echo "<h2>4. Recent Debug Logs</h2>";
if (file_exists('debug_log.txt')) {
    $content = file_get_contents('debug_log.txt');
    if ($content) {
        echo "<pre style='background:white;padding:10px;border:1px solid #ccc;max-height:300px;overflow:auto;'>";
        echo htmlspecialchars(substr($content, -2000)); // Last 2000 characters
        echo "</pre>";
    } else {
        echo "<p>No debug logs found.</p>";
    }
} else {
    echo "<p>No debug log file found.</p>";
}
echo "</div>";

// Test 5: Manual booking test
echo "<div class='test-section'>";
echo "<h2>5. Manual Booking Test</h2>";
if (!empty($users)) {
    $first_user = $users[0];
    echo "<p>Testing with user: {$first_user['name']} (ID: {$first_user['id']})</p>";
    
    // Try to create a test appointment
    try {
        $test_date = date('Y-m-d', strtotime('+1 day'));
        $test_start = $test_date . ' 10:00:00';
        $test_end = $test_date . ' 10:30:00';
        
        $stmt = $pdo->prepare("INSERT INTO appointments (user_id, visitor_name, visitor_email, start_time, end_time, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$first_user['id'], 'Test Visitor', 'test@example.com', $test_start, $test_end, 'Test booking']);
        
        if ($result) {
            echo "<p style='color:green;'>✅ Test appointment created successfully!</p>";
            
            // Get the appointment ID
            $appointment_id = $pdo->lastInsertId();
            echo "<p>Appointment ID: $appointment_id</p>";
            
            // Verify it was saved
            $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
            $stmt->execute([$appointment_id]);
            $saved_apt = $stmt->fetch();
            
            if ($saved_apt) {
                echo "<p style='color:green;'>✅ Appointment verified in database</p>";
            } else {
                echo "<p style='color:red;'>❌ Appointment not found in database</p>";
            }
        } else {
            echo "<p style='color:red;'>❌ Failed to create test appointment</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Error creating test appointment: " . $e->getMessage() . "</p>";
    }
}
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>Quick Actions</h2>";
echo "<p><a href='calendly_single.php'>Go to Homepage</a></p>";
echo "<p><a href='debug.php'>Run Full Debug</a></p>";
echo "<p><a href='setup.php'>Setup Database</a></p>";
echo "</div>";
?>

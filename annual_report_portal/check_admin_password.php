<?php
include('includes/db_connect.php');

// Step 1: Fetch admin user
$sql = "SELECT * FROM users WHERE role='admin' LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo "<p style='color:red;'>❌ No admin found in database!</p>";
    exit;
}

$admin = $result->fetch_assoc();

echo "<h3>🔍 Admin Password Diagnostic</h3>";
echo "<p><strong>Email:</strong> " . htmlspecialchars($admin['email']) . "</p>";
echo "<p><strong>Hashed Password:</strong> " . htmlspecialchars($admin['password']) . "</p>";
echo "<hr>";

// Step 2: Check if it matches "admin123"
$testPassword = 'admin123';
if (password_verify($testPassword, $admin['password'])) {
    echo "<p style='color:green;font-weight:bold;'>✅ Success: 'admin123' matches the stored hash.</p>";
} else {
    echo "<p style='color:red;font-weight:bold;'>❌ Fail: 'admin123' does NOT match the stored hash.</p>";
}

// Optional: Show database details
echo "<hr><pre>";
print_r($admin);
echo "</pre>";
?>

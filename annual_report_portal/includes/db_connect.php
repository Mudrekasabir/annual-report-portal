<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "annual_report_portal";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
// Create default admin if not exists
$checkAdmin = $conn->query("SELECT * FROM users WHERE role='admin' LIMIT 1");
if ($checkAdmin->num_rows == 0) {
  $hashed = password_hash('admin123', PASSWORD_DEFAULT);
  $conn->query("INSERT INTO users (name, email, password, role, approval_status)
                VALUES ('System Administrator', 'admin@example.com', '$hashed', 'admin', 'approved')");
}

?>
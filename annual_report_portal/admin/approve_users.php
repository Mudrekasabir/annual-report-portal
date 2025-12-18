<?php
include('../includes/db_connect.php');
session_start();

// Only admin can approve/reject
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
  header("Location: ../login.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $user_id = intval($_POST['user_id']);
  $action = $_POST['action'];

  if ($action == 'approve') {
    $status = 'approved';
  } elseif ($action == 'reject') {
    $status = 'rejected';
  } else {
    $status = 'pending';
  }

  $stmt = $conn->prepare("UPDATE users SET approval_status = ? WHERE id = ?");
  $stmt->bind_param("si", $status, $user_id);
  $stmt->execute();

  $_SESSION['message'] = "Teacher has been {$status} successfully.";
}

header("Location: dashboard.php");
exit();
?>

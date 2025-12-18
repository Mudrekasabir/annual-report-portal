<?php
include('db_connect.php');
session_start();

$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$result = $conn->query("SELECT id, message, is_read, created_at FROM notifications WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 10");

$notifications = [];
while ($row = $result->fetch_assoc()) {
  $notifications[] = $row;
}

echo json_encode([
  'success' => true,
  'count' => $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'],
  'notifications' => $notifications
]);

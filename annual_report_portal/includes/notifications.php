<?php
header('Content-Type: application/json');
include('db_connect.php');
session_start();
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) { echo json_encode(['count'=>0,'notifications'=>[]]); exit; }

$res = $conn->query("SELECT id, message, created_at FROM notifications WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 10");
$notes = [];
while($n=$res->fetch_assoc()) { $notes[] = $n; }
$count = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
echo json_encode(['count'=>$count,'notifications'=>$notes]);

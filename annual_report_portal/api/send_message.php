<?php
include('../api/db_connect.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$sender = intval($data['sender_id'] ?? 0);
$receiver = intval($data['receiver_id'] ?? 0);
$msg = $data['message'] ?? '';

if (!$sender || !$receiver || !$msg) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Missing']); exit; }
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $sender, $receiver, $msg);
if ($stmt->execute()) echo json_encode(['status'=>'success']);
else echo json_encode(['status'=>'error']);

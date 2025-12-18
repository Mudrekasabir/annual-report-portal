<?php
include('db_connect.php');
header("Content-Type: application/json");

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
  echo json_encode(["status" => "error", "message" => "Invalid report ID"]);
  exit();
}

$result = $conn->query("SELECT * FROM annual_reports WHERE id = $id");
if ($result->num_rows === 0) {
  echo json_encode(["status" => "error", "message" => "Report not found"]);
  exit();
}

echo json_encode(["status" => "success", "report" => $result->fetch_assoc()]);
?>
<?php
include('db_connect.php');
header("Content-Type: application/json");

$teacher_id = intval($_GET['teacher_id'] ?? 0);
if ($teacher_id <= 0) {
  echo json_encode(["status" => "error", "message" => "Invalid teacher ID"]);
  exit();
}

$result = $conn->query("SELECT * FROM annual_reports WHERE uploaded_by = $teacher_id ORDER BY id DESC");

$reports = [];
while ($row = $result->fetch_assoc()) {
  $reports[] = $row;
}

echo json_encode(["status" => "success", "reports" => $reports]);
?>

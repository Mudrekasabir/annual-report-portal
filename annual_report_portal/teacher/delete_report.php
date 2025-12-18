<?php
session_start();
include('../includes/db_connect.php');
include('../includes/functions.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$report_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

// Fetch file path first
$stmt = $conn->prepare("SELECT file_path FROM annual_reports WHERE id = ? AND uploaded_by = ?");
if (!$stmt) {
    die("Prepare Error: " . $conn->error);
}

$stmt->bind_param("ii", $report_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "❌ Report not found or permission denied!";
    header("Location: dashboard.php");
    exit();
}

$report = $result->fetch_assoc();
$filePath = "../" . $report['file_path'];

// Delete related approvals
$delApproval = $conn->prepare("DELETE FROM approvals WHERE report_id = ?");
if ($delApproval) {
    $delApproval->bind_param("i", $report_id);
    $delApproval->execute();
    $delApproval->close();
}

// Delete report
$delReport = $conn->prepare("DELETE FROM annual_reports WHERE id = ? AND uploaded_by = ?");
if (!$delReport) {
    die("Prepare Error: " . $conn->error);
}

$delReport->bind_param("ii", $report_id, $user_id);

if ($delReport->execute()) {
    // Delete file if exists
    if (!empty($report['file_path']) && file_exists($filePath)) {
        @unlink($filePath);
    }
    
    log_action($conn, $user_id, "Deleted report ID $report_id", "teacher");
    $_SESSION['message'] = "🗑️ Report deleted successfully!";
} else {
    $_SESSION['message'] = "❌ Failed to delete report!";
}

$delReport->close();

header("Location: dashboard.php");
exit();
?>
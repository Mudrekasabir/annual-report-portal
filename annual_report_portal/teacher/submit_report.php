<?php
session_start();

// ============ SECURITY: Check Authentication ============
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

include('../includes/db_connect.php');
include('../includes/functions.php');

$user_id = $_SESSION['user_id'];

// ============ HANDLE SUBMISSION ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $report_id = intval($_POST['id'] ?? 0);
    
    // ============ VALIDATE REPORT ID ============
    if ($report_id <= 0) {
        $_SESSION['flash_message'] = "❌ Invalid report ID";
        header("Location: dashboard.php");
        exit();
    }
    
    // ============ VERIFY REPORT EXISTS & BELONGS TO USER ============
    $stmt_verify = $conn->prepare("
        SELECT id, status, title, file_path FROM annual_reports 
        WHERE id = ? AND uploaded_by = ?
    ");
    
    if (!$stmt_verify) {
        die("Prepare Error: " . $conn->error);
    }
    
    $stmt_verify->bind_param("ii", $report_id, $user_id);
    $stmt_verify->execute();
    $result = $stmt_verify->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['flash_message'] = "❌ Report not found or you don't have permission to submit it";
        $stmt_verify->close();
        header("Location: dashboard.php");
        exit();
    }
    
    $report = $result->fetch_assoc();
    $stmt_verify->close();
    
    // ============ CHECK IF REPORT IS IN DRAFT OR REJECTED STATUS ============
    $allowed_statuses = ['draft', 'rejected'];
    if (!in_array($report['status'], $allowed_statuses)) {
        $_SESSION['flash_message'] = "❌ Only draft or rejected reports can be submitted";
        header("Location: dashboard.php");
        exit();
    }
    
    // ============ OPTIONAL: Validate report has content ============
    // Check if report has either file_path or content
    $stmt_content = $conn->prepare("
        SELECT content FROM annual_reports WHERE id = ?
    ");
    
    if ($stmt_content) {
        $stmt_content->bind_param("i", $report_id);
        $stmt_content->execute();
        $content_result = $stmt_content->get_result();
        $content_data = $content_result->fetch_assoc();
        $stmt_content->close();
        
        $has_content = !empty($content_data['content']);
        $has_file = !empty($report['file_path']);
        
        if (!$has_content && !$has_file) {
            $_SESSION['flash_message'] = "❌ Cannot submit empty report. Please add content or attach a file.";
            header("Location: edit_report.php?id=" . $report_id);
            exit();
        }
    }
    
    // ============ UPDATE STATUS TO PENDING ============
    $stmt_update = $conn->prepare("
        UPDATE annual_reports 
        SET status = 'pending', updated_at = NOW()
        WHERE id = ?
    ");
    
    if (!$stmt_update) {
        die("Prepare Error: " . $conn->error);
    }
    
    $stmt_update->bind_param("i", $report_id);
    
    if (!$stmt_update->execute()) {
        $_SESSION['flash_message'] = "❌ Failed to submit report: " . $stmt_update->error;
        $stmt_update->close();
        header("Location: dashboard.php");
        exit();
    }
    
    $stmt_update->close();
    
    $report_title = $report['title'] ?? 'Your Report';
    
    // ============ LOG ACTION ============
    if (function_exists('log_action')) {
        log_action($conn, $user_id, "Submitted report for approval: $report_title", 'teacher');
    } else {
        // Fallback logging if function doesn't exist
        $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity, created_at) VALUES (?, ?, NOW())");
        if ($log_stmt) {
            $activity = "Submitted report for approval: $report_title";
            $log_stmt->bind_param("is", $user_id, $activity);
            $log_stmt->execute();
            $log_stmt->close();
        }
    }
    
    // ============ NOTIFY ADMIN AND COORDINATORS ============
    if (function_exists('notify_user')) {
        $teacher_name = htmlspecialchars($_SESSION['name'] ?? 'Teacher');
        
        // Notify admin (user_id = 1)
        notify_user($conn, 1, "📄 New report submitted by $teacher_name: $report_title");
        
        // Also notify active coordinators with approval permissions
        $coord_query = "
            SELECT DISTINCT u.id 
            FROM coordinators c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.status = 'active' 
            AND NOW() BETWEEN c.access_start AND c.access_end
            AND FIND_IN_SET('approve_reports', c.permissions) > 0
        ";
        
        $coord_result = $conn->query($coord_query);
        if ($coord_result && $coord_result->num_rows > 0) {
            while ($coordinator = $coord_result->fetch_assoc()) {
                notify_user($conn, $coordinator['id'], "📄 New report pending approval: $report_title by $teacher_name");
            }
        }
    }
    
    // ============ SET SUCCESS MESSAGE ============
    $_SESSION['flash_message'] = "✅ Report submitted successfully! The admin will review it shortly.";
    
    // ============ REDIRECT ============
    header("Location: dashboard.php");
    exit();
    
} else {
    // ============ IF NOT POST REQUEST ============
    $_SESSION['flash_message'] = "❌ Invalid request method";
    header("Location: dashboard.php");
    exit();
}
?>
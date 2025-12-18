<?php
session_start();
include('../includes/db_connect.php');
include('../includes/functions.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$alert_type = '';
$alert_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $year = trim($_POST['academic_year'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $status = isset($_POST['submit']) ? 'pending' : 'draft';
    
    // Validate
    if (!$title || !$year || !$desc) {
        $alert_type = 'danger';
        $alert_msg = "❌ Please fill all required fields!";
    } else if (!isset($_FILES['report_file']) || $_FILES['report_file']['error'] !== UPLOAD_ERR_OK) {
        $alert_type = 'danger';
        $alert_msg = "❌ Please upload a valid file!";
    } else {
        $file = $_FILES['report_file'];
        $allowed_types = ['pdf', 'doc', 'docx'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            $alert_type = 'danger';
            $alert_msg = "❌ Only PDF, DOC, and DOCX files are allowed!";
        } else {
            // Create uploads directory if it doesn't exist
            if (!is_dir('../uploads')) {
                mkdir('../uploads', 0755, true);
            }
            
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $targetPath = 'uploads/' . $filename;
            $full_path = '../' . $targetPath;
            
            if (move_uploaded_file($file['tmp_name'], $full_path)) {
                // Insert into database
                $stmt = $conn->prepare("
                    INSERT INTO annual_reports 
                    (title, academic_year, description, file_path, uploaded_by, status) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                if (!$stmt) {
                    $alert_type = 'danger';
                    $alert_msg = "❌ SQL Prepare Error: " . $conn->error;
                } else {
                    $stmt->bind_param("ssssis", $title, $year, $desc, $targetPath, $user_id, $status);
                    
                    if ($stmt->execute()) {
                        log_action($conn, $user_id, "Uploaded new report: $title", "teacher");
                        notify_user($conn, 1, "📄 New report submitted by Teacher ID $user_id: $title");
                        
                        $alert_type = 'success';
                        $alert_msg = "✅ Report submitted successfully for admin approval!";
                        
                        // Clear form after success
                        $_POST = array();
                    } else {
                        $alert_type = 'danger';
                        $alert_msg = "❌ Database Error: " . $stmt->error;
                    }
                    
                    $stmt->close();
                }
            } else {
                $alert_type = 'danger';
                $alert_msg = "⚠️ Failed to upload file. Check folder permissions in /uploads.";
            }
        }
    }
}

include('../includes/header.php');
?>

<link rel="stylesheet" href="../assets/style.css">
<div class="container mt-4">
    <div class="card shadow-sm p-4">
        <h2 class="mb-4 text-primary">📤 Upload Annual Report</h2>
        
        <?php if ($alert_msg): ?>
            <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                <?= $alert_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Report Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g., Quarterly Progress Report" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Academic Year</label>
                <input type="text" name="academic_year" class="form-control" placeholder="e.g., 2024-2025" required value="<?= htmlspecialchars($_POST['academic_year'] ?? '') ?>">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4" placeholder="Brief description of the report..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Upload File (PDF/DOC/DOCX)</label>
                <input type="file" name="report_file" class="form-control" accept=".pdf,.doc,.docx" required>
                <small class="text-muted">Maximum file size: 5MB</small>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" name="action" value="draft" class="btn btn-secondary">
                    💾 Save as Draft
                </button>
                <button type="submit" name="submit" class="btn btn-primary">
                    ✉️ Submit for Approval
                </button>
            </div>
        </form>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
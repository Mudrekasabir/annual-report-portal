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

// STEP 1 → Fetch Report
$stmt = $conn->prepare("SELECT * FROM annual_reports WHERE id = ? AND uploaded_by = ?");
if (!$stmt) {
    die("Prepare Error: " . $conn->error);
}

$stmt->bind_param("ii", $report_id, $user_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    $_SESSION['flash_message'] = "❌ Report not found or access denied!";
    header("Location: myreports.php");
    exit();
}

// Only allow editing draft reports
if ($report['status'] !== 'draft') {
    $_SESSION['flash_message'] = "❌ Only draft reports can be edited!";
    header("Location: dashboard.php");
    exit();
}

// STEP 2 → UPDATE Report (Before Output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $year = trim($_POST['academic_year'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    // Validate
    if (!$title || !$year || !$description || !$content) {
        $_SESSION['flash_message'] = "❌ All fields are required!";
        header("Location: edit_report.php?id=$report_id");
        exit();
    }
    
    $update = $conn->prepare("
        UPDATE annual_reports 
        SET title = ?, academic_year = ?, description = ?, content = ?
        WHERE id = ? AND uploaded_by = ?
    ");
    
    if (!$update) {
        die("Prepare Error: " . $conn->error);
    }
    
    $update->bind_param("ssssii", $title, $year, $description, $content, $report_id, $user_id);
    
    if ($update->execute()) {
        log_action($conn, $user_id, "Updated report: $title", "teacher");
        $_SESSION['flash_message'] = "✅ Report updated successfully.";
    } else {
        $_SESSION['flash_message'] = "❌ Update failed: " . $update->error;
    }
    
    $update->close();
    header("Location: dashboard.php");
    exit();
}

include('../includes/header.php');
?>

<link rel="stylesheet" href="../assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<div class="container mt-4">
    <div class="card shadow-sm p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-primary"><i class="bi bi-pencil-square"></i> Edit Report</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold">Title</label>
                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($report['title']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-semibold">Academic Year</label>
                <input type="text" name="academic_year" class="form-control" value="<?= htmlspecialchars($report['academic_year']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-semibold">Brief Description</label>
                <textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($report['description'] ?? '') ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-semibold">Report Content</label>
                <textarea id="editor" name="content" rows="12"><?= htmlspecialchars($report['content'] ?? '') ?></textarea>
            </div>
            
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/oe79zmuyvm29dcy8xtvk5mti756sqy7e99i6ddjoyzac2d8r/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<script>
tinymce.init({
    selector: '#editor',
    height: 500,
    menubar: true,
    plugins: 'lists table image code preview wordcount',
    toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | table image | preview code',
    branding: false,
    skin: 'oxide',
    content_css: 'default',
    content_style: `
        body { font-family: Inter, sans-serif; font-size: 14px; color: #1f2937; }
        h1,h2,h3 { color: #111827; }
    `
});
</script>

<?php include('../includes/footer.php'); ?>
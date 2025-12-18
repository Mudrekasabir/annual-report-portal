<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// ============ SECURITY: Check Authentication ============
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include('../includes/db_connect.php');
include('../includes/header.php');
include('../includes/functions.php');

// ============ CHECK ACCESS ============
$user_role = $_SESSION['role'];
$is_admin = ($user_role === 'admin');
$is_coordinator = ($user_role === 'coordinator' && is_valid_coordinator($conn, $_SESSION['user_id']));

if (!$is_admin && !$is_coordinator) {
    alert("Access Denied!", 'danger');
    header("Location: ../login.php");
    exit();
}

// ============ GET REPORT ID ============
$report_id = intval($_GET['id'] ?? 0);

if ($report_id <= 0) {
    header("Location: approve_reports.php");
    exit();
}

// ============ FETCH REPORT DETAILS ============
$stmt = $conn->prepare("
    SELECT a.*, u.name AS teacher_name, u.email AS teacher_email
    FROM annual_reports a
    JOIN users u ON a.uploaded_by = u.id
    WHERE a.id = ?
");

$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    alert("Report not found.", 'danger');
    header("Location: approve_reports.php");
    exit();
}

$report = $result->fetch_assoc();
$stmt->close();

// ============ SAFE DATE HELPER ============
function safe_date_format($date_value, $format = 'd M Y, h:i A') {
    if (empty($date_value) || $date_value === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    $timestamp = strtotime($date_value);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

// ============ STATUS BADGE ============
$status_badges = [
    'draft' => '<span class="badge bg-secondary">Draft</span>',
    'pending' => '<span class="badge bg-warning text-dark">Pending Review</span>',
    'approved' => '<span class="badge bg-success">Approved</span>',
    'rejected' => '<span class="badge bg-danger">Rejected</span>'
];

$status_badge = $status_badges[$report['status']] ?? '<span class="badge bg-secondary">Unknown</span>';

// Initialize CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

<style>
    body { font-family: 'Inter', sans-serif; background: #f4f6f9; }
    .report-header { 
        background: linear-gradient(135deg, #667eea, #764ba2); 
        color: white; 
        padding: 40px;
        border-radius: 16px;
        margin-bottom: 30px;
    }
    .content-card { 
        background: white; 
        border-radius: 12px; 
        padding: 30px; 
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .info-row { 
        display: flex; 
        padding: 12px 0; 
        border-bottom: 1px solid #e9ecef;
    }
    .info-row:last-child { border-bottom: none; }
    .info-label { 
        font-weight: 600; 
        color: #495057; 
        min-width: 150px;
    }
    .info-value { 
        color: #6c757d; 
        flex: 1;
    }
    .report-content { 
        line-height: 1.8; 
        color: #212529;
        font-size: 15px;
    }
    .file-download-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 20px;
        color: white;
        margin-bottom: 20px;
    }
    .teacher-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: 700;
        margin-right: 15px;
    }
    .print-button {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    @media print {
        .no-print { display: none !important; }
        .report-header { background: #667eea !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="container mt-4 mb-5">
    <!-- Back Button -->
    <div class="mb-3 no-print">
        <a href="approve_reports.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Approvals
        </a>
    </div>

    <!-- Report Header -->
    <div class="report-header">
        <div class="d-flex align-items-center mb-3">
            <div class="teacher-avatar">
                <?= strtoupper(substr($report['teacher_name'], 0, 1)) ?>
            </div>
            <div>
                <h2 class="mb-1 fw-bold"><?= htmlspecialchars($report['title']) ?></h2>
                <p class="mb-0 opacity-75">
                    by <?= htmlspecialchars($report['teacher_name']) ?> • 
                    <?= htmlspecialchars($report['academic_year']) ?>
                </p>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <?= $status_badge ?>
            </div>
            <?php if (isset($report['created_at'])): ?>
            <small class="opacity-75">
                <i class="bi bi-calendar"></i> 
                Created: <?= safe_date_format($report['created_at']) ?>
            </small>
            <?php endif; ?>
        </div>
    </div>

    <!-- Report Information -->
    <div class="content-card">
        <h5 class="fw-bold mb-4"><i class="bi bi-info-circle"></i> Report Information</h5>
        
        <div class="info-row">
            <div class="info-label">Teacher Name:</div>
            <div class="info-value"><?= htmlspecialchars($report['teacher_name']) ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Email:</div>
            <div class="info-value"><?= htmlspecialchars($report['teacher_email']) ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Academic Year:</div>
            <div class="info-value"><?= htmlspecialchars($report['academic_year']) ?></div>
        </div>
        
        <div class="info-row">
            <div class="info-label">Status:</div>
            <div class="info-value"><?= $status_badge ?></div>
        </div>
        
        <?php if (isset($report['created_at'])): ?>
        <div class="info-row">
            <div class="info-label">Submitted:</div>
            <div class="info-value"><?= safe_date_format($report['created_at']) ?></div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($report['updated_at']) && $report['updated_at'] && $report['updated_at'] !== $report['created_at']): ?>
        <div class="info-row">
            <div class="info-label">Last Updated:</div>
            <div class="info-value"><?= safe_date_format($report['updated_at']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Description -->
    <?php if (!empty($report['description'])): ?>
    <div class="content-card">
        <h5 class="fw-bold mb-3"><i class="bi bi-text-paragraph"></i> Description</h5>
        <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($report['description'])) ?></p>
    </div>
    <?php endif; ?>

    <!-- File Attachment -->
    <?php if (!empty($report['file_path'])): 
        $file_path = $report['file_path'];
        if (strpos($file_path, 'uploads/') === 0) {
            $view_path = '../' . $file_path;
        } else {
            $view_path = '../uploads/reports/' . basename($file_path);
        }
        
        $file_exists = file_exists($view_path);
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        $icon_class = 'bi-file-earmark';
        if ($file_extension === 'pdf') {
            $icon_class = 'bi-file-earmark-pdf';
        } elseif (in_array($file_extension, ['doc', 'docx'])) {
            $icon_class = 'bi-file-earmark-word';
        }
    ?>
    <div class="file-download-card no-print">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">
                    <i class="bi <?= $icon_class ?>"></i> 
                    Attached Document
                </h5>
                <small class="opacity-75">
                    <?= htmlspecialchars(basename($file_path)) ?> 
                    (<?= strtoupper($file_extension) ?>)
                </small>
            </div>
            <div>
                <?php if ($file_exists): ?>
                    <a href="<?= htmlspecialchars($view_path) ?>" 
                       target="_blank" 
                       class="btn btn-light btn-lg">
                        <i class="bi bi-download"></i> Download File
                    </a>
                <?php else: ?>
                    <button class="btn btn-outline-light" disabled>
                        <i class="bi bi-exclamation-triangle"></i> File Not Found
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Report Content -->
    <div class="content-card">
        <h5 class="fw-bold mb-4"><i class="bi bi-file-text"></i> Report Content</h5>
        
        <?php if (!empty($report['content'])): ?>
            <div class="report-content">
                <?= $report['content'] ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No text content available. 
                <?php if (!empty($report['file_path'])): ?>
                    Please download the attached file above to view the report.
                <?php else: ?>
                    This report appears to be empty.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions for Pending Reports -->
    <?php if ($report['status'] === 'pending'): ?>
    <div class="content-card no-print">
        <h5 class="fw-bold mb-3"><i class="bi bi-clipboard-check"></i> Quick Actions</h5>
        
        <form method="POST" action="approve_reports.php" onsubmit="return validateReviewForm(this)">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
            
            <div class="mb-3">
                <label class="form-label fw-semibold">Review Comments:</label>
                <textarea name="comment" id="review_comment" class="form-control" rows="3" placeholder="Add your remarks here (required for rejection)..."></textarea>
            </div>
            
            <div class="d-flex gap-3">
                <button type="submit" name="action" value="approve" class="btn btn-success btn-lg">
                    <i class="bi bi-check-circle"></i> Approve Report
                </button>
                <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg">
                    <i class="bi bi-x-circle"></i> Reject Report
                </button>
                <a href="approve_reports.php" class="btn btn-outline-secondary btn-lg">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Floating Print Button -->
<button onclick="window.print()" class="btn btn-primary btn-lg rounded-circle print-button no-print" title="Print Report">
    <i class="bi bi-printer"></i>
</button>

<script>
function validateReviewForm(form) {
    const action = event.submitter?.value;
    const comment = document.getElementById('review_comment').value.trim();
    
    if (action === 'reject' && !comment) {
        alert('Please provide a reason for rejection.');
        return false;
    }
    
    const actionText = action === 'approve' ? 'approve' : 'reject';
    return confirm(`Are you sure you want to ${actionText} this report?`);
}
</script>

<?php include('../includes/footer.php'); ?>
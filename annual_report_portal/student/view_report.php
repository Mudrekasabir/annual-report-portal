<?php
include('../includes/db_connect.php');
include('../includes/header.php');
include('../includes/functions.php');

if ($_SESSION['role'] != 'student') {
    alert("Access Denied!", 'danger');
    exit();
}

$report_id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT a.*, u.name AS teacher_name, u.email AS teacher_email
    FROM annual_reports a 
    JOIN users u ON a.uploaded_by = u.id 
    WHERE a.id = ? AND a.status = 'approved'
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    $_SESSION['flash_message'] = "❌ Report not found or not approved yet.";
    header("Location: dashboard.php");
    exit();
}
?>

<link rel="stylesheet" href="../assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    @media print {
        .no-print {
            display: none !important;
        }
        .card {
            box-shadow: none !important;
            border: none !important;
        }
    }
    
    .report-content {
        font-size: 15px;
        line-height: 1.8;
        color: #374151;
    }
    
    .report-content h1, .report-content h2, .report-content h3 {
        color: #111827;
        margin-top: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .report-content p {
        margin-bottom: 1rem;
    }
    
    .report-content img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        margin: 1rem 0;
    }
    
    .report-content table {
        width: 100%;
        border-collapse: collapse;
        margin: 1.5rem 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .report-content table td,
    .report-content table th {
        border: 1px solid #e5e7eb;
        padding: 12px;
        text-align: left;
    }
    
    .report-content table th {
        background-color: #f9fafb;
        font-weight: 600;
    }
    
    .report-content ul, .report-content ol {
        margin-left: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .report-content blockquote {
        border-left: 4px solid #4f46e5;
        padding-left: 1rem;
        margin: 1rem 0;
        font-style: italic;
        color: #6b7280;
    }
    
    .metadata-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: #f3f4f6;
        border-radius: 8px;
        font-size: 14px;
    }
</style>

<div class="container mt-4">
    <!-- Header Section -->
    <div class="p-4 mb-4 rounded-3 text-white no-print" style="background: linear-gradient(135deg, #4f46e5, #6366f1);">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold mb-0">
                <i class="bi bi-file-earmark-text"></i> Annual Report
            </h2>
            <a href="dashboard.php" class="btn btn-light btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Main Report Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            
            <!-- Report Title -->
            <h1 class="fw-bold text-primary mb-4" style="font-size: 28px;">
                <?= htmlspecialchars($report['title']) ?>
            </h1>

            <!-- Metadata Section -->
            <div class="d-flex flex-wrap gap-3 mb-4 pb-4 border-bottom">
                <div class="metadata-badge">
                    <i class="bi bi-calendar3 text-primary"></i>
                    <span><strong>Academic Year:</strong> <?= htmlspecialchars($report['academic_year']) ?></span>
                </div>
                
                <div class="metadata-badge">
                    <i class="bi bi-person-circle text-primary"></i>
                    <span><strong>By:</strong> <?= htmlspecialchars($report['teacher_name']) ?></span>
                </div>
                
                <?php if (!empty($report['submitted_at'])): ?>
                <div class="metadata-badge">
                    <i class="bi bi-clock-history text-primary"></i>
                    <span><strong>Submitted:</strong> <?= date('M d, Y', strtotime($report['submitted_at'])) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Description/Summary Section -->
            <?php if (!empty($report['description'])): ?>
            <div class="alert alert-light border-start border-primary border-4 mb-4">
                <h5 class="fw-semibold mb-2">
                    <i class="bi bi-info-circle-fill text-primary"></i> Executive Summary
                </h5>
                <p class="mb-0" style="font-size: 15px; line-height: 1.7;">
                    <?= nl2br(htmlspecialchars($report['description'])) ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- Main Content Section -->
            <div class="mt-4">
                <h4 class="fw-semibold mb-3 pb-2 border-bottom">
                    <i class="bi bi-file-text"></i> Full Report
                </h4>
                <div class="report-content">
                    <?php 
                      if (!empty($report['content'])) {
                          echo $report['content'];
                      } else {
                          echo '<div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    No detailed content available for this report.
                                </div>';
                      }
                    ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Action Buttons -->
    <div class="d-flex gap-2 flex-wrap mb-5 no-print">
        <?php if (!empty($report['file_path'])): ?>
            <a href="../<?= htmlspecialchars($report['file_path']) ?>" 
               target="_blank" 
               class="btn btn-outline-primary">
                <i class="bi bi-paperclip"></i> View Attachment
            </a>
        <?php endif; ?>

        <button class="btn btn-danger" onclick="downloadPDF()">
            <i class="bi bi-file-earmark-pdf"></i> Export as PDF
        </button>
        
        <button class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Report
        </button>
        
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left-circle"></i> Back to Reports
        </a>
    </div>
</div>

<?php include('../includes/footer.php'); ?>

<!-- PDF Generation Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
async function downloadPDF() {
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    
    // Show loading state
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating PDF...';
    btn.disabled = true;
    
    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'pt', 'a4');
        
        // Add header
        doc.setFontSize(20);
        doc.setTextColor(79, 70, 229);
        doc.text("<?= addslashes($report['title']) ?>", 40, 50);
        
        // Add metadata
        doc.setFontSize(10);
        doc.setTextColor(100, 100, 100);
        let yPos = 75;
        doc.text("Academic Year: <?= addslashes($report['academic_year']) ?>", 40, yPos);
        yPos += 15;
        doc.text("Teacher: <?= addslashes($report['teacher_name']) ?>", 40, yPos);
        yPos += 15;
        <?php if (!empty($report['submitted_at'])): ?>
        doc.text("Submitted: <?= date('M d, Y', strtotime($report['submitted_at'])) ?>", 40, yPos);
        yPos += 20;
        <?php endif; ?>
        
        // Add description if available
        <?php if (!empty($report['description'])): ?>
        doc.setFontSize(11);
        doc.setTextColor(0, 0, 0);
        const description = "<?= addslashes(strip_tags($report['description'])) ?>";
        const descLines = doc.splitTextToSize(description, 520);
        doc.text("Summary:", 40, yPos);
        yPos += 15;
        doc.text(descLines, 40, yPos);
        yPos += (descLines.length * 12) + 20;
        <?php endif; ?>
        
        // Add content
        const content = document.querySelector('.report-content');
        
        await doc.html(content, {
            callback: function (doc) {
                const filename = "<?= preg_replace('/[^a-zA-Z0-9-_]/', '-', $report['title']) ?>-<?= $report['academic_year'] ?>.pdf";
                doc.save(filename);
            },
            x: 40,
            y: yPos,
            width: 520,
            windowWidth: 900,
            html2canvas: { 
                scale: 0.65,
                useCORS: true,
                logging: false
            }
        });
        
    } catch (error) {
        console.error('PDF generation error:', error);
        alert('❌ Error generating PDF. Please try the Print option instead.');
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}
</script>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// ============ GENERATE CSRF TOKEN ============
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include('../includes/db_connect.php');
include('../includes/header.php');
include('../includes/functions.php');

$user_id = $_SESSION['user_id'];
?>

<link rel="stylesheet" href="../assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
    body {
        background: #f8fafc;
        font-family: 'Inter', sans-serif;
    }

    .main-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 30px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
    }

    .page-header h1 {
        font-size: 2rem;
        font-weight: 700;
        margin: 0 0 10px 0;
    }

    .page-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 1rem;
    }

    .form-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .form-section {
        padding: 30px;
        border-bottom: 1px solid #e5e7eb;
    }

    .form-section:last-child {
        border-bottom: none;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }

    .section-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1f2937;
        margin: 0;
    }

    .form-label {
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .required-badge {
        background: #fee2e2;
        color: #dc2626;
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 500;
    }

    .optional-badge {
        background: #e0e7ff;
        color: #6366f1;
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 500;
    }

    .form-control, .form-select {
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }

    .input-hint {
        font-size: 0.85rem;
        color: #6b7280;
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .char-counter {
        font-size: 0.85rem;
        color: #6b7280;
        text-align: right;
        margin-top: 6px;
    }

    .file-upload-area {
        border: 3px dashed #cbd5e0;
        border-radius: 16px;
        padding: 40px;
        text-align: center;
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
    }
    
    .file-upload-area:hover {
        border-color: #667eea;
        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        transform: translateY(-2px);
    }
    
    .file-upload-area.dragover {
        border-color: #667eea;
        background: #e0e7ff;
        transform: scale(1.02);
    }

    .upload-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2.5rem;
    }
    
    .file-preview {
        background: #f9fafb;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
        display: none;
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .file-preview.active {
        display: block;
    }
    
    .file-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .file-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        flex-shrink: 0;
    }
    
    .progress-container {
        margin-top: 15px;
        display: none;
    }
    
    .progress-container.active {
        display: block;
    }

    .progress {
        height: 8px;
        border-radius: 10px;
        background: #e5e7eb;
    }

    .progress-bar {
        border-radius: 10px;
        background: linear-gradient(90deg, #667eea, #764ba2);
    }

    .action-buttons {
        display: flex;
        gap: 15px;
        justify-content: space-between;
        padding: 30px;
        background: #f9fafb;
    }

    .btn {
        border-radius: 10px;
        padding: 12px 28px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        border: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
        background: #6b7280;
        color: white;
    }

    .btn-secondary:hover {
        background: #4b5563;
        transform: translateY(-2px);
    }

    .btn-outline-danger {
        border: 2px solid #dc2626;
        color: #dc2626;
        background: white;
    }

    .btn-outline-danger:hover {
        background: #dc2626;
        color: white;
        transform: translateY(-2px);
    }

    .btn-outline-secondary {
        border: 2px solid #6b7280;
        color: #6b7280;
        background: white;
    }

    .btn-outline-secondary:hover {
        background: #6b7280;
        color: white;
    }

    .quick-tips {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-left: 4px solid #f59e0b;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
    }

    .quick-tips h5 {
        color: #92400e;
        font-weight: 600;
        margin: 0 0 12px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .quick-tips ul {
        margin: 0;
        padding-left: 20px;
        color: #78350f;
    }

    .quick-tips li {
        margin-bottom: 6px;
    }

    .export-section {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .template-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .template-btn {
        padding: 15px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        background: white;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: left;
    }

    .template-btn:hover {
        border-color: #667eea;
        background: #f9fafb;
        transform: translateY(-2px);
    }

    .template-btn strong {
        display: block;
        color: #1f2937;
        margin-bottom: 4px;
    }

    .template-btn small {
        color: #6b7280;
    }

    .alert {
        border-radius: 12px;
        border: none;
        padding: 16px 20px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.3s ease;
    }

    .alert i {
        font-size: 1.3rem;
    }
</style>

<div class="main-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1><i class="bi bi-journal-plus"></i> Create New Report</h1>
                <p>Compose a comprehensive academic report with rich formatting and attachments</p>
            </div>
            <a href="dashboard.php" class="btn btn-light">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Quick Tips -->
    <div class="quick-tips">
        <h5><i class="bi bi-lightbulb"></i> Quick Tips for Better Reports</h5>
        <ul>
            <li>Use clear, descriptive titles that reflect the report's content</li>
            <li>Structure your content with headings and bullet points for readability</li>
            <li>Attach supporting documents (PDF/DOC) when referencing data or resources</li>
            <li>Save as draft frequently to avoid losing your work</li>
        </ul>
    </div>

    <!-- Alert Container -->
    <div id="alertContainer"></div>

    <!-- Main Form -->
    <form id="reportForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="uploaded_by" value="<?php echo $user_id; ?>">

        <div class="form-card">
            <!-- Basic Information Section -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="bi bi-info-circle"></i>
                    </div>
                    <h3 class="section-title">Basic Information</h3>
                </div>

                <div class="mb-4">
                    <label class="form-label">
                        Report Title
                        <span class="required-badge">Required</span>
                    </label>
                    <input type="text" 
                           name="title" 
                           id="titleInput"
                           class="form-control" 
                           placeholder="e.g., Mid-Year Academic Performance Review" 
                           maxlength="200"
                           required>
                    <div class="char-counter">
                        <span id="titleCounter">0</span>/200 characters
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label">
                            Academic Year
                            <span class="required-badge">Required</span>
                        </label>
                        <select name="academic_year" class="form-select" required>
                            <option value="">-- Select Academic Year --</option>
                            <?php
                            $current_year = date('Y');
                            for ($i = $current_year - 5; $i <= $current_year + 1; $i++) {
                                $year_range = $i . '-' . ($i + 1);
                                $selected = ($i == $current_year) ? 'selected' : '';
                                echo "<option value='$year_range' $selected>$year_range</option>";
                            }
                            ?>
                        </select>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i>
                            Current year is pre-selected
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="form-label">
                            Report Template
                            <span class="optional-badge">Optional</span>
                        </label>
                        <select class="form-select" id="templateSelect" onchange="applyTemplate()">
                            <option value="">-- Use Custom Format --</option>
                            <option value="performance">Academic Performance Review</option>
                            <option value="progress">Student Progress Report</option>
                            <option value="incident">Incident Report</option>
                            <option value="meeting">Meeting Minutes</option>
                        </select>
                        <div class="input-hint">
                            <i class="bi bi-magic"></i>
                            Apply a pre-formatted template
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Brief Description
                        <span class="optional-badge">Optional</span>
                    </label>
                    <textarea name="description" 
                              id="descriptionInput"
                              class="form-control" 
                              rows="3" 
                              maxlength="500"
                              placeholder="Provide a brief summary of this report's purpose and key findings..."></textarea>
                    <div class="char-counter">
                        <span id="descCounter">0</span>/500 characters
                    </div>
                </div>
            </div>

            <!-- Content Section -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="bi bi-file-text"></i>
                    </div>
                    <h3 class="section-title">Report Content</h3>
                </div>

                <div class="input-hint mb-3">
                    <i class="bi bi-pencil-square"></i>
                    Use the rich text editor below to format your report with headings, lists, tables, and images
                </div>

                <textarea id="editor" name="content" rows="12"></textarea>
                
                <div class="mt-3">
                    <div class="char-counter">
                        Word count: <span id="wordCount">0</span> words
                    </div>
                </div>
            </div>

            <!-- File Attachment Section -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="bi bi-paperclip"></i>
                    </div>
                    <h3 class="section-title">Attachments</h3>
                </div>

                <div class="file-upload-area" id="fileUploadArea">
                    <input type="file" name="report_file" id="reportFile" accept=".pdf,.doc,.docx" style="display: none;">
                    <div class="upload-icon">
                        <i class="bi bi-cloud-upload"></i>
                    </div>
                    <h5 style="color: #1f2937; margin-bottom: 10px;">Drop your file here or click to browse</h5>
                    <p class="text-muted mb-0">Supports: PDF, DOC, DOCX (Maximum 10MB)</p>
                </div>

                <div class="file-preview" id="filePreview">
                    <div class="file-info">
                        <div class="file-icon">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold" style="color: #1f2937; font-size: 1rem;" id="fileName">document.pdf</div>
                            <small class="text-muted" id="fileSize">0 KB</small>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeFile()">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    </div>
                    
                    <div class="progress-container" id="progressContainer">
                        <div class="progress mt-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" 
                                 id="uploadProgress" 
                                 style="width: 0%"></div>
                        </div>
                        <div class="text-center mt-2">
                            <small class="text-muted">Uploading... <span id="uploadPercent">0%</span></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <div class="d-flex gap-3">
                    <button type="button" class="btn btn-secondary" onclick="saveReport('draft')" id="draftBtn">
                        <i class="bi bi-save"></i> Save as Draft
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="clearForm()">
                        <i class="bi bi-x-circle"></i> Clear Form
                    </button>
                </div>

                <button type="button" class="btn btn-primary" onclick="saveReport('pending')" id="submitBtn">
                    <i class="bi bi-send-check"></i> Submit for Approval
                </button>
            </div>
        </div>
    </form>

    <!-- Export Section -->
    <div class="form-card mt-4" style="padding: 30px;">
        <div class="section-header">
            <div class="section-icon">
                <i class="bi bi-download"></i>
            </div>
            <h3 class="section-title">Export Options</h3>
        </div>
        <p class="text-muted mb-3">Download a copy of your report before submitting</p>
        <div class="export-section">
            <button type="button" class="btn btn-outline-danger" onclick="exportPDF()">
                <i class="bi bi-file-pdf"></i> Export as PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="printReport()">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>
    </div>
</div>

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/oe79zmuyvm29dcy8xtvk5mti756sqy7e99i6ddjoyzac2d8r/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<script>
// Initialize TinyMCE with enhanced features
tinymce.init({
    selector: '#editor',
    height: 600,
    menubar: true,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | formatselect | bold italic underline strikethrough | ' +
             'alignleft aligncenter alignright alignjustify | ' +
             'bullist numlist outdent indent | table image link | ' +
             'forecolor backcolor | removeformat | help',
    toolbar_mode: 'sliding',
    branding: false,
    skin: 'oxide',
    content_css: 'default',
    content_style: `
        body { 
            font-family: 'Inter', sans-serif; 
            font-size: 15px; 
            color: #1f2937; 
            line-height: 1.6;
            padding: 20px;
        }
        h1, h2, h3, h4, h5, h6 { 
            color: #111827; 
            margin-top: 1.5em;
            margin-bottom: 0.5em;
        }
        p { margin-bottom: 1em; }
        ul, ol { margin-left: 1.5em; }
    `,
    setup: function(editor) {
        editor.on('keyup', function() {
            updateWordCount();
        });
    }
});

// Character counters
document.getElementById('titleInput').addEventListener('input', function() {
    document.getElementById('titleCounter').textContent = this.value.length;
});

document.getElementById('descriptionInput').addEventListener('input', function() {
    document.getElementById('descCounter').textContent = this.value.length;
});

// Word count for editor
function updateWordCount() {
    const content = tinymce.get('editor').getContent({format: 'text'});
    const words = content.trim().split(/\s+/).filter(word => word.length > 0).length;
    document.getElementById('wordCount').textContent = words;
}

// Template application
function applyTemplate() {
    const template = document.getElementById('templateSelect').value;
    let content = '';
    
    switch(template) {
        case 'performance':
            content = `<h2>Academic Performance Review</h2>
<h3>Executive Summary</h3>
<p>[Provide a brief overview of the academic performance]</p>

<h3>Key Findings</h3>
<ul>
<li>Finding 1</li>
<li>Finding 2</li>
<li>Finding 3</li>
</ul>

<h3>Detailed Analysis</h3>
<p>[Add detailed analysis here]</p>

<h3>Recommendations</h3>
<ol>
<li>Recommendation 1</li>
<li>Recommendation 2</li>
<li>Recommendation 3</li>
</ol>`;
            break;
        case 'progress':
            content = `<h2>Student Progress Report</h2>
<h3>Student Information</h3>
<p><strong>Student Name:</strong> [Name]<br>
<strong>Grade Level:</strong> [Grade]<br>
<strong>Period:</strong> [Time Period]</p>

<h3>Academic Progress</h3>
<table style="width: 100%; border-collapse: collapse;">
<thead>
<tr style="background: #f3f4f6;">
<th style="border: 1px solid #d1d5db; padding: 8px;">Subject</th>
<th style="border: 1px solid #d1d5db; padding: 8px;">Grade</th>
<th style="border: 1px solid #d1d5db; padding: 8px;">Comments</th>
</tr>
</thead>
<tbody>
<tr>
<td style="border: 1px solid #d1d5db; padding: 8px;">Mathematics</td>
<td style="border: 1px solid #d1d5db; padding: 8px;">[Grade]</td>
<td style="border: 1px solid #d1d5db; padding: 8px;">[Comments]</td>
</tr>
</tbody>
</table>

<h3>Behavioral Observations</h3>
<p>[Add observations here]</p>

<h3>Next Steps</h3>
<p>[Outline action items]</p>`;
            break;
        case 'incident':
            content = `<h2>Incident Report</h2>
<h3>Incident Details</h3>
<p><strong>Date:</strong> [Date]<br>
<strong>Time:</strong> [Time]<br>
<strong>Location:</strong> [Location]<br>
<strong>Reported By:</strong> [Name]</p>

<h3>Description of Incident</h3>
<p>[Detailed description of what occurred]</p>

<h3>Individuals Involved</h3>
<ul>
<li>[Person 1]</li>
<li>[Person 2]</li>
</ul>

<h3>Immediate Actions Taken</h3>
<p>[Describe actions taken]</p>

<h3>Follow-up Required</h3>
<p>[List follow-up actions]</p>`;
            break;
        case 'meeting':
            content = `<h2>Meeting Minutes</h2>
<h3>Meeting Information</h3>
<p><strong>Date:</strong> [Date]<br>
<strong>Time:</strong> [Time]<br>
<strong>Location:</strong> [Location]<br>
<strong>Attendees:</strong> [List attendees]</p>

<h3>Agenda Items</h3>
<ol>
<li>Item 1</li>
<li>Item 2</li>
<li>Item 3</li>
</ol>

<h3>Discussion Summary</h3>
<p>[Summarize key discussion points]</p>

<h3>Decisions Made</h3>
<ul>
<li>Decision 1</li>
<li>Decision 2</li>
</ul>

<h3>Action Items</h3>
<table style="width: 100%; border-collapse: collapse;">
<thead>
<tr style="background: #f3f4f6;">
<th style="border: 1px solid #d1d5db; padding: 8px;">Action</th>
<th style="border: 1px solid #d1d5db; padding: 8px;">Responsible</th>
<th style="border: 1px solid #d1d5db; padding: 8px;">Deadline</th>
</tr>
</thead>
<tbody>
<tr>
<td style="border: 1px solid #d1d5db; padding: 8px;">[Action]</td>
<td style="border: 1px solid #d1d5db; padding: 8px;">[Person]</td>
<td style="border: 1px solid #d1d5db; padding: 8px;">[Date]</td>
</tr>
</tbody>
</table>`;
            break;
    }
    
    if (content) {
        tinymce.get('editor').setContent(content);
        updateWordCount();
        showAlert('✨ Template applied! Feel free to customize it.', 'info');
    }
}

// Clear form
function clearForm() {
    if (confirm('Are you sure you want to clear all fields? This cannot be undone.')) {
        document.getElementById('reportForm').reset();
        tinymce.get('editor').setContent('');
        removeFile();
        document.getElementById('titleCounter').textContent = '0';
        document.getElementById('descCounter').textContent = '0';
        document.getElementById('wordCount').textContent = '0';
        showAlert('Form cleared successfully', 'info');
    }
}

// Print report
function printReport() {
    const title = document.querySelector('[name="title"]').value;
    const year = document.querySelector('[name="academic_year"]').value;
    const content = tinymce.get("editor").getContent();
    
    if (!title || !year || !content) {
        showAlert("⚠️ Please fill in the form before printing", 'warning');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; padding: 40px; line-height: 1.6; }
                h1 { color: #111827; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
                .meta { color: #6b7280; margin-bottom: 30px; }
                @media print { body { padding: 20px; } }
            </style>
        </head>
        <body>
            <h1>${title}</h1>
            <div class="meta">Academic Year: ${year}</div>
            ${content}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => printWindow.print(), 250);
}

// File Upload Handler
const fileUploadArea = document.getElementById('fileUploadArea');
const fileInput = document.getElementById('reportFile');
const filePreview = document.getElementById('filePreview');
const progressContainer = document.getElementById('progressContainer');

fileUploadArea.addEventListener('click', () => fileInput.click());

fileUploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    fileUploadArea.classList.add('dragover');
});

fileUploadArea.addEventListener('dragleave', () => {
    fileUploadArea.classList.remove('dragover');
});

fileUploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    fileUploadArea.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        handleFileSelect(files[0]);
    }
});

fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        handleFileSelect(e.target.files[0]);
    }
});

function handleFileSelect(file) {
    const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!allowedTypes.includes(file.type)) {
        showAlert('⚠️ Please upload a PDF, DOC, or DOCX file.', 'warning');
        return;
    }
    
    if (file.size > 10 * 1024 * 1024) {
        showAlert('⚠️ File size must be less than 10MB.', 'warning');
        return;
    }
    
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatFileSize(file.size);
    filePreview.classList.add('active');
    
    const icon = document.querySelector('.file-icon i');
    if (file.type === 'application/pdf') {
        icon.className = 'bi bi-file-earmark-pdf';
    } else {
        icon.className = 'bi bi-file-earmark-word';
    }
    
    showAlert('✅ File attached successfully: ' + file.name, 'success');
}

function removeFile() {
    fileInput.value = '';
    filePreview.classList.remove('active');
    progressContainer.classList.remove('active');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Alert function
function showAlert(message, type) {
    const alertContainer = document.getElementById('alertContainer');
    const alertDiv = document.createElement('div');
    
    let icon = '';
    switch(type) {
        case 'success': icon = '<i class="bi bi-check-circle-fill"></i>'; break;
        case 'danger': icon = '<i class="bi bi-x-circle-fill"></i>'; break;
        case 'warning': icon = '<i class="bi bi-exclamation-triangle-fill"></i>'; break;
        case 'info': icon = '<i class="bi bi-info-circle-fill"></i>'; break;
    }
    
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${icon}
        <span>${message}</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    alertContainer.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Save Report Function
function saveReport(status) {
    console.log('=== SAVE REPORT STARTED ===');
    console.log('Status:', status);
    
    const title = document.querySelector('[name="title"]').value.trim();
    const year = document.querySelector('[name="academic_year"]').value.trim();
    const description = document.querySelector('[name="description"]').value.trim();
    const content = tinymce.get("editor").getContent();
    const fileInput = document.getElementById('reportFile');
    const csrfToken = document.querySelector('[name="csrf_token"]').value;
    const uploadedBy = document.querySelector('[name="uploaded_by"]').value;

    console.log('Form Data:', { title, year, description, contentLength: content.length, uploadedBy });

    if (!title || !year || !content) {
        const missing = [];
        if (!title) missing.push('Title');
        if (!year) missing.push('Academic Year');
        if (!content) missing.push('Content');
        
        showAlert(`⚠️ Please fill in required fields: ${missing.join(', ')}`, 'warning');
        console.log('Validation failed:', missing);
        return;
    }

    const draftBtn = document.getElementById('draftBtn');
    const submitBtn = document.getElementById('submitBtn');
    const originalDraftText = draftBtn.innerHTML;
    const originalSubmitText = submitBtn.innerHTML;
    
    draftBtn.disabled = true;
    submitBtn.disabled = true;
    draftBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('title', title);
    formData.append('academic_year', year);
    formData.append('description', description);
    formData.append('content', content);
    formData.append('uploaded_by', uploadedBy);
    formData.append('status', status);
    
    if (fileInput.files.length > 0) {
        formData.append('report_file', fileInput.files[0]);
        console.log('File attached:', fileInput.files[0].name);
    }

    if (fileInput.files.length > 0) {
        progressContainer.classList.add('active');
    }

    console.log('Sending request to: api_create_report.php');

    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percentComplete = Math.round((e.loaded / e.total) * 100);
            const progressBar = document.getElementById('uploadProgress');
            progressBar.style.width = percentComplete + '%';
            document.getElementById('uploadPercent').textContent = percentComplete + '%';
            console.log('Upload progress:', percentComplete + '%');
        }
    });

    xhr.addEventListener('load', () => {
        console.log('Response Status:', xhr.status);
        console.log('Response Text:', xhr.responseText);
        
        draftBtn.disabled = false;
        submitBtn.disabled = false;
        draftBtn.innerHTML = originalDraftText;
        submitBtn.innerHTML = originalSubmitText;

        if (xhr.status === 200 || xhr.status === 201) {
            try {
                const res = JSON.parse(xhr.responseText);
                console.log('Parsed Response:', res);
                
                if (res.status === "success") {
                    showAlert('✅ ' + res.message, 'success');
                    
                    setTimeout(() => {
                        window.location.href = "dashboard.php";
                    }, 2000);
                } else {
                    showAlert("❌ " + res.message, 'danger');
                    progressContainer.classList.remove('active');
                }
            } catch (e) {
                console.error('JSON Parse Error:', e);
                showAlert("❌ Invalid response from server. Check console for details.", 'danger');
                progressContainer.classList.remove('active');
            }
        } else {
            showAlert(`⚠️ Server Error! Status: ${xhr.status}. Check console for details.`, 'danger');
            progressContainer.classList.remove('active');
        }
    });

    xhr.addEventListener('error', () => {
        console.error('Network Error - Cannot reach server');
        showAlert("⚠️ Network error! Make sure api_create_report.php exists in the teacher folder.", 'danger');
        progressContainer.classList.remove('active');
        draftBtn.disabled = false;
        submitBtn.disabled = false;
        draftBtn.innerHTML = originalDraftText;
        submitBtn.innerHTML = originalSubmitText;
    });

    xhr.open('POST', 'api_create_report.php');
    xhr.send(formData);
    console.log('Request sent!');
}

// PDF Export
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: "pt", format: "a4" });

    const title = document.querySelector('[name="title"]').value;
    const year = document.querySelector('[name="academic_year"]').value;
    const description = document.querySelector('[name="description"]').value;
    const text = tinymce.get("editor").getContent({ format: "text" });
    
    if (!title || !year || !text) {
        showAlert("⚠️ Please fill in the form before exporting", 'warning');
        return;
    }
    
    // Header
    doc.setFontSize(20);
    doc.setFont(undefined, 'bold');
    doc.text(title, 40, 50);
    
    // Academic Year
    doc.setFontSize(12);
    doc.setFont(undefined, 'normal');
    doc.text("Academic Year: " + year, 40, 75);
    
    // Description
    if (description) {
        doc.setFontSize(10);
        doc.setTextColor(100);
        const descLines = doc.splitTextToSize(description, 515);
        doc.text(descLines, 40, 95);
        doc.setTextColor(0);
    }
    
    // Line separator
    doc.setDrawColor(102, 126, 234);
    doc.setLineWidth(2);
    doc.line(40, description ? 120 : 90, 555, description ? 120 : 90);
    
    // Content
    doc.setFontSize(11);
    const lines = doc.splitTextToSize(text, 515);
    doc.text(lines, 40, description ? 140 : 110);
    
    // Footer
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(9);
        doc.setTextColor(150);
        doc.text('Page ' + i + ' of ' + pageCount, 40, 820);
        doc.text('Generated: ' + new Date().toLocaleDateString(), 495, 820);
    }
    
    doc.save("report-" + title.replace(/\s+/g, '-') + ".pdf");
    
    showAlert("✅ PDF exported successfully!", 'success');
}
</script>

<?php include('../includes/footer.php'); ?>
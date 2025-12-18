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

// ============ GENERATE CSRF TOKEN ============
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============ Get Current Academic Year ============
$current_year = date('Y');
$academic_years = [];
for ($i = $current_year - 5; $i <= $current_year + 1; $i++) {
    $academic_years[] = $i . '-' . ($i + 1);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Annual Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 30px; margin-bottom: 20px; }
        h1 { color: #1a73e8; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; color: #333; margin-bottom: 8px; }
        .required { color: #e74c3c; }
        input[type="text"], select, textarea { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #1a73e8; }
        textarea { min-height: 200px; resize: vertical; font-family: inherit; }
        .file-upload { border: 2px dashed #e0e0e0; border-radius: 8px; padding: 20px; text-align: center; background: #fafafa; cursor: pointer; transition: all 0.3s; }
        .file-upload:hover { border-color: #1a73e8; background: #f0f7ff; }
        .file-upload input { display: none; }
        .file-info { margin-top: 10px; color: #666; font-size: 13px; }
        .selected-file { display: inline-block; background: #e3f2fd; color: #1976d2; padding: 8px 15px; border-radius: 20px; margin-top: 10px; font-size: 13px; }
        .button-group { display: flex; gap: 15px; margin-top: 30px; }
        button { padding: 12px 30px; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-primary { background: #1a73e8; color: white; }
        .btn-primary:hover { background: #1557b0; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(26,115,232,0.3); }
        .btn-secondary { background: #f1f3f4; color: #333; }
        .btn-secondary:hover { background: #e0e0e0; }
        .btn-success { background: #34a853; color: white; }
        .btn-success:hover { background: #2d9248; }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: none; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        .spinner { display: none; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #1a73e8; border-radius: 50%; animation: spin 1s linear infinite; margin-left: 10px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .help-text { font-size: 13px; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>📄 Create Annual Report</h1>
            <p class="subtitle">Fill in the details below to create your annual report</p>

            <!-- Alert Messages -->
            <div id="alertBox" class="alert"></div>

            <form id="reportForm" enctype="multipart/form-data">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="uploaded_by" value="<?php echo $user_id; ?>">

                <!-- Title -->
                <div class="form-group">
                    <label for="title">Report Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" placeholder="e.g., Annual Teaching Report 2024" required>
                </div>

                <!-- Academic Year -->
                <div class="form-group">
                    <label for="academic_year">Academic Year <span class="required">*</span></label>
                    <select id="academic_year" name="academic_year" required>
                        <option value="">-- Select Academic Year --</option>
                        <?php foreach ($academic_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($year === ($current_year . '-' . ($current_year + 1))) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <input type="text" id="description" name="description" placeholder="Brief description of the report">
                    <div class="help-text">A short summary of what this report covers</div>
                </div>

                <!-- Content -->
                <div class="form-group">
                    <label for="content">Report Content <span class="required">*</span></label>
                    <textarea id="content" name="content" placeholder="Enter your report content here..." required></textarea>
                    <div class="help-text">Write your detailed report content or attach a file below</div>
                </div>

                <!-- File Upload -->
                <div class="form-group">
                    <label>Attach File (Optional)</label>
                    <div class="file-upload" onclick="document.getElementById('report_file').click()">
                        <input type="file" id="report_file" name="report_file" accept=".pdf,.doc,.docx" onchange="handleFileSelect(event)">
                        <div>
                            <span style="font-size: 48px;">📎</span>
                            <p style="margin-top: 10px; font-weight: 600;">Click to upload file</p>
                            <p class="file-info">Supported: PDF, DOC, DOCX (Max 10MB)</p>
                        </div>
                    </div>
                    <div id="selectedFileName"></div>
                </div>

                <!-- Status Selection -->
                <div class="form-group">
                    <label for="status">Save As</label>
                    <select id="status" name="status">
                        <option value="draft">📝 Draft (Save for later)</option>
                        <option value="pending">📤 Submit for Approval</option>
                    </select>
                    <div class="help-text">Choose "Draft" to save and continue editing, or "Submit" to send for admin review</div>
                </div>

                <!-- Buttons -->
                <div class="button-group">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <span id="btnText">💾 Save Report</span>
                        <span class="spinner" id="spinner"></span>
                    </button>
                    <button type="button" class="btn-secondary" onclick="window.location.href='dashboard.php'">
                        ❌ Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ============ File Selection Handler ============
        function handleFileSelect(event) {
            const file = event.target.files[0];
            const fileNameDiv = document.getElementById('selectedFileName');
            
            if (file) {
                // Validate file size
                if (file.size > 10 * 1024 * 1024) {
                    showAlert('File size must be less than 10MB', 'error');
                    event.target.value = '';
                    fileNameDiv.innerHTML = '';
                    return;
                }
                
                // Display selected file
                fileNameDiv.innerHTML = `<div class="selected-file">✓ ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</div>`;
            } else {
                fileNameDiv.innerHTML = '';
            }
        }

        // ============ Show Alert Function ============
        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.className = 'alert alert-' + type;
            alertBox.textContent = message;
            alertBox.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 5000);
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // ============ Form Submission Handler ============
        document.getElementById('reportForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const spinner = document.getElementById('spinner');
            
            // Validate required fields
            const title = document.getElementById('title').value.trim();
            const year = document.getElementById('academic_year').value.trim();
            const content = document.getElementById('content').value.trim();
            
            if (!title || !year || !content) {
                showAlert('Please fill in all required fields (Title, Academic Year, Content)', 'error');
                return;
            }
            
            // Disable button and show loading
            submitBtn.disabled = true;
            btnText.textContent = '⏳ Saving...';
            spinner.style.display = 'inline-block';
            
            try {
                // Prepare form data
                const formData = new FormData(this);
                
                // Send AJAX request
                const response = await fetch('api_create_report.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (response.ok && result.status === 'success') {
                    showAlert(result.message, 'success');
                    
                    // Redirect after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    showAlert(result.message || 'An error occurred while saving the report', 'error');
                    
                    // Re-enable button
                    submitBtn.disabled = false;
                    btnText.textContent = '💾 Save Report';
                    spinner.style.display = 'none';
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Network error: Unable to connect to server', 'error');
                
                // Re-enable button
                submitBtn.disabled = false;
                btnText.textContent = '💾 Save Report';
                spinner.style.display = 'none';
            }
        });

        // ============ Update Button Text Based on Status ============
        document.getElementById('status').addEventListener('change', function() {
            const btnText = document.getElementById('btnText');
            if (this.value === 'pending') {
                btnText.textContent = '📤 Submit for Approval';
            } else {
                btnText.textContent = '💾 Save as Draft';
            }
        });
    </script>
</body>
</html>
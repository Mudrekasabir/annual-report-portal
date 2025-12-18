<?php
session_start();
header('Content-Type: application/json');

// ============ CONFIGURATION ============
define('LOG_FILE', '../logs/api_debug.txt');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_DIR', '../uploads/reports/');
define('ALLOWED_FILE_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]);
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx']);

// ============ UTILITY FUNCTIONS ============
function api_log($msg) {
    $log_dir = dirname(LOG_FILE);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    @file_put_contents(LOG_FILE, date("Y-m-d H:i:s") . " - " . $msg . "\n", FILE_APPEND);
}

function send_response($status, $message, $data = [], $http_code = 200) {
    http_response_code($http_code);
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
        'timestamp' => date('c')
    ], $data));
    exit;
}

function send_error($message, $http_code = 400, $details = null) {
    api_log("ERROR: $message" . ($details ? " | Details: $details" : ""));
    $response = ['status' => 'error', 'message' => $message];
    if ($details && (isset($_GET['debug']) || ini_get('display_errors'))) {
        $response['debug'] = $details;
    }
    send_response('error', $message, [], $http_code);
}

function send_success($message, $data = [], $http_code = 200) {
    api_log("SUCCESS: $message");
    send_response('success', $message, $data, $http_code);
}

function validate_csrf_token($token) {
    if (empty($token) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// ============ MAIN EXECUTION ============
try {
    api_log("=== API REQUEST STARTED ===");
    api_log("Method: " . $_SERVER['REQUEST_METHOD']);
    api_log("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));

    // ============ AUTHENTICATION CHECK ============
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
        send_error(
            'Unauthorized - Please login as a teacher',
            401,
            "User: " . ($_SESSION['user_id'] ?? 'none') . ", Role: " . ($_SESSION['role'] ?? 'none')
        );
    }

    $user_id = intval($_SESSION['user_id']);
    $user_name = $_SESSION['name'] ?? 'Teacher';
    api_log("Authenticated User: ID=$user_id, Name=$user_name");

    // ============ REQUEST METHOD VALIDATION ============
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Method not allowed. Use POST.', 405);
    }

    // ============ CSRF VALIDATION ============
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        send_error('Invalid security token. Please refresh the page.', 403);
    }
    api_log("CSRF token validated");

    // ============ DATABASE CONNECTION ============
    include('../includes/db_connect.php');
    include('../includes/functions.php');

    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? 'unknown error'));
    }
    api_log("Database connected");

    // ============ INPUT EXTRACTION & SANITIZATION ============
    $input = [
        'title' => sanitize_input($_POST['title'] ?? ''),
        'academic_year' => sanitize_input($_POST['academic_year'] ?? ''),
        'description' => sanitize_input($_POST['description'] ?? ''),
        'content' => trim($_POST['content'] ?? ''), // Don't sanitize HTML content yet
        'status' => sanitize_input($_POST['status'] ?? 'draft'),
        'uploaded_by' => intval($_POST['uploaded_by'] ?? 0)
    ];

    api_log("Input Data: Title='{$input['title']}', Year={$input['academic_year']}, Status={$input['status']}");
    api_log("Content Length: " . strlen($input['content']) . " characters");

    // ============ INPUT VALIDATION ============
    $validation_errors = [];

    // Title validation
    if (empty($input['title'])) {
        $validation_errors[] = 'Title is required';
    } elseif (strlen($input['title']) > 200) {
        $validation_errors[] = 'Title must not exceed 200 characters';
    } elseif (strlen($input['title']) < 5) {
        $validation_errors[] = 'Title must be at least 5 characters';
    }

    // Academic year validation
    if (empty($input['academic_year'])) {
        $validation_errors[] = 'Academic year is required';
    } elseif (!preg_match('/^\d{4}-\d{4}$/', $input['academic_year'])) {
        $validation_errors[] = 'Invalid academic year format. Expected: YYYY-YYYY';
    }

    // Content validation
    if (empty($input['content'])) {
        $validation_errors[] = 'Report content is required';
    } elseif (strlen($input['content']) < 50) {
        $validation_errors[] = 'Report content must be at least 50 characters';
    }

    // Status validation
    if (!in_array($input['status'], ['draft', 'pending'])) {
        $validation_errors[] = 'Invalid status. Use "draft" or "pending"';
    }

    // User ownership validation
    if ($user_id !== $input['uploaded_by']) {
        $validation_errors[] = 'Cannot submit reports on behalf of other users';
        api_log("SECURITY ALERT: User $user_id attempted to submit as user {$input['uploaded_by']}");
    }

    if (!empty($validation_errors)) {
        send_error(
            'Validation failed: ' . implode('; ', $validation_errors),
            400,
            json_encode($validation_errors)
        );
    }

    api_log("Validation passed");

    // ============ FILE UPLOAD HANDLING ============
    $file_data = null;

    if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['report_file'];
        api_log("Processing file upload: {$file['name']} ({$file['size']} bytes)");

        // Check upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'PHP extension stopped the upload'
            ];
            $error_msg = $upload_errors[$file['error']] ?? 'Unknown upload error';
            send_error("File upload failed: $error_msg", 400);
        }

        // Validate file size
        if ($file['size'] > MAX_FILE_SIZE) {
            send_error('File size exceeds 10MB limit. Current size: ' . round($file['size'] / 1024 / 1024, 2) . 'MB', 400);
        }

        if ($file['size'] === 0) {
            send_error('Uploaded file is empty', 400);
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, ALLOWED_FILE_TYPES)) {
            send_error("Invalid file type: $mime_type. Only PDF, DOC, and DOCX files are allowed.", 400);
        }

        // Validate file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, ALLOWED_EXTENSIONS)) {
            send_error("Invalid file extension: .$file_extension. Allowed: " . implode(', ', ALLOWED_EXTENSIONS), 400);
        }

        // Create upload directory if needed
        if (!is_dir(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
            api_log("Created upload directory: " . UPLOAD_DIR);
        }

        // Generate secure filename
        $safe_original_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $unique_filename = sprintf(
            'report_%d_%s_%s.%s',
            $user_id,
            date('Ymd_His'),
            bin2hex(random_bytes(6)),
            $file_extension
        );
        $full_path = UPLOAD_DIR . $unique_filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $full_path)) {
            throw new Exception('Failed to save uploaded file to ' . $full_path);
        }

        // Verify file was written
        if (!file_exists($full_path)) {
            throw new Exception('File upload verification failed');
        }

        $file_data = [
            'path' => 'uploads/reports/' . $unique_filename,
            'original_name' => sanitize_input($file['name']),
            'size' => $file['size'],
            'type' => $mime_type,
            'extension' => $file_extension
        ];

        api_log("File uploaded successfully: {$file_data['path']}");
    }

    // ============ DATABASE TRANSACTION ============
    $conn->begin_transaction();

    try {
        // Prepare SQL query
        if ($file_data) {
            $sql = "INSERT INTO annual_reports 
                    (title, academic_year, description, content, file_path, uploaded_by, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sssssis",
                $input['title'],
                $input['academic_year'],
                $input['description'],
                $input['content'],
                $file_data['path'],
                $user_id,
                $input['status']
            );
        } else {
            $sql = "INSERT INTO annual_reports 
                    (title, academic_year, description, content, uploaded_by, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssis",
                $input['title'],
                $input['academic_year'],
                $input['description'],
                $input['content'],
                $user_id,
                $input['status']
            );
        }

        if (!$stmt->execute()) {
            throw new Exception("Database insert failed: " . $stmt->error);
        }

        $report_id = $stmt->insert_id;
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        if ($affected_rows === 0) {
            throw new Exception("No rows inserted into database");
        }

        api_log("Report created successfully: ID=$report_id, Rows affected=$affected_rows");

        // ============ ACTIVITY LOGGING ============
        if (function_exists('log_action')) {
            $activity_message = sprintf(
                "Created report: '%s' (Status: %s, Year: %s)",
                $input['title'],
                $input['status'],
                $input['academic_year']
            );
            log_action($conn, $user_id, $activity_message, 'teacher');
            api_log("Activity logged: $activity_message");
        }

        // ============ NOTIFICATIONS ============
        if ($input['status'] === 'pending' && function_exists('notify_user')) {
            $notification_message = sprintf(
                "📄 New report submitted by %s: '%s' (Academic Year: %s)",
                $user_name,
                $input['title'],
                $input['academic_year']
            );
            
            // Get all admin users
            $admin_query = $conn->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
            if ($admin_query && $admin_query->num_rows > 0) {
                while ($admin = $admin_query->fetch_assoc()) {
                    notify_user($conn, $admin['id'], $notification_message);
                }
                api_log("Notifications sent to admins");
            }
        }

        // ============ COMMIT TRANSACTION ============
        $conn->commit();
        api_log("Transaction committed successfully");

        // ============ SUCCESS RESPONSE ============
        $success_message = $input['status'] === 'pending'
            ? '✅ Report submitted for approval successfully!'
            : '✅ Report saved as draft successfully!';

        $response_data = [
            'report_id' => $report_id,
            'status' => $input['status'],
            'title' => $input['title'],
            'academic_year' => $input['academic_year'],
            'has_attachment' => $file_data !== null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        if ($file_data) {
            $response_data['file'] = [
                'name' => $file_data['original_name'],
                'size' => round($file_data['size'] / 1024, 2) . ' KB',
                'type' => $file_data['extension']
            ];
        }

        send_success($success_message, $response_data, 201);

    } catch (Exception $e) {
        // ============ ROLLBACK ON ERROR ============
        $conn->rollback();
        api_log("Transaction rolled back: " . $e->getMessage());

        // Clean up uploaded file
        if ($file_data && file_exists('../' . $file_data['path'])) {
            if (unlink('../' . $file_data['path'])) {
                api_log("Cleaned up uploaded file after transaction failure");
            } else {
                api_log("WARNING: Failed to clean up file: " . $file_data['path']);
            }
        }

        throw $e; // Re-throw to be caught by outer try-catch
    }

} catch (Exception $e) {
    // ============ GLOBAL ERROR HANDLER ============
    api_log("CRITICAL ERROR: " . $e->getMessage());
    api_log("Stack trace: " . $e->getTraceAsString());
    
    send_error(
        'An unexpected error occurred. Please try again or contact support.',
        500,
        $e->getMessage()
    );
} finally {
    // ============ CLEANUP ============
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
        api_log("Database connection closed");
    }
    api_log("=== API REQUEST COMPLETED ===\n");
}
?>
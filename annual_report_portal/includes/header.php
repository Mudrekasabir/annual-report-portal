<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* -------------------------------------------------------------
   SMART BASE PATH DETECTION
------------------------------------------------------------- */
$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, "/admin/") !== false) {
  $basePath = "../";
} elseif (strpos($uri, "/teacher/") !== false) {
  $basePath = "../";
} elseif (strpos($uri, "/student/") !== false) {
  $basePath = "../";
} else {
  $basePath = "";
}

// Get unread notification count if logged in
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
  require_once $basePath . 'includes/db_connect.php';
  $user_id = $_SESSION['user_id'];
  $stmt_notif = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
  if ($stmt_notif) {
    $stmt_notif->bind_param("i", $user_id);
    $stmt_notif->execute();
    $result_notif = $stmt_notif->get_result();
    $unread_count = $result_notif->fetch_assoc()['count'];
    $stmt_notif->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Annual Report Portal</title>

  <!-- BOOTSTRAP + ICONS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- FIXED — Beige Theme CSS (correct folder) -->
  <link href="<?= $basePath ?>styles/style.css" rel="stylesheet">
  
  <style>
    body {
      font-family: 'Inter', sans-serif;
    }
    
    /* Enhanced Navbar */
    .navbar {
      backdrop-filter: blur(10px);
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .navbar-brand {
      font-size: 1.3rem;
      letter-spacing: -0.5px;
      transition: transform 0.3s ease;
    }
    
    .navbar-brand:hover {
      transform: scale(1.05);
    }
    
    /* Notification Bell Animation */
    .notification-bell {
      position: relative;
      transition: all 0.3s ease;
    }
    
    .notification-bell:hover {
      transform: scale(1.1);
    }
    
    .notification-bell.has-unread {
      animation: ring 2s ease-in-out infinite;
    }
    
    @keyframes ring {
      0%, 100% { transform: rotate(0deg); }
      10%, 30% { transform: rotate(-10deg); }
      20%, 40% { transform: rotate(10deg); }
    }
    
    .notification-badge {
      font-size: 0.65rem;
      padding: 2px 5px;
      animation: pulse 2s ease-in-out infinite;
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.1); }
    }
    
    /* Enhanced Dropdown */
    .dropdown-menu {
      border: none;
      box-shadow: 0 4px 20px rgba(0,0,0,0.15);
      border-radius: 12px;
      padding: 0;
      overflow: hidden;
      min-width: 320px;
    }
    
    .dropdown-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      font-weight: 600;
      padding: 12px 20px;
      border-bottom: none;
    }
    
    .dropdown-item {
      padding: 12px 20px;
      transition: all 0.2s ease;
      border-left: 3px solid transparent;
    }
    
    .dropdown-item:hover {
      background: #f8f9fa;
      border-left-color: #667eea;
      transform: translateX(3px);
    }
    
    .dropdown-item.unread {
      background: #f0f7ff;
      border-left-color: #667eea;
    }
    
    .dropdown-divider {
      margin: 0;
    }
    
    /* User Info Badge */
    .user-info-dropdown .dropdown-toggle {
      background: rgba(255,255,255,0.1);
      border-radius: 25px;
      padding: 6px 15px;
      transition: all 0.3s ease;
    }
    
    .user-info-dropdown .dropdown-toggle:hover {
      background: rgba(255,255,255,0.2);
    }
    
    .role-badge {
      font-size: 0.7rem;
      padding: 3px 8px;
      border-radius: 10px;
      font-weight: 600;
    }
    
    .coordinator-indicator {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      padding: 4px 10px;
      border-radius: 15px;
      font-size: 0.75rem;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      animation: shimmer 3s infinite;
    }
    
    @keyframes shimmer {
      0%, 100% { box-shadow: 0 0 10px rgba(102, 126, 234, 0.5); }
      50% { box-shadow: 0 0 20px rgba(102, 126, 234, 0.8); }
    }
    
    /* Theme Toggle Enhancement */
    #toggleTheme {
      border-radius: 20px;
      transition: all 0.3s ease;
    }
    
    #toggleTheme:hover {
      transform: rotate(180deg);
      background: rgba(255,255,255,0.2) !important;
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
      .dropdown-menu {
        min-width: 280px;
      }
      
      .navbar-brand {
        font-size: 1.1rem;
      }
    }
    
    /* Notification Item Styling */
    .notif-item {
      border-bottom: 1px solid #f0f0f0;
      cursor: pointer;
    }
    
    .notif-item:last-child {
      border-bottom: none;
    }
    
    .notif-item .notif-message {
      font-size: 0.9rem;
      line-height: 1.4;
      margin-bottom: 4px;
    }
    
    .notif-item .notif-time {
      font-size: 0.75rem;
      color: #6c757d;
    }
    
    .mark-read-btn {
      font-size: 0.75rem;
      padding: 4px 10px;
      border-radius: 12px;
    }
    
    /* Empty State */
    .empty-notifications {
      text-align: center;
      padding: 30px 20px;
      color: #6c757d;
    }
    
    .empty-notifications i {
      font-size: 3rem;
      opacity: 0.3;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>

<link rel="manifest" href="<?= $basePath ?>manifest.json">

<!-- ===========================================================
                         NAVBAR
============================================================== -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">

    <a class="navbar-brand fw-bold" href="<?= $basePath ?>index.php">
      <i class="bi bi-file-earmark-text"></i> Annual Report Portal
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto align-items-center">

        <!-- Notifications -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <li class="nav-item dropdown me-3">
          <a class="nav-link position-relative notification-bell <?= $unread_count > 0 ? 'has-unread' : '' ?>" 
             data-bs-toggle="dropdown" 
             href="#" 
             aria-label="Notifications">
            <i class="bi bi-bell fs-5"></i>
            <?php if ($unread_count > 0): ?>
            <span id="notifCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
              <?= $unread_count > 9 ? '9+' : $unread_count ?>
            </span>
            <?php else: ?>
            <span id="notifCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" style="display:none">0</span>
            <?php endif; ?>
          </a>

          <ul class="dropdown-menu dropdown-menu-end" id="notifMenu">
            <li class="dropdown-header">
              <div class="d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bell-fill"></i> Notifications</span>
                <?php if ($unread_count > 0): ?>
                <button class="btn btn-sm btn-light mark-read-btn" onclick="markAllAsRead()">
                  Mark all read
                </button>
                <?php endif; ?>
              </div>
            </li>
            <li><hr class="dropdown-divider m-0"></li>
            <div id="notifList">
              <li class="dropdown-item text-muted small text-center py-3">
                <i class="bi bi-hourglass-split"></i> Loading notifications...
              </li>
            </div>
          </ul>
        </li>
        <?php endif; ?>

        <!-- Theme Toggle -->
        <li class="nav-item me-3">
          <button class="btn btn-sm btn-dark" id="toggleTheme" aria-label="Toggle theme">
            <i class="bi bi-moon" id="themeIcon"></i>
          </button>
        </li>

        <!-- Dynamic navigation -->
        <?php if (!isset($_SESSION['user_id'])): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= $basePath ?>login.php">
              <i class="bi bi-box-arrow-in-right"></i> Login
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= $basePath ?>signup.php">
              <i class="bi bi-person-plus"></i> Signup
            </a>
          </li>

        <?php else: ?>
          <!-- Admin -->
          <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?= $basePath ?>admin/dashboard.php">
                <i class="bi bi-speedometer2"></i> Admin Dashboard
              </a>
            </li>

          <!-- Teacher/Coordinator -->
          <?php elseif ($_SESSION['role'] === 'teacher' || $_SESSION['role'] === 'coordinator'): ?>
            <li class="nav-item me-2">
              <a class="nav-link" href="<?= $basePath ?>teacher/dashboard.php">
                <i class="bi bi-<?= $_SESSION['role'] === 'coordinator' ? 'shield-check' : 'person-workspace' ?>"></i>
                <?= $_SESSION['role'] === 'coordinator' ? 'Coordinator' : 'Teacher' ?> Dashboard
              </a>
            </li>
            <?php if ($_SESSION['role'] === 'coordinator'): ?>
            <li class="nav-item me-2">
              <span class="coordinator-indicator">
                <i class="bi bi-star-fill"></i>
                <span>Coordinator Mode</span>
              </span>
            </li>
            <?php endif; ?>

          <!-- Student -->
          <?php elseif ($_SESSION['role'] === 'student'): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?= $basePath ?>student/dashboard.php">
                <i class="bi bi-mortarboard"></i> Student Dashboard
              </a>
            </li>
          <?php endif; ?>

          <!-- User Info Dropdown -->
          <li class="nav-item dropdown user-info-dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle fs-5"></i>
              <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li class="dropdown-header">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-person-circle fs-4"></i>
                  <div>
                    <div class="fw-bold"><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></div>
                    <small class="opacity-75"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></small>
                  </div>
                </div>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <span class="dropdown-item-text small">
                  <i class="bi bi-award"></i> Role: 
                  <span class="role-badge bg-<?= $_SESSION['role'] === 'admin' ? 'danger' : ($_SESSION['role'] === 'coordinator' ? 'purple' : ($_SESSION['role'] === 'teacher' ? 'primary' : 'success')) ?>">
                    <?= htmlspecialchars(ucfirst($_SESSION['role'])) ?>
                  </span>
                </span>
              </li>
              <li>
                <a class="dropdown-item text-danger" href="<?= $basePath ?>logout.php">
                  <i class="bi bi-box-arrow-right"></i> Logout
                </a>
              </li>
            </ul>
          </li>

        <?php endif; ?>

      </ul>
    </div>

  </div>
</nav>

<!-- CONTENT START -->
<div class="container mt-4">


<!-- ===========================
      NOTIFICATIONS SCRIPT
============================= -->
<script>
let notificationRefreshInterval;

function loadNotifications() {
    fetch("<?= $basePath ?>includes/fetch_notifications.php")
      .then(r => r.json())
      .then(data => {
          const listContainer = document.getElementById("notifList");
          const badge = document.getElementById("notifCount");
          const bell = document.querySelector(".notification-bell");

          if (!data.success) {
              listContainer.innerHTML = '<li class="dropdown-item text-danger small">Error loading notifications</li>';
              return;
          }

          // Clear existing content
          listContainer.innerHTML = "";
          
          if (data.notifications.length === 0) {
              listContainer.innerHTML = `
                <li class="empty-notifications">
                  <i class="bi bi-bell-slash"></i>
                  <div>No notifications</div>
                </li>
              `;
              badge.style.display = "none";
              bell.classList.remove('has-unread');
              return;
          }

          // Display notifications
          data.notifications.forEach((n, index) => {
              const isUnread = n.is_read == 0;
              const notifItem = document.createElement('li');
              notifItem.className = `dropdown-item notif-item ${isUnread ? 'unread' : ''}`;
              notifItem.innerHTML = `
                <div class="notif-message ${isUnread ? 'fw-bold' : ''}">
                  ${escapeHtml(n.message)}
                </div>
                <div class="notif-time">
                  <i class="bi bi-clock"></i> ${n.created_at}
                </div>
              `;
              
              // Mark as read on click
              if (isUnread) {
                notifItem.onclick = () => markAsRead(n.id);
              }
              
              listContainer.appendChild(notifItem);
          });

          // Update badge
          if (data.count > 0) {
              badge.innerText = data.count > 9 ? '9+' : data.count;
              badge.style.display = "inline-block";
              bell.classList.add('has-unread');
          } else {
              badge.style.display = "none";
              bell.classList.remove('has-unread');
          }
      })
      .catch(err => {
          console.error("Notification error:", err);
          document.getElementById("notifList").innerHTML = 
            '<li class="dropdown-item text-danger small">Connection error</li>';
      });
}

function markAsRead(notificationId) {
    fetch("<?= $basePath ?>includes/mark_notification_read.php", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notification_id: notificationId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    })
    .catch(err => console.error("Mark read error:", err));
}

function markAllAsRead() {
    fetch("<?= $basePath ?>includes/mark_all_notifications_read.php", {
        method: 'POST'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    })
    .catch(err => console.error("Mark all read error:", err));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

<?php if (isset($_SESSION['user_id'])): ?>
  // Load immediately
  loadNotifications();
  
  // Refresh every 15 seconds
  notificationRefreshInterval = setInterval(loadNotifications, 15000);
  
  // Stop refreshing when page is hidden (save bandwidth)
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
      clearInterval(notificationRefreshInterval);
    } else {
      loadNotifications();
      notificationRefreshInterval = setInterval(loadNotifications, 15000);
    }
  });
<?php endif; ?>
</script>



<!-- ===========================
        THEME TOGGLE
============================= -->
<script>
const root = document.documentElement;
const btn = document.getElementById("toggleTheme");
const icon = document.getElementById("themeIcon");

// Load saved theme
let theme = localStorage.getItem("theme") || "light";
root.setAttribute("data-theme", theme);
icon.className = theme === "dark" ? "bi bi-sun-fill" : "bi bi-moon-stars-fill";

btn.addEventListener("click", () => {
    theme = theme === "light" ? "dark" : "light";
    root.setAttribute("data-theme", theme);
    localStorage.setItem("theme", theme);
    icon.className = theme === "dark" ? "bi bi-sun-fill" : "bi bi-moon-stars-fill";
});
</script>
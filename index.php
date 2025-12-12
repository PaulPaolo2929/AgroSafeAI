<?php
// START OF INDEX.PHP
session_start();

// 1. Security Check: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Personalize the Experience
$user_name = $_SESSION['username'] ?? 'Farmer';

// Load shared configuration
require_once __DIR__ . '/includes/config.php';

// Determine which page to load (Default to dashboard)
$page = $_GET['page'] ?? 'dashboard';
$allowed_pages = ['dashboard', 'market', 'history'];

if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroSafeAI - <?php echo ucfirst($page); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <nav class="sidebar">
        <div class="brand">
            <i class="fas fa-leaf"></i> AgroSafeAI
        </div>
        
        <div class="nav-links">
            <a href="index.php?page=dashboard" class="nav-link <?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="index.php?page=market" class="nav-link <?php echo ($page == 'market') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Market Data
            </a>
            <a href="index.php?page=history" class="nav-link <?php echo ($page == 'history') ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> History Log
            </a>
            <a href="logout.php" class="nav-link text-danger mt-3">
                <i class="fas fa-sign-out-alt"></i> Logout
</a>
        </div>

        <div class="mt-auto pt-5">
            <div class="alert alert-success border-0" style="background: #e8f5e9; font-size: 0.85rem;">
                <i class="fas fa-check-circle me-1"></i> System Online
            </div>
        </div>
    </nav>

    <main class="main-content">
        <?php 
            // Dynamically include the requested page
            include __DIR__ . "/pages/{$page}.php"; 
        ?>
    </main>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    $(document).ready(function() {
    $('#symptom-select').select2({
        theme: "bootstrap-5",
        placeholder: "Type to search (e.g., 'spots')",
        allowClear: true,
        width: '100%' 
    });
});
</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
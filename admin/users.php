<?php
// admin/users.php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// DB Connection
$host = 'localhost'; $db = 'agrosafe_db'; $u = 'root'; $p = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $u, $p);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("DB Error"); }

// --- SET PAGE NAME FOR ACTIVE SIDEBAR LINK ---
$page_name = 'users';

// DELETE USER FUNCTION
if (isset($_POST['delete_user'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$_POST['user_id']]);
    $msg = "User deleted successfully.";
}

// FETCH USERS
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --admin-bg: #f4f7fe;
            --sidebar-width: 260px;
            --sidebar-bg: #111c44;
            --primary: #4318FF;
            --text-dark: #2b3674;
        }
        body { background-color: var(--admin-bg); font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* SIDEBAR (Unified Styling) */
        .sidebar {
            width: var(--sidebar-width); height: 100vh; position: fixed; top: 0; left: 0;
            background: var(--sidebar-bg); color: white; padding: 24px;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .brand { font-size: 1.5rem; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; letter-spacing: 1px; }
        .nav-link { 
            color: #a3aed0; padding: 14px 10px; margin-bottom: 5px; border-radius: 10px; 
            font-weight: 500; display: flex; align-items: center; gap: 12px; text-decoration: none; transition: 0.2s;
        }
        .nav-link:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-link.active { background: rgba(255,255,255,0.1); color: white; border-right: 4px solid var(--primary); border-radius: 10px 0 0 10px; }
        .nav-link i { width: 20px; }

        .main-content { margin-left: var(--sidebar-width); padding: 30px; }
        .card-custom { background: white; border: none; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 24px; }
        .badge-role { background: #e8f5e9; color: #2ecc71; padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
        .security-alert { border-left: 5px solid #e74c3c; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="brand"><i class="fas fa-leaf text-success me-2"></i> AGRO<span class="text-white">SAFE</span></div>
        <small class="fw-bold text-uppercase text-light mb-4 d-block opacity-75" style="font-size:0.7rem;">Admin Panel</small>
        <div class="nav flex-column">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-grid-"></i> Dashboard Overview</a>
            <a href="users.php" class="nav-link active"><i class="fas fa-users"></i> Farmer Management</a>
            <a href="scans.php" class="nav-link"><i class="fas fa-database"></i> Scan History</a>
            <a href="settings.php" class="nav-link"><i class="fas fa-sliders-h"></i> System Settings</a>
            <a href="security.php" class="nav-link"><i class="fas fa-lock"></i> Security & Privacy</a>
            <div style="margin-top: auto; padding-top: 100px;">
                <a href="../logout.php?redirect=admin" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-dark">User Management</h3>
            <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo count($users); ?> Total Users</span>
        </div>

        <?php if(isset($msg)): ?>
            <div class="alert alert-success rounded-3 shadow-sm border-0"><i class="fas fa-check-circle me-2"></i><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <div class="alert alert-danger security-alert shadow-sm rounded-3 mb-4">
            <h6 class="fw-bold text-danger mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Security Note: Data Visibility</h6>
            <p class="small mb-0">Emails are displayed as a **Non-Reversible Hash Token** to protect privacy. Usernames are stored in plain text for essential application display/functionality (e.g., History Logs).</p>
        </div>

        <div class="card-custom">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 py-3">ID</th>
                            <th>Profile Name (Functional Display)</th>
                            <th>Secure Email Token (SHA-256)</th>
                            <th>Role</th>
                            <th>Join Date</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-secondary">#<?php echo $user['id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;font-weight:800;">
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    </div>
                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($user['username']); ?></span>
                                </div>
                            </td>
                            <td class="text-muted small" style="word-break: break-all; font-size: 0.75rem;">
                                <?php 
                                    // Use SHA256 as the non-reversible, secure hash token
                                    echo hash('sha256', $user['email']); 
                                ?>
                            </td>
                            <td><span class="badge-role">FARMER</span></td>
                            <td class="text-muted small"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td class="text-end pe-3">
                                <form method="POST" onsubmit="return confirm('Permanently delete this user and ALL their data?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="delete_user" value="1">
                                    <button class="btn btn-sm btn-light text-danger border"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
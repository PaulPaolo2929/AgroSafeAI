<?php
// admin/security.php
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
$page_name = 'security'; 
$msg = "";
$error = "";

// --- FETCH CURRENT ADMIN PROFILE ---
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'admin_username'");
$current_admin_username = $stmt->fetchColumn() ?? 'admin';


// --- HANDLE FORM SUBMISSION: PROFILE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_admin_profile') {
    
    $new_username = trim($_POST['new_username']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (!empty($new_password) && $new_password !== $confirm_password) {
        $error = "New Password and Confirmation do not match.";
    } elseif (empty($new_username)) {
        $error = "Username cannot be empty.";
    } else {
        try {
            // 1. Update Username
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'admin_username'");
            $stmt->execute([$new_username]);

            // 2. Update Password (Only if provided)
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'admin_password_hash'");
                $stmt->execute([$hashed_password]);
                $msg = "Admin Username and Password updated successfully!";
            } else {
                $msg = "Admin Username updated successfully! Password unchanged.";
            }
            
            $current_admin_username = $new_username; // Update display name
        
        } catch (Exception $e) {
            $error = "Database error during update.";
        }
    }
}


// --- AUDIT TRAIL SIMULATION ---
$audit_logs = [
    ["time"=>"2025-12-13 10:05", "user"=>"admin", "action"=>"Updated Market Prices", "result"=>"Success"],
    ["time"=>"2025-12-13 09:40", "user"=>"user_401", "action"=>"Account created", "result"=>"Success"],
    ["time"=>"2025-12-12 23:15", "user"=>"dev_admin", "action"=>"Accessed User Table", "result"=>"Success"],
    ["time"=>"2025-12-12 18:30", "user"=>"user_102", "action"=>"Failed login attempt (3x)", "result"=>"Warning"],
];
$test_user_id = 1; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security & Privacy - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7fe; font-family: 'Plus Jakarta Sans', sans-serif; }
        .sidebar { width: 260px; height: 100vh; position: fixed; background: #111c44; color: white; padding: 24px; }
        .main-content { margin-left: 260px; padding: 30px; }
        .nav-link { color: #a3aed0; padding: 14px 10px; margin-bottom: 5px; border-radius: 10px; font-weight: 500; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; border-right: 4px solid #4318FF; }
        .card-custom { background: white; border: none; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 30px; }
        .log-table th { font-weight: 700; color: #7f8c8d; font-size: 0.8rem; }
        .log-table td { font-size: 0.9rem; }
        .btn-action { width: 100%; border-radius: 10px; font-weight: 700; }
        .alert-item { background: #f8f9fa; border-radius: 8px; padding: 10px 15px; margin-bottom: 10px; border-left: 4px solid #f1c40f; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="brand"><i class="fas fa-leaf text-success me-2"></i> AGRO<span class="text-white">SAFE</span></div>
        <small class="fw-bold text-uppercase text-light mb-4 d-block opacity-75" style="font-size:0.7rem;">Admin Panel</small>
        <div class="nav flex-column">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-grid-2"></i> Dashboard Overview</a>
            <a href="users.php" class="nav-link"><i class="fas fa-users"></i> Farmer Management</a>
            <a href="scans.php" class="nav-link"><i class="fas fa-database"></i> Scan History</a>
            <a href="settings.php" class="nav-link"><i class="fas fa-sliders-h"></i> System Settings</a>
            <a href="security.php" class="nav-link active"><i class="fas fa-lock"></i> Security & Privacy</a>
            <div style="margin-top: auto; padding-top: 100px;">
                <a href="../logout.php?redirect=admin" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <h3 class="fw-bold mb-4">Security & Compliance Center</h3>

        <?php if($msg): ?>
            <div class="alert alert-success d-flex align-items-center rounded-3"><i class="fas fa-check-circle me-2"></i> <?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger d-flex align-items-center rounded-3"><i class="fas fa-times-circle me-2"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row g-4 mb-5">
            
            <div class="col-lg-4">
                <div class="card-custom">
                    <h5 class="fw-bold mb-4"><i class="fas fa-user-circle me-2 text-primary"></i>Admin Profile</h5>
                    <p class="small text-muted mb-4">Update your login credentials (Username is for display and login).</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_admin_profile">
                        
                        <div class="mb-3">
                            <label class="form-label small text-muted">Current Username</label>
                            <input type="text" name="new_username" class="form-control" value="<?php echo htmlspecialchars($current_admin_username); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted">New Password (Leave blank to keep current)</label>
                            <input type="password" name="new_password" class="form-control" placeholder="••••••••">
                        </div>

                        <div class="mb-4">
                            <label class="form-label small text-muted">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••">
                        </div>

                        <button type="submit" class="btn btn-primary btn-action py-2">
                            <i class="fas fa-save me-2"></i>Save Credentials
                        </button>
                    </form>
                    
                </div>
                
                <div class="card-custom mt-4">
                    <h5 class="fw-bold mb-4"><i class="fas fa-user-lock me-2 text-primary"></i>Data Privacy & Portability</h5>
                    
                    <p class="small text-muted mb-4">Manage requests for personal data deletion and export.</p>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Data Deletion (Right to be Forgotten)</label>
                            <input type="hidden" name="action" value="delete_user_data">
                            <button type="submit" class="btn btn-sm btn-outline-danger btn-action" onclick="return confirm('WARNING: Permanently delete ALL data for User ID <?php echo $test_user_id; ?>?');">
                                <i class="fas fa-eraser me-2"></i>Trigger Data Deletion
                            </button>
                            <p class="small text-danger mt-1">This includes scans, images, and user history.</p>
                        </div>
                    </form>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Data Export (GDPR/CCPA)</label>
                            <input type="hidden" name="action" value="download_data">
                            <button type="submit" class="btn btn-sm btn-outline-primary btn-action">
                                <i class="fas fa-download me-2"></i>Download User Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                 <div class="card-custom">
                    <h5 class="fw-bold mb-4"><i class="fas fa-file-contract me-2 text-dark"></i>Legal Pages Manager</h5>
                    <p class="small text-muted mb-4">You must inform users what data you collect (Email, Location, Scans, Images) before using the service. </p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="save_legal">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Privacy Policy (Last Updated: <?php echo date('M d'); ?>)</label>
                                <textarea class="form-control" rows="6" placeholder="[Template] We collect email, password (hashed), and location data for weather services..."></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Terms of Service</label>
                                <textarea class="form-control" rows="6" placeholder="[Template] Users grant us license to use uploaded images for AI training purposes..."></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success mt-4 px-4 py-2 fw-bold"><i class="fas fa-save me-2"></i>Save Legal Documents</button>
                    </form>
                </div>

                <div class="card-custom mt-4">
                    <h5 class="fw-bold mb-4"><i class="fas fa-clipboard-list me-2 text-dark"></i>System Audit Trail (Last 6 Events)</h5>
                    <div class="table-responsive">
                        <table class="table table-custom table-hover align-middle log-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action Performed</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($audit_logs as $log): ?>
                                <tr>
                                    <td class="text-muted"><?php echo $log['time']; ?></td>
                                    <td class="fw-bold"><?php echo $log['user']; ?></td>
                                    <td><?php echo $log['action']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo strtolower($log['result']); ?> bg-opacity-10 text-<?php echo strtolower($log['result']); ?>">
                                            <?php echo $log['result']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
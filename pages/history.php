<?php
// pages/history.php

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];

// 2. Database Connection
$host = 'localhost'; $db = 'agrosafe_db'; $u = 'root'; $p = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $u, $p);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// 3. Handle Delete Request (New Feature)
if (isset($_POST['delete_id'])) {
    $del_id = (int)$_POST['delete_id'];
    
    // Security: Ensure the ID actually belongs to the logged-in user!
    $stmt = $pdo->prepare("DELETE FROM history WHERE id = ? AND user_id = ?");
    $stmt->execute([$del_id, $user_id]);
    
    // Refresh to show changes
    echo "<script>window.location.href='index.php?page=history';</script>";
    exit;
}

// 4. Fetch History
$stmt = $pdo->prepare("SELECT * FROM history WHERE user_id = ? ORDER BY date DESC");
$stmt->execute([$user_id]);
$full_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="custom-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold m-0"><i class="fas fa-history text-primary me-2"></i>My Scan History</h4>
            <small class="text-muted">You have <?php echo count($full_history); ?> saved reports.</small>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
            <i class="fas fa-sync"></i> Refresh
        </button>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="bg-light">
                <tr>
                    <th class="border-0 rounded-start ps-4">Date Logged</th>
                    <th class="border-0">Symptom</th>
                    <th class="border-0">Diagnosis</th>
                    <th class="border-0">Est. ROI</th>
                    <th class="border-0 rounded-end text-end pe-4">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($full_history)): ?>
                    <?php foreach ($full_history as $log): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($log['date'])); ?></div>
                                <div class="small text-muted"><?php echo date('h:i A', strtotime($log['date'])); ?></div>
                            </td>
                            
                            <td>
                                <span class="text-capitalize"><?php echo htmlspecialchars($log['symptom']); ?></span>
                            </td>
                            
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
                                    <?php echo htmlspecialchars($log['diagnosis']); ?>
                                </span>
                            </td>
                            
                            <td class="fw-bold <?php echo ($log['roi'] >= 0) ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($log['roi'] >= 0 ? '+' : '') . '$' . number_format($log['roi'], 2); ?>
                            </td>

                            <td class="text-end pe-4">
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this record?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $log['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-light text-danger border-0 hover-shadow">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <div class="text-muted opacity-50 mb-2">
                                <i class="fas fa-folder-open fa-3x"></i>
                            </div>
                            <p class="text-muted fw-bold">No records found.</p>
                            <a href="index.php?page=dashboard" class="btn btn-primary btn-sm rounded-pill px-4">Start New Scan</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
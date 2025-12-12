<?php
// pages/history.php

if (!isset($_SESSION['user_id'])) { die("Access Denied"); }
$user_id = $_SESSION['user_id'];

// Database Connection
$host = 'localhost'; $db = 'agrosafe_db'; $u = 'root'; $p = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $u, $p);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("DB Error"); }

// --- HANDLE ACTIONS (Standard PHP) ---
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $stmt = $pdo->prepare("UPDATE history SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['new_status'], $_POST['record_id'], $user_id]);
        echo "<script>window.location.href='index.php?page=history&msg=updated';</script>";
        exit;
    }
    if ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM history WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['record_id'], $user_id]);
        echo "<script>window.location.href='index.php?page=history&msg=deleted';</script>";
        exit;
    }
    if ($_POST['action'] === 'save_note') {
        $stmt = $pdo->prepare("UPDATE history SET notes = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['note'], $_POST['record_id'], $user_id]);
        echo "<script>window.location.href='index.php?page=history&msg=saved';</script>";
        exit;
    }
}

// --- FETCH DATA ---
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT * FROM history WHERE user_id = ?";
if ($filter === 'pending') { $sql .= " AND status = 'Pending'"; }
if ($filter === 'resolved') { $sql .= " AND status = 'Resolved'"; }
$sql .= " ORDER BY date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats for HUD
// We run a separate query to get totals regardless of the filter
$statStmt = $pdo->prepare("SELECT status, roi FROM history WHERE user_id = ?");
$statStmt->execute([$user_id]);
$all_rows = $statStmt->fetchAll(PDO::FETCH_ASSOC);

$pending = 0; $resolved = 0; $savings = 0;
foreach ($all_rows as $h) {
    if ($h['status'] === 'Pending' || $h['status'] === 'In Progress') $pending++;
    if ($h['status'] === 'Resolved') $resolved++;
    $savings += $h['roi'];
}
?>

<style>
    .avatar-initials {
        width: 40px; height: 40px;
        background: #e9ecef; color: #495057;
        border-radius: 50%; display: flex;
        align-items: center; justify-content: center;
        font-weight: 700; font-size: 0.9rem;
    }
    .status-badge {
        padding: 6px 12px; border-radius: 20px;
        font-size: 0.75rem; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    .status-Pending { background: #fff3cd; color: #856404; }
    .status-InProgress { background: #cff4fc; color: #055160; }
    .status-Resolved { background: #d1e7dd; color: #0f5132; }
    
    .table-hover tbody tr:hover {
        background-color: rgba(0,0,0,0.015);
        cursor: pointer;
        transform: scale(1.001); transition: all 0.2s;
    }
    .stat-card {
        border: none; border-radius: 16px;
        background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        transition: transform 0.2s;
    }
    .stat-card:hover { transform: translateY(-3px); }
</style>

<div class="container-fluid px-0 animate-fade-in">

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card p-4 d-flex align-items-center justify-content-between border-start border-4 border-primary">
                <div><small class="text-uppercase text-muted fw-bold">Active Cases</small><h2 class="fw-bold text-dark m-0"><?php echo $pending; ?></h2></div>
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle"><i class="fas fa-clipboard-list fa-lg"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4 d-flex align-items-center justify-content-between border-start border-4 border-success">
                <div><small class="text-uppercase text-muted fw-bold">Resolved</small><h2 class="fw-bold text-success m-0"><?php echo $resolved; ?></h2></div>
                <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle"><i class="fas fa-check-circle fa-lg"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4 d-flex align-items-center justify-content-between border-start border-4 border-info">
                <div><small class="text-uppercase text-muted fw-bold">Value Preserved</small><h2 class="fw-bold text-info m-0">₱<?php echo number_format($savings); ?></h2></div>
                <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle"><i class="fas fa-chart-line fa-lg"></i></div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h4 class="fw-bold m-0 text-dark">Diagnostic Records</h4><small class="text-muted">Manage your farm's disease history</small></div>
        <div class="d-flex gap-2">
            <a href="index.php?page=history&filter=all" class="btn btn-sm btn-light border <?php echo $filter=='all'?'active':''; ?>">All</a>
            <a href="index.php?page=history&filter=pending" class="btn btn-sm btn-light border <?php echo $filter=='pending'?'active':''; ?>">Pending</a>
            <a href="index.php?page=history&filter=resolved" class="btn btn-sm btn-light border <?php echo $filter=='resolved'?'active':''; ?>">Resolved</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3 text-muted text-uppercase small fw-bold">Date Logged</th>
                        <th class="py-3 text-muted text-uppercase small fw-bold">Diagnosis</th>
                        <th class="py-3 text-muted text-uppercase small fw-bold">Symptoms</th>
                        <th class="py-3 text-muted text-uppercase small fw-bold">Status</th>
                        <th class="py-3 text-muted text-uppercase small fw-bold">ROI</th>
                        <th class="pe-4 py-3 text-end text-muted text-uppercase small fw-bold">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    <?php if (count($history) > 0): ?>
                        <?php foreach ($history as $row): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($row['date'])); ?></div>
                                <div class="small text-muted"><?php echo date('h:i A', strtotime($row['date'])); ?></div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-initials me-2 text-white bg-<?php echo ($row['status']=='Resolved'?'success':'danger'); ?>">
                                        <?php echo substr($row['diagnosis'], 0, 1); ?>
                                    </div>
                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($row['diagnosis']); ?></span>
                                </div>
                            </td>
                            <td>
                                <?php 
                                    $syms = explode(',', $row['symptom']);
                                    $count = count($syms);
                                    echo '<span class="badge bg-light text-dark border me-1">'.htmlspecialchars(trim($syms[0])).'</span>';
                                    if ($count > 1) echo '<small class="text-muted">+'.($count-1).' more</small>';
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo str_replace(' ', '', $row['status']); ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td class="fw-bold text-success">+₱<?php echo number_format($row['roi']); ?></td>
                            <td class="pe-4 text-end">
                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                    View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($history as $row): ?>
<div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Case File #<?php echo $row['id']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-3">
                    <div>
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.65rem">Current Status</small>
                        <form method="POST" class="d-flex align-items-center gap-2 mt-1">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="record_id" value="<?php echo $row['id']; ?>">
                            <select name="new_status" class="form-select form-select-sm border-0 shadow-sm fw-bold text-<?php echo ($row['status']=='Resolved'?'success':'warning'); ?>" onchange="this.form.submit()">
                                <option value="Pending" <?php echo $row['status']=='Pending'?'selected':''; ?>>Pending</option>
                                <option value="In Progress" <?php echo $row['status']=='In Progress'?'selected':''; ?>>In Progress</option>
                                <option value="Resolved" <?php echo $row['status']=='Resolved'?'selected':''; ?>>Resolved</option>
                            </select>
                        </form>
                    </div>
                </div>

                <h6 class="text-uppercase text-muted small fw-bold mb-3">Diagnostic Report</h6>
                <div class="mb-3">
                    <label class="small text-muted">Identified Disease</label>
                    <div class="fs-5 fw-bold text-danger"><?php echo htmlspecialchars($row['diagnosis']); ?></div>
                </div>
                
                <h6 class="text-uppercase text-muted small fw-bold mb-3 mt-4">Field Notes</h6>
                <form method="POST">
                    <input type="hidden" name="action" value="save_note">
                    <input type="hidden" name="record_id" value="<?php echo $row['id']; ?>">
                    <div class="form-floating mb-2">
                        <textarea class="form-control bg-light border-0" placeholder="Add note" name="note" style="height: 100px"><?php echo htmlspecialchars($row['notes'] ?? ''); ?></textarea>
                        <label>Progress notes...</label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-dark w-100">Save Note</button>
                </form>

            </div>
            <div class="modal-footer border-0 pt-0">
                <form method="POST" onsubmit="return confirm('Delete this record?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="record_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" class="btn btn-link text-danger text-decoration-none btn-sm">Delete Record</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
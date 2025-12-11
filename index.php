<?php
require_once __DIR__ . '/vendor/autoload.php';
use Phpml\ModelManager;

// --- CONFIGURATION ---
$market_data = [
    'crop_value' => 12.50,
    'fungicide_cost' => 0.15,
    'labor_cost' => 20.00
];

// --- 1. HANDLE DIAGNOSIS LOGIC ---
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['symptom'])) {
    $modelManager = new ModelManager();
    
    // Load Models
    $classifier = $modelManager->restoreFromFile(__DIR__ . '/models/disease_classifier.phpml');
    $waterModel = $modelManager->restoreFromFile(__DIR__ . '/models/water_predictor.phpml');
    $fungicideModel = $modelManager->restoreFromFile(__DIR__ . '/models/fungicide_predictor.phpml');

    // Inputs
    $symptom = $_POST['symptom'];
    $farmSize = (int)$_POST['farm_size'];
    $severity = (int)$_POST['severity'];

    // Predictions
    $disease = $classifier->predict([$symptom]);
    $water = $waterModel->predict([$farmSize, $severity]);
    $fungicide = $fungicideModel->predict([$farmSize, $severity]);

    // Financials
    $loss_rate = ($severity == 2) ? 0.60 : 0.20;
    $potential_loss = ($farmSize * $market_data['crop_value']) * $loss_rate;
    $treatment_cost = ($fungicide * $market_data['fungicide_cost']) + $market_data['labor_cost'];
    $roi = $potential_loss - $treatment_cost;

    $result = [
        'disease' => $disease,
        'water' => round($water, 1),
        'fungicide' => round($fungicide, 1),
        'loss' => number_format($potential_loss, 2),
        'cost' => number_format($treatment_cost, 2),
        'roi' => number_format($roi, 2)
    ];

    // --- SAVE TO HISTORY LOG ---
    $historyFile = fopen(__DIR__ . '/data/history.csv', 'a');
    // Format: Date, Symptom, Disease, ROI
    fputcsv($historyFile, [date('Y-m-d H:i'), $symptom, $disease, $result['roi']]);
    fclose($historyFile);
}

// --- 2. LOAD HISTORY FOR DISPLAY ---
$history_log = [];
if (file_exists(__DIR__ . '/data/history.csv')) {
    if (($h = fopen(__DIR__ . '/data/history.csv', "r")) !== FALSE) {
        while (($data = fgetcsv($h, 1000, ",")) !== FALSE) {
            $history_log[] = $data;
        }
        fclose($h);
    }
    $history_log = array_reverse($history_log); // Newest first
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroSafeAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <nav class="sidebar">
        <div class="brand">
            <i class="fas fa-leaf"></i> AgroSafeAI
        </div>
        
        <div class="nav-links">
            <a href="#" class="nav-link active" data-target="dashboard">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="#" class="nav-link" data-target="market">
                <i class="fas fa-chart-line"></i> Market Data
            </a>
            <a href="#" class="nav-link" data-target="history">
                <i class="fas fa-history"></i> History Log
            </a>
        </div>

        <div class="mt-auto pt-5">
            <div class="alert alert-success border-0" style="background: #e8f5e9; font-size: 0.85rem;">
                <i class="fas fa-check-circle me-1"></i> System Online<br>
                <small class="text-muted">v2.4 Enterprise</small>
            </div>
        </div>
    </nav>

    <main class="main-content">
        
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold m-0">Welcome back, Farmer! 🌾</h2>
                <p class="text-muted">Here is your farm's health overview.</p>
            </div>
            <div class="d-flex gap-3">
                <div class="stat-box">
                    <div class="stat-icon bg-green-soft"><i class="fas fa-seedling"></i></div>
                    <div>
                        <h5 class="m-0 fw-bold"><?php echo count($history_log); ?></h5>
                        <small class="text-muted">Scans</small>
                    </div>
                </div>
            </div>
        </header>

        <section id="dashboard" class="view-section active">
            <div class="row">
                <div class="col-md-5">
                    <div class="custom-card">
                        <h5 class="mb-4 fw-bold">🔍 Start Diagnosis</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label text-muted small fw-bold">SYMPTOM</label>
                                <select name="symptom" class="form-select border-0 bg-light p-3" required>
                                    <option value="yellow leaves">Yellow Leaves</option>
                                    <option value="stunted growth">Stunted Growth</option>
                                    <option value="white powder">White Powder</option>
                                    <option value="black spots">Black Spots</option>
                                    <option value="holes in leaves">Holes in Leaves</option>
                                    <option value="wilting">Wilting</option>
                                </select>
                            </div>
                            <div class="row mb-4">
                                <div class="col-6">
                                    <label class="form-label text-muted small fw-bold">SIZE (SQM)</label>
                                    <input type="number" name="farm_size" class="form-control border-0 bg-light p-3" placeholder="100" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-muted small fw-bold">SEVERITY</label>
                                    <select name="severity" class="form-select border-0 bg-light p-3">
                                        <option value="1">Mild</option>
                                        <option value="2">Severe</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn-primary-custom shadow">Analyze Farm</button>
                        </form>
                    </div>
                </div>

                <div class="col-md-7">
                    <?php if ($result): ?>
                    <div class="custom-card bg-white border-0 h-100">
                        <div class="d-flex justify-content-between mb-4">
                            <h5 class="fw-bold text-success">Diagnosis Complete</h5>
                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Confident</span>
                        </div>
                        
                        <div class="text-center py-3">
                            <p class="text-muted mb-1">DETECTED ISSUE</p>
                            <h2 class="fw-bold display-6"><?php echo $result['disease']; ?></h2>
                        </div>

                        <div class="row mt-4 g-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 bg-light">
                                    <small class="text-muted fw-bold">TREATMENT PLAN</small>
                                    <div class="mt-2">
                                        <div>💧 <strong><?php echo $result['water']; ?> L</strong> Water</div>
                                        <div>🧪 <strong><?php echo $result['fungicide']; ?> ml</strong> Fungicide</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 bg-light">
                                    <small class="text-muted fw-bold">FINANCIAL IMPACT</small>
                                    <div class="mt-2">
                                        <div class="text-danger">Loss Risk: $<?php echo $result['loss']; ?></div>
                                        <div class="text-success fw-bold">ROI: +$<?php echo $result['roi']; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="custom-card h-100 d-flex align-items-center justify-content-center text-center text-muted">
                        <div>
                            <i class="fas fa-robot fa-3x mb-3 text-light"></i>
                            <p>AI is ready. Submit a diagnosis form.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="market" class="view-section">
            <h4 class="fw-bold mb-4">Live Market Rates</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="custom-card text-center">
                        <i class="fas fa-dollar-sign fa-2x text-warning mb-3"></i>
                        <h5>Crop Value</h5>
                        <h3 class="fw-bold">$<?php echo number_format($market_data['crop_value'], 2); ?></h3>
                        <small class="text-muted">per sqm yield</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="custom-card text-center">
                        <i class="fas fa-flask fa-2x text-info mb-3"></i>
                        <h5>Chemical Cost</h5>
                        <h3 class="fw-bold">$<?php echo number_format($market_data['fungicide_cost'], 2); ?></h3>
                        <small class="text-muted">per ml unit</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="custom-card text-center">
                        <i class="fas fa-users fa-2x text-success mb-3"></i>
                        <h5>Labor Flat Rate</h5>
                        <h3 class="fw-bold">$<?php echo number_format($market_data['labor_cost'], 2); ?></h3>
                        <small class="text-muted">per session</small>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info mt-4 rounded-3 border-0">
                <i class="fas fa-info-circle me-2"></i> Note: These rates are pulled from the configuration file. In a live production environment, this would connect to a Commodities API.
            </div>
        </section>

        <section id="history" class="view-section">
            <div class="custom-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold m-0">Scan History</h4>
                    <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()"><i class="fas fa-sync"></i> Refresh</button>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="border-0 rounded-start">Date</th>
                                <th class="border-0">Symptom</th>
                                <th class="border-0">Diagnosis</th>
                                <th class="border-0 rounded-end">Saved (ROI)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($history_log)): ?>
                                <?php foreach ($history_log as $log): ?>
                                    <?php if(count($log) >= 4): ?>
                                    <tr>
                                        <td><small class="text-muted"><?php echo $log[0]; ?></small></td>
                                        <td><?php echo htmlspecialchars($log[1]); ?></td>
                                        <td><span class="badge bg-primary bg-opacity-10 text-primary"><?php echo htmlspecialchars($log[2]); ?></span></td>
                                        <td class="fw-bold text-success">+$<?php echo htmlspecialchars($log[3]); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No history yet. Start diagnosing!</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </main>

    <script src="assets/js/app.js"></script>
</body>
</html>
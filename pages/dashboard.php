<?php
// pages/dashboard.php

// 1. SECURE AUTH & DB CONNECTION
if (!isset($_SESSION['user_id'])) { die("Access Denied"); }
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'Farmer';

// Database Config
$host = 'localhost'; $db = 'agrosafe_db'; $u = 'root'; $p = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $u, $p);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("DB Connection Error."); }

// 2. LOGIC: PREDICTION & TREATMENT MAP
use Phpml\ModelManager;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['symptom'])) {
    $modelManager = new ModelManager();
    
    // Load Models (Ensure paths are correct)
    try {
        $classifier = $modelManager->restoreFromFile(__DIR__ . '/../models/disease_classifier.phpml');
        $waterModel = $modelManager->restoreFromFile(__DIR__ . '/../models/water_predictor.phpml');
        $fungicideModel = $modelManager->restoreFromFile(__DIR__ . '/../models/fungicide_predictor.phpml');

        // Inputs
        $symptom = $_POST['symptom'];
        $farmSize = (int)$_POST['farm_size'];
        $severity = (int)$_POST['severity'];

        // Predictions
        $disease = $classifier->predict([$symptom]);
        $water = $waterModel->predict([$farmSize, $severity]);
        $fungicide_amount = $fungicideModel->predict([$farmSize, $severity]);

        // --- SPECIFIC TREATMENT DICTIONARY ---
        $treatment_map = [
            'Nitrogen deficiency' => 'Nitrogen-Rich Fertilizer (Urea)',
            'Phosphorus Deficiency' => 'Phosphorus Fertilizer (Bone Meal)',
            'Potassium Deficiency' => 'Potash / Potassium Fertilizer',
            'Iron Deficiency' => 'Chelated Iron Spray',
            'Calcium Deficiency' => 'Calcium Nitrate Spray',
            'Magnesium Deficiency' => 'Epsom Salts (Magnesium)',
            'Boron Deficiency' => 'Boron Spray',
            'Blossom end rot' => 'Calcium Spray',
            'Powdery mildew' => 'Sulfur Fungicide or Neem Oil',
            'Fungal infection' => 'Copper-Based Fungicide',
            'Leaf Spot' => 'Chlorothalonil Fungicide',
            'Rust Fungus' => 'Sulfur Dust',
            'Botrytis (Gray Mold)' => 'Bio-Fungicide (Bacillus subtilis)',
            'Early Blight' => 'Copper Fungicide',
            'Root Rot' => 'Hydrogen Peroxide Drench',
            'Scab Disease' => 'Captan Fungicide',
            'Blackleg' => 'Copper Fungicide Drench',
            'Spider Mites' => 'Miticide or Neem Oil',
            'Aphids' => 'Insecticidal Soap',
            'Whiteflies' => 'Yellow Sticky Traps + Neem Oil',
            'Thrips Damage' => 'Spinosad Spray',
            'Leaf Miners' => 'Spinosad or Neem Oil',
            'Nematodes' => 'Nematicide Drench',
            'Root Knot Nematode' => 'Nematicide',
            'Scale Insects' => 'Horticultural Oil',
            'Mealybugs' => 'Alcohol Spray or Insecticidal Soap',
            'Japanese Beetle' => 'Pyrethrin Spray',
            'Slugs or Snails' => 'Iron Phosphate Pellets',
            'Fruit Fly Larvae' => 'Spinosad Bait',
            'Bacterial Wilt' => 'Copper Bactericide',
            'Bacterial Spot' => 'Copper-Based Bactericide',
            'Bacterial Canker' => 'Copper Spray',
            'Mosaic Virus' => 'Zinc Booster (Immune Support)',
            'Leaf Curl Virus' => 'Copper Spray (Preventative)',
            'Sunscald' => 'Use Shade Cloth (No Chemical)',
        ];

        $chemical_name = $treatment_map[$disease] ?? 'Broad-Spectrum Fungicide';

        // Financials
        $loss_rate = ($severity == 2) ? 0.60 : 0.20;
        $potential_loss = ($farmSize * $market_data['crop_value']) * $loss_rate; // market_data from config.php
        $treatment_cost = ($fungicide_amount * $market_data['fungicide_cost']) + $market_data['labor_cost'];
        $roi = $potential_loss - $treatment_cost;

        $result = [
            'disease' => $disease,
            'treatment_name' => $chemical_name,
            'water' => round($water, 1),
            'fungicide' => round($fungicide_amount, 1),
            'loss' => number_format($potential_loss, 2),
            'cost' => number_format($treatment_cost, 2),
            'roi' => number_format($roi, 2)
        ];

        // Save to History
        $stmt = $pdo->prepare("INSERT INTO history (user_id, date, symptom, diagnosis, roi) VALUES (?, NOW(), ?, ?, ?)");
        $stmt->execute([$user_id, $symptom, $disease, $result['roi']]);

    } catch (Exception $e) {
        $error_msg = "AI Model Error: " . $e->getMessage();
    }
}
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        --glass-bg: rgba(255, 255, 255, 0.95);
        --shadow-soft: 0 10px 40px -10px rgba(0,0,0,0.08);
        --radius-card: 16px;
    }
    .hero-welcome {
        background: var(--primary-gradient);
        border-radius: var(--radius-card);
        color: white;
        padding: 2rem;
        position: relative;
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .hero-pattern {
        position: absolute; top: 0; right: 0; opacity: 0.1;
        transform: translate(20%, -20%) rotate(-15deg);
    }
    .card-modern {
        background: var(--glass-bg);
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: var(--radius-card);
        box-shadow: var(--shadow-soft);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card-modern:hover { transform: translateY(-3px); box-shadow: 0 15px 45px -10px rgba(0,0,0,0.12); }
    .form-floating-custom > label { color: #95a5a6; font-size: 0.85rem; font-weight: 600; letter-spacing: 0.5px; }
    .btn-action {
        background: var(--primary-gradient); border: none; color: white;
        padding: 12px; border-radius: 12px; font-weight: 600; letter-spacing: 0.5px;
        box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);
        transition: all 0.3s;
    }
    .btn-action:hover { box-shadow: 0 6px 20px rgba(46, 204, 113, 0.6); transform: translateY(-2px); }
    .result-section { animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="container-fluid px-0">

    <div class="hero-welcome shadow-sm">
        <i class="fas fa-leaf fa-10x hero-pattern"></i>
        <div class="position-relative" style="z-index: 2;">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-white text-success bg-opacity-25 border border-white border-opacity-25 px-3 py-1 rounded-pill me-2">
                    <i class="fas fa-signal me-1"></i> System Online
                </span>
                <small class="opacity-75"><?php echo date('l, F j, Y'); ?></small>
            </div>
            <h2 class="fw-bold mb-1">Hello,Farmer <?php echo htmlspecialchars($user_name); ?>! 👋</h2>
            <p class="mb-0 opacity-90">Ready to analyze your crops today?</p>
        </div>
    </div>

    <?php if ($result): ?>
    <div class="result-section mb-5">
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark m-0"><i class="fas fa-clipboard-check text-primary me-2"></i>Analysis Report</h5>
            <div class="ms-auto">
                <button onclick="window.print()" class="btn btn-sm btn-light text-muted border"><i class="fas fa-print me-1"></i> Print</button>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="card-modern h-100 p-4 position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-10"><i class="fas fa-microscope fa-4x text-danger"></i></div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-2 me-3"><i class="fas fa-biohazard"></i></div>
                        <h6 class="fw-bold m-0 text-muted text-uppercase small">Diagnosis</h6>
                    </div>
                    <h3 class="fw-bold text-danger mb-2 text-break"><?php echo $result['disease']; ?></h3>
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-3">
                        <?php echo ($severity == 2) ? 'Severe Condition' : 'Early Stage'; ?>
                    </span>
                    <hr class="my-3 opacity-10">
                    <div class="d-flex justify-content-between text-muted small">
                        <span>Symptom Detected:</span>
                        <strong class="text-dark text-capitalize"><?php echo htmlspecialchars($symptom); ?></strong>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card-modern h-100 p-4 position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-10"><i class="fas fa-prescription fa-4x text-info"></i></div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-info bg-opacity-10 text-info rounded-circle p-2 me-3"><i class="fas fa-file-medical"></i></div>
                        <h6 class="fw-bold m-0 text-muted text-uppercase small">Prescription</h6>
                    </div>
                    
                    <div class="bg-light border rounded-3 p-3 mb-3">
                        <small class="text-info fw-bold text-uppercase d-block mb-1" style="font-size: 0.65rem;">RX / Chemical</small>
                        <div class="fw-bold text-dark fs-5 text-break" style="line-height:1.2;"><?php echo $result['treatment_name']; ?></div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-2 border rounded-3 text-center">
                                <small class="d-block text-muted fw-bold small">DOSAGE</small>
                                <strong class="text-dark"><?php echo $result['fungicide']; ?> ml</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded-3 text-center">
                                <small class="d-block text-muted fw-bold small">WATER</small>
                                <strong class="text-dark"><?php echo $result['water']; ?> L</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-12">
                <div class="card-modern h-100 p-4 position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-10"><i class="fas fa-chart-line fa-4x text-success"></i></div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-2 me-3"><i class="fas fa-coins"></i></div>
                        <h6 class="fw-bold m-0 text-muted text-uppercase small">ROI Analysis</h6>
                    </div>
                    
                    <div class="text-center py-2">
                        <small class="text-success fw-bold text-uppercase">Net Savings</small>
                        <h2 class="fw-bold text-success m-0 display-6">+₱<?php echo $result['roi']; ?></h2>
                    </div>

                    <div class="mt-3 pt-3 border-top small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Crop Value Preserved</span>
                            <span class="fw-bold text-dark">₱<?php echo $result['loss']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Treatment Cost</span>
                            <span class="fw-bold text-danger">-₱<?php echo $result['cost']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr class="my-5 border-secondary opacity-10">
    <?php endif; ?>


    <div class="row g-4">
        
        <div class="col-lg-7">
            <div class="card-modern h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold text-dark m-0"><i class="fas fa-robot text-primary me-2"></i>Run New Diagnosis</h5>
                    <p class="text-muted small mt-1">Select observed symptoms to generate a treatment plan.</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="diagnosisForm">
                        <div class="mb-4">
                            <label class="form-label text-uppercase text-muted fw-bold small">Visual Observation</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-eye text-muted"></i></span>
                                <select name="symptom" id="symptom-select" class="form-select border-start-0 bg-light py-3 ps-3" required>
                                    <option value="" disabled selected>Select what you see...</option>
                                    <optgroup label="Common Issues">
                                        <option value="yellow leaves">Yellow Leaves (General)</option>
                                        <option value="wilting">Wilting (Drooping)</option>
                                        <option value="stunted growth">Stunted Growth</option>
                                        <option value="dry leaf tips">Dry/Burnt Leaf Tips</option>
                                    </optgroup>
                                    <optgroup label="Spots & Marks">
                                        <option value="black spots">Black Spots</option>
                                        <option value="brown spots">Brown Spots (Leaf Spot)</option>
                                        <option value="rust spots">Rust Colored Spots</option>
                                        <option value="white powder">White Powder (Mildew)</option>
                                        <option value="silver patches">Silver Patches (Thrips)</option>
                                        <option value="concentric rings">Target Spots (Blight)</option>
                                        <option value="water soaked spots">Water-Soaked Spots</option>
                                        <option value="bleached spots">Bleached Spots (Sunscald)</option>
                                    </optgroup>
                                    <optgroup label="Pests & Damage">
                                        <option value="holes in leaves">Holes in Leaves</option>
                                        <option value="skeletonized leaves">Skeletonized Leaves</option>
                                        <option value="webbing on leaves">Webbing (Mites)</option>
                                        <option value="sticky honey dew">Sticky Residue</option>
                                        <option value="white cottony mass">White Cottony Mass</option>
                                        <option value="leaf miner tracks">Winding Tracks</option>
                                    </optgroup>
                                    <optgroup label="Roots & Fruit">
                                        <option value="rotting roots">Rotting Roots</option>
                                        <option value="galls on roots">Root Galls/Knots</option>
                                        <option value="deformed fruit">Deformed Fruit</option>
                                        <option value="blossom end rot">Rotten Fruit Bottom</option>
                                        <option value="tunneling in fruit">Tunneling in Fruit</option>
                                    </optgroup>
                                    <optgroup label="Nutrient/Viral">
                                        <option value="mottled leaves">Mottled/Mosaic Pattern</option>
                                        <option value="yellowing between veins">Yellowing Between Veins</option>
                                        <option value="purple veins">Purple Veins</option>
                                        <option value="pale young leaves">Pale Young Leaves</option>
                                        <option value="crinkled leaves">Crinkled Leaves</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-uppercase text-muted fw-bold small">Affected Area</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-ruler-combined text-muted"></i></span>
                                    <input type="number" name="farm_size" class="form-control bg-light border-start-0 py-3" placeholder="e.g. 50" required>
                                    <span class="input-group-text bg-light text-muted">sqm</span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-uppercase text-muted fw-bold small">Severity Level</label>
                                <div class="d-flex gap-2">
                                    <input type="radio" class="btn-check" name="severity" id="sev1" value="1" checked>
                                    <label class="btn btn-outline-success w-50 py-3 fw-bold" for="sev1">
                                        <i class="fas fa-shield-alt me-1"></i> Mild
                                    </label>

                                    <input type="radio" class="btn-check" name="severity" id="sev2" value="2">
                                    <label class="btn btn-outline-danger w-50 py-3 fw-bold" for="sev2">
                                        <i class="fas fa-exclamation-circle me-1"></i> Severe
                                    </label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-action w-100">
                            <i class="fas fa-search-plus me-2"></i> Analyze & Predict Treatment
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-modern h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark m-0"><i class="fas fa-chart-bar text-success me-2"></i>Live Market</h5>
                    <span class="badge bg-success bg-opacity-10 text-success animate-pulse">● LIVE</span>
                </div>
                <div class="card-body p-4">
                    
                    <div class="alert alert-light border border-success border-opacity-25 rounded-3 mb-4 d-flex align-items-center">
                        <i class="fas fa-globe-asia fa-2x text-success me-3 opacity-50"></i>
                        <div style="line-height: 1.2;">
                            <strong class="text-dark d-block">Global Exchange Active</strong>
                            <small class="text-muted">USD/PHP Rate: ₱<?php echo number_format($current_rate, 2); ?></small>
                        </div>
                    </div>

                    <div class="mb-3 p-3 border rounded-3 d-flex justify-content-between align-items-center hover-bg-light transition">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 p-2 rounded me-3 text-warning"><i class="fas fa-wheat"></i></div>
                            <div>
                                <small class="d-block text-muted fw-bold" style="font-size:0.7rem;">CROP YIELD</small>
                                <strong class="text-dark">Value / sqm</strong>
                            </div>
                        </div>
                        <h5 class="fw-bold text-dark m-0" id="price-crop">₱<?php echo number_format($market_data['crop_value'], 2); ?></h5>
                    </div>

                    <div class="mb-3 p-3 border rounded-3 d-flex justify-content-between align-items-center hover-bg-light transition">
                        <div class="d-flex align-items-center">
                            <div class="bg-info bg-opacity-10 p-2 rounded me-3 text-info"><i class="fas fa-flask"></i></div>
                            <div>
                                <small class="d-block text-muted fw-bold" style="font-size:0.7rem;">CHEMICAL</small>
                                <strong class="text-dark">Cost / ml</strong>
                            </div>
                        </div>
                        <h5 class="fw-bold text-dark m-0" id="price-chem">₱<?php echo number_format($market_data['fungicide_cost'], 2); ?></h5>
                    </div>

                    <div class="p-3 border rounded-3 d-flex justify-content-between align-items-center hover-bg-light transition">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 p-2 rounded me-3 text-success"><i class="fas fa-users"></i></div>
                            <div>
                                <small class="d-block text-muted fw-bold" style="font-size:0.7rem;">LABOR</small>
                                <strong class="text-dark">Standard Rate</strong>
                            </div>
                        </div>
                        <h5 class="fw-bold text-dark m-0" id="price-labor">₱<?php echo number_format($market_data['labor_cost'], 2); ?></h5>
                    </div>

                    <div class="text-center mt-4">
                        <small class="text-muted fst-italic" id="last-update">Last Updated: <?php echo date("h:i A"); ?></small>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<?php
// pages/dashboard.php

// 1. AUTH & CONNECTION
if (!isset($_SESSION['user_id'])) { die("Access Denied"); }
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'Farmer';

$host = 'localhost'; $db = 'agrosafe_db'; $u = 'root'; $p = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $u, $p);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("DB Connection Error."); }

// 2. LOAD AI SYSTEMS
require_once __DIR__ . '/../includes/ai_prediction.php'; // Weather AI
use Phpml\ModelManager;

// --- A. RUN WEATHER PREDICTION ---
$forecaster = new DiseaseForecaster();
$weather = $forecaster->getWeatherData();
$prediction = $forecaster->predictRisk($weather);

// --- B. RUN FARM INTELLIGENCE (Restored Feature) ---
// Fetch recent history to calculate health score
$stmt = $pdo->prepare("SELECT diagnosis, date FROM history WHERE user_id = ? ORDER BY date DESC LIMIT 50");
$stmt->execute([$user_id]);
$history_log = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Dynamic Health Score (Last 7 Days weight)
$total_scans = count($history_log);
$recent_issues = 0;
$now = time();

foreach ($history_log as $log) {
    if ($now - strtotime($log['date']) < (7 * 24 * 60 * 60)) { // Issues in last 7 days
        $recent_issues++;
    }
}

// Score Logic: Start at 100. Deduct 15 points per recent issue.
// This allows the score to "heal" if you haven't had issues lately.
$health_score = max(0, 100 - ($recent_issues * 15)); 

// Identify Top Threat
$diagnoses = array_column($history_log, 'diagnosis');
$counts = array_count_values($diagnoses);
arsort($counts);
$top_threat = !empty($counts) ? key($counts) : 'None';
$top_threat_count = !empty($counts) ? current($counts) : 0;

// Generate Smart Insight
if ($health_score >= 80) {
    $insight_msg = "Farm condition is <strong>Stable</strong>. Continue monitoring.";
    $insight_color = "success";
    $health_label = "Healthy";
} elseif ($health_score >= 50) {
    $insight_msg = "Recurring issue detected: <strong>$top_threat</strong> ($top_threat_count cases). Review soil nutrients.";
    $insight_color = "warning";
    $health_label = "At Risk";
} else {
    $insight_msg = "<strong>CRITICAL ALERT:</strong> High disease frequency this week. Immediate intervention required.";
    $insight_color = "danger";
    $health_label = "Critical";
}


// --- C. HANDLE DIAGNOSIS FORM ---
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['symptom'])) {
    $modelManager = new ModelManager();
    try {
        $classifier = $modelManager->restoreFromFile(__DIR__ . '/../models/disease_classifier.phpml');
        $waterModel = $modelManager->restoreFromFile(__DIR__ . '/../models/water_predictor.phpml');
        $fungicideModel = $modelManager->restoreFromFile(__DIR__ . '/../models/fungicide_predictor.phpml');

        $symptomsInput = $_POST['symptom']; 
        $farmSize = (int)$_POST['farm_size'];
        $severity = (int)$_POST['severity'];

        if (!is_array($symptomsInput)) { $symptomsInput = [$symptomsInput]; }

        // Prediction
        $disease = $classifier->predict([$symptomsInput]); 
        $disease = $disease[0]; 
        $water = $waterModel->predict([$farmSize, $severity]);
        $fungicide_amount = $fungicideModel->predict([$farmSize, $severity]);

        // Treatment Map
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
        $potential_loss = ($farmSize * $market_data['crop_value']) * $loss_rate; 
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

        $symptomString = implode(', ', $symptomsInput);
        $stmt = $pdo->prepare("INSERT INTO history (user_id, date, symptom, diagnosis, roi) VALUES (?, NOW(), ?, ?, ?)");
        $stmt->execute([$user_id, $symptomString, $disease, $result['roi']]);

    } catch (Exception $e) { $error_msg = "Error: " . $e->getMessage(); }
}
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        --glass-bg: rgba(255, 255, 255, 0.95);
        --radius-card: 16px;
    }
    .hero-welcome {
        background: var(--primary-gradient);
        border-radius: var(--radius-card); color: white; padding: 2rem;
        position: relative; overflow: hidden; margin-bottom: 1.5rem;
    }
    .hero-pattern { position: absolute; top: 0; right: 0; opacity: 0.1; transform: translate(20%, -20%) rotate(-15deg); }
    .card-modern {
        background: var(--glass-bg); border: 1px solid rgba(0,0,0,0.05);
        border-radius: var(--radius-card); box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08);
    }
    .animate-pulse { animation: pulse-red 2s infinite; }
    @keyframes pulse-red { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(231,76,60,0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(231,76,60,0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(231,76,60,0); } }
    
    /* NEW: Health Gauge */
    .health-gauge {
        width: 80px; height: 80px;
        border-radius: 50%;
        background: conic-gradient(var(--gauge-color) var(--gauge-value), #e9ecef 0deg);
        display: flex; align-items: center; justify-content: center;
        position: relative;
    }
    .health-gauge::before {
        content: ""; position: absolute; width: 65px; height: 65px;
        background: white; border-radius: 50%;
    }
    .health-gauge span { position: relative; z-index: 2; font-weight: 800; font-size: 1.2rem; }
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
            <h2 class="fw-bold mb-1">Hello, Farmer <?php echo htmlspecialchars($user_name); ?>! 👋</h2>
        </div>
    </div>

    <div class="row g-4 mb-4">
        
        <div class="col-lg-6">
            <?php if (isset($prediction) && $prediction['status'] === 'danger'): ?>
                <div class="card-modern border-start border-4 border-danger p-4 h-100 position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-10 animate-pulse"><i class="fas fa-radar fa-3x text-danger"></i></div>
                    <div class="d-flex align-items-center">
                        <div class="me-3 text-center">
                            <h2 class="fw-bold text-dark m-0"><?php echo round($weather['temp']); ?>°C</h2>
                            <small class="text-muted"><?php echo $weather['humidity']; ?>% Hum</small>
                        </div>
                        <div class="border-start ps-3 border-danger border-opacity-25">
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge bg-danger animate-pulse me-2">WARNING</span>
                                <small class="text-danger fw-bold">HIGH RISK</small>
                            </div>
                            <h5 class="fw-bold text-dark mb-0"><?php echo $prediction['data']['disease']; ?></h5>
                            <small class="text-muted"><?php echo $prediction['data']['reason']; ?></small>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card-modern border-start border-4 border-success p-4 h-100">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-3 me-3"><i class="fas fa-shield-alt fa-lg"></i></div>
                        <div>
                            <h6 class="fw-bold text-dark m-0">Weather Optimal</h6>
                            <small class="text-muted">No immediate disease vectors detected.</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-6">
            <div class="card-modern p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="health-gauge me-3" 
                             style="--gauge-value: <?php echo $health_score; ?>%; --gauge-color: var(--bs-<?php echo $insight_color; ?>);">
                            <span class="text-dark"><?php echo $health_score; ?></span>
                        </div>
                        <div>
                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.65rem;">FARM HEALTH SCORE</small>
                            <h5 class="fw-bold text-dark m-0"><?php echo $health_label; ?></h5>
                            <small class="text-<?php echo $insight_color; ?>">
                                <?php echo ($health_score > 50) ? 'Trending Up 📈' : 'Action Needed 📉'; ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="border-start ps-3 ms-3 w-50">
                        <small class="text-uppercase text-primary fw-bold mb-1 d-block" style="font-size: 0.65rem;">
                            <i class="fas fa-lightbulb me-1"></i> AI INSIGHT
                        </small>
                        <p class="small text-muted mb-0 lh-sm">
                            <?php echo $insight_msg; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($result): ?>
    <div class="result-section mb-5 animate-fade-in">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="card-modern h-100 p-4 position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-10"><i class="fas fa-microscope fa-4x text-danger"></i></div>
                    <h6 class="fw-bold text-muted text-uppercase small mb-3">Diagnosis</h6>
                    <h3 class="fw-bold text-danger mb-2 text-break"><?php echo $result['disease']; ?></h3>
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-3">
                        <?php echo ($severity == 2) ? 'Severe' : 'Early Stage'; ?>
                    </span>
                    <div class="mt-3">
                        <small class="text-muted d-block mb-2">Symptoms Observed:</small>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($symptomsInput as $sym): ?>
                                <span class="badge bg-white border text-secondary rounded-pill fw-normal">
                                    <i class="fas fa-check me-1 small"></i><?php echo htmlspecialchars($sym); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card-modern h-100 p-4 position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-10"><i class="fas fa-prescription fa-4x text-info"></i></div>
                    <h6 class="fw-bold text-muted text-uppercase small mb-3">Primary Remedy</h6>
                    <div class="bg-light border rounded-3 p-3 mb-3">
                        <small class="text-info fw-bold text-uppercase d-block mb-1" style="font-size: 0.65rem;">RX / Chemical</small>
                        <div class="fw-bold text-dark fs-5 text-break" style="line-height:1.2;"><?php echo $result['treatment_name']; ?></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><div class="p-2 border rounded-3 text-center"><small class="d-block text-muted fw-bold small">DOSAGE</small><strong class="text-dark"><?php echo $result['fungicide']; ?> ml</strong></div></div>
                        <div class="col-6"><div class="p-2 border rounded-3 text-center"><small class="d-block text-muted fw-bold small">WATER</small><strong class="text-dark"><?php echo $result['water']; ?> L</strong></div></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-12">
                <div class="card-modern h-100 p-4 position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-10"><i class="fas fa-chart-line fa-4x text-success"></i></div>
                    <h6 class="fw-bold text-muted text-uppercase small mb-3">ROI Analysis</h6>
                    <div class="text-center py-2">
                        <small class="text-success fw-bold text-uppercase">Net Savings</small>
                        <h2 class="fw-bold text-success m-0 display-6">+₱<?php echo $result['roi']; ?></h2>
                    </div>
                    <div class="mt-3 pt-3 border-top small">
                        <div class="d-flex justify-content-between mb-1"><span class="text-muted">Crop Value</span><span class="fw-bold text-dark">₱<?php echo $result['loss']; ?></span></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Cost</span><span class="fw-bold text-danger">-₱<?php echo $result['cost']; ?></span></div>
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
                    <h5 class="fw-bold text-dark m-0"><i class="fas fa-robot text-primary me-2"></i>Run Diagnosis</h5>
                    <p class="text-muted small mt-1">Select observed symptoms to generate a plan.</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="diagnosisForm">
                        <div class="mb-4">
                            <label class="form-label text-uppercase text-muted fw-bold small">Visual Observation</label>
                            <select name="symptom[]" id="symptom-select" class="form-select border-start-0 bg-light py-3 ps-3" multiple="multiple" required>
                                <option value="" disabled>Select symptoms...</option>
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

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-uppercase text-muted fw-bold small">Area (sqm)</label>
                                <input type="number" name="farm_size" class="form-control bg-light py-3" placeholder="50" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase text-muted fw-bold small">Severity</label>
                                <div class="d-flex gap-2">
                                    <input type="radio" class="btn-check" name="severity" id="sev1" value="1" checked>
                                    <label class="btn btn-outline-success w-50 py-3 fw-bold" for="sev1">Mild</label>
                                    <input type="radio" class="btn-check" name="severity" id="sev2" value="2">
                                    <label class="btn btn-outline-danger w-50 py-3 fw-bold" for="sev2">Severe</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn-action w-100"><i class="fas fa-search-plus me-2"></i> Analyze</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-modern h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 px-4 pb-0 d-flex justify-content-between">
                    <h5 class="fw-bold text-dark m-0"><i class="fas fa-chart-bar text-success me-2"></i>Live Market</h5>
                    <span class="badge bg-success bg-opacity-10 text-success animate-pulse">● LIVE</span>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3 p-3 border rounded-3 d-flex justify-content-between align-items-center">
                        <div><small class="d-block text-muted fw-bold" style="font-size:0.7rem;">CROP VALUE</small><strong class="text-dark">per sqm</strong></div>
                        <h5 class="fw-bold text-dark m-0">₱<?php echo number_format($market_data['crop_value'], 2); ?></h5>
                    </div>
                    <div class="mb-3 p-3 border rounded-3 d-flex justify-content-between align-items-center">
                        <div><small class="d-block text-muted fw-bold" style="font-size:0.7rem;">CHEMICAL</small><strong class="text-dark">per ml</strong></div>
                        <h5 class="fw-bold text-dark m-0">₱<?php echo number_format($market_data['fungicide_cost'], 2); ?></h5>
                    </div>
                    <div class="p-3 border rounded-3 d-flex justify-content-between align-items-center">
                        <div><small class="d-block text-muted fw-bold" style="font-size:0.7rem;">LABOR</small><strong class="text-dark">Rate</strong></div>
                        <h5 class="fw-bold text-dark m-0">₱<?php echo number_format($market_data['labor_cost'], 2); ?></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
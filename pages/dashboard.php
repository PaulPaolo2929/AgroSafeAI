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

require_once __DIR__ . '/../includes/ai_prediction.php'; 
use Phpml\ModelManager;

// --- A. HANDLE DIAGNOSIS (RUNS FIRST) ---
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

        $disease = $classifier->predict([$symptomsInput]); 
        $disease = $disease[0]; 
        $water = $waterModel->predict([$farmSize, $severity]);
        $fungicide_amount = $fungicideModel->predict([$farmSize, $severity]);

        // Smart Treatment Map
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
        
        // Save to DB (Try/Catch to handle old schemas gracefully)
        try {
            $stmt = $pdo->prepare("INSERT INTO history (user_id, date, symptom, diagnosis, roi, status, notes) VALUES (?, NOW(), ?, ?, ?, 'Pending', NULL)");
            $stmt->execute([$user_id, $symptomString, $disease, $result['roi']]);
        } catch (Exception $e) {
            $stmt = $pdo->prepare("INSERT INTO history (user_id, date, symptom, diagnosis, roi) VALUES (?, NOW(), ?, ?, ?)");
            $stmt->execute([$user_id, $symptomString, $disease, $result['roi']]);
        }

    } catch (Exception $e) { $error_msg = $e->getMessage(); }
}

// --- B. WEATHER AI ---
$forecaster = new DiseaseForecaster();
$weather = $forecaster->getWeatherData();
$prediction = $forecaster->predictRisk($weather);

// --- C. FARM INTELLIGENCE (UPDATED) ---
$stmt = $pdo->prepare("SELECT diagnosis, date, status FROM history WHERE user_id = ? ORDER BY date DESC LIMIT 50");
$stmt->execute([$user_id]);
$history_log = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recent_active_issues = 0;
$now = time();
foreach ($history_log as $log) {
    $is_recent = ($now - strtotime($log['date']) < (7 * 24 * 60 * 60)); 
    $status = isset($log['status']) ? $log['status'] : 'Pending';
    if ($is_recent && $status !== 'Resolved') { $recent_active_issues++; }
}

$health_score = max(0, 100 - ($recent_active_issues * 20)); 

$active_diagnoses = [];
foreach ($history_log as $log) {
    if ((isset($log['status']) ? $log['status'] : 'Pending') !== 'Resolved') { $active_diagnoses[] = $log['diagnosis']; }
}
$counts = array_count_values($active_diagnoses);
arsort($counts);
$top_threat = !empty($counts) ? key($counts) : 'None';
$top_threat_count = !empty($counts) ? current($counts) : 0;

if ($health_score >= 80) {
    $insight_msg = "Farm condition is <strong>Stable</strong>. Great job!";
    $insight_color = "success";
    $health_label = "Healthy";
} elseif ($health_score >= 50) {
    $insight_msg = "Alert: <strong>$top_threat</strong> detected ($top_threat_count cases).";
    $insight_color = "warning";
    $health_label = "At Risk";
} else {
    $insight_msg = "<strong>CRITICAL:</strong> High disease rate. Action needed.";
    $insight_color = "danger";
    $health_label = "Critical";
}
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        --glass-bg: rgba(255, 255, 255, 0.98);
        --card-radius: 20px;
        --input-bg: #f8f9fa;
        --input-focus-border: #2ecc71;
    }

    /* GLOBAL ANIMATIONS */
    .animate-fade-in { animation: fadeIn 0.8s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    /* CARD SYSTEM */
    .card-modern {
        background: var(--glass-bg);
        border: 1px solid rgba(0,0,0,0.04);
        border-radius: var(--card-radius);
        box-shadow: 0 12px 40px -10px rgba(0,0,0,0.06);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card-modern:hover { transform: translateY(-3px); box-shadow: 0 15px 50px -10px rgba(0,0,0,0.1); }

    /* HERO SECTION */
    .hero-welcome {
        background: var(--primary-gradient);
        border-radius: var(--card-radius); color: white; padding: 2.5rem;
        position: relative; overflow: hidden; margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(46, 204, 113, 0.4);
    }
    .hero-pattern { position: absolute; top: -20px; right: -20px; opacity: 0.15; transform: rotate(-15deg); font-size: 15rem; }

    /* PULSING ALERTS */
    .animate-pulse { animation: pulse-red 2s infinite; }
    @keyframes pulse-red { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(231,76,60,0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(231,76,60,0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(231,76,60,0); } }

    /* HEALTH GAUGE */
    .health-gauge {
        width: 85px; height: 85px; border-radius: 50%;
        background: conic-gradient(var(--gauge-color) var(--gauge-value), #e9ecef 0deg);
        display: flex; align-items: center; justify-content: center; position: relative;
        box-shadow: inset 0 0 10px rgba(0,0,0,0.1);
    }
    .health-gauge::before { content: ""; position: absolute; width: 70px; height: 70px; background: white; border-radius: 50%; }
    .health-gauge span { position: relative; z-index: 2; font-weight: 800; font-size: 1.25rem; }

    /* CUSTOM FORM INPUTS (SOFT UI) */
    .form-control, .form-select {
        background-color: var(--input-bg);
        border: 1px solid transparent;
        border-radius: 12px;
        padding: 14px 18px;
        font-weight: 500;
        transition: all 0.3s;
    }
    .form-control:focus, .form-select:focus {
        background-color: white;
        border-color: var(--input-focus-border);
        box-shadow: 0 0 0 4px rgba(46, 204, 113, 0.15);
    }
    .input-group-text { background: var(--input-bg); border: none; border-radius: 12px 0 0 12px; color: #95a5a6; }
    
    /* ANALYZE BUTTON (GLOW EFFECT) */
    .btn-action {
        background: linear-gradient(135deg, #0f9b0f 0%, #00d2ff 100%);
        border: none; color: white; padding: 18px 24px;
        border-radius: 50px; font-weight: 800; font-size: 1rem;
        letter-spacing: 1px; text-transform: uppercase;
        box-shadow: 0 10px 25px rgba(15, 155, 15, 0.4);
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        position: relative; overflow: hidden; width: 100%;
        display: flex; align-items: center; justify-content: center;
    }
    .btn-action:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 20px 40px rgba(15, 155, 15, 0.6); }
    .btn-action:active { transform: translateY(2px); }
    
    /* MARKET CARDS */
    .market-card { transition: all 0.2s; border-left: 3px solid transparent; }
    .market-card:hover { background: #f8f9fa; border-left-color: #2ecc71; }
</style>

<div class="container-fluid px-0 animate-fade-in">

    <div class="hero-welcome">
        <i class="fas fa-leaf hero-pattern"></i>
        <div class="position-relative" style="z-index: 2;">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-white text-success bg-opacity-25 border border-white border-opacity-25 px-3 py-1 rounded-pill me-2">
                    <i class="fas fa-satellite-dish me-1"></i> AI Connected
                </span>
                <small class="opacity-75"><?php echo date('F j, Y'); ?></small>
            </div>
            <h1 class="fw-bold mb-1 display-5">Hello, Farmer <?php echo htmlspecialchars($user_name); ?>.</h1>
            <p class="mb-0 opacity-90 fs-5">Your farm intelligence hub is ready.</p>
        </div>
    </div>

    <div class="row g-4 mb-5">
        
        <div class="col-lg-6">
            <?php if (isset($prediction) && $prediction['status'] === 'danger'): ?>
                <div class="card-modern border-start border-4 border-danger p-4 h-100 position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-10 animate-pulse"><i class="fas fa-radar fa-4x text-danger"></i></div>
                    <div class="d-flex align-items-center">
                        <div class="me-4 text-center">
                            <h2 class="fw-bold text-dark m-0 display-6"><?php echo round($weather['temp']); ?>°</h2>
                            <small class="text-muted fw-bold"><?php echo $weather['humidity']; ?>% HUM</small>
                        </div>
                        <div class="border-start ps-4 border-danger border-opacity-25">
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge bg-danger animate-pulse me-2">AI WARNING</span>
                                <small class="text-danger fw-bold text-uppercase">High Probability</small>
                            </div>
                            <h4 class="fw-bold text-dark mb-1"><?php echo $prediction['data']['disease']; ?></h4>
                            <small class="text-muted d-block lh-sm"><?php echo $prediction['data']['reason']; ?></small>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card-modern border-start border-4 border-success p-4 h-100">
                    <div class="d-flex align-items-center h-100">
                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-4 me-3"><i class="fas fa-shield-alt fa-2x"></i></div>
                        <div>
                            <h4 class="fw-bold text-dark m-0">Conditions Optimal</h4>
                            <small class="text-muted">No immediate environmental disease vectors detected.</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-6">
            <div class="card-modern p-4 h-100">
                <div class="d-flex align-items-center justify-content-between h-100">
                    <div class="d-flex align-items-center">
                        <div class="health-gauge me-4" 
                             style="--gauge-value: <?php echo $health_score; ?>%; --gauge-color: var(--bs-<?php echo $insight_color; ?>);">
                            <span class="text-dark"><?php echo $health_score; ?></span>
                        </div>
                        <div>
                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Current Health Index</small>
                            <h3 class="fw-bold text-dark m-0"><?php echo $health_label; ?></h3>
                            <small class="text-<?php echo $insight_color; ?> fw-bold">
                                <?php echo ($health_score > 50) ? '<i class="fas fa-arrow-trend-up me-1"></i> Improving' : '<i class="fas fa-arrow-trend-down me-1"></i> Declining'; ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="border-start ps-4 ms-3 w-50">
                        <small class="text-uppercase text-primary fw-bold mb-2 d-block" style="font-size: 0.7rem;">
                            <i class="fas fa-robot me-1"></i> Automated Insight
                        </small>
                        <p class="text-muted mb-0 lh-sm small">
                            <?php echo $insight_msg; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($result): ?>
    <div class="mb-5 animate-fade-in">
        <div class="d-flex align-items-center mb-3">
            <div class="bg-dark text-white rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width:32px;height:32px"><i class="fas fa-file-medical"></i></div>
            <h5 class="fw-bold m-0">Analysis Report</h5>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card-modern h-100 p-4 position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-10"><i class="fas fa-microscope fa-5x text-danger"></i></div>
                    <small class="text-uppercase text-muted fw-bold">Identified Issue</small>
                    <h2 class="fw-bold text-danger mb-2 mt-1"><?php echo $result['disease']; ?></h2>
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-3 py-2">
                        <?php echo ($severity == 2) ? 'Severe Severity' : 'Early Stage Detection'; ?>
                    </span>
                    <hr class="my-4 opacity-10">
                    <small class="text-muted d-block mb-2 fw-bold">BASED ON SYMPTOMS:</small>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($symptomsInput as $sym): ?>
                            <span class="badge bg-white border text-secondary rounded-pill fw-normal px-3 py-2">
                                <i class="fas fa-check me-1 small text-success"></i><?php echo htmlspecialchars($sym); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card-modern h-100 p-4 position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-10"><i class="fas fa-prescription fa-5x text-info"></i></div>
                    <small class="text-uppercase text-muted fw-bold">Primary Protocol</small>
                    <div class="bg-light border rounded-4 p-4 mt-2 mb-3">
                        <small class="text-info fw-bold text-uppercase d-block mb-1">Recommended Chemical</small>
                        <div class="fw-bold text-dark fs-4" style="line-height:1.2;"><?php echo $result['treatment_name']; ?></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><div class="p-3 border rounded-4 text-center bg-white"><small class="d-block text-muted fw-bold small mb-1">DOSAGE</small><strong class="text-dark fs-5"><?php echo $result['fungicide']; ?> ml</strong></div></div>
                        <div class="col-6"><div class="p-3 border rounded-4 text-center bg-white"><small class="d-block text-muted fw-bold small mb-1">WATER</small><strong class="text-dark fs-5"><?php echo $result['water']; ?> L</strong></div></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card-modern h-100 p-4 position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-10"><i class="fas fa-coins fa-5x text-success"></i></div>
                    <small class="text-uppercase text-muted fw-bold">Financial Impact</small>
                    <div class="text-center py-4">
                        <small class="text-success fw-bold text-uppercase ls-1">Net Savings</small>
                        <h1 class="fw-bold text-success m-0 display-4">+₱<?php echo $result['roi']; ?></h1>
                    </div>
                    <div class="pt-3 border-top">
                        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Crop Value</span><span class="fw-bold text-dark">₱<?php echo $result['loss']; ?></span></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Treatment Cost</span><span class="fw-bold text-danger">-₱<?php echo $result['cost']; ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        
        <div class="col-lg-8">
            <div class="card-modern h-100">
                <div class="p-4 border-bottom">
                    <h4 class="fw-bold text-dark m-0"><i class="fas fa-wand-magic-sparkles text-primary me-2"></i>New Diagnosis</h4>
                    <p class="text-muted small mt-1 mb-0">Select observations below to generate an AI treatment plan.</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="diagnosisForm">
                        <div class="mb-4">
                            <label class="form-label text-uppercase text-muted fw-bold small ms-1">Visual Observations</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-eye"></i></span>
                                <select name="symptom[]" id="symptom-select" class="form-select border-start-0 ps-3" multiple="multiple" required>
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
                        </div>

                        <div class="row g-4 mb-5">
                            <div class="col-md-6">
                                <label class="form-label text-uppercase text-muted fw-bold small ms-1">Affected Area</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-ruler-combined"></i></span>
                                    <input type="number" name="farm_size" class="form-control border-start-0" placeholder="e.g. 50" required>
                                    <span class="input-group-text">sqm</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase text-muted fw-bold small ms-1">Severity Level</label>
                                <div class="d-flex gap-3">
                                    <input type="radio" class="btn-check" name="severity" id="sev1" value="1" checked>
                                    <label class="btn btn-outline-success w-50 py-3 fw-bold rounded-4" for="sev1">
                                        <i class="fas fa-shield-alt me-2"></i> Mild
                                    </label>
                                    <input type="radio" class="btn-check" name="severity" id="sev2" value="2">
                                    <label class="btn btn-outline-danger w-50 py-3 fw-bold rounded-4" for="sev2">
                                        <i class="fas fa-radiation me-2"></i> Severe
                                    </label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-action">
                            <i class="fas fa-microchip me-2 fa-lg"></i> Run AI Diagnosis
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-modern h-100">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark m-0"><i class="fas fa-chart-line text-success me-2"></i>Market Data</h5>
                    <span class="badge bg-success bg-opacity-10 text-success animate-pulse">● LIVE</span>
                </div>
                <div class="card-body p-0">
                    <div class="market-card p-4 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 text-warning rounded p-3 me-3"><i class="fas fa-wheat fa-lg"></i></div>
                            <div>
                                <small class="text-muted fw-bold" style="font-size:0.7rem">CROP VALUE</small>
                                <div class="fw-bold text-dark">Yield / sqm</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <h5 class="fw-bold text-dark m-0">₱<?php echo number_format($market_data['crop_value'], 2); ?></h5>
                            <small class="text-success fw-bold"><i class="fas fa-caret-up"></i> 2.4%</small>
                        </div>
                    </div>

                    <div class="market-card p-4 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="bg-info bg-opacity-10 text-info rounded p-3 me-3"><i class="fas fa-flask fa-lg"></i></div>
                            <div>
                                <small class="text-muted fw-bold" style="font-size:0.7rem">CHEMICAL</small>
                                <div class="fw-bold text-dark">Cost / ml</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <h5 class="fw-bold text-dark m-0">₱<?php echo number_format($market_data['fungicide_cost'], 2); ?></h5>
                            <small class="text-muted fw-bold">- 0.0%</small>
                        </div>
                    </div>

                    <div class="market-card p-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="bg-secondary bg-opacity-10 text-secondary rounded p-3 me-3"><i class="fas fa-users fa-lg"></i></div>
                            <div>
                                <small class="text-muted fw-bold" style="font-size:0.7rem">LABOR</small>
                                <div class="fw-bold text-dark">Avg Rate</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <h5 class="fw-bold text-dark m-0">₱<?php echo number_format($market_data['labor_cost'], 2); ?></h5>
                            <small class="text-danger fw-bold"><i class="fas fa-caret-up"></i> 1.2%</small>
                        </div>
                    </div>
                    
                    <div class="p-3 bg-light text-center">
                        <small class="text-muted fst-italic" style="font-size: 0.75rem;">
                            <i class="fas fa-clock me-1"></i> Updated: <?php echo date("h:i A"); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
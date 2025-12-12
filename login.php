<?php require_once 'includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroSafeAI - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f4f7f6 0%, #e8f5e9 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-container { width: 100%; max-width: 420px; padding: 20px; }
        .auth-card { animation: fadeInUp 0.5s ease-out; }
        .hidden { display: none; }
        .brand-login { font-size: 2rem; color: var(--primary-color); font-weight: 800; text-align: center; margin-bottom: 1.5rem; }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="brand-login"><i class="fas fa-leaf"></i> AgroSafeAI</div>

    <?php if($error): ?>
        <div class="alert alert-danger border-0 shadow-sm"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success border-0 shadow-sm"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
    <?php endif; ?>

    <div id="login-form" class="custom-card auth-card">
        <h4 class="fw-bold mb-1">Welcome Back</h4>
        <p class="text-muted small mb-4">Enter your credentials to access the AI.</p>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">USERNAME</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" name="username" class="form-control bg-light border-0 p-3" placeholder="Farmer123" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">PASSWORD</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control bg-light border-0 p-3" placeholder="••••••••" required>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label small text-muted" for="remember">Remember me</label>
                </div>
                <a href="#" onclick="toggleForm('forgot')" class="small text-primary text-decoration-none">Forgot Password?</a>
            </div>
            <button type="submit" name="login" class="btn-primary-custom shadow w-100 py-3 mb-3">Login to Dashboard</button>
            <div class="text-center">
                <small class="text-muted">No account? <a href="#" onclick="toggleForm('register')" class="fw-bold text-primary text-decoration-none">Register here</a></small>
            </div>
        </form>
    </div>

    <div id="register-form" class="custom-card auth-card hidden">
        <h4 class="fw-bold mb-1">Join AgroSafeAI</h4>
        <p class="text-muted small mb-4">Create your secure farm profile.</p>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">USERNAME</label>
                <input type="text" name="username" class="form-control bg-light border-0 p-3" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">EMAIL ADDRESS</label>
                <input type="email" name="email" class="form-control bg-light border-0 p-3" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">PASSWORD</label>
                <input type="password" name="password" class="form-control bg-light border-0 p-3" required>
            </div>
            <button type="submit" name="register" class="btn-primary-custom shadow w-100 py-3 mb-3">Create Account</button>
            <div class="text-center">
                <small class="text-muted">Already have an account? <a href="#" onclick="toggleForm('login')" class="fw-bold text-primary text-decoration-none">Login here</a></small>
            </div>
        </form>
    </div>

    <div id="forgot-form" class="custom-card auth-card hidden">
        <h4 class="fw-bold mb-1">Reset Password</h4>
        <p class="text-muted small mb-4">We'll send a recovery link to your email.</p>
        
        <form>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">EMAIL ADDRESS</label>
                <input type="email" class="form-control bg-light border-0 p-3" placeholder="name@farm.com">
            </div>
            <button type="button" class="btn-primary-custom shadow w-100 py-3 mb-3" onclick="alert('Demo Mode: Contact admin reset.')">Send Link</button>
            <div class="text-center">
                <a href="#" onclick="toggleForm('login')" class="small text-muted text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
            </div>
        </form>
    </div>

</div>

<script>
    function toggleForm(formType) {
        // Hide all forms
        document.getElementById('login-form').classList.add('hidden');
        document.getElementById('register-form').classList.add('hidden');
        document.getElementById('forgot-form').classList.add('hidden');
        
        // Show selected form
        document.getElementById(formType + '-form').classList.remove('hidden');
    }
</script>
</body>
</html>
<?php
require_once 'includes/header.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $message_type = 'error';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                // In production, send password reset email here
                $message = 'If an account exists with this email, you will receive password reset instructions shortly.';
                $message_type = 'success';
            } else {
                $message = 'If an account exists with this email, you will receive password reset instructions shortly.';
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = 'An error occurred. Please try again later.';
            $message_type = 'error';
        }
    }
}
?>

<style>
    .forgot-card {
        max-width: 460px;
        margin: 40px auto;
        background: #fff;
        border-radius: 24px;
        padding: 40px 36px;
        box-shadow: 0 8px 40px rgba(0, 51, 102, 0.08);
        border: 1px solid rgba(255, 204, 0, 0.1);
        text-align: center;
    }
    .forgot-card .icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: linear-gradient(135deg, #003366, #004080);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 2rem;
        color: #ffcc00;
    }
    .forgot-card h2 {
        color: #003366;
        font-size: 1.4rem;
        margin-bottom: 8px;
    }
    .forgot-card p {
        color: #888;
        font-size: 0.9rem;
        margin-bottom: 28px;
        line-height: 1.5;
    }
    .forgot-card .input-group {
        margin-bottom: 20px;
        text-align: left;
    }
    .forgot-card label {
        display: block;
        font-weight: 600;
        color: #003366;
        margin-bottom: 6px;
        font-size: 0.9rem;
    }
    .forgot-card input[type="email"] {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 0.95rem;
        outline: none;
        transition: border-color 0.3s;
        box-sizing: border-box;
    }
    .forgot-card input[type="email"]:focus {
        border-color: #003366;
    }
    .forgot-card .back-link {
        display: block;
        margin-top: 20px;
        color: #888;
        font-size: 0.88rem;
        text-decoration: none;
        transition: color 0.3s;
    }
    .forgot-card .back-link:hover {
        color: #003366;
    }
</style>

<!-- Hero Inner -->
<section class="hero-inner">
    <div class="container" style="text-align: center; position: relative; z-index: 2;">
        <h1 style="font-size: 2.5rem; color: #fff; margin-bottom: 12px;">Reset Password</h1>
        <p style="color: rgba(255,255,255,0.8); font-size: 1.05rem; max-width: 500px; margin: 0 auto;">Forgot your password? No worries — we'll help you get back into your account.</p>
    </div>
</section>

<section class="section" style="min-height: 50vh;">
    <div class="container">
        <div class="forgot-card">
            <div class="icon">🔑</div>
            <h2>Forgot Your Password?</h2>
            <p>Enter the email address associated with your account and we'll send you instructions to reset your password.</p>

            <?php if (!empty($message)): ?>
            <div style="padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:0.88rem;<?php echo $message_type === 'success' ? 'background:#d4edda;color:#155724;border:1px solid #c3e6cb;' : 'background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email address">
                </div>
                <button type="submit" class="btn-gold" style="width:100%;justify-content:center;padding:14px;">
                    Send Reset Instructions →
                </button>
            </form>

            <a href="login.php" class="back-link">← Back to Login</a>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>

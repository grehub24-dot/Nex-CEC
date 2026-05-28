<?php
/**
 * Resource Redirect Interstitial
 *
 * A clean Nex CEC-branded interstitial page that shows before
 * sending the user to an external interactive resource (like PBS Kids).
 * The user must click "Launch" to proceed — they see a Nex CEC page
 * with resource info and a big launch button.
 *
 * URL: resource_redirect.php?id=5
 */
require_once 'includes/db.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}
$school_name = $settings['school_name'] ?? 'Nex CEC';

$resource_id = (int)($_GET['id'] ?? 0);
$resource = null;

if ($resource_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM resource_links WHERE id = ? AND is_active = 1");
        $stmt->execute([$resource_id]);
        $resource = $stmt->fetch();
    } catch (Exception $e) {}
}

if (!$resource) {
    http_response_code(404);
    header("Location: ../");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($resource['title']); ?> — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .interstitial-card {
            background: #fff;
            border-radius: 16px;
            padding: 48px 40px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        }
        .school-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 30px;
        }
        .school-brand i { color: var(--primary-color); font-size: 1.2rem; }
        .icon-wrapper {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #eef2ff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .icon-wrapper i { font-size: 2rem; color: var(--primary-color); }
        h2 {
            font-size: 1.3rem;
            color: #222;
            margin-bottom: 8px;
        }
        .description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .source-info {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f5f5f5;
            color: #888;
            margin-bottom: 25px;
        }
        .notice {
            background: #fff8e1;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 0.8rem;
            color: #856404;
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            text-align: left;
        }
        .notice i { margin-top: 2px; flex-shrink: 0; }
        .btn-launch {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 36px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.2s, transform 0.1s;
            width: 100%;
            justify-content: center;
        }
        .btn-launch:hover { opacity: 0.92; transform: translateY(-1px); }
        .btn-launch:active { transform: translateY(0); }
        .btn-back {
            display: inline-block;
            margin-top: 16px;
            color: #999;
            font-size: 0.85rem;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .btn-back:hover { background: #f5f5f5; color: #555; }
        .separator { border: none; border-top: 1px solid #eee; margin: 20px 0; }

        @media (max-width: 480px) {
            .interstitial-card { padding: 32px 24px; }
            h2 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
    <div class="interstitial-card">
        <div class="school-brand">
            <i class="fas fa-graduation-cap"></i>
            <span><?php echo htmlspecialchars($school_name); ?></span>
        </div>

        <div class="icon-wrapper">
            <i class="fas fa-external-link-alt"></i>
        </div>

        <h2><?php echo htmlspecialchars($resource['title']); ?></h2>

        <?php if ($resource['description']): ?>
            <p class="description"><?php echo htmlspecialchars($resource['description']); ?></p>
        <?php endif; ?>

        <div class="source-info">
            <?php echo htmlspecialchars($resource['source'] ?: 'External Resource'); ?>
        </div>

        <div class="notice">
            <i class="fas fa-info-circle"></i>
            <span>This activity is hosted on an external site. It will open in a new browser tab.</span>
        </div>

        <a href="<?php echo htmlspecialchars($resource['url']); ?>"
           class="btn-launch"
           target="_blank"
           rel="noopener noreferrer">
            <i class="fas fa-external-link-alt"></i>
            Launch Activity
        </a>

        <hr class="separator">

        <a href="javascript:history.back()" class="btn-back">
            <i class="fas fa-arrow-left"></i> Go Back
        </a>
    </div>
</body>
</html>

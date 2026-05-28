<?php
/**
 * Resource Viewer — masks external URLs behind nexcec.com domain.
 *
 * Accepts ?id=X, looks up the resource_links table, and renders
 * the external content inside a Nex CEC-branded page. The user's
 * URL bar always shows /resource.php?id=X — the real external URL
 * is never visible.
 *
 * Embed types:
 *   'iframe'   → shows content in an iframe (hand2mind, kiddoworksheets)
 *   'redirect' → shows a button to launch in new tab via interstitial
 *
 * URL: resource.php?id=5
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

// 404 if not found
if (!$resource) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Not Found — <?php echo htmlspecialchars($school_name); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 80vh; background: #f5f7fa; font-family: system-ui, sans-serif; }
        .not-found { text-align: center; padding: 40px; }
        .not-found h1 { font-size: 3rem; color: #dc3545; margin-bottom: 10px; }
        .not-found p { color: #666; font-size: 1.1rem; }
    </style>
</head>
<body>
    <div class="not-found">
        <h1>404</h1>
        <p>Resource not found or has been deactivated.</p>
        <p><a href="javascript:history.back()" style="color: var(--primary-color);">&larr; Go Back</a></p>
    </div>
</body>
</html>
    <?php
    exit;
}

// Determine allowed frame-src based on resource URL
$parsed_url = parse_url($resource['url']);
$frame_origin = ($parsed_url['scheme'] ?? 'https') . '://' . ($parsed_url['host'] ?? '');

// Override CSP to allow iframing the external resource
header("Content-Security-Policy: default-src 'self'; "
    . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
    . "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com; "
    . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com; "
    . "img-src 'self' data: https:; "
    . "frame-src 'self' $frame_origin; "
    . "frame-ancestors 'self'; "
    . "base-uri 'self'");
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
        body { font-family: system-ui, -apple-system, sans-serif; background: #fff; }

        /* Viewer header */
        .viewer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 24px;
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .viewer-header .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 1rem;
            color: #333;
        }
        .viewer-header .brand i { color: var(--primary-color); }
        .viewer-header .resource-title {
            font-size: 0.9rem;
            color: #555;
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .viewer-header .actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .viewer-header .actions a, .viewer-header .actions button {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.8rem;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid #ddd;
            background: #fff;
            color: #555;
            transition: all 0.2s;
        }
        .viewer-header .actions a:hover, .viewer-header .actions button:hover {
            background: #f5f5f5;
            border-color: #ccc;
        }
        .viewer-header .actions .btn-primary {
            background: var(--primary-color);
            color: #fff;
            border-color: var(--primary-color);
        }
        .viewer-header .actions .btn-primary:hover {
            opacity: 0.9;
        }

        /* Iframe container */
        .iframe-container {
            width: 100%;
            height: calc(100vh - 56px);
            position: relative;
        }
        .iframe-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Loading indicator */
        .iframe-loader {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            z-index: 1;
            transition: opacity 0.3s;
        }
        .iframe-loader.hidden {
            opacity: 0;
            pointer-events: none;
        }
        .iframe-loader .spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            color: #888;
        }
        .iframe-loader .spinner i { font-size: 2rem; }

        /* Redirect fallback (for embed_type = 'redirect') */
        .redirect-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 56px);
            background: #f5f7fa;
        }
        .redirect-card {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            max-width: 480px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        .redirect-card .icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        .redirect-card h2 { margin-bottom: 10px; color: #333; }
        .redirect-card p { color: #666; margin-bottom: 25px; line-height: 1.6; }
        .redirect-card .btn-launch {
            display: inline-block;
            padding: 12px 32px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .redirect-card .btn-launch:hover { opacity: 0.9; }
        .redirect-card .btn-back {
            display: inline-block;
            margin-top: 12px;
            color: #888;
            font-size: 0.85rem;
            text-decoration: none;
        }
        .redirect-card .btn-back:hover { color: #555; }

        .source-tag {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .viewer-header { padding: 10px 16px; flex-wrap: wrap; gap: 8px; }
            .viewer-header .resource-title { max-width: 200px; }
            .viewer-header .actions a, .viewer-header .actions button { font-size: 0.75rem; padding: 4px 10px; }
            .iframe-container { height: calc(100vh - 52px); }
            .redirect-container { min-height: calc(100vh - 52px); padding: 20px; }
            .redirect-card { padding: 24px; }
        }
    </style>
</head>
<body>
    <!-- Header bar — always shows Nex CEC branding -->
    <div class="viewer-header">
        <div class="brand">
            <i class="fas fa-graduation-cap"></i>
            <span><?php echo htmlspecialchars($school_name); ?></span>
            <span style="color:#ccc; margin:0 6px;">|</span>
            <span class="resource-title" title="<?php echo htmlspecialchars($resource['title']); ?>">
                <?php echo htmlspecialchars($resource['title']); ?>
            </span>
        </div>
        <div class="actions">
            <a href="javascript:history.back()"><i class="fas fa-arrow-left"></i> Back</a>
            <?php if ($resource['embed_type'] === 'redirect'): ?>
                <a href="../resource_redirect.php?id=<?php echo (int)$resource['id']; ?>" class="btn-primary" target="_blank">
                    <i class="fas fa-external-link-alt"></i> Open Activity
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($resource['embed_type'] === 'iframe'): ?>
        <!-- Iframe viewer — external URL hidden in src attribute only -->
        <div class="iframe-container">
            <div class="iframe-loader" id="iframeLoader">
                <div class="spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Loading resource...</span>
                </div>
            </div>
            <iframe src="<?php echo htmlspecialchars($resource['url']); ?>"
                    title="<?php echo htmlspecialchars($resource['title']); ?>"
                    allowfullscreen
                    loading="lazy"
                    onload="document.getElementById('iframeLoader').classList.add('hidden');"
                    referrerpolicy="no-referrer-when-downgrade"
                    sandbox="allow-scripts allow-forms allow-popups allow-storage-access-by-user-activation allow-top-navigation-by-user-activation">
            </iframe>
        </div>
    <?php elseif ($resource['embed_type'] === 'redirect'): ?>
        <!-- Redirect interstitial — content launches in new tab -->
        <div class="redirect-container">
            <div class="redirect-card">
                <div class="icon">
                    <i class="fas fa-gamepad"></i>
                </div>
                <h2><?php echo htmlspecialchars($resource['title']); ?></h2>
                <?php if ($resource['description']): ?>
                    <p><?php echo htmlspecialchars($resource['description']); ?></p>
                <?php endif; ?>
                <?php if ($resource['source']): ?>
                    <div class="source-tag"><?php echo htmlspecialchars($resource['source']); ?></div>
                <?php endif; ?>
                <p style="margin-top:20px; font-size:0.9rem; color:#999;">
                    This interactive activity opens in a new tab.
                </p>
                <a href="../resource_redirect.php?id=<?php echo (int)$resource['id']; ?>"
                   class="btn-launch"
                   target="_blank"
                   rel="noopener noreferrer">
                    <i class="fas fa-external-link-alt"></i> Launch Activity
                </a>
                <br>
                <a href="javascript:history.back()" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Go Back
                </a>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>

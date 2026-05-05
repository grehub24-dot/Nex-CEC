<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

function fetchHtml(string $url): string {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0\r\nAccept: text/html\r\n",
            'timeout' => 8,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $html = @file_get_contents($url, false, $context);
    return $html !== false ? (string)$html : '';
}

function fetchRandomUstedPhotos(int $count): array {
    if ($count < 1) {
        return [];
    }

    $listingHtml = fetchHtml('https://usted.edu.gh/fasme/staff/');
    if ($listingHtml === '') {
        return [];
    }

    preg_match_all('/https:\/\/usted\.edu\.gh\/staff\/[^"\']+/i', $listingHtml, $matches);
    $profileUrls = array_values(array_unique($matches[0] ?? []));
    if (empty($profileUrls)) {
        return [];
    }

    shuffle($profileUrls);
    $photos = [];

    foreach ($profileUrls as $profileUrl) {
        $profileHtml = fetchHtml($profileUrl);
        if ($profileHtml === '') {
            continue;
        }

        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $profileHtml, $og)) {
            $photo = trim((string)$og[1]);
            if ($photo !== '') {
                $photos[] = $photo;
            }
        } elseif (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $profileHtml, $tw)) {
            $photo = trim((string)$tw[1]);
            if ($photo !== '') {
                $photos[] = $photo;
            }
        }

        if (count($photos) >= $count) {
            break;
        }
    }

    return $photos;
}

$stmt = $pdo->query("SELECT * FROM executives ORDER BY id ASC");
$executives = $stmt->fetchAll();

if (empty($executives)) {
    $executives = [
        [
            'full_name' => 'Dela Stephen Dunyo',
            'position' => 'President',
            'image_url' => '',
            'bio' => 'Cybersecurity • Level 300',
            'email' => ''
        ],
        [
            'full_name' => 'Tetteh Reuben',
            'position' => 'Vice President',
            'image_url' => '',
            'bio' => 'BSc ITE • Level 300',
            'email' => ''
        ],
        [
            'full_name' => 'Poleson Godwin',
            'position' => 'General Secretary',
            'image_url' => '',
            'bio' => 'Level 300',
            'email' => ''
        ],
        [
            'full_name' => 'Kitsi Roland',
            'position' => 'Financial Secretary',
            'image_url' => '',
            'bio' => 'Level 300',
            'email' => ''
        ],
        [
            'full_name' => 'Naazir Godfred',
            'position' => 'Organizer',
            'image_url' => '',
            'bio' => 'Level 300',
            'email' => ''
        ],
        [
            'full_name' => 'Abdul Razzaq Adama',
            'position' => 'Treasurer',
            'image_url' => '',
            'bio' => 'Level 300',
            'email' => ''
        ],
        [
            'full_name' => 'Dosuntey Rose',
            'position' => 'WOCOM',
            'image_url' => '',
            'bio' => 'Level 300',
            'email' => ''
        ],
    ];
}

/*
$randomPhotos = fetchRandomUstedPhotos(count($executives));
if (!empty($randomPhotos)) {
    $photoCount = count($randomPhotos);
    foreach ($executives as $index => $exec) {
        $executives[$index]['image_url'] = $randomPhotos[$index % $photoCount];
    }
}
*/
?>

<div class="hero" style="height: 50vh;">
    <h1>Our Leadership</h1>
    <p>Meet the executives serving the 2025/2026 administration.</p>
</div>

<div class="section">
    <div class="container">
        <div class="card-grid">
            <?php foreach ($executives as $exec): ?>
            <div class="card" style="text-align: center;">
                <div style="padding: 20px;">
                    <img src="<?php echo htmlspecialchars($exec['image_url'] ?: 'images/aamusted.jpg'); ?>" alt="<?php echo htmlspecialchars($exec['full_name']); ?>" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover;">
                </div>
                <div class="card-content">
                    <h3 class="card-title"><?php echo htmlspecialchars($exec['full_name']); ?></h3>
                    <p style="color: var(--secondary-color); font-weight: bold;"><?php echo htmlspecialchars($exec['position']); ?></p>
                    <p style="margin: 10px 0;"><?php echo htmlspecialchars($exec['bio'] ?? ''); ?></p>
                    <div style="margin-top: 15px;">
                        <?php if (!empty($exec['email'])): ?>
                        <a href="mailto:<?php echo htmlspecialchars($exec['email']); ?>" style="color: var(--primary-color); margin: 0 10px;"><i class="fas fa-envelope"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($exec['linkedin_url'])): ?>
                        <a href="<?php echo htmlspecialchars($exec['linkedin_url']); ?>" style="color: var(--primary-color); margin: 0 10px;"><i class="fab fa-linkedin"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($exec['github_url'])): ?>
                        <a href="<?php echo htmlspecialchars($exec['github_url']); ?>" style="color: var(--primary-color); margin: 0 10px;"><i class="fab fa-github"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

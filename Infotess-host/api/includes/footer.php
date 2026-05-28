    </main>

    <!-- Public Footer (Navy) -->
    <footer class="footer-navy">
        <div class="footer-grid">
            <!-- Brand -->
            <div>
                <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="<?php echo htmlspecialchars($school_name); ?> Logo" height="40" onerror="this.onerror=null;this.src='<?php echo $base_url; ?>images/chariot-logo.svg'" style="margin-bottom: var(--space-sm);">
                <h3 style="color: var(--color-on-dark); font-size: 18px; font-weight: 600; margin: 0 0 4px;"><?php echo htmlspecialchars($school_name); ?></h3>
                <p style="color: var(--color-on-dark-muted); font-size: 14px; margin-bottom: var(--space-md);"><?php echo htmlspecialchars($settings['school_motto'] ?? 'Excellence in Education'); ?></p>
                <p style="color: var(--color-on-dark-muted); font-size: 13px; line-height: 1.6;">Providing quality basic education from Creche through JHS — building strong academic foundations, character development, and holistic growth for every child.</p>
                <div style="display: flex; gap: var(--space-sm); margin-top: var(--space-md);">
                    <a href="<?php echo htmlspecialchars($settings['school_facebook'] ?? '#'); ?>" target="_blank" rel="noopener" aria-label="Facebook" style="color: var(--color-on-dark-muted); font-size: 16px;"><i class="fab fa-facebook-f"></i></a>
                    <a href="<?php echo htmlspecialchars($settings['school_twitter'] ?? '#'); ?>" target="_blank" rel="noopener" aria-label="Twitter" style="color: var(--color-on-dark-muted); font-size: 16px;"><i class="fab fa-twitter"></i></a>
                    <a href="<?php echo htmlspecialchars($settings['school_instagram'] ?? '#'); ?>" target="_blank" rel="noopener" aria-label="Instagram" style="color: var(--color-on-dark-muted); font-size: 16px;"><i class="fab fa-instagram"></i></a>
                    <a href="<?php echo htmlspecialchars($settings['school_youtube'] ?? '#'); ?>" target="_blank" rel="noopener" aria-label="YouTube" style="color: var(--color-on-dark-muted); font-size: 16px;"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="footer-heading">Quick Links</h4>
                <a href="<?php echo $base_url; ?>index.php" class="footer-link">Home</a>
                <a href="<?php echo $base_url; ?>about.php" class="footer-link">About Us</a>
                <a href="<?php echo $base_url; ?>news.php" class="footer-link">News & Updates</a>
                <a href="<?php echo $base_url; ?>events.php" class="footer-link">School Events</a>
                <a href="<?php echo $base_url; ?>gallery.php" class="footer-link">Photo Gallery</a>
                <a href="<?php echo $base_url; ?>contact.php" class="footer-link">Contact Us</a>
            </div>

            <!-- Programs -->
            <div>
                <h4 class="footer-heading">Programs</h4>
                <a href="<?php echo $base_url; ?>about.php#early-childhood" class="footer-link">Creche (1–2 yrs)</a>
                <a href="<?php echo $base_url; ?>about.php#early-childhood" class="footer-link">Nursery (2–4 yrs)</a>
                <a href="<?php echo $base_url; ?>about.php#early-childhood" class="footer-link">Kindergarten (4–6 yrs)</a>
                <a href="<?php echo $base_url; ?>about.php#primary" class="footer-link">Primary (6–11 yrs)</a>
                <a href="<?php echo $base_url; ?>about.php#jhs" class="footer-link">JHS (11–14 yrs)</a>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="footer-heading">Contact</h4>
                <a href="tel:<?php echo htmlspecialchars($settings['school_phone'] ?? ''); ?>" class="footer-link"><i class="fas fa-phone-alt" style="width: 18px;"></i> <?php echo htmlspecialchars($settings['school_phone'] ?? '+233 XX XXX XXXX'); ?></a>
                <a href="mailto:<?php echo htmlspecialchars($settings['school_email'] ?? ''); ?>" class="footer-link"><i class="fas fa-envelope" style="width: 18px;"></i> <?php echo htmlspecialchars($settings['school_email'] ?? 'info@school.edu.gh'); ?></a>
                <span class="footer-link"><i class="fas fa-map-marker-alt" style="width: 18px;"></i> <?php echo htmlspecialchars($settings['school_address'] ?? 'School Address, City, Ghana'); ?></span>
                <div style="margin-top: var(--space-md);">
                    <p style="color: var(--color-on-dark-muted); font-size: 13px; margin: 0 0 4px;"><strong style="color: var(--color-on-dark);">Office Hours</strong></p>
                    <p style="color: var(--color-on-dark-muted); font-size: 13px; margin: 0;">Mon–Fri: 7:30 AM – 4:00 PM</p>
                </div>
            </div>
        </div>

        <hr class="footer-divider">

        <div class="footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_name); ?>. All rights reserved.</span>
            <ul class="footer-bottom-links">
                <li><a href="<?php echo $base_url; ?>about.php">About</a></li>
                <li><a href="<?php echo $base_url; ?>contact.php">Contact</a></li>
                <li><a href="<?php echo $base_url; ?>login.php">Staff Login</a></li>
            </ul>
        </div>
    </footer>

    <!-- JS -->
    <script src="<?php echo $base_url; ?>js/main.js"></script>
</body>
</html>

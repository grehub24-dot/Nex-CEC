    </main>

    <!-- Public Footer (Navy) -->
    <footer class="footer-navy">
        <div class="footer-links-grid">
            <!-- Brand -->
            <div class="footer-brand-block">
                <a href="<?php echo $base_url; ?>index.php" class="footer-logo">
                    <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="<?php echo htmlspecialchars($school_name); ?> Logo" onerror="this.onerror=null;this.src='<?php echo $base_url; ?>images/chariot-logo.svg'">
                </a>
                <p><?php echo htmlspecialchars($settings['school_motto'] ?? 'Excellence in Education'); ?> — Providing quality basic education from Creche through JHS, building strong academic foundations, character development, and holistic growth for every child.</p>
                <div class="footer-social" style="display:flex;gap:12px;margin-top:12px;">
                    <a href="<?php echo htmlspecialchars($settings['school_facebook'] ?? '#'); ?>" target="_blank" rel="noopener" aria-label="Facebook" style="color:var(--color-on-dark-muted);font-size:18px;"><i class="fab fa-facebook-f"></i></a>
                    <a href="<?php echo htmlspecialchars($settings['school_twitter'] ?? '#'); ?>" target="_blank" rel="noopener" aria-label="Twitter" style="color:var(--color-on-dark-muted);font-size:18px;"><i class="fab fa-twitter"></i></a>
                    <a href="<?php echo htmlspecialchars($settings['school_instagram'] ?? '#'); ?>" target="_blank" rel="noopener" aria-label="Instagram" style="color:var(--color-on-dark-muted);font-size:18px;"><i class="fab fa-instagram"></i></a>
                    <a href="<?php echo htmlspecialchars($settings['school_youtube'] ?? '#'); ?>" target="_blank" rel="noopener" aria-label="YouTube" style="color:var(--color-on-dark-muted);font-size:18px;"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="<?php echo $base_url; ?>index.php">Home</a></li>
                    <li><a href="<?php echo $base_url; ?>about.php">About Us</a></li>
                    <li><a href="<?php echo $base_url; ?>news.php">News & Updates</a></li>
                    <li><a href="<?php echo $base_url; ?>events.php">School Events</a></li>
                    <li><a href="<?php echo $base_url; ?>gallery.php">Photo Gallery</a></li>
                    <li><a href="<?php echo $base_url; ?>contact.php">Contact Us</a></li>
                </ul>
            </div>

            <!-- Programs -->
            <div class="footer-col">
                <h4>Programs</h4>
                <ul>
                    <li><a href="<?php echo $base_url; ?>about.php#early-childhood">Creche (1–2 yrs)</a></li>
                    <li><a href="<?php echo $base_url; ?>about.php#early-childhood">Nursery (2–4 yrs)</a></li>
                    <li><a href="<?php echo $base_url; ?>about.php#early-childhood">Kindergarten (4–6 yrs)</a></li>
                    <li><a href="<?php echo $base_url; ?>about.php#primary">Primary (6–11 yrs)</a></li>
                    <li><a href="<?php echo $base_url; ?>about.php#jhs">JHS (11–14 yrs)</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div class="footer-col">
                <h4>Contact</h4>
                <ul>
                    <li><a href="tel:<?php echo htmlspecialchars($settings['school_phone'] ?? ''); ?>"><i class="fas fa-phone-alt" style="width:16px;"></i> <?php echo htmlspecialchars($settings['school_phone'] ?? '+233 XX XXX XXXX'); ?></a></li>
                    <li><a href="mailto:<?php echo htmlspecialchars($settings['school_email'] ?? ''); ?>"><i class="fas fa-envelope" style="width:16px;"></i> <?php echo htmlspecialchars($settings['school_email'] ?? 'info@school.edu.gh'); ?></a></li>
                    <li><span style="color:var(--color-on-dark-muted);font-size:var(--text-sm-size);"><i class="fas fa-map-marker-alt" style="width:16px;"></i> <?php echo htmlspecialchars($settings['school_address'] ?? 'School Address, City, Ghana'); ?></span></li>
                    <li style="margin-top:8px;"><span style="color:var(--color-on-dark-muted);font-size:var(--text-sm-size);"><strong style="color:var(--color-on-dark);">Office Hours:</strong> Mon–Fri: 7:30 AM – 4:00 PM</span></li>
                </ul>
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

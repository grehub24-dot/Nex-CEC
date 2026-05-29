    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <!-- School Info -->
                <div class="footer-section footer-brand">
                    <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="<?php echo htmlspecialchars($school_name); ?> Logo" height="50" class="footer-logo" onerror="this.onerror=null;this.src='<?php echo $base_url; ?>images/chariot-logo.svg'">
                    <h3><?php echo htmlspecialchars($school_name); ?></h3>
                    <p><?php echo htmlspecialchars($school_motto); ?></p>
                    <p class="footer-description">Providing quality basic education from Creche through JHS — building strong academic foundations, character development, and holistic growth for every child.</p>
                    <div class="footer-social">
                        <?php if (!empty($settings['school_facebook'] ?? '')): ?>
                            <a href="<?php echo htmlspecialchars($settings['school_facebook']); ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($settings['school_twitter'] ?? '')): ?>
                            <a href="<?php echo htmlspecialchars($settings['school_twitter']); ?>" target="_blank" rel="noopener" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($settings['school_instagram'] ?? '')): ?>
                            <a href="<?php echo htmlspecialchars($settings['school_instagram']); ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($settings['school_youtube'] ?? '')): ?>
                            <a href="<?php echo htmlspecialchars($settings['school_youtube']); ?>" target="_blank" rel="noopener" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                        <?php endif; ?>
                        <!-- Default social icons if not configured -->
                        <?php if (empty($settings['school_facebook'] ?? '') && empty($settings['school_twitter'] ?? '') && empty($settings['school_instagram'] ?? '') && empty($settings['school_youtube'] ?? '')): ?>
                            <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                            <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="footer-section">
                    <h3><i class="fas fa-link"></i> Quick Links</h3>
                    <ul>
                        <li><a href="<?php echo $base_url; ?>index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="<?php echo $base_url; ?>about.php"><i class="fas fa-chevron-right"></i> About Us</a></li>
                        <li><a href="<?php echo $base_url; ?>news.php"><i class="fas fa-chevron-right"></i> News & Updates</a></li>
                        <li><a href="<?php echo $base_url; ?>events.php"><i class="fas fa-chevron-right"></i> School Events</a></li>
                        <li><a href="<?php echo $base_url; ?>gallery.php"><i class="fas fa-chevron-right"></i> Photo Gallery</a></li>
                        <li><a href="<?php echo $base_url; ?>resources.php"><i class="fas fa-chevron-right"></i> Resources</a></li>
                        <li><a href="<?php echo $base_url; ?>register.php"><i class="fas fa-chevron-right"></i> Enroll Now</a></li>
                        <li><a href="<?php echo $base_url; ?>contact.php"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div class="footer-section">
                    <h3><i class="fas fa-address-card"></i> Contact Us</h3>
                    <ul class="footer-contact">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($settings['school_address'] ?? 'School Address, City, Ghana'); ?></span>
                        </li>
                        <li>
                            <i class="fas fa-phone-alt"></i>
                            <span><a href="tel:<?php echo htmlspecialchars($settings['school_phone'] ?? ''); ?>"><?php echo htmlspecialchars($settings['school_phone'] ?? '+233 XX XXX XXXX'); ?></a></span>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span><a href="mailto:<?php echo htmlspecialchars($settings['school_email'] ?? ''); ?>"><?php echo htmlspecialchars($settings['school_email'] ?? 'info@school.edu.gh'); ?></a></span>
                        </li>
                    </ul>
                    <div class="footer-hours">
                        <h4><i class="fas fa-clock"></i> Office Hours</h4>
                        <p>Monday – Friday: <strong>7:30 AM – 4:00 PM</strong></p>
                        <p>Saturday & Sunday: <strong>Closed</strong></p>
                    </div>
                </div>

                <!-- Newsletter / CTA -->
                <div class="footer-section">
                    <h3><i class="fas fa-bullhorn"></i> Stay Connected</h3>
                    <p>Get the latest school news and updates delivered to your inbox.</p>
                    <form class="footer-newsletter" method="post" action="<?php echo $base_url; ?>contact.php">
                        <div class="newsletter-input-group">
                            <input type="email" name="email" placeholder="Your email address" required aria-label="Email for newsletter">
                            <button type="submit" aria-label="Subscribe"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </form>
                    <div class="footer-cta">
                        <p>Ready to join our school community?</p>
                        <a href="<?php echo $base_url; ?>register.php" class="btn-cta">Enroll Your Child Today</a>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_name); ?>. All Rights Reserved.</p>
                <ul class="footer-bottom-links">
                    <li><a href="<?php echo $base_url; ?>about.php">About</a></li>
                    <li><a href="<?php echo $base_url; ?>contact.php">Contact</a></li>
                    <li><a href="<?php echo $base_url; ?>login.php">Staff Login</a></li>
                </ul>
            </div>
        </div>
    </footer>

    <!-- JS -->
    <script src="<?php echo $base_url; ?>js/main.js"></script>
</body>
</html>

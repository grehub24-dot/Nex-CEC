    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?php echo htmlspecialchars($school_name); ?></h3>
                    <p><?php echo htmlspecialchars($school_motto); ?> — Providing quality basic education from Creche through JHS.</p>
                </div>
                <div class="footer-section">
                    <?php $base_url = getBasePath(); ?>
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="<?php echo $base_url; ?>index.php">Home</a></li>
                        <li><a href="<?php echo $base_url; ?>about.php">About Us</a></li>
                        <li><a href="<?php echo $base_url; ?>register.php">Enroll</a></li>
                        <li><a href="<?php echo $base_url; ?>contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($settings['school_address'] ?? 'School Address, City, Ghana'); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($settings['school_email'] ?? 'info@school.edu.gh'); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($settings['school_phone'] ?? '+233 XX XXX XXXX'); ?></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_name); ?>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- JS -->
    <script src="<?php echo $base_url; ?>js/main.js"></script>
</body>
</html>

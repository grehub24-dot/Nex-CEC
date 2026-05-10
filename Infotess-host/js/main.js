document.addEventListener('DOMContentLoaded', () => {
    // Hamburger Menu Toggle (public nav)
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');

    if (hamburger && navLinks) {
        function toggleNav() {
            navLinks.classList.toggle('active');
            const expanded = navLinks.classList.contains('active');
            hamburger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }

        hamburger.addEventListener('click', toggleNav);
        hamburger.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleNav();
            }
        });

        // Close nav when clicking outside
        document.addEventListener('click', (e) => {
            if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
                navLinks.classList.remove('active');
                hamburger.setAttribute('aria-expanded', 'false');
            }
        });

        // Close nav on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                navLinks.classList.remove('active');
                hamburger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Mobile Dropdown Toggle (public nav)
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const dropbtn = dropdown.querySelector('.dropbtn');
        const dropdownContent = dropdown.querySelector('.dropdown-content');

        if (dropbtn) {
            // For mobile: toggle on click
            dropbtn.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    const isOpen = dropdown.classList.contains('active');
                    
                    // Close other dropdowns
                    dropdowns.forEach(other => {
                        other.classList.remove('active');
                        const btn = other.querySelector('.dropbtn');
                        if (btn) btn.setAttribute('aria-expanded', 'false');
                    });

                    if (!isOpen) {
                        dropdown.classList.add('active');
                        dropbtn.setAttribute('aria-expanded', 'true');
                    } else {
                        dropbtn.setAttribute('aria-expanded', 'false');
                    }
                }
            });

            // Keyboard support
            dropbtn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (window.innerWidth <= 768) {
                        dropdown.classList.toggle('active');
                        const isOpen = dropdown.classList.contains('active');
                        dropbtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    }
                }
            });
        }

        // Keyboard navigation for dropdown links
        if (dropdownContent) {
            const links = dropdownContent.querySelectorAll('a');
            links.forEach((link, index) => {
                link.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const next = links[index + 1];
                        if (next) next.focus();
                    }
                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        const prev = links[index - 1];
                        if (prev) prev.focus();
                    }
                    if (e.key === 'Escape') {
                        dropdown.classList.remove('active');
                        if (dropbtn) {
                            dropbtn.focus();
                            dropbtn.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            });
        }
    });

    // Auto-dismiss alerts — ONLY success alerts (not errors)
    const successAlerts = document.querySelectorAll('.alert-success');
    if (successAlerts.length > 0) {
        setTimeout(() => {
            successAlerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) alert.remove();
                }, 500);
            });
        }, 5000);
    }

    // Close modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
        }
    });
});

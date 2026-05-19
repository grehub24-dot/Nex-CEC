/**
 * Nex CEC — Main JavaScript
 * Handles navigation, modals, alerts, and UI interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    // ============================
    // HAMBURGER MENU TOGGLE (Public Nav)
    // ============================
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');

    if (hamburger && navLinks) {
        function toggleNav(forceState) {
            const isActive = forceState !== undefined ? forceState : !navLinks.classList.contains('active');
            navLinks.classList.toggle('active', isActive);
            hamburger.setAttribute('aria-expanded', isActive ? 'true' : 'false');
            document.body.style.overflow = isActive ? 'hidden' : '';
        }

        hamburger.addEventListener('click', () => toggleNav());
        hamburger.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleNav();
            }
        });

        // Close nav when clicking outside
        document.addEventListener('click', (e) => {
            if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
                toggleNav(false);
            }
        });

        // Close nav on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                toggleNav(false);
            }
        });
    }

    // ============================
    // DROPDOWN MENUS (Public Nav)
    // ============================
    const dropdowns = document.querySelectorAll('.dropdown');
    let activeDropdown = null;

    dropdowns.forEach(dropdown => {
        const dropbtn = dropdown.querySelector('.dropbtn');
        const dropdownContent = dropdown.querySelector('.dropdown-content');

        if (!dropbtn) return;

        // Desktop: hover handled by CSS; focus-within for keyboard
        // Mobile: toggle on click
        const isMobile = () => window.innerWidth <= 768;

        function openDropdown() {
            // Close any other open dropdown
            if (activeDropdown && activeDropdown !== dropdown) {
                closeDropdown(activeDropdown);
            }
            dropdown.classList.add('active');
            dropbtn.setAttribute('aria-expanded', 'true');
            activeDropdown = dropdown;
        }

        function closeDropdown(el) {
            if (!el) el = dropdown;
            el.classList.remove('active');
            const btn = el.querySelector('.dropbtn');
            if (btn) btn.setAttribute('aria-expanded', 'false');
            if (activeDropdown === el) activeDropdown = null;
        }

        dropbtn.addEventListener('click', (e) => {
            if (isMobile()) {
                e.preventDefault();
                if (dropdown.classList.contains('active')) {
                    closeDropdown();
                } else {
                    openDropdown();
                }
            }
        });

        // Keyboard: Enter/Space toggle
        dropbtn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (dropdown.classList.contains('active')) {
                    closeDropdown();
                } else {
                    openDropdown();
                }
            }
        });

        // Keyboard navigation within dropdown
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
                        closeDropdown();
                        dropbtn.focus();
                    }
                });
            });
        }

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (isMobile() && !dropdown.contains(e.target)) {
                closeDropdown(dropdown);
            }
        });
    });

    // ============================
    // AUTO-DISMISS ALERTS (success only)
    // ============================
    const successAlerts = document.querySelectorAll('.alert-success');
    if (successAlerts.length > 0) {
        setTimeout(() => {
            successAlerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    if (alert.parentNode) alert.remove();
                }, 500);
            });
        }, 5000);
    }

    // ============================
    // CLOSE MODAL ON ESCAPE
    // ============================
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
        }
    });

    // ============================
    // SMOOTH SCROLL FOR ANCHOR LINKS
    // ============================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            const targetEl = document.querySelector(targetId);
            if (targetEl) {
                e.preventDefault();
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ============================
    // STICKY NAVBAR SHADOW ON SCROLL
    // ============================
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        let lastScroll = 0;
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            if (currentScroll > 50) {
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('navbar-scrolled');
            }
            lastScroll = currentScroll;
        }, { passive: true });
    }
});

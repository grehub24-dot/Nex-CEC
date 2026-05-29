# Nex CEC School UI Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign all portals (public, admin, staff, parent) with Notion-inspired design system — navy hero, purple CTA, pastel stage-tinted cards, Inter typography, decorative Three.js school-appropriate 3D elements.

**Architecture:** CSS custom properties (design tokens) → component classes → PHP include updates → JS enhancements. Six new CSS files, one new JS module, inheritance-safe updates to `style.css`. Each phase produces independently testable output.

**Tech Stack:** PHP 8.x, vanilla CSS (custom properties), vanilla JS, Three.js (existing CDN), FontAwesome 6 (existing CDN).

**Real file path prefix:** `Infotess-host/` (not `api/`). CSS in `Infotess-host/css/`, JS in `Infotess-host/js/`, PHP in `Infotess-host/api/`.

---

## File Map

### New Files
| # | File | Responsibility |
|---|------|---------------|
| F1 | `Infotess-host/css/design-tokens.css` | CSS custom properties: colors, spacing, rounded, typography scale, shadows, transitions |
| F2 | `Infotess-host/css/typography.css` | `@import url('Inter')`, base HTML tags, `.text-hero`, `.text-h1`–`.text-h4`, `.text-body`, `.text-sm`, `.text-caption`, `.text-micro` classes |
| F3 | `Infotess-host/css/layout.css` | `.container` (1200px), `.page-wrapper`, `.hero-band`, `.stats-bar`, card grids, responsive breakpoints (1024/768) |
| F4 | `Infotess-host/css/components.css` | Buttons (5 variants), cards (5 types), form inputs, badges, testimonials, stat counters, CTA banners |
| F5 | `Infotess-host/css/animations.css` | `.fade-up`, `.fade-in-left`, `.fade-in-right`, `.stagger-children`, count-up keyframes, reduced-motion query |
| F6 | `Infotess-host/css/3d-school.css` | `.school-3d-container` sizing, positioning, fallback SVG |
| F7 | `Infotess-host/js/school-3d.js` | Shared Three.js module: `initScene(containerId, sceneType)` with scene builders for school, books, envelope, frame, calendar, certificate |

### Modified Files
| # | File | Change |
|---|------|--------|
| M1 | `Infotess-host/api/includes/header.php` | Add new CSS + JS links, restructure nav to Notion-style sticky white bar, update announcement bar |
| M2 | `Infotess-host/api/includes/footer.php` | Restructure to navy footer with 3 columns + minimal portal footer |
| M3 | `Infotess-host/api/home.php` | Replace globe 3D with school-building scene, restructure hero to centered navy + right 3D, update feature cards to pastel-tinted, replace inline styles with CSS classes |
| M4 | `Infotess-host/api/includes/functions.php` | Update `renderSidebar()` to 240px white sidebar with purple active indicator, update `getSidebarMenu()` if needed |
| M5 | `Infotess-host/css/style.css` | Add override/compat section at bottom for new design tokens (keep existing styles to avoid breaking pages until they're refactored) |
| M6 | `Infotess-host/api/admin_dashboard.php` | Apply component classes, update stat cards, top bar |
| M7 | `Infotess-host/api/staff_dashboard.php` | Apply component classes |
| M8 | `Infotess-host/api/parent_dashboard.php` | Apply component classes |
| M9 | `Infotess-host/api/about.php` | Restructure with new layout + 3D books |
| M10 | `Infotess-host/api/contact.php` | Restructure with new layout + 3D envelope |
| M11 | `Infotess-host/api/gallery.php` | Restructure with new layout + 3D frame |
| M12 | `Infotess-host/api/news.php` | Restructure with new layout + 3D calendar |
| M13 | `Infotess-host/api/events.php` | Restructure with new layout + 3D calendar |
| M14 | `Infotess-host/api/register.php` | Update CTA enroll button styling |
| M15 | `Infotess-host/js/main.js` | Add mobile hamburger handler, scroll-trigger animations using IntersectionObserver, sidebar toggle |

---

## Phase 1: CSS Architecture Foundation

### Task 1: Create design-tokens.css

**Files:**
- Create: `Infotess-host/css/design-tokens.css`

- [ ] **Step 1: Create design-tokens.css with all custom properties**

```css
/* ========================================
   Nex CEC Design Tokens
   Notion-Inspired School Design System
   ======================================== */

:root {
  /* ---- Brand Colors ---- */
  --color-primary: #5645d4;
  --color-primary-pressed: #4534b3;
  --color-on-primary: #ffffff;
  --color-brand-navy: #0a1530;
  --color-brand-navy-deep: #070f24;
  --color-brand-navy-mid: #1a2a52;
  --color-link-blue: #0075de;
  --color-link-blue-pressed: #005bab;

  /* ---- Education-Stage Card Tints ---- */
  --color-tint-peach: #ffe8d4;     /* Creche / Early Childhood */
  --color-tint-rose: #fde0ec;      /* Nursery / KG */
  --color-tint-mint: #d9f3e1;      /* Primary (B1–B6) */
  --color-tint-lavender: #e6e0f5;  /* JHS (JHS1–JHS3) */
  --color-tint-sky: #dcecfa;       /* Extracurricular */
  --color-tint-yellow: #fef7d6;    /* Achievements */
  --color-tint-cream: #f8f5e8;     /* General content */

  /* ---- Neutrals ---- */
  --color-canvas: #ffffff;
  --color-surface: #f6f5f4;
  --color-surface-soft: #fafaf9;
  --color-hairline: #e5e3df;
  --color-hairline-soft: #ede9e4;
  --color-hairline-strong: #c8c4be;
  --color-ink-deep: #000000;
  --color-ink: #1a1a1a;
  --color-charcoal: #37352f;
  --color-slate: #5d5b54;
  --color-steel: #787671;
  --color-muted: #bbb8b1;
  --color-on-dark: #ffffff;
  --color-on-dark-muted: #a4a097;

  /* ---- Semantic ---- */
  --color-success: #1aae39;
  --color-warning: #dd5b00;
  --color-error: #e03131;

  /* ---- Typography Scale ---- */
  --font-family: 'Inter', -apple-system, system-ui, sans-serif;
  --text-hero-size: 72px;
  --text-hero-lh: 1.05;
  --text-hero-ls: -2px;
  --text-hero-weight: 700;
  --text-display-size: 48px;
  --text-display-lh: 1.10;
  --text-display-ls: -1px;
  --text-display-weight: 700;
  --text-h1-size: 40px;
  --text-h1-lh: 1.15;
  --text-h1-ls: -0.5px;
  --text-h1-weight: 700;
  --text-h2-size: 32px;
  --text-h2-lh: 1.20;
  --text-h2-ls: -0.3px;
  --text-h2-weight: 600;
  --text-h3-size: 24px;
  --text-h3-lh: 1.25;
  --text-h3-weight: 600;
  --text-h4-size: 20px;
  --text-h4-lh: 1.30;
  --text-h4-weight: 600;
  --text-body-size: 16px;
  --text-body-lh: 1.60;
  --text-body-weight: 400;
  --text-sm-size: 14px;
  --text-sm-lh: 1.50;
  --text-sm-weight: 400;
  --text-button-size: 14px;
  --text-button-lh: 1.30;
  --text-button-weight: 500;
  --text-caption-size: 13px;
  --text-caption-lh: 1.40;
  --text-caption-weight: 500;
  --text-micro-size: 11px;
  --text-micro-lh: 1.40;
  --text-micro-weight: 600;
  --text-micro-ls: 1px;

  /* ---- Spacing Scale ---- */
  --space-xxs: 4px;
  --space-xs: 8px;
  --space-sm: 12px;
  --space-md: 16px;
  --space-lg: 20px;
  --space-xl: 24px;
  --space-xxl: 32px;
  --space-xxxl: 40px;
  --space-section-sm: 48px;
  --space-section: 64px;
  --space-section-lg: 96px;
  --space-hero: 120px;

  /* ---- Rounded Corners ---- */
  --radius-xs: 4px;
  --radius-sm: 6px;
  --radius-md: 8px;
  --radius-lg: 12px;
  --radius-xl: 16px;
  --radius-xxl: 24px;
  --radius-full: 9999px;

  /* ---- Shadows ---- */
  --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
  --shadow-md: 0 2px 8px rgba(0,0,0,0.06);
  --shadow-lg: 0 4px 16px rgba(0,0,0,0.08);

  /* ---- Transitions ---- */
  --transition-fast: 150ms ease-out;
  --transition-base: 200ms ease;
  --transition-slow: 250ms ease-out;
}

/* ---- Mobile Typography Overrides ---- */
@media (max-width: 767px) {
  :root {
    --text-hero-size: 40px;
    --text-hero-ls: -1px;
    --text-display-size: 36px;
    --text-h1-size: 28px;
    --text-h2-size: 24px;
    --text-h3-size: 20px;
    --text-h4-size: 18px;
    --text-body-size: 15px;
    --text-sm-size: 13px;
    --space-section-lg: 48px;
    --space-hero: 80px;
  }
}
```

- [ ] **Step 2: Create typography.css**

```css
/* ========================================
   Nex CEC Typography
   ======================================== */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

html {
  font-family: var(--font-family);
  font-size: var(--text-body-size);
  line-height: var(--text-body-lh);
  color: var(--color-ink);
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* ---- Headings ---- */
h1, .text-h1 {
  font-size: var(--text-h1-size);
  font-weight: var(--text-h1-weight);
  line-height: var(--text-h1-lh);
  letter-spacing: var(--text-h1-ls);
  color: var(--color-charcoal);
  margin: 0 0 var(--space-md);
}
h2, .text-h2 {
  font-size: var(--text-h2-size);
  font-weight: var(--text-h2-weight);
  line-height: var(--text-h2-lh);
  letter-spacing: var(--text-h2-ls);
  color: var(--color-charcoal);
  margin: 0 0 var(--space-sm);
}
h3, .text-h3 {
  font-size: var(--text-h3-size);
  font-weight: var(--text-h3-weight);
  line-height: var(--text-h3-lh);
  color: var(--color-charcoal);
  margin: 0 0 var(--space-xs);
}
h4, .text-h4 {
  font-size: var(--text-h4-size);
  font-weight: var(--text-h4-weight);
  line-height: var(--text-h4-lh);
  color: var(--color-charcoal);
  margin: 0 0 var(--space-xs);
}

/* ---- Hero Display ---- */
.text-hero {
  font-size: var(--text-hero-size);
  font-weight: var(--text-hero-weight);
  line-height: var(--text-hero-lh);
  letter-spacing: var(--text-hero-ls);
  color: var(--color-on-dark);
}

.text-display {
  font-size: var(--text-display-size);
  font-weight: var(--text-display-weight);
  line-height: var(--text-display-lh);
  letter-spacing: var(--text-display-ls);
  color: var(--color-charcoal);
}

/* ---- Body ---- */
p, .text-body {
  font-size: var(--text-body-size);
  line-height: var(--text-body-lh);
  color: var(--color-slate);
  margin: 0 0 var(--space-md);
}
.text-sm {
  font-size: var(--text-sm-size);
  line-height: var(--text-sm-lh);
  color: var(--color-slate);
}

/* ---- Utility ---- */
.text-caption {
  font-size: var(--text-caption-size);
  font-weight: var(--text-caption-weight);
  line-height: var(--text-caption-lh);
  color: var(--color-steel);
}
.text-micro {
  font-size: var(--text-micro-size);
  font-weight: var(--text-micro-weight);
  line-height: var(--text-micro-lh);
  letter-spacing: var(--text-micro-ls);
  text-transform: uppercase;
  color: var(--color-muted);
}
.text-center { text-align: center; }
.text-on-dark { color: var(--color-on-dark); }
.text-on-dark-muted { color: var(--color-on-dark-muted); }
```

- [ ] **Step 3: Create layout.css**

```css
/* ========================================
   Nex CEC Layout System
   ======================================== */

*, *::before, *::after {
  box-sizing: border-box;
}

body {
  margin: 0;
  padding: 0;
  background: var(--color-canvas);
}

/* ---- Container ---- */
.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--space-xl);
}

/* ---- Page Wrapper (portal layout) ---- */
.page-wrapper {
  display: flex;
  min-height: 100vh;
}

.main-content {
  flex: 1;
  padding: var(--space-xl);
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}

/* ---- Hero Band (full-width navy) ---- */
.hero-band {
  background: var(--color-brand-navy);
  color: var(--color-on-dark);
  padding: var(--space-hero) 0;
  position: relative;
  overflow: hidden;
}

.hero-band-narrow {
  padding: var(--space-section) 0;
}

/* ---- Stats Bar ---- */
.stats-bar {
  background: var(--color-surface);
  padding: var(--space-section-sm) 0;
}

/* ---- Feature Section ---- */
.section-block {
  padding: var(--space-section) 0;
}

.section-block-sm {
  padding: var(--space-section-sm) 0;
}

/* ---- CTA Banner ---- */
.cta-banner {
  background: var(--color-surface);
  padding: var(--space-section) 0;
  text-align: center;
  border-radius: var(--radius-lg);
}

.cta-banner-dark {
  background: var(--color-brand-navy);
  color: var(--color-on-dark);
  padding: var(--space-section) 0;
  text-align: center;
  border-radius: var(--radius-lg);
}

/* ---- Grid Systems ---- */
.grid-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-xl);
}

.grid-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--space-xl);
}

.grid-auto {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--space-xl);
}

.grid-4 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-xl);
}

/* ---- Split Layout ---- */
.split-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-xxl);
  align-items: center;
}

/* ---- Sidebar ---- */
.sidebar {
  width: 240px;
  min-width: 240px;
  background: var(--color-canvas);
  border-right: 1px solid var(--color-hairline);
  padding: var(--space-md) var(--space-xs);
  display: flex;
  flex-direction: column;
  height: 100vh;
  position: sticky;
  top: 0;
  overflow-y: auto;
}

.sidebar-header {
  padding: var(--space-lg) var(--space-sm);
  border-bottom: 1px solid var(--color-hairline);
  margin-bottom: var(--space-xs);
}

.sidebar-menu {
  list-style: none;
  padding: 0;
  margin: 0;
}

.sidebar-menu li {
  margin: 2px 0;
}

.sidebar-menu a {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-xs) var(--space-sm);
  color: var(--color-charcoal);
  font-size: var(--text-sm-size);
  font-weight: 400;
  text-decoration: none;
  border-radius: var(--radius-sm);
  border-left: 3px solid transparent;
  transition: var(--transition-fast);
}

.sidebar-menu a:hover,
.sidebar-menu a:active {
  background: var(--color-surface);
}

.sidebar-menu a.active {
  color: var(--color-primary);
  border-left-color: var(--color-primary);
  background: var(--color-tint-lavender);
  font-weight: 500;
}

.sidebar-menu a i {
  width: 18px;
  text-align: center;
  color: var(--color-steel);
}

.sidebar-menu a.active i {
  color: var(--color-primary);
}

.sidebar-section-label {
  font-size: var(--text-micro-size);
  font-weight: var(--text-micro-weight);
  letter-spacing: var(--text-micro-ls);
  text-transform: uppercase;
  color: var(--color-muted);
  padding: var(--space-md) var(--space-sm) var(--space-xs);
}

/* ---- Top Bar ---- */
.top-bar {
  height: 56px;
  background: var(--color-canvas);
  border-bottom: 1px solid var(--color-hairline);
  display: flex;
  align-items: center;
  padding: 0 var(--space-xl);
  position: sticky;
  top: 0;
  z-index: 100;
}

.top-bar-left {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.top-bar-center {
  flex: 1;
  text-align: center;
}

.top-bar-right {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.breadcrumb {
  font-size: var(--text-sm-size);
  color: var(--color-steel);
}

.breadcrumb a {
  color: var(--color-link-blue);
  text-decoration: none;
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
  cursor: pointer;
}

/* ---- Footer ---- */
.footer-navy {
  background: var(--color-brand-navy);
  color: var(--color-on-dark);
  padding: var(--space-section) var(--space-xl) var(--space-xxl);
}

.footer-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: var(--space-xxl);
  max-width: 1200px;
  margin: 0 auto;
}

.footer-heading {
  font-size: var(--text-sm-size);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--color-on-dark);
  margin-bottom: var(--space-md);
}

.footer-link {
  color: var(--color-on-dark-muted);
  text-decoration: none;
  font-size: var(--text-sm-size);
  display: block;
  padding: var(--space-xxs) 0;
  transition: var(--transition-fast);
}

.footer-link:hover {
  color: var(--color-on-dark);
}

.footer-divider {
  border: none;
  border-top: 1px solid rgba(255,255,255,0.15);
  margin: var(--space-xl) 0;
  max-width: 1200px;
  margin-left: auto;
  margin-right: auto;
}

.footer-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  max-width: 1200px;
  margin: 0 auto;
  font-size: var(--text-caption-size);
  color: var(--color-on-dark-muted);
}

.footer-bottom-links {
  display: flex;
  gap: var(--space-lg);
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-bottom-links a {
  color: var(--color-on-dark-muted);
  text-decoration: none;
}

.footer-bottom-links a:hover {
  color: var(--color-on-dark);
}

/* ---- Portal Footer ---- */
.portal-footer {
  text-align: center;
  padding: var(--space-md);
  font-size: var(--text-caption-size);
  color: var(--color-muted);
  border-top: 1px solid var(--color-hairline);
}

/* ---- Navigation (Public) ---- */
.nav-white {
  background: var(--color-canvas);
  border-bottom: 1px solid var(--color-hairline);
  height: 56px;
  position: sticky;
  top: 0;
  z-index: 200;
  display: flex;
  align-items: center;
}

.nav-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--space-xl);
  width: 100%;
}

.nav-links {
  display: flex;
  align-items: center;
  gap: var(--space-lg);
  list-style: none;
  padding: 0;
  margin: 0;
}

.nav-links a {
  color: var(--color-charcoal);
  text-decoration: none;
  font-size: var(--text-sm-size);
  font-weight: 400;
  padding: var(--space-xs) 0;
  transition: var(--transition-fast);
}

.nav-links a:hover {
  color: var(--color-primary);
}

/* ---- Mobile Overlay ---- */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  z-index: 300;
}

.sidebar-overlay.active {
  display: block;
}

.hamburger-btn {
  display: none;
  background: none;
  border: none;
  font-size: 20px;
  cursor: pointer;
  padding: var(--space-xs);
  color: var(--color-charcoal);
}

.sidebar-close-btn {
  display: none;
  background: none;
  border: none;
  font-size: 18px;
  cursor: pointer;
  position: absolute;
  top: 16px;
  right: 16px;
  color: var(--color-charcoal);
}

/* ---- Responsive ---- */
@media (max-width: 1023px) {
  .sidebar {
    position: fixed;
    left: -280px;
    top: 0;
    z-index: 400;
    width: 280px;
    min-width: 280px;
    transition: left var(--transition-slow);
    box-shadow: var(--shadow-lg);
  }
  .sidebar.open {
    left: 0;
  }
  .hamburger-btn {
    display: block;
  }
  .sidebar-close-btn {
    display: block;
  }
  .grid-4 {
    grid-template-columns: repeat(2, 1fr);
  }
  .footer-grid {
    grid-template-columns: 1fr 1fr;
  }
  .nav-links {
    display: none;
  }
  .nav-links.open {
    display: flex;
    flex-direction: column;
    position: fixed;
    inset: 0;
    background: var(--color-canvas);
    z-index: 300;
    padding: var(--space-xxl);
    gap: var(--space-md);
  }
}

@media (max-width: 767px) {
  .grid-3, .grid-2, .grid-4 {
    grid-template-columns: 1fr;
  }
  .split-layout {
    grid-template-columns: 1fr;
  }
  .footer-grid {
    grid-template-columns: 1fr;
  }
  .footer-bottom {
    flex-direction: column;
    gap: var(--space-xs);
    text-align: center;
  }
  .container {
    padding: 0 var(--space-md);
  }
  .main-content {
    padding: var(--space-md);
  }
  .hero-band {
    padding: var(--space-hero) 0;
  }
}

/* ---- Skip Link (a11y) ---- */
.skip-link {
  position: absolute;
  top: -999px;
  left: 0;
  background: var(--color-primary);
  color: var(--color-on-primary);
  padding: var(--space-xs) var(--space-md);
  z-index: 9999;
  text-decoration: none;
}
.skip-link:focus {
  top: 0;
}
```

- [ ] **Step 4: Create components.css**

```css
/* ========================================
   Nex CEC Components
   ======================================== */

/* ---- Buttons ---- */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-xs);
  font-family: var(--font-family);
  font-size: var(--text-button-size);
  font-weight: var(--text-button-weight);
  line-height: var(--text-button-lh);
  padding: 10px 18px;
  border-radius: var(--radius-md);
  border: none;
  cursor: pointer;
  text-decoration: none;
  transition: var(--transition-fast);
  white-space: nowrap;
}

.btn:active {
  transform: scale(0.97);
}

.btn-primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}
.btn-primary:active {
  background: var(--color-primary-pressed);
}

.btn-on-dark {
  background: var(--color-on-dark);
  color: var(--color-ink);
}
.btn-on-dark:active {
  background: var(--color-hairline);
}

.btn-secondary {
  background: transparent;
  color: var(--color-charcoal);
  border: 1px solid var(--color-hairline-strong);
}
.btn-secondary:active {
  background: var(--color-surface);
}

.btn-secondary-on-dark {
  background: transparent;
  color: var(--color-on-dark);
  border: 1px solid var(--color-on-dark-muted);
}
.btn-secondary-on-dark:active {
  background: rgba(255,255,255,0.1);
}

.btn-link {
  background: transparent;
  color: var(--color-link-blue);
  padding: 0;
}
.btn-link:active {
  color: var(--color-link-blue-pressed);
}

.btn-lg {
  padding: 14px 28px;
  font-size: 16px;
}

/* ---- Cards ---- */
.card {
  background: var(--color-canvas);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
}

.card-feature {
  border-radius: var(--radius-lg);
  padding: var(--space-xxl);
}

.card-tint-peach { background: var(--color-tint-peach); }
.card-tint-rose { background: var(--color-tint-rose); }
.card-tint-mint { background: var(--color-tint-mint); }
.card-tint-lavender { background: var(--color-tint-lavender); }
.card-tint-sky { background: var(--color-tint-sky); }
.card-tint-yellow { background: var(--color-tint-yellow); }
.card-tint-cream { background: var(--color-tint-cream); }

.card-testimonial {
  background: var(--color-canvas);
  border: 1px solid var(--color-hairline);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
}

.card-stat {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-section-sm);
  text-align: center;
}

.card-stat h3 {
  font-size: 36px;
  font-weight: 600;
  color: var(--color-brand-navy);
  margin: 0 0 var(--space-xs);
}

.stat-icon {
  font-size: 32px;
  color: var(--color-primary);
  margin-bottom: var(--space-sm);
}

/* ---- Form Inputs ---- */
.input {
  font-family: var(--font-family);
  font-size: var(--text-body-size);
  color: var(--color-ink);
  background: var(--color-canvas);
  border: 1px solid var(--color-hairline-strong);
  border-radius: var(--radius-md);
  padding: var(--space-sm) var(--space-md);
  height: 44px;
  width: 100%;
  outline: none;
  transition: var(--transition-fast);
}

.input:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 2px rgba(86, 69, 212, 0.15);
}

.input::placeholder {
  color: var(--color-steel);
}

textarea.input {
  height: auto;
  min-height: 100px;
  padding: var(--space-sm) var(--space-md);
}

/* ---- Badges ---- */
.badge {
  display: inline-flex;
  align-items: center;
  font-size: var(--text-caption-size);
  font-weight: var(--text-caption-weight);
  line-height: var(--text-caption-lh);
  padding: 4px 10px;
  border-radius: var(--radius-full);
}

.badge-primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.badge-tag {
  background: var(--color-tint-lavender);
  color: var(--color-primary);
  border-radius: var(--radius-sm);
}

/* ---- Testimonials ---- */
.testimonial-stars {
  color: #f5b342;
  margin-bottom: var(--space-sm);
}

.testimonial-quote {
  font-size: var(--text-body-size);
  line-height: var(--text-body-lh);
  color: var(--color-charcoal);
  font-style: italic;
  margin-bottom: var(--space-md);
}

.testimonial-author {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.testimonial-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--color-tint-lavender);
  color: var(--color-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: var(--text-body-size);
}

/* ---- 3D Container ---- */
.school-3d-container {
  position: relative;
  overflow: hidden;
}

.school-3d-container.hero-3d {
  position: absolute;
  right: 0;
  top: 0;
  width: 50%;
  height: 100%;
  pointer-events: none;
}

.school-3d-container.content-3d {
  width: 400px;
  height: 400px;
  margin: 0 auto;
}

@media (max-width: 767px) {
  .school-3d-container.hero-3d {
    width: 100%;
    height: 200px;
    position: relative;
    margin-top: var(--space-xl);
  }
  .school-3d-container.content-3d {
    width: 280px;
    height: 280px;
  }
}
```

- [ ] **Step 5: Create animations.css**

```css
/* ========================================
   Nex CEC Animations
   ======================================== */

@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(24px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeInLeft {
  from {
    opacity: 0;
    transform: translateX(-24px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes fadeInRight {
  from {
    opacity: 0;
    transform: translateX(24px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes countUp {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}

.anim-fade-up {
  opacity: 0;
  animation: fadeUp 500ms ease-out forwards;
}

.anim-fade-in-left {
  opacity: 0;
  animation: fadeInLeft 500ms ease-out forwards;
}

.anim-fade-in-right {
  opacity: 0;
  animation: fadeInRight 500ms ease-out forwards;
}

.anim-stagger > * {
  opacity: 0;
  animation: fadeUp 400ms ease-out forwards;
}

.anim-stagger > *:nth-child(1) { animation-delay: 0ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 100ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 200ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 300ms; }
.anim-stagger > *:nth-child(5) { animation-delay: 400ms; }
.anim-stagger > *:nth-child(6) { animation-delay: 500ms; }

/* ---- Reduced Motion ---- */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
  .anim-fade-up,
  .anim-fade-in-left,
  .anim-fade-in-right {
    opacity: 1;
  }
}
```

- [ ] **Step 6: Create 3d-school.css**

```css
/* ========================================
   Nex CEC 3D School Elements
   Styles for Three.js container and SVG fallback
   ======================================== */

.school-3d-container {
  position: relative;
}

.school-3d-container.hero-3d {
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 50%;
  height: 80%;
  pointer-events: none;
}

.school-3d-container.content-3d {
  width: 400px;
  height: 400px;
  margin: 0 auto var(--space-xl);
}

.school-3d-container canvas {
  display: block;
  width: 100% !important;
  height: 100% !important;
}

/* ---- SVG Fallback ---- */
.school-3d-fallback {
  display: none;
  width: 100%;
  height: 100%;
  align-items: center;
  justify-content: center;
  color: var(--color-on-dark-muted);
  font-size: var(--text-sm-size);
}

.no-webgl .school-3d-fallback {
  display: flex;
}

/* ---- Scroll-trigger visibility ---- */
.anim-on-scroll {
  opacity: 0;
  transform: translateY(24px);
  transition: opacity 500ms ease-out, transform 500ms ease-out;
}

.anim-on-scroll.visible {
  opacity: 1;
  transform: translateY(0);
}

@media (max-width: 767px) {
  .school-3d-container.hero-3d {
    position: relative;
    transform: none;
    width: 100%;
    height: 200px;
    margin-top: var(--space-xl);
  }
  .school-3d-container.content-3d {
    width: 280px;
    height: 280px;
  }
}
```

- [ ] **Step 7: Commit Phase 1 foundation**

```bash
git add Infotess-host/css/design-tokens.css
git add Infotess-host/css/typography.css
git add Infotess-host/css/layout.css
git add Infotess-host/css/components.css
git add Infotess-host/css/animations.css
git add Infotess-host/css/3d-school.css
git commit -m "feat(css): add Notion-inspired design system foundation

Create 6 CSS files with custom property tokens, Inter typography,
layout system, component classes, animations, and 3D container styles."
```

---

## Phase 2: Header & Navigation

### Task 2: Update header.php with new navigation + CSS links

**Files:**
- Modify: `Infotess-host/api/includes/header.php` (lines 1–131)

- [ ] **Step 1: Replace existing `<head>` CSS links with new design system files**

Replace this in `header.php`:
```php
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/style.css">
```

With:
```php
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/design-tokens.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/typography.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/layout.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/components.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/animations.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/3d-school.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/style.css"><!-- legacy compat -->
```

- [ ] **Step 2: Replace the announcement bar with simplified version**

Replace lines 56–62 (the full `.top-announcement` div) with:
```php
    <!-- Top announcement bar (simplified) -->
    <div class="top-announcement" style="background: var(--color-brand-navy); color: var(--color-on-dark-muted); font-size: 13px; padding: 6px 0; display: none;">
        <div class="container" style="display: flex; justify-content: center; gap: 24px;">
            <span><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($settings['school_phone'] ?? '+233 XX XXX XXXX'); ?></span>
            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($settings['school_email'] ?? 'info@school.edu.gh'); ?></span>
        </div>
    </div>
```

- [ ] **Step 3: Replace the `<nav class="navbar">` with new Notion-style navigation**

Replace lines 64–129 (from `<nav class="navbar">` to `</nav>`) with:

```php
    <!-- Navigation (Notion-style sticky white bar) -->
    <nav class="nav-white" role="navigation" aria-label="Main navigation">
        <div class="nav-inner">
            <a href="<?php echo $base_url; ?>index.php" class="logo" aria-label="<?php echo htmlspecialchars($school_name); ?> Home" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
                <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="<?php echo htmlspecialchars($school_name); ?> Logo" height="32" onerror="this.onerror=null;this.src='<?php echo $base_url; ?>images/chariot-logo.svg'">
                <span style="font-size: 16px; font-weight: 600; color: var(--color-charcoal);"><?php echo htmlspecialchars($school_name); ?></span>
            </a>

            <ul class="nav-links" role="menubar" id="navLinks">
                <li role="none"><a href="<?php echo $base_url; ?>index.php" role="menuitem" class="<?php echo ($current_page === 'home.php' || $current_page === 'index.php') ? 'active' : ''; ?>">Home</a></li>
                <li role="none"><a href="<?php echo $base_url; ?>about.php" role="menuitem" class="<?php echo ($current_page === 'about.php') ? 'active' : ''; ?>">About</a></li>
                <li role="none"><a href="<?php echo $base_url; ?>news.php" role="menuitem" class="<?php echo ($current_page === 'news.php') ? 'active' : ''; ?>">News</a></li>
                <li role="none"><a href="<?php echo $base_url; ?>events.php" role="menuitem" class="<?php echo ($current_page === 'events.php') ? 'active' : ''; ?>">Events</a></li>
                <li role="none"><a href="<?php echo $base_url; ?>gallery.php" role="menuitem" class="<?php echo ($current_page === 'gallery.php') ? 'active' : ''; ?>">Gallery</a></li>
                <li role="none"><a href="<?php echo $base_url; ?>contact.php" role="menuitem" class="<?php echo ($current_page === 'contact.php') ? 'active' : ''; ?>">Contact</a></li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (isset($_SESSION['has_children']) && $_SESSION['has_children']): ?>
                        <li role="none"><a href="<?php echo $base_url; ?>route_selector.php" class="btn btn-primary btn-sm" role="menuitem">Portals</a></li>
                    <?php elseif ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin'): ?>
                        <li role="none"><a href="<?php echo $base_url; ?>admin_dashboard.php" class="btn btn-primary btn-sm" role="menuitem">Admin Panel</a></li>
                    <?php elseif ($_SESSION['role'] === 'parent'): ?>
                        <li role="none"><a href="<?php echo $base_url; ?>parent_dashboard.php" class="btn btn-primary btn-sm" role="menuitem">Parent Portal</a></li>
                    <?php elseif ($_SESSION['role'] === 'staff' || $_SESSION['role'] === 'teacher'): ?>
                        <li role="none"><a href="<?php echo $base_url; ?>staff_dashboard.php" class="btn btn-primary btn-sm" role="menuitem">Staff Portal</a></li>
                    <?php else: ?>
                        <li role="none"><a href="<?php echo $base_url; ?>student_dashboard.php" class="btn btn-primary btn-sm" role="menuitem">Dashboard</a></li>
                    <?php endif; ?>
                    <li role="none"><a href="<?php echo $base_url; ?>logout.php" role="menuitem" style="color: var(--color-steel);"><i class="fas fa-sign-out-alt"></i></a></li>
                <?php else: ?>
                    <li role="none"><a href="<?php echo $base_url; ?>register.php" class="btn btn-primary" role="menuitem"><i class="fas fa-user-plus"></i> Enroll Now</a></li>
                    <li role="none"><a href="<?php echo $base_url; ?>login.php" class="btn btn-secondary" role="menuitem"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <?php endif; ?>
            </ul>

            <button class="hamburger-btn" id="mobileNavToggle" aria-label="Toggle navigation menu" aria-expanded="false" style="display: none;">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>
```

- [ ] **Step 4: Comment out the existing `<script>` for service worker (keep for later)**

Actually keep the service worker script block as-is. It's fine.

- [ ] **Step 5: Commit header update**

```bash
git add Infotess-host/api/includes/header.php
git commit -m "feat(header): update to Notion-style sticky white navigation

Replace old announcement bar and navbar with simplified
white nav bar with purple CTA button and responsive hamburger."
```

---

## Phase 3: Footer Redesign

### Task 3: Update footer.php with navy footer

**Files:**
- Modify: `Infotess-host/api/includes/footer.php` (lines 1–106)

- [ ] **Step 1: Replace footer content with new navy design**

Replace everything inside `</main>` to the end of file (lines 2–106) with:

```php
    </main>

    <!-- Public Footer (Navy) -->
    <footer class="footer-navy">
        <div class="footer-grid">
            <!-- Brand -->
            <div>
                <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="<?php echo htmlspecialchars($school_name); ?> Logo" height="40" onerror="this.onerror=null;this.src='<?php echo $base_url; ?>images/chariot-logo.svg'" style="margin-bottom: var(--space-sm);">
                <h3 style="color: var(--color-on-dark); font-size: 18px; font-weight: 600; margin: 0 0 4px;"><?php echo htmlspecialchars($school_name); ?></h3>
                <p style="color: var(--color-on-dark-muted); font-size: 14px; margin-bottom: var(--space-md);"><?php echo htmlspecialchars($school_motto); ?></p>
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
```

- [ ] **Step 2: Commit footer update**

```bash
git add Infotess-host/api/includes/footer.php
git commit -m "feat(footer): restructure to navy Notion-inspired design

Replaced old multi-column footer with clean navy footer
with 4-column grid, social icons, and minimal bottom bar."
```

---

## Phase 4: Homepage Redesign

### Task 4: Restructure home.php with new hero, stats, feature cards, testimonials, and 3D

**Files:**
- Modify: `Infotess-host/api/home.php` (lines 1–372)

- [ ] **Step 1: Replace the hero section with new centered navy hero + 3D container**

Replace lines 27–45 (the entire `<section class="hero">` block) with:

```php
<!-- Hero Section -->
<section class="hero-band" style="position: relative; overflow: hidden;">
    <!-- 3D School Building -->
    <div id="hero-3d-container" class="school-3d-container hero-3d"></div>

    <!-- Hero Content -->
    <div style="position: relative; z-index: 2; text-align: center; max-width: 900px; margin: 0 auto; padding: 0 24px;">
        <h1 class="text-hero" style="margin-bottom: var(--space-md);">Welcome to <?php echo htmlspecialchars($school_name); ?></h1>
        <p class="text-on-dark-muted" style="font-size: 18px; line-height: 1.7; max-width: 700px; margin: 0 auto var(--space-xl);">
            <?php echo htmlspecialchars($school_motto); ?> — Providing quality education from Creche through Junior High School in a safe, nurturing, and academically excellent environment.
        </p>
        <div style="display: flex; gap: var(--space-md); flex-wrap: wrap; justify-content: center; margin-bottom: var(--space-xxl);">
            <a href="register.php" class="btn btn-on-dark btn-lg"><i class="fas fa-user-plus"></i> Enroll Now</a>
            <a href="contact.php" class="btn btn-secondary-on-dark btn-lg">Contact Us</a>
        </div>
        <div style="display: flex; gap: var(--space-xxl); flex-wrap: wrap; justify-content: center;">
            <span style="color: var(--color-on-dark-muted); font-size: 14px;"><i class="fas fa-calendar-check" style="color: var(--color-primary); margin-right: 6px;"></i> 18+ Years of Excellence</span>
            <span style="color: var(--color-on-dark-muted); font-size: 14px;"><i class="fas fa-chalkboard-teacher" style="color: var(--color-primary); margin-right: 6px;"></i> Dedicated Staff</span>
            <span style="color: var(--color-on-dark-muted); font-size: 14px;"><i class="fas fa-users" style="color: var(--color-primary); margin-right: 6px;"></i> Holistic Education</span>
        </div>
    </div>
</section>
```

- [ ] **Step 2: Replace stats section**

Replace lines 47–73 (the `<section class="stats-section">` block) with:

```php
<!-- Stats Section -->
<section class="stats-bar">
    <div class="container">
        <div class="grid-4 anim-stagger" id="statsGrid">
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <h3><?php echo number_format($student_count); ?></h3>
                <p class="text-sm" style="color: var(--color-steel); margin: 0;">Students Enrolled</p>
            </div>
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3><?php echo number_format($staff_count); ?></h3>
                <p class="text-sm" style="color: var(--color-steel); margin: 0;">Staff Members</p>
            </div>
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                <h3><?php echo $class_count; ?>+</h3>
                <p class="text-sm" style="color: var(--color-steel); margin: 0;">Class Levels</p>
            </div>
            <div class="card-stat">
                <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                <h3><?php echo $years_count; ?>+</h3>
                <p class="text-sm" style="color: var(--color-steel); margin: 0;">Years of Impact</p>
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 3: Replace "What We Offer" section with pastel-tinted cards**

Replace lines 75–115 (the `<section class="features-section">` block) with:

```php
<!-- What We Offer -->
<section class="section-block">
    <div class="container">
        <h2 class="text-h2 text-center" style="margin-bottom: var(--space-xs);">What We Offer</h2>
        <p class="text-sm text-center" style="max-width: 600px; margin: 0 auto var(--space-xxl); color: var(--color-steel);">
            Comprehensive educational programmes designed to nurture every child's potential from early childhood through junior high school.
        </p>
        <div class="grid-3 anim-stagger" id="featuresGrid">
            <div class="card-feature card-tint-peach">
                <div style="font-size: 36px; margin-bottom: var(--space-md);"><i class="fas fa-baby" style="color: var(--color-brand-orange);"></i></div>
                <h3 class="text-h3">Early Childhood</h3>
                <p class="text-sm" style="color: var(--color-charcoal);">Creche, Nursery, and Kindergarten programmes designed to spark curiosity, creativity, and a lifelong love for learning.</p>
                <a href="about.php#early-childhood" class="btn btn-link" style="margin-top: var(--space-sm);">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-feature card-tint-mint">
                <div style="font-size: 36px; margin-bottom: var(--space-md);"><i class="fas fa-book-open" style="color: var(--color-brand-green);"></i></div>
                <h3 class="text-h3">Primary Education</h3>
                <p class="text-sm" style="color: var(--color-charcoal);">Basic 1 to 6 with a comprehensive curriculum covering core subjects, creative arts, ICT, and physical education.</p>
                <a href="about.php#primary" class="btn btn-link" style="margin-top: var(--space-sm);">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-feature card-tint-lavender">
                <div style="font-size: 36px; margin-bottom: var(--space-md);"><i class="fas fa-graduation-cap" style="color: var(--color-primary);"></i></div>
                <h3 class="text-h3">Junior High School</h3>
                <p class="text-sm" style="color: var(--color-charcoal);">JHS 1 to 3 preparing students for the BECE with strong academics, practical skills, and character formation.</p>
                <a href="about.php#jhs" class="btn btn-link" style="margin-top: var(--space-sm);">Learn More <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 4: Replace About Preview section with modern split layout**

Replace lines 117–132 (the `<section class="section">` about block) with:

```php
<!-- About Preview -->
<section class="section-block" style="background: var(--color-surface);">
    <div class="container">
        <div class="split-layout">
            <div>
                <span class="badge badge-primary" style="margin-bottom: var(--space-sm);">Our School</span>
                <h2 class="text-h2" style="margin-bottom: var(--space-md);">Nurturing Excellence, Building Character</h2>
                <p><?php echo htmlspecialchars($school_name); ?> is a nurturing learning environment dedicated to building strong academic foundations, character development, and holistic growth for every child from Creche to JHS 3.</p>
                <p>Our school follows the Ghana Education Service curriculum while fostering creativity, discipline, and a love for lifelong learning. We believe in partnering with parents to provide the best possible educational experience for every child.</p>
                <a href="about.php" class="btn btn-primary" style="margin-top: var(--space-sm);"><i class="fas fa-arrow-right"></i> Learn More About Us</a>
            </div>
            <div style="text-align: center;">
                <!-- 3D Books -->
                <div id="about-3d-preview" class="school-3d-container content-3d" style="width: 100%; max-width: 400px; height: 300px; margin: 0 auto;"></div>
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 5: Replace testimonials section**

Replace lines 134–195 (the `<section class="testimonials-section">` block) with:

```php
<!-- Testimonials -->
<section class="section-block">
    <div class="container">
        <h2 class="text-h2 text-center" style="margin-bottom: var(--space-xs);">What Parents Say</h2>
        <p class="text-sm text-center" style="max-width: 600px; margin: 0 auto var(--space-xxl); color: var(--color-steel);">
            Hear from our community of parents and guardians about their experience with our school.
        </p>
        <div class="grid-3 anim-stagger" id="testimonialsGrid">
            <div class="card-testimonial">
                <div class="testimonial-stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <blockquote class="testimonial-quote">"The care and attention my child receives at <?php echo htmlspecialchars($school_name); ?> is outstanding. I've seen remarkable growth in both academics and confidence."</blockquote>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">A</div>
                    <div>
                        <strong style="font-size: 14px;">Parent of KG 2 Student</strong>
                        <p style="font-size: 13px; color: var(--color-steel); margin: 0;">Current Parent</p>
                    </div>
                </div>
            </div>
            <div class="card-testimonial">
                <div class="testimonial-stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <blockquote class="testimonial-quote">"The dedicated teachers and small class sizes make all the difference. My child loves going to school every day!"</blockquote>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">M</div>
                    <div>
                        <strong style="font-size: 14px;">Parent of B4 Student</strong>
                        <p style="font-size: 13px; color: var(--color-steel); margin: 0;">Current Parent</p>
                    </div>
                </div>
            </div>
            <div class="card-testimonial">
                <div class="testimonial-stars">
                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                </div>
                <blockquote class="testimonial-quote">"Excellent preparation for the BECE. The academic standards are high, and the moral foundation my child received is invaluable."</blockquote>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">E</div>
                    <div>
                        <strong style="font-size: 14px;">Parent of JHS Graduate</strong>
                        <p style="font-size: 13px; color: var(--color-steel); margin: 0;">Alumni Parent</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 6: Replace CTA section**

Replace lines 197–207 (the `<section class="cta-section">` block) with:

```php
<!-- CTA Banner -->
<section class="section-block" style="background: var(--color-surface);">
    <div class="container" style="text-align: center; max-width: 700px;">
        <h2 class="text-h2" style="margin-bottom: var(--space-sm);">Enroll Your Child Today</h2>
        <p style="margin-bottom: var(--space-xl); color: var(--color-steel);">Give your child the best foundation for a bright future. Registration is now open for all levels — Creche through JHS 3.</p>
        <div style="display: flex; gap: var(--space-md); justify-content: center; flex-wrap: wrap;">
            <a href="register.php" class="btn btn-primary btn-lg"><i class="fas fa-user-plus"></i> Enroll Now</a>
            <a href="contact.php" class="btn btn-secondary btn-lg"><i class="fas fa-phone-alt"></i> Contact Us</a>
        </div>
    </div>
</section>
```

- [ ] **Step 7: Replace the 3D Globe script with new school-building 3D**

Replace lines 209–370 (the entire Three.js script block from `<script type="importmap">` through the closing `</script>`) with:

```php
<!-- Three.js 3D School Building -->
<script type="importmap">
{
    "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"
    }
}
</script>
<script type="module">
import * as THREE from 'three';

(function initSchoolScene() {
    const container = document.getElementById('hero-3d-container');
    if (!container) return;

    // Check WebGL support
    const canvas = document.createElement('canvas');
    const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
    if (!gl) {
        container.classList.add('no-webgl');
        return;
    }

    const width = container.offsetWidth || 400;
    const height = container.offsetHeight || 300;
    if (width < 100 || height < 100) return;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(35, width / height, 0.1, 1000);
    camera.position.set(3, 1.5, 4);
    camera.lookAt(0, 0, 0);

    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    container.appendChild(renderer.domElement);

    // --- Lights ---
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambientLight);
    const dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
    dirLight.position.set(5, 8, 5);
    dirLight.castShadow = true;
    scene.add(dirLight);
    const fillLight = new THREE.DirectionalLight(0x8888ff, 0.3);
    fillLight.position.set(-3, 2, -3);
    scene.add(fillLight);

    // --- School Building (main block) ---
    const buildingMat = new THREE.MeshPhongMaterial({
        color: 0x5645d4,
        emissive: 0x2a1a7a,
        emissiveIntensity: 0.1,
        shininess: 30,
    });
    const building = new THREE.Mesh(new THREE.BoxGeometry(1.6, 1.2, 1.0), buildingMat);
    building.position.y = 0.6;
    building.castShadow = true;
    scene.add(building);

    // Roof
    const roofMat = new THREE.MeshPhongMaterial({
        color: 0x0a1530,
        shininess: 10,
    });
    const roof = new THREE.Mesh(new THREE.ConeGeometry(1.1, 0.5, 4), roofMat);
    roof.position.y = 1.45;
    roof.rotation.y = Math.PI / 4;
    roof.castShadow = true;
    scene.add(roof);

    // Windows (row of small blocks)
    const windowMat = new THREE.MeshPhongMaterial({
        color: 0xffe8d4,
        emissive: 0xffcc80,
        emissiveIntensity: 0.3,
    });
    for (let i = -0.5; i <= 0.5; i += 0.5) {
        const windowBox = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.25, 0.05), windowMat);
        windowBox.position.set(i, 0.65, 0.51);
        scene.add(windowBox);
        const windowBox2 = new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.25, 0.05), windowMat);
        windowBox2.position.set(i, 0.65, -0.51);
        scene.add(windowBox2);
    }

    // Door
    const doorMat = new THREE.MeshPhongMaterial({ color: 0x1a2a52 });
    const door = new THREE.Mesh(new THREE.BoxGeometry(0.25, 0.4, 0.05), doorMat);
    door.position.set(0, 0.2, 0.51);
    scene.add(door);

    // Ground plane
    const groundMat = new THREE.MeshPhongMaterial({
        color: 0x1a2a52,
        transparent: true,
        opacity: 0.3,
    });
    const ground = new THREE.Mesh(new THREE.CircleGeometry(2.5, 32), groundMat);
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -0.01;
    ground.receiveShadow = true;
    scene.add(ground);

    // --- Floating books ---
    const bookMat = new THREE.MeshPhongMaterial({ color: 0xd9f3e1 });
    const bookMat2 = new THREE.MeshPhongMaterial({ color: 0xe6e0f5 });
    const bookMat3 = new THREE.MeshPhongMaterial({ color: 0xffe8d4 });

    const book1 = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.05, 0.2), bookMat);
    book1.position.set(-1.4, 1.0, 0.6);
    book1.rotation.z = 0.1;
    scene.add(book1);

    const book2 = new THREE.Mesh(new THREE.BoxGeometry(0.25, 0.05, 0.18), bookMat2);
    book2.position.set(-1.3, 1.1, 0.7);
    book2.rotation.z = -0.05;
    scene.add(book2);

    const book3 = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.05, 0.22), bookMat3);
    book3.position.set(-1.5, 1.2, 0.5);
    book3.rotation.z = 0.15;
    scene.add(book3);

    // --- Small floating stars/particles ---
    const starMat = new THREE.PointsMaterial({
        color: 0xffffff,
        size: 0.02,
        transparent: true,
        opacity: 0.4,
    });
    const starPositions = [];
    for (let i = 0; i < 60; i++) {
        const theta = Math.random() * Math.PI * 2;
        const phi = Math.acos(2 * Math.random() - 1);
        const r = 2.5 + Math.random() * 1.5;
        starPositions.push(
            r * Math.sin(phi) * Math.cos(theta),
            r * Math.cos(phi) * 0.5 + 0.5,
            r * Math.sin(phi) * Math.sin(theta)
        );
    }
    const starGeo = new THREE.BufferGeometry();
    starGeo.setAttribute('position', new THREE.Float32BufferAttribute(starPositions, 3));
    const stars = new THREE.Points(starGeo, starMat);
    scene.add(stars);

    // --- Animation ---
    function animate() {
        requestAnimationFrame(animate);
        building.rotation.y += 0.005;
        roof.rotation.y += 0.005;
        book1.rotation.y += 0.003;
        book2.rotation.y += 0.003;
        book3.rotation.y += 0.003;
        stars.rotation.y -= 0.001;
        renderer.render(scene, camera);
    }
    animate();

    // --- Resize ---
    window.addEventListener('resize', function() {
        const w = container.offsetWidth || 400;
        const h = container.offsetHeight || 300;
        if (w < 100 || h < 100) return;
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        renderer.setSize(w, h);
    });

    // --- Tab visibility ---
    document.addEventListener('visibilitychange', function() {
        // Three.js continues by default, but we can pause if needed
    });
})();
</script>
```

- [ ] **Step 8: Commit homepage redesign**

```bash
git add Infotess-host/api/home.php
git commit -m "feat(home): redesign with Notion-inspired hero and 3D school scene

Replace wireframe globe with school building 3D scene,
add pastel-tinted feature cards, testimonial cards,
stats bar with count-up layout, and split about section."
```

---

## Phase 5: Public Website Pages

### Task 5: Restructure about.php

**Files:**
- Modify: `Infotess-host/api/about.php`

- [ ] **Step 1: Add 3D books scene to about page**

Add after the hero section:
```php
<div id="about-3d-books" class="school-3d-container content-3d" style="margin: var(--space-xxl) auto;"></div>
```

- [ ] **Step 2: Add Three.js 3D books script section before `<?php require_once 'includes/footer.php'; ?>`**

```php
<script type="importmap">
{
    "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js"
    }
}
</script>
<script type="module">
import * as THREE from 'three';
(function initBooks() {
    const container = document.getElementById('about-3d-books');
    if (!container) return;
    const w = container.offsetWidth || 400;
    const h = container.offsetHeight || 300;
    if (w < 100 || h < 100) return;
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(40, w / h, 0.1, 1000);
    camera.position.set(2, 1.5, 3);
    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(w, h);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.appendChild(renderer.domElement);
    const ambient = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambient);
    const dir = new THREE.DirectionalLight(0xffffff, 0.8);
    dir.position.set(3, 5, 4);
    scene.add(dir);
    // Stacked books
    const colors = [0xe6e0f5, 0xd9f3e1, 0xffe8d4, 0xdcecfa];
    for (let i = 0; i < 4; i++) {
        const book = new THREE.Mesh(
            new THREE.BoxGeometry(0.8 - i * 0.08, 0.1, 0.5 - i * 0.05),
            new THREE.MeshPhongMaterial({ color: colors[i] })
        );
        book.position.set(0, i * 0.12, 0);
        book.rotation.z = (i - 1.5) * 0.06;
        scene.add(book);
    }
    // Grad cap on top
    const capMat = new THREE.MeshPhongMaterial({ color: 0x5645d4 });
    const capBase = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.03, 0.5), capMat);
    capBase.position.set(0, 0.55, 0);
    scene.add(capBase);
    const capTop = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.06, 0.08), capMat);
    capTop.position.set(0, 0.6, 0);
    scene.add(capTop);
    function animate() {
        requestAnimationFrame(animate);
        scene.rotation.y += 0.008;
        renderer.render(scene, camera);
    }
    animate();
    window.addEventListener('resize', function() {
        const w2 = container.offsetWidth || 400;
        const h2 = container.offsetHeight || 300;
        if (w2 < 100 || h2 < 100) return;
        camera.aspect = w2 / h2;
        camera.updateProjectionMatrix();
        renderer.setSize(w2, h2);
    });
})();
</script>
```

- [ ] **Step 3: Commit about page**

```bash
git add Infotess-host/api/about.php
git commit -m "feat(about): add 3D books scene and apply design system classes"
```

### Task 6: Restructure contact.php

**Files:**
- Modify: `Infotess-host/api/contact.php`

- [ ] **Step 1: Add contact 3D envelope container and script**

Same pattern as about.php but with an envelope 3D scene. Add container div, importmap, and module script with envelope geometry.

```php
<div id="contact-3d" class="school-3d-container content-3d" style="margin: var(--space-xxl) auto;"></div>
```

The script creates a simple envelope shape: a box for the body + a triangle for the flap, with a subtle pulse animation.

- [ ] **Step 2: Commit contact page**

```bash
git add Infotess-host/api/contact.php
git commit -m "feat(contact): add 3D envelope scene and apply design system"
```

### Task 7: Restructure gallery.php

- [ ] Step: Add 3D picture frame container and scene (rotating frame with image planes)
- [ ] Step: Commit gallery page

### Task 8: Restructure news.php and events.php

- [ ] Step: Add 3D calendar container and scene (tilted calendar geometry) — shared between news and events
- [ ] Step: Commit news and events pages

---

## Phase 6: Portal Sidebar & Top Bar

### Task 9: Update renderSidebar() in functions.php

**Files:**
- Modify: `Infotess-host/api/includes/functions.php`

- [ ] **Step 1: Remove inline styles from sidebar HTML**

Replace lines 330–335 (sidebar header with inline styles) with classes:
```php
$html .= '<div class="sidebar-header">';
$html .= '<button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close menu"><i class="fas fa-times"></i></button>';
$logoUrl = getCachedSchoolLogoUrl();
$html .= '<img src="' . htmlspecialchars($logoUrl) . '" alt="Logo" class="sidebar-logo" onerror="this.onerror=null;this.src=\'../images/aamusted.jpg\'">';
$html .= '<h3 style="font-size: 14px; font-weight: 600; color: var(--color-charcoal); margin: var(--space-sm) 0 0;">' . htmlspecialchars($schoolName) . '</h3>';
$html .= '<span class="text-micro">' . $roleLabel . '</span>';
$html .= '</div>';
```

- [ ] **Step 2: Replace <aside> tag with sidebar class**

Replace `<aside class="sidebar" id="sidebar">` (the full aside element gets the `sidebar` class from layout.css).

- [ ] **Step 3: Update the mobile toggle JavaScript to match new class names**

The script at the end of renderSidebar() already matches the selectors. Just ensure it uses `sidebar.classList.toggle('open')`.

- [ ] **Step 4: Commit functions.php sidebar update**

```bash
git add Infotess-host/api/includes/functions.php
git commit -m "feat(sidebar): update renderSidebar with new design system classes

Replace inline styles with CSS class references for 240px
white sidebar with purple active indicator."
```

### Task 10: Add top bar to portal pages

**Files:** Portal PHP files (admin_dashboard.php, etc.)

- [ ] Step: Add top bar HTML after `<?php require_once 'includes/db.php'; ?>` in portal pages
- [ ] Step: Commit top bar changes

---

## Phase 7: Portal Dashboard Pages

### Task 11: Update admin_dashboard.php

**Files:**
- Modify: `Infotess-host/api/admin_dashboard.php`

- [ ] Step: Replace stat card HTML with card-stat component classes
- [ ] Step: Update table styling with design system classes
- [ ] Step: Add scroll-trigger animation classes
- [ ] Step: Commit admin dashboard update

### Task 12: Update staff_dashboard.php

- [ ] Step: Apply component classes
- [ ] Step: Commit staff dashboard update

### Task 13: Update parent_dashboard.php

- [ ] Step: Apply component classes
- [ ] Step: Commit parent dashboard update

---

## Phase 8: JS Enhancements

### Task 14: Create school-3d.js shared module

**Files:**
- Create: `Infotess-host/js/school-3d.js`

- [ ] Step: Create shared Three.js module with `initScene(containerId, sceneType)` function
- [ ] Step: Export scene types: 'school', 'books', 'envelope', 'frame', 'calendar', 'certificate'
- [ ] Step: Commit 3D module

### Task 15: Update main.js with new interactions

**Files:**
- Modify: `Infotess-host/js/main.js`

- [ ] Step: Add IntersectionObserver for scroll-trigger animations (`.anim-on-scroll`)
- [ ] Step: Add mobile hamburger toggle for sidebar
- [ ] Step: Add mobile nav toggle for public pages
- [ ] Step: Add count-up animation for stats
- [ ] Step: Commit main.js update

---

## Self-Review Checklist

- [ ] **Spec coverage:** Does the plan cover all sections from the spec?
  - Colors → Task 1 (design-tokens.css) ✅
  - Typography → Task 1 (typography.css) ✅
  - Navigation → Task 2 (header.php) ✅
  - Homepage layout → Task 4 (home.php) ✅
  - 3D elements → Task 4 (home), Task 5 (about), Task 6 (contact), Task 14 (shared module) ✅
  - Components → Task 1 (components.css) ✅
  - Sidebar → Task 9 (functions.php), Task 1 (layout.css) ✅
  - Responsive → Task 1 (layout.css) ✅
  - Footer → Task 3 (footer.php) ✅
  - Animations → Task 1 (animations.css), Task 15 (main.js) ✅

- [ ] **Placeholder scan:** No "TBD", "TODO", or "implement later" patterns used in code blocks.

- [ ] **Type consistency:** CSS class names match across all tasks (`.btn`, `.card-feature`, `.card-tint-*`, `.hero-band`, `.nav-white`, `.footer-navy`, `.sidebar`, `.top-bar`).

- [ ] **Browser support:** CSS custom properties work in all modern browsers. `prefers-reduced-motion` degrades gracefully.

- [ ] **Accessibility:** Skip link preserved, semantic HTML maintained, aria labels on navigation.

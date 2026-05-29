# Nex CEC School UI Redesign — Design Spec

**Date:** 2026-05-28
**Status:** Approved
**Design System Reference:** `DESIGN.md` (project root)
**Design Inspiration:** Notion (adapted for school context)
**Project:** Nex Central Excel College — School Management Web App

---

## 1. Overview

Full UI/UX redesign of all four portals of the Nex CEC school management system:

| Portal | Pages | Audience |
|--------|-------|----------|
| **Public Website** | Home, About, Contact, Gallery, News, Events, Admissions | Parents, visitors |
| **Admin Portal** | Dashboard, Students, Staff, Classes, Subjects, Billing, Grades, Settings | School administrators |
| **Staff Portal** | Dashboard, Grades, Attendance, Lesson Notes, Payslip | Teachers |
| **Parent Portal** | Dashboard, Children, Payments, Grades, Lesson Notes | Parents |

The design follows a **Notion-inspired aesthetic** — clean, editorial, with deep navy hero bands, a signature purple primary CTA, pastel-tinted education-stage cards, and decorative 3D school-appropriate elements.

---

## 2. Color Palette

Defined in `DESIGN.md` under `colors:`.

### Brand Colors
| Token | Hex | Usage |
|-------|-----|-------|
| `primary` | `#5645d4` | Primary buttons, links, active indicators |
| `primary-pressed` | `#4534b3` | Button press state |
| `brand-navy` | `#0a1530` | Hero bands, footer |
| `brand-navy-deep` | `#070f24` | Hero gradient end |
| `brand-navy-mid` | `#1a2a52` | Subtle navy backgrounds |
| `link-blue` | `#0075de` | Inline text links |

### Education-Stage Card Tints
| Token | Hex | Stage |
|-------|-----|-------|
| `card-tint-peach` | `#ffe8d4` | Creche / Early Childhood |
| `card-tint-rose` | `#fde0ec` | Nursery / KG |
| `card-tint-mint` | `#d9f3e1` | Primary (B1–B6) |
| `card-tint-lavender` | `#e6e0f5` | JHS (JHS1–JHS3) |
| `card-tint-sky` | `#dcecfa` | Extracurricular / Special Programs |
| `card-tint-yellow` | `#fef7d6` | Achievements / Highlights |
| `card-tint-cream` | `#f8f5e8` | General content |

### Neutral / Canvas
| Token | Hex | Usage |
|-------|-----|-------|
| `canvas` | `#ffffff` | Page backgrounds |
| `surface` | `#f6f5f4` | Stats bar, CTA banners |
| `hairline` | `#e5e3df` | Card borders |
| `ink` | `#1a1a1a` | Body text |
| `charcoal` | `#37352f` | Headings |
| `slate` | `#5d5b54` | Secondary text |
| `steel` | `#787671` | Placeholder text |
| `muted` | `#bbb8b1` | Disabled / caption |

### Semantic
| Token | Hex |
|-------|-----|
| `semantic-success` | `#1aae39` |
| `semantic-warning` | `#dd5b00` |
| `semantic-error` | `#e03131` |

---

## 3. Typography

**Typeface:** Inter (Google Font) — single typeface throughout

### Scale
| Token | Size | Weight | Line Height | Letter Spacing | Used For |
|-------|------|--------|-------------|----------------|----------|
| hero-display | 72px (40px mobile) | 700 | 1.05 | -2px | Homepage hero heading |
| display-lg | 48px | 700 | 1.10 | -1px | Section hero headings |
| heading-1 (h1) | 40px | 700 | 1.15 | -0.5px | Page titles |
| heading-2 (h2) | 32px | 600 | 1.20 | -0.3px | Section headings |
| heading-3 (h3) | 24px | 600 | 1.25 | — | Card titles |
| heading-4 (h4) | 20px | 600 | 1.30 | — | Sub-section titles |
| body-md | 16px | 400 | 1.60 | — | Body text |
| body-sm | 14px | 400 | 1.50 | — | Secondary text |
| button-md | 14px | 500 | 1.30 | — | Buttons |
| caption | 13px | 500 | 1.40 | — | Badges, timestamps |
| micro-uppercase | 11px | 600 | 1.40 | +1px | Section headers in sidebar |

---

## 4. Spacing Scale

| Token | Value | Usage |
|-------|-------|-------|
| `xxs` | 4px | Tiny gaps |
| `xs` | 8px | Tight spacing |
| `sm` | 12px | Input padding |
| `md` | 16px | Standard gap |
| `lg` | 20px | Button padding |
| `xl` | 24px | Card padding / page margin |
| `xxl` | 32px | Section spacing |
| `xxxl` | 40px | Large sections |
| `section-sm` | 48px | Between sections (mobile) |
| `section` | 64px | Between sections |
| `section-lg` | 96px | Hero → next section |
| `hero` | 120px | Hero vertical padding |

**Page container:** `max-width: 1200px; margin: 0 auto; padding: 0 24px`

---

## 5. Rounded Corners

| Token | Value | Usage |
|-------|-------|-------|
| `xs` | 4px | Tags |
| `sm` | 6px | Small badges |
| `md` | 8px | Buttons, inputs |
| `lg` | 12px | Cards, stat rows |
| `xl` | 16px | Feature cards |
| `xxl` | 24px | Large cards |
| `full` | 9999px | Pill badges |

---

## 6. Layout Structure

### Public Website Pages
```
┌──────────────────────────────────────────────────────┐
│  [Sticky Navigation Bar: Logo | Links | CTA Button]   │
├──────────────────────────────────────────────────────┤
│  [Hero Section: Navy band, centered heading + 3D]    │
├──────────────────────────────────────────────────────┤
│  [Stats Bar: Surface bg, 4 counters]                 │
├──────────────────────────────────────────────────────┤
│  [Feature Cards: Pastel-tinted grid, 3 per row]      │
├──────────────────────────────────────────────────────┤
│  [About / Content Section]                           │
├──────────────────────────────────────────────────────┤
│  [Testimonials: White cards, alternating layout]     │
├──────────────────────────────────────────────────────┤
│  [CTA Banner: Surface band, centered button]         │
├──────────────────────────────────────────────────────┤
│  [Footer: Navy bg, 3-column grid]                    │
└──────────────────────────────────────────────────────┘
```

### Portal Pages
```
┌────────────────────────────────────────────────────────────┐
│  Top Bar: Logo (compact) | Breadcrumb | User Avatar (32px) │
├─────────────┬──────────────────────────────────────────────┤
│             │                                              │
│  Sidebar    │  Main Content Area                           │
│  (240px)    │  (max-width: 1200px, centered)               │
│             │                                              │
│  - Icons +  │  [Dashboard widgets / tables / forms /       │
│    text     │   cards / stat rows]                         │
│  - Purple   │                                              │
│    active   │                                              │
│    indicator│                                              │
│             │                                              │
├─────────────┴──────────────────────────────────────────────┤
│  Footer: Minimal — © 2026 Nex Central Excel College        │
└────────────────────────────────────────────────────────────┘
```

---

## 7. Navigation

### Public Website Navigation
- **Type:** Sticky white bar
- **Height:** 56px
- **Bg:** White, 1px bottom hairline
- **Left:** School logo
- **Right:** Home | About | Academics | Admissions | Gallery | Contact → [Enroll Now] button
- **CTA Button:** Primary purple (`#5645d4`)
- **Mobile:** Hamburger menu → full-screen overlay drawer

### Portal Sidebar
- **Width:** 240px
- **Bg:** White, 1px right hairline
- **Link style:** Charcoal `#37352f`, 14px/400
- **Active indicator:** 3px purple left border + purple text
- **Hover/press:** Surface `#f6f5f4` bg
- **Section headers:** 11px/600 uppercase, `#bbb8b1` muted
- **Gap:** 4px between items
- **Collapsible groups:** Parent → child items with chevron toggle

### Mobile Sidebar
- **Type:** Overlay drawer (not push)
- **Width:** 280px
- **Animation:** Slide from left, 250ms ease-out
- **Backdrop:** Dark semi-transparent overlay
- **Close:** Backdrop tap or Escape key

---

## 8. Homepage Layout

1. **Hero** — Full-width navy band (`#0a1530`)
   - Centered heading: school name + tagline
   - Subtitle: brief value proposition
   - Two buttons: [Enroll Now] (white bg) + [Contact Us] (outlined white)
   - 3D school building scene on the right (50% width)
   - Bottom: subtle gradient fade to next section
2. **Stats Bar** — Full-width surface band
   - 4 counters: Students Enrolled, Qualified Staff, Years Established, Classrooms
   - Count-up animation on scroll
3. **Feature Cards** — 1200px container
   - 3-column grid (→ 2→ 1 on mobile)
   - Each card: pastel tint matching stage (Peach→Creche, Mint→Primary, etc.)
   - Icon, heading, description, optional link
4. **About Section** — 1200px container, text left + optional image right
5. **Testimonials** — 1200px container
   - Alternating left/right fade-in cards
6. **CTA Banner** — Full-width surface band
   - Centered: "Ready to Enroll Your Child?" → [Enroll Now] button
7. **Footer** — Full-width navy band

---

## 9. 3D Decorative Elements

**Library:** Three.js (already loaded, swap existing globe scene)
**Module:** `js/school-3d.js` — shared module, each page calls `initScene(elementId, sceneType)`

### Per-Page Elements
| Page | 3D Element | Construction |
|------|------------|-------------|
| Home | School building + campus scene | Three.js primitives (BoxGeometry, etc.) |
| About | Floating books + grad cap | Book stack with fanning pages |
| Contact | Envelope + map pin | Floating with gentle pulse |
| Gallery | Picture frame with photos | Rotating frame cycling images |
| News/Events | Calendar with highlights | Tilted 3D calendar |
| Admissions | Certificate / scroll | Rolled certificate with ribbon |

### Technical Specs
- **Pixel ratio:** `Math.min(window.devicePixelRatio, 2)`
- **FPS cap:** Max 30fps on mobile
- **Tab visibility:** Pause animation when `document.hidden`
- **Fallback:** Static SVG if WebGL unavailable
- **Container:** Hero → right-aligned behind text, 50% width; Content pages → centered 400×400px box; Mobile → smaller, less detail
- **Visual style:** Clean geometry, beveled edges, navy/purple/pastel palette, slow auto-rotation (10–15s per rotation), ambient + directional light, shadow plane

---

## 10. Components

### Button System
| Variant | Bg | Text | Border | Where |
|---------|----|------|--------|-------|
| Primary | `#5645d4` | White | None | "Enroll Now", "Register" |
| Primary On-Dark | White | Charcoal | None | CTAs on navy hero |
| Secondary | Transparent | Charcoal | 1px `#c8c4be` | "Contact Us", "View Gallery" |
| Secondary On-Dark | Transparent | White | 1px `#a4a097` | "Contact Us" on hero |
| Link | Transparent | `#0075de` | None | Text links |

All: 8px rounded, 14px/500, `10px 18px` padding. Press: scale 0.97 + bg darken.

### Card System
| Type | Bg | Border | Radius | Padding | Used For |
|------|-----|--------|--------|---------|----------|
| Feature (tinted) | Pastel tint | None | 12px | 32px | "What We Offer" |
| Testimonial | White | 1px hairline | 12px | 24px | Parent quotes |
| Stats | Surface | None | 12px | 48px | Counters |
| Photo | White | 1px hairline | 12px | 16px | Gallery |
| CTA Banner | Surface or Navy | None | 12px | 64px | Call-to-action bands |

### Form Inputs
- Height: 44px, 8px rounded, 1px hairline border
- Focus: 2px purple border (`#5645d4`), outline offset
- Labels: Floating above input
- Padding: `12px 16px`

### Badges
| Variant | Bg | Text | Radius |
|---------|-----|------|--------|
| Primary | `#5645d4` | White | Full pill |
| Tag | `#e6e0f5` | `#5645d4` | 6px |

---

## 11. Animations & Transitions

### Scroll-Triggered (Public Pages)
| Element | Animation | Timing |
|---------|-----------|--------|
| Hero heading | Fade-in + slide-up | 500ms on load |
| Stats counters | Count-up | On scroll into view |
| Feature cards | Staggered fade-up | 200ms delay per card |
| Testimonial cards | Fade-in from sides | Alternating left/right |
| Footer | Simple fade-in | On scroll into view |

### Micro-Interactions
| Element | Press State | Transition |
|---------|------------|------------|
| Buttons | Scale 0.97, bg darken 15% | 150ms ease-out |
| Cards | translateY(-2px), shadow | 200ms ease |
| Sidebar links | Left border animate in | 150ms ease |
| Modals | Fade backdrop + scale content | 200ms ease |
| Mobile sidebar | Slide from left | 250ms ease-out |

### Reduced Motion
- `prefers-reduced-motion: reduce` → disable all motion, keep fades only
- Stagger delays for lists: 50–100ms apart
- Easing: `ease-out` for entrances, `ease-in-out` for transitions

---

## 12. Responsive Breakpoints

| Name | Width | Key Behaviors |
|------|-------|---------------|
| Desktop | ≥ 1024px | Full sidebar, multi-column grids |
| Tablet | 768–1023px | Sidebar icon-only, 2-column cards |
| Mobile | < 768px | Overlay sidebar, single column |

### Responsive Typography
| Element | Desktop | Mobile |
|---------|---------|--------|
| Hero heading | 72px | 40px |
| Section heading | 36px | 28px |
| Card title | 18px | 16px |
| Body | 16px | 15px |
| Small | 14px | 13px |

### Responsive Spacing
| Section gap | Desktop | Mobile |
|-------------|---------|--------|
| Hero → features | 96px | 48px |
| Card grid gap | 24px | 16px |
| Page padding | 24px | 16px |

---

## 13. Footer Design

### Public Footer
- **Bg:** Navy (`#0a1530`) — matching hero
- **Text:** White body 14px, headers 12px/600 uppercase
- **Columns:** Quick Links | Programs | Contact → 3 columns → 1 column mobile
- **Divider:** 1px hairline at `rgba(255,255,255,0.15)`
- **Padding:** 64px 24px 32px
- **Bottom bar:** © 2026 Nex Central Excel College. All rights reserved. | Privacy Policy | Terms of Service

### Portal Footer
- Minimal: `© 2026 Nex Central Excel College`
- 12px centered, hairline top border, 16px padding

---

## 14. Implementation Phases

### Phase 1: Foundation (CSS Architecture)
1. Create `css/design-tokens.css` — CSS custom properties from DESIGN.md
2. Create `css/typography.css` — Inter font import + type scale classes
3. Create `css/layout.css` — Grid system, containers, breakpoints
4. Create `css/components.css` — Button, card, input, badge component classes
5. Create `css/animations.css` — Scroll animations, micro-interactions
6. Create `css/3d-school.css` — Three.js shared module styles
7. Create `js/school-3d.js` — Three.js shared module

### Phase 2: Public Website (home.php first)
1. Home page — hero, stats, features, about, testimonials, CTA, footer
2. About page — about content + 3D books
3. Contact page — form + 3D envelope
4. Gallery page — photo grid + 3D frame
5. News/Events page — article list + 3D calendar
6. Admissions page — enrollment info + 3D certificate
7. Navigation bar component → shared include

### Phase 3: Admin Portal
1. Sidebar restyling — 240px, purple active indicator
2. Top bar — breadcrumbs, user avatar
3. Dashboard — stat cards, recent activity, quick actions
4. Tables — striped rows, consistent spacing
5. Forms — 44px inputs, focus states, validation

### Phase 4: Staff Portal
1. Sidebar restyling (same as admin)
2. Grades page — subject/student loading
3. Lesson Notes — Quill.js editor styling
4. Payslip — card layout

### Phase 5: Parent Portal
1. Sidebar restyling (same pattern)
2. Dashboard — children overview
3. Payments — transaction history
4. Grades — report card view
5. Lesson Notes — read-only view

---

## 15. Files to Create / Modify

### New Files
| File | Purpose |
|------|---------|
| `Infotess-host/css/design-tokens.css` | CSS custom properties |
| `Infotess-host/css/typography.css` | Inter + type classes |
| `Infotess-host/css/layout.css` | Grid, containers, breakpoints |
| `Infotess-host/css/components.css` | Buttons, cards, inputs, badges |
| `Infotess-host/css/animations.css` | Scroll animations, transitions |
| `Infotess-host/css/3d-school.css` | 3D container styles |
| `Infotess-host/js/school-3d.js` | Shared Three.js module |

### Modified Files
| File | Change |
|------|--------|
| `Infotess-host/api/home.php` | Replace globe 3D with school building, restructure hero HTML |
| `Infotess-host/css/style.css` | Refactor to CSS custom properties, remove old navy/teal |
| `Infotess-host/api/includes/functions.php` | Update sidebar HTML structure |
| `Infotess-host/api/header.php` (if exists) | New nav bar structure |
| `Infotess-host/api/footer.php` (if exists) | New footer structure |
| All portal pages | Apply new CSS classes, component markup |

---

## 16. Design Principles

1. **Editorial geometry** — 8px buttons, 12px cards, clean lines
2. **Color tells the stage story** — peach→creche, rose→nursery, mint→primary, lavender→JHS
3. **Purple is the action color** — only primary CTA uses purple; everything else is neutral
4. **Navy is the container** — full-width bands for hero and footer
5. **One typeface** — Inter everywhere, no pairing
6. **Generous whitespace** — 64px+ between sections, 24px card padding
7. **Touch-friendly** — 44px minimum tap targets, 280px+ sidebar on mobile
8. **Reduce, don't remove** — `prefers-reduced-motion` keeps fades
9. **3D is decorative, not functional** — enhances brand feel without blocking content
10. **Progressive enhancement** — CSS/JS fails gracefully to readable content

---

## 17. Open Questions / Edge Cases

- [ ] Portal nav items — confirm full sidebar menu for each role
- [ ] Global CSS — will old `style.css` references be migrated all at once or incrementally?
- [ ] Three.js CDN — confirm current source in home.php for replacement
- [ ] Breadcrumb data source — currently hardcoded or dynamic?
- [ ] Test data for gallery/news pages — placeholder content needed?

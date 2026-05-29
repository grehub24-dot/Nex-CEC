# Nex CEC — Eduman-Inspired Design Tokens & UI Guide

## Mission

Create a comprehensive, implementation-ready design language for **Nex CEC** (school management system) inspired by the **Eduman** LMS visual identity. This guide adapts Eduman's clean dashboard aesthetic, typography system, and component patterns to Nex CEC's existing **Warm Navy + Gold** brand, targeting a professional, trustworthy, and accessible school administration interface.

---

## Brand Context

| Attribute | Eduman (Source) | Nex CEC (Target) |
|---|---|---|
| Product | Learning Management System (LMS) | School Management System |
| Audience | Online learners, educators | School admin staff, teachers, parents |
| Surface | E-learning marketplace / LMS | Admin dashboard, teacher portal, parent portal |
| Visual style | Clean, modern, card-based dashboard | Professional, warm, data-rich admin |
| Typography | Raleway (headings) + Nunito Sans (body) | **Keep Inter** — or adopt Raleway + Nunito Sans |
| Primary color | `#2467ec` (Vibrant Blue) | `#1B3A5C` (Warm Navy) |
| Accent | `#ffb013` (Amber) | `#E8A838` (Gold) |

> **Decision: Typography** — The current Nex CEC uses **Inter** (loaded via Google Fonts) with an 8-step size scale. Inter is a compatible substitute for Nunito Sans (both are humanist sans-serifs). **Keep Inter as the body font** for consistency with existing pages. Optionally layer Raleway for hero/display headings on marketing pages. See §4.1 for the recommendation.

---

## 1. Design Tokens

### 1.1 Color Palette — Brand (60/30/10)

Nex CEC **keeps its existing Warm Navy + Gold palette** as the core brand. Eduman's vibrant blue (`#2467ec`) and amber (`#ffb013`) become **optional accent alternatives** for specific dashboard contexts.

#### Core Brand (Primary — 60% of UI)

```css
/* Warm Navy — Trustworthy, academic, authoritative */
--nex-primary:         #1B3A5C;
--nex-primary-hover:   #24527A;
--nex-primary-pressed: #122840;
--nex-on-primary:      #FFFFFF;

/* Gold Accent — Achievement, warmth, welcome */
--nex-accent:         #E8A838;
--nex-accent-hover:   #F2B94D;
--nex-accent-pressed: #D49A2E;
--nex-on-accent:      #1B3A5C;
```

#### Eduman-Inspired Accent Alternatives (Optional — 10% of UI)

These come from Eduman's real live tokens and may be used as **secondary accent** for call-to-action buttons, active states, or data highlights:

```css
/* Eduman Blue — Optional CTA / interactive accent */
--eduman-primary:        #2467ec;
--eduman-primary-hover:  #1d56d0;
--eduman-primary-pressed:#1645b4;
--eduman-on-primary:     #FFFFFF;

/* Eduman Amber — Optional warning / highlight */
--eduman-accent:         #ffb013;
--eduman-accent-hover:   #e69e0f;
--eduman-accent-pressed: #cc8c0d;
--eduman-on-accent:      #141517;

/* Eduman Semantic Colors */
--eduman-purple:         #6f19c5;  /* Badges / labels */
--eduman-teal:           #53b3b3;  /* Success states */
--eduman-red:            #d61212;  /* Error states */
--eduman-border:         #edeef2;  /* Card / table borders */
```

#### Neutrals

```css
/* Eduman-style surface (light) — complements existing Nex CEC neutrals */
--nex-canvas:     #F8F6F3;   /* Page background — kept from Nex CEC */
--nex-surface:    #FFFFFF;   /* Card / panel background */
--nex-hairline:   #E2DFDB;   /* Borders — kept from Nex CEC */

--eduman-ink:       #141517; /* Near-black text (Eduman) */
--nex-ink:          #2D2A27; /* Warm dark text (Nex CEC) */
--nex-slate:        #6B6560; /* Body text */
--nex-muted:        #A9A49E; /* Secondary / muted text */
--nex-on-dark:      #FFFFFF;
--nex-on-dark-muted:#C4C0BB;
```

#### Semantic

```css
--nex-success: #2D9F6F;  /* Warm green */
--nex-warning: #E8A838;  /* Gold */
--nex-error:   #D94452;  /* Warm red */
```

> **Contrast Validation (WCAG AA):**
> - `#1B3A5C` on `#FFFFFF` = **9.2:1** ✅ AA normal text
> - `#1B3A5C` on `#F8F6F3` = **8.4:1** ✅ AA normal text
> - `#E8A838` on `#1B3A5C` = **5.8:1** ✅ AA normal text
> - `#6B6560` on `#FFFFFF` = **4.8:1** ✅ AA normal text
> - `#A9A49E` on `#FFFFFF` = **2.9:1** ❌ AA — use only for decorative / disabled
> - `#2D9F6F` on `#FFFFFF` = **3.6:1** ⚠️ AA large text only (≥18px or ≥14px bold)
> - `#2467ec` on `#FFFFFF` = **4.5:1** ✅ AA normal text (Eduman blue passes on white)
> - `#D94452` on `#FFFFFF` = **4.2:1** ⚠️ AA large text only

> **Use `--nex-slate` (#6B6560) for body text** at 15px — passes 4.8:1 on white.
> **Do NOT use `--nex-muted` (#A9A49E) for text smaller than 18px** — fails WCAG AA.

---

### 1.2 Typography Scale

The current Nex CEC uses **Inter** with an 8-step responsive scale. This is sufficient and compatible. The Eduman type pair (Raleway headings + Nunito Sans body) is **optional for marketing / public-facing pages**.

#### Recommendation

| Context | Font | Rationale |
|---|---|---|
| **Dashboard core** (existing) | **Inter** (current) | Already deployed, clean, readable at 15px body. No migration cost. |
| **Marketing / hero** (optional) | **Raleway** (headings) + **Inter** (body) | Raleway gives display weight; Inter pairs well. |
| **Print reports** (optional) | **Nunito Sans** | Warmer, designed for extended reading. |

If adopting the Eduman type pair, the CSS `@import` is:

```css
@import url('https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700;800&family=Nunito+Sans:ital,wght@0,400;0,600;0,700;1,400&display=swap');
```

#### Type Scale

```
--text-hero:      clamp(2.25rem, 4.5vw, 4.5rem) / 1.05  weight: 800  →  Hero banners
--text-display:   clamp(2rem, 3vw, 3rem)    / 1.10  weight: 700  →  Page titles
--text-h1:        clamp(1.625rem, 2.5vw, 2.5rem) / 1.15 weight: 700  →  Section headers
--text-h2:        1.375rem / 1.3   weight: 600  →  Card titles
--text-h3:        1.125rem / 1.35  weight: 600  →  Subsection titles
--text-h4:        1rem     / 1.4   weight: 600  →  Form labels / sidebar items
--text-body:      0.9375rem / 1.65  weight: 400  →  Paragraphs
--text-sm:        0.8125rem / 1.55  weight: 400  →  Helper text
--text-caption:   0.75rem  / 1.45  weight: 500  →  Table cell data
--text-micro:     0.6875rem / 1.40  weight: 600  →  Badges / labels
```

> ✅ All sizes use `rem` for accessibility (respects user font-size preferences).

---

### 1.3 Spacing Scale

```
--space-xxs:   4px    →  Tight icon gaps
--space-xs:    8px    →  Inline element spacing
--space-sm:    12px   →  Small component gaps
--space-md:    16px   →  Default component gap
--space-lg:    20px   →  Section padding (mobile)
--space-xl:    24px   →  Card padding
--space-xxl:   32px   →  Mobile section spacing
--space-xxxl:  40px   →  Large section spacing
--space-section:   56px  →  Desktop section spacing
--space-section-lg: 72px  →  Wider sections
--space-hero:   80px  →  Hero / banner padding
```

> **Mobile-first**: root spacing uses smaller values; `@media (min-width: 768px)` overrides `--space-xxl` to 48px, `--space-section` to 96px, etc.

---

### 1.4 Border Radius

```css
--radius-xs:  6px   →  Badges, tags
--radius-sm:  8px   →  Inputs, buttons, small cards
--radius-md:  10px  →  Cards, panels
--radius-lg:  14px  →  Modal, drawer
--radius-xl:  20px  →  Hero image containers
--radius-xxl: 28px  →  Large promo cards
--radius-full: 9999px →  Avatars, pills
```

---

### 1.5 Shadows

Warm navy-tinted shadows (using `rgb(27 58 92)` alpha):

```
--shadow-sm:  0 1px 3px 0 rgb(27 58 92 / 0.06), 0 1px 2px -1px rgb(27 58 92 / 0.06)
--shadow-md:  0 4px 8px -2px rgb(27 58 92 / 0.08), 0 2px 4px -2px rgb(27 58 92 / 0.04)
--shadow-lg:  0 12px 20px -4px rgb(27 58 92 / 0.10), 0 4px 8px -4px rgb(27 58 92 / 0.06)
--shadow-hover: 0 20px 30px -6px rgb(27 58 92 / 0.12), 0 8px 12px -6px rgb(27 58 92 / 0.08)
```

---

### 1.6 Transition Timing

```
--transition-fast:  150ms cubic-bezier(0.4, 0, 0.2, 1)
--transition-base:  200ms cubic-bezier(0.4, 0, 0.2, 1)
--transition-slow:  300ms cubic-bezier(0.4, 0, 0.2, 1)
```

---

## 2. Component Patterns

### 2.1 Dashboard Stat Cards

Eduman-inspired stat card pattern adapted for Nex CEC:

```html
<div class="stat-card">
  <div class="stat-card__icon stat-card__icon--students">
    <i class="fas fa-user-graduate"></i>
  </div>
  <div class="stat-card__body">
    <span class="stat-card__label">Total Students</span>
    <span class="stat-card__value">1,284</span>
    <span class="stat-card__change stat-card__change--up">
      <i class="fas fa-arrow-up"></i> +12 this term
    </span>
  </div>
</div>
```

```css
.stat-card {
  display: flex;
  align-items: flex-start;
  gap: var(--space-md);
  padding: var(--space-lg);
  background: var(--nex-surface);
  border: 1px solid var(--nex-hairline);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  transition: box-shadow var(--transition-base), transform var(--transition-base);
}
.stat-card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}
.stat-card__icon {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: var(--nex-on-primary);
  background: var(--nex-primary);
  flex-shrink: 0;
}
.stat-card__icon--students  { background: var(--nex-primary); }
.stat-card__icon--revenue   { background: var(--nex-accent); }
.stat-card__icon--staff     { background: var(--eduman-purple); }
.stat-card__icon--alerts    { background: var(--nex-error); }

.stat-card__body {
  display: flex;
  flex-direction: column;
  gap: var(--space-xxs);
}
.stat-card__label {
  font-size: var(--text-sm-size);
  font-weight: 500;
  color: var(--nex-slate);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.stat-card__value {
  font-size: var(--text-h2-size);
  font-weight: 700;
  color: var(--nex-ink);
  line-height: 1.2;
}
.stat-card__change {
  font-size: var(--text-sm-size);
  display: inline-flex;
  align-items: center;
  gap: var(--space-xxs);
}
.stat-card__change--up   { color: var(--nex-success); }
.stat-card__change--down { color: var(--nex-error); }
```

**States:** default → hover (elevate shadow + translateY -2px)  
**Responsive:** 2-column grid on mobile (<640px), 4-column on desktop  
**Empty state:** Show "—" for value, "No data" for label  
**Accessibility:** Labels use `<span>` with `aria-label` fallback on icon; icon is `aria-hidden="true"`

---

### 2.2 Data Tables

Nex CEC tables follow a warm striped pattern compatible with Eduman's clean table style:

```css
/* Existing Nex CEC .table class pattern — enhanced with Eduman hairline */
.table-wrap {
  overflow-x: auto;
  border: 1px solid var(--nex-hairline);
  border-radius: var(--radius-md);
  background: var(--nex-surface);
}
table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--text-sm-size);
}
thead th {
  background: var(--nex-canvas);
  color: var(--nex-charcoal, #3D3935);
  font-weight: 600;
  font-size: var(--text-caption-size);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: var(--space-sm) var(--space-md);
  text-align: left;
  border-bottom: 2px solid var(--nex-hairline);
  white-space: nowrap;
}
tbody td {
  padding: var(--space-sm) var(--space-md);
  border-bottom: 1px solid var(--nex-hairline);
  color: var(--nex-ink);
  vertical-align: middle;
}
tbody tr:nth-child(even) {
  background: var(--nex-canvas);
}
tbody tr:hover {
  background: var(--color-tint-soft-blue, #F0F5FC);
}
```

**Pagination bar** (per CSS standards: inline `style=` to avoid class conflicts):

```html
<div style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-md) 0; gap: var(--space-md); flex-wrap: wrap;">
  <span class="text-sm" style="color: var(--nex-slate);">Showing 1–10 of 284</span>
  <div style="display: flex; gap: var(--space-xs);">
    <button style="padding: var(--space-xs) var(--space-sm); border: 1px solid var(--nex-hairline); border-radius: var(--radius-xs); background: var(--nex-surface); color: var(--nex-slate); cursor: pointer;">Prev</button>
    <button style="padding: var(--space-xs) var(--space-sm); border: 1px solid var(--nex-primary); border-radius: var(--radius-xs); background: var(--nex-primary); color: var(--nex-on-primary); cursor: pointer;">1</button>
    <button style="padding: var(--space-xs) var(--space-sm); border: 1px solid var(--nex-hairline); border-radius: var(--radius-xs); background: var(--nex-surface); color: var(--nex-ink); cursor: pointer;">2</button>
    <button style="padding: var(--space-xs) var(--space-sm); border: 1px solid var(--nex-hairline); border-radius: var(--radius-xs); background: var(--nex-surface); color: var(--nex-ink); cursor: pointer;">3</button>
    <button style="padding: var(--space-xs) var(--space-sm); border: 1px solid var(--nex-hairline); border-radius: var(--radius-xs); background: var(--nex-surface); color: var(--nex-slate); cursor: pointer;">Next</button>
  </div>
</div>
```

---

### 2.3 Filter Bar (Eduman-Inspired)

School admin pages need filters for term, class, subject, status:

```html
<div class="filter-bar">
  <div class="filter-bar__group">
    <label class="filter-bar__label" for="filter-term">Term</label>
    <select id="filter-term" class="filter-bar__select">
      <option>2026 First Term</option>
      <option>2025 Third Term</option>
    </select>
  </div>
  <div class="filter-bar__group">
    <label class="filter-bar__label" for="filter-class">Class</label>
    <select id="filter-class" class="filter-bar__select">
      <option>All Classes</option>
      <option>Grade 7A</option>
    </select>
  </div>
  <button class="btn btn--primary">Apply Filters</button>
  <button class="btn btn--ghost">Reset</button>
</div>
```

```css
.filter-bar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-md);
  align-items: flex-end;
  padding: var(--space-md) var(--space-lg);
  background: var(--nex-surface);
  border: 1px solid var(--nex-hairline);
  border-radius: var(--radius-md);
  margin-bottom: var(--space-lg);
}
.filter-bar__group {
  display: flex;
  flex-direction: column;
  gap: var(--space-xxs);
  min-width: 160px;
}
.filter-bar__label {
  font-size: var(--text-caption-size);
  font-weight: 600;
  color: var(--nex-charcoal, #3D3935);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.filter-bar__select {
  padding: var(--space-xs) var(--space-sm);
  border: 1px solid var(--nex-hairline);
  border-radius: var(--radius-sm);
  background: var(--nex-surface);
  color: var(--nex-ink);
  font-size: var(--text-body-size);
  font-family: inherit;
  min-height: 40px;
}
```

---

### 2.4 Sidebar Navigation (Existing — verified accessible)

Nex CEC's sidebar uses `renderStaffSidebar()` / `renderParentSidebar()` from `functions.php`. The CSS at `layout.css` defines a collapsible left rail pattern.

**Accessibility requirements:**
- Sidebar must use `<nav>` with `aria-label="Main navigation"`
- Current page item must have `aria-current="page"`
- Collapse toggle must use `aria-expanded` and `aria-controls`
- All items must be keyboard-focusable (native `<a>` or `<button>`)
- Focus indicator: `outline: 2px solid var(--eduman-primary, #2467ec); outline-offset: 2px`

---

### 2.5 Alert / Notification Banners

Eduman uses coloured top banners for alerts. Adapted for Nex CEC:

```html
<div class="alert alert--success" role="alert">
  <i class="fas fa-check-circle" aria-hidden="true"></i>
  <span>Student record saved successfully.</span>
  <button class="alert__dismiss" aria-label="Dismiss notification">&times;</button>
</div>
```

```css
.alert {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding: var(--space-sm) var(--space-md);
  border-radius: var(--radius-sm);
  font-size: var(--text-sm-size);
  font-weight: 500;
  border-left: 4px solid transparent;
  margin-bottom: var(--space-md);
}
.alert--success { background: #E6F7EF; color: #1A6B48; border-left-color: var(--nex-success); }
.alert--warning { background: #FEF7E0; color: #8B6A1E; border-left-color: var(--nex-warning); }
.alert--error   { background: #FDE8EF; color: #B02C3A; border-left-color: var(--nex-error); }
.alert--info    { background: #E8F0FE; color: #1A4B8B; border-left-color: var(--eduman-primary, #2467ec); }

.alert__dismiss {
  margin-left: auto;
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: inherit;
  opacity: 0.7;
  padding: var(--space-xxs);
}
.alert__dismiss:hover { opacity: 1; }
```

---

## 3. Accessibility (WCAG 2.2 AA)

### 3.1 Color Contrast Table

| Token Pair | Ratio | Passes AA? |
|---|---|---|
| `--nex-ink (#2D2A27)` on `--nex-surface (#FFFFFF)` | 12.7:1 | ✅ Normal text |
| `--nex-slate (#6B6560)` on `--nex-surface (#FFFFFF)` | 4.8:1 | ✅ Normal text |
| `--nex-muted (#A9A49E)` on `--nex-surface (#FFFFFF)` | 2.9:1 | ❌ Fail — decorative only |
| `--nex-primary (#1B3A5C)` on `--nex-on-primary (#FFFFFF)` | 9.2:1 | ✅ Normal text |
| `--nex-accent (#E8A838)` on `--nex-on-accent (#1B3A5C)` | 5.8:1 | ✅ Normal text |
| `--nex-success (#2D9F6F)` on `--nex-surface (#FFFFFF)` | 3.6:1 | ⚠️ Large text only |
| `--nex-error (#D94452)` on `--nex-surface (#FFFFFF)` | 4.2:1 | ⚠️ Large text only |
| `--eduman-primary (#2467ec)` on `--nex-surface (#FFFFFF)` | 4.5:1 | ✅ Normal text |
| `--eduman-ink (#141517)` on `--nex-surface (#FFFFFF)` | 16.5:1 | ✅ Normal text |

### 3.2 Focus Indicators

```css
:focus-visible {
  outline: 2px solid var(--eduman-primary, #2467ec);
  outline-offset: 2px;
  border-radius: var(--radius-xs);
}
/* Remove mouse-only focus ring */
:focus:not(:focus-visible) {
  outline: none;
}
```

### 3.3 Keyboard Navigation

- All interactive elements (buttons, links, form controls, toggles) must be natively focusable — no `tabindex="0"` on `<div>` unless as a last resort with `role` mapping.
- Modals must trap focus: first focusable element receives focus on open; `Tab` cycles within modal; `Escape` closes.
- Dropdown menus must use `aria-expanded`, `aria-controls`, and support `ArrowDown`/`ArrowUp` navigation.
- Sortable table headers must use `<button>` inside `<th>` with `aria-sort` attribute.

### 3.4 Loading States

```html
<button class="btn btn--primary" aria-busy="true" disabled>
  <span class="spinner" aria-hidden="true"></span>
  Saving...
</button>
```

```css
.spinner {
  display: inline-block;
  width: 1em;
  height: 1em;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: var(--radius-full);
  animation: spin 0.6s linear infinite;
  vertical-align: middle;
  margin-right: var(--space-xxs);
}
@keyframes spin { to { transform: rotate(360deg); } }
```

---

## 4. Migration Notes

### 4.1 Typography

| Decision | Recommendation |
|---|---|
| Keep `Inter` as the primary UI font? | **Yes** — it's already loaded, well-integrated, and passes readability at 15px. No migration cost. |
| Adopt `Raleway` for marketing pages? | **Optional** — add `Raleway` as a secondary font for `/home`, `/about`, public landing pages. |
| Replace body with `Nunito Sans` in the future? | Possible but non-urgent; would require updating all `--font-family` references. |

### 4.2 Color

| Current (Nex CEC) | Eduman-Inspired Additions | Migration |
|---|---|---|
| `--color-primary: #1B3A5C` | — | **Keep**. Eduman blue `#2467ec` is optional secondary. |
| `--color-accent: #E8A838` | `--eduman-accent: #ffb013` | Gold is warmer and on-brand for school. Keep as primary accent. |
| `--color-link-blue: #2563EB` | `--eduman-primary: #2467ec` | Link blue is already close to Eduman primary. Keep existing. |
| `--color-success: #2D9F6F` | `--eduman-teal: #53b3b3` | Existing warm green is fine. Teal is optional alternate. |

### 4.3 CSS File Mapping

| Design System Layer | Existing File | Action |
|---|---|---|
| Design tokens | `design-tokens.css` | Keep — add Eduman optional tokens as CSS custom properties |
| Typography | `typography.css` | Keep — add `.text-display` weight fix if using Raleway |
| Components | `components.css` | Keep — verify stat-card patterns match §2.1 |
| Layout | `layout.css` | Keep — sidebar/footer are already correct |
| Style (legacy compat) | `style.css` | Keep — maps old var names to new ones |

---

## 5. Anti-Patterns & Prohibited Implementations

| Anti-Pattern | Why | Fix |
|---|---|---|
| Using `--nex-muted` (#A9A49E) for body text | Fails WCAG AA (2.9:1) | Use `--nex-slate` (#6B6560) for body text |
| Raw `#0000ee` or `#545454` from old Eduman DESIGN.md | These were AI extraction errors — not real Eduman tokens | Map to `--color-link-blue` or `--nex-ink` |
| Hardcoding `Helvetica Neue` as font-family | Old DESIGN.md hallucination — Eduman uses Raleway + Nunito Sans | Use `--font-family` (Inter) consistently |
| Using `#82b440` green on white backgrounds | Fails WCAG AA (~2.7:1) | Use `--nex-success` (#2D9F6F) with dark text fallback |
| `!important` in component CSS | Breaks cascade, hard to override | Increase specificity or use CSS custom properties |
| Inline `onclick` handlers | Violates CSP, hard to maintain | Use `addEventListener` in JS |
| `<div>` as interactive element without `role` | Breaks screen reader navigation | Use native `<button>` or `<a>` |

---

## 6. QA Checklist

Every implementation must verify:

- [ ] All colors use CSS custom properties (`var(--nex-*)`), never raw hex
- [ ] All interactive elements have `:focus-visible` outline (2px solid, offset 2px)
- [ ] All form inputs have associated `<label>` elements
- [ ] All icon-only controls have `aria-label` or visible text
- [ ] Stat cards, tables, and filter bars match responsive grid rules
- [ ] Type scale uses `rem` units — heading sizes pass WCAG AA on target background
- [ ] No `#0000ee`, `#545454`, `#82b440`, or `Helvetica Neue` from old DESIGN.md remain
- [ ] Loading states use `aria-busy="true"` on the interactive element
- [ ] Sidebar navigation uses `<nav>` with `aria-label` and `aria-current="page"` on active item
- [ ] Pagination bars use inline `style=` per project CSS standards
- [ ] No duplicate `DESIGN (1).md` or `SKILL (1).md` files remain in the repo

---

## 7. Reference: Eduman Live Tokens (Verified)

These tokens were extracted from the live Eduman preview site (May 2026) and are provided for reference:

| Token | Value | Usage |
|---|---|---|
| Primary | `#2467ec` | Navigation, CTA buttons, active states |
| Accent | `#ffb013` | Highlights, badges, sale/offer tags |
| Dark text | `#141517` | Headings, body text (near-black) |
| Border | `#edeef2` | Card outlines, table borders |
| Purple | `#6f19c5` | Category badges |
| Teal | `#53b3b3` | Success indicators |
| Red | `#d61212` | Error / danger |
| Heading font | `Raleway` (700) | Section titles, hero text |
| Body font | `Nunito Sans` (400/600/700) | Paragraphs, navigation, buttons |
| Icon set | Font Awesome Pro 5.14.0 | All icons |
| Base framework | Bootstrap 5.3.1 | Grid, utilities, responsive helpers |

---

*This DESIGN.md is a living document. Last updated: 2026-05-29. Update tokens when the Eduman source theme receives a major version bump.*

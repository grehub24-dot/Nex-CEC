---
version: alpha
name: Linear-design-analysis
description: "A near-black product-focused admin canvas built around #010102, light gray text (#f7f8f8), and the signature Linear lavender-blue (#5e6ad2) as the single chromatic accent."

colors:
  primary: "#5e6ad2"
  on-primary: "#ffffff"
  primary-hover: "#828fff"
  primary-focus: "#5e69d1"
  ink: "#f7f8f8"
  ink-muted: "#d0d6e0"
  ink-subtle: "#8a8f98"
  ink-tertiary: "#62666d"
  canvas: "#010102"
  surface-1: "#0f1011"
  surface-2: "#141516"
  surface-3: "#18191a"
  hairline: "#23252a"
  hairline-strong: "#34343a"
  semantic-success: "#27a644"
  semantic-overlay: "#000000"

typography:
  display-xl:
    fontFamily: "Inter, SF Pro Display, -apple-system, system-ui"
    fontSize: 80px
    fontWeight: 600
    lineHeight: 1.05
    letterSpacing: -3.0px
  display-lg:
    fontFamily: "Inter, SF Pro Display, -apple-system, system-ui"
    fontSize: 56px
    fontWeight: 600
    lineHeight: 1.10
    letterSpacing: -1.8px
  display-md:
    fontFamily: "Inter, SF Pro Display, -apple-system, system-ui"
    fontSize: 40px
    fontWeight: 600
    lineHeight: 1.15
    letterSpacing: -1.0px
  headline:
    fontFamily: "Inter, SF Pro Display, -apple-system, system-ui"
    fontSize: 28px
    fontWeight: 600
    lineHeight: 1.20
    letterSpacing: -0.6px
  card-title:
    fontFamily: "Inter, SF Pro Display, -apple-system, system-ui"
    fontSize: 22px
    fontWeight: 500
    lineHeight: 1.25
    letterSpacing: -0.4px
  subhead:
    fontFamily: "Inter, SF Pro Display, -apple-system, system-ui"
    fontSize: 20px
    fontWeight: 400
    lineHeight: 1.40
    letterSpacing: -0.2px
  body:
    fontFamily: "Inter, SF Pro Display, -apple-system, system-ui"
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.50
    letterSpacing: -0.05px
  body-sm:
    fontFamily: "Inter, SF Pro Display, -apple-system, system-ui"
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.50
    letterSpacing: 0
  caption:
    fontFamily: "Inter, SF Pro Display, -apple-system, system-ui"
    fontSize: 12px
    fontWeight: 400
    lineHeight: 1.40
    letterSpacing: 0
  button:
    fontFamily: "Inter, SF Pro Display, -apple-system, system-ui"
    fontSize: 14px
    fontWeight: 500
    lineHeight: 1.20
    letterSpacing: 0
  mono:
    fontFamily: "JetBrains Mono, ui-monospace, SF Mono, Menlo"
    fontSize: 13px
    fontWeight: 400
    lineHeight: 1.50
    letterSpacing: 0

rounded:
  sm: 6px
  md: 8px
  lg: 12px
  xl: 16px
  pill: 9999px

spacing:
  xs: 8px
  sm: 12px
  md: 16px
  lg: 24px
  xl: 32px
  xxl: 48px
  section: 96px

components:
  button-primary:
    backgroundColor: "#5e6ad2"
    textColor: "#ffffff"
    typography: button
    rounded: md
    padding: 8px 14px
    border: none
  button-secondary:
    backgroundColor: "#0f1011"
    textColor: "#f7f8f8"
    typography: button
    rounded: md
    padding: 8px 14px
    border: 1px solid #23252a
  text-input:
    backgroundColor: "#0f1011"
    textColor: "#f7f8f8"
    typography: body
    rounded: md
    padding: 8px 12px
    border: 1px solid #23252a
  surface-card:
    backgroundColor: "#0f1011"
    textColor: "#f7f8f8"
    typography: body
    rounded: lg
    padding: 24px
    border: 1px solid #23252a
  table:
    backgroundColor: "#010102"
    textColor: "#f7f8f8"
    typography: body-sm
    rounded: md
    border: 1px solid #23252a
  modal:
    backgroundColor: "#0f1011"
    textColor: "#f7f8f8"
    typography: body
    rounded: lg
    border: 1px solid #34343a
  status-badge:
    backgroundColor: "#141516"
    textColor: "#d0d6e0"
    typography: caption
    rounded: pill
    padding: 2px 8px

---

## Linear Design System — Admin Theme

Dark-canvas admin interface built on the Linear design language. Near-black backgrounds, a four-step surface ladder for hierarchy, lavender-blue as the single accent, and hairline borders instead of shadows.

### Key Principles
- **Dark canvas** (#010102) as the default background — never true black
- **Surface ladder** (canvas → surface-1 → surface-2 → surface-3) for depth without shadows
- **Lavender-blue** (#5e6ad2) reserved for primary actions, focus rings, and links only
- **Hairline borders** (1px) instead of drop shadows
- **Negative tracking** on headings, tight letter-spacing on body
- **Inter** font family throughout (Linear Display substitute)

### When to Use
- Admin dashboards
- Data tables with dense information
- School management backends
- Any tool where readability and precision matter more than decoration

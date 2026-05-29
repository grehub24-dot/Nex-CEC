---
version: alpha
name: Nex-CEC-Light-Navy-Gold
description: >
  Light Navy Blue + Bright Gold design system for Nex Central Excel College
  (Creche through JHS 3). Applies the 60-30-10 rule: 60% Crisp White canvas,
  30% Light Navy Blue structural elements (nav, footer, section headers),
  10% Bright Gold action accents (CTAs, alerts, icons, active tabs).
  Suited for a Basic School web app serving parents (seeking security and
  prestige), young students age 2–15 (needing warmth and approachability),
  and staff. Light navy is softer and friendlier than midnight navy, avoiding
  a corporate-bank feel. Gold adds cheerful, sunny energy representing youth
  and excellence. Rounded corners on buttons and images keep the theme
  child-friendly. Colorful student photography breaks up blue/white blocks.

colors:
  # ---- 60/30/10 Palette — "Golden Waves" (from Piktochart) ----
  # 60% Base (Background) — Crisp White
  canvas: "#ffffff"
  surface: "#f6f5f4"
  surface-soft: "#fafaf9"

  # 30% Secondary (Structure) — Light Navy Blue
  primary: "#2b4c7e"
  primary-hover: "#3b6da0"
  primary-pressed: "#1e3a5f"
  on-primary: "#ffffff"
  brand-navy: "#1e3a5f"           # Light Navy (nav, footer, headers)
  brand-navy-deep: "#0f2847"
  brand-navy-mid: "#2b4c7e"
  deep-blue: "#003366"            # From Golden Waves article — deeper navy

  # 10% Accent (Action) — Vibrant Gold (Golden Waves)
  accent: "#FFD700"
  accent-hover: "#FFE44D"
  accent-pressed: "#DAA520"
  on-accent: "#1e3a5f"            # Navy text on gold (for readability)
  bold-orange: "#FF8C00"          # From Golden Waves article — energy/CTAs

  # Golden Waves secondary tones
  soft-blue: "#A3C1DA"           # Calming secondary — section backgrounds
  pale-yellow: "#F0E68C"         # Warm highlight — banners, badges
  tint-soft-blue: "#D6E4F0"      # Light soft blue tint for cards
  star: "#f5b342"

  # Functional links
  link-blue: "#0075de"
  link-blue-pressed: "#005bab"

  # Education-stage card tints
  card-tint-peach: "#ffe8d4"    # Creche / Early Childhood
  card-tint-rose: "#fde0ec"     # Nursery / KG
  card-tint-mint: "#d9f3e1"     # Primary (B1–B6)
  card-tint-navy: "#dbeafe"     # JHS (JHS1–JHS3) — light navy
  card-tint-sky: "#dcecfa"      # Extracurricular / Special Programs
  card-tint-yellow: "#fef7d6"   # Achievements / Highlights
  card-tint-cream: "#f8f5e8"    # General content
  card-tint-yellow-bold: "#f9e79f"  # Featured banner
  card-tint-gray: "#f0eeec"     # Muted sections

  # Neutrals
  hairline: "#e5e3df"
  hairline-soft: "#ede9e4"
  hairline-strong: "#c8c4be"
  ink-deep: "#000000"
  ink: "#1a1a1a"
  charcoal: "#37352f"
  slate: "#5d5b54"
  steel: "#787671"
  muted: "#bbb8b1"
  on-dark: "#ffffff"
  on-dark-muted: "#a4a097"

  # Semantic
  semantic-success: "#1aae39"
  semantic-warning: "#dd5b00"
  semantic-error: "#e03131"

typography:
  hero-display:
    fontFamily: "Inter, -apple-system, system-ui, sans-serif"
    fontSize: 72px
    fontWeight: 700
    lineHeight: 1.05
    letterSpacing: -2px
  display-lg:
    fontFamily: "Inter, -apple-system, system-ui, sans-serif"
    fontSize: 48px
    fontWeight: 700
    lineHeight: 1.10
    letterSpacing: -1px
  heading-1:
    fontFamily: "Inter, -apple-system, system-ui, sans-serif"
    fontSize: 40px
    fontWeight: 700
    lineHeight: 1.15
    letterSpacing: -0.5px
  heading-2:
    fontFamily: "Inter, -apple-system, system-ui, sans-serif"
    fontSize: 32px
    fontWeight: 600
    lineHeight: 1.20
    letterSpacing: -0.3px
  heading-3:
    fontFamily: "Inter, -apple-system, system-ui, sans-serif"
    fontSize: 24px
    fontWeight: 600
    lineHeight: 1.25
  heading-4:
    fontFamily: "Inter, -apple-system, system-ui, sans-serif"
    fontSize: 20px
    fontWeight: 600
    lineHeight: 1.30
  body-md:
    fontFamily: "Inter, -apple-system, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.60
  body-sm:
    fontFamily: "Inter, -apple-system, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.50
  button-md:
    fontFamily: "Inter, -apple-system, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 500
    lineHeight: 1.30
  caption:
    fontFamily: "Inter, -apple-system, system-ui, sans-serif"
    fontSize: 13px
    fontWeight: 500
    lineHeight: 1.40
  micro-uppercase:
    fontFamily: "Inter, -apple-system, system-ui, sans-serif"
    fontSize: 11px
    fontWeight: 600
    lineHeight: 1.40
    letterSpacing: 1px

rounded:
  xs: 4px
  sm: 6px
  md: 8px
  lg: 12px
  xl: 16px
  xxl: 24px
  full: 9999px

spacing:
  xxs: 4px
  xs: 8px
  sm: 12px
  md: 16px
  lg: 20px
  xl: 24px
  xxl: 32px
  xxxl: 40px
  section-sm: 48px
  section: 64px
  section-lg: 96px
  hero: 120px

components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.button-md}"
    rounded: "{rounded.md}"
    padding: "10px 18px"
  button-primary-pressed:
    backgroundColor: "{colors.primary-pressed}"
  button-accent:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.on-accent}"     # Navy text on gold
    typography: "{typography.button-md}"
    rounded: "{rounded.md}"
    padding: "10px 18px"
  button-accent-hover:
    backgroundColor: "{colors.accent-hover}"
  button-dark:
    backgroundColor: "{colors.ink-deep}"
    textColor: "{colors.on-dark}"
    typography: "{typography.button-md}"
    rounded: "{rounded.md}"
    padding: "10px 18px"
  button-secondary:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    typography: "{typography.button-md}"
    rounded: "{rounded.md}"
    padding: "10px 18px"
    border: "1px solid {colors.hairline-strong}"
  button-on-dark:
    backgroundColor: "{colors.on-dark}"
    textColor: "{colors.ink}"
    typography: "{typography.button-md}"
    rounded: "{rounded.md}"
    padding: "10px 18px"
  button-secondary-on-dark:
    backgroundColor: "transparent"
    textColor: "{colors.on-dark}"
    typography: "{typography.button-md}"
    rounded: "{rounded.md}"
    padding: "10px 18px"
    border: "1px solid {colors.on-dark-muted}"
  card-base:
    backgroundColor: "{colors.canvas}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xl}"
    border: "1px solid {colors.hairline}"
  card-feature:
    backgroundColor: "{colors.canvas}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xxl}"
    border: "1px solid {colors.hairline}"
  card-feature-peach:
    backgroundColor: "{colors.card-tint-peach}"
    textColor: "{colors.charcoal}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xxl}"
  card-feature-rose:
    backgroundColor: "{colors.card-tint-rose}"
    textColor: "{colors.charcoal}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xxl}"
  card-feature-mint:
    backgroundColor: "{colors.card-tint-mint}"
    textColor: "{colors.charcoal}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xxl}"
  card-feature-navy:
    backgroundColor: "{colors.card-tint-navy}"
    textColor: "{colors.charcoal}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xxl}"
  card-feature-sky:
    backgroundColor: "{colors.card-tint-sky}"
    textColor: "{colors.charcoal}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xxl}"
  card-feature-yellow:
    backgroundColor: "{colors.card-tint-yellow}"
    textColor: "{colors.charcoal}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xxl}"
  card-feature-cream:
    backgroundColor: "{colors.card-tint-cream}"
    textColor: "{colors.charcoal}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xxl}"
  pricing-card:
    backgroundColor: "{colors.canvas}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xxl}"
    border: "1px solid {colors.hairline}"
  pricing-card-featured:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xxl}"
    border: "2px solid {colors.primary}"
  text-input:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    typography: "{typography.body-md}"
    rounded: "{rounded.md}"
    padding: "{spacing.sm} {spacing.md}"
    border: "1px solid {colors.hairline-strong}"
    height: 44px
  search-pill:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.steel}"
    typography: "{typography.body-md}"
    rounded: "{rounded.md}"
    padding: "{spacing.sm} {spacing.md}"
    height: 44px
    border: "1px solid {colors.hairline}"
  pill-tab:
    backgroundColor: "transparent"
    textColor: "{colors.steel}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.full}"
    padding: "{spacing.xs} {spacing.md}"
    border: "1px solid {colors.hairline}"
  pill-tab-active:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-dark}"
    rounded: "{rounded.full}"
  badge-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: "4px 10px"
  badge-accent:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.on-accent}"
    typography: "{typography.caption}"
    rounded: "{rounded.full}"
    padding: "4px 10px"
  badge-tag:
    backgroundColor: "{colors.card-tint-navy}"
    textColor: "{colors.primary}"
    typography: "{typography.caption}"
    rounded: "{rounded.sm}"
    padding: "2px 8px"
  hero-band-dark:
    backgroundColor: "{colors.brand-navy}"
    textColor: "{colors.on-dark}"
    rounded: "0"
    padding: "{spacing.hero}"
  cta-banner-light:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "{spacing.section}"
  testimonial-card:
    backgroundColor: "{colors.canvas}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xxl}"
    border: "1px solid {colors.hairline}"
  stat-row:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "{spacing.section-sm}"
  footer-region:
    backgroundColor: "{colors.brand-navy}"
    textColor: "{colors.on-dark}"
    padding: "{spacing.section} {spacing.xxl}"
  footer-link:
    backgroundColor: "transparent"
    textColor: "{colors.on-dark-muted}"
    typography: "{typography.body-sm}"
    padding: "{spacing.xxs} 0"
---

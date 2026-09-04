---
name: FIUCC Institutional Portal
colors:
  surface: '#f9f9ff'
  surface-dim: '#d3daef'
  surface-bright: '#f9f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f1f3ff'
  surface-container: '#e9edff'
  surface-container-high: '#e1e8fd'
  surface-container-highest: '#dce2f7'
  on-surface: '#141b2b'
  on-surface-variant: '#424753'
  inverse-surface: '#293040'
  inverse-on-surface: '#edf0ff'
  outline: '#727784'
  outline-variant: '#c2c6d5'
  surface-tint: '#005cba'
  primary: '#004c9a'
  on-primary: '#ffffff'
  primary-container: '#0063c6'
  on-primary-container: '#d8e3ff'
  inverse-primary: '#aac7ff'
  secondary: '#06629f'
  on-secondary: '#ffffff'
  secondary-container: '#7abbfe'
  on-secondary-container: '#004a7b'
  tertiary: '#725c00'
  on-tertiary: '#ffffff'
  tertiary-container: '#cea700'
  on-tertiary-container: '#4e3e00'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d7e3ff'
  primary-fixed-dim: '#aac7ff'
  on-primary-fixed: '#001b3e'
  on-primary-fixed-variant: '#00458e'
  secondary-fixed: '#d0e4ff'
  secondary-fixed-dim: '#9ccaff'
  on-secondary-fixed: '#001d35'
  on-secondary-fixed-variant: '#00497a'
  tertiary-fixed: '#ffe083'
  tertiary-fixed-dim: '#edc219'
  on-tertiary-fixed: '#231b00'
  on-tertiary-fixed-variant: '#564500'
  background: '#f9f9ff'
  on-background: '#141b2b'
  surface-variant: '#dce2f7'
typography:
  display-hero:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '800'
    lineHeight: 56px
    letterSpacing: -0.025em
  display-hero-mobile:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '800'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
    letterSpacing: -0.02em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 26px
    fontWeight: '700'
    lineHeight: 34px
    letterSpacing: -0.015em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
    letterSpacing: -0.015em
  headline-sm:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 26px
    letterSpacing: -0.01em
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-lg:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.02em
  label-caps:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '700'
    lineHeight: 16px
    letterSpacing: 0.06em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  space-2: 0.125rem
  space-4: 0.25rem
  space-8: 0.5rem
  space-12: 0.75rem
  space-16: 1rem
  space-20: 1.25rem
  space-24: 1.5rem
  space-32: 2rem
  space-40: 2.5rem
  space-48: 3rem
  space-64: 4rem
  layout-max-width: 1200px
  gutter-desktop: 1.5rem
  gutter-mobile: 1rem
---

## Brand & Style
The design system powers the official digital portal of the Congolese Community at Final International University (FIUCC). It balances collegiate institutional authority with the vibrant identity of Congolese scholars abroad.

The brand personality is:
- **Diplomatic & Trustworthy:** Anchored in official student governance, presenting clear administrative utility, accountability, and security.
- **Supportive & Welcoming:** Radiating warmth for diaspora students through respectful, subtle cultural nods without compromising academic rigor.
- **Civic & Unified:** A single source of truth for services, events, housing, and integration abroad.

The design movement is **Modern Institutionalism**. It pairs clean, accessible digital interfaces with crisp structural hierarchy, refined borders, and calculated moments of high-signal color. Decorative excess is eliminated in favor of structured data, readable administrative announcements, and purposeful civic workflows.

## Colors
The palette centers on an authoritative democratic blue supported by energetic national accent tones used with strict quantitative discipline.

### Palette Discipline & Distribution
- **Dominant Surfaces & Foundation (60%):** Page background (`#F8FAFC`), card surfaces (`#FFFFFF`), and borders (`#E5E7EB`). Large expansive fields must always remain neutral and clean.
- **Brand Authority (30%):** Brand blue (`#0063C6`) anchors global navigation, section headers, primary interactive states, key actions, and links.
- **Tonal Support (5%):** Light blue (`#7BBCFF`) is reserved for contextual card tints, active filters, subtle highlight badges, and hover feedback.
- **Sovereign Accents (≤5% combined):**
  - **Gold Accent (`#ECC117`):** High-prestige badges, active states, and secondary visual markers. *Rule: Never display gold text directly on white or light backgrounds; pair it with dark text or use gold as an icon/badge fill.*
  - **Red Accent (`#C00F1F`):** Critical alerts, urgent administrative deadlines, destructive operations, and selective cultural flourishes.

### Neutral & Contrast Standards
- **Primary Text (`#111827`):** Deep slate providing WCAG AAA compliance on all `#FFFFFF` and `#F8FAFC` surfaces.
- **Muted Text (`#64748B`):** Applied to metadata, subheaders, timestamps, and descriptive hints.
- **Dividers & Strokes (`#E5E7EB`):** Used consistently for structural definition and clean visual separation.

## Typography
Typography is crisp, neutral, and highly legible across dense administrative listings and dynamic editorial announcements. Inter provides uniform metrics and clear contrast between hierarchy tiers.

- **Headline Treatment:** Section headers and title bars utilize `-0.02em` tracking and bold weights to project official weight.
- **Bilingual Considerations (EN/FR):** French copy typically expands text lengths by 15–20% compared to English. All label containers, table headers, and navigation slots must accommodate text growth without line breaks or clipping.
- **Hierarchy Ratios:** Clear vertical distinction between `h1` (36px), `h2` (24px), and `h3` (18px) guarantees users can scan long regulatory documents, minutes, and forms efficiently.

## Layout & Spacing
The layout follows an 8px base rhythm, framed inside a centralized fixed grid with a maximum content boundary of `1200px`.

### Layout Principles
- **Grid Architecture:** Desktop displays standard 12-column layouts with 24px (`space-24`) gutters and padding. Tablet (768px–1024px) downscales to an 8-column format with 20px gutters. Mobile devices (<768px) collapse to a 4-column system with 16px (`space-16`) page margins.
- **Vertical Hierarchy:** Section blocks have `space-64` top and bottom margins on desktop, reducing to `space-40` on mobile. In-card content grouping maintains `space-16` or `space-24` intervals.
- **Dense Utility Containers:** Administrative forms, student directory tables, and registry profiles utilize tight 8px and 12px steps to maintain functional data density.

## Elevation & Depth
Elevation conveys structural hierarchy through subtle, clinical ambient lighting paired with physical container boundaries. Heavy dropshadows are forbidden.

### Elevation Levels
- **Level 0 (Flat):** Base canvas (`#F8FAFC`). Flat utility surfaces, table rows, and secondary input wells.
- **Level 1 (Card & Content Blocks):** Card surface (`#FFFFFF`), with a 1px solid `#E5E7EB` border and ambient shadow: `0 1px 3px rgba(17, 24, 39, 0.05), 0 1px 2px rgba(17, 24, 39, 0.03)`.
- **Level 2 (Dropdowns, Flyouts & Interactive Hover):** Elevates on mouseover or popover launch: `0 4px 6px -1px rgba(17, 24, 39, 0.07), 0 2px 4px -1px rgba(17, 24, 39, 0.04)`.
- **Level 3 (Modals, Overlays & Sticky Nav):** Header bar and modal dialogs: `0 10px 15px -3px rgba(17, 24, 39, 0.08), 0 4px 6px -2px rgba(17, 24, 39, 0.03)`.

### Border Discipline
All elevated cards, modals, and list containers retain their 1px `#E5E7EB` structural border regardless of shadow elevation, maintaining sharp definition on high-density displays.

## Shapes
The design system employs a calibrated geometric rounding scale to balance institutional reliability with friendly accessibility:

- **Standard Cards & Data Containers:** Fixed at **12px** border radius (`rounded-card`), producing structured panels with softened corners.
- **Interactive Controls (Buttons, Inputs, Selects):** Standardized at **8px** radius (`rounded-md`), providing tactile definition for clickable areas.
- **Pill & Circular Elements:** Badges, avatar placeholders, language switch indicators, and the official university community logo use fully rounded geometry (`rounded-full` / 9999px).
- **Checkboxes & Tooltips:** Micro-controls utilize **4px** radius (`rounded-sm`).

## Components

### 1. Navigation & Header
- **Top Navigation Bar:** Sticky position, height `72px`, background `#FFFFFF` with 1px bottom border `#E5E7EB` and Level 3 ambient shadow. Inner content constrained to `1200px`.
- **Logo Area:** 44px circular mark on the far left featuring the FIUCC crest, accompanied by institutional wordmark in `#111827`.
- **Navigation Links:** Horizontal links in `body-sm` bold (`#64748B`), transitioning to `#0063C6` on hover/active with an underline indicator.
- **Language Switcher:** A segmented or pill control toggling `EN` and `FR` with an active state in `#0063C6` (white text) or subtle blue outline.
- **Auth Actions:** Subdued "Sign In" link followed by the primary "Join the community" button.

### 2. Buttons
- **Primary:** Background `#0063C6`, text `#FFFFFF`, 8px radius, height `40px` (desktop) or `44px` (mobile). Hover state: darkens to `#0050A3`.
- **Secondary / Accent:** Background `#ECC117`, text `#111827` (bold), 8px radius. Applied sparingly for featured community onboarding or special civic actions.
- **Outline / Secondary Neutral:** Background transparent, 1px border `#0063C6`, text `#0063C6`. Hover: background `#7BBCFF` at 10% opacity.
- **Destructive:** Background `#C00F1F`, text `#FFFFFF`, 8px radius.

### 3. Cards
- **Base Card:** Background `#FFFFFF`, 12px border radius, 1px solid border `#E5E7EB`, padding `24px`. Subdued Level 1 shadow.
- **Featured / Announcement Card:** Adds a 4px primary blue `#0063C6` top accent stripe.
- **Interactive Listing Card:** Applies a soft transition on hover to Level 2 elevation and border color `#7BBCFF`.

### 4. Chips & Badges
- **Status Badges:** Height `24px`, padding `0 10px`, `rounded-full`, text `label-md`.
  - *Official / Certified:* Background `#7BBCFF` at 20% opacity, text `#0063C6`.
  - *Highlight / Honor:* Background `#ECC117` at 20% opacity, text `#8A6B00`.
  - *Alert / Urgent:* Background `#C00F1F` at 15% opacity, text `#C00F1F`.

### 5. Form Inputs & Controls
- **Input Fields:** Height `42px`, background `#FFFFFF`, border 1px solid `#E5E7EB`, radius `8px`, text `body-md` in `#111827`. Focus ring: 2px offset glow in `#7BBCFF`, border `#0063C6`.
- **Checkboxes & Radios:** Accent tint `#0063C6` when checked. 4px radius for checkboxes, full circle for radios.

### 6. Institutional Footer
- **Footer Body:** Background `#FFFFFF` with top border `#E5E7EB`. 4-column desktop grid:
  1. Community Crest & Mission statement.
  2. Quick Resources (Consular, Academic, Housing).
  3. Association Governance (Bylaws, Executive Committee, Archives).
  4. Campus Contact & Office hours.
- **Sub-footer Anchor Bar:** Full-bleed `#0063C6` bar at the absolute bottom. Text in pure white or 90% opacity white, displaying copyright, legal disclaimers, and reciprocal affiliation with Final International University.
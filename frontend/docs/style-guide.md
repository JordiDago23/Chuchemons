# Chuchemons Frontend Style Guide

## Scope

This document describes the current design system implemented in the Angular frontend.

## Product Screens

- Login
- Register
- Home
- Chuchedex
- Mochila
- Amigos
- Perfil
- Team Selector
- Admin

## Figma Status

- No Figma files, exported prototypes, or repository-linked Figma references are present in this workspace.
- High-fidelity Figma prototypes for all screens cannot be verified from code alone.

## Color System

Primary palette used across the product:

- Primary red: `#e63946`
- Primary orange: `#f4722b`
- Accent yellow: `#f7b733`
- Water blue: `#3b5bdb`
- Air blue: `#87ceeb`
- Earth sand: `#d4a574`
- Success green: `#16a34a`
- Neutral ink: `#1a1a2e`
- Page background: `#f6f7fb`

Common surfaces:

- Card background: `#ffffff`
- Soft border: `#e5e7eb`
- Muted text: `#667085` / `#888888`

## Typography

Current implementation uses:

- Primary UI font: `Segoe UI, Roboto, Arial, sans-serif`
- Large page titles: `1.8rem` to `2rem`, weight `800`
- Card titles: `0.95rem` to `1rem`, weight `700`
- Body text: `0.85rem` to `1rem`
- Small metadata: `0.65rem` to `0.8rem`

## Component System

### Forms

- Bootstrap classes are used in auth and admin flows: `form-control`, `form-select`, `btn`, `alert`.
- Login and register forms are vertically centered using Flexbox.
- Validation feedback is inline and accessible through `aria-invalid` and `aria-live`.

### Cards

- Xuxemons use card-based layout with shadow and hover lift.
- Friends, requests, items and inventory slots use card surfaces with rounded corners and subtle borders.
- Badges communicate type, size, quantity and pending counts.

### Navigation

- Left sidebar is the main desktop navigation pattern.
- Mobile behavior collapses layouts vertically through media queries already present in page styles.

### Modals and Dialogs

- Confirm and details dialogs exist in the current implementation.
- A specific Bootstrap modal flow for feed/vaccinate actions is not present in the workspace.

## Layout Rules

### Auth

- Full-screen centered card using Flexbox.
- Max width around `360px` for login/register forms.

### Chuchedex

- Responsive CSS Grid.
- Intended card density:
  - 6 columns on wide screens
  - 4 columns on medium screens
  - 2 columns on narrow screens

### Mochila

- Fixed 4x5 inventory concept for 20 slots.
- Grid uses 4 columns in desktop layout.

### Amigos

- Search, pending requests and friends list use flexible row/card layouts.
- Search now uses Bootstrap input-group semantics.

## Motion

Implemented motion patterns:

- Hover lift on cards
- Button hover transitions
- Progress and bar transitions
- Alert fade-in in auth flows

Missing or partial motion areas:

- Dedicated evolution animation sequence beyond basic UI transitions
- Reward toast-style notification animation

## Accessibility and SEO Baseline

- `lang` set in the root HTML document
- Meta description and robots tags present in `index.html`
- Route titles configured in Angular routes
- Keyboard-visible focus states implemented globally

## Requirement Notes

- The style guide and design system are now documented in-repo.
- Figma prototypes still need to exist outside the codebase or be linked into it.
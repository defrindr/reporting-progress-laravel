---
name: frontend-tailwind-glass
description: Build consistent modern UI using Tailwind with glassmorphism design principles, dark mode, and design tokens
license: MIT
compatibility: opencode
metadata:
  audience: frontend-developers
  stack: tailwindcss
  design: glassmorphism
  theme: dark-mode
---

## What I do

- Create modern, clean UI using Tailwind CSS
- Apply glassmorphism design (blur, transparency, subtle borders)
- Enforce consistent design tokens (color, spacing, layout)
- Support dark mode by default
- Use primary and accent color system
- Promote reusable and scalable components

---

## When to use me

Use this when:
- Building scalable frontend UI
- Creating dashboards, admin panels, or landing pages
- You need consistent theming (light + dark)
- You want glassmorphism without sacrificing readability

Ask clarifying questions if:
- Accent color is not defined
- Branding guidelines are missing
- Dark mode behavior is unclear

---

## Design Tokens

### Primary Color
- `primary`: `#1D546D`

### Suggested Palette
```js
// tailwind.config.js
export default {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: "#1D546D",

        primarySoft: "#2A6F8E",
        primaryMuted: "#163F52",

        accent: "#38BDF8", // sky blue accent (can be adjusted)

        glassLight: "rgba(255,255,255,0.1)",
        glassDark: "rgba(0,0,0,0.3)",

        borderGlassLight: "rgba(255,255,255,0.2)",
        borderGlassDark: "rgba(255,255,255,0.08)"
      }
    }
  }
}

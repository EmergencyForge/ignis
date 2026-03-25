## Design Context

### Users
Roleplay community administrators, staff members, and emergency dispatchers managing fictional fire departments and emergency services (Feuerwehr/Rettungsdienst) in FiveM. They use the system in two primary contexts:
- **Admin back-office**: Managing personnel, protocols, documents, and organizational structure — needs efficiency and clarity for data-dense workflows
- **Emergency dispatch (eNOTF)**: Real-time operations during active roleplay — needs focus, speed, and immersion under simulated high-stress conditions

Users expect an interface that feels like a real emergency services system while being modern and polished enough to stand out as a premium tool.

### Brand Personality
**Bold, Immersive, Authentic** — intraRP should feel like stepping into a real fire department command center. The interface earns trust through visual authority and purposeful design, not decoration.

Emotional goals: Modern & polished, immersive & realistic, efficient & focused. The system should feel like professional emergency infrastructure — serious, competent, and built for the job.

### Aesthetic Direction
- **Visual tone**: Dark-first, data-dense, with sharp contrasts and purposeful color. Red (`#d10000`) as the signature accent reinforces the fire department identity.
- **References**: Real emergency dispatch/C4I systems for authenticity and functional inspiration; Notion/Coda for clean content layout, flexible structure, and subtle design touches that keep dense interfaces breathable.
- **Anti-references**: Generic Bootstrap admin templates, overly playful or whimsical UIs, cluttered dashboards with excessive gradients or decorative elements. Should never look like a toy or a generic SaaS dashboard.
- **Theme**: Dark mode primary with runtime accent color customization. The dark palette (purplish-dark grays #2b2930, #232128) creates depth and professionalism.

### Design Principles

1. **Authenticity over aesthetics** — Every design decision should reinforce the feeling of a real emergency services system. Favor functional patterns from actual dispatch interfaces over trendy UI patterns.

2. **Density with clarity** — Information-dense layouts are expected and welcome, but each element must have clear visual hierarchy. Use spacing, typography weight, and subtle color to guide the eye — not borders and dividers everywhere.

3. **Bold identity, quiet execution** — The red accent and dark theme make a strong first impression. Beyond that, the UI should be calm and focused. Let content dominate; chrome should recede.

4. **Speed of use over speed of learning** — Optimize for power users who live in this system daily. Efficient workflows, keyboard shortcuts, and compact layouts beat hand-holding onboarding.

5. **Immersion-preserving** — Design choices should keep users in the roleplay mindset. Avoid breaking the fourth wall with generic placeholder text, stock icons, or patterns that feel out-of-universe.

### Technical Foundation
- **Framework**: Bootstrap 5.3 with extensive SCSS customization
- **Fonts**: Rubik (primary), Maven Pro, PT Sans, Inconsolata (mono)
- **Icons**: Font Awesome 7 Free
- **Color tokens**: CSS custom properties with runtime accent color switching (red, blue, green, purple, orange, teal, pink, amber)
- **Border radius scale**: 4px (sm) → 6px (md) → 8px (lg) → 10px (xl)
- **Font size scale**: 0.72rem (xs) → 0.78rem (sm) → 0.82rem (base) → 0.88rem (md)
- **Shadow system**: Three tiers (light, medium, strong) using rgba black
- **Accessibility**: General best practices, high-contrast dark theme

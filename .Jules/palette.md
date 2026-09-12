## 2025-02-18 - Accessibility on Icon-only Header Elements
**Learning:** Icon-only buttons (like menu toggles) and purely visually context-driven inputs (like search bars with placeholders) often lack ARIA attributes in this application.
**Action:** When working on topbar or header components, always ensure that icon-only buttons receive `aria-label` and `title`, their inner `<i>` tags receive `aria-hidden="true"`, and inputs get an explicit `aria-label`.

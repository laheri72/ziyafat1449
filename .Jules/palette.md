## 2024-05-24 - Add ARIA Labels to Icon-Only Buttons
**Learning:** Found that the topbar search input and menu toggle button rely purely on placeholders and icons for visual context, missing `aria-label`s for screen reader accessibility. Also, `aria-hidden="true"` should be on the `<i>` tags for accessibility to prevent them from being read aloud when an `aria-label` is on the parent button.
**Action:** When adding icon-only buttons or search inputs with only placeholders, ensure they have proper `aria-label`s on the button/input and `aria-hidden="true"` on the icon itself.

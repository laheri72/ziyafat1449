## 2024-07-03 - Accessible Icon Buttons in Admin Tables
**Learning:** Icon-only buttons using FontAwesome need semantic meaning for screen readers. Simply hiding the icon (`aria-hidden="true"`) and providing an explicit label (`aria-label`) on the wrapping anchor tag resolves navigation ambiguity. Adding a `title` attribute further assists sighted users with tooltips.
**Action:** Always wrap `fas fa-*` icons in an anchor or button tag with appropriate `aria-label` and `title` attributes when the icon itself is the sole interactive element. Ensure the `<i>` tag is hidden from screen readers.

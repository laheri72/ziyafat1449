## 2024-05-18 - Missing ARIA Labels on Icon-only Buttons
**Learning:** Found an accessibility issue where the sidebar menu toggle button only contains a FontAwesome icon (`<i class="fas fa-bars"></i>`) without an `aria-label` or `title`. This makes it completely invisible and inaccessible to screen reader users.
**Action:** When identifying icon-only buttons, especially global navigation controls, always add `aria-label` for screen readers, `title` for hover tooltips, and `aria-hidden="true"` to the icon element itself.

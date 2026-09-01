## 2024-05-06 - Added ARIA labels to header icons
**Learning:** Found missing ARIA attributes on icon-only buttons (like menu toggle) and decorative icons in the main header. This is a common pattern in the app.
**Action:** Always ensure `aria-label` is present on interactive elements that only have icons, and add `aria-hidden="true"` to decorative icons (like FontAwesome icons inside buttons) to prevent screen reader noise.

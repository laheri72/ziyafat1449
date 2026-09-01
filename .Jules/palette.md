## 2026-06-02 - Accessibility labels for icon-only buttons
**Learning:** Found a recurring pattern in the app's components where FontAwesome icons are used as icon-only interactive elements (buttons/links) without proper ARIA labels, titles, or aria-hidden attributes, causing poor accessibility for screen readers and lacking tooltips for mouse users.
**Action:** Always add 'aria-label', 'title' to the parent element, and 'aria-hidden="true"' to the <i> tag when implementing or updating icon-only interactive elements.

## 2024-05-18 - Missing ARIA labels on Icon-only buttons
**Learning:** Found a recurring pattern where `a` tags containing only an icon (`<i class="fas fa-*"></i>`) and no text are missing `aria-label` and `title` attributes. Also, the inner `<i>` tags are missing `aria-hidden="true"`.
**Action:** When working on this application, always add `aria-label` and `title` attributes to icon-only interactive elements, and `aria-hidden="true"` to the icon elements.

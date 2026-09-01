## 2026-05-22 - Adding context to icon-only buttons
**Learning:** Found multiple instances of icon-only action buttons (Edit, Delete, View) in admin lists that lacked context for screen readers. Using variables like user names and contribution amounts in aria-labels significantly improves accessibility.
**Action:** When adding or fixing action buttons in list views or tables, always use the row's primary data point (e.g. name, date, amount) in the `aria-label` alongside the action verb, and hide the generic icon from screen readers.

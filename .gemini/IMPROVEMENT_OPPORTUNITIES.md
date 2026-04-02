# Improvement Opportunities

## Technical Debt
- **Password Hashing:** Implement secure hashing immediately.
- **Environment Configuration:** Move hardcoded credentials and constants (like exchange rates and targets) to a `.env` file or a `settings` table in the DB.
- **Code Duplication:** Several pages (e.g., the different category reports) likely share similar logic that could be abstracted into classes or shared functions.
- **Modernization:** Consider migrating from procedural `mysqli` to PDO for better abstraction and security.
- **Frontend Assets:** Use a proper build tool (like Vite) for JS/CSS if the complexity grows, rather than including scripts directly.

## UI/UX Enhancements
- **Interactive Reports:** Use a charting library (like Chart.js or ApexCharts) for more visual representations of spiritual and financial progress.
- **Mobile Optimization:** Ensure all tracking pages are fully responsive for users logging data on the go.
- **Real-time Feedback:** Use more AJAX/Fetch for form submissions to avoid full page reloads.

## Feature Ideas
- **Email Notifications:** Send automated receipts for contributions or weekly spiritual progress summaries.
- **Bulk Import/Export:** Allow admins to import user data or contribution lists via CSV/Excel.
- **Goal Setting:** Let users set personal targets for spiritual activities.
- **Audit Logs:** Implement a more robust `activity_log` for admin actions (who edited which contribution and when).

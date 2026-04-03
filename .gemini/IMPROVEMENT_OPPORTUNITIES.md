# Improvement Opportunities

## Completed ✅
- **Real-time Feedback:** Site-wide implementation of AJAX/Fetch for all tracking and data entry.
- **Mobile Optimization:** Fully responsive navigation and table structures.
- **Email Notifications:** Implemented Super Admin Broadcast Center for personalized reminders.
- **Admin Utilities:** Added Super Admin password reset functionality.

## Technical Debt (High Priority)
- **Password Hashing:** **CRITICAL.** Implement `password_hash()` immediately to replace plain-text storage.
- **Environment Configuration:** Move SMTP and Database credentials to a `.env` file.
- **Code Duplication:** Refactor repeated AJAX handling patterns into a shared controller or class.

## Future Enhancements
- **Automated SMTP Bounce Handling:** Logic to automatically mark emails as "Invalid" in the `users` table if they bounce multiple times.
- **Interactive Charts:** Use Chart.js for the visual "Waterfall" and Amali progress on the dashboard.
- **Activity Log:** Expand `mail_sent_logs` into a general `admin_audit_log` for tracking all sensitive changes.

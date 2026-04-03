# System Architecture

## Technology Stack
- **Backend:** PHP 7.2+
- **Database:** MariaDB (InnoDB engine)
- **Mailing:** PHPMailer via SMTP (Hostinger)
- **Frontend:** HTML5, CSS3 (Vanilla), JavaScript (Vanilla), jQuery (Select2).
- **Icons:** FontAwesome 6.4.0

## Request Flow (Mailing Example)
1. **Admin Action:** Create campaign in `broadcast_center.php`.
2. **Batch Request:** Admin clicks "Send Batch" -> AJAX request to `ajax_broadcast.php`.
3. **Data Fetching:** Script pulls users not yet logged in `mail_sent_logs` for that campaign.
4. **Processing:** Loop through batch -> Calculate personalized Amali/Finance stats -> Construct HTML body.
5. **Delivery:** Hand off to `mailer_helper.php` -> PHPMailer -> Hostinger SMTP.
6. **Logging:** Record success/fail in `mail_sent_logs` -> Return JSON report to UI.

## Responsive Strategy
- **Viewport:** Mobile-first approach using CSS variables for spacing.
- **Tables:** Custom `@media` queries transform `<table>` structures into stackable cards on devices < 768px.
- **Sidebar:** State persisted in `localStorage` to prevent layout flicker across sessions.

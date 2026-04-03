# Dependency Graph

## Server-Side
- **mysqli:** Core database extension.
- **PHPMailer:** Located in `includes/PHPMailer/`. Handles SMTP communication.
- **datetime:** Used for fiscal year and payment period calculations.

## Client-Side
- **FontAwesome 6.4.0:** Icons (CDN).
- **Inter Font:** Typography (Google Fonts).
- **Select2:** Used for searchable user dropdowns in Admin (CDN).
- **SweetAlert2:** Used for confirmation dialogs (CDN).

## Internal Flow
- `includes/functions.php` -> Essential for every page (Auth & Progress).
- `includes/mailer_helper.php` -> Required by `ajax_broadcast.php` and debug scripts.
- `assets/js/script.js` -> Centralized sidebar and UI initialization.

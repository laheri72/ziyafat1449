# Routes and Pages

## Public Routes
- `/index.php`: Root entry point, redirects based on role.
- `/auth/login.php`: User/Admin authentication.
- `/auth/logout.php`: Destroys session and redirects to login.
- `/auth/register.php`: New user registration.

## User Routes (`/user`)
- `index.php`: User dashboard with optimized mobile grid navigation.
- `quran_tracking.php`: AJAX-enabled multi-select Juz tracking.
- `dua_tracking.php`: Interface to log Dua counts via AJAX.
- `dua_history.php`: View history of recorded Duas.
- `book_transcription.php`: AJAX-enabled book transcription management.
- `surat_finance_report.php`: Mobile-optimized financial report for Surat users.
- `profile.php`: View/Edit user profile information.
- `ajax_dua_entry.php`: API endpoint for logging Dua counts.
- `ajax_quran_tracking.php`: API endpoint for batch Juz updates.
- `ajax_book_transcription.php`: API endpoint for book progress.

## Admin Routes (`/admin`)
- `index.php`: Admin dashboard with Broadcast Center quick link.
- `broadcast_center.php`: Campaign management and batch mailing system (Super Admin only).
- `view_users.php`: Mobile-responsive user list with search.
- `user_details.php`: Detailed view of a specific user's progress.
- `add_user.php`: Form to create new users.
- `edit_user.php`: User editing with Super Admin password reset button.
- `reports.php`: Financial reports across different categories.
- `amali_reports.php`: Detailed spiritual progress reports.
- `add_contribution.php`: Record new financial payments via AJAX.
- `manage_books.php`: CRUD for `books_master`.
- `manage_duas.php`: CRUD for `duas_master`.
- `ajax_broadcast.php`: API endpoint for batch email dispatch.
- `ajax_add_contribution.php`: API endpoint for financial records.

## Internal / Partial Paths
- `includes/header.php`: Common nav, responsive sidebar toggle, and Toast system.
- `includes/footer.php`: Closing tags and script inclusions.
- `includes/mailer_helper.php`: Core logic for PHPMailer and HTML templates.
- `config/mail.php`: SMTP server settings.

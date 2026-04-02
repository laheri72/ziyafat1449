# Routes and Pages

## Public Routes
- `/index.php`: Root entry point, redirects based on role.
- `/auth/login.php`: User/Admin authentication.
- `/auth/logout.php`: Destroys session and redirects to login.
- `/auth/register.php`: New user registration.

## User Routes (`/user`)
- `index.php`: User dashboard with Amali summary and contribution status.
- `quran_tracking.php`: Interface to log completion of Quran Juz.
- `dua_tracking.php`: Interface to log Dua counts.
- `dua_history.php`: View history of recorded Duas.
- `book_transcription.php`: Select books and track transcription progress.
- `surat_finance_report.php`: Detailed financial report for the user.
- `profile.php`: View/Edit user profile information.
- `amali_janib.php`: (TBD - Needs further investigation into specific content).
- `ajax_dua_entry.php`: API endpoint for logging Dua counts.

## Admin Routes (`/admin`)
- `index.php`: Admin dashboard with system-wide stats.
- `view_users.php`: List of all registered users.
- `user_details.php`: Detailed view of a specific user's progress.
- `add_user.php`: Form to create new users.
- `edit_user.php`: Form to modify existing user details.
- `delete_user.php`: Action to remove a user.
- `reports.php`: Financial reports across different categories.
- `amali_reports.php`: Detailed spiritual progress reports.
- `add_contribution.php`: Record new financial payments.
- `edit_contribution.php`: Modify existing financial records.
- `delete_contribution.php`: Action to remove a contribution.
- `manage_books.php`: CRUD for `books_master`.
- `manage_duas.php`: CRUD for `duas_master`.

## Internal / Partial Paths
- `includes/header.php`: Common navigation and meta tags.
- `includes/footer.php`: Common scripts and copyright info.
- `user/partials/tracking_card.php`: Reusable UI card for tracking metrics.

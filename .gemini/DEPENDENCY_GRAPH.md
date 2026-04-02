# Dependency Graph

## Server-Side (PHP)
- **mysqli:** Standard PHP extension for database interaction.
- **session:** Core PHP session management.
- **datetime:** For fiscal year and payment period calculations.

## Client-Side (Frontend)
- **FontAwesome 6.5.0:** Iconography (CDN).
- **Google Fonts (Inter):** Typography (CDN).
- **jQuery:** (Likely used for legacy AJAX or DOM manipulation).

## Internal Dependencies
- `includes/functions.php` -> Used by almost all pages.
- `config/database.php` -> Required by `includes/functions.php` or directly by pages.
- `includes/header.php` / `includes/footer.php` -> UI wrapper for all pages.

## Data Flow Dependencies
- **Reports** -> Depend on `contributions`, `users`, `dua_entries`, `quran_progress`, and `book_transcription`.
- **User Dashboard** -> Depends on `users`, `contributions`, and aggregated Amali data from `includes/functions.php`.

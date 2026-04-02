# Components and Reusable Logic

## UI Components
- **`includes/header.php`:**
  - Standard HTML head with meta tags and CSS links.
  - Navigation bar (dynamic based on role).
  - Profile dropdown.
- **`includes/footer.php`:**
  - Closing body/html tags.
  - Standard JS links (jQuery, FontAwesome).
  - Copyright and footer links.
- **`user/partials/tracking_card.php`:**
  - Reusable visual card for the user dashboard to show metrics like "Quran Progress" or "Dua Count".

## Core Logic (`includes/functions.php`)
This file is the engine of the application, containing:
- **Session Helpers:** `init_session()`, `is_logged_in()`, `is_admin()`.
- **Security Helpers:** `clean_input()`, `generate_csrf_token()`, `verify_csrf_token()`.
- **Data Distribution:** `get_user_contributions()` implements the **Waterfall Logic** for finance.
- **Activity Tracking:** `get_quran_progress()`, `get_dua_progress()`, `get_book_progress()`.
- **Aggregation:** `get_amali_summary()` provides a unified view of all spiritual metrics.
- **Formatting:** `format_currency()`, `calculate_percentage()`.

## Assets
- **`assets/css/style.css`:** Main application styling.
- **`assets/css/login.css`:** Specialized styling for the auth pages.
- **`assets/js/script.js`:** General interactivity and AJAX handlers.

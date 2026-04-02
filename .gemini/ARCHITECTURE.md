# System Architecture

## Technology Stack
- **Backend:** PHP 7.2+ (Procedural and basic Object-Oriented patterns)
- **Database:** MariaDB / MySQL
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla), jQuery (in some parts), React/TypeScript (limited usage detected in `src/App.reports.tsx`)
- **Styling:** Custom CSS (Vanilla CSS preferred as per guidelines)
- **Icons:** FontAwesome 6.5.0

## Directory Structure & Responsibilities
- `/admin`: Contains admin-only pages for management and reporting.
- `/user`: Contains user-specific tracking and dashboard pages.
- `/auth`: Handles login, logout, and registration flows.
- `/config`: Centralized configuration (e.g., `database.php`).
- `/includes`: Reusable UI components (`header.php`, `footer.php`) and core logic (`functions.php`).
- `/assets`: Static assets (CSS, JS, Images).
- `/database`: SQL schema and seed data.
- `/src`: Modern frontend source (React/TSX) possibly for specialized reporting views.

## Request Flow Map
1. **User Request:** A user accesses a page (e.g., `user/index.php`).
2. **Session Check:** `require_login()` or `require_admin()` in `includes/functions.php` is called via `auth/login.php` logic or direct include.
3. **Configuration:** `config/database.php` establishes DB connection `$conn`.
4. **Data Retrieval:** Page calls functions from `includes/functions.php` which execute SQL queries against MariaDB.
5. **UI Rendering:** Page includes `includes/header.php`, renders its specific content using PHP, and ends with `includes/footer.php`.
6. **Response:** Server sends complete HTML/CSS/JS to the client browser.

## Logic Flow (Amali Entry Example)
- **Page:** `user/ajax_dua_entry.php`
- **Flow:** User submits form -> AJAX request -> Page validates session -> Sanitizes input -> Updates `dua_entries` table -> Returns success/error JSON response.

## Logic Flow (Finance Waterfall Example)
- **Function:** `get_user_contributions($conn, $user_id)`
- **Flow:** Fetch total INR from `contributions` -> Calculate distribution:
    - `tasea_paid = min(total, 66000)`
    - `ashera_paid = min(remaining, 97000)`
    - `hadi_paid = min(remaining, 127000)`
- **Return:** Structured array with distributed amounts.

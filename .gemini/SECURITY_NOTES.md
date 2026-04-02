# Security Notes

## Critical Risks

### 1. Plain-text Passwords
- **Discovery:** `auth/login.php` compares input passwords directly with the value in `users.password`.
- **Impact:** If the database is compromised, all user passwords are exposed.
- **Recommendation:** Migration to `password_hash()` and `password_verify()`.

### 2. SQL Injection Potential
- **Discovery:** While many queries use `bind_param`, a thorough audit of all PHP files (especially `admin/` and `user/`) is needed to ensure no user-supplied data is concatenated directly into SQL strings.
- **Recommendation:** Enforce the use of Prepared Statements for all database interactions.

### 3. Lack of CSRF Protection
- **Discovery:** `includes/functions.php` contains CSRF helper functions, but their usage across all forms (e.g., in `admin/add_contribution.php`) needs verification.
- **Recommendation:** Implement and verify CSRF tokens on every state-changing POST request.

### 4. Direct File Access
- **Discovery:** The `includes/` and `config/` folders do not appear to have an `.htaccess` or `index.php` to prevent directory listing or direct file access.
- **Recommendation:** Add `Deny from all` in `.htaccess` for internal directories.

### 5. Sensitive Data Exposure
- **Discovery:** `config/database.php` contains hardcoded database credentials.
- **Recommendation:** Use environment variables or a `.env` file (outside the web root) to store sensitive configuration.

## Minor Risks
- **Session Security:** Ensure `session.cookie_httponly` and `session.cookie_secure` (if on HTTPS) are set.
- **Input Sanitization:** While `clean_input()` exists, use of `filter_var()` for specific types (like email or numbers) is preferred.
- **Error Handling:** Ensure `display_errors` is off in production to prevent path disclosure.
